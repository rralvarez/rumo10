"""Expansão por top 100 mundial Opta + mercados Concacaf com cobertura ESPN.
As ligas adicionais têm calendário de protótipo explicitamente identificado.
Fonte da seleção: bundle público da própria visualização Opta (masculino).
"""
import runpy,pathlib,json,re,urllib.request,concurrent.futures
root=pathlib.Path(__file__).resolve().parents[1];cache=root/'storage/concacaf-sources'
env=runpy.run_path(str(root/'bin/build-concacaf.py'));download=env['download'];write=env['write'];badges=env['badges']
clubs=env['clubs'];comps=env['comps']
url='https://dataviz.theanalyst.com/opta-power-rankings/index.js';p=cache/'opta.js'
if not p.exists():p.write_bytes(env['get'](url))
allrows=None
for m in re.finditer(r'\[\{"rank":1,',p.read_text(encoding='utf-8')):
 rows,_=json.JSONDecoder().raw_decode(p.read_text(encoding='utf-8')[m.start():])
 if len(rows)>10000:allrows=rows;break
assert allrows and len(allrows[:100])==100
allrows=[r for r in allrows if r.get('contestantName')]
settings={
 'MX':('mex','Liga MX',18,75,'https://fmf.mx/justicia-deportiva/reglamentos','short_cups'),
 'CR':('crc','Primera División de Costa Rica',10,59,'https://www.unafut.com/','short_four'),
 'HN':('hon','Liga Nacional de Honduras',12,56,'https://www.lnphn.com/','short_cups'),
 'GT':('gua','Liga Nacional de Guatemala',12,54,'https://ligagt.com/','short_cups'),
 'SV':('slv','Primera División de El Salvador',12,50,'https://laprimera.com.sv/','short_cups'),
 'NO':('nor','Eliteserien',16,69,'https://www.eliteserien.no/','league'),
 'DK':('den','Superliga',12,70,'https://www.superliga.dk/stats','split'),
 'GR':('gre','Super League Greece',14,72,'https://www.slgr.gr/en/','split_greece'),
 'CY':(None,'Cyprus First Division',14,62,'https://www.cfa.com.cy/','adapted'),
 'RU':('rus','Russian Premier League',16,74,'https://premierliga.ru/','league'),
 'SA':('ksa','Saudi Pro League',18,74,'https://www.spl.com.sa/','league'),
 'EC':('ecu','LigaPro Serie A',16,69,'https://ligapro.ec/tabla-de-posiciones/','split_ecuador'),
 'CO':('col','Liga BetPlay',20,72,'https://dimayor.com.co/estadisticas/','short_cups'),
 'CZ':(None,'Chance Liga',16,68,'https://www.chanceliga.cz/','adapted'),
 'PL':(None,'Ekstraklasa',18,66,'https://www.ekstraklasa.org/','adapted'),
 'HU':(None,'NB I',12,60,'https://www.mlsz.hu/','adapted'),
 'HR':(None,'HNL',10,66,'https://hnl.hr/','adapted'),
}
opta_countries={'CZ':'Czechia','PL':'Poland','HU':'Hungary','HR':'Croatia','CY':'Cyprus'}
for country,(code,title,count,base,source,system) in settings.items():
 if code:
  f=cache/(code+'.1.json');u=f'https://site.api.espn.com/apis/site/v2/sports/soccer/{code}.1/teams?limit=100&season=2026'
  if not f.exists():f.write_bytes(env['get'](u))
  teams=[t['team'] for t in json.loads(f.read_bytes())['sports'][0]['leagues'][0]['teams']];assert len(teams)==count,(code,len(teams))
 else:
  # Cobertura só dos clubes selecionados quando não há lista de participantes verificada.
  teams=[{'displayName':r['contestantName'],'logos':[{'href':f"https://omo.akamai.opta.net/image.php?secure=true&h=omo.akamai.opta.net&sport=football&entity=team&description=badges&dimensions=150&id={r['contestantId']}"}]} for r in allrows[:100] if r['country']==opta_countries[country]]
  title='Circuito '+title+' (adaptado)'
  # Um só clube não pode disputar sozinho: usar outros dois da mesma liga da fonte, sem afirmar filiação oficial.
  league=next(r['league'] for r in allrows[:100] if r['country']==opta_countries[country])
  existing={t['displayName'] for t in teams}
  for r in allrows:
   if len(teams)>=4:break
   if r['country']==opta_countries[country] and r['league']==league and r['contestantName'] not in existing:
    teams.append({'displayName':r['contestantName'],'logos':[{'href':f"https://omo.akamai.opta.net/image.php?secure=true&h=omo.akamai.opta.net&sport=football&entity=team&description=badges&dimensions=150&id={r['contestantId']}"}]});existing.add(r['contestantName'])
  u='https://theanalyst.com/articles/who-are-the-best-football-team-in-the-world-opta-power-rankings'
 cid=country.lower()+'-1';names=[]
 for team in teams:
  if not team.get('logos'):
   lookup={'Akritas Chlorakas':'Akritas','Ethnikos Achnas':'Ethnikos Achna','Krasava':'Krasava Ypsonas','Enosis Neon Paralimni':'Enosis'}.get(team['displayName'],team['displayName'])
   r=next(r for r in allrows if r['country']=='Cyprus' and r['contestantName']==lookup)
   team['logos']=[{'href':f"https://omo.akamai.opta.net/image.php?secure=true&h=omo.akamai.opta.net&sport=football&entity=team&description=badges&dimensions=150&id={r['contestantId']}"}]
  name=team['displayName']+'-'+country;names.append(name)
  clubs.append({'name':name,'uf':country,'country':country,'competitions':[cid],'source':source if code else u,'roster_source':u,'badge_source':u,'badge_url':team['logos'][0]['href'],'edition':'2026/27' if country in ['DK','GR','CY','RU','SA','CZ','PL','HU','HR'] else '2026','reserve':False,'membership_status':'publisher_current_season' if code else 'selected_reference_clubs'})
 comps.append({'id':cid,'name':title,'edition':2026,'season_label':clubs[-1]['edition'],'tier':5,'family':'international','country':country,'format':'world','teams':names,'groups':[names],'source':source,'checked':'2026-10-13','baseline':base,'rules':{'system':system,'relegations':0,'note':'Calendário de protótipo; datas, acessos, regras administrativas e vagas continentais não modelados. Circuitos adaptados têm apenas clubes de referência, não a liga oficial completa.'}})
