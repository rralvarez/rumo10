from pathlib import Path
import subprocess,time,json,secrets,hashlib,os
r=Path(__file__).resolve().parents[1];root=r/'tests'/('drain-runs-'+secrets.token_hex(5));root.mkdir();php=os.environ.get("PHP_BIN",r"C:\PHP\php-8.4.26\php.exe" if os.name=="nt" else "php");results=[];children=[]
def run(mode,gate,work,owner='',timeout='2'):
 return subprocess.run([php,'-n',str(r/'tests/drain-driver.php'),mode,str(gate),str(work),owner,timeout],capture_output=True,text=True,timeout=8)
def spawn(mode,gate,work,owner='',timeout='2'):
 p=subprocess.Popen([php,'-n',str(r/'tests/drain-driver.php'),mode,str(gate),str(work),owner,timeout],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True);children.append(p);return p
def wait(path):
 for i in range(500):
  if path.exists():return
  time.sleep(.01)
 raise AssertionError('No receipt '+str(path))
def ok(name,condition):
 assert condition,name
 results.append({'test':name,'status':'PASS'})
def new():
 w=root/secrets.token_hex(4);w.mkdir();g=w/'gate';assert run('init',g,w).returncode==0;return w,g
try:
 w,g=new();a=w/'rumo10';a.mkdir();b=w/'rumohub';b.mkdir();owner=secrets.token_hex(16)
 wa=spawn('writer',g,a);wb=spawn('writer',g,b);wait(a/'entered');wait(b/'entered');pub=spawn('install',g,w,owner);wait(w/'closed')
 ok('new request rejected while previous requests active',run('probe',g,w).returncode==42)
 time.sleep(.1);ok('no install before either writer ends',not (w/'install-start').exists())
 (a/'finish').touch();wa.communicate(timeout=5);time.sleep(.1);ok('both projects must drain, not just first',not (w/'install-start').exists())
 (b/'finish').touch();wb.communicate(timeout=5);wait(w/'install-start');ok('install begins only after writes are durable',(a/'written').exists() and (b/'written').exists())
 ok('exclusive lease rejects new writer',run('probe',g,w).returncode==42)
 ok('second publisher cannot take over',run('install',g,w,secrets.token_hex(16),'.1').returncode==42)
 (w/'release').touch();pub.communicate(timeout=5);ok('validated release admits request',run('probe',g,w).returncode==0)
 # Timeout: install must never start, and closed marker must survive.
 w,g=new();a=w/'old';a.mkdir();wa=spawn('writer',g,a);wait(a/'entered');owner=secrets.token_hex(16);x=run('install',g,w,owner,'.1')
 ok('timeout refuses installation',x.returncode==42 and not (w/'install-start').exists())
 ok('timeout retains maintenance',(g/'closed.json').exists() and run('probe',g,w).returncode==42)
 (a/'finish').touch();wa.communicate(timeout=5);(w/'release').touch();ok('same owner resumes after timeout',run('resume',g,w,owner).returncode==0)
 # Real OS kill, not a caught exception.
 w,g=new();owner=secrets.token_hex(16);pub=spawn('install',g,w,owner);wait(w/'install-start');pub.kill();pub.communicate(timeout=5)
 ok('abrupt publisher termination preserves closed gate',(g/'closed.json').exists() and run('probe',g,w).returncode==42)
 ok('foreign owner cannot recover',run('resume',g,w,secrets.token_hex(16),'.1').returncode==42)
 (w/'release').touch();ok('owned durable evidence permits recovery',run('resume',g,w,owner).returncode==0)
 # Missing/malformed identity must deny admission rather than recreate a lock.
 w,g=new();(g/'identity.json').write_text('{}');ok('identity corruption fails closed',run('probe',g,w).returncode==42)
 w,g=new();(g/'writers.lock').unlink();ok('missing master lock is never recreated',run('probe',g,w).returncode==42 and not (g/'writers.lock').exists())
 w,g=new();(g/'closed.json').write_text('partial write');ok('partial closed marker refuses requests',run('probe',g,w).returncode==42)
 # Real request shutdown must retain the shared lease even after function returns.
 w,g=new();a=w/'request';a.mkdir();code='$rumoRequestGateRoot='+repr(str(g).replace('\\','/'))+'; require '+repr(str(r/'bin/request-gate.php').replace('\\','/'))+'; file_put_contents('+repr(str(a/'entered').replace('\\','/'))+',"yes"); usleep(800000); file_put_contents('+repr(str(a/'written').replace('\\','/'))+',"saved");'
 req=subprocess.Popen([php,'-n','-r',code],stdout=subprocess.PIPE,stderr=subprocess.PIPE);children.append(req);wait(a/'entered');owner=secrets.token_hex(16);pub=spawn('install',g,w,owner);wait(w/'closed');time.sleep(.1);ok('bridge integration retains lease through response',not (w/'install-start').exists());req.communicate(timeout=5);wait(w/'install-start');(w/'release').touch();pub.communicate(timeout=5)
 # Admission must cover late shutdown callbacks and destructors, not only the request body.
 w,g=new();a=w/'shutdown-request';a.mkdir()
 def phpstr(p):return repr(str(p).replace('\\','/'))
 code='$rumoRequestGateRoot='+phpstr(g)+'; require '+phpstr(r/'bin/request-gate.php')+'; register_shutdown_function(function(){file_put_contents('+phpstr(a/'shutdown-entered')+',"yes"); usleep(800000); file_put_contents('+phpstr(a/'shutdown-written')+',"saved");}); $obj=new class {function __destruct(){usleep(200000);file_put_contents('+phpstr(a/'destructor-written')+',"saved");}};'
 req=subprocess.Popen([php,'-n','-r',code],stdout=subprocess.PIPE,stderr=subprocess.PIPE);children.append(req);wait(a/'shutdown-entered');owner=secrets.token_hex(16);pub=spawn('install',g,w,owner);wait(w/'closed');time.sleep(.1)
 ok('late shutdown callback cannot outlive drain lease',not (w/'install-start').exists())
 req.communicate(timeout=5);wait(w/'install-start');ok('destructor write precedes installation',(a/'shutdown-written').exists() and (a/'destructor-written').exists());(w/'release').touch();pub.communicate(timeout=5)
 # Sessions and an intentionally blocked destructor must still be inside the lease.
 w,g=new();a=w/'session-request';a.mkdir();req=spawn('shutdown-writer',g,a);wait(a/'shutdown-entered');owner=secrets.token_hex(16);pub=spawn('install',g,w,owner);wait(w/'closed');time.sleep(.1)
 ok('session callback remains inside drain',not (w/'install-start').exists())
 ok('session XP persisted under retained lease','12345' in (a/'sess_synthetic-drain-request').read_text())
 (a/'finish').touch();wait(a/'destructor-entered');time.sleep(.1);ok('blocked destructor still prevents installation',not (w/'install-start').exists());(a/'destructor-finish').touch();req.communicate(timeout=5);wait(w/'install-start');ok('session and destructor complete before exclusive lease',(a/'shutdown-written').exists() and (a/'destructor-written').exists());(w/'release').touch();pub.communicate(timeout=5)
finally:
 for p in children:
  if p.poll() is None:p.kill();p.communicate(timeout=5)
 (r/'evidence/drain-processes.json').write_text(json.dumps({'results':results,'passed':len(results),'children_stopped':all(p.poll() is not None for p in children),'production_connections':0},indent=2))
print(json.dumps({'passed':len(results),'children_stopped':True}))
