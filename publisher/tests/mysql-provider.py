"""Real isolated MySQL provider rehearsal. Never uses the installed MySQL service."""
import pathlib,subprocess,tempfile,time,socket,json,hashlib,os,sqlite3,shutil,sys
ROOT=pathlib.Path(__file__).resolve().parents[1]
BIN=pathlib.Path(r"C:\Program Files\MySQL\MySQL Server 8.0\bin")
PHP=pathlib.Path(r"C:\PHP\php-8.4.26\php.exe")
work=ROOT/"tests"/("mysql-private-"+str(time.time_ns()));work.mkdir(mode=0o700)
assert shutil.disk_usage(work).free>1024**3
gateProc=None;gateWork=None
children=[];profiles={};summary={"status":"BLOCKED","scope":"two newly initialized local MySQL instances, synthetic data only","production_connections":0,"work":str(work)}
def run(cmd,**kw):return subprocess.run([str(x) for x in cmd],capture_output=True,timeout=180,**kw)
def port():
 with socket.socket() as s:s.bind(("127.0.0.1",0));return s.getsockname()[1]
def client(label,sql,database=None):
 args=[BIN/"mysql.exe","--defaults-extra-file="+str(profiles[label]),"--batch","--skip-column-names"]
 if database:args.append(database)
 p=run(args,input=sql.encode("utf-8"));assert p.returncode==0,p.stderr.decode(errors="replace")
 return p.stdout.decode("utf-8").strip()
