"""Local validation entrypoint; no production connection, token or git mutation."""
import argparse,datetime,hashlib,importlib.util,io,json,os,pathlib,socket,subprocess,sys,tarfile,time,zipfile
ROOT=pathlib.Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser()
parser.add_argument('--project',choices=['rumo10','rumohub'])
parser.add_argument('--source',type=pathlib.Path)
parser.add_argument("--frozen-archive",type=pathlib.Path)
opts=parser.parse_args()
if opts.project:os.environ["PUBLISHER_TEST_PROJECT"]=opts.project
if opts.frozen_archive:
 if opts.project!="rumo10":parser.error("frozen archive is approved for rumo10 only")
 expected=json.loads((ROOT/"config/approved-release62.json").read_text())["package_sha256"]
 if hashlib.sha256(opts.frozen_archive.read_bytes()).hexdigest()!=expected:parser.error("approved TAR digest mismatch")
 os.environ["REPRESENTATIVE_RUMO10"]=str(opts.frozen_archive.resolve())
 os.environ["APPROVED_RELEASE62_ARCHIVE"]=str(opts.frozen_archive.resolve())
if opts.project and not opts.frozen_archive:
 if not opts.source:parser.error('--source required for project CI')
 os.environ['PUBLISHER_TEST_PROJECT']=opts.project
 spec=importlib.util.spec_from_file_location('produce_ci',ROOT/'bin/produce.py')
 module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
 sha0=subprocess.check_output(['git','-C',str(opts.source),'rev-parse','HEAD'],text=True).strip()
 out=ROOT/'tests'/('ci-representative-'+str(time.time_ns()))
 module.produce(opts.source,sha0,opts.project,{'repository':'rralvarez/'+opts.project,'workflow_id':7,'run_id':100,'run_number':2,'run_attempt':1,'data_contracts':['rumo10-development-v3'] if opts.project=='rumo10' else ['rumohub-writer-v1']},out)
 os.environ['REPRESENTATIVE_'+opts.project.upper()]=str(out/'release.tar.gz')
PHP=os.environ.get("PHP_BIN",r"C:\PHP\php-8.4.26\php.exe" if os.name=="nt" else "php")
ARGS=["-n","-d",r"extension_dir=C:\PHP\php-8.4.26\ext","-d","extension=php_sqlite3.dll","-d","memory_limit=512M"] if os.name=="nt" else ["-d","memory_limit=512M"]
F=ROOT/"tests/fixtures";F.mkdir(exist_ok=True)
def sha(b):return hashlib.sha256(b).hexdigest()
def fixture(name,bad=None,extra=None):
 data={"app/bootstrap.php":b"<?php // fixture","public/index.php":b"<?php echo 'synthetic';","public/.htaccess":b"Options -Indexes\n","public/assets/style.css":b"new-css","app/PlayerDevelopment.php":b"<?php // development-v3-isolated","app/new.php":b"<?php // new"}
 data.update(extra or {})
 manifest={"project":"rumo10","files":{p:sha(b) for p,b in data.items()}}
 with tarfile.open(F/(name+".tar.gz"),"w:gz",format=tarfile.USTAR_FORMAT) as t:
  for p,b in list(data.items())+[("release.json",json.dumps(manifest).encode())]:
   m=tarfile.TarInfo(p);m.size=len(b);m.mode=0o640;t.addfile(m,io.BytesIO(b))
  if bad=="duplicate":
   m=tarfile.TarInfo("app/bootstrap.php");m.size=3;t.addfile(m,io.BytesIO(b"bad"))
  if bad in ("symlink","hardlink"):
   m=tarfile.TarInfo("app/link");m.type=tarfile.SYMTYPE if bad=="symlink" else tarfile.LNKTYPE;m.linkname="/etc/passwd";t.addfile(m)
  if bad=="traversal":
   m=tarfile.TarInfo("../escape");m.size=3;t.addfile(m,io.BytesIO(b"bad"))
