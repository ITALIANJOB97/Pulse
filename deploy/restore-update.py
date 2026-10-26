#!/usr/bin/env python3
"""Read/restore an update snapshot as www-data, while all application writers are stopped."""
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import sqlite3
import sys
import tempfile
import zipfile

def verify(archive, scratch):
    with zipfile.ZipFile(archive) as z:
        names = [i.filename for i in z.infolist()]
        if len(names) != len(set(names)) or len(names) > 100000:
            raise ValueError("Duplicate or excessive backup entries")
        for name in names:
            if not re.fullmatch(r"(manifest\.json|pulse\.sqlite|media/[a-f0-9]{40}\.jpg)", name):
                raise ValueError("Unsafe backup path")
        if sum(i.file_size for i in z.infolist()) > 2 * 1024**3:
            raise ValueError("Backup exceeds the 2 GiB restore limit")
        if z.getinfo("manifest.json").file_size > 65536 or z.testzip():
            raise ValueError("Backup CRC check failed")
        manifest = json.loads(z.read("manifest.json"))
        if manifest.get("app") != "pulse-dietikon":
            raise ValueError("Wrong backup application")
        database = scratch / "pulse.sqlite"
        with z.open("pulse.sqlite") as src, open(database, "xb") as dst:
            shutil.copyfileobj(src, dst)
        with open(database,"rb") as dbfile:
            dbhash = hashlib.file_digest(dbfile,"sha256").hexdigest()
        if dbhash != manifest.get("database_sha256"):
            raise ValueError("Database checksum mismatch")
        with sqlite3.connect(database.as_uri() + "?mode=ro", uri=True) as db:
            if db.execute("PRAGMA integrity_check").fetchone()[0] != "ok" or db.execute("SELECT COUNT(*) FROM admins").fetchone()[0] < 1:
                raise ValueError("Invalid backup database")
            files = [row[0] for row in db.execute("SELECT filename FROM media")]
        for name in files:
            if not re.fullmatch(r"[a-f0-9]{40}\.jpg", name) or "media/" + name not in names:
                raise ValueError("Referenced image missing")
        return database, files

def restore(archive, data):
    with tempfile.TemporaryDirectory(prefix="pulse-restore-") as directory:
        database, names = verify(archive, Path(directory))
        if (data / "pulse.sqlite").is_symlink() or (data / "media").is_symlink():
            raise ValueError("Unexpected data symlink")
        # Rollback happens before writes are reopened. Restore changed or missing images
        # atomically; never follow a link or erase files created by other versions.
        with zipfile.ZipFile(archive) as z:
            changed = []
            for name in names:
                target = data / "media" / name
                if target.is_symlink():
                    raise ValueError("Unexpected image symlink")
                if target.exists():
                    with z.open("media/" + name) as src, open(target, "rb") as dst:
                        if hashlib.file_digest(src, "sha256").digest() == hashlib.file_digest(dst, "sha256").digest():
                            continue
                changed.append(name)
            for name in changed:
                fd, temporary = tempfile.mkstemp(prefix="restore-", suffix=".tmp", dir=data / "media")
                try:
                    with z.open("media/" + name) as src, os.fdopen(fd, "wb") as dst:
                        shutil.copyfileobj(src, dst)
                        dst.flush()
                        os.fsync(dst.fileno())
                    os.chmod(temporary, 0o640)
                    os.replace(temporary, data / "media" / name)
                finally:
                    Path(temporary).unlink(missing_ok=True)
        with sqlite3.connect(database) as source, sqlite3.connect(data / "pulse.sqlite") as target:
            source.backup(target)
            if target.execute("PRAGMA integrity_check").fetchone()[0] != "ok":
                raise ValueError("Restored database check failed")
        # Keep sessions; this is a rollback of code/schema with the same accounts.
        print("Database and media restored.")

def preferences(data):
    path = data / "pulse.sqlite"
    if path.is_symlink():
        raise ValueError("Unexpected database symlink")
    with sqlite3.connect(path.as_uri() + "?mode=ro", uri=True) as db:
        if db.execute("SELECT COUNT(*) FROM admins").fetchone()[0] == 0:
            print("null")
            return
        keys = ("update_auto", "update_hour", "update_postpone_until", "update_requested", "update_retry", "update_cancel", "update_skip", "update_install")
        result = {}
        for key in keys:
            row = db.execute("SELECT value FROM settings WHERE key=?", (key,)).fetchone()
            result[key] = json.loads(row[0]) if row and len(row[0]) < 1024 else None
        print(json.dumps(result))
if __name__ == "__main__":
    try:
        if os.geteuid() == 0:
            raise ValueError("This helper must run as www-data, never root")
        if len(sys.argv) == 3 and sys.argv[1] == "preferences":
            preferences(Path(sys.argv[2]).resolve())
        elif len(sys.argv) == 4 and sys.argv[1] in ("verify", "restore"):
            mode, archive, data = sys.argv[1], Path(sys.argv[2]), Path(sys.argv[3])
            if mode == "restore":
                restore(archive, data)
            else:
                with tempfile.TemporaryDirectory(prefix="pulse-verify-") as directory:
                    verify(archive, Path(directory))
                print("Backup verified.")
        else:
            raise ValueError("Invalid helper command")
    except Exception as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
