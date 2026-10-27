"""Wrap an already approved TAR without rebuilding or changing a single byte."""
import argparse, pathlib, json, hashlib, tarfile, io, re, os, importlib.util
ROOT = pathlib.Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('producer_rules', ROOT / 'bin/produce.py')
rules = importlib.util.module_from_spec(spec)
spec.loader.exec_module(rules)

def seal(archive, approval, metadata, out):
    if set(metadata) != {'commit', 'repository', 'workflow_id', 'run_id', 'run_number', 'run_attempt'}:
        raise ValueError('unexpected metadata fields')
    a = json.loads(pathlib.Path(approval).read_text())
    if not a['schema'] == 'approved-frozen-release-v1':
        raise ValueError('approved archive validation failed')
    if not (a['project'] == 'rumo10' and a['repository'] == 'rralvarez/rumo10'):
        raise ValueError('approved archive validation failed')
    raw = pathlib.Path(archive).read_bytes()
    if not len(raw) <= 256 * 1024 * 1024:
        raise ValueError('approved archive validation failed')
    sha = hashlib.sha256(raw).hexdigest()
    if not sha == a['package_sha256']:
        raise ValueError('approved package digest mismatch')
    if not (metadata['repository'] == a['repository'] and re.fullmatch('[a-f0-9]{40}', metadata['commit'])):
        raise ValueError('approved archive validation failed')
    if not all((type(metadata[x]) is int and metadata[x] > 0 for x in ['workflow_id', 'run_id', 'run_number', 'run_attempt'])):
        raise ValueError('approved archive validation failed')
    files = {}
    total = 0
    with tarfile.open(fileobj=io.BytesIO(raw), mode='r:gz') as t:
        for m in t:
            if not (m.isfile() and m.name not in files and (not m.name.startswith('/')) and ('..' not in m.name.split('/')) and ('\\' not in m.name)):
                raise ValueError('unsafe TAR')
            if not (m.name == 'release.json' or rules.permitted(m.name, a['project'])):
                raise ValueError('disallowed payload')
            total += m.size
            if not total <= 512 * 1024 * 1024:
                raise ValueError('approved archive validation failed')
            files[m.name] = t.extractfile(m).read()
    manifest = json.loads(files.pop('release.json'))
    if not manifest['project'] == a['project']:
        raise ValueError('approved archive validation failed')
    if not manifest['files'] == {p: hashlib.sha256(b).hexdigest() for p, b in files.items()}:
        raise ValueError('approved archive validation failed')
    if not b'development-v3-isolated' in files['app/PlayerDevelopment.php']:
        raise ValueError('approved archive validation failed')
    out = pathlib.Path(out)
    out.mkdir(mode=448, parents=True, exist_ok=False)
    with (out / 'release.tar.gz').open('xb') as f:
        f.write(raw)
        f.flush()
        os.fsync(f.fileno())
    if not hashlib.sha256((out / 'release.tar.gz').read_bytes()).hexdigest() == sha:
        raise ValueError('approved archive validation failed')
    envelope = {'schema': 'rumo-pull-v1', 'project': a['project'], 'package_sha256': sha, 'data_contracts': a['data_contracts'], **metadata}
    (out / 'candidate.json').write_text(json.dumps(envelope, indent=2), encoding='utf-8')
    (out / 'source-provenance.json').write_text(json.dumps({'kind': 'approved frozen bytes, never rebuilt', 'application_base_commit': a['application_base_commit'], 'controller_commit': metadata['commit'], 'package_sha256': sha}, indent=2), encoding='utf-8')
    return envelope
if __name__ == '__main__':
    p = argparse.ArgumentParser()
    p.add_argument('--archive', required=True)
    p.add_argument('--approval', default=str(ROOT / 'config/approved-release62.json'))
    p.add_argument('--output', required=True)
    for n in ['commit', 'repository', 'workflow-id', 'run-id', 'run-number', 'attempt']:
        p.add_argument('--' + n, required=True)
    a = p.parse_args()
    m = {'commit': a.commit, 'repository': a.repository, 'workflow_id': int(a.workflow_id), 'run_id': int(a.run_id), 'run_number': int(a.run_number), 'run_attempt': int(a.attempt)}
    try:
        r = seal(a.archive, a.approval, m, a.output)
        print(json.dumps({'sha256': r['package_sha256'], 'rebuilt': False}))
    except Exception as e:
        raise SystemExit('BLOCKED: approved archive sealing failed: ' + type(e).__name__)
