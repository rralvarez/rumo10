"""Elencos públicos ESPN, ratings publicados EA FC27 e complemento fictício.
Execute export-player-clubs.php antes. --fetch consulta fontes públicas e usa cache.
Sem acesso ao banco ou .env. Nunca importa retratos, textos editoriais ou dados de login.
"""
import pathlib,json,urllib.request,re,unicodedata,hashlib,concurrent.futures,sys,datetime
root=pathlib.Path(__file__).resolve().parents[1];cache=root/'storage/player-sources';out=root/'database/rosters';out.mkdir(exist_ok=True)
clubs=json.loads((cache/'clubs.json').read_bytes());fetch='--fetch' in sys.argv;today='2026-10-13'
def norm(s):return re.sub(r'[^a-z0-9]','',unicodedata.normalize('NFKD',s).encode('ascii','ignore').decode().lower())
def digest(s):return hashlib.sha256(s.encode()).hexdigest()
def get(url):return urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'Mozilla/5.0 (Rumo10 roster catalog)'}),timeout=18).read()
def data(path,url):
 if path.exists():return json.loads(path.read_bytes())
 if not fetch:return None
 obj=json.loads(get(url));path.write_text(json.dumps(obj,ensure_ascii=False),encoding='utf-8');return obj
leagues={**{code+'.'+str(n):country+'-'+str(n) for code,country in [('eng','en'),('esp','es'),('ger','de'),('ita','it'),('fra','fr'),('por','pt'),('ned','nl'),('bel','be'),('tur','tr'),('sco','sc')] for n in [1,2]},'bra.1':'br-a','bra.2':'br-b','bra.3':'br-c','bra.4':'br-d','arg.1':'ar-1','arg.2':'ar-2','uru.1':'uy-1','uru.2':'uy-2','par.1':'py-1','par.2':'py-2','bol.1':'bo-1','bol.2':'bo-2'}
leagues.update({'usa.1':'us-1','mex.1':'mx-1'})
leagues.update({'chi.1':'cl-1','per.1':'pe-1','ven.1':'ve-1','rsa.1':'za-1','nga.1':'ng-1','gha.1':'gh-1','ken.1':'ke-1'})
leagues.update({code+'.1':country+'-1' for code,country in [('crc','cr'),('hon','hn'),('gua','gt'),('slv','sv'),('nor','no'),('den','dk'),('gre','gr'),('cyp','cy'),('rus','ru'),('ksa','sa'),('ecu','ec'),('col','co')]})
aliases={'América-MG':'América Mineiro','Atlético-GO':'Atlético Goianiense','Operário-PR':'Operário PR','São Bernardo FC-SP':'São Bernardo','Bayern Munich-DE':'Bayern München','Internazionale-IT':'Inter','FC Cologne-DE':'1. FC Köln','Hertha Berlin-DE':'Hertha BSC','Borussia Mönchengladbach-DE':'Borussia Mgladbach','VfL Osnabruck-DE':'VfL Osnabrück','C.D. Nacional-PT':'Nacional','FC Famalicao-PT':'Famalicão','Vitória de Guimaraes-PT':'Vitória SC','Benfica-PT':'SL Benfica','FC Porto-PT':'FC Porto','AC Milan-IT':'Milan','AS Roma-IT':'Roma','Ajax Amsterdam-NL':'Ajax','AZ Alkmaar-NL':'AZ','Feyenoord Rotterdam-NL':'Feyenoord','Heerenveen-NL':'sc Heerenveen','Racing Genk-BE':'KRC Genk','Antwerp-BE':'Royal Antwerp FC','Anderlecht-BE':'RSC Anderlecht','Union St.-Gilloise-BE':'Union SG','Standard Liege-BE':'Standard Liège','Waasland-Beveren-BE':'SK Beveren','Galatasaray-TR':'Galatasaray','Besiktas-TR':'Beşiktaş','Fenerbahce-TR':'Fenerbahçe','Heart of Midlothian-SC':'Hearts','Alavés-ES':'Deportivo Alavés','Barcelona-ES':'FC Barcelona','Atlético Madrid-ES':'Atlético de Madrid','Deportivo-ES':'RC Deportivo','Racing Santander-ES':'Racing de Santander','Málaga-ES':'Málaga CF','Real Valladolid-ES':'Real Valladolid CF','Sporting Gijón-ES':'Real Sporting','Almería-ES':'UD Almería','Castellón-ES':'CD Castellón','Leganés-ES':'CD Leganés','CD Sabadell-ES':'CE Sabadell','Stade Rennais-FR':'Stade Rennais FC','Paris Saint-Germain-FR':'Paris SG','Brest-FR':'Stade Brestois 29','AJ Auxerre-FR':'AJ Auxerre','Stade de Reims-FR':'Stade de Reims','US Avellino-IT':'Avellino'}
def names(club):
 # Remove apenas o sufixo cadastrado; seleção permanece restrita à competição/país.
 return {norm(club),norm(club.rsplit('-',1)[0]),norm(aliases.get(club,''))}-{''}
