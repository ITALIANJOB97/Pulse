#!/usr/bin/env python3
"""Linux-only deployment transaction tests in isolated temporary paths.
Real SQLite/media backup verification and restoration; systemd and PHP commands
are simulated. No installed /opt/pulse or /var/lib/pulse paths are modified.
"""
import base64
import datetime as dt
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import shutil
import sqlite3
import stat
import subprocess
import tempfile
import unittest
from unittest.mock import patch
import zipfile

ROOT = Path(__file__).resolve().parent.parent
spec = importlib.util.spec_from_file_location("updater", ROOT / "deploy/updater.py")
u = importlib.util.module_from_spec(spec)
spec.loader.exec_module(u)

def make_source(root, version="0.2.0"):
    files = {"VERSION": version + "\n", "app/bootstrap.php": "<?php // fixture\n",
             "public/index.php": "<?php // fixture\n", "bin/console.php": "<?php // fixture\n"}
    for name, content in files.items():
        p = root / name
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text(content)
    (root / "CONTENTS.sha256").write_text("".join(hashlib.sha256(text.encode()).hexdigest() + "  " + name + "\n" for name, text in files.items()))
    return root

class Fake(u.Updater):
    migration_fails = False
    health_fails = False
    crash_at = None
    corrupt_backup = False
    def systemctl(self, *args, check=True):
        self.calls.append(args)
        return subprocess.CompletedProcess(args, 0, "", "")
    def run(self, args, as_web=False, timeout=300, check=True):
        a = list(map(str, args))
        if a[0] == self.php:
            if "-l" in a or "-r" in a:
                return subprocess.CompletedProcess(a, 0, "8.3.6", "")
            if a[-2:] == ["backup", "manual"]:
                dbcopy = self.data / "snapshot.tmp"
                with sqlite3.connect(self.data / "pulse.sqlite") as src, sqlite3.connect(dbcopy) as dst:
                    src.backup(dst)
                name = "manual-20261017-030000-" + os.urandom(3).hex() + ".zip"
                out = self.data / "backups" / name
                with zipfile.ZipFile(out, "w") as z:
                    z.write(dbcopy, "pulse.sqlite")
                    z.writestr("manifest.json", json.dumps({"app":"pulse-dietikon","database_sha256":hashlib.sha256(dbcopy.read_bytes()).hexdigest()}))
                    for file in (self.data / "media").glob("*.jpg"):
                        z.write(file, "media/" + file.name)
                dbcopy.unlink()
                if self.corrupt_backup:
                    out.write_bytes(b"broken zip")
                os.chown(out, self.account.pw_uid, self.account.pw_gid)
                os.chmod(out, 0o640)
                return subprocess.CompletedProcess(a, 0, name + "\n", "")
            if a[-1] == "migrate":
                with sqlite3.connect(self.data / "pulse.sqlite") as db:
                    db.execute("UPDATE settings SET value=? WHERE key='installed_version'", (json.dumps("0.2.0"),))
                    db.execute("INSERT INTO settings VALUES('migration_marker','true')")
                if self.crash_at == "migration":
                    raise SystemExit("simulated power loss")
                if self.migration_fails:
                    raise RuntimeError("simulated migration failure")
                return subprocess.CompletedProcess(a, 0, "", "")
        return super().run(args, as_web, timeout, check)
    def health(self, release):
        if self.crash_at == "health":
            raise SystemExit("simulated power loss after switch")
        if self.health_fails:
            raise RuntimeError("simulated HTTP failure")
    def save_status(self, **changes):
        if self.crash_at == "commit" and changes.get("phase") == "installed":
            raise SystemExit("simulated power loss after commit")
        return super().save_status(**changes)

