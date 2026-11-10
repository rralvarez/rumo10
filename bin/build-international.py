"""Reconstrói o cadastro de 2026 a partir da relação conferida e dos acervos citados.
Não acessa o banco. Os escudos são buscados apenas com --download.
"""
import json,pathlib,sys,urllib.request,urllib.parse,concurrent.futures,io,hashlib
from html.parser import HTMLParser
root=pathlib.Path(__file__).resolve().parents[1]
sources=root/'storage/international-sources';sources.mkdir(exist_ok=True)
class Images(HTMLParser):
 def __init__(self):super().__init__();self.items=[]
 def handle_starttag(self,tag,attrs):
  if tag=='img':self.items.append(dict(attrs))
def get(url):return urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'Mozilla/5.0 (Rumo10 catalog)'}),timeout=35).read()
def parse(country):
 path=sources/('badges-'+country+'.html')
 if not path.exists():path.write_bytes(get('https://escudosfc.com.br/'+country+'.htm'))
 p=Images();p.feed(path.read_text(encoding='cp1252'));return p.items
def write(name,data):(root/'database'/name).write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
# Ordem do acervo inspecionada: nome inequívoco e índice da imagem evitam confundir homônimos.
rosters={
'AR':('arg',[
 'Boca Juniors|Central Córdoba Santiago del Estero|Defensa y Justicia|Deportivo Riestra|Estudiantes La Plata|Gimnasia Mendoza|Independiente|Instituto Córdoba|Lanús|Newell’s Old Boys|Platense|San Lorenzo|Talleres Córdoba|Unión Santa Fe|Vélez Sarsfield',
 'Aldosivi|Argentinos Juniors|Atlético Tucumán|Banfield|Barracas Central|Belgrano Córdoba|Estudiantes Río Cuarto|Gimnasia La Plata|Huracán|Independiente Rivadavia|Racing Club|River Plate|Rosario Central|Sarmiento Junín|Tigre',
 'Acassuso|All Boys|Almirante Brown|Central Norte Salta|Chaco For Ever|Ciudad de Bolívar|Colón Santa Fe|Defensores de Belgrano|Deportivo Madryn|Deportivo Morón|Estudiantes Caseros|Ferro Carril Oeste|Godoy Cruz|Los Andes|Mitre Santiago del Estero|Racing Córdoba|San Miguel|San Telmo',
 'Agropecuario Argentino|Almagro|Atlanta|Atlético Rafaela|Chacarita Juniors|Colegiales|Deportivo Maipú|Ferro Carril Midland|Gimnasia Jujuy|Gimnasia y Tiro|Güemes Santiago del Estero|Nueva Chicago|Patronato|Quilmes|San Martín San Juan|San Martín Tucumán|Temperley|Tristán Suárez']),
'UY':('uru',[
 'Albion|Boston River|Central Español|Cerro|Cerro Largo|Danubio|Defensor Sporting|Deportivo Maldonado|Juventud Las Piedras|Liverpool Montevideo|Montevideo City Torque|Montevideo Wanderers|Nacional Montevideo|Peñarol|Progreso|Racing Montevideo',
 'Atenas San Carlos|Cerrito|Colón Montevideo|Fénix|Huracán FC|La Luz|Miramar Misiones|Oriental La Paz|Paysandú FC|Plaza Colonia|Rentistas|River Plate Montevideo|Tacuarembó|Uruguay Montevideo']),
'PY':('par',[
 '2 de Mayo|Sportivo Ameliano|Cerro Porteño|Guaraní|Libertad|Nacional Asunción|Olimpia|Recoleta|Rubio Ñu|Sportivo San Lorenzo|Sportivo Luqueño|Sportivo Trinidense',
 '3 de Noviembre|12 de Junio Villa Hayes|Benjamín Aceval|Deportivo Capiatá|Deportivo Santaní|Encarnación FC|Fernando de la Mora|General Caballero JLM|Guaireña|Independiente Campo Grande|Paraguarí AC|Resistencia|Sol de América|Sportivo Carapeguá|Tacuary|Atlético Tembetary']),
'BO':('bol',[
 'ABB|Always Ready|Aurora|Blooming|Bolívar|Guabirá|GV San José|Independiente Petrolero|Nacional Potosí|Oriente Petrolero|Real Potosí|Real Tomayapo|San Antonio Bulo Bulo|The Strongest|Totora Real Oruro|Universitario de Vinto',
 'Deportivo A y B|Germán Busch|Deportivo The Strongest Guayaramerín|Universitario del Beni|1º de Mayo Beni|Atlético del Beni|Deportivo Kivón',
 'Atlético Junior Yotala|Flamengo Sucre|Nacional Sucre|Universitario Sucre|Atlético Sucre|Fancesa|Impacto Deportivo|Tomina Lions',
 'Cultural Amanecer|Deportivo Libertad Quillacollo|Olympic|Tiquipaya|Atlético Independiente Cochabamba|Israel University|Mi Llajta|Deportivo Tigres',
 'Chaco Petrolero|Hiska Nacional|Pucarani|Torrez El Alto|26 de Febrero|FATIC|Ingenio|Virgen de Chijipata',
 '22 de Octubre Caracollo|Deportivo Shalón|Ingenieros|Oruro Royal|AFIZ|Deportivo Escara|Empresa Minera Huanuni|Guadalajara Qaqachaca',
 'Independiente Unificada|Juan Aurich Alcatuyo|Deportivo Mineros Potosí|Río San Juan Humi|Atlético Minero Amayapampa|Highland Players|Wilstermann Cooperativas',
 'Bilbao Rioja|Nacional La Pampa|Unión Tarija|Universitario Tarija|García Agreda|Municipal Tarija|Nacional SENAC|Deportivo San Martín Yacuiba',
 'Astros de Pando|Chaco FC|Deportivo El Sena|Universitario de Pando|Filadelfia|Libertad Pando|Noroeste Santa Nelly',
 'Nueva Santa Cruz|Real Santa Cruz|Royal Pari|San Juan FC|Ciudad Nueva Santa Cruz Academia|Deportivo Esperanza|Los Ángeles Cotoca|Virginia USC'])}
