"""Auditoria Opta 250 + EA FC27 250 e duas divisões dos mercados ausentes.
Fatos de fontes públicas; recibos em storage/top250-sources. Sem banco/.env.
Execute com o Python com Pillow. Nomes Opta conciliados com listas dos organizadores.
Calendário de jogo adaptado, sem alegar reprodução de regulamentos oficiais.
"""
import pathlib,json,re,urllib.request,unicodedata,runpy,concurrent.futures,hashlib
root=pathlib.Path(__file__).resolve().parents[1];cache=root/'storage/top250-sources';cache.mkdir(exist_ok=True)
def get(url):return urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'Mozilla/5.0 (RumoHub public catalog)'}),timeout=35).read()
def write(name,data):(root/'database'/name).write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n',encoding='utf8')
def norm(s):return re.sub('[^a-z0-9]','',unicodedata.normalize('NFKD',s).encode('ascii','ignore').decode().lower())
p=cache/'opta.js'
if not p.exists():p.write_bytes(get('https://dataviz.theanalyst.com/opta-power-rankings/index.js'))
text=p.read_text(encoding='utf8');rows=None
for m in re.finditer(r'\[\{"rank":1,',text):
 data,_=json.JSONDecoder().raw_decode(text[m.start():])
 if len(data)>10000:rows=[r for r in data if r.get('contestantName')];break
assert rows and len(rows)>=250
# league, title, counts, primary sources. Full participants, not a four-team circuit.
markets={
 'AT':('Austria',['Austrian Bundesliga','Austrian 2. Liga'],['Bundesliga austríaca','2. Liga austríaca'],[12,16],['https://www.bundesliga.at/de/klubs','https://www.2liga.at/de/klubs'],72),
 'AZ':('Azerbaijan',['Azerbaijan Premier League','Birinci Dasta'],['Misli Premyer Liqası','I Liqa'],[12,10],['https://pfl.az/turnir-cədvəli','https://liqa1.pfl.az/turnir-cədvəli'],62),
 'CH':('Switzerland',['Brack Super League','dieci Challenge League'],['Brack Super League','dieci Challenge League'],[12,10],['https://sfl.ch/de/klubs-brack-super-league','https://sfl.ch/de/klubs-dieci-challenge-league'],70),
 'UA':('Ukraine',['Ukraine Premier League','Persha Liga'],['Premier League ucraniana','Persha Liga'],[16,16],['https://upl.ua/ua/tournaments/championship?id=432&tab=table','https://pfl.ua/news/1699'],67),
 'RS':('Serbia',['Serbian Super Liga','Prva Liga'],['SuperLiga sérvia','Prva Liga sérvia'],[14,16],['https://fss.rs/takmicenje/mozzart-bet-super-liga-srbije-26-27/?script=lat','https://fss.rs/takmicenje/mozzart-bet-prva-liga-srbije-26-27/?script=lat'],65),
 'JP':('Japan',['Japanese J1 League','Japanese J2 League'],['J1 League','J2 League'],[20,20],['https://www.jleague.jp/news/article/32852/?mode=pc']*2,65),
 'IL':('Israel',["Israeli Ligat Ha'al",'Liga Leumit'],['Ligat Ha’al','Liga Leumit'],[14,16],['https://www.football.org.il/en/leagues/league/?league_id=40&season_id=28','https://www.football.org.il/en/leagues/league/?league_id=45&season_id=28'],63),
 'BG':('Bulgaria',['Bulgaria First League','Second League'],['Parva Liga','Vtora Liga'],[14,18],['https://bpfl.bg/ranking']*2,61),
 'SE':('Sweden',['Swedish Allsvenskan','Swedish Superettan'],['Allsvenskan','Superettan'],[16,16],['https://www.svenskfotboll.se/4af842/globalassets/svff/dokumentdokumentblock/tavling/arsplaner--seriesammansattningar/2026/faststalld-seriesammansattning-allsvenskan-och-superettan-2026.pdf']*2,64)
}
excluded={('AT',2):{'Stripfing'},('AZ',2):{'Difai Ağsu'},('UA',2):{'Vorskla'},('RS',2):{'Dinamo Jug'}}
opta_source='https://theanalyst.com/articles/who-are-the-best-football-team-in-the-world-opta-power-rankings'
env=runpy.run_path(str(root/'bin/build-concacaf.py'));badges=env['badges'];download=env['download'];clubs=[];comps=[];coverage=[]
az_aliases={'Karvan':'Karvan-Yevlax','Şahdağ':'Şahdağ Qusar','MOIK':'MOİK','Zira':'Zirə','Kapaz':'Kəpəz','Safa':'Şəfa','Turan':'Turan Tovuz','Araz Naxçıvan':'Araz-Naxçıvan'}
az_badges={}
bg_aliases={'Vihren Sandanski':'ofk-vixren-sandanski','CSKA Sofia II':'pfk-cska-ii-ead','Fratria':'profesionalen-futbolen-klub-fratriia-ad','Chernomorets Burgas':'futbolen-klub-cernomorec-1919-burgas','Yantra 2019':'ofk-iantra-2019','Beroe Stara Zagora':'pfk-beroe-stara-zagora','Marek':'fk-marek-1915','Hebar 1918':'pfk-xebier-1918-ad','Etar VT':'fk-etier-vt','Ludogorets II':'pfk-ludogorec-1945-ii','Nesebar':'sdruzenie-obshhinski-futbolen-klub-nesebier','Rilski sportist':'futbolen-klub-rilski-sportist-2011','Montana 1921':'pfk-montana-1921','Dobrudzha 1919':'futbolen-klub-dobrudza-1919','Lokomotiv GO':'ofk-lokomotiv-gorna-oriaxovica','Spartak Pleven':'ofk-spartak-pl','Sportist Svoge':'sportist-2009','Pirin Blagoevgrad':'pfk-pirin-22-ad'}
bg_badges={};bp=cache/'bg-ranking.html'
if not bp.exists():bp.write_bytes(get('https://bpfl.bg/ranking'))
for m in re.finditer(r'<a[^>]*href="/teams/vtora-liga/([^"]+)"[^>]*>(.*?)</a>',bp.read_text(encoding='utf8'),re.S):
 urls=re.findall(r'https://admin\.bpfl\.bg/storage/football_clubs_teams/[^"&?\s]+',m[2])
 if urls:bg_badges[m[1]]=urls[0]
