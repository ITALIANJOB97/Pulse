#!/usr/bin/env python3
"""First installation on Raspberry Pi OS Desktop; release values pinned by builder.

Run as the desktop user, not with sudo. This file downloads only the exact
release for which it was generated. It never reinstalls an existing PULSE.
"""
import argparse
import hashlib
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import stat
import subprocess
import sys
import tempfile
import urllib.parse
import urllib.request
import zipfile

RELEASE_TAG = "@RELEASE_TAG@"
VERSION = "@VERSION@"
PACKAGE_SHA256 = "@PACKAGE_SHA256@"
PACKAGE_SIZE = 0  # @PACKAGE_SIZE@
REPOSITORY = "ITALIANJOB97/Pulse"
MAX_EXPANDED = 300 * 1024 * 1024


def run(*args):
    subprocess.run(args, check=True)


def output(*args):
    return subprocess.check_output(args, text=True).strip()


def package_url():
    if (not re.fullmatch(r"v\d+\.\d+\.\d+(?:-[a-z0-9.-]+)?", RELEASE_TAG)
            or not re.fullmatch(r"\d+\.\d+\.\d+", VERSION)
            or not re.fullmatch(r"[a-f0-9]{64}", PACKAGE_SHA256)
            or not 0 < PACKAGE_SIZE <= 100 * 1024 * 1024):
        raise RuntimeError("Installer non pubblicato: usare il file preparato per una release.")
    return f"https://github.com/{REPOSITORY}/releases/download/{RELEASE_TAG}/pulse-raspberry-{VERSION}.zip"


def allowed_download(url):
    parsed = urllib.parse.urlsplit(url)
    return (parsed.scheme == "https" and parsed.hostname in
            {"github.com", "release-assets.githubusercontent.com", "objects.githubusercontent.com"}
            and parsed.port in (None, 443) and not parsed.username and not parsed.password)


class GithubRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        if not allowed_download(newurl):
            raise RuntimeError("Reindirizzamento del download non autorizzato.")
        return super().redirect_request(req, fp, code, msg, headers, newurl)


def download(target):
    url = package_url()
    opener = urllib.request.build_opener(GithubRedirect())
    request = urllib.request.Request(url, headers={"User-Agent": "PULSE-Raspberry-Installer"})
    digest = hashlib.sha256()
    size = 0
    with opener.open(request, timeout=60) as response, target.open("xb") as archive:
        if not allowed_download(response.url):
            raise RuntimeError("Origine del download non autorizzata.")
        while chunk := response.read(1024 * 1024):
            size += len(chunk)
            if size > PACKAGE_SIZE:
                raise RuntimeError("Pacchetto piu grande del previsto.")
            digest.update(chunk)
            archive.write(chunk)
    if size != PACKAGE_SIZE or digest.hexdigest() != PACKAGE_SHA256:
        raise RuntimeError("Verifica SHA256 fallita: installazione non avviata.")


def extract(archive, destination):
    destination.mkdir(mode=0o700)
    with zipfile.ZipFile(archive) as package:
        entries = package.infolist()
        if not entries or len(entries) > 4000:
            raise RuntimeError("Numero di file non valido.")
        prefix, seen, total = None, set(), 0
        for item in entries:
            parts = item.filename.split("/")
            if (len(parts) < 2 or any(p in ("", ".", "..") for p in parts)
                    or "\\" in item.filename or ":" in item.filename
                    or item.is_dir() or stat.S_IFMT(item.external_attr >> 16) not in (0, stat.S_IFREG)):
                raise RuntimeError("Percorso non valido nel pacchetto.")
            if prefix is not None and prefix != parts[0]:
                raise RuntimeError("Struttura del pacchetto non valida.")
            prefix = parts[0]
            name = PurePosixPath(*parts[1:])
            total += item.file_size
            if name in seen or total > MAX_EXPANDED:
                raise RuntimeError("Pacchetto duplicato o troppo grande.")
            seen.add(name)
            target = destination.joinpath(*name.parts)
            target.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
            with package.open(item) as source, target.open("xb") as out:
                shutil.copyfileobj(source, out)
            target.chmod(0o600)
    if (destination / "VERSION").read_text(encoding="utf-8").strip() != VERSION:
        raise RuntimeError("La versione del pacchetto non corrisponde.")
    for required in ("deploy/install-raspberry.sh", "bin/package_compatibility.py", "deploy/updater.py"):
        if not (destination / required).is_file():
            raise RuntimeError("Pacchetto incompleto.")


