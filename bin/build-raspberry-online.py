#!/usr/bin/env python3
"""Prepare immutable GitHub bootstrap assets from an already verified Raspberry ZIP."""
import argparse
import hashlib
from pathlib import Path
import re
import zipfile
from package_compatibility import verify_package

ROOT = Path(__file__).resolve().parent.parent


def build(archive, tag, output):
    if not re.fullmatch(r"v\d+\.\d+\.\d+(?:-[a-z0-9.-]+)?", tag):
        raise ValueError("Invalid release tag")
    version = verify_package(archive, ROOT / "deploy/updater.py")
    if not (tag == "v" + version or tag.startswith("v" + version + "-")):
        raise ValueError("Tag does not match package version")
    with zipfile.ZipFile(archive) as package:
        names = package.namelist()
        prefix = names[0].split("/", 1)[0]
        template = package.read(prefix + "/deploy/install-raspberry-online.py").decode("utf-8")
    data = archive.read_bytes()
    digest = hashlib.sha256(data).hexdigest()
    script = (template.replace("@RELEASE_TAG@", tag).replace("@VERSION@", version)
              .replace("@PACKAGE_SHA256@", digest)
              .replace("PACKAGE_SIZE = 0  # @PACKAGE_SIZE@", "PACKAGE_SIZE = " + str(len(data))))
    compile(script, "install-pulse-raspberry.py", "exec")
    output.mkdir(parents=True, exist_ok=True)
    files = {f"pulse-raspberry-{version}.zip": data, "install-pulse-raspberry.py": script.encode("utf-8")}
    for name, content in files.items():
        target = output / name
        if target.exists() and target.read_bytes() != content:
            raise ValueError("Existing release asset differs; choose a new preview tag")
    for name, content in files.items():
        (output / name).write_bytes(content)
    (output / "SHA256SUMS").write_text("".join(
        f"{hashlib.sha256(content).hexdigest()}  {name}\n" for name, content in files.items()), encoding="utf-8")
    print("Prepared locally (not published): " + str(output))
    print("Package SHA256: " + digest)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("archive", type=Path)
    parser.add_argument("--tag", required=True)
    args = parser.parse_args()
    build(args.archive, args.tag, ROOT / "dist/Raspberry-Online" / args.tag)
