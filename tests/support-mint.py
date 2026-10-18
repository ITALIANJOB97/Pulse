#!/usr/bin/env python3
"""Offline support tests, run on Linux: python3 tests/support-mint.py"""
import contextlib
import errno
import importlib.util
import io
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import os
from pathlib import Path
import stat
import sys
import tempfile
import threading
import unittest
from unittest import mock

SOURCE = Path(__file__).resolve().parent.parent
spec = importlib.util.spec_from_file_location("pulse_support", SOURCE / "deploy/support-mint.py")
support = importlib.util.module_from_spec(spec)
spec.loader.exec_module(support)


class HTTPHandler(BaseHTTPRequestHandler):
    visits = []

    def log_message(self, *args):
        pass

    def do_GET(self):
        type(self).visits.append(self.path)
        if self.path == "/redirect":
            self.send_response(302)
            self.send_header("Location", "/secret?key=do-not-log-this")
            body = b""
        elif self.path == "/blank":
            self.send_response(200)
            body = b""
        elif self.path == "/failure":
            self.send_response(503)
            body = b"<h1>PULSE - System check</h1>"
        else:
            self.send_response(200)
            body = (b'<form><input name="action" value="setup">'
                    b'<input name="csrf" value="private-csrf-value"></form>'
                    b'<h1>Private employee name</h1>')
        self.send_header("Content-Type", "text/html")
        self.send_header("Set-Cookie", "session=private-cookie-value")
        self.end_headers()
        self.wfile.write(body)


class SupportTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="pulse-support-test-")
        self.directory = Path(self.temp.name)
        self.report = support.Report(self.directory, "TEST")

    def tearDown(self):
        self.report.close()
        self.temp.cleanup()

    def text(self):
        return self.report.path.read_text(encoding="utf-8")

    def test_sensitive_values_removed(self):
        secrets = [
            "arbitrary-passphrase", "a" * 32, "b" * 64, "private-cookie-value",
            "bearer-credential", "url-passphrase", "url-secret", "test@example.invalid",
            "positional-passphrase", "quoted password with spaces"
        ]
        self.report.write('password=arbitrary-passphrase')
        self.report.write('{"password": "quoted password with spaces"}')
        self.report.write("\n".join([secrets[1], secrets[2]]))
        self.report.write("Set-Cookie: session=private-cookie-value")
        self.report.write("Authorization: Bearer bearer-credential")
        self.report.write("https://user:url-passphrase@example.invalid/release?key=url-secret")
        self.report.write("User: test@example.invalid")
        self.report.write("#0 /opt/pulse/app.php(30): login('user', 'positional-passphrase')")
        for secret in secrets:
            self.assertNotIn(secret, self.text())
        self.report.write("PHP Fatal error: Failed opening required app/bootstrap.php")
        self.assertIn("Failed opening required", self.text())

    def test_installer_failure_preserves_exit_and_redacts_only_file(self):
        worker = self.directory / "worker.sh"
        token = "0123456789abcdef" * 2
        worker.write_text(
            "printf 'ordinary output\\n'\n"
            "printf 'password=keep-out-of-report\\n' >&2\n"
            f"printf '{token}\\n'\n"
            "exit 17\n")
        console = io.StringIO()
        with contextlib.redirect_stdout(console):
            result = support.record_installer(self.report, worker)
        self.assertEqual(result, 17)
        self.assertIn(token, console.getvalue())
        self.assertNotIn(token, self.text())
        self.assertNotIn("keep-out-of-report", self.text())
        self.assertIn("ordinary output", self.text())

    def test_missing_command_and_timeout_keep_report_alive(self):
        self.assertEqual(support.command(self.report, "missing", ["/pulse/nonexistent-command"]), 127)
        self.assertEqual(support.command(self.report, "timeout",
                         [sys.executable, "-c", "import time;time.sleep(2)"], seconds=0.1), 124)
        support.tail_log(self.report, self.directory / "missing.log")
        self.report.write("Reached end")
        self.assertIn("Reached end", self.text())

    def test_distinct_private_reports(self):
        other = support.Report(self.directory, "TEST")
        try:
            self.assertNotEqual(other.path, self.report.path)
            self.assertEqual(stat.S_IMODE(other.path.stat().st_mode), 0o600)
            self.assertEqual(other.path.stat().st_uid, support.owner_ids()[0])
        finally:
            other.close()

    def test_report_survives_usb_without_posix_permissions(self):
        with mock.patch.object(support.os, "fchmod", side_effect=OSError(errno.EPERM, "Unsupported")):
            report = support.Report(self.directory, "USB")
        try:
            report.write("USB report complete")
            self.assertIn("USB report complete", report.path.read_text())
        finally:
            report.close()
    def test_local_http_blank_error_redirect_and_no_body_secrets(self):
        server = ThreadingHTTPServer(("127.0.0.1", 0), HTTPHandler)
        thread = threading.Thread(target=server.serve_forever, daemon=True)
        thread.start()
        HTTPHandler.visits = []
        base = f"http://127.0.0.1:{server.server_port}"
        try:
            # Even an invalid corporate proxy must not affect loopback checks.
            with mock.patch.dict(os.environ, {"http_proxy": "http://127.0.0.1:1",
                                             "HTTP_PROXY": "http://127.0.0.1:1", "no_proxy": ""}):
                for path in ("/blank", "/failure", "/redirect", "/setup"):
                    support.probe_http(self.report, base + path)
            data = self.text()
            self.assertIn("RISPOSTA VUOTA", data)
            self.assertIn("Stato HTTP: 503", data)
            self.assertIn("Pagina controllo requisiti rilevata: True", data)
            self.assertIn("Stato HTTP: 302", data)
            self.assertIn("Modulo setup rilevato: True", data)
            for secret in ("private-csrf-value", "private-cookie-value", "Private employee name", "do-not-log-this"):
                self.assertNotIn(secret, data)
            self.assertEqual(HTTPHandler.visits, ["/blank", "/failure", "/redirect", "/setup"])
        finally:
            server.shutdown()
            server.server_close()
            thread.join()

    def test_install_entry_point_preserves_failure_and_collects_report(self):
        output = self.directory / "entry"
        argv = ["support-mint.py", "install", "--source", str(self.directory), "--output-dir", str(output)]
        with mock.patch.object(sys, "argv", argv), mock.patch.object(support.os, "geteuid", return_value=0), \
             mock.patch.object(support, "record_installer", return_value=23), \
             mock.patch.object(support, "diagnose", side_effect=lambda report: report.write("Diagnosis completed")), \
             contextlib.redirect_stdout(io.StringIO()):
            # On unprivileged CI, leave ownership unchanged while exercising the CLI.
            with mock.patch.object(support.os, "fchown"), mock.patch.object(support.os, "chown"):
                result = support.main()
        self.assertEqual(result, 23)
        reports = list(output.glob("*.txt"))
        self.assertEqual(len(reports), 1)
        data = reports[0].read_text()
        self.assertIn("Esito installer: 23", data)
        self.assertIn("Diagnosis completed", data)


if __name__ == "__main__":
    unittest.main(verbosity=2)