# Países adicionais representados entre os 100 primeiros da Concacaf.
from html.parser import HTMLParser
from urllib.parse import urljoin
class Ranking(HTMLParser):
 def __init__(self):super().__init__();self.rows=[];self.row=None;self.cell=None
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='tr' and 'data-region' in a:self.row=[]
  if tag=='td' and self.row is not None:self.cell={'text':'','images':[]}
  if tag=='img' and self.cell is not None:self.cell['images'].append(a.get('src',''))
 def handle_data(self,text):
  if self.cell is not None:self.cell['text']+=text
 def handle_endtag(self,tag):
  if tag=='td' and self.cell is not None:self.row.append(self.cell);self.cell=None
  if tag=='tr' and self.row is not None:self.rows.append(self.row);self.row=None
ranking=Ranking();rp=cache/'ranking.html';ru='https://www.hypercube.nl/CCR/ranking.php?type=Club'
if not rp.exists():rp.write_bytes(env['get'](ru))
ranking.feed(rp.read_text(encoding='utf-8'))
ranking_rows=[{'rank':int(r[0]['text']),'name':r[1]['text'].strip(),'country':r[2]['text'].strip(),'league':r[3]['text'].strip(),'points':int(r[5]['text'].replace(',','')),'badge':urljoin(ru,r[1]['images'][0])} for r in ranking.rows if len(r)==7]
write('concacaf-ranking-2026.json',{'source':'https://www.concacaf.com/rankings/club','ranking_date':'2026-10-12','retrieved':'2026-10-13','clubs':ranking_rows[:100]})
for country,nation,title,base in [('CA','Canada','Canadian Premier League',52),('NI','Nicaragua','Liga Primera',45),('PA','Panama','Liga Panameña',51),('HT','Haiti','Ligue Haïtienne',42),('JM','Jamaica','Jamaica Premier League',44),('DO','Dominican Republic','Liga Dominicana',40),('SR','Suriname','Suriname Major League',39)]:
 rows=[r for r in ranking_rows if r['country']==nation and r['league']!='Major League Soccer'];selected=[r for r in rows if r['rank']<=100]
 for r in rows:
  if len(selected)>=4:break
  if r not in selected:selected.append(r)
 source='https://www.concacaf.com/rankings/club';system='adapted';cid=country.lower()+'-1';names=[]
 if country=='CA':
  source='https://external-tags.canpl.ca/about-us';system='cpl';selected=[]
  mapping={'Cavalry':'Cavalry FC','Forge':'Forge FC','Atlético Ottawa':'Atlético Ottawa','Supra du Québec':'FC Supra','HFX Wanderers':'Halifax Wanderers','Vancouver FC':'Vancouver FC','Inter Toronto':'Inter Toronto','Pacific':'Pacific FC'}
  for r in allrows:
   if r['country']=='Canada' and r['contestantName'] in mapping:selected.append({'name':mapping[r['contestantName']],'badge':f"https://omo.akamai.opta.net/image.php?secure=true&h=omo.akamai.opta.net&sport=football&entity=team&description=badges&dimensions=150&id={r['contestantId']}"})
  assert len(selected)==8
 else:title='Circuito '+title+' (adaptado)'
 for r in selected:
  name=r['name']+'-'+country;names.append(name);clubs.append({'name':name,'uf':country,'country':country,'competitions':[cid],'source':source,'roster_source':ru,'badge_source':ru if country!='CA' else 'https://theanalyst.com/articles/who-are-the-best-football-team-in-the-world-opta-power-rankings','badge_url':r['badge'],'edition':'2026','reserve':False,'membership_status':'federation_source' if country=='CA' else 'selected_reference_clubs'})
 comps.append({'id':cid,'name':title,'edition':2026,'season_label':'2026','tier':5,'family':'international','country':country,'format':'world','teams':names,'groups':[names],'source':source,'checked':'2026-10-13','baseline':base,'rules':{'system':system,'relegations':0,'note':'CPL: 28 jogos, semifinal ida/volta e final única. Outros circuitos: seleção de clubes pelo ranking, não campeonato oficial completo.'}})