official={
 'AR':['https://www.lpf.org.ar/notas/primera/2025/12/10/se-definen-las-zonas-para-la-temporada-20226/','https://www.afa.com.ar/3423/posts/primera-nacional-fixture-para-la-temporada-2026'],
 'UY':['https://sudamericanos20.auf.org.uy/liga-auf-uruguaya/','https://sudamericanos20.auf.org.uy/segunda-division-profesional/'],
 'PY':['https://www.apf.org.py/copa-de-primera','https://www.apf.org.py/noticias/asi-iniciara-la-intermedia-2026'],
 'BO':['https://fbf.com.bo/torneos/division-profesional/','https://fbf.com.bo/arranca-la-copa-simon-bolivar-2026/']}
names={'AR':['Liga Profesional Argentina','Primera Nacional Argentina'],'UY':['Liga AUF Uruguaya','Segunda División Profesional Uruguay'],'PY':['Copa de Primera Paraguay','División Intermedia Paraguay'],'BO':['División Profesional Bolivia','Copa Simón Bolívar Bolivia']}
clubs=[];competitions=[];downloads=[]
for country,(acervo,lines) in rosters.items():
 images=parse(acervo);groups=[[name+'-'+country for name in line.split('|')] for line in lines];imageindex=2
 split=2 if country=='AR' else 1
 for n,divisiongroups in enumerate([groups[:split],groups[split:]]):
  division=n+1;comp=country.lower()+'-'+str(division);teams=sum(divisiongroups,[])
  c={'id':comp,'name':names[country][n],'edition':2026,'tier':4 if division==1 else 3,'family':'international','country':country,'format':'international','teams':teams,'groups':divisiongroups,'source':official[country][n],'checked':'2026-10-13'}
  competitions.append(c)
  for group in divisiongroups:
   for name in group:
    asset=images[imageindex];imageindex+=1
    url=urllib.parse.urljoin('https://escudosfc.com.br/',asset['src'])
    badge_source='https://escudosfc.com.br/'+acervo+'.htm'
    if name=='GV San José-BO':
     url='https://fbf.com.bo/wp-content/uploads/2025/12/1-14-400x400.png';badge_source='https://fbf.com.bo/torneos/division-profesional/'
    club={'name':name,'uf':country,'country':country,'competitions':[comp],'source':official[country][n],'badge_source':badge_source,'badge_url':url,'colors_status':'pending'}
    clubs.append(club);downloads.append((club,url))