errors=[];team_jobs=[]
def league_job(item):
 code,cid=item;url=f'https://site.api.espn.com/apis/site/v2/sports/soccer/{code}/teams?limit=100&season=2026'
 try:
  old=root/'storage/europe-sources'/(code+'.json');p=cache/(code+'-teams.json')
  d=json.loads(old.read_bytes()) if old.exists() else data(p,url)
  if not d:return []
  teams=d['sports'][0]['leagues'][0]['teams'];candidates=[c for c,v in clubs.items() if cid in v['competitions']];jobs=[]
  for t in teams:
   team=t['team'];options={norm(team.get(k,'')) for k in ['displayName','name','shortDisplayName']} - {''}
   matches=[c for c in candidates if names(c)&options]
   if len(matches)==1:jobs.append((matches[0],code,str(team['id'])))
  return jobs
 except Exception as e:errors.append({'source':url,'error':type(e).__name__});return []
with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:
 for jobs in pool.map(league_job,leagues.items()):team_jobs+=jobs
team_jobs=list({club:(club,code,tid) for club,code,tid in team_jobs}.values())
print('Clubes ESPN identificados sem ambiguidade:',len(team_jobs),flush=True)
if not (cache/'ea-filters.json').exists():
 if not fetch:raise RuntimeError('Sem cache EA. Execute com --fetch para consultar fontes públicas.')
 html=get('https://www.ea.com/games/ea-sports-fc/ratings/teams-ratings/arsenal/1').decode('utf-8')
 payload=json.loads(re.search(r'<script id="__NEXT_DATA__"[^>]*>(.*?)</script>',html,re.S)[1])['props']['pageProps']
 (cache/'ea-filters.json').write_text(json.dumps(payload['ratingsFilters']['teamGroups']),encoding='utf-8')
ea_filters=json.loads((cache/'ea-filters.json').read_bytes())
ea_country={'13':'EN','14':'EN','53':'ES','54':'ES','19':'DE','20':'DE','31':'IT','32':'IT','16':'FR','17':'FR','308':'PT','10':'NL','4':'BE','68':'TR','50':'SC','353':'AR'}
ea_country.update({'39':'US','341':'MX'})
ea_country.update({'1':'DK','41':'NO','66':'PL','350':'SA'})
ea_jobs=[]
for group in ea_filters:
 country=ea_country.get(str(group['id']))
 if not country or group.get('gender',{}).get('id')!=0:continue
 candidates=[c for c,v in clubs.items() if v['country']==country or (str(group['id'])=='39' and 'us-1' in v['competitions'])]
 for team in group['teams']:
  matches=[c for c in candidates if norm(team['label']) in names(c)]
  if len(matches)==1:ea_jobs.append((matches[0],team['id'],team['label']))
def ea_job(job):
 club,tid,label=job;p=cache/('ea-'+str(tid)+'.json');slug=re.sub('[^a-z0-9]+','-',unicodedata.normalize('NFKD',label).encode('ascii','ignore').decode().lower()).strip('-');url=f'https://www.ea.com/games/ea-sports-fc/ratings/teams-ratings/{slug}/{tid}'
 try:
  if p.exists():return club,json.loads(p.read_bytes()),url
  if not fetch:return club,[],url
  s=get(url).decode('utf-8');match=re.search(r'<script id="__NEXT_DATA__"[^>]*>(.*?)</script>',s,re.S)
  if not match or 'FC™ 27' not in s and 'fc-27' not in s:raise ValueError('Edição não confirmada')
  d=json.loads(match[1])['props']['pageProps'];items=[a for a in d['ratingsEntries']['items'] if a.get('gender',{}).get('id')==0 and str(a['team']['id'])==str(tid)]
  # Guardar somente fatos necessários, não textos, imagens ou atributos completos do produto.
  rows=[{k:a[k] for k in ['id','firstName','lastName','commonName','birthdate','overallRating','position']} for a in items]
  p.write_text(json.dumps(rows,ensure_ascii=False),encoding='utf-8');return club,rows,url
 except Exception as e:errors.append({'club':club,'source':url,'error':type(e).__name__});return club,[],url
