#!/usr/bin/env python3
"""Offline PULSE support reports. Uses Python's standard library only."""
from __future__ import annotations

import argparse
import datetime as dt
import errno
import grp
import html.parser
import os
from pathlib import Path
import platform
import pwd
import re
import shutil
import subprocess
import sys
import tempfile
import urllib.error
import urllib.request

MAX_BODY = 262144


def redact(text: str) -> str:
    """Remove known credential formats before anything reaches the report."""
    # PHP traces can contain positional passwords, names, and SQL parameters.
    text = re.sub(r"#\d+\s+.*", "[PHP stack arguments omitted]", text)
    text = re.sub(r"(?im)^.*(?:set-cookie|proxy-authorization|authorization|cookie)\s*:.*$",
                  "[HTTP credentials omitted]", text)
    text = re.sub(r"(?i)(https?://)[^/\s\"'<>]+@", r"\1[credentials]@", text)
    # Request/referrer/update URLs: never retain query parameters.
    text = re.sub(r"\?[^\s\"'<>]*", "?[query omitted]", text)
    text = re.sub(
        r"""(?i)(["']?(?:password|passwd|pwd|token|csrf|secret|screen_key|api_key|access_key|setup[_ -]?token)["']?\s*[:=]\s*)(?:"[^"]*"|'[^']*'|[^\s,;]+)""",
        r"\1[redacted]", text)
    text = re.sub(r"\$2[aby]\$\d+\$[./A-Za-z0-9]{53}", "[password hash omitted]", text)
    text = re.sub(r"\$argon2[^\s\"']+", "[password hash omitted]", text)
    text = re.sub(r"(?<![A-Za-z0-9])[A-Fa-f0-9]{32,}(?![A-Za-z0-9])", "[token/hash omitted]", text)
    text = re.sub(r"[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}", "[email omitted]", text)
    return text


def owner_ids() -> tuple[int, int]:
    """Return the invoking user's identity for a report produced with sudo."""
    if os.geteuid() == 0:
        try:
            uid = int(os.environ.get("SUDO_UID", "0"))
            if uid > 0:
                account = pwd.getpwuid(uid)
                return uid, account.pw_gid
        except (ValueError, KeyError):
            pass
    return os.getuid(), os.getgid()


class Report:
    def __init__(self, directory: Path, kind: str):
        directory = directory.expanduser().resolve()
        created = not directory.exists()
        directory.mkdir(mode=0o700, parents=True, exist_ok=True)
        uid, gid = owner_ids()
        permissions_supported = True
        if created and os.geteuid() == 0:
            try:
                os.chown(directory, uid, gid)
            except OSError as error:
                if error.errno not in (errno.EPERM, errno.EOPNOTSUPP):
                    raise
                permissions_supported = False
        stamp = dt.datetime.now().strftime("%Y%m%d-%H%M%S")
        fd, name = tempfile.mkstemp(prefix=f"PULSE-{kind}-{stamp}-", suffix=".txt", dir=directory)
        self.path = Path(name)
        try:
            if os.geteuid() == 0:
                os.fchown(fd, uid, gid)
            os.fchmod(fd, 0o600)
        except OSError as error:
            if error.errno not in (errno.EPERM, errno.EOPNOTSUPP):
                os.close(fd)
                raise
            permissions_supported = False
        self.file = os.fdopen(fd, "w", encoding="utf-8", buffering=1)
        if not permissions_supported:
            self.write("Supporto USB/filesystem: i permessi sono gestiti dal sistema di montaggio.")
        self.write("PULSE · Dietikon — rapporto di supporto")
        self.write(f"Tipo: {kind}; data: {dt.datetime.now().astimezone().isoformat(timespec='seconds')}")
        self.write("Nessun database, foto, file di sessione, ambiente completo o codice setup viene incluso.")
        self.write("Le credenziali riconoscibili e le query degli URL sono oscurate.")
        self.write("Il rapporto resta su questo PC: nessun invio automatico.")
        self.write("")

    def write(self, text: str):
        self.file.write(redact(str(text)) + "\n")
        self.file.flush()

    def section(self, title: str):
        self.write("\n=== " + title + " ===")

    def close(self):
        self.file.close()


