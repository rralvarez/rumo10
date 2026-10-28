"""Audita respostas resolvidas, não supõe que presets representem texto original."""
from pathlib import Path
import json,csv,collections,re,hashlib,itertools
ROOT=Path(__file__).resolve().parents[1];OUT=ROOT/'Docs/narrativa-v1';OUT.mkdir(exist_ok=True)
src=ROOT/'storage/private/narrative-v1/baseline-resolved.json'
data=json.loads(src.read_text(encoding='utf-8-sig'))
technical={2,1000,1002,1006,1009,1010,1015,1018,1024,1026,1028,1031,1034,1037,1038,1039,1042,1044,1048,1049}
post={1152,1154,1155,1159,1160,1163,1164,1169,1170,1172,1174,1176,1179,1180,1182,1183,1185,1188,1189,1192,1197,1199}
unsupported={1:'weather',8:'opponent_foul',10:'sponsor_contract',11:'rivalry_verified',1003:'weather',1004:'muscle_stiffness',1016:'travel_fatigue',1020:'weather',1053:'opponent_style',1054:'teammate_return',1055:'weather',1058:'opponent_lineup',1060:'family_attendance',1064:'ball_change',1067:'pitch_width',1071:'transport_delay',1072:'new_kit',1074:'opponent_style',1077:'late_goals_history',1078:'weather',1084:'opponent_style',1086:'ceremony',1088:'new_teammate',1090:'kickoff_delay',1091:'opponent_style',1092:'penalty_taker',1093:'fixture_congestion',1094:'opponent_provocation',1141:'crowd_booing',1143:'actual_injury',1144:'teammate_error',1145:'referee_warning',1148:'opponent_provocation',1149:'substitution_event',1153:'agent_relationship',1167:'transfer_enquiry',1186:'agent_relationship'}
def module(s):
 i=s['id'];st=s['stage']
 return ('T01' if i in technical else 'T02') if st=='treino' else 'P01' if st=='pre_jogo' else ('J01' if 1100<=i<=1124 else 'J02' if 1125<=i<=1139 else 'J03') if st=='partida' else 'F01' if i in post else 'F02'
families=[('recepcao',['domínio','dominar','recebe de costas']),('passes',['passe','passes','tabela','circulação']),('finalizacao',['finaliza','chutes','chutar','goleiro','rebote']),('jogo_aereo',['salto','aérea','cruzamento','bola longa']),('cobertura',['cobertura','cobrir','distância','compacta','reorganiza']),('duelo',['disputa','duelo','marcador','marcação']),('carga_fisica',['força','academia','aceleração','física','intensidade']),('recuperacao',['recuperação','descanso','prevenção','sono','corpo']),('imprensa',['imprensa','entrevista','jornalista','repórter','rumor','notícia']),('relacoes',['companheiro','elenco','parceiro','grupo']),('familia',['família','familiar','amigo']),('comunidade',['comunidade','bairro','criança','professor','doação']),('concentracao',['nervosismo','foco','confiança','atenção'])]
def family(s):
 text=s['text'].lower()
 return module(s)+'.'+next((name for name,words in families if any(w in text for w in words)),'rotina')
