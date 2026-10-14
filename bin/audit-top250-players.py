"""Liga fatos EA do recorte aos IDs reais já conciliados, sem mover atletas de clube."""
import pathlib,json,collections
root=pathlib.Path(__file__).resolve().parents[1];p=root/'database/players-top250-2026.json';d=json.loads(p.read_bytes());index=json.loads((root/'database/player-catalog.json').read_bytes());linked=collections.defaultdict(list)
for club,e in index['clubs'].items():
 for player in json.loads((root/'database/rosters'/e['file']).read_bytes())['players']:
  if player.get('rating_external_id'):linked[int(player['rating_external_id'])].append((club,player))
for row in d['players']:
 match=linked[row['id']];assert len(match)<=1,('EA identity linked to several players',row['id'])
 row['catalog_id']=match[0][1]['id'] if match else None;row['catalog_club']=match[0][0] if match else None;row['catalog_status']='matched_identity' if match else 'pending_identity_reconciliation'
d['catalog_matched']=sum(r['catalog_id'] is not None for r in d['players']);d['catalog_pending']=250-d['catalog_matched'];p.write_text(json.dumps(d,ensure_ascii=False,indent=2)+'\n',encoding='utf8');print('Top250 EA:',d['catalog_matched'],'identidades conciliadas;',d['catalog_pending'],'pendentes.')
