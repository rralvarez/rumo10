"""Reproduce Entrega58 research checkpoint; never certifies sports implementation.

Raw public documents are optional and ignored by Git. When supplied, their hashes
must match the research manifest. No network, database, environment or save writes.
"""
from pathlib import Path
import argparse
import csv
import hashlib
import json
import subprocess

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'Docs/mundializacao-v07/expansao-estadual/lote2'


def audit(raw_dir=None):
    manifest = json.loads((OUT / 'research.json').read_text(encoding='utf-8'))
    if manifest['schema'] != 'state-lot2-research-v1':
        raise ValueError('Unknown research schema')
    if manifest['public_activation'] or manifest['new_federations_integrated']:
        raise ValueError('This research checkpoint does not certify new competitions')
    rows = manifest['sources']
    if len(rows) != len({r['uf'] for r in rows}) or set(manifest['selection']) != {r['uf'] for r in rows}:
        raise ValueError('Duplicate or inconsistent UF selection')
    checks = []
    for row in rows:
        if row['edition'] != 2026 or row['implemented'] or not row['gaps']:
            raise ValueError('Research status conflicts with executable status')
        if row['new_candidate_receipts'] != 0 or len(row['sha256']) != 64:
            raise ValueError('Unproved candidate or missing document hash')
        raw = Path(raw_dir) / row['local_file'] if raw_dir else None
        if raw_dir:
            if not raw.is_file() or hashlib.sha256(raw.read_bytes()).hexdigest() != row['sha256']:
                raise ValueError('Public source missing or changed: ' + row['uf'])
        checks.append({'uf': row['uf'], 'source_hash_checked': bool(raw_dir),
                       'status': 'researched_not_integrated'})

    # Independent baseline per career. Never sum separate cohort simulations.
    baseline = manifest['baseline_cohort']
    sports = json.loads((ROOT / baseline['path']).read_text(encoding='utf-8'))
    admission = sports['admission']
    candidates = sum(r['candidate_count'] for r in sports['rows'].values())
    if admission['career_id'] != baseline['career_id'] or candidates != baseline['state_candidates']:
        raise ValueError('Baseline cohort changed; review counts explicitly')
    if admission['federation_missing_total'] != 64 - candidates or admission['residual_missing'] != 4:
        raise ValueError('State/residual totals mixed')
    if admission['memberships_applied'] or sports['entries'] or sports['national_renewals']:
        raise ValueError('Unexpected baseline enrollment/renewal')

    protected = {}
    for name in ['ConfiguredStateCompetition.php', 'StateResultProof.php',
                 'StateQualification.php', 'FutureSerieDAdmission.php',
                 'WorldSeason.php', 'CompetitionCalendar.php', 'Game.php',
                 'Progression.php', 'Evolution.php', 'MatchVenue.php']:
        relative = 'app/' + name
        old = subprocess.check_output(['git', 'show', manifest['base'] + ':' + relative], cwd=ROOT)
        now = (ROOT / relative).read_bytes()
        if now.replace(b'\r\n', b'\n') != old.replace(b'\r\n', b'\n'):
            raise ValueError('Approved engine changed; checkpoint scope must be reviewed: ' + relative)
        protected[relative] = hashlib.sha256(now.replace(b'\r\n', b'\n')).hexdigest()

    output = {'kind': 'research_checkpoint_not_sports_acceptance', 'sources': checks,
              'protected_engine_hashes': protected, 'baseline_career': admission['career_id'],
              'baseline_state_candidates': candidates, 'baseline_state_pending': 64 - candidates,
              'baseline_residual_pending': 4, 'new_federations_integrated': 0,
              'new_candidate_receipts': 0, 'entries_applied': 0, 'renewals_applied': 0}
    (OUT / 'audit.json').write_text(json.dumps(output, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    with (OUT / 'research.csv').open('w', encoding='utf-8', newline='') as stream:
        writer = csv.DictWriter(stream, fieldnames=['uf', 'edition', 'competition', 'source',
                                                   'sha256', 'locator', 'status', 'next_component'])
        writer.writeheader()
        writer.writerows({k: row[k] for k in writer.fieldnames} for row in rows)
    selected = {r['uf']: r for r in rows}
    previous = ROOT / 'Docs/mundializacao-v07/expansao-estadual/df/federations.csv'
    with previous.open(encoding='utf-8-sig', newline='') as stream:
        matrix = list(csv.DictReader(stream))
    for row in matrix:
        row['evidence_scope'] = 'unchanged_Entrega57_baseline_not_new_Entrega58_cohort'
        row['lot2_research'] = selected.get(row['uf'], {}).get('status', 'not_selected')
    with (OUT / 'federations.csv').open('w', encoding='utf-8', newline='') as stream:
        writer = csv.DictWriter(stream, fieldnames=list(matrix[0]))
        writer.writeheader()
        writer.writerows(matrix)
    print('Research checkpoint verified: 4 UFs researched, 0 newly integrated; baseline 14/50 +4 residual.')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--raw-dir', type=Path, help='Directory containing the four ignored public PDFs')
    audit(parser.parse_args().raw_dir)
