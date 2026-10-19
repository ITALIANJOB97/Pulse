#!/usr/bin/env python3
"""PULSE trusted deployment service. Python standard library; Mint 22/PHP 8.3.
Installed outside the releases. Downloaded PHP is only executed as www-data.
"""
from __future__ import annotations
import argparse
import base64
import datetime as dt
import fcntl
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import pwd
import re
import shutil
import sqlite3
import stat
import subprocess
import sys
import tempfile
import time
import urllib.parse
import urllib.request
import zipfile
from zoneinfo import ZoneInfo

UPDATER_VERSION = 3
MAX_ZIP = 100 * 1024 * 1024
MAX_EXPANDED = 300 * 1024 * 1024
REPOSITORY = "ITALIANJOB97/Pulse"
ORIGIN = "https://github.com/" + REPOSITORY + "/releases/download/"
LATEST_URL = "https://api.github.com/repos/" + REPOSITORY + "/releases/latest"
LEGACY_CONFIG = {"schema": 1, "origin": "https://www.luigipranzo.com/myfile/Pulse/"}
PRIVATE_GITHUB_CONFIG = {"schema": 2, "repository": REPOSITORY, "private": True}
GITHUB_CONFIG = {"schema": 2, "repository": REPOSITORY, "private": False}
TZ = ZoneInfo("Europe/Zurich")
VERSION_RE = r"\d+\.\d+\.\d+"
BACKUP_RE = r"(?:manual|preupdate)-\d{8}-\d{6}-[a-f0-9]{6}\.zip"
APP_FILES = {"VERSION", "README.md", "CHANGELOG.md", ".gitignore", "CONTENTS.sha256",
             "LEGGIMI_LINUX_MINT.txt", "INSTALLAZIONE_LINUX_MINT.txt",
             "INSTALLAZIONE_RASPBERRY.txt", "DIAGNOSI_LINUX_MINT.txt",
             "AGGIORNAMENTO_LINUX_MINT.txt", "PUBBLICAZIONE_BLOG.txt", "PUBBLICAZIONE_GITHUB.txt", ".gitattributes"}
APP_DIRS = {"app", "bin", "config", "public", "deploy", "docs", "tests"}
def now():
    return dt.datetime.now(dt.timezone.utc)
def stamp():
    return now().isoformat(timespec="seconds")
def version(value):
    if not isinstance(value, str) or not re.fullmatch(VERSION_RE, value):
        raise ValueError("Invalid version")
    return tuple(map(int, value.split(".")))
def digest(path):
    with open(path, "rb") as handle:
        return hashlib.file_digest(handle, "sha256").hexdigest()
def atomic_json(path, data):
    path = Path(path)
    temp = path.with_name(path.name + ".tmp")
    with open(temp, "w", encoding="utf-8") as handle:
        os.fchmod(handle.fileno(), 0o640)
        json.dump(data, handle, ensure_ascii=False, indent=2)
        handle.flush()
        os.fsync(handle.fileno())
    os.replace(temp, path)
    fd = os.open(path.parent, os.O_DIRECTORY)
    try:
        os.fsync(fd)
    finally:
        os.close(fd)
def read_json(path, default=None):
    try:
        if Path(path).stat().st_size > 131072:
            raise ValueError("JSON too large")
        return json.loads(Path(path).read_text(encoding="utf-8"))
    except FileNotFoundError:
        return default
def allowed_url(url, origin=ORIGIN):
    # Initial requests are bound to one repository and versioned release assets.
    return (isinstance(url, str) and origin == ORIGIN and bool(re.fullmatch(
        re.escape(ORIGIN) + r"v" + VERSION_RE + r"/(?:stable\.json(?:\.sig)?|pulse-" + VERSION_RE + r"\.zip)", url)))

def github_api_asset(url):
    return isinstance(url, str) and bool(re.fullmatch(
        r"https://api\.github\.com/repos/" + re.escape(REPOSITORY) + r"/releases/assets/[1-9][0-9]*", url))

def github_asset_cdn(url):
    if not isinstance(url, str) or re.search(r"[\\\s]", url):
        return False
    parsed = urllib.parse.urlsplit(url)
    return (parsed.scheme == "https" and parsed.netloc == "release-assets.githubusercontent.com"
            and parsed.path.startswith("/github-production-release-asset/") and not parsed.fragment)

class TrustedRedirect(urllib.request.HTTPRedirectHandler):
    max_redirections = 4
    max_repeats = 2
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        # GitHub sends release assets to its signed CDN URL. No arbitrary redirects.
        if not (allowed_url(req.full_url) or github_api_asset(req.full_url) or github_asset_cdn(req.full_url)) or not github_asset_cdn(newurl):
            raise ValueError("Untrusted GitHub download redirect")
        redirected = super().redirect_request(req, fp, code, msg, headers, newurl)
        if redirected is not None:
            redirected.remove_header("Authorization")
            redirected.remove_header("Cookie")
        return redirected