for level in [1,2]:
 p=cache/f'az{level}-grid.json'
 if not p.exists():p.write_bytes(get(f'https://pfl.az/api/v1/leagues/grid?season_id=76&league_id={51+level}'))
 data=json.loads(p.read_bytes())['data'];assert len(data)==(12 if level==1 else 10)
 az_badges.update({r['title']:r['logo'] for r in data})
for country,(nation,leagues,titles,counts,sources,baseline) in markets.items():
 for level in [1,2]:
  cid=country.lower()+'-'+str(level);source=sources[level-1]
  selected=[r for r in rows if r['country']==nation and r['league']==leagues[level-1] and r['contestantName'] not in excluded.get((country,level),set())]
  if (country,level)==('CH',1):selected.append(next(r for r in rows if r['country']=='Liechtenstein' and r['contestantName']=='Vaduz'))
  assert len(selected)==counts[level-1],(cid,len(selected),counts[level-1])
  names=[]
  for row in selected:
   clubcountry='LI' if row['contestantName']=='Vaduz' and country=='CH' else country;name=row['contestantName']+'-'+clubcountry;names.append(name)
   badge=f"https://omo.akamai.opta.net/image.php?secure=true&h=omo.akamai.opta.net&sport=football&entity=team&description=badges&dimensions=150&id={row['contestantId']}"
   club={'name':name,'uf':clubcountry,'country':clubcountry,'competitions':[cid],'edition':'2026' if country=='SE' else '2026/27','source':source,'roster_source':opta_source,'membership_status':'primary_list_reconciled_opta','badge_url':badge,'badge_source':opta_source,'reserve':row['contestantName'].endswith(' II'),'reference_overall':max(20,min(95,round(float(row['currentRating']),1))),'overall_source':opta_source,'overall_checked':'2026-10-14','opta_id':row['contestantId'],'source_name':row['contestantName']}
   if country=='AZ':
    key=az_aliases.get(row['contestantName'],row['contestantName']);assert key in az_badges,key
    club['badge_url']=az_badges[key];club['badge_source']=source
    # Force refresh if a previous provider badge was only a generic placeholder.
    old=badges.get(name)
    if old and old.get('download_url')!=club['badge_url']:
     path=root/('public'+old['path']);path.unlink(missing_ok=True)
   if country=='BG' and level==2:
    key=bg_aliases[row['contestantName']];assert key in bg_badges,key
    club['badge_url']=bg_badges[key];club['badge_source']='https://bpfl.bg/teams/vtora-liga/'+key
    old=badges.get(name)
    if old and old.get('download_url')!=club['badge_url']:(root/('public'+old['path'])).unlink(missing_ok=True)
   clubs.append(club)
  comps.append({'id':cid,'name':titles[level-1],'edition':2026,'season_label':'2026' if country=='SE' else '2026/27','country':country,'family':'international','tier':5 if level==1 else 4,'format':'world','teams':names,'groups':[names],'source':source,'checked':'2026-10-14','baseline':baseline-(level-1)*12,'rules':{'system':'league','relegations':0,'note':'Participantes das duas divisões cadastrados. Calendário de jogo adaptado em ida/volta; fases adicionais, acessos, licenças, vagas continentais e datas oficiais não reproduzidos.'}})
  coverage.append({'competition':cid,'country':country,'level':level,'clubs':len(names),'source':source,'naming_source':opta_source,'checked':'2026-10-14','excluded_stale_provider_entries':sorted(excluded.get((country,level),set()))})
