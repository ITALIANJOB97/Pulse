#!/usr/bin/env python3
"""Publish a tested, signed PULSE release from a clean local main checkout.
Credentials stay in Git Credential Manager/gh; the signing key stays local.
Usage: python bin/release-github.py --php /path/to/php --gh /path/to/gh
"""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys

ROOT=Path(__file__).resolve().parent.parent
REPO='ITALIANJOB97/Pulse'
GIT=['git','-c','safe.directory='+ROOT.as_posix(),'-C',str(ROOT)]
ASSETS=('stable.json','stable.json.sig','blog-stable.json','blog-stable.json.sig')


def run(args, *, env=None, capture=False):
    result=subprocess.run(list(map(str,args)),cwd=ROOT,env=env,capture_output=capture,text=True)
    if result.returncode:
        # Never print credential-command output or a credential-bearing environment.
        raise RuntimeError('Command failed: '+Path(str(args[0])).name)
    return result.stdout.strip() if capture else None


def credentials():
    env={**os.environ,'GIT_TERMINAL_PROMPT':'0','GCM_INTERACTIVE':'never','GH_PROMPT_DISABLED':'1'}
    if env.get('GH_TOKEN') or env.get('GITHUB_TOKEN'):
        return env
    result=subprocess.run(['git','credential','fill'],input='protocol=https\nhost=github.com\n\n',capture_output=True,text=True,env=env)
    if result.returncode==0:
        values=dict(line.split('=',1) for line in result.stdout.splitlines() if '=' in line)
        if values.get('password'):env['GH_TOKEN']=values['password']
    return env # gh may also have its own OS-stored login.


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php',default=shutil.which('php'))
    parser.add_argument('--gh',default=shutil.which('gh'))
    args=parser.parse_args()
    if not args.php or not args.gh:raise RuntimeError('Provide --php and --gh executable paths.')
    if run(GIT+['status','--porcelain','--untracked-files=all'],capture=True):raise RuntimeError('Commit reviewed source changes before publishing; checkout must be clean.')
    if run(GIT+['branch','--show-current'],capture=True)!='main':raise RuntimeError('Publish only from the reviewed main branch.')
    remote=run(GIT+['remote','get-url','origin'],capture=True)
    if remote not in ('https://github.com/'+REPO+'.git','git@github.com:'+REPO+'.git'):raise RuntimeError('Unexpected origin repository.')
    version=(ROOT/'VERSION').read_text(encoding='utf-8').strip()
    if not re.fullmatch(r'\d+\.\d+\.\d+',version):raise RuntimeError('Invalid VERSION.')
    env=credentials();gh=[args.gh]
    repo=json.loads(run(gh+['api','repos/'+REPO],env=env,capture=True))
    if repo.get('full_name')!=REPO or repo.get('private') is not False:raise RuntimeError('Repository must be public before publishing this channel.')
    if not repo.get('permissions',{}).get('push'):raise RuntimeError('A development credential with Contents: Read and write is required.')
    # Refuse non-fast-forward remote history. Resolve it explicitly, never force push.
    run(GIT+['fetch','origin'],env=env)
    found=subprocess.run(GIT+['rev-parse','--verify','refs/remotes/origin/main'],capture_output=True,text=True)
    if found.returncode==0:
        check=subprocess.run(GIT+['merge-base','--is-ancestor','origin/main','HEAD'],capture_output=True)
        if check.returncode:raise RuntimeError('Remote main has changes to integrate before publishing.')
    tag='v'+version
    existing=subprocess.run(gh+['release','view',tag,'--repo',REPO,'--json','isDraft'],capture_output=True,text=True,env=env)
    if existing.returncode==0:raise RuntimeError('Release '+tag+' already exists. Do not overwrite it; review any draft or increment VERSION.')
    # Application tests are isolated in system temporary folders.
    for test in ('core.php','features-020.php','podium-decimals.php','seasonal-motto.php','blog-relay.php','signatures.php'):
        run([args.php,'-d','extension=sodium',ROOT/'tests'/test])
    run([sys.executable,ROOT/'bin/build-linux-mint-zip.py'])
    directory=ROOT/'dist/GitHub'/tag
    if not all((directory/name).is_file() for name in (*ASSETS,'pulse-'+version+'.zip')):
        run([args.php,'-d','extension=sodium',ROOT/'bin/publish-release.php','publish-blog'])
    # Reuse prepared manifests after a clean build only when the signed ZIP is unchanged.
    archive=ROOT/f'dist/PULSE-Dietikon-Linux-Mint-{version}.zip'
    digest=hashlib.sha256(archive.read_bytes()).hexdigest()
    if hashlib.sha256((directory/('pulse-'+version+'.zip')).read_bytes()).hexdigest()!=digest:
        raise RuntimeError('Prepared release differs from the source build. Increment VERSION.')
    for name in ('stable.json','blog-stable.json'):
        m=json.loads((directory/name).read_text(encoding='utf-8'))
        if m['version']!=version or m['sha256']!=digest or m['size']!=archive.stat().st_size:raise RuntimeError('Release manifest mismatch.')
        run([args.php,'-d','extension=sodium',ROOT/'deploy/verify-release.php',directory/name,directory/(name+'.sig'),ROOT/'deploy/release-public.key'])
    commit=run(GIT+['rev-parse','HEAD'],capture=True)
    run(GIT+['push','origin','HEAD:main'],env=env)
    files=[directory/('pulse-'+version+'.zip'),*[directory/name for name in ASSETS]]
    run(gh+['release','create',tag,*files,'--repo',REPO,'--target',commit,'--draft','--title','PULSE '+version,'--notes-file',directory/'release-notes.md'],env=env)
    draft=json.loads(run(gh+['release','view',tag,'--repo',REPO,'--json','isDraft,assets'],env=env,capture=True))
    if draft.get('isDraft') is not True or {a['name'] for a in draft['assets']}!={p.name for p in files}:raise RuntimeError('Incomplete release left as draft; it will not be installed.')
    # Verify server-side upload sizes before exposing the release to Mint.
    sizes={a['name']:a['size'] for a in draft['assets']}
    if any(sizes[p.name]!=p.stat().st_size for p in files):raise RuntimeError('Upload size mismatch; release left as draft.')
    run(gh+['release','edit',tag,'--repo',REPO,'--draft=false','--latest'],env=env)
    release=json.loads(run(gh+['release','view',tag,'--repo',REPO,'--json','isDraft,isPrerelease,url'],env=env,capture=True))
    if release['isDraft'] or release['isPrerelease']:raise RuntimeError('Release was not published as stable.')
    print('Published: '+release['url'])


if __name__=='__main__':
    try:main()
    except (RuntimeError,OSError,ValueError,KeyError) as error:
        print('PULSE release: '+str(error),file=sys.stderr);sys.exit(1)