def command(report: Report, title: str, args: list[str], seconds: int = 12) -> int:
    report.section(title)
    try:
        result = subprocess.run(args, stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                                timeout=seconds, text=True, errors="replace",
                                env={**os.environ, "LC_ALL": "C", "SYSTEMD_PAGER": ""})
        output = result.stdout or "(nessun output)"
        report.write(output[-48000:])
        report.write(f"Codice uscita: {result.returncode}")
        return result.returncode
    except FileNotFoundError:
        report.write("Comando non disponibile: " + args[0])
        return 127
    except subprocess.TimeoutExpired as error:
        output = error.stdout or b""
        if isinstance(output, bytes):
            output = output.decode("utf-8", errors="replace")
        report.write(output[-12000:])
        report.write(f"Timeout dopo {seconds} secondi.")
        return 124
    except OSError as error:
        report.write(f"Controllo non eseguibile: {error}")
        return 126


def path_info(report: Report, path: Path):
    try:
        details = path.stat()
        report.write(f"{path}: mode={details.st_mode & 0o7777:04o}, "
                     f"owner={pwd.getpwuid(details.st_uid).pw_name}:"
                     f"{grp.getgrgid(details.st_gid).gr_name}, bytes={details.st_size}")
        if path.is_symlink():
            report.write("  collegamento a: " + str(path.resolve()))
    except (OSError, KeyError) as error:
        report.write(f"{path}: {error}")


def tail_log(report: Report, path: Path):
    report.section("Log: " + str(path))
    try:
        with path.open("rb") as stream:
            stream.seek(0, 2)
            stream.seek(max(0, stream.tell() - 48000))
            lines = stream.read().decode("utf-8", errors="replace").splitlines()
        report.write("\n".join(lines[-60:]) or "(vuoto)")
    except OSError as error:
        report.write(str(error))


class PageSummary(html.parser.HTMLParser):
    def __init__(self):
        super().__init__()
        self.setup_form = False
        self.system_check = False
        self.visible_chars = 0
        self.hidden_depth = 0

    def handle_starttag(self, tag, attrs):
        if tag in ("script", "style"):
            self.hidden_depth += 1
        attr = dict(attrs)
        if tag == "input" and attr.get("name") == "action" and attr.get("value") == "setup":
            self.setup_form = True

    def handle_endtag(self, tag):
        if tag in ("script", "style"):
            self.hidden_depth = max(0, self.hidden_depth - 1)

    def handle_data(self, data):
        if not self.hidden_depth:
            self.visible_chars += len(data.strip())
            if "PULSE" in data and "System check" in data:
                self.system_check = True


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def probe_http(report: Report, url: str):
    report.section("HTTP locale: " + url)
    # Ignore corporate proxy variables; never follow redirects or submit cookies.
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirect())
    request = urllib.request.Request(url, headers={"User-Agent": "PULSE-local-diagnostics/0.1.2"})
    try:
        try:
            response = opener.open(request, timeout=5)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            data = response.read(MAX_BODY + 1)
            report.write(f"Stato HTTP: {response.code}")
            for header in ("Server", "Content-Type", "Content-Length", "Location"):
                if response.headers.get(header):
                    report.write(f"{header}: {response.headers[header]}")
            report.write(f"Byte letti: {min(len(data), MAX_BODY)}"
                         + (" (risposta troncata)" if len(data) > MAX_BODY else ""))
            if not data:
                report.write("RISPOSTA VUOTA: il server non ha restituito contenuto.")
            if data.lstrip().startswith(b"<?php"):
                report.write("ERRORE: il server sta restituendo sorgente PHP anziche eseguirlo.")
            if "text/html" in response.headers.get("Content-Type", ""):
                summary = PageSummary()
                summary.feed(data[:MAX_BODY].decode("utf-8", errors="replace"))
                report.write(f"Caratteri testo visibile: {summary.visible_chars}")
                report.write(f"Modulo setup rilevato: {summary.setup_form}")
                report.write(f"Pagina controllo requisiti rilevata: {summary.system_check}")
            report.write("Corpo della risposta e cookie non salvati.")
    except (OSError, urllib.error.URLError, ValueError) as error:
        report.write(f"Richiesta fallita: {error}")


