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
            original_run=test.agent.run
            test.agent.run=lambda args,**kwargs:subprocess.CompletedProcess(args,0,"","") if args[-1]=="health" else original_run(args,**kwargs)
            test.agent.health=lambda release:u.Updater.health(test.agent,release)
            test.agent.install(source)
            assert test.agent.current().name=='0.2.4';test.assert_existing()
            assert len(list((test.agent.state/'backups').glob('preupdate-*.zip')))==1
        finally:test.tearDown()
    finally:server.shutdown();server.server_close();thread.join()
    print('PASS: original ZIP unchanged; idempotent root patch with backup; real HTTP health succeeds; redirects blocked; immediate 0.2.4 transaction preserves user data and safety backup.')
