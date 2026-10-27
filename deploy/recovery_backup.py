#!/usr/bin/env python3
"""Offline, verified full PULSE snapshot for the operator recovery kit."""
import datetime as dt
import hashlib
import io
import json
import os
from pathlib import Path
import pwd
import re
import shutil
import sqlite3
import stat
import subprocess
import tarfile
import tempfile
import uuid


def digest_file(path):
    with open(path, 'rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()


def protected_directory(path):
    path = Path(path)
    info = path.lstat()
    if not stat.S_ISDIR(info.st_mode) or info.st_uid != 0 or info.st_mode & 0o022:
        raise ValueError('Cartella non protetta: ' + str(path))
    return path


def retire_inactive_release(agent, target_version):
    """Preserve a root-owned inactive version even if retry cleared failed_version."""
    parent = protected_directory(agent.base / 'releases')
    if not re.fullmatch(r'\d+\.\d+\.\d+', target_version):
        raise ValueError('Versione di destinazione non valida.')
    target = parent / target_version
    if not target.exists() and not target.is_symlink():
        return None
    if agent.journal.exists():
        raise ValueError('Completare il recupero della transazione prima di riprovare.')
    protected_directory(target)
    if target.resolve() == agent.current().resolve():
        raise ValueError('La versione attiva non puo essere spostata.')
    version_file = target / 'VERSION'
    info = version_file.lstat()
    if not stat.S_ISREG(info.st_mode) or info.st_uid != 0 or info.st_mode & 0o022 or version_file.read_text().strip() != target_version:
        raise ValueError('La cartella residua non corrisponde alla versione prevista.')
    previous = parent / (target_version + '.preserved-' + uuid.uuid4().hex)
    # Both paths are direct children of the verified, root-owned releases directory.
    target.rename(previous)
    fd = os.open(parent, os.O_DIRECTORY)
    try:
        os.fsync(fd)
    finally:
        os.close(fd)
    print('Cartella inattiva conservata: ' + str(previous), flush=True)
    return previous


def entries(paths):
    for source, name in paths:
        source = Path(source)
        if not source.exists() and not source.is_symlink():
            continue
        yield source, name
        if source.is_dir() and not source.is_symlink():
            for directory, subdirs, files in os.walk(source, followlinks=False):
                for leaf in sorted(subdirs + files):
                    item = Path(directory) / leaf
                    yield item, name + '/' + item.relative_to(source).as_posix()


def verify_archive(path):
    with tarfile.open(path, 'r:gz') as archive:
        items = archive.getmembers()
        names = [item.name for item in items]
        if len(names) != len(set(names)):
            raise ValueError('Backup con elementi duplicati.')
        manifest = json.load(archive.extractfile('PULSE-BACKUP-MANIFEST.json'))
        if manifest.get('format') != 'pulse-full-1':
            raise ValueError('Formato backup non riconosciuto.')
        records = manifest['entries']
        if set(records) != set(names) - {'PULSE-BACKUP-MANIFEST.json'}:
            raise ValueError('Elenco file del backup incompleto.')
        for item in items:
            if item.name == 'PULSE-BACKUP-MANIFEST.json':
                continue
            record = records[item.name]
            if item.isfile():
                with archive.extractfile(item) as content:
                    actual = hashlib.file_digest(content, 'sha256').hexdigest()
                if record != {'kind': 'file', 'size': item.size, 'sha256': actual}:
                    raise ValueError('Verifica backup fallita: ' + item.name)
            elif item.isdir():
                if record != {'kind': 'directory'}:
                    raise ValueError('Cartella backup non valida.')
            elif item.issym():
                if record != {'kind': 'link', 'target': item.linkname}:
                    raise ValueError('Collegamento backup non valido.')
            else:
                raise ValueError('Elemento backup non supportato.')
    return manifest


def create_full_backup(agent, destination=Path('/var/backups/pulse-full'), extra_paths=None):
    """Caller holds the updater lock. Briefly stop PULSE writers, always restart."""
    if agent.journal.exists() or agent.maintenance.exists():
        raise ValueError('Recuperare prima la transazione o manutenzione in corso.')
    agent.current()
    destination.mkdir(mode=0o700, parents=True, exist_ok=True)
    protected_directory(destination)
    paths = [(agent.data, 'var/lib/pulse'), (agent.base, 'opt/pulse'),
             (agent.state, 'var/lib/pulse-updater'), (agent.control, 'usr/local/lib/pulse')]
    if extra_paths is None:
        extra_paths = [(Path('/etc/pulse'), 'etc/pulse'),
            (Path('/etc/nginx/sites-available/pulse'), 'etc/nginx/sites-available/pulse'),
            (Path('/etc/nginx/sites-enabled/pulse'), 'etc/nginx/sites-enabled/pulse'),
            (Path('/etc/php/8.3/fpm/pool.d/pulse.conf'), 'etc/php/8.3/fpm/pool.d/pulse.conf'),
            (Path('/etc/systemd/system/php8.3-fpm.service.d/pulse-recovery.conf'), 'etc/systemd/system/php8.3-fpm.service.d/pulse-recovery.conf')]
        for name in ('pulse-backup@.service', 'pulse-backup-daily.timer', 'pulse-backup-weekly.timer', 'pulse-updates.service', 'pulse-updates.timer', 'pulse-update-recover.service'):
            extra_paths.append((Path('/etc/systemd/system') / name, 'etc/systemd/system/' + name))
        for name in ('pulse-kiosk', 'pulse-enable-kiosk'):
            extra_paths.append((Path('/usr/local/bin') / name, 'usr/local/bin/' + name))
    paths += extra_paths
    for source, _ in paths:
        if destination.resolve().is_relative_to(source.resolve()):
            raise ValueError('Il backup deve essere esterno alle cartelle salvate.')
    size = sum(p.lstat().st_size for p, _ in entries(paths) if stat.S_ISREG(p.lstat().st_mode))
    if shutil.disk_usage(destination).free < size * 2 + 256 * 1024 * 1024:
        raise ValueError('Spazio insufficiente per backup completo e verifica. Nessun dato modificato.')
    units = ('pulse-backup-daily.timer', 'pulse-backup-weekly.timer', 'php8.3-fpm')
    active = [unit for unit in units if agent.systemctl('is-active', unit, check=False).returncode == 0]
    name = 'PULSE-COMPLETO-' + dt.datetime.now().strftime('%Y%m%d-%H%M%S') + '-' + uuid.uuid4().hex[:8] + '.tar.gz'
    target = destination / name
    partial = destination / (name + '.partial')
    try:
        agent.maintenance.write_text('PULSE full backup in progress\n')
        agent.systemctl('stop', *units)
        agent.systemctl('stop', 'pulse-backup@daily.service', 'pulse-backup@weekly.service')
        print('Backup completo in corso. Attendere senza spegnere il PC...', flush=True)
        with tempfile.TemporaryDirectory(prefix='.verify-', dir=destination) as temporary:
            snapshot = Path(temporary) / 'pulse.sqlite'
            database = agent.data / 'pulse.sqlite'
            if database.is_symlink():
                raise ValueError('Database simbolico non previsto.')
            with sqlite3.connect(database.as_uri() + '?mode=ro', uri=True) as original, sqlite3.connect(snapshot) as copy:
                original.backup(copy)
                if copy.execute('PRAGMA integrity_check').fetchone()[0] != 'ok':
                    raise ValueError('Integrita database non valida. Aggiornamento annullato.')
                for (filename,) in copy.execute('SELECT filename FROM media'):
                    if not isinstance(filename, str) or not re.fullmatch(r'[a-f0-9]{40}\.jpg', filename):
                        raise ValueError('Riferimento immagine non valido.')
                    image = agent.data / 'media' / filename
                    if not image.is_file() or image.is_symlink():
                        raise ValueError('Immagine referenziata assente. Aggiornamento annullato.')
            manifest = {'format': 'pulse-full-1', 'version': agent.current().name,
                        'created_at': dt.datetime.now(dt.timezone.utc).isoformat(), 'entries': {}}
            with open(partial, 'xb') as output:
                os.fchmod(output.fileno(), 0o600)
                with tarfile.open(fileobj=output, mode='w:gz', compresslevel=1, dereference=False) as archive:
                    for item, arcname in entries(paths):
                        # SQLite backup includes committed WAL data; WAL/SHM are runtime files.
                        if item in (agent.data / 'pulse.sqlite-wal', agent.data / 'pulse.sqlite-shm', agent.maintenance):
                            continue
                        if item.is_relative_to(agent.data) and item.is_symlink():
                            raise ValueError('Collegamento imprevisto nei dati: ' + str(item))
                        info = archive.gettarinfo(str(item), arcname=arcname)
                        if info.isreg() or info.islnk():
                            content_path = snapshot if item == database else item
                            with open(content_path, 'rb') as content:
                                checksum = hashlib.file_digest(content, 'sha256').hexdigest()
                                content.seek(0)
                                info.type = tarfile.REGTYPE; info.linkname = ''; info.size = os.fstat(content.fileno()).st_size
                                archive.addfile(info, content)
                            record = {'kind': 'file', 'size': info.size, 'sha256': checksum}
                        elif info.isdir():
                            archive.addfile(info); record = {'kind': 'directory'}
                        elif info.issym():
                            archive.addfile(info); record = {'kind': 'link', 'target': info.linkname}
                        else:
                            raise ValueError('File speciale non previsto nel backup: ' + arcname)
                        manifest['entries'][arcname] = record
                    payload = json.dumps(manifest, ensure_ascii=False).encode('utf-8')
                    info = tarfile.TarInfo('PULSE-BACKUP-MANIFEST.json'); info.size = len(payload); info.mode = 0o600
                    archive.addfile(info, io.BytesIO(payload))
                output.flush(); os.fsync(output.fileno())
            verify_archive(partial)
            os.replace(partial, target)
            checksum = digest_file(target)
            with open(str(target) + '.sha256', 'x') as sidecar:
                sidecar.write(checksum + '  ' + target.name + '\n'); sidecar.flush(); os.fsync(sidecar.fileno())
            fd = os.open(destination, os.O_DIRECTORY)
            try: os.fsync(fd)
            finally: os.close(fd)
        print('Backup completo VERIFICATO: ' + str(target), flush=True)
        return target
    finally:
        partial.unlink(missing_ok=True)
        agent.maintenance.unlink(missing_ok=True)
        if active:
            agent.systemctl('start', *active)


def export_backup(path):
    """Copy as the invoking user: no privileged writes in their home."""
    uid = int(os.environ.get('SUDO_UID', '0'))
    if uid <= 0:
        print('Copia protetta disponibile in ' + str(path), flush=True)
        return None
    account = pwd.getpwuid(uid)
    target = Path(account.pw_dir) / 'PULSE-Backup' / path.name
    code = "import pathlib,sys,os,shutil,hashlib; p=pathlib.Path(sys.argv[1]); p.parent.mkdir(mode=0o700,exist_ok=True); f=open(p,'xb'); os.fchmod(f.fileno(),0o600); shutil.copyfileobj(sys.stdin.buffer,f); f.flush(); os.fsync(f.fileno()); f.close(); h=hashlib.file_digest(open(p,'rb'),'sha256').hexdigest(); assert h==sys.argv[2]; pathlib.Path(str(p)+'.sha256').write_text(h+'  '+p.name+chr(10))"
    with open(path, 'rb') as source:
        result = subprocess.run(['/usr/sbin/runuser', '-u', account.pw_name, '--', '/usr/bin/python3', '-c', code, str(target), digest_file(path)], stdin=source, capture_output=True, timeout=3600)
    if result.returncode:
        raise ValueError('Backup protetto riuscito, copia nella cartella personale fallita. File: ' + str(path))
    print('Copia per USB: ' + str(target), flush=True)
    return target