def download(url, target, limit, origin=ORIGIN, token=None):
    if url != LATEST_URL and not github_api_asset(url) and not allowed_url(url, origin):
        raise ValueError("Release URL is outside the trusted repository")
    opener = urllib.request.build_opener(TrustedRedirect)
    request = urllib.request.Request(url, headers={"User-Agent": "PULSE-Updater/3", "Cache-Control": "no-cache",
        "Accept": "application/vnd.github+json" if url == LATEST_URL else "application/octet-stream"})
    if token is not None:
        if url != LATEST_URL and not github_api_asset(url):
            raise ValueError("Credentials may only be sent to the repository API")
        request.add_header("Authorization", "Bearer " + token)
    start = time.monotonic()
    with opener.open(request, timeout=20) as response, open(target, "wb") as out:
        if response.status != 200:
            raise ValueError("Unexpected HTTP response")
        count = 0
        while chunk := response.read(65536):
            count += len(chunk)
            if count > limit or time.monotonic() - start > 300:
                raise ValueError("Download exceeds size/time limit")
            out.write(chunk)
        out.flush()
        os.fsync(out.fileno())
    return count

def release_asset_urls(data):
    if not isinstance(data, dict) or data.get("draft") is not False or data.get("prerelease") is not False:
        raise ValueError("Only published stable GitHub releases are supported")
    tag = data.get("tag_name", "")
    if not isinstance(tag, str) or not re.fullmatch("v" + VERSION_RE, tag):
        raise ValueError("Invalid GitHub release tag")
    assets = data.get("assets")
    if not isinstance(assets, list):
        raise ValueError("Missing GitHub release assets")
    urls = {}
    for name in ("stable.json", "stable.json.sig", "pulse-" + tag[1:] + ".zip"):
        matches = [a for a in assets if isinstance(a, dict) and a.get("name") == name]
        expected = ORIGIN + tag + "/" + name
        if len(matches) != 1 or matches[0].get("state") != "uploaded" or matches[0].get("browser_download_url") != expected:
            raise ValueError("Missing or invalid GitHub release asset: " + name)
        api_url = matches[0].get("url")
        if not github_api_asset(api_url):
            raise ValueError("Invalid GitHub asset API address")
        urls[name] = api_url
    return tag[1:], urls

def validate_manifest(data, origin=ORIGIN, clock=None):
    clock = clock or now()
    if data.get("app") != "pulse-dietikon" or data.get("schema") != 1 or data.get("channel") != "stable":
        raise ValueError("Wrong application or manifest format")
    version(data.get("version"))
    version(data.get("min_from"))
    if type(data.get("min_updater")) is not int or type(data.get("db_schema")) is not int or not 1 <= data["db_schema"] <= 1000:
        raise ValueError("Invalid requirements")
    version(data.get("php_min"))
    if data.get("platform") != "linuxmint22-amd64":
        raise ValueError("Unsupported platform")
    for key in ("published_at", "expires_at"):
        parsed = dt.datetime.fromisoformat(data[key].replace("Z", "+00:00"))
        if parsed.tzinfo is None:
            raise ValueError("Missing time zone")
        if key == "expires_at" and parsed <= clock:
            raise ValueError("Expired release manifest")
        if key == "published_at" and parsed > clock + dt.timedelta(minutes=10):
            raise ValueError("Release from the future; check system clock")
    if type(data.get("size")) is not int or not 0 < data["size"] <= MAX_ZIP:
        raise ValueError("Invalid package size")
    if not re.fullmatch(r"[a-f0-9]{64}", data.get("sha256", "")) or not allowed_url(data.get("url"), origin):
        raise ValueError("Invalid package URL or checksum")
    if data["url"] != ORIGIN + "v" + data["version"] + "/pulse-" + data["version"] + ".zip":
        raise ValueError("Package URL does not match release version")
    notes = data.get("notes")
    if not isinstance(notes, dict) or any(not isinstance(notes.get(lang), str) or len(notes[lang]) > 2000 for lang in ("en", "de", "fr")):
        raise ValueError("Invalid release notes")
    return data
def safe_member(name):
    parts = PurePosixPath(name).parts
    return bool(parts) and not name.startswith("/") and "\\" not in name and ":" not in name and all(p not in (".", "..", "") for p in name.split("/"))
