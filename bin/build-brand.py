"""Identidade vetorial própria e exports PNG/ICO. Nenhuma fonte é distribuída."""
from pathlib import Path
from PIL import Image,ImageDraw,ImageFont
root=Path(__file__).resolve().parents[1];out=root/'public/assets/brand';out.mkdir(parents=True,exist_ok=True)
lime='#D7FF60';ink='#0C171B';white='#F1F7F3'
# Numeral geométrico e avanço diagonal: legível mesmo sem o nome do jogo.
mark=f'<rect x="4" y="4" width="88" height="88" rx="22" fill="{lime}"/><path d="M20 34L34 26H43V72H32V41L20 47Z" fill="{ink}"/><rect x="49" y="26" width="27" height="46" rx="13.5" fill="{ink}"/><rect x="59" y="36" width="7" height="26" rx="3.5" fill="{lime}"/><path d="M72 12H84V24H80V19L72 27L69 24L77 16H72Z" fill="{ink}"/>'
def svg(body,viewbox,title):return f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{viewbox}" role="img" aria-label="{title}"><title>{title}</title>{body}</svg>\n'
(out/'mark.svg').write_text(svg(mark,'0 0 96 96','Rumo à Camisa 10'),encoding='utf8')
for name,color in [('logo',white),('logo-dark',ink)]:
 body=f'<g transform="translate(0 8)">{mark}</g><g fill="{color}" font-family="Segoe UI,Arial,sans-serif" font-weight="800"><text x="116" y="42" font-size="23" letter-spacing="4">RUMO À</text><text x="113" y="85" font-size="40" letter-spacing="-1">CAMISA 10</text></g>'
 (out/(name+'.svg')).write_text(svg(body,'0 0 336 112','Rumo à Camisa 10'),encoding='utf8')
font_path=Path('C:/Windows/Fonts/segoeuib.ttf')
def icon(size):
 scale=8;s=size*scale;im=Image.new('RGBA',(s,s));d=ImageDraw.Draw(im);q=lambda v:round(v/96*s)
 d.rounded_rectangle((q(4),q(4),q(92),q(92)),radius=q(22),fill=lime)
 d.polygon([(q(x),q(y)) for x,y in [(20,34),(34,26),(43,26),(43,72),(32,72),(32,41),(20,47)]],fill=ink)
 d.rounded_rectangle((q(49),q(26),q(76),q(72)),radius=q(13.5),fill=ink);d.rounded_rectangle((q(59),q(36),q(66),q(62)),radius=q(3.5),fill=lime)
 d.polygon([(q(x),q(y)) for x,y in [(72,12),(84,12),(84,24),(80,24),(80,19),(72,27),(69,24),(77,16),(72,16)]],fill=ink)
 return im.resize((size,size),Image.Resampling.LANCZOS)
for size,name in [(32,'favicon-32.png'),(180,'apple-touch-icon.png'),(192,'icon-192.png'),(512,'icon-512.png')]:icon(size).save(out/name)
icon(256).save(root/'public/favicon.ico',sizes=[(16,16),(24,24),(32,32),(48,48),(64,64),(128,128),(256,256)])
(root/'public/favicon.svg').write_text(svg(mark,'0 0 96 96','Rumo à Camisa 10'),encoding='utf8')
if font_path.is_file():
 for name,color in [('logo',white),('logo-dark',ink)]:
  im=Image.new('RGBA',(672,224));im.alpha_composite(icon(192),(0,16));d=ImageDraw.Draw(im)
  d.text((232,32),'RUMO À',font=ImageFont.truetype(str(font_path),46),fill=color)
  d.text((226,106),'CAMISA 10',font=ImageFont.truetype(str(font_path),80),fill=color)
  im.save(out/(name+'.png'))
  if name=='logo':
   preview=Image.new('RGBA',im.size,ink);preview.alpha_composite(im);preview.convert('RGB').save(out/'logo-preview.png')
print('OK: logo SVG/PNG, marca, favicon SVG/ICO e ícones 32/180/192/512.')
