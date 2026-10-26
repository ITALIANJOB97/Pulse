#!/usr/bin/env python3
"""Protocol, HTTPS transport and existing transaction integration tests. Linux only."""
import datetime as dt
import importlib.util
import io
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch, Mock
import urllib.error
import urllib.request

ROOT=Path(__file__).resolve().parent.parent
spec=importlib.util.spec_from_file_location("existing_tests",ROOT/"tests/updater.py")
existing=importlib.util.module_from_spec(spec);spec.loader.exec_module(existing)
u=existing.u;p=u.protocol

def manifest(**changes):
    return {"schemaVersion":1,"program":"Pulse","version":"0.3.0","downloadUrl":p.BASE_URL+"pulse-0.3.0.zip","sha256":"a"*64,**changes}

class Protocol(unittest.TestCase):
    def test_numeric_version_comparison(self):
        self.assertGreater(p.version('1.10.0'),p.version('1.9.0'))
        self.assertGreater(p.version('2.0.0'),p.version('1.99.0'))
        self.assertEqual(p.version('0.2.4'),p.version('0.2.4'))
        self.assertLess(p.version('0.2.3'),p.version('0.2.4'))
    def test_invalid_versions(self):
        for value in [None,True,1,'1.2','1.2.3.4','01.2.3','-1.2.3','1.0.0-rc1','1.0.0+build','1.2.3\n','9999999999.1.2']:
            with self.subTest(value=value),self.assertRaises(ValueError):p.version(value)
    def test_required_fields_and_future_fields(self):
        self.assertEqual(p.validate_manifest(manifest(future={"x":True}))["version"],'0.3.0')
        for key in ['schemaVersion','program','version','downloadUrl','sha256']:
            data=manifest();del data[key]
            with self.subTest(key=key),self.assertRaises(ValueError):p.validate_manifest(data)
    def test_wrong_program_and_schema(self):
        for changes in [{'program':'pulse'},{'program':'Other'},{'schemaVersion':True},{'schemaVersion':2}]:
            with self.subTest(changes=changes),self.assertRaises(ValueError):p.validate_manifest(manifest(**changes))
    def test_url_restrictions(self):
        for url in ['http://www.luigipranzo.com/myfile/Pulse/a.zip', 'https://evil.test/a.zip',p.BASE_URL+'../a.zip',p.BASE_URL+'%2e%2e/a.zip',p.BASE_URL+'a.zip?x=1',p.BASE_URL+'a.zip#x',p.BASE_URL+'a\\b.zip',p.BASE_URL+'a.php','https://www.luigipranzo.com.evil.test/myfile/Pulse/a.zip','https://user@www.luigipranzo.com/myfile/Pulse/a.zip','https://www.luigipranzo.com:443/myfile/Pulse/a.zip','https://www.luigipranzo.com/myfile/Pulse2/a.zip']:
            with self.subTest(url=url):self.assertFalse(p.package_url(url))
    def test_optional_fields(self):
        release=p.validate_manifest(manifest(mandatory=True,size=500,publishedAt='2026-01-01T00:00:00Z',releaseNotes={'fixes':['<script>untrusted</script>']}))
        self.assertTrue(release['mandatory']);self.assertEqual(release['size'],500)
        self.assertEqual(release['release_notes']['fixes'],['<script>untrusted</script>'])
        self.assertIsNone(p.validate_manifest(manifest())['size'])
    def test_invalid_optional_fields(self):
        for changes in [{'mandatory':1},{'size':True},{'size':0},{'size':p.MAX_PACKAGE+1},{'sha256':'x'*64},{'minimumSupportedVersion':'1.0.0'},{'releaseNotes':[]},{'releaseNotes':{'features':'text'}},{'releaseNotes':{'fixes':['x'*2001]}},{'publishedAt':'invalid'},{'publishedAt':'2026-01-01T00:00:00'},{'pulse':{'dbSchema':True}}]:
            with self.subTest(changes=changes),self.assertRaises(ValueError):p.validate_manifest(manifest(**changes))
    def test_bad_json_and_one_mb_limit(self):
        for body in [b'{',b'[]',b'{"program":1,"program":2}',b' '*1048577]:
            with self.subTest(body=body[:30]),self.assertRaises(ValueError):p.parse_json(body)
    def test_no_redirect_even_same_domain(self):
        for url in [p.BASE_URL+'a.zip','http://www.luigipranzo.com/a','https://evil.test/a']:
            with self.subTest(url=url),self.assertRaises(ValueError):p.NoRedirect().redirect_request(urllib.request.Request(p.MANIFEST_URL),None,302,'Found',{},url)

class Response(io.BytesIO):
    status=200
    def __init__(self,body,headers=None):super().__init__(body);self.headers=headers or {}

