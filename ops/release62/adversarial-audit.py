"""Independent in-memory FTPS adversary. No sockets, credentials or production writes."""
import contextlib
import ftplib
import hashlib
import io
import json
import os
from pathlib import Path
import runpy
import ssl
import sys
import tarfile
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parent
BASE = 'apps/rumo10/current/'
LOCK = 'apps/rumo10/deployment.lock'
JOURNAL = 'apps/rumo10/deployment-journal.json'
MANIFEST = BASE+'release.json'
BOOT = BASE+'app/bootstrap.php'

class Server:
    def __init__(self):
        self.files={BASE+'.env': b'PRIVATE=preserve', BASE+'storage/sessions/qa': b'private session'}
        self.events=[]
        self.hook=lambda *args: None
        self.connections=0
        self.context=None
        self.connected=False
    def event(self, op, path='', stage='before'):
        self.events.append((op,path,stage))
        self.hook(op,path,stage,self)
    def factory(self, **kw):
        self.context=kw['context']
        assert self.context.check_hostname and self.context.verify_mode == ssl.CERT_REQUIRED
        return self
    def __enter__(self): return self
    def __exit__(self,*args): self.close();return False
    def connect(self, address, port):
        self.connected=True;self.connections+=1;self.event('CONNECT',str((address,port)))
    def close(self): self.connected=False;self.event('CLOSE')
    def auth(self): self.event('AUTH', self.host)
    def login(self,*args): self.event('LOGIN')
    def prot_p(self): self.event('PROT_P')
    def set_pasv(self,value): self.event('PASV',str(value))
    def cwd(self,path): self.event('CWD',path)
    def voidcmd(self,cmd): return '200'
    def size(self,path): return len(self.files[path]) if path in self.files else None
    def nlst(self): return ['public_html','rumohub.rumoacamisa10.com.br']
    def mlsd(self,parent):
        self.event('MLSD',parent)
        prefix=parent+'/'
        names=sorted({p[len(prefix):].split('/')[0] for p in self.files if p.startswith(prefix)})
        for name in names: yield name,{}
    def mkd(self,path): self.event('MKD',path)
    def retrbinary(self,cmd,callback):
        path=cmd[5:];self.event('RETR',path)
        if path not in self.files: raise ftplib.error_perm('550 missing')
        data=self.files[path];middle=max(1,len(data)//2)
        callback(data[:middle]);self.event('RETR',path,'partial')
        callback(data[middle:]);self.event('RETR',path,'complete')
    def storbinary(self,cmd,stream):
        path=cmd[5:];self.event('STOR',path);self.files[path]=stream.read();self.event('STOR',path,'applied')
    def rename(self,source,target):
        self.event('RENAME',target);self.files[target]=self.files.pop(source);self.event('RENAME',target,'applied')
    def delete(self,path):
        self.event('DELE',path);self.files.pop(path,None);self.event('DELE',path,'applied')

class Audit(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.s=Server()
        self.a={'app/bootstrap.php':b'<?php // A','public/index.php':b'<?php // Front','public/.htaccess':b'RewriteEngine On\n','public/assets/style.css':b'body{color:black}'}
        self.b={**self.a,'app/bootstrap.php':b'<?php // B','app/OnlyB.php':b'<?php // B-only','public/assets/style.css':b'body{color:blue}'}
        self.tar_a,self.man_a=self.archive(self.a,'a');self.tar_b,self.man_b=self.archive(self.b,'b')
        self.assertIsNone(self.run_publish(self.tar_a));self.s.events.clear();self.old_manifest=self.s.files[MANIFEST]
    def tearDown(self):
        evidence = ROOT/'case-evidence'
        evidence.mkdir(exist_ok=True)
        records = json.loads(self.s.files.get(JOURNAL,b'{"records":{}}')).get('records',{})
        data = {'test':self._testMethodName,'lock_present':LOCK in self.s.files,
                'manifest':json.loads(self.s.files[MANIFEST]) if MANIFEST in self.s.files else None,
                'journal_status':json.loads(self.s.files.get(JOURNAL,b'{}')).get('status'),
                'journal_records':records,
                'file_hashes':{p:hashlib.sha256(v).hexdigest() for p,v in self.s.files.items()},
                'events':self.s.events}
        (evidence/(self._testMethodName+'.json')).write_text(json.dumps(data,indent=2))
        self.tmp.cleanup()
    def archive(self,files,name):
        manifest={'project':'rumo10','files':{n:hashlib.sha256(v).hexdigest() for n,v in files.items()}}
        path=Path(self.tmp.name)/(name+'.tar.gz')
        with tarfile.open(path,'w:gz') as t:
            for n,v in {**files,'release.json':json.dumps(manifest).encode()}.items():
                item=tarfile.TarInfo(n);item.size=len(v);t.addfile(item,io.BytesIO(v))
        return path,manifest
    def run_publish(self,path):
        try:
            with patch.object(sys,'argv',['deploy-ftps.py',str(path),'rumo10']), patch.dict(os.environ,{'UOL_FTP_HOST':'fixture.invalid','UOL_FTP_ADDRESS':'192.0.2.1','UOL_FTP_USERNAME':'synthetic','UOL_FTP_PASSWORD':'synthetic'}),patch('ftplib.FTP_TLS',side_effect=self.s.factory),patch('socket.create_connection'),patch('ssl.SSLContext.wrap_socket'),contextlib.redirect_stdout(io.StringIO()):
                runpy.run_path(str(ROOT/'bin/deploy-ftps.py'),run_name='__main__')
        except BaseException as e: return e
        return None
    def trigger(self,error=EOFError,stage='before',change=None,persistent=False):
        state={'fired':0,'changed':False}
        def hook(op,path,when,s):
            if op=='RETR' and path==BOOT and when==stage and LOCK in s.files and (persistent or not state['fired']):
                state['fired']+=1;raise error('421 synthetic read lost')
            if op=='CONNECT' and state['fired'] and not state['changed']:
                state['changed']=True
                if change:change(s)
        self.s.hook=hook;return state
    def assert_safe_abort(self,error):
        self.assertIsNotNone(error,'Publisher incorrectly declared success')
        self.assertIn(LOCK,self.s.files)
        self.assertEqual(self.s.files[MANIFEST],self.old_manifest)
    def assert_success(self,error):
        self.assertIsNone(error,str(error));self.assertEqual(json.loads(self.s.files[MANIFEST]),self.man_b);self.assertNotIn(LOCK,self.s.files)
        self.assertEqual(self.s.files[BASE+'.env'],b'PRIVATE=preserve');self.assertEqual(self.s.files[BASE+'storage/sessions/qa'],b'private session')
    def test_eof_before_bytes(self):
        state=self.trigger();self.assert_success(self.run_publish(self.tar_b));self.assertEqual(state['fired'],1)
    def test_eof_partial_payload_discarded(self):
        state=self.trigger(stage='partial');self.assert_success(self.run_publish(self.tar_b));self.assertEqual(state['fired'],1)
    def test_eof_after_payload_requires_ack(self):
        state=self.trigger(stage='complete');self.assert_success(self.run_publish(self.tar_b));self.assertEqual(state['fired'],1)
    def test_timeout(self):
        self.trigger(error=TimeoutError);self.assert_success(self.run_publish(self.tar_b))
    def test_repeated_disconnect_bounded_and_blocked(self):
        state=self.trigger(persistent=True);error=self.run_publish(self.tar_b);self.assert_safe_abort(error);self.assertEqual(state['fired'],2);self.assertEqual(json.loads(self.s.files[JOURNAL])['status'],'blocked')
    def test_handshake_tls_failure(self):
        state=self.trigger();old=self.s.hook
        def hook(op,p,when,s):
            old(op,p,when,s)
            if op=='AUTH' and state['fired']:raise ssl.SSLError('TLS handshake failed')
        self.s.hook=hook;self.assert_safe_abort(self.run_publish(self.tar_b))
    def test_invalid_certificate(self):
        state=self.trigger();old=self.s.hook
        def hook(op,p,when,s):
            old(op,p,when,s)
            if op=='AUTH' and state['fired']:raise ssl.SSLCertVerificationError('invalid certificate')
        self.s.hook=hook;self.assert_safe_abort(self.run_publish(self.tar_b))
    def test_rejected_credentials(self):
        state=self.trigger();old=self.s.hook
        def hook(op,p,when,s):
            old(op,p,when,s)
            if op=='LOGIN' and state['fired']:raise ftplib.error_perm('530 rejected')
        self.s.hook=hook;self.assert_safe_abort(self.run_publish(self.tar_b))
    def test_lock_replaced(self):
        self.trigger(change=lambda s:s.files.__setitem__(LOCK,b'new-owner'));self.assert_safe_abort(self.run_publish(self.tar_b));self.assertEqual(self.s.files[LOCK],b'new-owner')
    def test_lock_removed(self):
        self.trigger(change=lambda s:s.files.pop(LOCK));error=self.run_publish(self.tar_b);self.assertIsNotNone(error);self.assertEqual(self.s.files[MANIFEST],self.old_manifest)
    def test_lock_timestamp_changed(self):
        def change(s):
            lock=json.loads(s.files[LOCK]);lock['started_at']=1;s.files[LOCK]=json.dumps(lock).encode()
        self.trigger(change=change);self.assert_safe_abort(self.run_publish(self.tar_b))
    def test_expired_lock_payload_change_blocks(self):
        def change(s):
            lock=json.loads(s.files[LOCK]);lock['expires']=1;s.files[LOCK]=json.dumps(lock).encode()
        self.trigger(change=change);self.assert_safe_abort(self.run_publish(self.tar_b))
    def test_lock_age_alone_has_no_expiry_protocol(self):
        # Existing lock has no expires field/TTL. Wall clock passage alone is not revocation.
        self.trigger();old=self.s.hook
        with patch('time.time',side_effect=lambda:4102444800):self.assert_success(self.run_publish(self.tar_b))
    def test_no_write_retry_stor_ack_lost(self):
        fired=[]
        def hook(op,p,when,s):
            if op=='STOR' and '.rumo-upload-' in p and when=='applied' and not fired:
                fired.append(p);raise EOFError('STOR ack lost')
        self.s.hook=hook;self.assert_safe_abort(self.run_publish(self.tar_b));self.assertEqual(len(fired),1)
        self.assertEqual(sum(op=='STOR' and p==fired[0] and when=='before' for op,p,when in self.s.events),1)
        journal=json.loads(self.s.files[JOURNAL]);self.assertEqual(journal['status'],'blocked');self.assertTrue(any(x['pending'] for x in journal['records'].values()))
    def test_no_write_retry_rename_ack_lost(self):
        fired=[]
        def hook(op,p,when,s):
            if op=='RENAME' and p=='public_html/index.php' and when=='applied' and not fired:fired.append(p);raise EOFError('rename ack lost')
        self.s.hook=hook;self.assert_safe_abort(self.run_publish(self.tar_b));self.assertEqual(sum(op=='RENAME' and p=='public_html/index.php' and when=='before' for op,p,when in self.s.events),1)
    def test_no_write_retry_delete_ack_lost(self):
        self.assert_success(self.run_publish(self.tar_b));self.old_manifest=self.s.files[MANIFEST];self.s.events.clear();fired=[]
        def hook(op,p,when,s):
            if op=='DELE' and p==BASE+'app/OnlyB.php' and when=='applied' and not fired:fired.append(p);raise EOFError('DELE ack lost')
        self.s.hook=hook;self.assert_safe_abort(self.run_publish(self.tar_a));self.assertEqual(sum(op=='DELE' and p==fired[0] and when=='before' for op,p,when in self.s.events),1)
    def test_corrupt_temp_after_read_retry_cannot_promote(self):
        state={'fired':False}
        def hook(op,p,when,s):
            if op=='RETR' and '.rumo-upload-' in p and p.endswith('-bootstrap.php') and not state['fired']:
                state['fired']=True;s.files[p]=b'corrupt remote temp';raise EOFError('read lost before validation')
        self.s.hook=hook;self.assert_safe_abort(self.run_publish(self.tar_b));self.assertTrue(state['fired'])
    def test_manifest_changed_on_reconnect_must_abort(self):
        external_path=BASE+'app/External.php'
        def change(s):
            s.files[external_path]=b'<?php // independently introduced'
            external={'project':'rumo10','files':{**self.man_a['files'],'app/External.php':hashlib.sha256(s.files[external_path]).hexdigest()}}
            s.files[MANIFEST]=json.dumps(external).encode();self.external_manifest=s.files[MANIFEST]
        state=self.trigger(change=change);error=self.run_publish(self.tar_b)
        self.assertTrue(state['changed'])
        self.assertIsNotNone(error,'Manifest changed during reconnect; publisher succeeded and retained untracked executable')
        self.assertEqual(self.s.files[MANIFEST],self.external_manifest)
        self.assertIn(LOCK,self.s.files)
    def test_journal_changed_on_reconnect_must_not_lose_evidence(self):
        residue=BASE+'app/Unresolved.php'
        def change(s):
            s.files[residue]=b'<?php // unresolved'
            journal=json.loads(s.files[JOURNAL]);journal['records'][residue]={'ownership':'uncertain','digest':hashlib.sha256(s.files[residue]).hexdigest(),'pending':{'op':'upload','expected_digest':'a'*64,'before_digest':None}};s.files[JOURNAL]=json.dumps(journal).encode()
        state=self.trigger(change=change);error=self.run_publish(self.tar_b)
        self.assertTrue(state['changed']);self.assertIsNotNone(error,'Reconnect overwrote newer journal and released untracked executable')
        self.assertIn(residue,json.loads(self.s.files[JOURNAL])['records']);self.assertIn(LOCK,self.s.files)
    def test_destination_changed_during_temp_verification_must_abort(self):
        fired=[];changed=[];external=b'<?php // concurrent hotfix must not be overwritten'
        def hook(op,p,when,s):
            if op=='RETR' and '.rumo-upload-' in p and p.endswith('-bootstrap.php') and when=='before' and not fired:
                fired.append(True);raise EOFError('read lost after STOR, before rename')
            if op=='CONNECT' and fired and not changed:
                changed.append(True);s.files[BOOT]=external
        self.s.hook=hook;error=self.run_publish(self.tar_b)
        self.assertTrue(changed);self.assertIsNotNone(error,'Destination changed after before_digest observation but was overwritten')
        self.assertEqual(self.s.files[BOOT],external);self.assertEqual(self.s.files[MANIFEST],self.old_manifest);self.assertIn(LOCK,self.s.files)

if __name__=='__main__':
    out=io.StringIO();result=unittest.TextTestRunner(stream=out,verbosity=2).run(unittest.defaultTestLoader.loadTestsFromTestCase(Audit))
    (ROOT/'adversarial-results.log').write_text(out.getvalue(),encoding='utf-8')
    receipt={'run':result.testsRun,'passed':result.testsRun-len(result.failures)-len(result.errors),'failures':[str(x) for x,_ in result.failures],'errors':[str(x) for x,_ in result.errors],'simulator_only':True}
    (ROOT/'adversarial-results.json').write_text(json.dumps(receipt,indent=2));print(out.getvalue());print(json.dumps(receipt));sys.exit(not result.wasSuccessful())
