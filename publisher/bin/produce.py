"""Commit-tree producer: never packages working-tree bytes or private storage."""
import argparse,hashlib,io,json,pathlib,re,subprocess,tarfile
def sha(b):return hashlib.sha256(b).hexdigest()
def permitted(p,project):
 parts=p.lower().split("/")
 if any(x in parts for x in (".env","clients.php","storage","media","sessions","saves","uploads",".git",".ssh","secrets","credentials")):return False
 if any(x.startswith(".env.") for x in parts):return False
 if re.search(r"\.(pem|key|token|dump|bak|example)$",p,re.I):return False
 if not re.match(r"^(app|bin|database|public|deploy)/",p):return False
 if p.startswith(("database/production/","public/assets/clubs/")) or p=="public/web.config":return False
 if project=="rumo10" and (p.startswith("database/rosters/") or p=="database/player-catalog.json"):return False
 return True
def produce(repo,commit,project,metadata,out):
 if not re.fullmatch("[a-f0-9]{40}",commit):raise ValueError("full commit SHA required")
 actual=subprocess.check_output(["git","-C",str(repo),"rev-parse",commit+"^{commit}"],text=True).strip()
 if actual!=commit:raise ValueError("commit mismatch")
 out=pathlib.Path(out);out.mkdir(mode=0o700,parents=True,exist_ok=False);tree=out/"source-tree.tar"
 with tree.open("xb") as f:subprocess.run(["git","-C",str(repo),"archive","--format=tar",commit],stdout=f,check=True)
 files={}
 with tarfile.open(tree,"r:") as source:
  for m in source:
   if m.isdir() or not permitted(m.name,project):continue
   if not m.isfile() or ".." in m.name.split("/") or "\\" in m.name:raise ValueError("unsafe tracked member")
   data=source.extractfile(m).read()
   if re.search(rb"-----BEGIN (?:OPENSSH |RSA |EC |DSA )?PRIVATE KEY-----|github_pat_[A-Za-z0-9_]{40,}|ghp_[A-Za-z0-9]{36}",data):raise ValueError("potential tracked secret: "+m.name)
   files[m.name]=data
 tree.unlink()
 if any(x not in files for x in ("app/bootstrap.php","public/index.php","public/.htaccess")):raise ValueError("incomplete runtime")
 if project=="rumo10" and b"development-v3-isolated" not in files.get("app/PlayerDevelopment.php",b""):raise ValueError("missing v3 writer")
 manifest={"project":project,"files":{p:sha(b) for p,b in sorted(files.items())}}
 with tarfile.open(out/"release.tar.gz","w:gz",format=tarfile.USTAR_FORMAT) as package:
  for name,data in sorted(files.items())+[("release.json",json.dumps(manifest,separators=(",",":")).encode())]:
   i=tarfile.TarInfo(name);i.size=len(data);i.mode=0o640;i.mtime=0;package.addfile(i,io.BytesIO(data))
 envelope={"schema":"rumo-pull-v1","project":project,"commit":commit,"package_sha256":sha((out/"release.tar.gz").read_bytes()),**metadata}
 (out/"candidate.json").write_text(json.dumps(envelope,indent=2)+"\n",encoding="utf-8");return envelope
if __name__=="__main__":
 p=argparse.ArgumentParser()
 for n in ("repo","commit","project","repository","workflow-id","run-id","run-number","attempt","output"):p.add_argument("--"+n,required=True)
 p.add_argument("--data-contract",action="append",default=[]);a=p.parse_args()
 e=produce(a.repo,a.commit,a.project,{"repository":a.repository,"workflow_id":int(a.workflow_id),"run_id":int(a.run_id),"run_number":int(a.run_number),"run_attempt":int(a.attempt),"data_contracts":a.data_contract},a.output)
 print(json.dumps({"commit":e["commit"],"sha256":e["package_sha256"]}))
