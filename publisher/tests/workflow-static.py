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
