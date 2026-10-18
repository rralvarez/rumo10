"""Merge the certified batch into the prior 27-federation inventory; never edit it."""
import json,csv
from pathlib import Path
root=Path(__file__).resolve().parents[1]
base=json.loads((root/'Docs/mundializacao-v07/classificatorias-estaduais/federations.json').read_text(encoding='utf-8'))
ref=json.loads((root/'database/competition-references/br-state-batch-2026-v1.json').read_text(encoding='utf-8'))
proof=json.loads((root/'Docs/mundializacao-v07/motor-estadual/sports.json').read_text(encoding='utf-8'))
rows=base['rows']
for r in rows:
 uf=r['uf']; p=ref['paths'].get(uf); receipt=proof['rows'].get(uf)
 if p:
  c=ref['definitions'][p['competition']]
  r.update(qualifying_competition=c['id'],rule_source=p['source'],location=p['location'],path_status='implemented_local_candidate_only',usable_final_classification='verified_prefix_'+str(p['ranking_prefix']),specific_engine='configured-state-v1',lower_or_selective='lower_state_renewal_not_implemented; selective_not_required_for_this_path',roll_down=p['roll_down'])
  r['competitions'].append(dict(id=c['id'],name=c['name'],edition=2026,format=c['format'],participants=len(c['teams']),participant_ids=c['teams'],sources=c['verified_sources'],validation='sports_configuration_with_declared_limits',can_generate_legacy_results=False))
 r['proven_candidates_in_fixture']=receipt['candidate_count'] if receipt else 0
 r['remaining_in_fixture']=r['quota']-r['proven_candidates_in_fixture']
 r['national_entries_granted']=0
out=root/'Docs/mundializacao-v07/motor-estadual'
result=dict(reference_quota_sources=base['source_quota'],knowledge='official2026reference; save results are simulated; no entries granted',rows=rows,state_candidates=sum(r['proven_candidates_in_fixture'] for r in rows),state_slots_pending=sum(r['remaining_in_fixture'] for r in rows),national_renewals=0)
(out/'federations.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
keys=['uf','quota','qualifying_competition','path_status','rule_source','location','specific_engine','roll_down','proven_candidates_in_fixture','remaining_in_fixture','national_entries_granted']
with (out/'federations.csv').open('w',encoding='utf-8-sig',newline='') as f:
 w=csv.DictWriter(f,fieldnames=keys,extrasaction='ignore');w.writeheader();w.writerows(rows)
assert len(rows)==27 and sum(r['quota'] for r in rows)==64
print(str(result['state_candidates'])+' candidatos comprovados; '+str(result['state_slots_pending'])+' vagas estaduais pendentes; zero inscricoes.')