def preflight(configure_tv=False):
    if sys.platform != "linux" or os.geteuid() == 0:
        raise RuntimeError("Eseguire sul Raspberry come utente desktop, SENZA sudo.")
    os_info = dict(line.split("=", 1) for line in Path("/etc/os-release").read_text().splitlines()
                   if "=" in line and not line.startswith("#"))
    os_info = {key: value.strip('\"\'') for key, value in os_info.items()}
    if (os_info.get("ID") not in ("debian", "raspbian") or os_info.get("VERSION_ID") != "13"
            or os_info.get("VERSION_CODENAME") != "trixie" or output("dpkg", "--print-architecture") != "arm64"):
        raise RuntimeError("Serve Raspberry Pi OS Desktop Trixie a 64 bit (arm64).")
    if b"Raspberry Pi" not in Path("/proc/device-tree/model").read_bytes():
        raise RuntimeError("Questo installer e riservato ai Raspberry Pi.")
    if not (Path("/usr/share/wayland-sessions").is_dir() or Path("/usr/share/xsessions").is_dir()):
        raise RuntimeError("Serve Raspberry Pi OS con Desktop, non Lite.")
    if not Path("/run/systemd/system").is_dir() or not shutil.which("raspi-config") or not shutil.which("sudo"):
        raise RuntimeError("Avviare normalmente Raspberry Pi OS con systemd e raspi-config.")
    if configure_tv:
        if not Path("/usr/local/bin/pulse-enable-kiosk").is_file():
            raise RuntimeError("Installare prima PULSE.")
    else:
        # The privileged installer repeats the full existing-installation check.
        for name in ("/opt/pulse/current", "/var/lib/pulse", "/etc/pulse/updater.json", "/usr/local/bin/pulse-kiosk"):
            if os.path.lexists(name):
                raise RuntimeError("PULSE e gia presente. Usare l'aggiornamento; per la sola TV usare --configure-tv.")


def configure_tv():
    # Runs as the desktop user: autostart files must not belong to root.
    run("/usr/local/bin/pulse-enable-kiosk")
    run("sudo", "raspi-config", "nonint", "do_boot_behaviour", "B4")
    run("sudo", "raspi-config", "nonint", "do_blanking", "1")
    if output("sudo", "raspi-config", "nonint", "get_boot_cli") != "1":
        raise RuntimeError("Avvio desktop non confermato: controllare raspi-config.")
    if output("sudo", "raspi-config", "nonint", "get_autologin") != "0":
        raise RuntimeError("Accesso automatico desktop non confermato: controllare raspi-config.")
    if output("sudo", "raspi-config", "nonint", "get_blanking") != "1":
        raise RuntimeError("Spegnimento automatico dello schermo ancora attivo: controllare raspi-config.")
    run("systemctl", "is-active", "--quiet", "nginx", "php8.4-fpm")
    run("systemctl", "is-enabled", "--quiet", "nginx", "php8.4-fpm")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--branch-setup", action="store_true", help="Solo nuovi dispositivi: primo admin dalla LAN senza codice")
    parser.add_argument("--configure-tv", action="store_true", help="Ripete solo la configurazione TV senza reinstallare o cambiare dati")
    parser.add_argument("--reboot", action="store_true", help="Riavvia solo dopo installazione e verifiche riuscite")
    args = parser.parse_args()
    if args.configure_tv and args.branch_setup:
        parser.error("--configure-tv non modifica il setup: omettere --branch-setup")
    preflight(args.configure_tv)
    if not args.configure_tv:
        package_url()  # Refuse an unpublished template before requesting sudo.
    print("Configuro PULSE e l'accesso automatico al desktop per questo utente.", flush=True)
    run("sudo", "-v")
    if not args.configure_tv:
        work = Path(tempfile.mkdtemp(prefix="PULSE-install-", dir=Path.home()))
        print(f"Download e rapporto conservati in: {work}", flush=True)
        package = work / "pulse-raspberry.zip"
        download(package)
        source = work / "package"
        extract(package, source)
        print(f"PULSE {VERSION}: SHA256 verificato. Installazione...", flush=True)
        command = ["sudo", "bash", str(source / "deploy/install-raspberry.sh"), "--output-dir", str(work / "diagnostica")]
        if args.branch_setup:
            command.append("--branch-setup")
        run(*command)
    configure_tv()
    print("Installazione e avvio automatico configurati. Al riavvio la TV mostra l'IP per il setup.", flush=True)
    if args.reboot:
        run("sudo", "systemctl", "reboot")
    else:
        print("Per completare la prova di avvio: sudo reboot", flush=True)


if __name__ == "__main__":
    try:
        main()
    except (OSError, RuntimeError, ValueError, subprocess.CalledProcessError, zipfile.BadZipFile) as error:
        print(f"PULSE: operazione interrotta: {error}", file=sys.stderr)
        print("Nessun riavvio eseguito. Conservare il rapporto; non cancellare dati e non forzare la reinstallazione.", file=sys.stderr)
        sys.exit(1)
