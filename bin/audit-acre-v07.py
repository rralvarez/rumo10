"""Export one AC sports cohort; never aggregate different career universes."""
from pathlib import Path
import csv
import json

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'Docs/mundializacao-v07/expansao-estadual/lote2/ac'
reference = json.loads((ROOT / 'database/competition-references/br-acreano-2026-v1.json').read_text(encoding='utf-8'))
evidence = json.loads((OUT / 'sports.json').read_text(encoding='utf-8'))
admission = evidence['admission']
assert admission['blocked'] and not admission['memberships_applied']
assert 34 <= evidence['ac_games'] <= 42 and evidence['entries'] == evidence['national_renewals'] == 0
assert len({row['career_id'] for row in evidence['rows'].values()}) == 1
assert sum(row['candidate_count'] for row in evidence['rows'].values()) + admission['federation_missing_total'] == 64
assert admission['residual_missing'] == 4
baseline = ROOT / 'Docs/mundializacao-v07/expansao-estadual/df/federations.csv'
with baseline.open(encoding='utf-8-sig', newline='') as stream:
    quotas = {row['uf']: int(row['quota']) for row in csv.DictReader(stream)}
rows = []
for uf, quota in quotas.items():
    proof = evidence['rows'].get(uf)
    rows.append({'uf': uf, 'quota': quota, 'proven_candidates': proof['candidate_count'] if proof else 0,
                 'pending': quota - (proof['candidate_count'] if proof else 0),
                 'competition': proof['competition'] if proof else 'not_certified',
                 'source': proof['source'] if proof else '',
                 'status': 'proven_pending_national_admission' if proof else 'qualifying_results_required',
                 'entries': 0, 'career_id': admission['career_id']})
assert len(rows) == 27 and sum(row['pending'] for row in rows) == admission['federation_missing_total']
with (OUT / 'federations.csv').open('w', encoding='utf-8', newline='') as stream:
    writer = csv.DictWriter(stream, fieldnames=list(rows[0]));writer.writeheader();writer.writerows(rows)
(OUT / 'federations.json').write_text(json.dumps({'career_id': admission['career_id'], 'federations': rows,
    'state_pending': admission['federation_missing_total'], 'residual_pending': 4,
    'entries': 0, 'renewals': 0}, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
with (OUT / 'sources.csv').open('w', encoding='utf-8', newline='') as stream:
    writer = csv.DictWriter(stream, fieldnames=list(reference['sources'][0]));writer.writeheader();writer.writerows(reference['sources'])
definition = reference['definitions']['ac-a1']
with (OUT / 'participants.csv').open('w', encoding='utf-8', newline='') as stream:
    writer = csv.writer(stream);writer.writerow(['club_id', 'identity_origin', 'edition', 'source'])
    for club in definition['teams']:
        writer.writerow([club, 'game_reference_only' if club in definition['reference_clubs'] else 'existing_catalog_id', 2026, definition['verified_sources'][0]])
print('27 UFs, same career:', admission['career_id'], 'state pending:', admission['federation_missing_total'], '+4 residual; zero entries/renewals.')
