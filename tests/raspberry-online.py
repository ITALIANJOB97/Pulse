#!/usr/bin/env python3
"""Isolated bootstrap checks: no sudo, services, reboot or real network."""
import hashlib
import contextlib
import importlib.util
import io
from pathlib import Path
import stat
import sys
import tempfile
import unittest
from unittest.mock import patch
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parent.parent
spec = importlib.util.spec_from_file_location("pi_online", ROOT / "deploy/install-raspberry-online.py")
app = importlib.util.module_from_spec(spec)
spec.loader.exec_module(app)


class Bootstrap(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="pulse-online-test-")
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)

    def configured(self, data=b"package"):
        values = dict(RELEASE_TAG="v0.4.0-raspberry-preview.1", VERSION="0.4.0",
                      PACKAGE_SHA256=hashlib.sha256(data).hexdigest(), PACKAGE_SIZE=len(data))
        mocked = patch.multiple(app, **values)
        mocked.start()
        self.addCleanup(mocked.stop)

    def test_unpublished_template_cannot_download(self):
        with self.assertRaisesRegex(RuntimeError, "non pubblicato"):
            app.package_url()

    def test_url_pins_repository_release_and_package(self):
        self.configured()
        self.assertEqual(app.package_url(), "https://github.com/ITALIANJOB97/Pulse/releases/download/v0.4.0-raspberry-preview.1/pulse-raspberry-0.4.0.zip")

    def test_redirects_refuse_other_hosts_http_credentials_ports(self):
        for url in ("http://github.com/a", "https://evil.example/a", "https://github.com.evil.example/a",
                    "https://user:password@github.com/a", "https://github.com:444/a", "file:///tmp/a"):
            with self.subTest(url=url), self.assertRaises(RuntimeError):
                app.GithubRedirect().redirect_request(urllib.request.Request("https://github.com/a"), None, 302, "", {}, url)
        self.assertTrue(app.allowed_download("https://release-assets.githubusercontent.com/a?token=example"))

    def download_fixture(self, payload):
        response = io.BytesIO(payload)
        response.url = "https://release-assets.githubusercontent.com/package"
        mocked = patch.object(app.urllib.request, "build_opener")
        opener = mocked.start()
        self.addCleanup(mocked.stop)
        opener.return_value.open.return_value = response

    def test_complete_download_and_hash(self):
        self.configured(b"verified")
        self.download_fixture(b"verified")
        app.download(self.root / "package.zip")
        self.assertEqual((self.root / "package.zip").read_bytes(), b"verified")

    def test_wrong_hash_truncation_and_oversize(self):
        self.configured(b"verified")
        for index, payload in enumerate((b"tampered", b"part", b"oversized package")):
            with self.subTest(payload=payload):
                self.download_fixture(payload)
                with self.assertRaises(RuntimeError):
                    app.download(self.root / f"package-{index}.zip")

    def zip_fixture(self, extra=None):
        archive = self.root / "package.zip"
        with zipfile.ZipFile(archive, "w") as package:
            for name, body in (("VERSION", "0.4.0"), ("deploy/install-raspberry.sh", "fixture"),
                               ("bin/package_compatibility.py", "fixture"), ("deploy/updater.py", "fixture")):
                package.writestr("PULSE/" + name, body)
            if extra:
                package.writestr(*extra)
        return archive

    def test_extract_valid_archive(self):
        self.configured()
        app.extract(self.zip_fixture(), self.root / "extracted")
        self.assertEqual((self.root / "extracted/VERSION").read_text(), "0.4.0")

    def test_extract_refuses_traversal_and_symlinks(self):
        self.configured()
        symlink = zipfile.ZipInfo("PULSE/link")
        symlink.external_attr = (stat.S_IFLNK | 0o777) << 16
        for index, item in enumerate((("PULSE/../../outside", "bad"), (symlink, "/etc/passwd"), ("OTHER/file", "bad"))):
            with self.subTest(index=index), self.assertRaises(RuntimeError):
                app.extract(self.zip_fixture(item), self.root / f"extracted-{index}")
        self.assertFalse((self.root.parent / "outside").exists())

    def test_configures_user_autostart_before_raspberry_desktop(self):
        with patch.object(app, "run") as run, patch.object(app, "output", side_effect=["1", "0", "1"]):
            app.configure_tv()
        commands = [call.args for call in run.call_args_list]
        self.assertEqual(commands[0], ("/usr/local/bin/pulse-enable-kiosk",))
        self.assertIn(("sudo", "raspi-config", "nonint", "do_boot_behaviour", "B4"), commands)
        self.assertIn(("sudo", "raspi-config", "nonint", "do_blanking", "1"), commands)
        self.assertTrue(all("reboot" not in cmd for cmd in commands))

    def test_bad_autologin_is_not_reported_as_success(self):
        with patch.object(app, "run"), patch.object(app, "output", side_effect=["1", "1"]):
            with self.assertRaisesRegex(RuntimeError, "automatico"):
                app.configure_tv()

    def main_fixture(self, argv, download_error=None, tv_error=None):
        self.configured()
        work = self.root / "work"
        work.mkdir()
        with patch.object(sys, "argv", ["installer", *argv]), patch.object(app, "preflight") as preflight, \
                patch.object(app.tempfile, "mkdtemp", return_value=str(work)), \
                patch.object(app, "download", side_effect=download_error) as download, \
                patch.object(app, "extract"), patch.object(app, "run") as run, \
                patch.object(app, "configure_tv", side_effect=tv_error) as tv:
            if download_error or tv_error:
                with self.assertRaises(RuntimeError):
                    app.main()
            else:
                app.main()
        return preflight, download, run, tv

    def test_branch_setup_flag_and_reboot_only_after_success(self):
        _, download, run, tv = self.main_fixture(["--branch-setup", "--reboot"])
        commands = [call.args for call in run.call_args_list]
        self.assertEqual(commands[-1], ("sudo", "systemctl", "reboot"))
        self.assertIn("--branch-setup", commands[1])
        download.assert_called_once()
        tv.assert_called_once()

    def test_integrity_failure_never_installs_or_reboots(self):
        _, _, run, tv = self.main_fixture(["--reboot"], download_error=RuntimeError("hash"))
        self.assertEqual([call.args for call in run.call_args_list], [("sudo", "-v")])
        tv.assert_not_called()

    def test_tv_failure_never_reboots(self):
        _, _, run, _ = self.main_fixture(["--reboot"], tv_error=RuntimeError("autologin"))
        self.assertFalse(any("reboot" in call.args for call in run.call_args_list))

    def test_tv_recovery_never_downloads_or_reinstalls(self):
        preflight, download, run, tv = self.main_fixture(["--configure-tv"])
        preflight.assert_called_once_with(True)
        download.assert_not_called()
        self.assertEqual([call.args for call in run.call_args_list], [("sudo", "-v")])
        tv.assert_called_once()

    def test_default_install_keeps_setup_code_and_does_not_reboot(self):
        _, _, run, _ = self.main_fixture([])
        commands = [call.args for call in run.call_args_list]
        self.assertNotIn("--branch-setup", commands[1])
        self.assertFalse(any("reboot" in cmd for cmd in commands))

    def test_preflight_refuses_root(self):
        with patch.object(sys, "platform", "linux"), patch.object(app.os, "geteuid", return_value=0, create=True):
            with self.assertRaisesRegex(RuntimeError, "SENZA sudo"):
                app.preflight()

    def test_preflight_refuses_installed_and_wrong_architecture(self):
        with contextlib.ExitStack() as stack:
            stack.enter_context(patch.object(sys, "platform", "linux"))
            stack.enter_context(patch.object(app.os, "geteuid", return_value=1000, create=True))
            stack.enter_context(patch.object(Path, "read_text", return_value='ID=debian\nVERSION_ID="13"\nVERSION_CODENAME=trixie'))
            stack.enter_context(patch.object(Path, "read_bytes", return_value=b"Raspberry Pi 3 Model B Plus"))
            stack.enter_context(patch.object(Path, "is_dir", return_value=True))
            stack.enter_context(patch.object(app.shutil, "which", return_value="/usr/bin/tool"))
            exists = stack.enter_context(patch.object(app.os.path, "lexists", return_value=True))
            command = stack.enter_context(patch.object(app, "output", return_value="armhf"))
            with self.assertRaisesRegex(RuntimeError, "64 bit"):
                app.preflight()
            exists.assert_not_called()
            command.return_value = "arm64"
            with self.assertRaisesRegex(RuntimeError, "gia presente"):
                app.preflight()
            exists.return_value = False
            app.preflight()


if __name__ == "__main__":
    unittest.main()