# Grupos do Torneo Competencia publicados pela AUF; a grade efetiva inclui Fénix.
uy=next(c for c in competitions if c['id']=='uy-2');uy['groups']=[[name+'-UY' for name in x] for x in [
 ['Huracán FC','Tacuarembó','Uruguay Montevideo','Atenas San Carlos','La Luz','Cerrito','Fénix'],
 ['Rentistas','Paysandú FC','River Plate Montevideo','Miramar Misiones','Plaza Colonia','Oriental La Paz','Colón Montevideo']]]
# Bolívia: nove departamentos, dois grupos em cada um; emparelhamento local do acervo.
bo=next(c for c in competitions if c['id']=='bo-2');bo['departments']=['Beni','Chuquisaca','Cochabamba','La Paz','Oruro','Potosí','Tarija','Pando','Santa Cruz']
bo['groups']=sum([[g[:4],g[4:]] for g in bo['groups']],[])
write('international-competitions-2026.json',competitions)
if '--download' in sys.argv:
 from PIL import Image
 badges=json.loads((root/'database/club-badges.json').read_text(encoding='utf-8'))
 def download(item):
  club,url=item
  try:
   blob=get(url);im=Image.open(io.BytesIO(blob)).convert('RGBA');im.thumbnail((256,256))
   if im.width<16 or im.height<16:raise ValueError('Imagem muito pequena')
   file='intl-'+hashlib.sha256(club['name'].encode()).hexdigest()[:14]+'.png';target=root/'public/assets/clubs'/file;im.save(target)
   # Paleta aproximada do escudo; não é certificação de cores de uniforme.
   small=im.copy();small.thumbnail((96,96));hist={}
   for r,g,b,a in small.getdata():
    if a<160 or min(r,g,b)>240:continue
    rgb=tuple(min(255,round(v/32)*32) for v in (r,g,b));hist[rgb]=hist.get(rgb,0)+1
   palette=[]
   for rgb,count in sorted(hist.items(),key=lambda row:-row[1]):
    if count<max(hist.values(),default=1)*.04:break
    if all(sum((a-b)**2 for a,b in zip(rgb,other))>5500 for other in palette):palette.append(rgb)
    if len(palette)==3:break
   if len(palette)<2:palette.append((255,255,255))
   club['colors']=['#'+''.join(f'{v:02X}' for v in rgb) for rgb in palette];club['colors_status']='approximate_badge_palette';club['colors_source']=club['badge_source']
   badges[club['name']]={'path':'/assets/clubs/'+file,'source':club['badge_source'],'download_url':url,'retrieved':'2026-10-13','identity_status':'federation_source' if club['name']=='GV San José-BO' else 'community_archive'}
   return club['name'],True
  except Exception as ex:return club['name'],str(ex)
 with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
  for name,result in pool.map(download,downloads):
   if result is not True:print(name,result)
 write('club-badges.json',badges)
write('international-clubs-2026.json',clubs)
print(len(clubs),'clubes;',len(competitions),'divisões; escudos',sum(c['colors_status']!='pending' for c in clubs))
