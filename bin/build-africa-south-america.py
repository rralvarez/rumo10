"""África e países sul-americanos restantes. Referências públicas, não ligas inventadas como oficiais."""
import pathlib,json,runpy,re,concurrent.futures,sys
root=pathlib.Path(__file__).resolve().parents[1];folder=root/'storage/africa-sources';folder.mkdir(exist_ok=True)
env=runpy.run_path(str(root/'bin/build-concacaf.py'));get=env['get'];write=env['write'];download=env['download'];badges=env['badges']
rows=json.loads((root/'storage/concacaf-sources/opta-all.json').read_bytes());rows=[r for r in rows if r.get('contestantName')]
settings={
 'CL':('Chile','chi.1','Liga de Primera',16,71,'https://www.anfp.cl/'),
 'PE':('Peru','per.1','Liga 1',18,63,'https://liga1.pe/'),
 'VE':('Venezuela','ven.1','Liga FUTVE',14,59,'https://ligafutve.org/'),
 'GY':('Guyana',None,'Elite League',4,35,'https://www.guyanafootball.org/'),
 'ZA':('South Africa','rsa.1','Premiership',16,66,'https://www.psl.co.za/'),
 'NG':('Nigeria','nga.1','NPFL',20,49,'https://npfl.com.ng/'),
 'GH':('Ghana','gha.1','Premier League',18,46,'https://www.ghanafa.org/'),
 'KE':('Kenya','ken.1','Premier League',18,43,'https://footballkenya.org/'),
 'MA':('Morocco',None,'Botola',4,68,'https://frmf.ma/'),
 'EG':('Egypt',None,'Premier League',4,70,'https://www.efa.com.eg/'),
 'DZ':('Algeria',None,'Ligue 1',4,62,'https://www.faf.dz/'),
 'TN':('Tunisia',None,'Ligue 1',4,65,'https://www.ftf.org.tn/'),
 'TZ':('Tanzania',None,'Premier League',4,52,'https://www.tff.or.tz/'),
 'CD':('Congo DR',None,'Linafoot',4,56,'https://www.fecofa.cd/'),
 'AO':('Angola',None,'Girabola',4,53,'https://www.cafonline.com/'),
 'CI':("Côte d'Ivoire",None,'Ligue 1',4,49,'https://www.fif.ci/'),
 'ZM':('Zambia',None,'Super League',4,47,'https://www.faz.co.zm/'),
 'UG':('Uganda',None,'Premier League',4,43,'https://www.fufa.co.ug/'),
 'RW':('Rwanda',None,'Premier League',4,42,'https://www.ferwafa.rw/'),
 'CM':('Cameroon',None,'Elite One',4,48,'https://fecafoot-officiel.com/'),
 'SN':('Senegal',None,'Ligue 1',4,45,'https://www.cafonline.com/'),
 'ML':('Mali',None,'Première Division',4,45,'https://www.cafonline.com/'),
 'ET':('Ethiopia',None,'Premier League',4,41,'https://www.cafonline.com/'),
 'LY':('Libya',None,'Premier League',4,47,'https://www.cafonline.com/'),
}
clubs=[];comps=[];coverage=[]
for cc,(nation,code,title,size,base,official) in settings.items():
 cid=cc.lower()+'-1';source='https://theanalyst.com/articles/who-are-the-best-football-team-in-the-world-opta-power-rankings'
 if code:
  source=f'https://site.api.espn.com/apis/site/v2/sports/soccer/{code}/teams?limit=100&season=2026';p=folder/(code+'.json')
  if not p.exists():p.write_bytes(get(source))
  teams=[t['team'] for t in json.loads(p.read_bytes())['sports'][0]['leagues'][0]['teams']];assert len(teams)==size
 else:
  candidates=[r for r in rows if r.get('country')==nation];assert candidates,nation
  league=candidates[0]['league'];selected=[r for r in candidates if r['league']==league][:4];assert len(selected)==4,(nation,len(selected))
  teams=[{'displayName':r['contestantName'],'logos':[{'href':f"https://omo.akamai.opta.net/image.php?secure=true&h=omo.akamai.opta.net&sport=football&entity=team&description=badges&dimensions=150&id={r['contestantId']}"}]} for r in selected]
  title='Circuito '+title+' (adaptado)'
 names=[]
 for t in teams:
  name=t['displayName']+'-'+cc;names.append(name)
  if not t.get('logos'):
   aliases={'Bendel Insurance FC':'Bendel Insurance','Rivers United FC':'Rivers United','Kun Khalifat FC':'Kun Khalifat','Vision FC':'Vision','Heart of Lions':'Heart of Lions','Basake Holy Stars':'Basake Holy Stars','Bibiani Gold Stars':'Gold Stars','Hohoe United':'Hohoe United','Swedru All Blacks':'All Blacks','Murang\'a Seal FC':'Murang\'a SEAL','Bandari Mtwara':'Bandari','Polokwane City FC':'Polokwane City'}
   aliases.update({'Bibiani Gold Stars':'Bibiani Gold Stars','AFC Leopards':'Leopards','Kakamega Homeboyz':'Homeboyz','Kenya Police':'Police','Tusker FC':'Tusker'})
   lookup=aliases.get(t['displayName'],t['displayName']);candidate=[r for r in rows if r.get('country')==nation and lookup in [r['contestantName'],r.get('contestantShortName'),r.get('contestantClubName')]]
   assert len(candidate)==1,(name,lookup)
   r=candidate[0];t['logos']=[{'href':f"https://omo.akamai.opta.net/image.php?secure=true&h=omo.akamai.opta.net&sport=football&entity=team&description=badges&dimensions=150&id={r['contestantId']}"}]
  candidates=[r for r in rows if r.get('country')==nation and r['contestantName']==t['displayName']]
  club={'name':name,'country':cc,'uf':cc,'competitions':[cid],'edition':'2026' if cc in ['CL','PE','VE','GY'] else '2026/27','source':source,'federation_source':official,'badge_source':source,'badge_url':t['logos'][0]['href'],'reserve':False,'membership_status':'publisher_current_season' if code else 'selected_reference_clubs'}
  if len(candidates)==1:club.update(reference_overall=round(max(20,min(95,candidates[0]['currentRating'])),1),overall_source='https://theanalyst.com/articles/who-are-the-best-football-team-in-the-world-opta-power-rankings',overall_method='opta_public_snapshot_clamped_20_95')
  clubs.append(club)
 note='Circuito de clubes de referência; não representa a liga completa. Sem vagas continentais, acesso ou rebaixamento.' if not code else 'Participantes segundo publicador. Calendário de protótipo em ida/volta; fases especiais, vagas e acesso/rebaixamento não modelados.'
 # Peru e Venezuela têm fases/títulos separados: nome deixa a adaptação explícita.
 if cc in ['PE','VE']:title+=' (formato adaptado)'
 comps.append({'id':cid,'name':title,'edition':2026,'season_label':clubs[-1]['edition'],'tier':5,'family':'international','country':cc,'format':'world','teams':names,'groups':[names],'source':source,'checked':'2026-10-13','baseline':base,'rules':{'system':'league' if code else 'adapted','relegations':0,'note':note}})
 coverage.append({'country':cc,'nation':nation,'clubs':len(teams),'coverage':'publisher_participants' if code else 'reference_circuit','source':source})
overrides=json.loads((root/'database/africa-badge-overrides.json').read_bytes()) if (root/'database/africa-badge-overrides.json').exists() else {}
def checked_badge(c):
 override=overrides.get(c['name'],{})
 if override.get('pending'):
  c['badge_status']='pending_verified_asset';c['colors_status']='pending';c['colors']=['#808080','#FFFFFF'];badges.pop(c['name'],None);return
 if override:
  c['badge_url']=override['url'];c['badge_source']=override['source']
 download(c)
 if override:badges[c['name']]['identity_status']='community_archive'
with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:list(pool.map(checked_badge,clubs))
write('club-badges.json',badges);write('africa-south-clubs-2026.json',clubs);write('africa-south-competitions-2026.json',comps);write('africa-south-coverage-2026.json',coverage)
print(len(clubs),'clubes;',len(comps),'mercados;',len([c for c in coverage if c['country'] not in ['CL','PE','VE','GY']]),'africanos')
