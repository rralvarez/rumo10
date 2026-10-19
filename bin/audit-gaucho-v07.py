from pathlib import Path
import json,csv
ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'Docs/mundializacao-v07/expansao-estadual/lote3/rs'
e=json.loads((OUT/'sports.json').read_text(encoding='utf-8'))
e['rows']=e['rows'] or {}
r=json.loads((ROOT/'database/competition-references/br-gaucho-2026-v1.json').read_text(encoding='utf-8'))
assert e['career_id']==864001 and e['rs_games']==64 and e['entries']==e['national_renewals']==0
with (ROOT/'Docs/mundializacao-v07/expansao-estadual/lote2/ac/federations.csv').open(encoding='utf-8-sig',newline='') as f:quotas={x['uf']:int(x['quota']) for x in csv.DictReader(f)}
rows=[]
for uf,quota in quotas.items():
 sport=e['state_sports'].get(uf,{});receipt=e['rows'].get(uf)
 rows.append({'uf':uf,'quota_reference':quota,'competition':sport.get('competition','not_implemented'),'sports_proven':sport.get('proof',False),'candidate_count':receipt['candidate_count'] if receipt else None,'candidate_state':'valid_pending_admission' if receipt else 'not_assessed_required_tournaments_pending','enrollments':0,'future_composition_complete':False,'renewals':0,'career_id':e['career_id']})
assert len(rows)==27
with (OUT/'federations.csv').open('w',encoding='utf-8',newline='') as f:w=csv.DictWriter(f,fieldnames=list(rows[0]));w.writeheader();w.writerows(rows)
a=e['admission'];missing=a.get('federation_missing_total')
(OUT/'renewal-dependencies.json').write_text(json.dumps({'career_id':e['career_id'],'sports':e['state_sports'],'required_competitions_pending':e['pending'],'state_candidates':sum(x['candidate_count'] for x in e['rows'].values()) if e['rows'] else None,'state_missing':missing,'transition_residual_missing':4,'national_enrollments':0,'future_composition_complete':False,'renewals':0,'future_capacities':{'C':24,'D':96,'CopaBrasil':128,'CopaBrasil_games':157},'remaining_obligations':['Complete state/selective admissions and four transition residuals','Future Nordeste and Copa do Brasil entries and contracts','Sul-Sudeste/regional obligations not certified club by club','Continental obligations not implemented; unknown entry is not dispensation'],'federations':rows},ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
with (OUT/'sources.csv').open('w',encoding='utf-8',newline='') as f:w=csv.DictWriter(f,fieldnames=list(r['sources'][0]));w.writeheader();w.writerows(r['sources'])
with (OUT/'participants.csv').open('w',encoding='utf-8',newline='') as f:
 w=csv.writer(f);w.writerow(['club_id','identity_origin','edition','source'])
 for club in r['definitions']['br-rs-a1']['teams']:w.writerow([club,'game_reference_only' if club in r['definitions']['br-rs-a1']['reference_clubs'] else 'existing_catalog_id',2026,r['sources'][0]['url']])
print('27 UFs; candidates/missing:',None if not e['rows'] else sum(x['candidate_count'] for x in e['rows'].values()),missing,'+4 residual; no enrollment or renewal')
