from pathlib import Path
import urllib.request,hashlib,tarfile,io,subprocess,json,tempfile
root=Path(__file__).resolve().parents[1]
url='https://github.com/rhysd/actionlint/releases/download/v1.7.12/actionlint_1.7.12_linux_amd64.tar.gz'
raw=urllib.request.urlopen(url,timeout=60).read()
assert hashlib.sha256(raw).hexdigest()=='8aca8db96f1b94770f1b0d72b6dddcb1ebb8123cb3712530b08cc387b349a3d8'
with tempfile.TemporaryDirectory() as d:
 p=Path(d)/'actionlint'
 with tarfile.open(fileobj=io.BytesIO(raw),mode='r:gz') as t:p.write_bytes(t.extractfile('actionlint').read())
 p.chmod(0o700)
 files=list((root.parent/'.github/workflows').glob('*.yml'))+list((root/'workflows').glob('*.yml'))
 r=subprocess.run([str(p),'-shellcheck=','-pyflakes=',*[str(f) for f in files]],capture_output=True,text=True)
 report={'version':'1.7.12','status':'PASS' if r.returncode==0 else 'FAIL','files':[str(f.relative_to(root.parent)) for f in files],'output':r.stdout+r.stderr,'archive_sha256':hashlib.sha256(raw).hexdigest()}
 (root/'evidence/actionlint.json').write_text(json.dumps(report,indent=2));print(json.dumps(report));raise SystemExit(r.returncode)
