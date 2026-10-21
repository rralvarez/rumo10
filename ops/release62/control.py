"""Selective release controller. verify-only has no remote mutating operations."""
import argparse
import datetime
import ftplib
import hashlib
import io
import ipaddress
import json
import os
from pathlib import Path
import re
import socket
import ssl
import subprocess
import sys
import tarfile
import time
import urllib.error
import urllib.request

ROOT=Path(__file__).resolve().parent
PACKAGE_SHA='caec057a8acc65ce58df9f7f4243082fdd8fe069e445c57f937b68609d633378'
PUBLISHER_SHA='ecd2f7b0864352b53bc7fc6cbb6722c62b78ada0e604ec3ebf76c409fea3e23c'
OLD_MANIFEST_SHA='0587dd3b5558be15671efdf526ccfb34a2ff6068d2810c92c9064245cb52384f'
OLD_JOURNAL_SHA='86c9b7c971076f096fa883712c08031d58620fdd0ec19e5147e6e998c4e57f49'
HOST='a16-asgard21.hospedagemuolhost.com.br'
WEB='https://www.rumoacamisa10.com.br'
BASE='apps/rumo10/current/'
sha=lambda b:hashlib.sha256(b).hexdigest()

def require(value,message):
    if not value: raise RuntimeError(message)

def offline():
    require(sha((ROOT/'package.tar.gz').read_bytes())==PACKAGE_SHA,'Approved TAR hash mismatch')
    require(sha((ROOT/'bin/deploy-ftps.py').read_bytes())==PUBLISHER_SHA,'Publisher hash mismatch')
    with tarfile.open(ROOT/'package.tar.gz','r:gz') as t:
        names=[x.name for x in t.getmembers()]
        require(len(names)==len(set(names)),'Duplicate TAR paths')
        m=json.load(t.extractfile('release.json'))
        require(m['project']=='rumo10' and len(m['files'])==198,'Unexpected package contract')
        require(set(names)==set(m['files'])|{'release.json'},'Package inventory mismatch')
        for p,d in m['files'].items():
            require(t.getmember(p).isfile() and not p.startswith('/') and not any(x in ('','.','..') for x in p.split('/')),'Unsafe TAR member')
            require(not any(x.lower().startswith('.env') or x.lower()=='clients.php' for x in p.split('/')),'Private configuration in package')
            require(sha(t.extractfile(p).read())==d,'Package member hash mismatch')
    return m

def api(path):
    headers={'User-Agent':'Rumo10-selective62-control','Accept':'application/vnd.github+json'}
    if os.environ.get('GH_TOKEN'):headers['Authorization']='Bearer '+os.environ['GH_TOKEN']
    with urllib.request.urlopen(urllib.request.Request('https://api.github.com/repos/rralvarez/rumo10/'+path,headers=headers),timeout=25) as response:return json.load(response)

def automation():
    workflows=api('actions/workflows')['workflows']
    expected={'.github/workflows/production.yml','.github/workflows/production-ftps.yml'}
    states={x['path']:x['state'] for x in workflows if x['path'] in expected}
    require(set(states)==expected and set(states.values())=={'disabled_manually'},'Old workflows must stay disabled')
    own=os.environ.get('GITHUB_RUN_ID','')
    for status in ('in_progress','queued','waiting','pending','requested'):
        for page in range(1,11):
            runs=api(f'actions/runs?status={status}&per_page=100&page={page}')['workflow_runs']
            require(not any(str(x['id'])!=own for x in runs),'Another Actions run is active or pending')
            if len(runs)<100:break
        else:raise RuntimeError('Unable to establish bounded run inventory')
    return states

class ReadOnlyFTPS:
    def __init__(self):self.ftp=None;self.reads=0;self.connections=0;self.tls_versions=set()
    def connect(self):
        if self.ftp:self.ftp.close()
        host=os.environ.get('UOL_FTP_HOST') or HOST
        require(host==HOST,'Unapproved FTPS hostname')
        address=os.environ.get('UOL_FTP_ADDRESS') or host
        if address!=host:ipaddress.ip_address(address)
        self.ftp=ftplib.FTP_TLS(context=ssl.create_default_context(),timeout=30)
        self.ftp.connect(address,int(os.environ.get('UOL_FTP_PORT') or '21'))
        self.ftp.host=host;self.ftp.auth();self.tls_versions.add(self.ftp.sock.version());self.ftp.login(os.environ['UOL_FTP_USERNAME'],os.environ['UOL_FTP_PASSWORD'])
        self.ftp.prot_p();self.ftp.set_pasv(True);self.ftp.cwd('/');self.reads=0;self.connections+=1
    def read(self,path):
        for attempt in range(2):
            try:
                if not self.ftp or self.reads>=20:self.connect()
                self.reads+=1;data=io.BytesIO()
                try:self.ftp.retrbinary('RETR '+path,data.write)
                except ftplib.error_perm as e:
                    if not str(e).startswith('550'):raise
                    parent,name=path.rsplit('/',1)
                    require(name not in {n for n,f in self.ftp.mlsd(parent)},'Remote path unreadable, not absent')
                    return None
                return data.getvalue()
            except (EOFError,TimeoutError,ConnectionError,ssl.SSLEOFError):
                if attempt:raise
                self.connect()
        raise RuntimeError('Unreachable retry state')
    def close(self):
        if self.ftp:self.ftp.close()

def http_status(path):
    try:
        with urllib.request.urlopen(WEB+path,timeout=25) as response:
            require(response.url==WEB+path,'Unexpected public redirect')
            return response.status
    except urllib.error.HTTPError as error:return error.code

