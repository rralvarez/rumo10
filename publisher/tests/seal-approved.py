from pathlib import Path
import importlib.util,tempfile,hashlib,json,os
r=Path(__file__).resolve().parents[1];spec=importlib.util.spec_from_file_location('seal',r/'bin/seal-approved.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
root=Path(tempfile.mkdtemp(prefix='sealed-proof-',dir=r/'tests'));archive=Path(os.environ.get('APPROVED_RELEASE62_ARCHIVE',r'C:\Users\rodri\AppData\Local\Temp\rumo10-release62-final-786zub0y\rumo10-entrega62-selective.tar.gz'));meta={'repository':'rralvarez/rumo10','commit':'a'*40,'workflow_id':7,'run_id':100,'run_number':2,'run_attempt':1};passed=0
out=root/'sealed';m.seal(archive,r/'config/approved-release62.json',meta,out);assert (out/'release.tar.gz').read_bytes()==archive.read_bytes();passed+=1
bad=root/'bad.tar.gz';bad.write_bytes(archive.read_bytes()+b'changed')
for src,md,dest in [(bad,meta,root/'bad-output'),(archive,{**meta,'repository':'attacker/other'},root/'bad-repo'),(archive,{**meta,'commit':'main'},root/'bad-commit'),(archive,meta,out)]:
 try:m.seal(src,r/'config/approved-release62.json',md,dest)
 except Exception:passed+=1
 else:raise AssertionError('missing refusal')
(r/'evidence/seal-approved.json').write_text(json.dumps({'passed':passed,'exact_frozen_sha256':hashlib.sha256((out/'release.tar.gz').read_bytes()).hexdigest(),'operational_artifact_uploaded':False,'ci_metadata':'synthetic fixture, not a GitHub run'},indent=2));print(json.dumps({'passed':passed}))
