"""Cadastro europeu 2026/27. Fontes públicas; nenhum acesso a .env ou banco.
Execute com Python/Pillow. --download baixa apenas os escudos ainda ausentes.
Receipts ESPN/ligas ficam em storage/europe-sources (fora do Git).
"""
import json,pathlib,urllib.request,urllib.parse,concurrent.futures,hashlib,io,sys
from html.parser import HTMLParser
root=pathlib.Path(__file__).resolve().parents[1];cache=root/'storage/europe-sources';cache.mkdir(exist_ok=True)
def get(url):return urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'Mozilla/5.0 (Rumo10 catalog)'}),timeout=35).read()
def receipt(code,url):
 p=cache/(code+'.json')
 if not p.exists():p.write_bytes(get(url))
 return json.loads(p.read_bytes())
def write(name,data):(root/'database'/name).write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
settings={
 'EN':('eng',['Premier League','EFL Championship'],[20,24],[90,72],[3,3],['league','six_playoff'],'https://www.premierleague.com/en/clubs','https://www.efl.com/news/2026/march/05/efl-statement--sky-bet-championship-play-off-format/'),
 'ES':('esp',['LaLiga','LaLiga Hypermotion'],[20,22],[87,68],[3,4],['league','four_playoff'],'https://www.laliga.com/en-ES/laliga-easports/clubs','https://www.laliga.com/en-ES/laliga-hypermotion/clubs'),
 'DE':('ger',['Bundesliga','2. Bundesliga'],[18,18],[86,69],[2,2],['barrage','candidate'],'https://www.bundesliga.com/en/bundesliga/clubs','https://www.bundesliga.com/en/2bundesliga/clubs'),
 'IT':('ita',['Serie A','Serie B'],[20,20],[86,67],[3,3],['league','italian_playoff'],'https://www.legaseriea.it/serie-a','https://www.legab.it/seriebkt'),
 'FR':('fra',['Ligue 1','Ligue 2'],[18,18],[82,64],[2,2],['barrage','ladder'],'https://ligue1.com/','https://www.lfp.fr/article/publication-du-calendrier-general-de-la-ligue-2-bkt-pour-la-saison-2026-2027'),
 'PT':('por',['Liga Portugal','Liga Portugal 2'],[18,18],[74,55],[2,2],['barrage','candidate'],'https://www.ligaportugal.pt/','https://www.ligaportugal.pt/pages/estatutos-regulamentos'),
 'NL':('ned',['Eredivisie','Eerste Divisie'],[18,20],[70,50],[2,0],['barrage','dutch_playoff'],'https://eredivisie.com/','https://www.knvb.nl/competities/keuken-kampioen-divisie'),
 'BE':('bel',['Jupiler Pro League','Challenger Pro League'],[18,15],[67,49],[2,2],['league','belgian_playoff'],'https://www.proleague.be/nieuws/vanaf-seizoen-26-27-met-18-clubs-in-de-jupiler-pro-league','https://www.proleague.be/nieuws/kalender-2026-2027-nieuwkomers-treffen-elkaar-meteen-in-challenger-pro-league'),
 'TR':('tur',['Süper Lig','TFF 1. Lig'],[18,20],[68,49],[3,4],['league','turkish_playoff'],'https://www.tff.org/default.aspx?pageID=198','https://www.tff.org/default.aspx?pageID=142'),
 'SC':('sco',['Scottish Premiership','Scottish Championship'],[12,10],[62,42],[1,1],['scottish_split','scottish_playoff'],'https://spfl.co.uk/league/premiership','https://spfl.co.uk/pages/rules-and-regulations'),
}
class Images(HTMLParser):
 def __init__(self):super().__init__();self.items=[]
 def handle_starttag(self,t,a):
  if t=='img':self.items.append(dict(a))
