#!/usr/bin/env python3
"""Operator-run repair for the published 0.2.4 NoRedirect defect. Mint 22 only.
The original signed application ZIP is unchanged. The missing redirect guard is
added only to the root-owned update service; normal backup/rollback still apply.
"""
import hashlib
import importlib.util
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
import zipfile

PACKAGE_SHA256 = "e0bc9c2b95f5b19dd2b131d5447c81c355533821251d5d2302ec724810e1c8af"
UPDATER_SHA256 = "76413afa539ec0ac0fa1544a8ad4268883b9e226e9973009033a58115bfdab40"
FIX = b'class NoRedirect(urllib.request.HTTPRedirectHandler):\n    def redirect_request(self, req, fp, code, msg, headers, newurl):\n        raise ValueError("Redirect not permitted for local health check")\n\n'

def repaired_bytes(original):
    if hashlib.sha256(original).hexdigest() != UPDATER_SHA256:
        raise ValueError("Il servizio non corrisponde alla versione 0.2.4 prevista. Nessuna modifica.")
    marker = b'class TrustedRedirect(urllib.request.HTTPRedirectHandler):'
    if original.count(marker) != 1:
        raise ValueError("Punto di correzione non trovato.")
    result = original.replace(marker, FIX + marker, 1)
    compile(result, 'updater.py', 'exec')
    return result

def patch_control(control, original):
    target = control / 'updater.py'
    info = target.lstat()
    if target.is_symlink() or info.st_uid != 0 or info.st_mode & 0o022:
        raise ValueError("Permessi del servizio non sicuri. Nessuna modifica.")
    corrected = repaired_bytes(original)
    current = target.read_bytes()
    if current == corrected:
        return
    if current != original:
        raise ValueError("Il servizio installato e diverso da quello verificato. Nessuna modifica.")
    backup = control / 'updater.py.before-noredirect-fix'
    if not backup.exists():
        with open(backup, 'xb') as handle:
            os.fchmod(handle.fileno(), 0o600);handle.write(current);handle.flush();os.fsync(handle.fileno())
    fd, name = tempfile.mkstemp(prefix='.repair-', dir=control)
    temp = Path(name)
    try:
        with os.fdopen(fd, 'wb') as handle:
            os.fchmod(handle.fileno(), 0o644);handle.write(corrected);handle.flush();os.fsync(handle.fileno())
        os.replace(temp, target)
        directory = os.open(control, os.O_DIRECTORY)
        try:os.fsync(directory)
        finally:os.close(directory)
    finally:
        temp.unlink(missing_ok=True)

def prepare(package, destination):
    sys.dont_write_bytecode = True # Keep the verified source tree byte-for-byte unchanged.
    if not package.is_file() or hashlib.sha256(package.read_bytes()).hexdigest() != PACKAGE_SHA256:
        raise ValueError("Pacchetto 0.2.4 mancante o non integro. Scarica nuovamente il kit di riparazione.")
    # The complete archive must match the exact published, audited package first.
    with zipfile.ZipFile(package) as archive:
        if archive.testzip() is not None:
            raise ValueError("Archivio danneggiato.")
        archive.extractall(destination)
    source = destination / 'PULSE-Dietikon-Linux-Mint-0.2.4'
    path = source / 'deploy/updater.py'
    original = path.read_bytes()
    repaired_bytes(original)
    spec = importlib.util.spec_from_file_location('pulse_original_024', path)
    updater = importlib.util.module_from_spec(spec);spec.loader.exec_module(updater)
    # The running process and persistent service both get the same literal fix.
    exec(FIX, updater.__dict__)
    original_bootstrap = updater.bootstrap
    def bootstrap(agent, package_source):
        original_bootstrap(agent, package_source)
        patch_control(agent.control, original)
    updater.bootstrap = bootstrap
    return updater, source

def main():
    if os.geteuid() != 0:
        raise ValueError("Eseguire: sudo python3 ripara-e-aggiorna.py")
    os.umask(0o027)
    current = Path('/opt/pulse/current')
    if not current.is_symlink() or not Path('/var/lib/pulse/pulse.sqlite').is_file():
        raise ValueError("Installazione PULSE esistente non trovata. Non usare l'installer iniziale.")
    version = (current / 'VERSION').read_text().strip()
    if not re.fullmatch(r'\d+\.\d+\.\d+', version) or tuple(map(int, version.split('.'))) > (0, 2, 4):
        raise ValueError("Questo kit serve solo per installazioni fino alla versione 0.2.4.")
    installed_service = Path('/usr/local/lib/pulse/updater.py')
    if installed_service.is_file():
        match = re.search(rb'^UPDATER_VERSION = (\d+)$', installed_service.read_bytes(), re.M)
        if not match or int(match[1]) > 3:
            raise ValueError("Servizio diverso dalla serie 0.2.4: il kit non lo sostituisce.")
    package = Path(__file__).resolve().with_name('pulse-0.2.4.zip')
    with tempfile.TemporaryDirectory(prefix='pulse-repair-024-') as temporary:
        updater, source = prepare(package, Path(temporary))
        command = 'bootstrap' if version == '0.2.4' else 'install'
        sys.argv = ['updater.py', command, '--source', str(source)]
        print('Riparazione NoRedirect e aggiornamento immediato a PULSE 0.2.4.', flush=True)
        print('Il rinvio notturno non blocca questo comando manuale. Non spegnere il PC.', flush=True)
        updater.main()
    print('Operazione completata. Apri http://127.0.0.1:8080/admin con il tuo account.')
    print('Il servizio corretto usa GitHub pubblico. Dati, foto e utenti conservati.')

if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print('PULSE: '+str(error),file=sys.stderr)
        if Path('/etc/pulse/updater.json').is_file():
            subprocess.run(['/usr/bin/systemctl','start','pulse-updates.timer'],capture_output=True)
        sys.exit(1)