def diagnose(report: Report):
    report.section("Sistema")
    report.write("Architettura: " + platform.machine())
    report.write("Permessi diagnostica: " + ("root" if os.geteuid() == 0 else "limitati; usare sudo per i log"))
    try:
        for line in Path("/etc/os-release").read_text().splitlines():
            if line.split("=", 1)[0] in {"ID", "VERSION_ID", "PRETTY_NAME", "UBUNTU_CODENAME"}:
                report.write(line)
    except OSError as error:
        report.write(str(error))
    report.section("Versione installata")
    try:
        version = Path("/opt/pulse/current/VERSION").read_text().strip()
        report.write(version if re.fullmatch(r"\d+\.\d+\.\d+", version) else "VERSION non valido")
    except OSError as error:
        report.write(str(error))
    for action in ("is-active", "is-enabled"):
        command(report, "Servizi: " + action,
                ["systemctl", action, "nginx", "php8.3-fpm",
                 "pulse-backup-daily.timer", "pulse-backup-weekly.timer", "pulse-updates.timer"])
    command(report, "Stato processi web", ["systemctl", "show", "nginx", "php8.3-fpm",
            "--property=Id,ActiveState,SubState,Result,ExecMainStatus"])
    command(report, "Test configurazione Nginx", ["nginx", "-t"])
    command(report, "Test configurazione PHP-FPM", ["php-fpm8.3", "-t"])
    command(report, "Porta HTTP locale 8080", ["ss", "-H", "-ltnp", "sport = :8080"])
    command(report, "Socket PHP", ["ss", "-H", "-lxnp", "src", "/run/php/pulse.sock"])
    report.section("Percorsi e permessi (nessun contenuto dati)")
    for name in ("/opt/pulse", "/opt/pulse/current", "/opt/pulse/current/public",
                 "/opt/pulse/current/public/index.php", "/opt/pulse/current/app/bootstrap.php",
                 "/etc/nginx/sites-enabled/pulse", "/etc/php/8.3/fpm/pool.d/pulse.conf",
                 "/run/php/pulse.sock", "/var/lib/pulse", "/var/lib/pulse/pulse.sqlite",
                 "/var/lib/pulse/sessions", "/var/lib/pulse/logs"):
        path_info(report, Path(name))
    php = shutil.which("php8.3") or shutil.which("php")
    if php:
        command(report, "Versione PHP CLI", [php, "-v"])
        command(report, "Estensioni PHP CLI", [php, "-m"])
        command(report, "File INI CLI (nessun valore ambiente)", [php, "--ini"])
        for name in ("public/index.php", "app/bootstrap.php", "app/core.php", "app/views.php",
                     "app/i18n.php", "config/defaults.php"):
            path = Path("/opt/pulse/current") / name
            if path.is_file():
                command(report, "Sintassi PHP: " + name, [php, "-l", str(path)])
        if os.geteuid() == 0 and shutil.which("runuser"):
            code = (
                '$p="/var/lib/pulse";echo "storage readable=".(int)is_readable($p).PHP_EOL;'
                'echo "storage writable=".(int)is_writable($p).PHP_EOL;'
                'echo "sessions writable=".(int)is_writable($p."/sessions").PHP_EOL;'
                'echo "entrypoint readable=".(int)is_readable("/opt/pulse/current/public/index.php").PHP_EOL;'
            )
            command(report, "Accesso effettivo www-data (nessuna scrittura)",
                    ["runuser", "-u", "www-data", "--", php, "-r", code])
    else:
        report.write("PHP CLI non disponibile.")
    for path in ("/setup", "/login", "/assets/app.css"):
        probe_http(report, "http://127.0.0.1:8080" + path)
    for path in ("/var/log/nginx/pulse-error.log", "/var/lib/pulse/logs/php.log"):
        tail_log(report, Path(path))
    command(report, "Ultimi messaggi dei servizi web", [
        "journalctl", "--no-pager", "-o", "cat", "-n", "50",
        "-u", "nginx", "-u", "php8.3-fpm", "-u", "pulse-updates", "-u", "pulse-update-recover"
    ])
    report.section("Fine diagnosi")
    report.write("Controlli terminati. Errori e file mancanti nel rapporto sono informazioni utili.")
    report.write("Nessuna installazione, riparazione, modifica di configurazione o riavvio eseguiti.")