participants={c['id']:{'source':c['source'],'teams':c['teams']} for c in comps}
pin=root/'database/top250-participants-2026.json'
if pin.exists():assert json.loads(pin.read_bytes())==participants,'Participantes mudaram: requer nova conciliação das listas oficiais, não atualização automática por ranking.'
else:write(pin.name,participants)
# Escudos: a identificação usa ID Opta ou página do clube da liga, nunca homônimos.
errors=[]
previous_coverage=root/'database/top250-coverage-2026.json'
pending_previous={p['club'] for p in json.loads(previous_coverage.read_bytes())['pending_badges'] if p['reason']=='generic_provider_placeholder'} if previous_coverage.exists() else set()
def badge_job(c):
 if c['name'] in pending_previous and c['badge_source']==opta_source:
  c['colors']=['#334155','#FFFFFF'];c['colors_status']='pending_verification';c['colors_source']=None;badges.pop(c['name'],None);errors.append({'club':c['name'],'reason':'generic_provider_placeholder'});return
 try:download(c);badges[c['name']]['retrieved']='2026-10-14';badges[c['name']]['identity_status']='opta_id';return
 except Exception as e:
  c['colors']=['#334155','#FFFFFF'];c['colors_status']='pending_verification';c['colors_source']=None;badges.pop(c['name'],None);errors.append({'club':c['name'],'reason':type(e).__name__})
with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:list(pool.map(badge_job,clubs))
# Provider's generic badge is shared by unrelated clubs: never present it as a crest.
hashes={}
for c in clubs:
 if c['name'] in badges:
  p=root/('public'+badges[c['name']]['path']);hashes.setdefault(hashlib.sha256(p.read_bytes()).hexdigest(),[]).append(c)
for group in hashes.values():
 if len(group)<5:continue
 for c in group:
  c['colors']=['#334155','#FFFFFF'];c['colors_status']='pending_verification';c['colors_source']=None
  old=badges.pop(c['name']);(root/('public'+old['path'])).unlink(missing_ok=True)
  errors.append({'club':c['name'],'reason':'generic_provider_placeholder'})
write('top250-clubs-2026.json',clubs);write('top250-competitions-2026.json',comps);write('club-badges.json',badges)
write('top250-coverage-2026.json',{'checked':'2026-10-14','markets':coverage,'pending_badges':errors,'notes':['Listas completas nas 18 divisões acrescentadas; não significa todas as divisões do mundo.','Vaduz: clube de Liechtenstein na pirâmide suíça.','No Japão a edição 2026/27 sucedeu o torneio especial de transição de 2026.','Fontes ESPN antigas não substituem as listas atuais dos organizadores.']})
# Preserva os nomes/IDs locais já usados pelas carreiras; aliases específicos e auditáveis.
known={}
for file in (root/'database').glob('*clubs-2026.json'):
 for c in json.loads(file.read_bytes()):known[c['name']]=c
