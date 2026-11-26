from pathlib import Path
import hashlib
import json
import os
import re
import runpy
import subprocess
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

# The production fence has an independent policy. Check its public, tracked-main
# pins before the existing publisher-safety job greenlights a release. A workflow
# change needs a separately authorized policy update, even if ordinary CI passes.
main_pins = {
    'cron-poc-artifact.yml': 'c792e11fa54bc14827518cc31fa98191a7afa1dde855b1cc0f647137a4ae25fd',
    'production-consolidated.yml': 'f810a72bba7927d21f157306b81b3f746879e19b0c1e68af1b98766dc3707a52',
    'production-recovery-v8.yml': '97178fc030a4cdd75dc0799e884eab5e6ad6d0eb6adf28e93a2246f9711728c6',
    'release62-selective-entry.yml': 'bf34121bbdf85673d000f208bd2256d2b36ae3edd95fa1cbcfea3c355bd2551b',
}
for name, expected in main_pins.items():
    path = '.github/workflows/' + name
    if (r.parent / '.git').exists():
        content = subprocess.check_output(['git', '-C', str(r.parent), 'show', 'HEAD:' + path])
    else:
        content = (r.parent / path).read_bytes().replace(b'\r\n', b'\n')
    observed = hashlib.sha256(content).hexdigest()
    assert observed == expected, f'{path}: tracked-main fence pin differs; expected {expected}, got {observed}'
assert 'ref: bb654298f52df1e18cc2cef908b545981fad1e9b' in normal
print('PASS tracked-main workflow pins and trusted producer reference')

# Run the same runtime-path registration check the trusted producer runs
# after merge. Using Git objects prevents generated or untracked files from
# silently changing this contract.
checkout = Path(os.environ.get('PUBLISHER_CONTRACT_CHECKOUT', r.parent))
if (checkout / '.git').exists():
    def git(*args):
        return subprocess.check_output(['git', '-C', str(checkout), *args])

    allowed = runpy.run_path(str(r / 'bin/produce.py'))['permitted']
    paths = git('ls-tree', '-rz', '--name-only', 'HEAD').decode().split('\0')
    expected_files = json.loads(git('show', 'HEAD:publisher/config/runtime-files.json'))
    if isinstance(expected_files, dict):
        expected_files = expected_files['files']
    runtime_files = {path for path in paths if allowed(path, 'rumo10')}
    registered = set(expected_files)
    assert len(expected_files) == len(registered), 'Duplicate registered runtime files'
    assert registered == runtime_files, (
        f'Runtime inventory disagrees with producer: '
        f'missing={sorted(runtime_files - registered)}, stale={sorted(registered - runtime_files)}'
    )
    print(f'PASS production runtime inventory checked before artifact creation ({len(runtime_files)} paths)')