def record_installer(report: Report, worker: Path, arguments=()) -> int:
    """Mirror original console output, but persist only sanitized output."""
    report.section("Output installer")
    process = subprocess.Popen(["bash", str(worker), *arguments], stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                               text=True, errors="replace", bufsize=1)
    try:
        for line in process.stdout:
            print(line, end="", flush=True)
            report.write(line.rstrip("\n"))
        return process.wait()
    except KeyboardInterrupt:
        process.terminate()
        try:
            process.wait(timeout=5)
        except subprocess.TimeoutExpired:
            process.kill()
            process.wait()
        report.write("Installazione interrotta dall'utente.")
        return 130
    finally:
        process.stdout.close()


def main() -> int:
    parser = argparse.ArgumentParser(description="Rapporti locali PULSE per supporto via USB.")
    parser.add_argument("mode", choices=["diagnose", "install", "upgrade"])
    parser.add_argument("--source", type=Path, default=Path(__file__).resolve().parent.parent)
    parser.add_argument("--output-dir", type=Path, default=Path.cwd() / "diagnostica")
    parser.add_argument("--channel", choices=["blog", "github"], default="blog")
    args = parser.parse_args()
    try:
        report = Report(args.output_dir, "AGGIORNAMENTO" if args.mode == "upgrade" else ("INSTALLAZIONE" if args.mode == "install" else "DIAGNOSI"))
    except OSError as error:
        print(f"Impossibile creare il rapporto: {error}", file=sys.stderr)
        return 1
    print("Rapporto: " + str(report.path), flush=True)
    status = 0
    try:
        if args.mode in ("install", "upgrade"):
            if os.geteuid() != 0:
                report.write("Installazione non avviata: eseguire con sudo.")
                print("Eseguire: sudo bash deploy/install-mint.sh", file=sys.stderr)
                status = 1
            else:
                worker = args.source.resolve() / ("deploy/upgrade-mint-server.sh" if args.mode == "upgrade" else "deploy/install-mint-server.sh")
                status = record_installer(report, worker, ["--channel", args.channel]) if args.mode == "upgrade" else record_installer(report, worker)
                report.write(f"Esito installer: {status}")
                print("\nRaccolta verifiche finali; attendere il rapporto...", flush=True)
                diagnose(report)
        else:
            diagnose(report)
    except KeyboardInterrupt:
        status = 130
        report.write("Raccolta interrotta; rapporto parziale conservato.")
    except Exception as error:
        status = status or 1
        report.write(f"Errore supporto: {type(error).__name__}: {error}")
        print(f"Raccolta incompleta: {type(error).__name__}. Vedere il rapporto.", file=sys.stderr)
    finally:
        report.close()
    print("\nFile pronto da copiare sulla USB:\n" + str(report.path), flush=True)
    if args.mode == "diagnose":
        print("Il rapporto registra lo stato attuale; non certifica che i servizi siano funzionanti.")
    return status


if __name__ == "__main__":
    raise SystemExit(main())
