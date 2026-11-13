#!/usr/bin/env python3
"""Verify the actual release contains unmodified binary countdown assets."""
import hashlib
import json
import sys
import zipfile
from pathlib import Path

root = Path(__file__).resolve().parent.parent
provenance = json.loads((root / 'docs/COUNTDOWN_ASSETS.json').read_text(encoding='utf-8'))['images']
with zipfile.ZipFile(sys.argv[1]) as archive:
    prefix = archive.namelist()[0].split('/')[0] + '/'
    manifest = dict(line.split('  ', 1)[::-1] for line in archive.read(prefix + 'CONTENTS.sha256').decode().splitlines())
    expected = {'public/assets/countdowns/' + row['asset'] for row in provenance}
    actual = {name[len(prefix):] for name in archive.namelist() if name.startswith(prefix + 'public/assets/countdowns/')}
    assert actual == expected and len(actual) == 29, 'Missing or unexpected artwork'
    for row in provenance:
        name = 'public/assets/countdowns/' + row['asset']
        data = archive.read(prefix + name)
        assert data == (root / name).read_bytes(), 'Binary image modified while packaging'
        assert hashlib.sha256(data).hexdigest() == row['sha256'] == manifest[name], 'Artwork hash mismatch'
    assert all(not name.startswith(prefix + 'storage/') or name == prefix + 'storage/.gitkeep' for name in archive.namelist()), 'Runtime data included'
print('29 binary images, source provenance and package checksums verified; no runtime data.')
