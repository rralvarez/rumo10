"""No network: independently exercise verify-only and deployment gates."""
import contextlib
import ftplib
import importlib.util
import io
import json
import os
from pathlib import Path
import ssl
import sys
import time
import unittest
from unittest.mock import patch
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('control',ROOT/'control.py');c=importlib.util.module_from_spec(spec);spec.loader.exec_module(c)

class FakeReadOnly:
    def __init__(self,files):
        self.files=files;self.sock=type('Sock',(),{'version':lambda s:'TLSv1.3'})();self.connects=0;self.reads=0;self.fail_once=False
    def connect(self,*args):self.connects+=1
    def close(self):pass
    def auth(self):pass
    def login(self,*args):pass
    def prot_p(self):pass
    def set_pasv(self,value):assert value is True
    def cwd(self,*args):pass
    def retrbinary(self,cmd,cb):
        self.reads+=1
        if self.fail_once:self.fail_once=False;cb(b'partial');raise EOFError('fixture only')
        p=cmd[5:]
        if p not in self.files:raise ftplib.error_perm('550 missing')
        cb(self.files[p])
    def mlsd(self,parent):return [(p[len(parent)+1:],{}) for p in self.files if p.startswith(parent+'/') and '/' not in p[len(parent)+1:]]
    def storbinary(self,*a):raise AssertionError('VERIFY-ONLY MUTATION: STOR')
    def rename(self,*a):raise AssertionError('VERIFY-ONLY MUTATION: RNTO')
    def delete(self,*a):raise AssertionError('VERIFY-ONLY MUTATION: DELE')
    def mkd(self,*a):raise AssertionError('VERIFY-ONLY MUTATION: MKD')

