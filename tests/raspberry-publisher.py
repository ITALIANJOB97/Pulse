"""Sign both platform manifests with an ephemeral key; never publish or use real credentials."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import zipfile

parser=argparse.ArgumentParser()
parser.add_argument("--php",required=True)
args=parser.parse_args()
source=Path(__file__).resolve().parent.parent
with tempfile.TemporaryDirectory(prefix="pulse-publisher-test-") as temporary:
    root=Path(temporary)
    for folder in ("bin","deploy","docs","public/guide","dist"):(root/folder).mkdir(parents=True,exist_ok=True)
    files=["bin/publish-release.php","deploy/verify-release.php","deploy/update-source.json","deploy/release-notes.json","deploy/release-changes.json","deploy/blog-sync.php","deploy/blog-relay-config.example.php","deploy/blog-relay-cron.php","docs/RELAY_BLOG.txt"]
    for name in files:shutil.copyfile(source/name,root/name)
    (root/"VERSION").write_text("0.4.0\n")
    (root/"public/guide/index.html").write_text("<h1>Test guide</h1>")
    for name in ("PULSE-Dietikon-Linux-Mint-0.4.0.zip","PULSE-Raspberry-Pi-0.4.0.zip"):
        with zipfile.ZipFile(root/"dist"/name,"w") as z:z.writestr("fixture/VERSION","0.4.0\n"+name)
    env={**os.environ,"PULSE_SIGNING_KEY":str(root/"private-test/signing.key")}
    def php(*arguments):
        result=subprocess.run([args.php,*map(str,arguments)],env=env,capture_output=True,text=True)
        if result.returncode:raise AssertionError(result.stderr)
    php(root/"bin/publish-release.php","init-key")
    php(root/"bin/publish-release.php","publish")
    directory=root/"dist/GitHub/v0.4.0"
    for name,platform,asset in (("stable.json","linuxmint22-amd64","pulse-0.4.0.zip"),("stable-raspberry.json","raspberrypi-trixie-arm64","pulse-raspberry-0.4.0.zip")):
        manifest=json.loads((directory/name).read_text(encoding="utf-8"))
        archive=directory/asset
        assert manifest["platform"]==platform and manifest["version"]=="0.4.0"
        assert manifest["url"].endswith("/v0.4.0/"+asset)
        assert manifest["size"]==archive.stat().st_size and manifest["sha256"]==hashlib.sha256(archive.read_bytes()).hexdigest()
        if "raspberry" in name:assert manifest["php_min"]=="8.4.0" and manifest["min_updater"]==5
        php(root/"deploy/verify-release.php",directory/name,directory/(name+".sig"),root/"deploy/release-public.key")
    assert not any("signing.key" in str(p) for p in (root/"dist").rglob("*"))
    print("Publisher passed: separate Mint/ARM64 assets, URLs, checksums and valid Ed25519 signatures; ephemeral test key only.")
