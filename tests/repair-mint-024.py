#!/usr/bin/env python3
"""Linux regression for the operator repair; original released ZIP supplied as argv[1]."""
from pathlib import Path
import hashlib
import importlib.util
import json
import sys
import subprocess
import tempfile
import threading
import shutil
from unittest.mock import patch
import urllib.request
from http.server import HTTPServer, BaseHTTPRequestHandler
ROOT=Path(__file__).resolve().parent.parent
spec=importlib.util.spec_from_file_location('repair',ROOT/'deploy/repair-mint-024.py')
repair=importlib.util.module_from_spec(spec);spec.loader.exec_module(repair)
with tempfile.TemporaryDirectory(prefix='pulse-repair-proof-') as folder:
    root=Path(folder);root.chmod(0o755)
    u,source=repair.prepare(Path(sys.argv[1]),root/'source')
    original=(source/'deploy/updater.py').read_bytes()
    control=root/'control';control.mkdir();(control/'updater.py').write_bytes(original);(control/'updater.py').chmod(0o644)
    repair.patch_control(control,original);repair.patch_control(control,original)
    assert b'class NoRedirect' in (control/'updater.py').read_bytes()
    assert (control/'updater.py.before-noredirect-fix').read_bytes()==original
    assert hashlib.sha256((source/'deploy/updater.py').read_bytes()).hexdigest()==repair.UPDATER_SHA256
    class Handler(BaseHTTPRequestHandler):
        redirect=False
        def log_message(self,*args):pass
        def do_GET(self):
            if self.redirect:
                self.send_response(302);self.send_header('Location','http://127.0.0.1:1/forbidden');self.end_headers();return
            self.send_response(200);self.end_headers()
            self.wfile.write(json.dumps({'ok':True,'version':'0.2.4','schema':1}).encode() if self.path=='/health' else b'/* asset */')
    server=HTTPServer(('127.0.0.1',8080),Handler);thread=threading.Thread(target=server.serve_forever,daemon=True);thread.start()
    try:
        class Agent:
            php='/usr/bin/php8.3'
            expected_schema=1
            def run(self,*args,**kwargs):pass
        u.Updater.health(Agent(),Path('0.2.4'))
        Handler.redirect=True
        try:
            urllib.request.build_opener(u.NoRedirect).open('http://127.0.0.1:8080/health',timeout=2)
            raise AssertionError('Redirect accepted')
        except ValueError:pass
        Handler.redirect=False
        spec=importlib.util.spec_from_file_location('old_suite',source/'tests/updater.py')
        suite=importlib.util.module_from_spec(spec);spec.loader.exec_module(suite)
        test=suite.Deployment();test.setUp()
        try:
            # Match the version observed on the shop PC.
            old = test.agent.base/'releases/0.2.3'
            test.old.rename(old); (old/'VERSION').write_text('0.2.3\n')
            test.agent.switch(old)
            original_run=test.agent.run
            test.agent.run=lambda args,**kwargs:subprocess.CompletedProcess(args,0,"","") if args[-1]=="health" else original_run(args,**kwargs)
            test.agent.health=lambda release:u.Updater.health(test.agent,release)
            # Reproduce the actual Mint report: an inactive 0.2.4 exists but
            # the UI retry already cleared its failed_version marker.
            stale = test.agent.base / 'releases/0.2.4'
            shutil.copytree(source, stale)
            test.agent.save_status(failed_version=None)
            try:
                test.agent.install(source)
                raise AssertionError('The original published updater should reproduce the failure')
            except ValueError as error:
                assert str(error) == 'Release directory already exists'
            real_backup = repair.recovery_backup.create_full_backup
            def isolated_backup(agent):
                return real_backup(agent, root/'full-backups', extra_paths=[])
            with patch.object(repair.recovery_backup, 'create_full_backup', side_effect=isolated_backup), patch.object(repair.recovery_backup, 'export_backup'):
                u.Updater.install(test.agent, source)
            assert len(list((test.agent.base/'releases').glob('0.2.4.preserved-*'))) == 1
            full = list((root/'full-backups').glob('*.tar.gz'))
            assert len(full) == 1
            repair.recovery_backup.verify_archive(full[0])
            assert test.agent.current().name=='0.2.4';test.assert_existing()
            assert len(list((test.agent.state/'backups').glob('preupdate-*.zip')))==1
        finally:test.tearDown()
    finally:server.shutdown();server.server_close();thread.join()
    print('PASS: reproduced Release directory already exists; full backup verified before preserving stale release; original ZIP unchanged; idempotent root patch with backup; real HTTP health succeeds; redirects blocked; immediate 0.2.4 transaction preserves user data and safety backup.')