class ControlTests(unittest.TestCase):
    def setUp(self):
        self.files={c.BASE+f'app/File{i}.php':str(i).encode() for i in range(197)}
        manifest={'project':'rumo10','files':{p[len(c.BASE):]:c.sha(v) for p,v in self.files.items()}}
        self.files[c.BASE+'release.json']=json.dumps(manifest).encode()
        self.files['apps/rumo10/deployment-journal.json']=json.dumps({'records':{'known':{'ownership':'managed','pending':None}}}).encode()
        self.files['public_html/index.php']=b'bridge';self.files['public_html/.htaccess']=b'htaccess'
        self.fake=FakeReadOnly(self.files)
        self.patchers=[patch.object(c,'OLD_MANIFEST_SHA',c.sha(self.files[c.BASE+'release.json'])),patch.object(c,'OLD_JOURNAL_SHA',c.sha(self.files['apps/rumo10/deployment-journal.json'])),patch.object(c,'automation',return_value={'old':'disabled_manually'}),patch.object(c.socket,'getaddrinfo',return_value=[]),patch.object(c,'http_status',return_value=200),patch.object(c.ftplib,'FTP_TLS',return_value=self.fake),patch.dict(os.environ,{'UOL_FTP_USERNAME':'synthetic','UOL_FTP_PASSWORD':'synthetic','UOL_FTP_HOST':c.HOST,'RELEASE62_ALLOW_DEPLOY':'','RELEASE62_APPROVAL':'','RELEASE62_BACKUP_RECEIPT':''})]
        for p in self.patchers:p.start()
        # Control-file hashes are intentionally constants; use an isolated function
        # globals copy with fixture hash substitution, never rewrite production code.
        code=(ROOT/'control.py').read_text(encoding='utf-8').replace('a2f40d7a71d09833fcfcdd572093643bf3e7aefe0c61e61ab7d692e88e11d3d0',c.sha(b'bridge')).replace('62b2bdcc125ea3e6ec3e069dc636a52a2cbe5196fb9b82e6ee1da239d79e116a',c.sha(b'htaccess'))
        ns={'__name__':'fixture_remote','__file__':str(ROOT/'control.py')};exec(compile(code,'fixture controller','exec'),ns)
        self.patchers.append(patch.object(c,'remote',side_effect=lambda mode,client:ns['remote'](mode,client)))
        ns['OLD_MANIFEST_SHA']=c.OLD_MANIFEST_SHA;ns['OLD_JOURNAL_SHA']=c.OLD_JOURNAL_SHA;ns['http_status']=lambda p:200 if 'apps/rumo10/maintenance.lock' not in self.files else 503
        self.patchers[-1].start()
    def tearDown(self):
        for p in reversed(self.patchers):p.stop()
    def invoke(self,mode):
        out=io.StringIO()
        with patch.object(sys,'argv',['control.py',mode]),contextlib.redirect_stdout(out):c.main()
        return json.loads(out.getvalue())
    def test_offline_package_is_exact_approved_tar(self):self.assertEqual(len(c.offline()['files']),198)
    def test_verify_only_never_invokes_writer_or_mutates_ftp(self):
        before=dict(self.files)
        with patch.object(c.subprocess,'run',side_effect=AssertionError('writer invoked')):
            result=self.invoke('verify-only')
        self.assertEqual(self.files,before);self.assertEqual(result['status'],'read-only verification passed; no remote mutations');self.assertGreater(self.fake.connects,5)
    def test_retr_partial_buffer_discarded_in_read_only_mode(self):
        self.fake.fail_once=True;self.assertEqual(self.invoke('verify-only')['mode'],'verify-only')
    def test_default_deploy_gate_blocks_before_connection(self):
        with self.assertRaisesRegex(RuntimeError,'Deploy gate'):self.invoke('deploy')
        self.assertEqual(self.fake.connects,0)
    def test_approval_must_match_exact_package(self):
        with patch.dict(os.environ,{'RELEASE62_ALLOW_DEPLOY':'true','RELEASE62_APPROVAL':'wrong'}):
            with self.assertRaisesRegex(RuntimeError,'approval'):self.invoke('deploy')
        self.assertEqual(self.fake.connects,0)
    def test_verify_rejects_active_maintenance(self):
        self.files['apps/rumo10/maintenance.lock']=b'hold'
        with self.assertRaisesRegex(RuntimeError,'maintenance state'):self.invoke('verify-only')
    def test_verify_rejects_active_deployment(self):
        self.files['apps/rumo10/deployment.lock']=b'busy'
        with self.assertRaisesRegex(RuntimeError,'Deployment lock'):self.invoke('verify-only')
    def test_changed_managed_file_blocks(self):
        self.files[c.BASE+'app/File0.php']=b'new unapproved'
        with self.assertRaisesRegex(RuntimeError,'Managed file differs'):self.invoke('verify-only')
    def test_changed_journal_blocks(self):
        self.files['apps/rumo10/deployment-journal.json']=b'changed'
        with self.assertRaisesRegex(RuntimeError,'Journal differs'):self.invoke('verify-only')
    def receipt(self):return {'schema':'release62-backup-v1','created_at_unix':time.time(),'production_manifest_sha256':c.OLD_MANIFEST_SHA,'maintenance_sha256':c.sha(b'hold'),'database_archive_sha256':'a'*64,'files_archive_sha256':'b'*64,'recovery_archive_sha256':'b339ef3d8381508dc391ee15ca9486682c9de13423a8be36f1018c0b2b83665e','isolated_restore_verified':True,'writes_drained_verified':True}
    def test_backup_gate_rejects_missing_receipt(self):
        with self.assertRaises(RuntimeError):c.backup_gate({}, {'maintenance_sha256':c.sha(b'hold')})
    def test_backup_gate_rejects_stale_wrong_owner_or_unverified(self):
        for k,v in [('created_at_unix',1),('maintenance_sha256','f'*64),('isolated_restore_verified',False),('writes_drained_verified',False),('database_archive_sha256','bad')]:
            with self.subTest(field=k):
                r=self.receipt();r[k]=v
                with self.assertRaises(RuntimeError):c.backup_gate(r,{'maintenance_sha256':c.sha(b'hold')})
    def test_backup_gate_rejects_wrong_recovery(self):
        r=self.receipt();r['recovery_archive_sha256']='f'*64
        with self.assertRaisesRegex(RuntimeError,'recovery'):c.backup_gate(r,{'maintenance_sha256':c.sha(b'hold')})
    def test_valid_backup_attestation_passes_only_its_structural_gate(self):c.backup_gate(self.receipt(),{'maintenance_sha256':c.sha(b'hold')})
    def test_deploy_missing_backup_never_calls_writer(self):
        self.files['apps/rumo10/maintenance.lock']=b'hold'
        with patch.dict(os.environ,{'RELEASE62_ALLOW_DEPLOY':'true','RELEASE62_APPROVAL':'PUBLISH:'+c.PACKAGE_SHA}),patch.object(c.subprocess,'run',side_effect=AssertionError('writer invoked')):
            with self.assertRaisesRegex(RuntimeError,'backup receipt'):self.invoke('deploy')

if __name__=='__main__':unittest.main()
