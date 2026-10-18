#!/usr/bin/env python3
"""Build source-only and offline support ZIPs on a workstation."""
from pathlib import Path
import hashlib
import re
import stat
import zipfile

ROOT = Path(__file__).resolve().parent.parent
VERSION = (ROOT / "VERSION").read_text(encoding="utf-8").strip()
if not re.fullmatch(r"\d+\.\d+\.\d+", VERSION):
    raise SystemExit("Invalid VERSION")
OUTPUT = ROOT / "dist"
OUTPUT.mkdir(exist_ok=True)
FILES = [
    "VERSION", "README.md", "CHANGELOG.md", "LEGGIMI_LINUX_MINT.txt",
    "INSTALLAZIONE_LINUX_MINT.txt", "INSTALLAZIONE_RASPBERRY.txt",
    "DIAGNOSI_LINUX_MINT.txt", "AGGIORNAMENTO_LINUX_MINT.txt", "PUBBLICAZIONE_BLOG.txt", ".gitignore", "storage/.gitkeep",
]
EXTENSIONS = {".php", ".json", ".css", ".js", ".cjs", ".sh", ".conf", ".service", ".timer", ".py", ".html", ".txt", ".md", ".key"}
for folder in ("app", "bin", "config", "deploy", "public", "tests", "docs"):
    for path in sorted((ROOT / folder).rglob("*")):
        if path.is_symlink():
            raise SystemExit(f"Refusing symlink: {path}")
        relative = path.relative_to(ROOT).as_posix()
        if any(part in {"diagnostica", "__pycache__"} for part in path.relative_to(ROOT).parts):
            continue
        if path.is_file() and path.suffix in EXTENSIONS and relative != "config/local.php":
            if path.suffix == ".key" and relative != "deploy/release-public.key":
                raise SystemExit("Unexpected key file; never package private keys")
            FILES.append(relative)


def build(prefix: str, files: list[str]):
    archive_path = OUTPUT / f"{prefix}.zip"
    temporary = OUTPUT / f"{prefix}.zip.partial"

    def entry(name: str) -> zipfile.ZipInfo:
        info = zipfile.ZipInfo(f"{prefix}/{name}", date_time=(2026, 10, 18, 0, 0, 0))
        info.create_system = 3
        info.external_attr = (stat.S_IFREG | (0o755 if name.endswith((".sh", ".py")) else 0o644)) << 16
        info.compress_type = zipfile.ZIP_DEFLATED
        return info

    try:
        manifest = []
        with zipfile.ZipFile(temporary, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
            for name in sorted(set(files)):
                path = ROOT / name
                if path.is_symlink():
                    raise SystemExit(f"Refusing symlink: {path}")
                data = path.read_text(encoding="utf-8-sig").replace("\r\n", "\n").encode("utf-8")
                archive.writestr(entry(name), data)
                manifest.append(f"{hashlib.sha256(data).hexdigest()}  {name}")
            archive.writestr(entry("CONTENTS.sha256"), ("\n".join(manifest) + "\n").encode("utf-8"))
        with zipfile.ZipFile(temporary) as archive:
            if archive.testzip() is not None:
                raise RuntimeError("Archive CRC check failed")
            for name in archive.namelist():
                relative = name.removeprefix(prefix + "/")
                if relative.startswith("storage/") and relative != "storage/.gitkeep":
                    raise RuntimeError("Runtime data must not be packaged")
                if relative in ("config/local.php", ".env") or "diagnostica/" in relative:
                    raise RuntimeError("Local secrets/reports must not be packaged")
        temporary.replace(archive_path)
        digest = hashlib.sha256(archive_path.read_bytes()).hexdigest()
        archive_path.with_suffix(".zip.sha256").write_text(
            f"{digest}  {archive_path.name}\n", encoding="utf-8")
        print(f"Created: {archive_path}")
        print(f"Files: {len(set(files)) + 1}; bytes: {archive_path.stat().st_size}")
        print(f"SHA256: {digest}")
    finally:
        temporary.unlink(missing_ok=True)


build(f"PULSE-Dietikon-Linux-Mint-{VERSION}", FILES)
build(f"PULSE-Diagnosi-Linux-Mint-{VERSION}", [
    "VERSION", "DIAGNOSI_LINUX_MINT.txt", "deploy/diagnose-mint.sh", "deploy/support-mint.py",
])
