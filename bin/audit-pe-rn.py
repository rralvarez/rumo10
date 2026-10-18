"""Export one measured universe; never aggregate qualifications from separate saves.

Prerequisite: tests/pe-rn-v07.php. No SQL, downloads, source installation or enrollment.
"""
from pathlib import Path
import csv, hashlib, json

ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'Docs/mundializacao-v07/expansao-estadual/pe-rn'
proof=json.loads((OUT/'sports.json').read_text(encoding='utf-8'))
reference=json.loads((ROOT/'database/competition-references/br-capixaba-2026-v1.json').read_text(encoding='utf-8'))
admission=proof['admission'];receipts=proof['rows'];rows=[]
for uf,stats in admission['federations'].items():
    receipt=receipts.get(uf,{})
    if receipt:assert receipt['career_id']==admission['career_id']
    rows.append(dict(uf=uf,quota=stats['reference_2026_quota'],proven_candidates=stats['accepted'],
        pending=stats['missing'],competition=receipt.get('competition','not_certified'),
        source=receipt.get('source',''),status=stats['state'],entries=0,career_id=admission['career_id']))
assert len(rows)==27 and sum(row['quota'] for row in rows)==64
assert sum(row['pending'] for row in rows)==admission['federation_missing_total']
assert receipts['ES']['candidate_count']<=1 and receipts['ES']['unavailable']>=1
with (OUT/'federations.csv').open('w',encoding='utf-8-sig',newline='') as f:
    writer=csv.DictWriter(f,fieldnames=list(rows[0]));writer.writeheader();writer.writerows(rows)
(OUT/'federations.json').write_text(json.dumps(dict(evidence='sports.json',same_career=True,rows=rows,
    state_pending=admission['federation_missing_total'],residual_pending=admission['residual_missing'],
    national_entries=0,national_renewals=0),ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
sources=dict(consulted='2026-10-18',pe_rn=json.loads((ROOT/'database/competition-references/br-pe-rn-2026-v1.json').read_text(encoding='utf-8'))['sources'])
(OUT/'sources.json').write_text(json.dumps(sources,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
for uf in ['PE','RN']:
    folder=OUT/uf.lower();folder.mkdir(exist_ok=True)
    (folder/'receipt.json').write_text(json.dumps(receipts[uf],ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print('27 federações; '+str(admission['federation_missing_total'])+' vagas estaduais pendentes no save de sports.json.')
