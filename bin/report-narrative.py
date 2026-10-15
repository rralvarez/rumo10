from pathlib import Path
import json,csv,collections,itertools,re,unicodedata
ROOT=Path(__file__).resolve().parents[1];OUT=ROOT/'Docs/narrativa-v1'
new=sorted([s for p in (ROOT/'database/scenes').glob('*.json') for s in json.loads(p.read_text(encoding='utf-8'))],key=lambda s:s['id'])
old=json.loads((OUT/'baseline-summary.json').read_text(encoding='utf-8'));meta=json.loads((ROOT/'database/scenes-legacy-metadata.json').read_text(encoding='utf-8'))
def csvfile(name,rows):
 with (OUT/name).open('w',encoding='utf-8-sig',newline='') as f:
  w=csv.DictWriter(f,fieldnames=list(rows[0]));w.writeheader();w.writerows(rows)
rows=[];alltexts=collections.defaultdict(list)
for s in new:
 for i,o in enumerate(s['options']):
  for k in ['text','success_text','failure_text']:alltexts[(k,o[k].casefold())].append([s['id'],i])
 rows.append({'id':s['id'],'module':s['module'],'stage':s['stage'],'family':s['family'],'positions':'|'.join(s['gating'].get('positions_allow',['zagueiro','lateral','volante','meia','ponta','centroavante'])),'gating':json.dumps(s['gating'],ensure_ascii=False),'weight':s['weight'],'rarity':s['rarity'],'cooldown':s['cooldown'],'options':len(s['options']),'source':'ficcao_editorial','review':'prosa_literal_e_contrato_validados; proximidade_semantica_revisavel','text':s['text']})
csvfile('auditoria-lote-01.csv',rows)
def tokens(t):
 t=''.join(c for c in unicodedata.normalize('NFKD',t.lower()) if not unicodedata.combining(c));stop={'voce','para','uma','com','que','seu','sua','pode','sem','mais','precisa','se','um','os','as','ao','de','da','do','no','na','e','o','a','dos','das','pelo','pela','por','enquanto','ou','como','esta','ainda','entre'}
 return set(re.findall(r'[a-z]{3,}',t))-stop
near=[]
for a,b in itertools.combinations(new,2):
 aa=tokens(a['text']);bb=tokens(b['text']);score=len(aa&bb)/max(1,len(aa|bb))
 if score>=.4:near.append({'ids':[a['id'],b['id']],'jaccard':round(score,3),'texts':[a['text'],b['text']],'status':'alerta_lexical_nao_prova_duplicidade_semantica'})
repeated=[{'field':field,'text':text,'occurrences':items} for (field,text),items in alltexts.items() if len(items)>1]
(OUT/'repeticoes-lote-01.json').write_text(json.dumps({'exact':repeated,'near_prompt_threshold':.4,'near_prompts':sorted(near,key=lambda x:-x['jaccard']),'note':'Jaccard é triagem lexical, não certificação de originalidade. Vetores mecânicos podem coincidir; não são contados como narrativas novas por isso.'},ensure_ascii=False,indent=2),encoding='utf-8')
matrix=[]
for mod in ['T01','T02','P01','J01','J02','J03','F01','F02']:
 n=sum(s['module']==mod for s in new);before=old['module_assignment_provisional'][mod]
 matrix.append({'module':mod,'legacy_assigned':before,'legacy_editorially_approved':0,'new_literal_scenes':n,'new_options':4*n,'nominal_total':before+n,'target':100,'deficit_even_if_all_legacy_approved':max(0,100-before-n),'deficit_without_legacy_approval':100-n})
csvfile('matriz-modulos.csv',matrix)
elig=OUT/'eligibilidade.json'
if elig.exists():
 data=json.loads(elig.read_text(encoding='utf-8'));csvfile('elegibilidade-real.csv',[dict(position=r['position'],stage=r['stage'],scenario=r['scenario'],eligible=r['eligible'],**{m:r['modules'].get(m,0) for m in ['T01','T02','P01','J01','J02','J03','F01','F02']}) for r in data])
(OUT/'contagens.json').write_text(json.dumps({'legacy_preserved':214,'legacy_options':856,'new':len(new),'new_options':sum(len(s['options']) for s in new),'nominal_catalog':214+len(new),'nominal_options':856+sum(len(s['options']) for s in new),'new_active':sum(s['enabled'] for s in new),'legacy_blocked_new_selection':sum(bool(s['blocked_context']) for s in meta.values()),'neutral_fallbacks_excluded_from_editorial_target':4,'total_target_achieved':False,'legacy_quality_approval_pending':214,'new_exact_repeated_text_groups':len(repeated),'near_prompt_pairs':len(near),'matrix':matrix},ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({'new':len(new),'exact_repeated_groups':len(repeated),'near_pairs':near},ensure_ascii=False,indent=2))
