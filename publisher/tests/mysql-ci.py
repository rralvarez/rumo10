"""Real MySQL backup proof in two owned Docker containers on this disposable runner only."""
from pathlib import Path
import os,sys,json,secrets,subprocess,time,sqlite3,hashlib,shutil,signal
ROOT=Path(__file__).resolve().parents[1]
PHP=os.environ.get('PHP_BIN','php');MYSQL=shutil.which('mysql');DUMP=shutil.which('mysqldump')
owner='rumo-ci-'+os.environ.get('GITHUB_RUN_ID','local')+'-'+secrets.token_hex(4)
work=ROOT/'tests'/('mysql-ci-'+secrets.token_hex(5));work.mkdir(mode=0o700)
summary={'status':'FAIL','scope':'disposable Docker MySQL only; never production','owner':owner,'production_connections':0};containers={};gate=None;network=None

def run(args,input=None,timeout=180,check=True):
 p=subprocess.run([str(v) for v in args],input=input,capture_output=True,timeout=timeout)
 if check and p.returncode:raise RuntimeError('isolated command failed; credentials withheld')
 return p

def client(label,sql,database=None):
 cmd=[MYSQL,'--defaults-extra-file='+str(work/(label+'.cnf')),'--batch','--skip-column-names']
 if database:cmd.append(database)
 return run(cmd,input=sql.encode()).stdout.decode().strip()

def cleanup():
 global gate
 if gate is not None and gate.poll() is None:
  (work/'gate-work/release').touch()
  try:gate.communicate(timeout=5)
  except subprocess.TimeoutExpired:gate.kill();gate.communicate(timeout=5)
 good=True
 for label,cid in containers.items():
  q=run(['docker','inspect',cid],check=False)
  if not q.returncode:
   j=json.loads(q.stdout)[0]
   if j['Id']!=cid or j['Config']['Labels'].get('rumo.ci.owner')!=owner:good=False;continue
   good=run(['docker','rm','-f',cid],check=False).returncode==0 and good
 if network:
  q=run(['docker','network','inspect',network],check=False)
  if not q.returncode:
   j=json.loads(q.stdout)[0]
   if j['Labels'].get('rumo.ci.owner')==owner:good=run(['docker','network','rm',network],check=False).returncode==0 and good
   else:good=False
 return good