occ=collections.defaultdict(list);rows=[];annotations={}
for item in data:
 raw=item['raw'];s=item['resolved'];i=s['id'];warnings=[]
 if i in unsupported:warnings.append('contexto_sem_fonte:'+unsupported[i])
 if i==1051:warnings.append('exige_forma_recente_ruim')
 if i==1075:warnings.append('exige_atuacao_anterior_ruim')
 if i==1140:warnings.append('irritacao_companheiro_sem_fonte')
 if i==7:warnings.append('gating_minuto80_empate_presente')
 duplicate_fx=collections.Counter();missing=[]
 for n,o in enumerate(s['options']):
  for field in ['text','success_text','failure_text']:
   if not o.get(field):missing.append(str(n)+':'+field)
   else:occ[(field,o[field].strip().casefold())].append([i,n])
  duplicate_fx[json.dumps(o['effects'],sort_keys=True)]+=1
  for kind in ['effects','failure_effects','severe_failure_effects']:
   if any(k not in ['velocidade','resistencia','forca','visao_jogo','passe','finalizacao','drible','desarme','posicionamento','energia','ritmo_jogo','confianca','entrosamento','confianca_treinador','confianca_diretoria','apoio_torcida','confianca_imprensa','lideranca','foco','moral_pessoal','risco_lesao','risco_cartao','risco_polemica','risco_encontrar_rivais'] for k in o.get(kind,{})):warnings.append('efeito_desconhecido')
 if missing:warnings.append('desfecho_ausente:'+','.join(missing))
 warnings.append('intensidades_sem_texto_proprio' if any(not o.get('exceptional_text') or not o.get('severe_failure_text') for o in s['options']) else 'intensidades_com_texto')
 positions=['zagueiro','lateral','volante','meia','ponta','centroavante']
 if 1100<=i<=1124:positions=[p for p in positions if p not in ['zagueiro','volante']]
 if 1125<=i<=1139:positions=[p for p in positions if p not in ['meia','ponta','centroavante']]
 gating=['etapa='+raw['stage']]
 if raw['stage']=='pre_jogo':gating+=['reserva' if i in [6,1200,1201] else 'titular']
 if raw['stage']=='partida':gating+=['em_campo','em_andamento']
 if i==7:gating+=['minuto=80','empate']
 if i==8:gating+=['minuto=35 (não comprova falta)']
 if i==9:gating+=['log de gol adversário no minuto']
 if i==1143:gating+=['risco_lesao>=35 (não comprova lesão)']
 if i==1149:gating+=['minuto>=60 (não comprova substituição)']
 inactive=i in [10,11]
 row={'id':i,'stage':raw['stage'],'module_proposto':module(raw),'family_proposta':family(raw),'positions_current':'|'.join(positions),'disabled_before':inactive,'actual_gating':'|'.join(gating),'text':s['text'],'options':4,'success_texts':sum(bool(o.get('success_text')) for o in s['options']),'failure_texts':sum(bool(o.get('failure_text')) for o in s['options']),'same_effect_vectors_in_scene':sum(v-1 for v in duplicate_fx.values() if v>1),'findings':'|'.join(warnings),'review':'pendente_revisao_editorial_contextual'}
 rows.append(row)
 annotations[str(i)]={'module':module(raw),'family':family(raw),'blocked_context':unsupported.get(i),'review':'legacy_requires_review'}
repeat=[{'field':f,'text':t,'occurrences':v,'count':len(v)} for (f,t),v in occ.items() if len(v)>1]
with (OUT/'auditoria-214.csv').open('w',encoding='utf-8-sig',newline='') as f:
 w=csv.DictWriter(f,fieldnames=rows[0]);w.writeheader();w.writerows(rows)
summary={'scenes':len(rows),'options':sum(r['options'] for r in rows),'by_stage':dict(collections.Counter(r['stage'] for r in rows)),'module_assignment_provisional':dict(collections.Counter(r['module_proposto'] for r in rows)),'disabled_before':[10,11],'potential_before_context_filters':len(rows)-2,'unsupported_context_ids':unsupported,'missing_success_or_failure':[r['id'] for r in rows if r['success_texts']!=4 or r['failure_texts']!=4],'repeated_text_groups':len(repeat),'repeated_success_groups':sum(x['field']=='success_text' for x in repeat),'repeated_failure_groups':sum(x['field']=='failure_text' for x in repeat),'source_sha256':hashlib.sha256(src.read_bytes()).hexdigest(),'approved_toward_800':0,'note':'Atribuição de módulo não equivale a aprovação editorial. Intensidades compartilham texto por padrão; diferenças mecânicas são exportadas no snapshot.'}
(OUT/'baseline-summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),encoding='utf-8')
(OUT/'repeticoes-baseline.json').write_text(json.dumps(repeat,ensure_ascii=False,indent=2),encoding='utf-8')
(OUT/'baseline-resolved.json').write_text(json.dumps(data,ensure_ascii=False,indent=2),encoding='utf-8')
(ROOT/'database/scenes-legacy-metadata.json').write_text(json.dumps(annotations,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({k:v for k,v in summary.items() if k!='unsupported_context_ids'},ensure_ascii=False,indent=2))