class Deployment(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="pulse-update-test-")
        self.root = Path(self.temp.name)
        self.root.chmod(0o755)
        self.base = self.root / "opt"
        (self.base / "releases").mkdir(parents=True)
        self.old = make_source(self.base / "releases/0.1.1", "0.1.1")
        (self.base / "current").symlink_to(self.old)
        self.source = make_source(self.root / "package")
        self.data = self.root / "data"
        for name in ("media", "backups", "sessions"):
            (self.data / name).mkdir(parents=True)
        self.image = "a"*40 + ".jpg"
        (self.data / "media" / self.image).write_bytes(b"real immutable image fixture")
        with sqlite3.connect(self.data / "pulse.sqlite") as db:
            db.executescript("CREATE TABLE admins(id INTEGER, name TEXT, password_hash TEXT);"
                "INSERT INTO admins VALUES(1,'Existing Admin','unchanged-password-hash');"
                "CREATE TABLE employees(id INTEGER,name TEXT);INSERT INTO employees VALUES(1,'Existing Employee');"
                "CREATE TABLE settings(key TEXT PRIMARY KEY,value TEXT);"
                "INSERT INTO settings VALUES('installed_version','\"0.1.1\"');"
                "CREATE TABLE media(filename TEXT);"
                "CREATE TABLE content(module TEXT, payload TEXT);INSERT INTO content VALUES('daily','existing tasks');")
            db.execute("INSERT INTO media VALUES(?)",(self.image,))
        self.control = self.root / "control"
        self.control.mkdir()
        shutil.copyfile(ROOT / "deploy/restore-update.py", self.control / "restore-update.py")
        self.agent = Fake(self.root / "state", self.base, self.data, self.root / "config.json", self.control)
        self.agent.calls = []
        self.agent.config.write_text(json.dumps(u.GITHUB_CONFIG))
        shutil.copyfile(ROOT / "deploy/release-public.key", self.control / "release-public.key")
        for path in [self.data, *self.data.rglob("*")]:
            os.chown(path, self.agent.account.pw_uid, self.agent.account.pw_gid)
            path.chmod(0o770 if path.is_dir() else 0o660)
        self.agent.prepare()
    def tearDown(self):
        self.temp.cleanup()
    def dbvalue(self, sql):
        with sqlite3.connect(self.data / "pulse.sqlite") as db:
            return db.execute(sql).fetchone()[0]
    def assert_existing(self):
        self.assertEqual(self.dbvalue("SELECT name FROM employees"), "Existing Employee")
        self.assertEqual(self.dbvalue("SELECT password_hash FROM admins"), "unchanged-password-hash")
        self.assertEqual(self.dbvalue("SELECT payload FROM content"), "existing tasks")
        self.assertEqual((self.data / "media" / self.image).read_bytes(), b"real immutable image fixture")
    def test_success_preserves_all_user_data_and_protected_backup(self):
        self.agent.install(self.source)
        self.assert_existing()
        self.assertEqual(self.agent.current().name, "0.2.0")
        self.assertFalse(self.agent.journal.exists())
        self.assertFalse(self.agent.maintenance.exists())
        backups = list((self.agent.state / "backups").glob("preupdate-*"))
        self.assertEqual(len(backups), 1)
        self.assertEqual(backups[0].stat().st_mode & 0o777, 0o640)
        self.assertEqual(backups[0].stat().st_uid, 0)
        self.assertIn(("stop", "pulse-backup-daily.timer", "pulse-backup-weekly.timer"), self.agent.calls)
    def test_failed_migration_restores_old_database_and_code(self):
        self.agent.migration_fails = True
        with self.assertRaises(RuntimeError):
            self.agent.install(self.source)
        self.assert_existing()
        self.assertEqual(self.agent.current().name, "0.1.1")
        self.assertEqual(self.dbvalue("SELECT value FROM settings WHERE key='installed_version'"), '"0.1.1"')
        self.assertEqual(self.dbvalue("SELECT COUNT(*) FROM settings WHERE key='migration_marker'"), 0)
        self.assertEqual(u.read_json(self.agent.status_file)["phase"], "rolled_back")
    def test_rollback_restores_changed_and_missing_images(self):
        original_health = self.agent.health
        def failed_health(release):
            (self.data / "media" / self.image).write_bytes(b"migration changed image")
            raise RuntimeError("bad release")
        self.agent.health = failed_health
        with self.assertRaises(RuntimeError):
            self.agent.install(self.source)
        self.assert_existing()
        self.assertEqual(self.agent.current().name,"0.1.1")
    def test_failed_health_restores_code_and_database(self):
        self.agent.health_fails = True
        with self.assertRaises(RuntimeError):
            self.agent.install(self.source)
        self.assert_existing()
        self.assertEqual(self.agent.current().name, "0.1.1")
        self.assertFalse(self.agent.maintenance.exists())
    def test_power_loss_after_migration_recovers_before_php(self):
        self.agent.crash_at = "migration"
        with self.assertRaises(SystemExit):
            self.agent.install(self.source)
        self.assertTrue(self.agent.journal.exists())
        self.assertTrue(self.agent.maintenance.exists())
        self.agent.crash_at = None
        self.agent.recover(boot=True)
        self.assert_existing()
        self.assertEqual(self.agent.current().name, "0.1.1")
        self.assertEqual(self.dbvalue("SELECT COUNT(*) FROM settings WHERE key='migration_marker'"), 0)
    def test_power_loss_after_switch_recovers_previous_release(self):
        self.agent.crash_at = "health"
        with self.assertRaises(SystemExit):
            self.agent.install(self.source)
        self.assertEqual(self.agent.current().name, "0.2.0")
        self.agent.crash_at = None
        self.agent.recover(boot=True)
        self.assertEqual(self.agent.current().name, "0.1.1")
        self.assert_existing()
    def test_power_loss_after_durable_commit_keeps_new_data(self):
        self.agent.crash_at = "commit"
        with self.assertRaises(SystemExit):
            self.agent.install(self.source)
        self.agent.crash_at = None
        self.agent.recover(boot=True)
        self.assertEqual(self.agent.current().name, "0.2.0")
        self.assert_existing()
        self.assertEqual(self.dbvalue("SELECT COUNT(*) FROM settings WHERE key='migration_marker'"), 1)
    def test_failed_backup_never_migrates(self):
        self.agent.corrupt_backup = True
        with self.assertRaises(RuntimeError):
            self.agent.install(self.source)
        self.assert_existing()
        self.assertEqual(self.agent.current().name, "0.1.1")
        self.assertFalse(self.agent.journal.exists())
        self.assertFalse(self.agent.maintenance.exists())
    def test_no_disk_space_leaves_old_release_running(self):
        with patch.object(u.shutil, "disk_usage", return_value=shutil._ntuple_diskusage(100,100,0)):
            with self.assertRaises(ValueError):
                self.agent.install(self.source)
        self.assertEqual(self.agent.calls, [])
        self.assert_existing()
    def test_reinstall_and_downgrade_are_rejected(self):
        make_source(self.source, "0.1.1")
        with self.assertRaises(ValueError):
            self.agent.install(self.source)
        self.assert_existing()
        self.assertEqual(self.agent.calls, [])
    def test_failed_rollback_keeps_maintenance_and_recovery_journal(self):
        self.agent.health_fails = True
        with patch.object(self.agent, "restore", side_effect=RuntimeError("disk problem")):
            with self.assertRaises(RuntimeError):
                self.agent.install(self.source)
        self.assertTrue(self.agent.journal.exists())
        self.assertTrue(self.agent.maintenance.exists())
        self.agent.recover(boot=True)
        self.assert_existing()
        self.assertEqual(self.agent.current().name, "0.1.1")
    def channel(self):
        package = self.agent.state / "downloads/0.2.0.zip"
        with zipfile.ZipFile(package,"w") as z:
            for file in self.source.rglob("*"):
                if file.is_file():z.write(file,"PULSE-test/"+file.relative_to(self.source).as_posix())
        return {"version":"0.2.0","min_from":"0.1.1","min_updater":1,"db_schema":1,"php_min":"8.3.0","size":package.stat().st_size,"sha256":u.digest(package),"url":u.ORIGIN+"v0.2.0/pulse-0.2.0.zip","notes":{"en":"Test","de":"Test","fr":"Test"}}
    def preference(self,key,value):
        with sqlite3.connect(self.data / "pulse.sqlite") as db:
            db.execute("INSERT INTO settings VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value",(key,json.dumps(value)))
    def test_tick_downloaded_release_waits_until_night(self):
        release=self.channel()
        with patch.object(self.agent,"signed_manifest",return_value=release),patch.object(u,"now",return_value=dt.datetime(2026,10,17,12,tzinfo=dt.timezone.utc)):
            self.agent.tick()
        self.assertEqual(self.agent.current().name,"0.1.1")
        self.assertEqual(u.read_json(self.agent.status_file)["phase"],"scheduled")
        self.assertEqual(self.agent.calls,[])
    def test_tick_installs_in_night_window(self):
        release=self.channel()
        with patch.object(self.agent,"signed_manifest",return_value=release),patch.object(u,"now",return_value=dt.datetime(2026,10,17,1,15,tzinfo=dt.timezone.utc)):
            self.agent.tick()
        self.assertEqual(self.agent.current().name,"0.2.0")
        self.assert_existing()
    def test_paused_and_postponed_never_install(self):
        release=self.channel()
        self.preference("update_auto",False)
        clock=dt.datetime(2026,10,17,1,15,tzinfo=dt.timezone.utc)
        with patch.object(self.agent,"signed_manifest",return_value=release),patch.object(u,"now",return_value=clock):
            self.agent.tick()
            self.assertEqual(u.read_json(self.agent.status_file)["phase"],"paused")
            self.preference("update_auto",True)
            self.preference("update_postpone_until",int(clock.timestamp())+86400)
            self.agent.tick()
            self.assertEqual(u.read_json(self.agent.status_file)["phase"],"postponed")
        self.assertEqual(self.agent.calls,[])
    def test_unavailable_or_invalid_signature_never_changes_data(self):
        with patch.object(self.agent,"signed_manifest",side_effect=ValueError("untrusted signature")):
            self.agent.tick()
        self.assertEqual(u.read_json(self.agent.status_file)["phase"],"offline")
        self.assert_existing()
        self.assertEqual(self.agent.calls,[])
    def test_incompatible_release_requires_manual_upgrade(self):
        release=self.channel();release["min_updater"]=u.UPDATER_VERSION+1
        with patch.object(self.agent,"signed_manifest",return_value=release):self.agent.tick()
        self.assertEqual(u.read_json(self.agent.status_file)["phase"],"incompatible")
        self.assertEqual(self.agent.calls,[])
    def test_failed_version_not_retried_without_admin_request(self):
        release=self.channel();self.agent.save_status(failed_version="0.2.0",phase="rolled_back")
        with patch.object(self.agent,"signed_manifest",return_value=release),patch.object(u,"now",return_value=dt.datetime(2026,10,17,1,15,tzinfo=dt.timezone.utc)):
            self.agent.tick()
            self.assertEqual(self.agent.calls,[])
            self.preference("update_retry","retry-1")
            self.agent.tick()
        self.assertEqual(self.agent.current().name,"0.2.0")
    def test_bootstrap_permissions_with_production_umask(self):
        shutil.rmtree(self.control)
        (self.source / "deploy").mkdir()
        for name in ("updater.py","update_protocol.py","update-source.json","restore-update.py","verify-release.php","release-public.key","pulse-updates.service","pulse-updates.timer","pulse-update-recover.service","pulse-backup@.service"):
            shutil.copyfile(ROOT / "deploy" / name,self.source / "deploy" / name)
        etc=self.root / "etc"
        (etc / "systemd/system").mkdir(parents=True)
        self.agent.config=etc / "pulse/updater.json"
        path_class=u.Path
        def redirected_path(value):
            value=str(value)
            return etc / value.removeprefix("/etc/") if value.startswith("/etc/") else path_class(value)
        self.agent.config.parent.mkdir(parents=True)
        self.agent.config.write_text(json.dumps(u.LEGACY_CONFIG))
        self.agent.config.chmod(0o644)
        (self.agent.state / "downloads/stable.json").write_text('old channel')
        old_mask=os.umask(0o027)
        try:
            with patch.object(u,"Path",side_effect=redirected_path):u.bootstrap(self.agent,self.source)
        finally:os.umask(old_mask)
        self.assertEqual(self.control.stat().st_mode & 0o777,0o755)
        self.assertEqual(self.agent.config.parent.stat().st_mode & 0o777,0o755)
        self.agent.run(["/usr/bin/test","-r",self.agent.config],as_web=True)
        self.assertTrue(self.agent.preferences()["auto"])
        self.assertEqual(u.read_json(self.agent.config),u.BLOG_CONFIG)
        self.assertFalse((self.agent.state / "downloads/stable.json").exists())
    def test_first_bootstrap_works_before_helper_is_installed(self):
        (self.control / "restore-update.py").unlink()
        read = u.Path.read_text
        def mocked_read(path,*args,**kwargs):
            if str(path)=="/etc/os-release":return 'ID=linuxmint\nVERSION_ID="22.3"\n'
            return read(path,*args,**kwargs)
        def bootstrap(agent,source):
            self.assertFalse((self.control / "restore-update.py").exists())
            shutil.copyfile(ROOT / "deploy/restore-update.py",self.control / "restore-update.py")
            (self.control / "restore-update.py").chmod(0o644)
        old_mask=os.umask(0o022)
        try:
            with patch.object(u,"Updater",return_value=self.agent),patch.object(u,"bootstrap",side_effect=bootstrap),patch.object(u.Path,"read_text",mocked_read),patch.object(u.sys,"argv",["updater.py","install","--source",str(self.source)]):
                u.main()
        finally:os.umask(old_mask)
        self.assert_existing()
        self.assertEqual(self.agent.current().name,"0.2.0")
    def test_credential_permissions_and_symlinks(self):
        with patch.object(self.agent,"configuration",return_value=u.PRIVATE_GITHUB_CONFIG):
            self.check_private_credential_permissions()
    def check_private_credential_permissions(self):
        token=self.agent.config.parent / "github-token"
        with self.assertRaises(ValueError):self.agent.github_token()
        token.write_text("github_pat_"+"x"*30+"\n");token.chmod(0o600)
        self.assertEqual(self.agent.github_token(),"github_pat_"+"x"*30)
        token.chmod(0o640)
        with self.assertRaises(ValueError):self.agent.github_token()
        token.unlink();token.symlink_to(self.data / "pulse.sqlite")
        with self.assertRaises(ValueError):self.agent.github_token()
    def test_public_mode_ignores_missing_expired_or_unsafe_token(self):
        with patch.object(self.agent,"configuration",return_value=u.GITHUB_CONFIG):
            self.assertIsNone(self.agent.github_token())
            token=self.agent.config.parent / "github-token"
            token.write_text("expired-invalid-token");token.chmod(0o644)
            self.assertIsNone(self.agent.github_token())
            token.unlink();token.symlink_to(self.data / "pulse.sqlite")
            self.assertIsNone(self.agent.github_token())
    def test_download_package_bound_to_signed_version(self):
        metadata={"tag_name":"v0.2.0","draft":False,"prerelease":False,"assets":[
            {"name":name,"state":"uploaded","url":"https://api.github.com/repos/ITALIANJOB97/Pulse/releases/assets/"+str(i+1),"browser_download_url":u.ORIGIN+"v0.2.0/"+name}
            for i,name in enumerate(("stable.json","stable.json.sig","pulse-0.2.0.zip"))]}
        u.atomic_json(self.agent.state / "downloads/github-release.json",metadata)
        release=self.channel()
        with patch.object(self.agent,"github_token",return_value="test-token"),patch.object(u,"download",side_effect=lambda url,target,*args,**kwargs:target.write_bytes(b"package")) as download:
            self.agent.download_package(release,self.root/"download.zip")
            self.assertEqual(download.call_args.kwargs["token"],"test-token")
            self.assertTrue(u.github_api_asset(download.call_args.args[0]))
            with self.assertRaises(ValueError):self.agent.download_package({**release,"version":"0.2.1"},self.root/"download.zip")
    def test_bootstrap_rejects_unknown_configuration_before_changes(self):
        (self.source/"deploy").mkdir()
        for name in ("updater.py","update_protocol.py","update-source.json","restore-update.py","verify-release.php","release-public.key"):
            shutil.copyfile(ROOT/"deploy"/name,self.source/"deploy"/name)
        self.agent.config.write_text('{"schema":99}');self.agent.config.chmod(0o600)
        with self.assertRaises(ValueError):u.bootstrap(self.agent,self.source)
        self.assertFalse((self.control/"updater.py").exists())
    def test_preferences_read_with_application_privileges(self):
        prefs = self.agent.preferences()
        self.assertTrue(prefs["auto"])
        self.assertEqual(prefs["hour"], 3)