# Relações conferidas com calendário/quadros das ligas. Índices do acervo identificam os escudos, não os participantes.
fallback={
 'PT':('por',21,'Académica Coimbra|AFS|Amarante|Benfica B|Chaves|Farense|Feirense|Felgueiras|Leixões|Lusitânia Lourosa|Penafiel|Portimonense|Porto B|Sporting CP B|Tondela|Torreense|União de Leiria|Vizela'),
 'BE':('bel',20,'RSCA Futures|Beerschot|Club NXT|Dender EH|Eupen|Excelsior Virton|Francs Borains|Jong Genk|Jong KAA Gent|Lierse|Lokeren|Patro Eisden|RFC Liège|Seraing|Sporting Hasselt'),
 'TR':('tur',21,'Antalyaspor|Bandırmaspor|Batman Petrolspor|Bodrum FK|Boluspor|Bursaspor|Esenler Erokspor|Fatih Karagümrük|Iğdır FK|İstanbulspor|Kayserispor|Keçiörengücü|Manisa FK|Mardin 1969|Muğlaspor|Pendikspor|Sarıyer|Sivasspor|Ümraniyespor|Van Spor'),
}
clubs=[];comps=[];histories={};id_to_name={}
for country,(code,names,counts,bases,downs,formats,source1,source2) in settings.items():
 for n in range(2):
  league=f'{code}.{n+1}';url=f'https://site.api.espn.com/apis/site/v2/sports/soccer/{league}/teams?limit=100&season=2026'
  teams=[]
  if n==1 and country in fallback:
   acervo,start,line=fallback[country];p=cache/(acervo+'-badges.html')
   if not p.exists():p.write_bytes(get(f'https://escudosfc.com.br/{acervo}.htm'))
   parser=Images();parser.feed(p.read_text(encoding='cp1252'))
   for i,name in enumerate(line.split('|')):teams.append({'displayName':name,'logos':[{'href':urllib.parse.urljoin('https://escudosfc.com.br/',parser.items[start+i]['src'])}],'source':'https://escudosfc.com.br/'+acervo+'.htm'})
   # AFS não é o antigo Desportivo das Aves; utilize o escudo identificado pela ESPN em 2025/26.
   if country=='PT':
    past=receipt('por.1-standings','https://site.api.espn.com/apis/v2/sports/soccer/por.1/standings?season=2025')
    old=next(e['team'] for e in past['children'][0]['standings']['entries'] if e['team']['displayName'] in ['AVS','AVS Futebol SAD','AFS'])
    teams[1]['logos']=old['logos'];teams[1]['source']='https://www.espn.com/soccer/standings/_/league/por.1/season/2025'
  else:teams=[t['team'] for t in receipt(league,url)['sports'][0]['leagues'][0]['teams']]
  assert len(teams)==counts[n],(league,len(teams),counts[n])
  cid=country.lower()+'-'+str(n+1);roster=[];reserves=[]
  for team in teams:
   name=team['displayName']+'-'+country;roster.append(name)
   reserve=team['displayName'].startswith('Jong ') or team['displayName'] in ['Real Sociedad II','RC Celta Fortuna','Benfica B','Porto B','Sporting CP B','RSCA Futures','Club NXT']
   if reserve:reserves.append(name)
   if team.get('id'):id_to_name[(country,str(team['id']))]=name
   clubs.append({'name':name,'uf':country,'country':country,'competitions':[cid],'source':source1 if n==0 else source2,'roster_source':url if team.get('id') else (source2 if country!='PT' else 'https://en.wikipedia.org/wiki/2026%E2%80%9327_Liga_Portugal_2'),'badge_source':team.get('source',team['links'][0]['href'] if team.get('links') else url),'badge_url':team['logos'][0]['href'],'colors_status':'pending','reserve':reserve,'edition':'2026/27'})
  comps.append({'id':cid,'name':names[n],'edition':2026,'season_label':'2026/27','tier':5 if n==0 else 4,'family':'international','country':country,'format':'european','teams':roster,'groups':[roster],'source':source1 if n==0 else source2,'checked':'2026-10-13','baseline':bases[n],'rules':{'system':formats[n],'relegations':downs[n],'reserves':reserves,'note':'Calendário e resultados simulados. Barragens entre divisões são resolvidas no fechamento da temporada; terceira divisão e copas não incluídas.'}})