try:
 for label in ("source","restore"):
  data=work/(label+"-data");data.mkdir();pno=port()
  init=run([BIN/"mysqld.exe","--no-defaults","--initialize-insecure","--basedir="+str(BIN.parent),"--datadir="+str(data)])
  (work/(label+"-initialize.log")).write_bytes(init.stdout+init.stderr)
  assert init.returncode==0,"isolated initialization failed"
  conf=work/(label+".cnf");conf.write_text("[client]\nhost=127.0.0.1\nport="+str(pno)+"\nuser=root\nprotocol=TCP\ndefault-character-set=utf8mb4\n")
  profiles[label]=conf
  proc=subprocess.Popen([str(BIN/"mysqld.exe"),"--no-defaults","--basedir="+str(BIN.parent),"--datadir="+str(data),"--port="+str(pno),"--bind-address=127.0.0.1","--mysqlx=OFF","--performance-schema=OFF","--innodb-buffer-pool-size=32M","--max-connections=10","--skip-log-bin","--log-error="+str(work/(label+"-server.log")),"--pid-file="+str(work/(label+".pid"))],
    stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,creationflags=subprocess.CREATE_NO_WINDOW)
  children.append((label,proc))
  for _ in range(180):
   if proc.poll() is not None:raise RuntimeError("isolated server exited; inspect private server log")
   try:
    p=run([BIN/"mysqladmin.exe","--defaults-extra-file="+str(conf),"ping"])
    if p.returncode==0:break
   except Exception:pass
   time.sleep(.2)
  else:raise RuntimeError("isolated server readiness timeout")
  summary[label]={"port":pno,"pid":proc.pid,"datadir":str(data),"uuid":client(label,"SELECT @@server_uuid")}
  import configparser
  identity=configparser.ConfigParser();identity.read(data/"auto.cnf")
  assert summary[label]["uuid"]==identity["auto"]["server-uuid"],"unexpected instance identity: abort before SQL writes"
  print("isolated "+label+" ready",flush=True)
 source="rumo_source_synthetic";target="rumo_restore_sandbox_stage3"
 client("source","CREATE DATABASE "+source+" CHARACTER SET utf8mb4;")
 client("restore","CREATE DATABASE "+target+" CHARACTER SET utf8mb4;")
 client("source","CREATE TABLE rh_accounts(id INT PRIMARY KEY, payload VARCHAR(100) NOT NULL) ENGINE=InnoDB;",source)
 client("source","INSERT INTO rh_accounts VALUES(1,'synthetic-hub-account-preserve');",source)
 client("source","CREATE TABLE careers(id INT PRIMARY KEY, state LONGTEXT NOT NULL, account_id INT NOT NULL DEFAULT 1, FOREIGN KEY(account_id) REFERENCES rh_accounts(id)) ENGINE=InnoDB;",source)
 # Use the actual Game-produced v3 saves from the preceding isolated test.
 runtimes=sorted((ROOT/"tests").glob("v3-runtime-*"),key=lambda x:x.stat().st_mtime)
 with sqlite3.connect(runtimes[-1]/"synthetic.sqlite") as db:rows=db.execute("SELECT id,state FROM saves ORDER BY id").fetchall()
 assert len(rows)==3
 for rid,state in rows:
  client("source","INSERT INTO careers(id,state) VALUES("+str(rid)+",CONVERT(X'"+state.encode().hex()+"' USING utf8mb4));",source)
 before=client("source","SELECT id,SHA2(state,256) FROM careers ORDER BY id",source)
 password=work/"synthetic-empty-password";password.write_text("")
 backup=work/"backup";backup.mkdir()
 cfg={"source_host":"127.0.0.1:"+str(summary["source"]["port"]),"restore_host":"127.0.0.1:"+str(summary["restore"]["port"]),
      "source_dsn":"mysql:host=127.0.0.1;port="+str(summary["source"]["port"])+";dbname="+source+";charset=utf8mb4",
      "restore_dsn":"mysql:host=127.0.0.1;port="+str(summary["restore"]["port"])+";dbname="+target+";charset=utf8mb4",
      "source_database":source,"restore_database":target,"source_user":"root","restore_user":"root",
      "source_password_file":str(password),"restore_password_file":str(password),
      "source_mysql_defaults":str(profiles["source"]),"restore_mysql_defaults":str(profiles["restore"]),
      "dump_bin":str(BIN/"mysqldump.exe"),"mysql_bin":str(BIN/"mysql.exe"),"restore_isolation_approved":True}
 profile=work/"private-profile.json";profile.write_text(json.dumps(cfg))
 request={"attempt":"synthetic-mysql-stage3","backup":str(backup)}
 args=[PHP,"-n","-d","extension_dir=C:/PHP/php-8.4.26/ext","-d","extension=php_pdo_mysql.dll",ROOT/"bin/mysql-backup-hook.php","backup","--profile",profile]
 gateWork=work/"coordinated-gate";gateWork.mkdir();gateRoot=gateWork/"gate"
 assert run([PHP,"-n",ROOT/"tests/drain-driver.php","init",gateRoot,gateWork]).returncode==0
 gateProc=subprocess.Popen([str(PHP),"-n",str(ROOT/"tests/drain-driver.php"),"install",str(gateRoot),str(gateWork),"d"*32,"2"],stdout=subprocess.PIPE,stderr=subprocess.PIPE)
 for _ in range(300):
  if (gateWork/"install-start").exists():break
  time.sleep(.01)
 else:raise RuntimeError("coordinated backup gate not acquired")
 probe=run([PHP,"-n",ROOT/"tests/drain-driver.php","probe",gateRoot,gateWork])
 assert probe.returncode==42,"writer admitted during coordinated backup"
 result=run(args,input=json.dumps(request).encode());(work/"provider-output.log").write_bytes(result.stdout+result.stderr)
 assert result.returncode==0,"provider rejected rehearsal; inspect local private log"
 receipt=json.loads(result.stdout);assert receipt["ok"] and receipt["restore_tested"]
 after=client("source","SELECT id,SHA2(state,256) FROM careers ORDER BY id",source)
 restored=client("restore","SELECT id,SHA2(state,256) FROM careers ORDER BY id",target)
 assert before==after==restored,"source/restored row mismatch"
 hub=client("source","SELECT id,SHA2(payload,256) FROM rh_accounts",source)
 assert hub==client("restore","SELECT id,SHA2(payload,256) FROM rh_accounts",target)
 assert client("restore","SELECT COUNT(*) FROM careers c LEFT JOIN rh_accounts a ON c.account_id=a.id WHERE a.id IS NULL",target)=="0"
 (gateWork/"release").touch();gateProc.communicate(timeout=10);assert gateProc.returncode==0
 # Same-instance alias must fail before any restoration.
 bad=dict(cfg);bad["restore_dsn"]=cfg["source_dsn"];bad["restore_mysql_defaults"]=cfg["source_mysql_defaults"]
 alias=work/"private-alias-profile.json";alias.write_text(json.dumps(bad))
 badargs=args[:-1]+[alias]
 rejected=run(badargs,input=json.dumps(request).encode());assert rejected.returncode!=0
 # Deliberate repeat into nonempty restore target MUST fail before import.
 repeat=run(args,input=json.dumps(request).encode());assert repeat.returncode!=0
 summary.update(status="PASS",provider="real mysql-backup-hook.php",rows=len(rows),source_unchanged=True,restored_rows_identical=True,
                same_instance_refused_by_uuid=True,nonempty_restore_refused=True,dump_sha256=receipt["files"],
                coordinated_gate_held=True,shared_hub_account_verified=True,foreign_keys_valid=True,native_repository_integration=False,mysql_version=client("source","SELECT VERSION()"))
except Exception as e:
 summary.update(status="FAIL",error=str(e))
finally:
 if gateProc is not None and gateProc.poll() is None:
  (gateWork/"release").touch()
  try:gateProc.communicate(timeout=5)
  except subprocess.TimeoutExpired:gateProc.kill();gateProc.communicate()
 summary["gate_process_stopped"]=gateProc is None or gateProc.poll() is not None
 for label,proc in reversed(children):
  if proc.poll() is None:
   try:run([BIN/"mysqladmin.exe","--defaults-extra-file="+str(profiles[label]),"shutdown"])
   except Exception:pass
   try:proc.wait(timeout=30)
   except subprocess.TimeoutExpired:proc.terminate();proc.wait(timeout=10)
 summary["test_servers_stopped"]=all(p.poll() is not None for _,p in children)
 (ROOT/"evidence/mysql-provider-test.json").write_text(json.dumps(summary,indent=2))
 print(json.dumps({k:v for k,v in summary.items() if k not in ("source","restore","dump_sha256")},indent=2),flush=True)
sys.exit(0 if summary["status"]=="PASS" else 1)
