"""MLS 2026: cadastro, conferências, escudos e cores aproximadas.
Fontes públicas; recibos em storage/concacaf-sources. Sem banco ou credenciais.
"""
import pathlib,json,urllib.request,hashlib,io,concurrent.futures
from PIL import Image
root=pathlib.Path(__file__).resolve().parents[1]
cache=root/'storage/concacaf-sources';cache.mkdir(exist_ok=True)
def get(url):return urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'Mozilla/5.0 (Rumo10 catalog)'}),timeout=35).read()
def write(name,data):(root/'database'/name).write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
p=cache/'usa.1.json'
url='https://site.api.espn.com/apis/site/v2/sports/soccer/usa.1/teams?limit=100&season=2026'
if not p.exists():p.write_bytes(get(url))
teams=[t['team'] for t in json.loads(p.read_bytes())['sports'][0]['leagues'][0]['teams']]
assert len(teams)==30
east={'18418','21300','182','183','193','18267','20232','9720','18986','189','17606','12011','10739','190','7318'}
canada={'9720','7318','9727'}
clubs=[];groups=[[],[]]
source='https://www.mlssoccer.com/league-reports/competition-guidelines/'
for team in teams:
 country='CA' if team['id'] in canada else 'US';name=team['displayName']+'-'+country
 groups[0 if team['id'] in east else 1].append(name)
 clubs.append({'name':name,'uf':country,'country':country,'competitions':['us-1'],'source':source,'roster_source':url,'badge_source':url,'badge_url':team['logos'][0]['href'],'edition':'2026','reserve':False})
badges=json.loads((root/'database/club-badges.json').read_bytes())
def download(club):
 file='cc-'+hashlib.sha256(club['name'].encode()).hexdigest()[:14]+'.png';target=root/'public/assets/clubs'/file
 if not target.exists():
  im=Image.open(io.BytesIO(get(club['badge_url']))).convert('RGBA');im.thumbnail((256,256));im.save(target)
 im=Image.open(target).convert('RGBA');im.thumbnail((96,96));hist={}
 for r,g,b,a in im.getdata():
  if a<160 or min(r,g,b)>240:continue
  rgb=tuple(min(255,round(v/32)*32) for v in (r,g,b));hist[rgb]=hist.get(rgb,0)+1
 palette=[]
 for rgb,count in sorted(hist.items(),key=lambda x:-x[1]):
  if count<max(hist.values(),default=1)*.06:break
  if all(sum((a-b)**2 for a,b in zip(rgb,other))>5500 for other in palette):palette.append(rgb)
  if len(palette)==3:break
 if len(palette)<2:palette.append((255,255,255))
 club['colors']=['#'+''.join(f'{v:02X}' for v in rgb) for rgb in palette];club['colors_status']='approximate_badge_palette';club['colors_source']=club['badge_source']
 badges[club['name']]={'path':'/assets/clubs/'+file,'source':club['badge_source'],'download_url':club['badge_url'],'retrieved':'2026-10-13','identity_status':'publisher_source'}
comps=[{'id':'us-1','name':'Major League Soccer','edition':2026,'season_label':'2026','tier':5,'family':'international','country':'US','format':'concacaf','teams':[c['name'] for c in clubs],'groups':groups,'source':source,'checked':'2026-10-13','baseline':73,'rules':{'system':'mls','relegations':0,'note':'34 jogos, conferências, wild card, melhor de três e MLS Cup. Adversários interconferência simulados; prorrogação e datas exatas não modeladas.'}}]
if __name__=='__main__':
 raise SystemExit('Execute build-world-markets.py para preservar todo o cadastro da expansão.')
