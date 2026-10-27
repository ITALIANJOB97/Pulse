#!/usr/bin/env python3
"""Build the separate recovery kit; never replace a published application ZIP."""
from pathlib import Path
import hashlib
import zipfile
ROOT=Path(__file__).resolve().parent.parent
NAME='PULSE-Backup-Recupero-0.2.4-R2'
PACKAGE=ROOT/'dist/PULSE-Dietikon-Linux-Mint-0.2.4.zip'
EXPECTED='e0bc9c2b95f5b19dd2b131d5447c81c355533821251d5d2302ec724810e1c8af'

def main():
    package=PACKAGE.read_bytes()
    if hashlib.sha256(package).hexdigest()!=EXPECTED:
        raise ValueError('Original published 0.2.4 package is required.')
    files={
        'pulse-0.2.4.zip':package,
        'ripara-e-aggiorna.py':(ROOT/'deploy/repair-mint-024.py').read_bytes(),
        'recovery_backup.py':(ROOT/'deploy/recovery_backup.py').read_bytes(),
        'support-mint.py':(ROOT/'deploy/support-mint.py').read_bytes(),
        'backup-e-aggiorna.sh':(ROOT/'deploy/repair-mint-024.sh').read_bytes(),
        'LEGGIMI.txt':(ROOT/'docs/RECUPERO_MINT_R2.txt').read_bytes(),
    }
    output=ROOT/'dist'/(NAME+'.zip')
    if output.exists():
        with zipfile.ZipFile(output) as archive:
            if set(archive.namelist())!={NAME+'/'+name for name in files} or any(archive.read(NAME+'/'+name)!=content for name,content in files.items()):
                raise ValueError('Existing recovery ZIP differs. Use a new revision name.')
    else:
        with zipfile.ZipFile(output,'x',compression=zipfile.ZIP_DEFLATED,compresslevel=9) as archive:
            for name,content in files.items():
                archive.writestr(NAME+'/'+name,content)
    with zipfile.ZipFile(output) as archive:
        if archive.testzip() is not None: raise ValueError('ZIP integrity check failed.')
    checksum=hashlib.sha256(output.read_bytes()).hexdigest()
    output.with_suffix('.zip.sha256').write_text(checksum+'  '+output.name+'\n',encoding='ascii')
    print(str(output)); print('SHA256 '+checksum)

if __name__=='__main__': main()