def remote(mode,client):
    manifest=client.read(BASE+'release.json')
    require(manifest is not None and sha(manifest)==OLD_MANIFEST_SHA,'Production base is not the reconciled old release')
    old=json.loads(manifest);require(old['project']=='rumo10' and len(old['files'])==197,'Unexpected installed manifest')
    require(client.read('apps/rumo10/deployment.lock') is None,'Deployment lock is active')
    maintenance=client.read('apps/rumo10/maintenance.lock')
    require((maintenance is None)==(mode=='verify-only'),'Unexpected maintenance state')
    require(client.read('apps/rumo10/maintenance-allow.json') is None,'QA allowlist must not bypass backup maintenance')
    journal=client.read('apps/rumo10/deployment-journal.json')
    require(journal is not None and sha(journal)==OLD_JOURNAL_SHA,'Journal differs from preserved reconciled attempt')
    j=json.loads(journal);require(all(x['ownership']=='managed' and not x['pending'] for x in j['records'].values()),'Unresolved journal operation')
    checked=0
    for name,digest in old['files'].items():
        require(re.fullmatch(r'(app|bin|database|public|deploy)/[^\\:]+|storage/media/[^\\:]+',name) and not any(x in ('','.','..') for x in name.split('/')),'Invalid remote manifest path')
        body=client.read(BASE+name);require(body is not None and sha(body)==digest,'Managed file differs: '+name);checked+=1
        if name.startswith('public/assets/') or name in ('public/favicon.svg','public/favicon.ico'):
            public=client.read('public_html/'+name[7:]);require(public==body,'Public copy differs: '+name);checked+=1
    for path,digest in [('public_html/index.php','a2f40d7a71d09833fcfcdd572093643bf3e7aefe0c61e61ab7d692e88e11d3d0'),('public_html/.htaccess','62b2bdcc125ea3e6ec3e069dc636a52a2cbe5196fb9b82e6ee1da239d79e116a')]:
        body=client.read(path);require(body is not None and sha(body)==digest,'Public control differs: '+path);checked+=1
    # Detect drift during the multi-connection read-only scan.
    require(client.read(BASE+'release.json')==manifest,'Manifest changed during scan')
    require(client.read('apps/rumo10/deployment-journal.json')==journal,'Journal changed during scan')
    require(client.read('apps/rumo10/deployment.lock') is None,'Deployment started during scan')
    require(client.read('apps/rumo10/maintenance.lock')==maintenance,'Maintenance changed during scan')
    expected=200 if mode=='verify-only' else 503
    require(http_status('/login')==expected,'Public HTTP disagrees with maintenance')
    return {'managed_and_public_checked':checked,'manifest_sha256':sha(manifest),'journal_sha256':sha(journal),'maintenance_sha256':sha(maintenance) if maintenance else None,'http_login':expected,'connections':client.connections,'tls_versions':sorted(client.tls_versions)}

def backup_gate(receipt,remote_state):
    require(isinstance(receipt,dict) and receipt.get('schema')=='release62-backup-v1','Fresh verified backup receipt required')
    age=time.time()-receipt.get('created_at_unix',0)
    require(0<=age<=1800,'Backup receipt must be from this maintenance window, within 30 minutes')
    require(receipt.get('production_manifest_sha256')==OLD_MANIFEST_SHA,'Backup is for a different base')
    require(receipt.get('maintenance_sha256')==remote_state['maintenance_sha256'],'Maintenance ownership mismatch')
    require(receipt.get('isolated_restore_verified') is True and receipt.get('writes_drained_verified') is True,'Restore/drain verification missing')
    for name in ('database_archive_sha256','files_archive_sha256','recovery_archive_sha256'):
        require(isinstance(receipt.get(name),str) and re.fullmatch('[0-9a-f]{64}',receipt[name]),'Missing verified backup hash')
    require(receipt['recovery_archive_sha256']=='b339ef3d8381508dc391ee15ca9486682c9de13423a8be36f1018c0b2b83665e','Wrong v3-compatible recovery archive')

def main():
    parser=argparse.ArgumentParser();parser.add_argument('mode',choices=['verify-only','deploy']);args=parser.parse_args()
    offline();states=automation();socket.getaddrinfo(HOST,21,type=socket.SOCK_STREAM)
    if args.mode=='deploy':
        require(os.environ.get('RELEASE62_ALLOW_DEPLOY')=='true','Deploy gate is disabled')
        require(os.environ.get('RELEASE62_APPROVAL')=='PUBLISH:'+PACKAGE_SHA,'Explicit package approval missing')
    client=ReadOnlyFTPS()
    try:state=remote(args.mode,client)
    finally:client.close()
    result={'mode':args.mode,'package_sha256':PACKAGE_SHA,'publisher_sha256':PUBLISHER_SHA,'workflows':states,'remote':state,'application_rebuilt':False}
    if args.mode=='deploy':
        backup_gate(json.loads(os.environ.get('RELEASE62_BACKUP_RECEIPT') or '{}'),state)
        automation()  # Last concurrency check before invoking the writer.
        subprocess.run([sys.executable,str(ROOT/'bin/deploy-ftps.py'),str(ROOT/'package.tar.gz'),'rumo10'],check=True)
        require(http_status('/login')==503,'Administrative maintenance was not preserved')
        result['status']='uploaded; maintenance retained; authenticated QA and flag activation still required'
    else:result['status']='read-only verification passed; no remote mutations'
    print(json.dumps(result,sort_keys=True))

if __name__=='__main__':main()
