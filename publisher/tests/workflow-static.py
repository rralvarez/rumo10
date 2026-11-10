from pathlib import Path
import re
r=Path(__file__).resolve().parents[1]
s=(r/'workflows/artifact.yml').read_text()
assert not re.search(r"\$\{(?!\{)",s)
assert s.count('${{')==s.count('}}')==11
assert 'needs: [syntax, regression, publisher-safety]' in s
assert 'overwrite: false' in s
assert 'PULL_PUBLISHER_CI_HOMOLOGATED' in s
assert 'deploy-ftps.py' not in s and 'deploy-cpanel.sh' not in s
print('PASS 6 structural workflow checks; not a GitHub runner execution')

normal=(r.parent/'.github/workflows/production-consolidated.yml').read_text()
recovery=(r.parent/'.github/workflows/production-recovery-v8.yml').read_text()
assert re.search(r'pull_request:\s+branches: \[main\]\s+push:\s+branches: \[main\]',normal)
assert 'workflow_dispatch:' not in normal
assert "github.event_name == 'push' && github.ref == 'refs/heads/main'" in normal
assert 'needs: [rumo10-ci-gate]' in normal
assert 'python trusted/publisher/bin/main-release.py source' in normal
assert 'workflow_dispatch:' in recovery and 'recovery-v8-rumo10-' in recovery
assert 'main-release.py source /tmp/run.json /tmp/recovery --recovery' in recovery
print('PASS main PR/push separation and distinct manual recovery workflow')
