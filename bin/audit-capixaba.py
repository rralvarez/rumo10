"""Export one measured universe; never aggregate qualifications from separate saves.

Prerequisite: tests/capixaba-v07.php. No SQL, downloads, source installation or enrollment.
"""
from pathlib import Path
import csv, hashlib, json

ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'Docs/mundializacao-v07/expansao-estadual/es'
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
sources=dict(consulted='2026-10-18',league=reference['source_digests'],
    source_update_check=reference['source_update_check'],
    distinct_clubs=reference['identity_distinction_sources'],
    second_path=dict(source='https://futebolcapixaba.com/site/wp-content/uploads/2026/04/Regulamento-COPA-ES-2026.pdf',
        location='art.11 paragraphs1-3, PDF p.6; amendment02/06/2026',
        notice='https://futebolcapixaba.com/2026/01/clubes-e-fes-realizam-a-primeira-reuniao-do-conselho-arbitral-da-copa-es-2026-que-comeca-em-abril/',
        state='not_implemented_edition_conflict',
        conflict='Official cup PDF says D2026; federation notice says D2027. No silent correction or qualification.'),
    national_slots_from_league=1,federation_reference_quota=2,public_enabled=False)
private=ROOT/'storage/qa/state57/es-copa-rec.pdf'
if private.exists():sources['second_path']['sha256']=hashlib.sha256(private.read_bytes()).hexdigest()
identity=ROOT/'storage/qa/state57/es-rio-branco-vn.html'
if identity.exists():sources['rio_branco_vn']=dict(source='https://futebolcapixaba.com/time/rio-branco-f-c/',
    sha256=hashlib.sha256(identity.read_bytes()).hexdigest(),distinct_from='Rio Branco-ES')
(OUT/'sources.json').write_text(json.dumps(sources,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print('27 federações; '+str(admission['federation_missing_total'])+' vagas estaduais pendentes no save de sports.json.')