def validate_tree(root):
    """Verify exact source manifest, with no symlinks, secrets, or runtime data."""
    root = Path(root)
    lines = (root / "CONTENTS.sha256").read_text(encoding="utf-8").splitlines()
    expected = {}
    for line in lines:
        match = re.fullmatch(r"([a-f0-9]{64})  (.+)", line)
        if not match:
            raise ValueError("Invalid source manifest")
        sha, name = match.groups()
        if not safe_member(name) or name in expected or name == "CONTENTS.sha256":
            raise ValueError("Unsafe or duplicate source entry")
        if name == "storage/.gitkeep":
            pass
        elif name not in APP_FILES and PurePosixPath(name).parts[0] not in APP_DIRS:
            raise ValueError("Unexpected source path")
        if name == "config/local.php" or any(x.startswith(".") for x in PurePosixPath(name).parts if x not in (".gitignore", ".gitkeep", ".gitattributes")):
            raise ValueError("Local files must not be distributed")
        expected[name] = sha
    actual = set()
    for file in root.rglob("*"):
        if file.is_symlink():
            raise ValueError("Source contains symbolic links")
        if file.is_file():
            name = file.relative_to(root).as_posix()
            # The support wrapper may have created reports next to an extracted package.
            if name.startswith("diagnostica/"):
                continue
            if name != "CONTENTS.sha256":
                actual.add(name)
    if actual != set(expected):
        raise ValueError("Package files do not match CONTENTS.sha256")
    for name, sha in expected.items():
        if digest(root / name) != sha:
            raise ValueError("Package checksum mismatch: " + name)
    for name in ("VERSION", "app/bootstrap.php", "public/index.php", "bin/console.php"):
        if name not in expected:
            raise ValueError("Incomplete application package")
    return (root / "VERSION").read_text().strip()
def extract_release(archive, destination):
    destination.mkdir(mode=0o750)
    with zipfile.ZipFile(archive) as package:
        entries = package.infolist()
        if len(entries) > 4000:
            raise ValueError("Too many package entries")
        prefix = None
        seen = set()
        total = 0
        for item in entries:
            # Publisher emits regular files, no directory or symlink entries.
            mode = item.external_attr >> 16
            if not safe_member(item.filename) or item.is_dir() or (stat.S_IFMT(mode) not in (0, stat.S_IFREG)):
                raise ValueError("Unsafe ZIP entry")
            parts = item.filename.split("/", 1)
            if len(parts) != 2 or (prefix is not None and prefix != parts[0]):
                raise ValueError("Package requires one top-level directory")
            prefix = parts[0]
            name = parts[1]
            if name in seen:
                raise ValueError("Duplicate ZIP entry")
            seen.add(name)
            total += item.file_size
            if total > MAX_EXPANDED or item.file_size > MAX_ZIP:
                raise ValueError("Expanded package exceeds limit")
            target = destination / name
            target.parent.mkdir(parents=True, exist_ok=True, mode=0o750)
            with package.open(item) as source, open(target, "xb") as out:
                shutil.copyfileobj(source, out)
            os.chmod(target, 0o640)
    return validate_tree(destination)
def next_window(clock, hour, postpone_until=0):
    local = clock.astimezone(TZ)
    allowed = dt.datetime.fromtimestamp(postpone_until, dt.timezone.utc) if postpone_until else clock
    start = local.replace(hour=hour, minute=0, second=0, microsecond=0)
    if start <= local < start + dt.timedelta(hours=1) and clock >= allowed:
        return clock
    if start <= local:
        start += dt.timedelta(days=1)
    while start.astimezone(dt.timezone.utc) < allowed:
        start += dt.timedelta(days=1)
    return start.astimezone(dt.timezone.utc)