class Transport(unittest.TestCase):
    def setUp(self):self.temp=tempfile.TemporaryDirectory();self.target=Path(self.temp.name)/'file.partial'
    def tearDown(self):self.temp.cleanup()
    def opener(self,body,headers=None):
        instance=Mock();instance.open.return_value=Response(body,headers);return instance
    def test_download_progress_and_etag(self):
        progress=[];opener=self.opener(b'a'*100000,{'Content-Length':'100000','ETag':'"v1"'})
        result=p.fetch(p.BASE_URL+'a.zip',self.target,200000,progress=lambda *x:progress.append(x),opener=opener)
        self.assertEqual(self.target.stat().st_size,100000);self.assertEqual(result['etag'],'"v1"');self.assertEqual(progress[-1][0],100000)
        self.assertGreater(progress[-1][2],0);self.assertEqual(opener.open.call_args.kwargs['timeout'],20)
    def test_304_preserves_cached_body(self):
        self.target.write_bytes(b'existing');opener=Mock();opener.open.side_effect=urllib.error.HTTPError(p.MANIFEST_URL,304,'Not modified',{},None)
        result=p.fetch(p.MANIFEST_URL,self.target,100,etag='"cached"',opener=opener)
        self.assertTrue(result['not_modified']);self.assertEqual(self.target.read_bytes(),b'existing')
        self.assertEqual(opener.open.call_args.args[0].get_header('If-none-match'),'"cached"')
    def test_http_error_codes(self):
        for status in [403,404,500]:
            opener=Mock();opener.open.side_effect=urllib.error.HTTPError(p.MANIFEST_URL,status,'Error',{},None)
            with self.subTest(status=status),self.assertRaises(urllib.error.HTTPError) as caught:p.fetch(p.MANIFEST_URL,self.target,100,opener=opener)
            self.assertEqual(p.error_code(caught.exception),'http_'+str(status));self.assertFalse(self.target.exists())
    def test_offline_and_timeout(self):
        for error,code in [(urllib.error.URLError('unreachable'),'offline'),(TimeoutError(),'timeout')]:
            opener=Mock();opener.open.side_effect=error
            with self.assertRaises(type(error)):p.fetch(p.MANIFEST_URL,self.target,100,opener=opener)
            self.assertEqual(p.error_code(error),code)
    def test_limit_and_truncation_remove_temp(self):
        for body,headers,limit in [(b'a'*101,{},100),(b'a',{'Content-Length':'2'},100),(b'a',{'Content-Length':'200'},100)]:
            with self.subTest(headers=headers),self.assertRaises(ValueError):p.fetch(p.MANIFEST_URL,self.target,limit,opener=self.opener(body,headers))
            self.assertFalse(self.target.exists())
    def test_cancel_mid_download_removes_temp(self):
        cancel=[False]
        def progress(*args):cancel[0]=True
        with self.assertRaises(p.DownloadCancelled):p.fetch(p.BASE_URL+'a.zip',self.target,200000,opener=self.opener(b'a'*150000),cancelled=lambda:cancel[0],progress=progress)
        self.assertFalse(self.target.exists())
    def test_cancel_before_network(self):
        opener=Mock()
        with self.assertRaises(p.DownloadCancelled):p.fetch(p.MANIFEST_URL,self.target,100,opener=opener,cancelled=lambda:True)
        opener.open.assert_not_called()
    def test_untrusted_source_never_connects(self):
        opener=Mock()
        with self.assertRaises(ValueError):p.fetch('https://evil.test/a.zip',self.target,100,opener=opener)
        opener.open.assert_not_called()