ea={}
with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:
 for club,rows,url in pool.map(ea_job,ea_jobs):ea[club]=(rows,url)
print('Clubes com notas EA publicadas:',sum(bool(v[0]) for v in ea.values()),flush=True)
positions={'G':'goleiro','GK':'goleiro','D':'zagueiro','CB':'zagueiro','LB':'lateral','RB':'lateral','LWB':'lateral','RWB':'lateral','M':'meia','CM':'meia','AM':'meia','CAM':'meia','CDM':'volante','DM':'volante','LM':'ponta','RM':'ponta','LW':'ponta','RW':'ponta','F':'centroavante','ST':'centroavante','CF':'centroavante'}
def birthday(s):
 if not s:return None
 for fmt in ['%Y-%m-%d','%m/%d/%Y']:
  try:return datetime.datetime.strptime(s.split('T')[0].split(' ')[0],fmt).date().isoformat()
  except ValueError:pass
 return None
def rating_match(a,ratings):
 dob=birthday(a.get('dateOfBirth'));name=norm(a.get('fullName') or a['displayName']);tokens={norm(t) for t in (a.get('fullName') or a['displayName']).split() if len(norm(t))>=3}
 matches=[]
 for r in ratings:
  fullname=r['firstName']+' '+r['lastName'];rtokens={norm(t) for t in fullname.split() if len(norm(t))>=3}
  given=norm(a.get('firstName',''));family=norm(a.get('lastName',''));rgiven=norm(r['firstName']);rfamily=norm(r['lastName'])
  short_given=len(given)>=3 and len(rgiven)>=3 and (given.startswith(rgiven) or rgiven.startswith(given))
  if dob and dob==birthday(r['birthdate']) and (norm(fullname)==name or len(tokens&rtokens)>=2 or (short_given and family==rfamily)):matches.append(r)
 return matches[0] if len(matches)==1 else None
def roster_job(job):
 club,code,tid=job;url=f'https://site.api.espn.com/apis/site/v2/sports/soccer/{code}/teams/{tid}/roster?season=2026';path=cache/(code+'-'+tid+'-roster.json')
 try:
  d=data(path,url)
  if not d:return club,[]
  if d.get('season',{}).get('year')!=2026 or str(d.get('team',{}).get('id'))!=tid:raise ValueError('Clube/edição divergente')
  athletes=d.get('athletes',[]);players=[];ratings,rating_url=ea.get(club,([],None))
  for a in athletes:
   if a.get('gender') not in [None,'MALE']:continue
   if a.get('defaultTeam',{}).get('id') and str(a['defaultTeam']['id'])!=tid:continue
   position=a.get('position',{}).get('abbreviation');mapped=positions.get(position)
   if not mapped or not a.get('id') or not a.get('displayName'):continue
   rid='espn:'+str(a['id']);published=rating_match(a,ratings);seed=int(digest(rid)[:8],16)
   age=a.get('age');dob=birthday(a.get('dateOfBirth'))
   if age is None and dob:
    born=datetime.date.fromisoformat(dob);ref=datetime.date.fromisoformat(today);age=ref.year-born.year-((ref.month,ref.day)<(born.month,born.day))
   penalty=8 if age is None else (12 if age<18 else (6 if age<20 else 0))
   overall=published['overallRating'] if published else max(20,min(95,round(clubs[club]['overall']-16+seed%9-4-penalty)))
   if published and position in ['D','M','F']:mapped=positions.get(published['position']['shortLabel'],mapped)
   players.append({'id':rid,'name':a['displayName'],'full_name':a.get('fullName',a['displayName']),'birthdate':birthday(a.get('dateOfBirth')),'age_at_import':age,'position':mapped,'source_position':position,'origin':'real','overall':int(overall),'overall_origin':'published_ea_fc27' if published else 'estimated_rumo10_v1','rating_source':rating_url if published else None,'rating_external_id':published['id'] if published else None,'source':url,'source_season':d['season'].get('displayName'),'checked':today})
  return club,players
 except Exception as e:errors.append({'club':club,'source':url,'error':type(e).__name__});return club,[]