class Updater:
    def __init__(self, state=Path("/var/lib/pulse-updater"), base=Path("/opt/pulse"), data=Path("/var/lib/pulse"), config=Path("/etc/pulse/updater.json"), control=Path("/usr/local/lib/pulse")):
        self.state, self.base, self.data, self.config, self.control = map(Path, (state, base, data, config, control))
        self.status_file = self.state / "status.json"
        self.journal = self.state / "transaction.json"
        self.maintenance = self.state / "maintenance"
        self.php = "/usr/bin/php8.3"
        self.account = pwd.getpwnam("www-data")
    def run(self, args, as_web=False, timeout=300, check=True):
        env = {"PATH": "/usr/sbin:/usr/bin:/sbin:/bin", "PULSE_STORAGE": str(self.data), "PULSE_UPDATER_STATE": str(self.state), "PULSE_UPDATER_CONFIG": str(self.config), "PULSE_UPDATE_WORKER": "1", "LANG": "C.UTF-8"}
        if as_web:
            args = ["/usr/sbin/runuser", "-u", "www-data", "--", "env", *[f"{k}={v}" for k, v in env.items()], *map(str, args)]
        result = subprocess.run(list(map(str, args)), env=env, text=True, capture_output=True, timeout=timeout)
        if check and result.returncode:
            # No credentials or raw database output in the public state.
            raise RuntimeError("Command failed: " + Path(str(args[-1])).name[:60])
        return result
    def systemctl(self, *args, check=True):
        return self.run(["/usr/bin/systemctl", *args], timeout=240, check=check)
    def prepare(self):
        for directory in (self.state, self.state / "backups", self.state / "downloads"):
            if directory.is_symlink():
                raise ValueError("Unexpected state symlink")
            directory.mkdir(parents=True, exist_ok=True, mode=0o750)
            os.chown(directory, 0, self.account.pw_gid)
            os.chmod(directory, 0o750)
        for parent in (self.base, self.base / "releases"):
            if parent.is_symlink() or parent.stat().st_uid != 0 or parent.stat().st_mode & 0o022:
                raise ValueError("Release directories must be root-owned and not writable by other users")
    def save_status(self, **changes):
        state = read_json(self.status_file, {})
        state.update(changes)
        state["updated_at"] = stamp()
        atomic_json(self.status_file, state)
        os.chown(self.status_file, 0, self.account.pw_gid)
        return state
    def current(self):
        current = (self.base / "current").resolve(strict=True)
        if current.parent != (self.base / "releases").resolve() or not re.fullmatch(VERSION_RE, current.name):
            raise ValueError("Unexpected current release path")
        if current.stat().st_uid != 0 or current.stat().st_mode & 0o022:
            raise ValueError("Current release is not protected")
        if version((current / "VERSION").read_text().strip()) != version(current.name):
            raise ValueError("Release version does not match directory")
        return current
    def preferences(self):
        database = self.data / "pulse.sqlite"
        if database.is_symlink() or not database.is_file():
            raise ValueError("Expected an existing PULSE database")
        out = json.loads(self.run(["/usr/bin/python3", self.control / "restore-update.py", "preferences", self.data], as_web=True).stdout)
        if out is None:
            return None
        hour = out["update_hour"]
        return {"auto": out["update_auto"] is not False, "hour": hour if type(hour) is int and 0 <= hour <= 23 else 3,
                "postpone": out["update_postpone_until"] if type(out["update_postpone_until"]) is int and 0 <= out["update_postpone_until"] < 4102444800 else 0,
                "request": out["update_requested"] if isinstance(out["update_requested"], str) and len(out["update_requested"]) <= 64 else "",
                "retry": out["update_retry"] if isinstance(out["update_retry"], str) and len(out["update_retry"]) <= 64 else ""}
    def configuration(self):
        for path in (self.config, self.control / "release-public.key"):
            info = path.stat()
            if path.is_symlink() or info.st_uid != 0 or info.st_mode & 0o022:
                raise ValueError("Untrusted updater configuration")
        config = read_json(self.config)
        # Repository changes require an operator-run upgrade of the trusted service.
        if config not in (GITHUB_CONFIG, PRIVATE_GITHUB_CONFIG):
            raise ValueError("Unsupported trusted update source")
        return config
    def github_token(self):
        # Public mode must ignore any stale token left by a private installation.
        if self.configuration() == GITHUB_CONFIG:
            return None
        path = self.config.parent / "github-token"
        if not path.exists():
            raise ValueError("GitHub credential missing. Run deploy/configure-github-mint.py with sudo.")
        info = path.stat()
        if path.is_symlink() or not stat.S_ISREG(info.st_mode) or info.st_uid != 0 or info.st_mode & 0o077 or info.st_size > 256:
            raise ValueError("GitHub credential must be a root-owned regular file with mode 600")
        token = path.read_text(encoding="ascii").strip()
        if not re.fullmatch(r"[A-Za-z0-9_]{20,255}", token):
            raise ValueError("Invalid GitHub credential format")
        return token
    def download_package(self, release, target):
        metadata = read_json(self.state / "downloads/github-release.json")
        expected_version, assets = release_asset_urls(metadata)
        if expected_version != release["version"]:
            raise ValueError("GitHub package version differs from signed manifest")
        download(assets["pulse-" + expected_version + ".zip"], target, release["size"], token=self.github_token())
    def signed_manifest(self, fresh=True):
        self.configuration()
        manifest = self.state / "downloads/stable.json"
        signature = self.state / "downloads/stable.json.sig"
        expected_version = None
        if fresh:
            metadata = self.state / "downloads/github-release.json"
            download(LATEST_URL, metadata, 131072, token=self.github_token())
            expected_version, assets = release_asset_urls(read_json(metadata))
            for name, target, limit in (("stable.json", manifest, 65536), ("stable.json.sig", signature, 256)):
                tmp = target.with_suffix(target.suffix + ".partial")
                download(assets[name], tmp, limit, token=self.github_token())
                os.replace(tmp, target)
        self.run([self.php, self.control / "verify-release.php", manifest, signature, self.control / "release-public.key"])
        release = validate_manifest(read_json(manifest))
        if expected_version is not None and release["version"] != expected_version:
            raise ValueError("GitHub tag and signed manifest version differ")
        return release
    def verify_package(self, release, package):
        if package.is_symlink() or package.stat().st_size != release["size"] or digest(package) != release["sha256"]:
            raise ValueError("Package integrity check failed")
    def make_backup(self, old):
        # Use the installed version's own consistent SQLite/media backup routine.
        result = self.run([self.php, old / "bin/console.php", "backup", "manual"], as_web=True, timeout=600)
        name = result.stdout.strip().splitlines()[-1]
        if not re.fullmatch(BACKUP_RE, name):
            raise ValueError("Unexpected backup filename")
        source = self.data / "backups" / name
        if source.is_symlink():
            raise ValueError("Backup must be a regular file")
        protected = self.state / "backups" / ("preupdate-" + name.split("-", 1)[1])
        with open(protected, "xb") as out:
            result = subprocess.run(["/usr/sbin/runuser", "-u", "www-data", "--", "/usr/bin/cat", "--", str(source)], stdout=out, stderr=subprocess.PIPE, timeout=600)
            if result.returncode:
                raise ValueError("Cannot copy safety backup")
            out.flush()
            os.fsync(out.fileno())
        os.chown(protected, 0, self.account.pw_gid)
        os.chmod(protected, 0o640)
        # Validate all CRCs, DB hash/integrity and every referenced image before proceeding.
        self.run(["/usr/bin/python3", self.control / "restore-update.py", "verify", protected, self.data], as_web=True, timeout=600)
        return protected
    def switch(self, destination):
        temp = self.base / "current.next"
        if temp.exists() or temp.is_symlink():
            temp.unlink()
        temp.symlink_to(destination)
        os.replace(temp, self.base / "current")
        fd = os.open(self.base, os.O_DIRECTORY)
        try:
            os.fsync(fd)
        finally:
            os.close(fd)
    def health(self, release):
        self.run([self.php, release / "bin/console.php", "health"], as_web=True)
        opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirect)
        for attempt in range(15):
            try:
                with opener.open("http://127.0.0.1:8080/health", timeout=10) as response:
                    body = json.loads(response.read(65536))
                    if body.get("ok") is not True or body.get("version") != release.name or body.get("schema") != self.expected_schema:
                        raise ValueError("HTTP health does not match deployed release")
                with opener.open("http://127.0.0.1:8080/assets/screen.js", timeout=10) as response:
                    if response.status != 200 or not response.read(1):
                        raise ValueError("Missing display assets")
                return
            except Exception:
                if attempt == 14:
                    raise
                time.sleep(1)
    def restore(self, transaction):
        old = Path(transaction["old"])
        if old.parent != self.base / "releases" or not re.fullmatch(VERSION_RE, old.name) or not old.is_dir():
            raise ValueError("Invalid rollback target")
        backup = Path(transaction["backup"])
        if backup.parent != self.state / "backups" or not re.fullmatch(r"preupdate-\d{8}-\d{6}-[a-f0-9]{6}\.zip", backup.name):
            raise ValueError("Invalid rollback backup")
        self.run(["/usr/bin/python3", self.control / "restore-update.py", "restore", backup, self.data], as_web=True, timeout=600)
        self.switch(old)
    def recover(self, boot=False):
        tx = read_json(self.journal)
        if not tx:
            # A maintenance flag without a journal indicates interrupted pre-backup work.
            self.maintenance.unlink(missing_ok=True)
            return
        if tx["phase"] != "committed":
            if not boot:
                self.systemctl("stop", "php8.3-fpm")
                self.systemctl("stop", "pulse-backup@daily.service", "pulse-backup@weekly.service", check=False)
            self.restore(tx)
            self.save_status(phase="rolled_back", error="interrupted_update", failed_version=tx["target"], scheduled_for=None)
        self.maintenance.unlink(missing_ok=True)
        self.journal.unlink()
        if not boot:
            self.systemctl("start", "php8.3-fpm")
    def install(self, source, release=None):
        old = self.current()
        target_version = validate_tree(source)
        if version(target_version) <= version(old.name):
            raise ValueError("Only newer releases can be installed; existing data was left unchanged")
        if self.journal.exists():
            raise ValueError("Recovery must complete before another installation")
        target = self.base / "releases" / target_version
        if target.exists():
            # Previous failed attempt: never overwrite a code directory.
            if read_json(self.status_file, {}).get("failed_version") != target_version:
                raise ValueError("Release directory already exists")
            target = self.base / "releases" / target_version
            previous = self.base / "releases" / (target_version + ".failed-" + str(int(time.time())))
            target.rename(previous)
        data_size = sum(p.stat().st_size for p in (self.data / "media").glob("*.jpg") if p.is_file()) + (self.data / "pulse.sqlite").stat().st_size
        if shutil.disk_usage(self.state).free < data_size * 3 + 256 * 1024 * 1024:
            raise ValueError("Insufficient free space for verified backup and rollback")
        if shutil.disk_usage(self.base).free < MAX_EXPANDED + 64 * 1024 * 1024:
            raise ValueError("Insufficient free space for staged code")
        stage = Path(tempfile.mkdtemp(prefix=".stage-", dir=self.base / "releases"))
        tx = None
        stopped = False
        try:
            # Copy only the exact manifest; never storage, environment or diagnostic reports.
            for line in (source / "CONTENTS.sha256").read_text().splitlines():
                name = line.split("  ", 1)[1]
                if name.startswith("storage/"):
                    continue
                dest = stage / name
                dest.parent.mkdir(parents=True, exist_ok=True, mode=0o750)
                shutil.copyfile(source / name, dest)
            shutil.copyfile(source / "CONTENTS.sha256", stage / "CONTENTS.sha256")
            local = old / "config/local.php"
            if local.exists():
                if local.is_symlink() or local.stat().st_uid != 0 or local.stat().st_mode & 0o022:
                    raise ValueError("Local configuration is not protected")
                shutil.copyfile(local, stage / "config/local.php")
            for path in [stage, *stage.rglob("*")]:
                os.chown(path, 0, self.account.pw_gid)
                os.chmod(path, 0o750 if path.is_dir() else 0o640)
            # Lint all new PHP using the deployed runtime, without executing it as root.
            for file in stage.rglob("*.php"):
                self.run([self.php, "-l", file], as_web=True)
            stage.rename(target)
            self.maintenance.write_text("PULSE update in progress\n")
            self.systemctl("stop", "pulse-backup-daily.timer", "pulse-backup-weekly.timer")
            self.systemctl("stop", "php8.3-fpm")
            stopped = True
            self.systemctl("stop", "pulse-backup@daily.service", "pulse-backup@weekly.service", check=False)
            backup = self.make_backup(old)
            tx = {"phase": "prepared", "old": str(old), "target": target_version, "backup": str(backup), "at": stamp()}
            atomic_json(self.journal, tx)
            self.save_status(phase="installing", error=None, backup=backup.name)
            self.run([self.php, target / "bin/console.php", "migrate"], as_web=True, timeout=600)
            self.switch(target)
            self.expected_schema = release["db_schema"] if release else 1
            self.systemctl("start", "php8.3-fpm")
            self.health(target)
            # Commit is durable before removing maintenance and allowing administrators to write.
            tx["phase"] = "committed"
            atomic_json(self.journal, tx)
            self.save_status(phase="installed", installed_at=stamp(), installed_version=target_version,
                             failed_version=None, error=None, scheduled_for=None, backup=backup.name)
            self.maintenance.unlink(missing_ok=True)
            self.journal.unlink()
            self.prune_backups()
            print(f"PULSE {target_version} installato. Backup protetto: {backup}")
        except Exception:
            if tx is not None and tx["phase"] != "committed":
                self.systemctl("stop", "php8.3-fpm")
                self.restore(tx)
                self.save_status(phase="rolled_back", failed_version=target_version, error="installation_failed", scheduled_for=None)
                self.maintenance.unlink(missing_ok=True)
                self.journal.unlink()
                self.systemctl("start", "php8.3-fpm")
                print("Aggiornamento annullato. Versione precedente e dati ripristinati.", file=sys.stderr)
            elif tx is None:
                self.maintenance.unlink(missing_ok=True)
                self.save_status(phase="failed", failed_version=target_version, error="preflight_or_backup_failed", scheduled_for=None)
                if stopped:
                    self.systemctl("start", "php8.3-fpm")
            # Failed rollback deliberately leaves the journal/maintenance for recovery.
            raise
        finally:
            if not self.maintenance.exists():
                self.systemctl("start", "pulse-backup-daily.timer", "pulse-backup-weekly.timer", check=False)
            if stage.exists():
                shutil.rmtree(stage)
    def prune_backups(self):
        # Retention runs only after commit; the rollback snapshot is never pruned mid-update.
        files = sorted((self.state / "backups").glob("preupdate-*.zip"), reverse=True)
        for file in files[5:]:
            if not file.is_symlink():
                file.unlink()
    def tick(self):
        if self.journal.exists():
            self.recover()
        prefs = self.preferences()
        if prefs is None:
            self.save_status(phase="waiting", error=None, scheduled_for=None)
            return
        old = self.current()
        state = read_json(self.status_file, {})
        force = prefs["request"] != state.get("request", "")
        due = time.time() - state.get("checked_epoch", 0) >= 1800
        retry = prefs["retry"] != state.get("retry", "")
        if retry:
            state = self.save_status(failed_version=None, retry=prefs["retry"], error=None)
        try:
            release = self.signed_manifest(fresh=(force or due or not (self.state / "downloads/stable.json").is_file()))
            if force or due:
                state = self.save_status(checked_at=stamp(), checked_epoch=time.time(), request=prefs["request"], error=None)
        except Exception as error:
            self.save_status(phase="offline", error="source_or_signature_unavailable", checked_at=stamp(),
                             checked_epoch=time.time(), request=prefs["request"], scheduled_for=None)
            print("Release check failed: " + str(error), file=sys.stderr)
            return
        public_release = {key: release[key] for key in ("version", "notes")}
        if version(release["version"]) <= version(old.name):
            self.save_status(phase="up_to_date", release=public_release, scheduled_for=None, error=None)
            return
        self.save_status(release=public_release)
        if release["min_updater"] > UPDATER_VERSION or version(release["min_from"]) > version(old.name):
            self.save_status(phase="incompatible", error="manual_upgrade_required", scheduled_for=None)
            return
        php_version = self.run([self.php, "-r", "echo PHP_VERSION;"]).stdout.strip().split("-")[0]
        if version(php_version) < version(release["php_min"]):
            self.save_status(phase="incompatible", error="php_upgrade_required", scheduled_for=None)
            return
        if state.get("failed_version") == release["version"]:
            self.save_status(phase=state.get("phase") if state.get("phase") in ("failed","rolled_back") else "failed", scheduled_for=None)
            return
        package = self.state / "downloads" / (release["version"] + ".zip")
        try:
            if not package.exists() or digest(package) != release["sha256"]:
                self.save_status(phase="downloading", scheduled_for=None)
                partial = package.with_suffix(".zip.partial")
                self.download_package(release, partial)
                self.verify_package(release, partial)
                os.replace(partial, package)
            self.verify_package(release, package)
        except Exception as error:
            self.save_status(phase="failed", error="package_download_or_integrity", scheduled_for=None)
            print("Package check failed: " + str(error), file=sys.stderr)
            return
        if not prefs["auto"]:
            self.save_status(phase="paused", scheduled_for=None, error=None)
            return
        clock = now()
        scheduled = next_window(clock, prefs["hour"], prefs["postpone"])
        self.save_status(phase="postponed" if prefs["postpone"] > clock.timestamp() else "scheduled",
                         scheduled_for=scheduled.isoformat(timespec="seconds"), error=None)
        if scheduled > clock:
            return
        # Re-read preferences and revalidate signature/package immediately before touching deployment.
        prefs2 = self.preferences()
        if not prefs2 or not prefs2["auto"] or next_window(now(), prefs2["hour"], prefs2["postpone"]) > now():
            return
        self.signed_manifest(fresh=False)
        self.verify_package(release, package)
        staging = self.state / ("extract-" + release["version"])
        if staging.exists():
            shutil.rmtree(staging)
        try:
            extracted_version = extract_release(package, staging)
            if extracted_version != release["version"]:
                raise ValueError("Manifest/package version mismatch")
            self.install(staging, release)
        except Exception as error:
            if not self.journal.exists() and read_json(self.status_file, {}).get("phase") != "rolled_back":
                self.save_status(phase="failed", failed_version=release["version"], error="installation_failed", scheduled_for=None)
            print("Installation failed: " + str(error), file=sys.stderr)
            raise
        finally:
            if staging.exists():
                shutil.rmtree(staging)

