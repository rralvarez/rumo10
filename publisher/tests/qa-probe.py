from pathlib import Path
import subprocess,sqlite3,json,hashlib,os,secrets
r=Path(__file__).resolve().parents[1];php=os.environ.get('PHP_BIN',r"C:\PHP\php-8.4.26\php.exe" if os.name=='nt' else 'php');w=r/'tests'/('qa-proof-'+secrets.token_hex(4));w.mkdir();results=[]
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
runtime=max((r/'tests').glob('v3-runtime-*'),key=lambda p:p.stat().st_mtime)
with sqlite3.connect(runtime/'synthetic.sqlite') as db:state=db.execute('SELECT state FROM saves ORDER BY id LIMIT 1').fetchone()[0]
v3=w/'v3.json';v3.write_text(state);legacy=w/'legacy.json'
code='require '+repr(str(runtime/'app/bootstrap.php').replace('\\','/'))+'; echo json_encode(Game::legacyCreate("Synthetic legacy","São Paulo","SP","meia"),JSON_THROW_ON_ERROR);'
x=subprocess.run([php,'-d','display_startup_errors=0','-r',code],capture_output=True,text=True,timeout=20);assert x.returncode==0;legacy.write_text(x.stdout)
cfg={'runtime':str(runtime),'legacy_snapshot':str(legacy),'legacy_sha256':sha(legacy),'v3_snapshot':str(v3),'v3_sha256':sha(v3)}
def check(name,c,success):
 f=w/'profile.json';f.write_text(json.dumps(c));f.chmod(0o600);env=os.environ.copy();env['RUMO10_DEVELOPMENT_V2']='0';x=subprocess.run([php,'-d','display_startup_errors=0','-d','zend.exception_ignore_args=1',str(r/'bin/qa-snapshot-probe.php'),'--profile',str(f)],capture_output=True,text=True,timeout=20,env=env)
 assert (x.returncode==0)==success,(name,x.stdout,x.stderr)
 if success:assert json.loads(x.stdout)['read_only']
 results.append({'test':name,'status':'PASS'})
check('actual legacy/v3 snapshot probe with flag off',cfg,True)
bad=dict(cfg);bad['v3_sha256']='0'*64;check('snapshot hash tamper rejected',bad,False)
badstate=json.loads(state);badstate['player_development']['received']['passe']+=1;v3.write_text(json.dumps(badstate));bad=dict(cfg);bad['v3_sha256']=sha(v3);check('corrupt XP ledger rejected',bad,False)
badstate=json.loads(state);badstate['player_development']['version']='unknown';v3.write_text(json.dumps(badstate));bad['v3_sha256']=sha(v3);check('unknown writer contract rejected',bad,False)
v3.write_text(state);check('valid snapshot remains readable after rejection',cfg,True)
assert sha(legacy)==cfg['legacy_sha256'] and sha(v3)==cfg['v3_sha256']
(r/'evidence/qa-probe.json').write_text(json.dumps({'passed':len(results),'results':results,'scope':'actual runtime, synthetic saved JSON, no database writes'},indent=2));print(json.dumps({'passed':len(results)}))
