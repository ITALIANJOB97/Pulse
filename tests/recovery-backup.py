#!/usr/bin/env python3
"""Linux-only backup/repair regressions; all files live in temporary fixtures."""
from pathlib import Path
import importlib.util
import io
import os
import shutil
import sqlite3
import subprocess
import tarfile
import types
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parent.parent
spec = importlib.util.spec_from_file_location('deployment_tests', ROOT/'tests/updater.py')
suite = importlib.util.module_from_spec(spec); spec.loader.exec_module(suite)
import recovery_backup as recovery

class Recovery(unittest.TestCase):
    def setUp(self):
        self.fixture = suite.Deployment(); self.fixture.setUp()
        self.agent = self.fixture.agent
        self.root = self.fixture.root
        self.destination = self.root/'full-backups'
    def tearDown(self):
        self.fixture.tearDown()
    def backup(self):
        return recovery.create_full_backup(self.agent, self.destination, extra_paths=[])
    def test_complete_archive_preserves_database_photos_logs_and_old_backups(self):
        (self.agent.data/'logs').mkdir()
        (self.agent.data/'logs/example.log').write_text('historic data')
        (self.agent.data/'backups/older.zip').write_bytes(b'older backup')
        path = self.backup()
        self.assertEqual(path.stat().st_mode & 0o777, 0o600)
        recovery.verify_archive(path)
        with tarfile.open(path) as archive:
            self.assertEqual(archive.extractfile('var/lib/pulse/media/'+self.fixture.image).read(), b'real immutable image fixture')
            self.assertEqual(archive.extractfile('var/lib/pulse/logs/example.log').read(), b'historic data')
            self.assertEqual(archive.extractfile('var/lib/pulse/backups/older.zip').read(), b'older backup')
            saved = self.root/'saved.sqlite'
            saved.write_bytes(archive.extractfile('var/lib/pulse/pulse.sqlite').read())
            with sqlite3.connect(saved) as db:
                self.assertEqual(db.execute('SELECT password_hash FROM admins').fetchone()[0], 'unchanged-password-hash')
                self.assertEqual(db.execute('PRAGMA integrity_check').fetchone()[0], 'ok')
        self.fixture.assert_existing()
        self.assertFalse(self.agent.maintenance.exists())
    def test_committed_wal_data_is_in_normalized_database(self):
        with sqlite3.connect(self.agent.data/'pulse.sqlite') as db:
            db.execute('PRAGMA journal_mode=WAL')
            db.execute("INSERT INTO employees VALUES(2,'WAL row')"); db.commit()
            path = self.backup()
            with tarfile.open(path) as archive:
                self.assertNotIn('var/lib/pulse/pulse.sqlite-wal', archive.getnames())
                saved = self.root/'restored.sqlite'; saved.write_bytes(archive.extractfile('var/lib/pulse/pulse.sqlite').read())
            with sqlite3.connect(saved) as restored:
                self.assertEqual(restored.execute('SELECT name FROM employees WHERE id=2').fetchone()[0], 'WAL row')
    def test_verification_failure_restarts_services_without_publishing_archive(self):
        with patch.object(recovery, 'verify_archive', side_effect=ValueError('corruption')):
            with self.assertRaisesRegex(ValueError, 'corruption'): self.backup()
        self.assertEqual(list(self.destination.glob('*.tar.gz')), [])
        self.assertEqual(list(self.destination.glob('*.partial')), [])
        self.assertFalse(self.agent.maintenance.exists())
        self.assertIn(('start','pulse-backup-daily.timer','pulse-backup-weekly.timer','php8.3-fpm'), self.agent.calls)
        self.fixture.assert_existing()
    def test_missing_photo_aborts_full_backup(self):
        (self.agent.data/'media'/self.fixture.image).unlink()
        with self.assertRaisesRegex(ValueError, 'Immagine'): self.backup()
        self.assertFalse(self.agent.maintenance.exists())
        self.assertEqual(list(self.destination.glob('*.tar.gz')), [])
    def test_archive_corruption_is_detected(self):
        path=self.backup(); broken=self.root/'corrupt.tar.gz'
        with tarfile.open(path) as original, tarfile.open(broken,'w:gz') as out:
            for info in original.getmembers():
                body=original.extractfile(info).read() if info.isfile() else None
                if info.name.endswith('/logs/example.log'): body=b'changed'
                if info.name=='var/lib/pulse/media/'+self.fixture.image: body=b'changed'
                if body is not None: info.size=len(body)
                out.addfile(info,io.BytesIO(body) if body is not None else None)
        with self.assertRaisesRegex(ValueError, 'Verifica backup'): recovery.verify_archive(broken)
    def test_copy_is_owned_and_readable_by_invoking_user(self):
        path=self.backup()
        userhome=self.root/'operator'; userhome.mkdir(); os.chown(userhome,65534,65534)
        account=types.SimpleNamespace(pw_name='nobody',pw_dir=str(userhome))
        with patch.dict(os.environ, {'SUDO_UID':'65534'}), patch.object(recovery.pwd,'getpwuid',return_value=account):
            exported=recovery.export_backup(path)
        self.assertEqual(exported.stat().st_uid,65534)
        self.assertEqual(exported.stat().st_mode & 0o777,0o600)
        self.assertEqual(recovery.digest_file(exported),recovery.digest_file(path))
        read=subprocess.run(['/usr/sbin/runuser','-u','nobody','--','/usr/bin/test','-r',str(exported)])
        self.assertEqual(read.returncode,0)
    def test_symlink_active_and_unknown_version_are_not_moved(self):
        target=self.agent.base/'releases/0.2.0'
        target.symlink_to(self.fixture.old)
        with self.assertRaises(ValueError): recovery.retire_inactive_release(self.agent,'0.2.0')
        target.unlink()
        with self.assertRaises(ValueError): recovery.retire_inactive_release(self.agent,'0.1.1')
        target.mkdir(); (target/'VERSION').write_text('something else')
        with self.assertRaises(ValueError): recovery.retire_inactive_release(self.agent,'0.2.0')
        self.assertTrue(target.exists())
    def test_transaction_and_writable_target_are_not_moved(self):
        target=self.agent.base/'releases/0.2.0'; target.mkdir(); (target/'VERSION').write_text('0.2.0')
        self.agent.journal.write_text('{}')
        with self.assertRaises(ValueError): recovery.retire_inactive_release(self.agent,'0.2.0')
        self.agent.journal.unlink(); target.chmod(0o777)
        with self.assertRaises(ValueError): recovery.retire_inactive_release(self.agent,'0.2.0')
        self.assertTrue(target.exists())

if __name__=='__main__': unittest.main(verbosity=2)