for x in ("good","duplicate","symlink","hardlink","traversal"):fixture(x,None if x=="good" else x)
fixture("protected",extra={"storage/saves/private.json":b"do not install","app/clients.php":b"do not install","app/.env":b"do not install"})
b=(F/"good.tar.gz").read_bytes();(F/"truncated.tar.gz").write_bytes(b[:len(b)//2])
head="a"*40
run={"id":100,"repository":{"full_name":"rralvarez/rumo10"},"workflow_id":7,"head_branch":"main","event":"push","status":"completed","conclusion":"success","head_sha":head,"run_number":2,"run_attempt":1}
jobs=[{"name":"checks","status":"completed","conclusion":"success","head_sha":head,"run_id":100,"steps":[{"conclusion":"success"}]}]
env={"schema":"rumo-pull-v1","project":"rumo10","repository":"rralvarez/rumo10","commit":head,"workflow_id":7,"run_id":100,"run_attempt":1,"run_number":2,"package_sha256":sha((F/"good.tar.gz").read_bytes()),"data_contracts":["rumo10-development-v3"]}
with zipfile.ZipFile(F/"client.zip","w",zipfile.ZIP_DEFLATED) as z:z.writestr("candidate.json",json.dumps(env));z.write(F/"good.tar.gz","release.tar.gz")
artifact={"id":200,"name":"release-rumo10-"+head,"expired":False,"digest":"sha256:"+sha((F/"client.zip").read_bytes()),"workflow_run":{"id":100,"head_sha":head}}
(F/"client.json").write_text(json.dumps({"run":run,"artifact":artifact,"jobs":jobs}))
for n in ("duplicate","traversal"):
 with zipfile.ZipFile(F/(n+".zip"),"w") as z:z.writestr("candidate.json","{}");z.writestr("candidate.json" if n=="duplicate" else "../escape","bad")
results=[]
def command(name,args,timeout=300):
 t=time.perf_counter();p=subprocess.run(args,capture_output=True,text=True,timeout=timeout)
 (ROOT/"evidence"/(name+".log")).write_text(p.stdout+p.stderr,encoding="utf-8")
 results.append({"name":name,"status":"PASS" if p.returncode==0 else "FAIL","returncode":p.returncode,"seconds":round(time.perf_counter()-t,3)})
 print(results[-1],flush=True);return p.returncode
# Start a loopback-only synthetic HTTP maintenance fixture and terminate it at the end.
with socket.socket() as s:s.bind(("127.0.0.1",0));port=s.getsockname()[1]
(ROOT/"tests/http-port.txt").write_text(str(port))
with (ROOT/"evidence/http-fixture.log").open("w") as log:
 server=subprocess.Popen([PHP,*ARGS,"-S",f"127.0.0.1:{port}",str(ROOT/"tests/router.php")],stdout=log,stderr=log)
 try:
  for _ in range(50):
   try:
    with socket.create_connection(("127.0.0.1",port),timeout=.1):break
   except OSError:time.sleep(.05)
  command("publisher-tests",[PHP,*ARGS,str(ROOT/"tests/publisher.php")])
 finally:server.terminate();server.wait(timeout=10)
command("client-tests",[PHP,*ARGS,str(ROOT/"tests/client.php")])
saveargs=[*ARGS,"-d","extension=php_mbstring.dll"] if os.name=="nt" else ARGS
if opts.project!='rumohub':
 command("saves-tests",[PHP,*saveargs,str(ROOT/"tests/saves.php")])
else:results.append({"name":"saves-tests","status":"NOT_TESTED","reason":"Rumo10-only runtime, not Hub test"})
command("drain-processes",[sys.executable,str(ROOT/"tests/drain-processes.py")])
command("legacy-fence",[PHP,*ARGS,str(ROOT/"tests/legacy-fence.php")])
command("workflow-static",[sys.executable,str(ROOT/"tests/workflow-static.py")])
if opts.project!='rumohub':command("qa-probe",[sys.executable,str(ROOT/"tests/qa-probe.py")])
# Lint all implementation/provider/test PHP, never production.
lint=[]
for d in ("lib","bin","tests"):
 for f in (ROOT/d).glob("*.php"):
  p=subprocess.run([PHP,"-n","-l",str(f)],capture_output=True,text=True)
  lint.append({"file":str(f.relative_to(ROOT)),"returncode":p.returncode,"output":p.stdout+p.stderr})
(ROOT/"evidence/lint.json").write_text(json.dumps(lint,indent=2))
results.append({"name":"php-lint","status":"PASS" if all(x["returncode"]==0 for x in lint) else "FAIL","files":len(lint)})
# Real producer tests operate on immutable Git objects only.
spec=importlib.util.spec_from_file_location("produce",ROOT/"bin/produce.py");producer=importlib.util.module_from_spec(spec);spec.loader.exec_module(producer)
producer_results=[]
for project in (() if opts.frozen_archive else ("rumo10","rumohub")):
 repo=opts.source if opts.project==project else pathlib.Path(r'C:\inetpub\wwwroot')/project
 if not repo.exists():
  producer_results.append({"project":project,"status":"BLOCKED","reason":"local source reference unavailable"});continue
 commit=subprocess.check_output(["git","-C",str(repo),"rev-parse","HEAD"],text=True).strip()
 out=ROOT/"tests"/("producer-AUDIT_ONLY-"+project+"-"+str(time.time_ns()))
 meta={"repository":"rralvarez/"+project,"workflow_id":7,"run_id":100,"run_number":2,"run_attempt":1,"data_contracts":["rumo10-development-v3"] if project=="rumo10" else ["rumohub-writer-v1"]}
 e=producer.produce(repo,commit,project,meta,out)
 with tarfile.open(out/"release.tar.gz","r:gz") as t:
  paths=t.getnames();m=json.load(t.extractfile("release.json"))
  assert all(producer.permitted(p,project) or p=="release.json" for p in paths)
  assert len(paths)==len(set(paths)) and e["commit"]==commit
  assert all(sha(t.extractfile(p).read())==h for p,h in m["files"].items())
 producer_results.append({"project":project,"status":"PASS","commit":commit,"files":len(m["files"]),"sha256":e["package_sha256"],"purpose":"AUDIT ONLY; not approved Entrega62 package"})
if opts.frozen_archive:producer_results.append({"project":"rumo10","status":"PASS","mode":"frozen bytes; no application rebuild","sha256":hashlib.sha256(opts.frozen_archive.read_bytes()).hexdigest()})
(ROOT/"evidence/producer-tests.json").write_text(json.dumps(producer_results,indent=2))
# Syntax compile to explicit temp output, not shared repo __pycache__.
import py_compile
for f in (ROOT/"bin/produce.py",pathlib.Path(__file__)):
 py_compile.compile(str(f),cfile=str(ROOT/"evidence"/(f.stem+".pyc")),doraise=True)
report={"at":datetime.datetime.now(datetime.timezone.utc).isoformat(),"suites":results,"producer":producer_results,"production_requests":0}
(ROOT/"evidence/validation.json").write_text(json.dumps(report,indent=2))
sys.exit(1 if any(x["status"]=="FAIL" for x in results+producer_results) else 0)