class Integration(unittest.TestCase):
    setUp=existing.Deployment.setUp
    tearDown=existing.Deployment.tearDown
    channel=existing.Deployment.channel
    preference=existing.Deployment.preference
    assert_existing=existing.Deployment.assert_existing
    dbvalue=existing.Deployment.dbvalue
    def test_checks_at_most_every_six_hours(self):
        release=self.channel();self.agent.save_status(checked_epoch=1000)
        with patch.object(u.time,'time',return_value=1001),patch.object(self.agent,'signed_manifest',return_value=release) as fetch:
            self.agent.tick();self.assertFalse(fetch.call_args.kwargs['fresh'])
        self.preference('update_requested','manual-check')
        with patch.object(u.time,'time',return_value=1002),patch.object(self.agent,'signed_manifest',return_value=release) as fetch:
            self.agent.tick();self.assertTrue(fetch.call_args.kwargs['fresh'])
        with patch.object(u.time,'time',return_value=1002+21600),patch.object(self.agent,'signed_manifest',return_value=release) as fetch:
            self.agent.tick();self.assertTrue(fetch.call_args.kwargs['fresh'])
    def test_offline_does_not_retry_network_every_tick(self):
        with patch.object(self.agent,'signed_manifest',side_effect=urllib.error.URLError('unreachable')) as fetch:
            self.agent.tick();self.assertTrue(fetch.call_args.kwargs['fresh'])
            self.agent.tick();self.assertFalse(fetch.call_args.kwargs['fresh'])
        self.assert_existing()
    def test_equal_and_older_versions_do_not_install(self):
        for v in ['0.1.1','0.1.0']:
            release=self.channel();release['version']=v
            with patch.object(self.agent,'signed_manifest',return_value=release):self.agent.tick()
            self.assertEqual(u.read_json(self.agent.status_file)['phase'],'up_to_date');self.assert_existing()
    def test_skip_optional_but_not_mandatory(self):
        release=self.channel();self.preference('update_skip',release['version'])
        with patch.object(self.agent,'signed_manifest',return_value=release):self.agent.tick()
        self.assertEqual(u.read_json(self.agent.status_file)['phase'],'skipped')
        release['mandatory']=True;self.preference('update_auto',False)
        with patch.object(self.agent,'signed_manifest',return_value=release):self.agent.tick()
        self.assertEqual(u.read_json(self.agent.status_file)['phase'],'paused');self.assert_existing()
    def test_manual_consent_installs_outside_night_with_auto_disabled(self):
        release=self.channel();self.preference('update_auto',False)
        self.preference('update_install',{'version':release['version'],'nonce':'a'*24})
        with patch.object(self.agent,'signed_manifest',return_value=release),patch.object(u,'now',return_value=dt.datetime(2026,10,26,12,tzinfo=dt.timezone.utc)):
            self.agent.tick()
        self.assertEqual(self.agent.current().name,release['version']);self.assert_existing()
    def test_cancellation_is_latched_until_new_consent(self):
        release=self.channel();(self.agent.state/'downloads/0.2.0.zip').unlink()
        def cancel(release,target):target.write_bytes(b'partial');raise p.DownloadCancelled()
        with patch.object(self.agent,'signed_manifest',return_value=release),patch.object(self.agent,'download_package',side_effect=cancel) as download:
            self.agent.tick();self.agent.tick();self.assertEqual(download.call_count,1)
        self.assertFalse(list((self.agent.state/'downloads').glob('*.partial')))
        self.assertEqual(u.read_json(self.agent.status_file)['phase'],'cancelled');self.assert_existing()
    def test_wrong_sha_blocks_install_and_cleans_partial(self):
        release=self.channel();(self.agent.state/'downloads/0.2.0.zip').unlink()
        with patch.object(self.agent,'signed_manifest',return_value=release),patch.object(self.agent,'download_package',side_effect=lambda release,target:target.write_bytes(b'corrupt')):
            self.agent.tick()
        self.assertEqual(u.read_json(self.agent.status_file)['error'],'package_download_or_integrity')
        self.assertFalse(list((self.agent.state/'downloads').glob('*.partial')));self.assert_existing();self.assertEqual(self.agent.calls,[])
    def test_valid_sha_and_optional_size(self):
        release=self.channel();package=self.agent.state/'downloads/0.2.0.zip'
        self.agent.verify_package(release,package);release['size']=None;self.agent.verify_package(release,package)
        release['sha256']='0'*64
        with self.assertRaises(ValueError):self.agent.verify_package(release,package)
    def test_abandoned_partial_is_removed(self):
        target=self.agent.state/'downloads/abandoned.zip.partial';target.write_bytes(b'interrupted')
        with patch.object(self.agent,'signed_manifest',side_effect=ValueError('no manifest')):self.agent.tick()
        self.assertFalse(target.exists());self.assert_existing()
    def test_protocol_etag_cache_requires_signature(self):
        calls=[]
        body=json.dumps(manifest()).encode()
        def fetch(url,target,limit,**kwargs):
            calls.append(url);target.write_bytes(b'signature' if url.endswith('.sig') else body)
            return {'not_modified':False,'etag':'"first"'}
        with patch.object(p,'fetch',side_effect=fetch),patch.object(self.agent,'run'):
            self.assertEqual(self.agent.protocol_manifest(True)['version'],'0.3.0')
        with patch.object(p,'fetch',return_value={'not_modified':True}),patch.object(self.agent,'run',side_effect=ValueError('bad signature')):
            with self.assertRaises(ValueError):self.agent.protocol_manifest(True)
        self.assertFalse((self.agent.state/'downloads/update-cache.json').exists())
        self.assertEqual(calls,[p.MANIFEST_URL,p.MANIFEST_URL+'.sig'])

if __name__=='__main__':
    if os.geteuid()!=0:raise SystemExit('Run as root in the isolated Linux test environment.')
    unittest.main()