real={}
with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:
 for club,players in pool.map(roster_job,team_jobs):real[club]=players
# Uma falha de fonte não apaga um elenco real já importado no catálogo local.
previous_path=root/'database/player-catalog.json'
if previous_path.exists():
 previous=json.loads(previous_path.read_bytes())['clubs']
 for club,entry in previous.items():
  old_players=json.loads((out/entry['file']).read_bytes())['players']
  if not real.get(club) and entry['real']:
   real[club]=[p for p in old_players if p['origin']=='real']
  else:
   old_by_id={p['id']:p for p in old_players}
   for p in real.get(club,[]):
    old=old_by_id.get(p['id'])
    if old and old['overall_origin']=='published_ea_fc27' and p['overall_origin']!='published_ea_fc27':
     for key in ['overall','overall_origin','rating_source','rating_external_id']:p[key]=old[key]
# Um atleta real só tem um clube no catálogo desta edição; afiliação ambígua vira pendência, não duplicação.
owners={}
for club,players in real.items():
 for p in players:owners.setdefault(p['id'],[]).append(club)
for club in real:real[club]=[p for p in real[club] if len(owners[p['id']])==1]
first={'BR':['Rafael','Lucas','Bruno','Gabriel','Caio','Pedro','Diego','Felipe','João','Vinícius','André','Leandro'],'ES':['Álvaro','Javier','Pablo','Diego','Sergio','Hugo','Adrián','Manuel'],'FR':['Lucas','Théo','Hugo','Antoine','Mathis','Alexandre'],'EN':['James','Oliver','Harry','Jack','George','Thomas'],'DE':['Lukas','Jonas','Felix','Leon','Max','Paul'],'IT':['Luca','Marco','Andrea','Matteo','Davide','Giulio'],'PT':['João','Tiago','Diogo','Miguel','Rui','Nuno'],'NL':['Daan','Bram','Lars','Luuk','Jesse','Milan'],'BE':['Arthur','Louis','Jules','Lucas','Noah','Liam'],'TR':['Emir','Eren','Kerem','Arda','Mert','Yusuf'],'SC':['Callum','Ewan','Lewis','Scott','Angus','Fraser']}
last={'BR':['Silva','Souza','Costa','Oliveira','Santos','Pereira','Almeida','Ferreira','Rocha','Lima'],'ES':['García','Fernández','Martínez','López','González','Ruiz'],'FR':['Martin','Bernard','Dubois','Moreau','Laurent','Girard'],'EN':['Smith','Jones','Brown','Taylor','Wilson','Walker'],'DE':['Müller','Schmidt','Weber','Wagner','Becker','Schulz'],'IT':['Rossi','Russo','Ferrari','Romano','Conti','Marino'],'PT':['Silva','Costa','Ferreira','Pereira','Almeida','Ribeiro'],'NL':['de Jong','de Vries','van Dijk','Jansen','Bakker','Visser'],'BE':['Peeters','Janssens','Maes','Jacobs','Willems','Dubois'],'TR':['Yılmaz','Kaya','Demir','Şahin','Çelik','Yıldız'],'SC':['Campbell','Stewart','MacDonald','Fraser','Robertson','Murray']}
roles=['goleiro']*2+['zagueiro']*4+['lateral']*4+['volante']*3+['meia']*4+['ponta']*4+['centroavante']*3
index={};total_real=total_fake=total_rated=0
for cc in ['US','CA','JM']:first[cc]=first['EN'];last[cc]=last['EN']
for cc in ['HT']:first[cc]=first['FR'];last[cc]=last['FR']
first.update({'SA':['Omar','Hassan','Ali','Fahad','Abdullah','Yasser'],'CZ':['Jan','Petr','Tomas','Pavel','Jakub','Lukas'],'PL':['Jan','Piotr','Pawel','Jakub','Kamil','Tomasz'],'HU':['Bence','Mate','Adam','Peter','Daniel','Laszlo'],'HR':['Luka','Ivan','Marko','Josip','Ante','Mateo'],'SR':['Daan','Ryan','Rico','Johan','Kevin','Brian']})
last.update({'SA':['Al Salem','Al Harbi','Al Qahtani','Al Dossari','Al Ali','Al Ghamdi'],'CZ':['Novak','Svoboda','Novotny','Dvorak','Cerny','Prochazka'],'PL':['Nowak','Kowalski','Wisniewski','Wojcik','Kowalczyk','Kaminski'],'HU':['Nagy','Kovacs','Toth','Szabo','Horvath','Varga'],'HR':['Horvat','Kovacevic','Babic','Maric','Juric','Novak'],'SR':['Pinas','Buitenhuis','San A Jong','Vlijter','Buur','Mac Donald']})
first.update({'ZA':['Sipho','Thabo','Themba','Sizwe','Andile','Bongani'],'NG':['Ayo','Chinedu','Ibrahim','Emeka','Samuel','Musa'],'GH':['Kwame','Kofi','Yaw','Kojo','Daniel','Joseph'],'KE':['David','Kevin','Brian','Peter','James','John'],'GY':['Ravi','Devon','Shawn','Kevin','Aaron','Jason']})
last.update({'ZA':['Dlamini','Nkosi','Mokoena','Khumalo','Mabena','Ndlovu'],'NG':['Okafor','Adeyemi','Ibrahim','Bello','Nwosu','Musa'],'GH':['Mensah','Owusu','Asante','Boateng','Appiah','Osei'],'KE':['Otieno','Kamau','Njoroge','Kiptoo','Wanjala','Ochieng'],'GY':['Singh','Persaud','Joseph','James','Williams','Ramdeen']})
for cc in ['MA','EG','DZ','TN','LY']:first[cc]=first['SA'];last[cc]=last['SA']
for cc in ['TZ','ZM','UG','RW']:first[cc]=first['KE'];last[cc]=last['KE']
for cc in ['CD','AO','CI','CM','SN','ML','ET']:
 first[cc]=['Mamadou','Amadou','Moussa','Ibrahim','Jean','Patrick'];last[cc]=['Diallo','Diop','Traore','Koné','Camara','Mbemba']