# Escudos de entidades atuais; os acervos também contêm clubes extintos e homônimos.
overrides={
 'Mardin 1969-TR':('https://www.futbollogo.com/resimler/logolar/mardin1969spor1.png','https://www.futbollogo.com/takim.php?id=3607'),
 'Van Spor-TR':('https://upload.wikimedia.org/wikipedia/en/4/4d/Belediye_Vanspor.png','https://en.wikipedia.org/wiki/Vanspor_FK'),
 'Lokeren-BE':('https://kassiesa.net/uefa/clubs/images/KSC-Lokeren%402.-new-logo.png','https://kassiesa.net/uefa/clubs/html/KSC-Lokeren.html'),
 'Lusitânia Lourosa-PT':('https://www.ligaportugal.pt/backoffice/assets/small_Lusitania_e11dda0d6f.png','https://www.ligaportugal.pt/team/219/l.-lourosa-fc/20262027'),
}
for club in clubs:
 if club['name'] in overrides:club['badge_url'],club['badge_source']=overrides[club['name']]
# Campanhas completas 2025/26 por identificador: clubes novos sem dados permanecem provisórios.
for country,(code,*_) in settings.items():
 for n in range(2):
  p=cache/(f'{code}.{n+1}-standings.json')
  if not p.exists():continue
  data=json.loads(p.read_bytes())
  assert data['season']['year']==2025
  children=data.get('children',[])
  # Ligas com split: só o quadro geral é usado; jamais duplicar pontos das fases.
  for child in children[:1]:
   entries=child.get('standings',{}).get('entries',[])
   for position,e in enumerate(entries,1):
    key=id_to_name.get((country,str(e['team']['id'])));stats={s['name']:s.get('value',0) for s in e['stats']}
    if key and stats.get('gamesPlayed',0)>=28:histories[key]={'position':int(stats.get('rank',position)),'teams':len(entries),'points':int(stats['points']),'games':int(stats['gamesPlayed']),'edition':'2025/26','source':f'https://www.espn.com/soccer/standings/_/league/{code}.{n+1}/season/2025'}
if '--download' in sys.argv:
 from PIL import Image
 badges=json.loads((root/'database/club-badges.json').read_bytes());errors=[]
 def download(club):
  try:
   file='eu-'+hashlib.sha256(club['name'].encode()).hexdigest()[:14]+'.png';target=root/'public/assets/clubs'/file
   if not target.exists() or (club['name'] in overrides and badges.get(club['name'],{}).get('download_url')!=club['badge_url']):
    im=Image.open(io.BytesIO(get(club['badge_url']))).convert('RGBA');im.thumbnail((256,256));im.save(target)
   im=Image.open(target).convert('RGBA');assert min(im.size)>=16
   small=im.copy();small.thumbnail((96,96));hist={}
   for r,g,b,a in small.getdata():
    if a<160 or min(r,g,b)>240:continue
    rgb=tuple(min(255,round(v/32)*32) for v in (r,g,b));hist[rgb]=hist.get(rgb,0)+1
   palette=[]
   for rgb,count in sorted(hist.items(),key=lambda x:-x[1]):
    if count<max(hist.values(),default=1)*.06:break
    if all(sum((a-b)**2 for a,b in zip(rgb,other))>5500 for other in palette):palette.append(rgb)
    if len(palette)==3:break
   if len(palette)<2:palette.append((255,255,255))
   club['colors']=['#'+''.join(f'{v:02X}' for v in rgb) for rgb in palette];club['colors_status']='approximate_badge_palette';club['colors_source']=club['badge_source']
   badges[club['name']]={'path':'/assets/clubs/'+file,'source':club['badge_source'],'download_url':club['badge_url'],'retrieved':'2026-10-13','identity_status':'federation_source' if club['name'] in ['Lusitânia Lourosa-PT'] else ('publisher_source' if 'espn' in club['badge_url'] else 'community_archive')}
   return None
  except Exception as e:return club['name']+': '+str(e)
 with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:errors=[r for r in pool.map(download,clubs) if r]
 if errors:raise RuntimeError('\n'.join(errors))
 write('club-badges.json',badges)
write('europe-clubs-2026.json',clubs);write('europe-competitions-2026.json',comps);write('europe-results-2025.json',histories)
print(len(clubs),'clubes;',len(comps),'divisões;',len(histories),'campanhas anteriores')
