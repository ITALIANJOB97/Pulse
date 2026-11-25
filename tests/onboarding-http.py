"""Exercise real PHP HTTP/session/CSRF routes using an isolated disposable database."""
import argparse
from contextlib import closing
import http.client
import os
from pathlib import Path
import re
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.parse

parser = argparse.ArgumentParser()
parser.add_argument("--php", required=True)
args = parser.parse_args()
root = Path(__file__).resolve().parent.parent
with tempfile.TemporaryDirectory(prefix="pulse-onboarding-http-") as temporary:
    env = {**os.environ, "PULSE_STORAGE": temporary}
    with socket.socket() as listener:
        listener.bind(("127.0.0.1", 0))
        port = listener.getsockname()[1]
    with open(Path(temporary) / "server.log", "w") as log:
        server = subprocess.Popen([args.php,"-S",f"127.0.0.1:{port}","-t",str(root/"public"),str(root/"public/router.php")],cwd=root,env=env,stdout=log,stderr=log)
        cookie = ""
        def request(path, data=None, extra=None):
            global cookie
            connection = http.client.HTTPConnection("127.0.0.1",port,timeout=5)
            headers = {"Cookie":cookie}
            if data is not None: headers["Content-Type"] = "application/x-www-form-urlencoded"
            headers.update(extra or {})
            connection.request("POST" if data is not None else "GET",path,urllib.parse.urlencode(data) if data is not None else None,headers)
            response = connection.getresponse()
            if response.getheader("Set-Cookie"): cookie = response.getheader("Set-Cookie").split(";",1)[0]
            result = response.status, dict(response.getheaders()), response.read().decode()
            connection.close()
            return result
        def admin_count():
            with closing(sqlite3.connect(Path(temporary)/"pulse.sqlite")) as db:
                return db.execute("SELECT COUNT(*) FROM admins").fetchone()[0]
        try:
            for attempt in range(100):
                try:
                    status,_,body=request("/setup")
                    break
                except (ConnectionError,OSError):time.sleep(.05)
            else:raise AssertionError("PHP did not start")
            assert status==200 and 'name="token"' in body
            subprocess.run([args.php,str(root/"bin/console.php"),"prepare-branch"],env=env,check=True,capture_output=True)
            status,_,body=request("/welcome")
            assert status==200 and 'http-equiv="refresh"' in body
            assert body.count('class="branch-address"') <= 1
            status,_,body=request("/setup")
            assert status==200 and 'name="token"' not in body and 'branch_setup_ready' not in body
            csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',body).group(1)
            form={"action":"setup","return":"/setup","csrf":csrf,"name":"Fixture Manager","email":"manager@example.invalid","password":"temporary-test-only-123","password_repeat":"temporary-test-only-123","store":"Fixture Branch","language":"en","duration":"20"}
            request("/action",{**form,"csrf":"invalid"})
            assert admin_count()==0
            request("/action",form,{"Origin":"http://evil.invalid"})
            assert admin_count()==0
            request("/action",form,{"X-Forwarded-For":"10.1.1.1"})
            assert admin_count()==0
            status,headers,_=request("/action",form,{"Origin":f"http://127.0.0.1:{port}"})
            assert status in (302,303) and headers.get("Location")=="/admin" and admin_count()==1
            assert not (Path(temporary)/"branch-setup.json").exists()
            status,headers,_=request("/welcome")
            assert status in (302,303) and headers.get("Location")=="/display"
            assert request("/display")[0]==200
            request("/action",{**form,"email":"second@example.invalid"})
            assert admin_count()==1
            assert request("/setup")[1].get("Location")=="/login"
            assert subprocess.run([args.php,str(root/"bin/console.php"),"prepare-branch"],env=env,capture_output=True).returncode!=0
            print("HTTP onboarding passed: default code, prepared setup, CSRF/origin/proxy rejection, one admin, welcome-to-display transition, permanently closed setup.")
        finally:
            server.terminate()
            server.wait(timeout=10)
