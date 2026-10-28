"""Compila prosa literal revisável. Não gera/permutaciona frases nem desfechos."""
from pathlib import Path
import json,collections,re
ROOT=Path(__file__).resolve().parents[1]
stages={'T01':'treino','T02':'treino','P01':'pre_jogo','J01':'partida','J02':'partida','J03':'partida','F01':'fim_semana','F02':'fim_semana'}
presets={'passe':('passe',{'passe':3,'energia':-3}),'chute':('chute',{'finalizacao':3,'energia':-5}),'drible':('drible',{'drible':3,'energia':-5,'risco_lesao':2}),'desarme':('desarme',{'desarme':3,'energia':-4}),'tecnica':('social',{'drible':2,'foco':3,'energia':-3}),'tatico':('social',{'visao_jogo':2,'posicionamento':2,'foco':3}),'posicionamento':('social',{'posicionamento':3,'ritmo_jogo':2}),'visao':('social',{'visao_jogo':3,'foco':3}),'forca':('social',{'forca':3,'energia':-6}),'resistencia':('social',{'resistencia':3,'energia':-6}),'velocidade':('social',{'velocidade':3,'energia':-6,'risco_lesao':2}),'recuperar':('social',{'energia':8,'risco_lesao':-4}),'apoio':('social',{'entrosamento':6,'moral_pessoal':3}),'liderar':('social',{'lideranca':5,'entrosamento':3}),'disciplina':('social',{'foco':3}),'imprensa':('social',{'confianca_imprensa':5,'apoio_torcida':3}),'lazer':('social',{'moral_pessoal':5,'energia':5}),'social':('social',{'foco':3,'entrosamento':3})}
failure={'recuperar':{},'lazer':{'moral_pessoal':-1},'apoio':{'entrosamento':-2},'liderar':{'entrosamento':-2},'imprensa':{'confianca_imprensa':-2,'risco_polemica':2},'disciplina':{'foco':-2},'tatico':{'foco':-2},'visao':{'foco':-2},'social':{'entrosamento':-2}}
tags={'T01':['fundamento'],'T02':['coletivo'],'P01':['concentracao'],'J01':['ataque'],'J02':['defesa'],'J03':['concentracao'],'F01':['imprensa'],'F02':['vida_pessoal']}
rows=[];scene=None
for line in '\n'.join(p.read_text(encoding='utf-8') for p in sorted((ROOT/'database/editorial').glob('lote-*.txt'))).splitlines():
 if not line or line.startswith('#'):continue
 if line.startswith('@'):
  if scene:assert len(scene['options'])==4
  identity,text,gates=line[1:].split('|');number,family=identity.split(' ',1);sid=int(number);module=family.split('.')[0]
  gating=json.loads(gates);scene={'id':sid,'stage':stages[module],'module':module,'family':family,'tags':tags[module],'gating':gating,'weight':10,'rarity':'common','cooldown':2,'text':text,'options':[],'source_type':'ficcao_editorial','schema_version':1,'enabled':not any(k in gating for k in ['future_competition','weather','rivalry_verified','table_context','competition_type','knockout_round','leg','active_benefit','pressure','squad_morale','set_piece','match_period','aggregate_state','player_balance','verified_event'])};rows.append(scene)
 else:
  code,intent,text,success,fail=line.split('|');action,fx=presets[code];fx=dict(fx);stage=scene['stage']
  event=stage=='partida' and action!='social'
  if stage=='partida':fx={} if event else {'visao_jogo':1} if code=='visao' else {'posicionamento':1,'ritmo_jogo':1} if code=='tatico' else {'posicionamento':1} if code=='posicionamento' else {'lideranca':2} if code=='liderar' else {'entrosamento':2} if code in ['apoio','social'] else {'foco':2}
  if stage=='pre_jogo':
   fx={k:(min(1,v) if k in ['passe','finalizacao','drible','desarme','visao_jogo','posicionamento','forca','resistencia','velocidade'] else int(v/2) if k=='energia' and v<0 else v) for k,v in fx.items()}
  # Custos físicos só em atividades físicas descritas. Falas não treinam técnica.
  if scene['module'] in ['F01','F02']:
   assert action=='social'
   fx={'disciplina':{'foco':3},'social':{'moral_pessoal':3},'apoio':{'entrosamento':3},'liderar':{'lideranca':3},'imprensa':{'confianca_imprensa':3},'lazer':{'moral_pessoal':3},'recuperar':{'energia':8,'risco_lesao':-4},'tatico':{'foco':3},'visao':{'foco':3}}[code]
  fe=failure.get(code,{'confianca':-2});se={k:v*2 if v<0 else v for k,v in fe.items()}
  if scene['module']=='F01' and code=='social':fx={'foco':3};fe={'foco':-1};se={'foco':-2}
  if scene['module']=='F02' and code in ['social','apoio']:fx={'moral_pessoal':3};fe={'moral_pessoal':-1};se={'moral_pessoal':-2}
  scene['options'].append({'code':code,'action':action,'text':text,'effects':fx,'failure_effects':fe,'severe_failure_effects':se,'success_text':success,'failure_text':fail,'semantic_intent':intent,'match_event':event})
assert scene and len(scene['options'])==4
assert len({s['id'] for s in rows})==len(rows),'ID duplicado no manuscrito'
assert all(re.fullmatch(r'[A-Z][0-9]{2}\.[a-z0-9_]+',s['family']) for s in rows),'Família inválida no manuscrito'
overrides={}
for p in sorted((ROOT/'database/editorial').glob('lote-*-effects.json')):overrides.update(json.loads(p.read_text(encoding='utf-8')))
for s in rows:
 for i,o in enumerate(s['options']):
  o.update(overrides.get(str(s['id']),{}).get(str(i),{}))
dest=ROOT/'database/scenes';dest.mkdir(exist_ok=True)
for mod in stages:(dest/(mod.lower()+'.json')).write_text(json.dumps([s for s in rows if s['module']==mod],ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({'new_scenes':len(rows),'new_options':sum(len(s['options']) for s in rows),'modules':dict(collections.Counter(s['module'] for s in rows))}))