def bootstrap(updater, source):
    # Explicit, operator-run bootstrap only. Automated updates never replace this root code.
    for name in ("updater.py", "restore-update.py", "verify-release.php", "release-public.key"):
        if not (source / "deploy" / name).is_file():
            raise ValueError("Missing deployment component: " + name)
    key = base64.b64decode((source / "deploy/release-public.key").read_text().strip(), validate=True)
    if len(key) != 32:
        raise ValueError("Invalid release public key")
    existing = updater.control / "release-public.key"
    if existing.exists() and existing.read_text().strip() != (source / "deploy/release-public.key").read_text().strip():
        raise ValueError("Signing key differs from installed key; explicit key rotation is required")
    if updater.config.exists() or updater.config.is_symlink():
        info = updater.config.stat()
        if updater.config.is_symlink() or info.st_uid != 0 or info.st_mode & 0o022:
            raise ValueError("Untrusted updater configuration")
        if read_json(updater.config) not in (LEGACY_CONFIG, PRIVATE_GITHUB_CONFIG, GITHUB_CONFIG):
            raise ValueError("Unknown update source; configuration was not replaced")
    updater.control.mkdir(parents=True, exist_ok=True, mode=0o755)
    os.chown(updater.control,0,0)
    os.chmod(updater.control,0o755)
    for name in ("updater.py", "restore-update.py", "verify-release.php", "release-public.key"):
        target = updater.control / name
        shutil.copyfile(source / "deploy" / name, target)
        os.chown(target, 0, 0)
        os.chmod(target, 0o644)
    updater.config.parent.mkdir(exist_ok=True, mode=0o755)
    os.chown(updater.config.parent,0,0)
    os.chmod(updater.config.parent,0o755)
    atomic_json(updater.config, GITHUB_CONFIG)
    os.chmod(updater.config, 0o644)
    # Drop the old channel cache; the next tick must query GitHub.
    for name in ("stable.json", "stable.json.sig", "github-release.json"):
        (updater.state / "downloads" / name).unlink(missing_ok=True)
    updater.save_status(checked_epoch=0, release=None, scheduled_for=None, phase="waiting", error=None)
    updater.configuration()
    for name in ("pulse-updates.service", "pulse-updates.timer", "pulse-update-recover.service"):
        shutil.copyfile(source / "deploy" / name, Path("/etc/systemd/system") / name)
    dropin = Path("/etc/systemd/system/php8.3-fpm.service.d")
    dropin.mkdir(exist_ok=True)
    (dropin / "pulse-recovery.conf").write_text("[Unit]\nRequires=pulse-update-recover.service\nAfter=pulse-update-recover.service\n")
    # Guard legacy 0.1.x backup jobs during the bootstrap transaction as well.
    backup_unit = (source / "deploy/pulse-backup@.service").read_text()
    backup_unit = backup_unit.replace("After=local-fs.target", "After=local-fs.target pulse-update-recover.service\nRequires=pulse-update-recover.service")
    backup_unit = backup_unit.replace("ExecStart=/usr/bin/php ", "ExecCondition=/usr/bin/test ! -e /var/lib/pulse-updater/maintenance\nExecStart=/usr/bin/php8.3 ")
    Path("/etc/systemd/system/pulse-backup@.service").write_text(backup_unit)
    updater.systemctl("daemon-reload")
    updater.systemctl("start", "pulse-update-recover.service")
    updater.systemctl("enable", "pulse-updates.timer")

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("command", choices=["tick", "recover", "install", "bootstrap"])
    parser.add_argument("--source", type=Path)
    args = parser.parse_args()
    if os.geteuid() != 0:
        raise SystemExit("Eseguire con sudo.")
    os.umask(0o027)
    updater = Updater()
    updater.prepare()
    if args.command in ("install", "bootstrap"):
        updater.systemctl("stop", "pulse-updates.timer", "pulse-updates.service", check=False)
    with open(updater.state / "update.lock", "a") as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        if args.command in ("install", "bootstrap"):
            if args.source is None:
                raise ValueError("Package source is required")
            source = args.source.resolve(strict=True)
            validate_tree(source)
            # Mint platform and runtime checks before any deployed code changes.
            os_release = Path("/etc/os-release").read_text()
            if not re.search(r'^ID=(?:\"linuxmint\"|linuxmint)$', os_release, re.M) or not re.search(r'^VERSION_ID=\"?22(?:\.\d+)?\"?$', os_release, re.M):
                raise ValueError("This updater requires Linux Mint 22.x")
            if updater.run(["/usr/bin/dpkg", "--print-architecture"]).stdout.strip() != "amd64":
                raise ValueError("Mint amd64 required")
            updater.run([updater.php, "-r", "foreach(['pdo_sqlite','sqlite3','gd','fileinfo','zip','curl','mbstring','intl','sodium'] as $e)if(!extension_loaded($e))exit(1);"])
            if args.command == "install":
                # Reject reinstall/downgrade before altering the bootstrap service.
                if version(validate_tree(source)) <= version(updater.current().name):
                    raise ValueError("Questa versione è già installata o è precedente. Nessun dato modificato.")
                # Detect incomplete setup before entering maintenance.
                # The trusted helper is not installed yet on 0.1.x. Ask the existing
                # application as www-data before installing deployment components.
                updater.run([updater.php, "-r", "require $argv[1].'/app/bootstrap.php';boot();exit(installed()?0:2);", updater.current()], as_web=True)
            # Start/reload recovery before acquiring a transaction. It also uses the same lock,
            # so bootstrap starts it only after the guard is released below.
            fcntl.flock(lock, fcntl.LOCK_UN)
            bootstrap(updater, source)
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            if updater.journal.exists():
                updater.recover()
            if args.command == "install":
                updater.install(source)
            updater.systemctl("start", "pulse-updates.timer")
        elif args.command == "recover":
            updater.recover(boot=True)
        else:
            updater.tick()
if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        print("PULSE: " + str(error), file=sys.stderr)
        # A failed operator-run bootstrap must not silently disable future checks.
        if len(sys.argv) > 1 and sys.argv[1] in ("install", "bootstrap") and Path("/etc/pulse/updater.json").is_file():
            subprocess.run(["/usr/bin/systemctl", "start", "pulse-updates.timer"], capture_output=True)
        sys.exit(1)
