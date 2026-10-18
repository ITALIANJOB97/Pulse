#!/usr/bin/env python3
"""Linux integration: use the unmodified 0.2.x deployment service on the final ZIP.
Usage: sudo python3 legacy-release.py old-app.zip new-app.zip [blog-stable.json]
PHP/systemd are simulated here; tests/migrate-011.php verifies real PHP migration.
"""
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import shutil
import sys
import tempfile
import zipfile


def load(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def main():
    assert os.geteuid() == 0, 'Run on isolated Linux test host as root for ownership checks.'
    old_zip, new_zip = map(Path, sys.argv[1:3])
    with tempfile.TemporaryDirectory(prefix='pulse-legacy-release-') as directory:
        root = Path(directory)
        # Inputs are local build artifacts, never untrusted network archives.
        with zipfile.ZipFile(old_zip) as z:
            z.extractall(root / 'old')
        old = next((root / 'old').iterdir())
        old_version = (old / 'VERSION').read_text().strip()
        suite = load('legacy_tests', old / 'tests/updater.py')
        u = suite.u
        assert u.UPDATER_VERSION == 1
        if len(sys.argv) > 3:
            manifest = json.loads(Path(sys.argv[3]).read_text())
            u.validate_manifest(manifest)
            assert manifest['min_updater'] <= u.UPDATER_VERSION
            assert manifest['min_from'] == '0.2.0'
            assert manifest['size'] == new_zip.stat().st_size
            assert manifest['sha256'] == hashlib.sha256(new_zip.read_bytes()).hexdigest()
        else:
            manifest = None
        # The real old extractor includes path, permissions, hash manifest and root allowlist checks.
        source = root / 'extracted'
        version = u.extract_release(new_zip, source)
        u.validate_tree(source)
        case = suite.Deployment(methodName='test_success_preserves_all_user_data_and_protected_backup')
        case.setUp()
        try:
            previous = case.agent.current()
            previous.rename(previous.with_name(old_version))
            suite.make_source(previous.with_name(old_version), old_version)
            case.agent.switch(previous.with_name(old_version))
            case.agent.install(source, manifest)
            case.assert_existing()
            assert case.agent.current().name == version
            assert len(list((case.agent.state / 'backups').glob('preupdate-*.zip'))) == 1
            assert not case.agent.maintenance.exists()
            assert not case.agent.journal.exists()
            print(f'Original {old_version} updater installed the complete {version} ZIP; backup and existing data preserved.')
        finally:
            case.tearDown()


if __name__ == '__main__':
    main()