for club,meta in clubs.items():
 players=real.get(club,[]);used={p['name'] for p in players};country=meta['country'];pool_country=country if country in first else 'ES'
 slots=list(roles)
 for p in players:
  if p['position'] in slots:slots.remove(p['position'])
 # Elenco mínimo 24, com dois goleiros; fontes genéricas D/M/F não inventam posições específicas dos reais.
 needed=max(0,24-len(players));fill=(['goleiro']*max(0,2-sum(p['position']=='goleiro' for p in players)))
 fill+= [r for r in slots if r!='goleiro'][:max(0,needed-len(fill))]
 for i,position in enumerate(fill):
  seed=int(digest(club+'|'+str(i))[:12],16);name=first[pool_country][seed%len(first[pool_country])]+' '+last[pool_country][(seed//17)%len(last[pool_country])]
  if name in used:name+=' '+last[pool_country][(seed//31+1)%len(last[pool_country])]
  used.add(name);players.append({'id':'fictional:'+digest(club+'|'+str(i))[:20],'name':name,'birthdate':None,'age_at_import':18+seed%16,'position':position,'origin':'fictional','overall':max(20,min(95,round(meta['overall']-5+seed%13-6))),'overall_origin':'estimated_rumo10_v1','rating_source':None,'source':None,'checked':today})
 real_count=sum(p['origin']=='real' for p in players);rated=sum(p['overall_origin']=='published_ea_fc27' for p in players);total_real+=real_count;total_fake+=len(players)-real_count;total_rated+=rated
 filename=digest(club)[:20]+'.json';payload={'club':club,'edition':meta.get('edition','2026' if country in ['BR','AR','UY','PY','BO','US','CA','MX','CR','HN','GT','SV','NO','EC','CO'] else '2026/27'),'checked':today,'players':players}
 (out/filename).write_text(json.dumps(payload,ensure_ascii=False,separators=(',',':'))+'\n',encoding='utf-8')
 index[club]={'file':filename,'real':real_count,'fictional':len(players)-real_count,'published_ratings':rated,'total':len(players),'status':'mixed' if real_count and len(players)>real_count else ('real' if real_count else 'fictional')}
summary={'checked':today,'clubs':len(index),'clubs_with_real_players':sum(v['real']>0 for v in index.values()),'real_players':total_real,'fictional_players':total_fake,'published_ratings':total_rated,'estimated_ratings':total_real+total_fake-total_rated,'ambiguous_real_ids':sum(len(v)>1 for v in owners.values()),'failed_sources':errors}
(root/'database/player-catalog.json').write_text(json.dumps({'summary':summary,'clubs':index},ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(json.dumps({k:v for k,v in summary.items() if k!='failed_sources'},ensure_ascii=True),flush=True)
