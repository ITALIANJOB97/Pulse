#!/usr/bin/env python3
"""Exercise release acceptance and rejection using current/previous updater code.
Usage: python tests/package-compatibility.py PACKAGE.zip [--updater SOURCE.py ...]
"""
import argparse
import hashlib
from pathlib import Path
import stat
import sys
import tempfile
import zipfile

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "bin"))
from package_compatibility import verify_package

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("archive", type=Path)
parser.add_argument("--updater", type=Path, action="append", default=[])
args = parser.parse_args()
updaters = args.updater or [Path(__file__).resolve().parents[1] / "deploy/updater.py"]
for updater in updaters:
    version = verify_package(args.archive, updater)
    print(f"Accepted {version}: {updater.name}", flush=True)

with tempfile.TemporaryDirectory(prefix="pulse-package-regression-") as directory:
    scratch = Path(directory).resolve()
    assert scratch.parent == Path(tempfile.gettempdir()).resolve()
    assert scratch.name.startswith("pulse-package-regression-")
    with zipfile.ZipFile(args.archive) as original:
        entries = {item.filename: (item, original.read(item)) for item in original.infolist()}
    prefix = next(iter(entries)).split("/", 1)[0] + "/"
    for scenario in ("unsupported-root", "checksum"):
        target = scratch / (scenario + ".zip")
        with zipfile.ZipFile(target, "w") as output:
            for name, (item, data) in entries.items():
                if scenario == "unsupported-root" and name == prefix + "CONTENTS.sha256":
                    data += (hashlib.sha256(b"#!/bin/sh\n").hexdigest() + "  aggiorna-ora.sh\n").encode()
                if scenario == "checksum" and name == prefix + "VERSION":
                    data = b"99.0.0\n"
                output.writestr(item, data)
            if scenario == "unsupported-root":
                item = zipfile.ZipInfo(prefix + "aggiorna-ora.sh")
                item.create_system = 3
                item.external_attr = (stat.S_IFREG | 0o755) << 16
                output.writestr(item, b"#!/bin/sh\n")
        for updater in updaters:
            try:
                verify_package(target, updater)
            except ValueError as error:
                expected = "Unexpected source path" if scenario == "unsupported-root" else "Package checksum mismatch"
                assert expected in str(error), str(error)
                print(f"Rejected {scenario}: {updater.name}", flush=True)
            else:
                raise AssertionError(f"Unsafe {scenario} accepted by {updater.name}")
