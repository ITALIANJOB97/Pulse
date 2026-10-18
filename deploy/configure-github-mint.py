#!/usr/bin/env python3
"""Configure read-only GitHub access on Mint. The token never enters command history."""
import getpass
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import tempfile
import urllib.error
import urllib.request

REPOSITORY = "ITALIANJOB97/Pulse"

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

def main():
    if os.geteuid() != 0:
        raise SystemExit("Eseguire con sudo python3 deploy/configure-github-mint.py")
    print("PULSE: accesso alla repository privata " + REPOSITORY)
    print("Usa un fine-grained token limitato a Pulse, Contents: Read-only.")
    token = getpass.getpass("Incolla il token GitHub (non verrà mostrato): ").strip()
    if not re.fullmatch(r"[A-Za-z0-9_]{20,255}", token):
        raise SystemExit("Formato del token non valido. Nessuna modifica.")
    url = "https://api.github.com/repos/" + REPOSITORY + "/releases/latest"
    request = urllib.request.Request(url, headers={"User-Agent":"PULSE-Setup/2", "Accept":"application/vnd.github+json", "Authorization":"Bearer " + token})
    try:
        with urllib.request.build_opener(NoRedirect).open(request, timeout=20) as response:
            result = json.loads(response.read(131073))
            if response.status != 200 or not isinstance(result, dict) or result.get("draft") is not False:
                raise ValueError("Unexpected release response")
    except (urllib.error.URLError, ValueError):
        raise SystemExit("Accesso alla release non riuscito. Controlla Internet, repository selezionata, Contents: Read-only e scadenza del token. La credenziale precedente resta invariata.")
    directory = Path("/etc/pulse")
    directory.mkdir(mode=0o755, exist_ok=True)
    info = directory.stat()
    if directory.is_symlink() or info.st_uid != 0 or info.st_mode & 0o022 or not stat.S_ISDIR(info.st_mode):
        raise SystemExit("La cartella /etc/pulse deve appartenere a root e non essere modificabile da altri.")
    descriptor, temporary = tempfile.mkstemp(prefix=".github-token-", dir=directory)
    try:
        with os.fdopen(descriptor, "w", encoding="ascii") as handle:
            os.fchmod(handle.fileno(), 0o600)
            handle.write(token + "\n")
            handle.flush()
            os.fsync(handle.fileno())
        os.replace(temporary, directory / "github-token")
        descriptor = os.open(directory, os.O_DIRECTORY)
        try: os.fsync(descriptor)
        finally: os.close(descriptor)
    finally:
        Path(temporary).unlink(missing_ok=True)
    token = None
    print("Accesso verificato e salvato. La credenziale è leggibile soltanto da root.")
    config = directory / "updater.json"
    if config.is_file() and json.loads(config.read_text()).get("schema") == 2:
        subprocess.run(["/usr/bin/systemctl", "start", "--no-block", "pulse-updates.service"], check=False)
    else:
        print("Ora esegui sudo bash deploy/upgrade-mint.sh dalla stessa cartella.")

if __name__ == "__main__":
    main()
