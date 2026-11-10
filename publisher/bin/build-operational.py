import pathlib,json,hashlib,tarfile,subprocess,sys,importlib.util,os
r=pathlib.Path(__file__).resolve().parents[2]
commit=os.environ['GITHUB_SHA']; api=json.loads(pathlib.Path(sys.argv[1]).read_text()); out=pathlib.Path(sys.argv[2])
files=json.loads((r/'publisher/config/runtime-files.json').read_text())
if isinstance(files,dict): files=files['files']
tracked=subprocess.check_output(['git','ls-tree','-r','--name-only',commit],cwd=r,text=True).splitlines()
allowed=set(files)
spec0=importlib.util.spec_from_file_location('rules',r/'publisher/bin/produce.py'); rules=importlib.util.module_from_spec(spec0); spec0.loader.exec_module(rules)
runtime={p for p in tracked if rules.permitted(p,'rumo10')}
if runtime!=allowed: raise SystemExit('Unregistered runtime path change: review required')
meta={'commit':commit,'repository':os.environ['GITHUB_REPOSITORY'],'workflow_id':api['workflow_id'],'run_id':int(os.environ['GITHUB_RUN_ID']),'run_number':int(os.environ['GITHUB_RUN_NUMBER']),'run_attempt':int(os.environ['GITHUB_RUN_ATTEMPT'])}
a=r/'approved-release/rumo10-consolidated-reviewed-candidate.tar.gz'
with tarfile.open(a,'r:gz') as t: manifest=json.load(t.extractfile('release.json'))
exact=all(hashlib.sha256(subprocess.check_output(['git','show',commit+':'+p],cwd=r)).hexdigest()==h for p,h in manifest['files'].items())
module='seal-approved' if exact else 'produce'
spec=importlib.util.spec_from_file_location('build',r/'publisher/bin'/ (module+'.py'));m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
if exact: m.seal(a,r/'publisher/config/approved-release62.json',meta,out)
else: m.produce(r,commit,'rumo10',{k:v for k,v in meta.items() if k!='commit'}|{'data_contracts':['rumo10-development-v3']},out)
print(json.dumps({'commit':commit,'frozen_bytes':exact,'sha256':hashlib.sha256((out/'release.tar.gz').read_bytes()).hexdigest()}))