# Overall de jogo derivado da nota pública Opta; nunca confundir com classificação FIFA.
import unicodedata
def normal(s):return re.sub('[^a-z0-9]','',unicodedata.normalize('NFKD',s).encode('ascii','ignore').decode().lower())
for club in clubs:
 n=normal(club['name'].rsplit('-',1)[0]);matches=[r for r in allrows if n in {normal(r['contestantName']),normal(r.get('contestantShortName','')),normal(r.get('contestantClubName',''))}]
 if len(matches)==1:
  club['reference_overall']=round(max(20,min(95,matches[0]['currentRating'])),1);club['overall_source']='https://theanalyst.com/articles/who-are-the-best-football-team-in-the-world-opta-power-rankings';club['overall_method']='opta_public_snapshot_clamped_20_95'
with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:list(pool.map(download,clubs))
for name in list(badges):
 if name.endswith('-CY') and name not in {c['name'] for c in clubs}:del badges[name]
write('club-badges.json',badges);write('concacaf-clubs-2026.json',clubs);write('concacaf-competitions-2026.json',comps)
# Seleção auditável por fonte/rank; ligas completas importadas para não restringir adversários aos 100.
existing=[]
for f in ['official-clubs-2026.json','state-clubs-2026.json','international-clubs-2026.json','europe-clubs-2026.json']:existing+=json.loads((root/'database'/f).read_bytes())
existing+=clubs
def norm(s):
 import unicodedata
 return re.sub('[^a-z0-9]','',unicodedata.normalize('NFKD',s).encode('ascii','ignore').decode().lower())
aliases={'Bayern München':'Bayern Munich-DE','Union Saint-Gilloise':'Union St.-Gilloise-BE','Roma':'AS Roma-IT','Bayer 04 Leverkusen':'Bayer Leverkusen-DE','Porto':'FC Porto-PT','Bodø / Glimt':'Bodo/Glimt-NO','Stuttgart':'VfB Stuttgart-DE','PSV':'PSV Eindhoven-NL','Milan':'AC Milan-IT','Slavia Praha':'Slavia Praha-CZ','Olympique Lyonnais':'Lyon-FR','Independiente Valle':'Independiente del Valle-EC','Rennes':'Stade Rennais-FR','Viking':'Viking FK-NO','Olympiakos Piraeus':'Olympiacos-GR','Mainz 05':'Mainz-DE','Feyenoord':'Feyenoord Rotterdam-NL','Athletic Club':'Athletic Club-ES','Olympique Marseille':'Marseille-FR','Sporting Braga':'Braga-PT','København':'F.C. København-DK','Zenit':'Zenit St Petersburg-RU','Genk':'Racing Genk-BE','Celta de Vigo':'Celta Vigo-ES'}
country_codes={'England':'EN','Germany':'DE','Spain':'ES','France':'FR','Italy':'IT','Portugal':'PT','Belgium':'BE','Brazil':'BR','Argentina':'AR','Netherlands':'NL','Türkiye':'TR','Monaco':'FR',**{v:k for k,v in opta_countries.items()},'Norway':'NO','Denmark':'DK','Greece':'GR','Cyprus':'CY','Ecuador':'EC','Colombia':'CO','Russia':'RU','Saudi Arabia':'SA','Mexico':'MX'}
aliases.update({'Atlético de Madrid':'Atlético Madrid-ES','Flamengo':'Flamengo-RJ','Cruzeiro':'Cruzeiro-MG','Fluminense':'Fluminense-RJ','Monaco':'AS Monaco-FR','Hoffenheim':'TSG Hoffenheim-DE','Athletico Paranaense':'Athletico-PR'})
selected=[];missing=[]
for r in allrows[:100]:
 cc=country_codes[r['country']];opts=[c['name'] for c in existing if (c.get('country') or 'BR')==cc and norm(c['name'].rsplit('-',1)[0]) in {norm(r['contestantName']),norm(r['contestantShortName']),norm(r['contestantClubName'])}]
 alias=aliases.get(r['contestantName']);names={c['name'] for c in existing}
 club=alias if alias in names else (opts[0] if len(set(opts))==1 else None)
 if not club:missing.append((r['rank'],r['contestantName'],cc))
 selected.append({'rank':r['rank'],'source_name':r['contestantName'],'club':club,'country':cc,'rating':r['currentRating']})
write('world-top100-2026.json',{'source':'https://theanalyst.com/articles/who-are-the-best-football-team-in-the-world-opta-power-rankings','retrieved':'2026-10-13','ranking_date':'Snapshot público consultado; sem data explícita no bundle','provider':'Opta (não FIFA)','clubs':selected})
print(len(clubs),'clubes adicionados;',len(comps),'mercados; seleção pendente:',json.dumps(missing,ensure_ascii=True))