class Protocol(unittest.TestCase):
    def test_source_checksum_tamper_rejected(self):
        with tempfile.TemporaryDirectory() as t:
            root=make_source(Path(t))
            (root/"public/index.php").write_text("tampered")
            with self.assertRaises(ValueError):u.validate_tree(root)
    def test_unsafe_zip_paths_links_duplicates_rejected(self):
        cases=[("../outside","text",None),("release/../../outside","text",None),("release/public/link","text",stat.S_IFLNK|0o777),("release/config/local.php","secret",None)]
        for name,content,mode in cases:
            with self.subTest(name=name), tempfile.TemporaryDirectory() as t:
                p=Path(t);zpath=p/"bad.zip"
                with zipfile.ZipFile(zpath,"w") as z:
                    entry=zipfile.ZipInfo(name)
                    if mode:entry.external_attr=mode<<16
                    z.writestr(entry,content)
                with self.assertRaises((ValueError,FileNotFoundError)):u.extract_release(zpath,p/"out")
    def test_origin_rejects_redirect_targets_credentials_and_encoded_paths(self):
        for url in ("http://github.com/ITALIANJOB97/Pulse/releases/download/v0.2.2/pulse-0.2.2.zip", "https://evil.invalid/a.zip",u.ORIGIN+"../a.zip",u.ORIGIN+"%2e%2e/a.zip",u.ORIGIN+"v0.2.2/pulse-0.2.2.zip?x=1",u.ORIGIN+"v0.2.2/other.zip"):
            self.assertFalse(u.allowed_url(url))
        self.assertTrue(u.allowed_url(u.ORIGIN+"v0.2.2/pulse-0.2.2.zip"))
    def github_metadata(self):
        tag="v0.2.2"
        return {"tag_name":tag,"draft":False,"prerelease":False,"assets":[
            {"name":name,"state":"uploaded","url":"https://api.github.com/repos/ITALIANJOB97/Pulse/releases/assets/"+str(i+1),"browser_download_url":u.ORIGIN+tag+"/"+name}
            for i,name in enumerate(("stable.json","stable.json.sig","pulse-0.2.2.zip"))]}
    def test_private_release_assets_use_repository_api(self):
        metadata=self.github_metadata();version,assets=u.release_asset_urls(metadata)
        self.assertEqual(version,"0.2.2")
        self.assertTrue(all(u.github_api_asset(url) for url in assets.values()))
        for key,value in (("draft",True),("prerelease",True),("tag_name","main"),("assets",[])):
            with self.subTest(key=key),self.assertRaises(ValueError):u.release_asset_urls({**metadata,key:value})
        metadata["assets"][0]["url"]="https://api.github.com/repos/attacker/Pulse/releases/assets/1"
        with self.assertRaises(ValueError):u.release_asset_urls(metadata)
    def test_cdn_redirect_removes_credentials(self):
        req=u.urllib.request.Request("https://api.github.com/repos/ITALIANJOB97/Pulse/releases/assets/123",headers={"Authorization":"Bearer test-secret","Cookie":"private=value"})
        cdn="https://release-assets.githubusercontent.com/github-production-release-asset/123/file?sig=example"
        redirected=u.TrustedRedirect().redirect_request(req,None,302,"Found",{},cdn)
        self.assertIsNone(redirected.get_header("Authorization"))
        self.assertIsNone(redirected.get_header("Cookie"))
        self.assertEqual(redirected.full_url,cdn)
        for target in ("http://release-assets.githubusercontent.com/github-production-release-asset/1/file", "https://evil.invalid/a", "https://release-assets.githubusercontent.com.evil.invalid/github-production-release-asset/a", "https://user:pass@release-assets.githubusercontent.com/github-production-release-asset/a"):
            with self.subTest(target=target),self.assertRaises(ValueError):u.TrustedRedirect().redirect_request(req,None,302,"Found",{},target)
        with self.assertRaises(ValueError):u.TrustedRedirect().redirect_request(u.urllib.request.Request(u.LATEST_URL),None,302,"Found",{},cdn)
    def test_token_never_sent_to_browser_download_url(self):
        with self.assertRaises(ValueError):u.download(u.ORIGIN+"v0.2.2/pulse-0.2.2.zip",Path("unused"),100,token="test-secret")
    def test_window_daytime_does_not_catch_up(self):
        clock=dt.datetime(2026,10,17,12,tzinfo=dt.timezone.utc)
        nextday=u.next_window(clock,3)
        self.assertEqual(nextday.astimezone(u.TZ).strftime("%Y-%m-%d %H:%M"),"2026-10-18 03:00")
    def test_night_and_postpone(self):
        clock=dt.datetime(2026,10,17,1,15,tzinfo=dt.timezone.utc)
        self.assertEqual(u.next_window(clock,3),clock)
        result=u.next_window(clock,3,int((clock+dt.timedelta(hours=24)).timestamp()))
        self.assertGreaterEqual(result,clock+dt.timedelta(hours=24))
    def test_dst_spring_and_fall(self):
        for date in (dt.datetime(2026,3,28,12,tzinfo=dt.timezone.utc),dt.datetime(2026,10,24,12,tzinfo=dt.timezone.utc)):
            result=u.next_window(date,3).astimezone(u.TZ)
            self.assertEqual(result.hour,3)
            self.assertEqual((result.date()-date.date()).days,1)
    def test_manifest_expiry_platform_and_requirements(self):
        release={"app":"pulse-dietikon","schema":1,"channel":"stable","version":"0.2.1","min_from":"0.2.0","min_updater":1,"db_schema":1,"php_min":"8.3.0","platform":"linuxmint22-amd64","published_at":"2026-10-17T00:00:00Z","expires_at":"2027-10-17T00:00:00Z","size":100,"sha256":"a"*64,"url":u.ORIGIN+"v0.2.1/pulse-0.2.1.zip","notes":{"en":"x","de":"x","fr":"x"}}
        clock=dt.datetime(2026,10,17,12,tzinfo=dt.timezone.utc)
        u.validate_manifest(release,clock=clock)
        for key,val in (("expires_at","2020-01-01T00:00:00Z"),("platform","other"),("size",True),("app","other"),("notes",{})):
            with self.subTest(key=key),self.assertRaises(ValueError):u.validate_manifest({**release,key:val},clock=clock)

if __name__=="__main__":
    if os.geteuid()!=0:raise SystemExit("Use root inside a disposable Linux test environment; tests only use /tmp paths.")
    unittest.main()