try:
 if os.name!='posix' or not MYSQL or not DUMP:raise RuntimeError('Linux MySQL CLI prerequisites missing')
 signal.signal(signal.SIGTERM,lambda *_: (_ for _ in ()).throw(RuntimeError('runner interrupted')))
 network=run(['docker','network','create','--label','rumo.ci.owner='+owner,owner]).stdout.decode().strip()
 password=secrets.token_hex(20);pw=work/'password';pw.write_text(password);pw.chmod(0o600)
 ports={};uuids={}
 for label in ['source','restore']:
  p=run(['docker','run','-d','--name',owner+'-'+label,'--label','rumo.ci.owner='+owner,'--network',network,
    '-e','MYSQL_ROOT_PASSWORD='+password,'-e','MYSQL_ROOT_HOST=%','-p','127.0.0.1::3306','mysql:8.0.44',
    '--innodb-buffer-pool-size=32M','--max-connections=12','--skip-log-bin'],timeout=300)
  cid=p.stdout.decode().strip();containers[label]=cid
  info=json.loads(run(['docker','inspect',cid]).stdout)[0];ports[label]=int(info['NetworkSettings']['Ports']['3306/tcp'][0]['HostPort'])
  cnf=work/(label+'.cnf');cnf.write_text('[client]\nhost=127.0.0.1\nport='+str(ports[label])+'\nuser=root\npassword='+password+'\nprotocol=TCP\n');cnf.chmod(0o600)
  for _ in range(120):
   q=run([MYSQL,'--defaults-extra-file='+str(cnf),'--batch','--skip-column-names','--execute=SELECT @@server_uuid'],check=False,timeout=5)
   if q.returncode==0:uuids[label]=q.stdout.decode().strip();break
   time.sleep(1)
  else:raise RuntimeError('isolated MySQL startup timeout')
  inside=run(['docker','exec','-e','MYSQL_PWD='+password,cid,'mysql','-uroot','-NBe','SELECT @@server_uuid']).stdout.decode().strip()
  if inside!=uuids[label]:raise RuntimeError('host connection does not match owned container')
 if uuids['source']==uuids['restore']:raise RuntimeError('instances must differ')
 source='rumo_source_synthetic';target='rumo_restore_sandbox_ci'
 client('source','CREATE DATABASE '+source+' CHARACTER SET utf8mb4;');client('restore','CREATE DATABASE '+target+' CHARACTER SET utf8mb4;')
 client('source',"CREATE TABLE rh_accounts(id INT PRIMARY KEY,payload VARCHAR(100) NOT NULL) ENGINE=InnoDB; INSERT INTO rh_accounts VALUES(1,'synthetic-hub-preserve');",source)
 client('source','CREATE TABLE careers(id INT PRIMARY KEY,state LONGTEXT NOT NULL,account_id INT DEFAULT 1,FOREIGN KEY(account_id) REFERENCES rh_accounts(id)) ENGINE=InnoDB;',source)
 runtimes=sorted((ROOT/'tests').glob('v3-runtime-*'),key=lambda p:p.stat().st_mtime)
 if runtimes:
  with sqlite3.connect(runtimes[-1]/'synthetic.sqlite') as db:rows=db.execute('SELECT id,state FROM saves ORDER BY id').fetchall()
  state_source='actual Game-generated v3 JSON fixtures'
 else:rows=[(i,json.dumps({'synthetic':True,'hub_test':i,'xp':12345})) for i in range(1,4)];state_source='Hub-only synthetic JSON; no Rumo10 compatibility claim'
 for rid,state in rows:client('source',"INSERT INTO careers(id,state) VALUES("+str(rid)+",CONVERT(X'"+state.encode().hex()+"' USING utf8mb4));",source)
 before=client('source','SELECT id,SHA2(state,256) FROM careers ORDER BY id',source)
 cfg={'source_host':'127.0.0.1:'+str(ports['source']),'restore_host':'127.0.0.1:'+str(ports['restore']),
  'source_dsn':'mysql:host=127.0.0.1;port='+str(ports['source'])+';dbname='+source+';charset=utf8mb4',
  'restore_dsn':'mysql:host=127.0.0.1;port='+str(ports['restore'])+';dbname='+target+';charset=utf8mb4',
  'source_database':source,'restore_database':target,'source_user':'root','restore_user':'root','source_password_file':str(pw),'restore_password_file':str(pw),
  'source_mysql_defaults':str(work/'source.cnf'),'restore_mysql_defaults':str(work/'restore.cnf'),'dump_bin':DUMP,'mysql_bin':MYSQL,'restore_isolation_approved':True}
 profile=work/'profile.json';profile.write_text(json.dumps(cfg));profile.chmod(0o600);backup=work/'backup';backup.mkdir(mode=0o700)
 gw=work/'gate-work';gw.mkdir();run([PHP,'-n',ROOT/'tests/drain-driver.php','init',gw/'gate',gw])
 gate=subprocess.Popen([PHP,'-n',str(ROOT/'tests/drain-driver.php'),'install',str(gw/'gate'),str(gw),'d'*32,'2'],stdout=subprocess.PIPE,stderr=subprocess.PIPE)
 for _ in range(300):
  if (gw/'install-start').exists():break
  time.sleep(.01)
 else:raise RuntimeError('exclusive gate not acquired')
 if run([PHP,'-n',ROOT/'tests/drain-driver.php','probe',gw/'gate',gw],check=False).returncode!=42:raise RuntimeError('writer entered during backup')
 args=[PHP,ROOT/'bin/mysql-backup-hook.php','backup','--profile',profile];request=json.dumps({'attempt':'ci-synthetic-only','backup':str(backup)}).encode()
 q=run(args,input=request);receipt=json.loads(q.stdout)
 if not receipt['ok'] or not receipt['restore_tested']:raise RuntimeError('backup receipt rejected')
 after=client('source','SELECT id,SHA2(state,256) FROM careers ORDER BY id',source);restored=client('restore','SELECT id,SHA2(state,256) FROM careers ORDER BY id',target)
 if not before==after==restored:raise RuntimeError('save hash mismatch')
 if client('source','SELECT SHA2(payload,256) FROM rh_accounts',source)!=client('restore','SELECT SHA2(payload,256) FROM rh_accounts',target):raise RuntimeError('Hub account mismatch')
 if client('restore','SELECT COUNT(*) FROM careers c LEFT JOIN rh_accounts a ON c.account_id=a.id WHERE a.id IS NULL',target)!='0':raise RuntimeError('orphan foreign key')
 bad=dict(cfg);bad['restore_dsn']=cfg['source_dsn'];bad['restore_mysql_defaults']=cfg['source_mysql_defaults'];alias=work/'alias.json';alias.write_text(json.dumps(bad));alias.chmod(0o600)
 if run(args[:-1]+[alias],input=request,check=False).returncode==0:raise RuntimeError('same-instance restore accepted')
 if run(args,input=request,check=False).returncode==0:raise RuntimeError('nonempty restore target accepted')
 (gw/'release').touch();gate.communicate(timeout=10)
 summary.update(status='PASS',mysql_version=client('source','SELECT VERSION()'),rows=len(rows),state_source=state_source,
  shared_account_and_foreign_keys=True,source_unchanged=True,restored_hashes_identical=True,same_instance_refused=True,nonempty_target_refused=True,coordinated_gate_held=True,
  native_repository_integration=False,container_image='mysql:8.0.44')
except Exception as ex:summary.update(status='FAIL',error_type=type(ex).__name__,error='isolated MySQL rehearsal failed; credentials withheld')
finally:
 try:summary['owned_containers_cleaned']=cleanup()
 except Exception:summary['owned_containers_cleaned']=False
 if not summary['owned_containers_cleaned']:summary['status']='FAIL'
 (ROOT/'evidence/mysql-ci.json').write_text(json.dumps(summary,indent=2))
print(json.dumps(summary,indent=2));sys.exit(0 if summary['status']=='PASS' else 1)