aliases={r['source_name']:r['club'] for r in json.loads((root/'database/world-top100-2026.json').read_bytes())['clubs']}
aliases.update({'Atlético Mineiro':'Atlético-MG','Independiente Rivadavia':'Independiente Rivadavia-AR','Ajax':'Ajax Amsterdam-NL','AZ':'AZ Alkmaar-NL','Belgrano':'Belgrano Córdoba-AR','Instituto':'Instituto Córdoba-AR','Estudiantes':'Estudiantes La Plata-AR','Elversberg':'SV Elversberg-DE','São Paulo':'São Paulo-SP','Racing de Santander':'Racing Santander-ES','Peñarol':'Peñarol-UY','LDU Quito':'Liga de Quito-EC','Club América':'América-MX','Hamburger SV':'Hamburg SV-DE','Köln':'FC Cologne-DE','Botafogo':'Botafogo-RJ',"Borussia M'gladbach":'Borussia Mönchengladbach-DE','Sint-Truiden':'Sint-Truidense-BE','América de Cali':'América de Cali-CO','Independiente Santa Fe':'Independiente Santa Fe-CO','Spartak Moskva':'Spartak Moscow-RU','Dinamo Moskva':'Dinamo Moscow-RU','Auxerre':'AJ Auxerre-FR','Tromsø':'Tromso-NO','Vitória':'Vitória-BA','Nacional':'Nacional Montevideo-UY'})
ranking=[]
country_codes={'England':'EN','Germany':'DE','Spain':'ES','France':'FR','Italy':'IT','Portugal':'PT','Belgium':'BE','Brazil':'BR','Norway':'NO','Monaco':'FR','Netherlands':'NL','Czechia':'CZ','Ecuador':'EC','Türkiye':'TR','Argentina':'AR','Greece':'GR','Cyprus':'CY','Saudi Arabia':'SA','Mexico':'MX','Poland':'PL','Denmark':'DK','Hungary':'HU','Croatia':'HR','Russia':'RU','Colombia':'CO','Austria':'AT','Azerbaijan':'AZ','Switzerland':'CH','Ukraine':'UA','USA':'US','Wales':'EN','Serbia':'RS','Uruguay':'UY','Japan':'JP','Bolivia':'BO','Egypt':'EG','Scotland':'SC','Israel':'IL','Paraguay':'PY','Chile':'CL','Canada':'CA','Bulgaria':'BG','Sweden':'SE'}
for row in rows[:250]:
 country=country_codes[row['country']]
 options={norm(row[k]) for k in ['contestantName','contestantShortName','contestantClubName'] if row.get(k)}
 matches=[c for c in known if known[c].get('country','BR')==country and norm(c.rsplit('-',1)[0]) in options]
 alias=aliases.get(row['contestantName']);alias=alias if alias in known and known[alias].get('country','BR')==country else None
 if row['country']=='Ecuador' and row['contestantName']=='Universidad Católica':alias='Universidad Católica (Quito)-EC'
 name=alias or (matches[0] if len(matches)==1 else None)
 assert name in known,(row['rank'],row['contestantName'],matches)
 ranking.append({'rank':row['rank'],'source_name':row['contestantName'],'club':name,'country_in_source':row['country'],'league_in_source':row['league'],'opta_id':row['contestantId'],'rating':float(row['currentRating'])})
write('world-top250-2026.json',{'source':opta_source,'data_source':'https://dataviz.theanalyst.com/opta-power-rankings/','retrieved':'2026-10-14','ranking_date':None,'criterion':'Primeiros 250 do ranking mundial masculino Opta no bundle público consultado; não é ranking FIFA.','clubs':ranking})
# Pagination is exposed by the public EA ratings page's Next.js client.
def ea_page(page):
 p=cache/f'ea-page{page}.html'
 if not p.exists():p.write_bytes(get(f'https://www.ea.com/games/ea-sports-fc/ratings?page={page}'))
 s=p.read_text(encoding='utf8');assert 'fc-27' in s or 'FC™ 27' in s
 return json.loads(re.search(r'<script id="__NEXT_DATA__"[^>]*>(.*?)</script>',s,re.S)[1])['props']['pageProps']['ratingDetails']['items']
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:ea=[r for page in pool.map(ea_page,range(1,7)) for r in page if r['gender']['id']==0]
unique={r['id']:r for r in ea};ea=sorted(unique.values(),key=lambda r:(r['rank'],r['id']))[:250];assert len(ea)==250
facts=[]
for order,r in enumerate(ea,1):
 row={k:r[k] for k in ['id','rank','overallRating','firstName','lastName','commonName','birthdate','leagueName','position']};row['male_rank']=order;row['team']={k:r['team'][k] for k in ['id','label']};facts.append(row)
write('players-top250-2026.json',{'source':'https://www.ea.com/games/ea-sports-fc/ratings','edition':'FC27','retrieved':'2026-10-14','criterion':'250 primeiros homens na ordenação pública da EA; overall e desempates da fonte, não avaliação universal de desempenho real. Clube licenciado da fonte pode divergir do vínculo real atual.','players':facts})
print('Top 250 clubes conciliados; 250 jogadores pesquisados;',len(clubs),'clubes em',len(comps),'novas divisões; escudos pendentes',len(errors),flush=True)
