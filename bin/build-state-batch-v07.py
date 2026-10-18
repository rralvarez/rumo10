"""Reproducible transcription of identified federation rules/fixtures, never scores."""
import json
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
PI='https://ffp-pi.com.br/assets/uploads/178043156618.pdf'
PI_TABLE='https://www.ffp-pi.com.br/assets/uploads/176306443317.pdf'
RO='https://drive.google.com/file/d/1IiXRE3n6xqyn0Y0Rs-IMcyxg0DxW3be9/view'
RO_TABLE='https://drive.google.com/file/d/1-V8mvdYmrGmSDNqF_AcWc4TDARHxum7z/view'
RO_NOTICE='https://drive.google.com/file/d/10bIvsGAow8D1IDqpb4BLaAXcDmKHQ54I/view'
pi=['Altos-PI','Atlético Piauiense-PI','Parnahyba-PI','Oeirense-PI','Teresina-PI','Piauí-PI','Fluminense-PI','Corisabbá-PI']
ro=['Porto Velho-RO','Genus-RO','Barcelona-RO','Rondoniense-RO','Ji-Paraná-RO','Guaporé-RO','União Cacoalense-RO']
existing_ids={'Porto Velho-RO','Guaporé-RO','Ji-Paraná-RO','Parnahyba-PI','Altos-PI','Piauí-PI','Fluminense-PI'}
# Indices into the stable participant arrays; rounds and home/away from federation tables.
pi_pairs=[[(3,6),(5,0),(2,1),(7,4)],[(6,5),(3,1),(4,0),(7,2)],[(4,3),(0,6),(1,7),(5,2)],[(3,7),(1,5),(6,4),(2,0)],[(0,1),(7,6),(4,5),(2,3)],[(1,4),(0,7),(5,3),(6,2)],[(3,0),(7,5),(2,4),(6,1)]]
ro_pairs=[[(0,3),(6,2),(4,5)],[(5,6),(2,0),(3,1)],[(1,6),(4,0),(3,2)],[(6,3),(2,4),(1,5)],[(2,5),(4,3),(0,1)],[(5,0),(2,1),(6,4)],[(1,4),(3,5),(6,0)],[(2,6),(5,4),(3,0)],[(6,5),(0,2),(1,3)],[(6,1),(2,3),(0,4)],[(3,6),(5,1),(4,2)],[(5,2),(1,0),(3,4)],[(1,2),(0,5),(4,6)],[(4,1),(5,3),(0,6)]]

def fixtures(teams,rounds):
    out=[[r,teams[a],teams[b]] for r,pairs in enumerate(rounds,1) for a,b in pairs]
    assert len({tuple(sorted([h,a])) for _,h,a in out})==len(teams)*(len(teams)-1)//2
    return out

def definition(uf,teams,rec,table,pairs,ret,relegation,ties,ranking):
    cid=uf.lower()+'-a1'
    engine={'version':'configured-state-v1','tiebreakers':ties,'relegation':relegation,'promotion':0,'final_ranking':ranking,'stages':[
        {'name':'liga','type':'league','return':ret,'fixtures':fixtures(teams,pairs)},
        {'name':'semifinal','type':'knockout','selection':'initial','pairs':[[0,3],[1,2]],'home_basis':'initial','legs':2,'tie_rule':'penalties'},
        {'name':'final','type':'knockout','selection':'winners','pairs':[[0,1]],'home_basis':'campaign','legs':2,'tie_rule':'penalties'}]}
    refs={t:{'name':t,'country':'BR','uf':uf,'competitions':[cid],'game_only':True,'source':rec,'rating_origin':'existing_game_fallback_not_official_rating'} for t in teams if t not in existing_ids}
    return {'id':cid,'name':'Piauiense Série A' if uf=='PI' else 'Rondoniense Série A','edition':2026,'country':'BR','uf':uf,'family':'state','scope':'state','pyramid':uf.lower(),'tier':5,'format':'configured_state','teams':teams,'groups':[teams],'test_fixture':True,'verified_sources':[rec,table],'reference_clubs':refs,'rules':{'access':0,'relegation':relegation,'note':'Datas simuladas; classificações externas pendentes. Inscrição/BID não verificados.'},'state_engine':engine}

defs={
 'pi-a1':definition('PI',pi,PI,PI_TABLE,pi_pairs,False,2,['points','wins','gd','gf','red','yellow','lots'],'campaign'),
 'ro-a1':definition('RO',ro,RO,RO_TABLE,ro_pairs,True,1,['points','wins','h2h_gd_two','gd','gf','red','yellow','lots'],'elimination')}
defs['ro-a1']['verified_sources'].append(RO_NOTICE)
defs['ro-a1']['rules']['limitations']=['REC art.14 singular clarified by art.15 and official fixtures47/48: two final legs','positions3/4 ordering not certified for external qualification; only finalists may supply D candidates','round dates explicitly simulated; official home pairs preserved, notice round date inversions not claimed as live calendar']
defs['pi-a1']['rules']['limitations']=['REC art.11 contains editorial references to2025; heading, participants and paragraph3 expressly2026/2027','BID, licensing, monetary obligations and administrative substitutions not simulated']
paths={}
for uf,teams,source,location,prefix in [('PI',pi,PI,'arts.3,7-11,17,21; PDF pp.1,3-4,6,8; final ranking and two D2027 slots',8),('RO',ro,RO,'arts.3,5-6,11-17, AnexoA; PDF pp.2-3,5-6,11; two finalists indicated to D2027',2)]:
    paths[uf]={'id':uf.lower()+'-state-d2027-v1','status':'sports_path_with_declared_limits','competition':uf.lower()+'-a1','source_edition':2026,'target_edition':2027,'quota':2,'source':source,'location':location,'participants':teams,'roll_down':'verified ranking prefix only; skip upper divisions and already selected national merits','ranking_prefix':prefix,'selective_required':False,'eligibility':'sports candidate, not BID/licensing/national entry','consulted_on':'2026-10-17','future_rule':'later editions require separate pinned simulated rules'}
result={'id':'state-batch-pi-ro2026-v1','public_runtime_enabled':False,'sources':{'ro_listing':'https://ffer.com.br/Publicacao.aspx?id=178275','ro_table_notice':'https://www.ffer.com.br/Publicacao.aspx?id=645189','pi_listing':'https://ffp-pi.com.br/conteudo/?q=92&sc=9','pi_table_notice':'https://www.ffp-pi.com.br/noticias/v/1433'},'definitions':defs,'paths':paths}
target=ROOT/'database/competition-references/br-state-batch-2026-v1.json'
target.write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print('PI28+6=34; RO42+6=48; no scores or national entries imported.')
