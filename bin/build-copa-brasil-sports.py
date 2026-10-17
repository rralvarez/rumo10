"""Sports reference transcribed from CBF REC/RNC and the 27 January draw notice.
No historic scores, new ratings or shared Hub data. Run from any directory.
"""
from pathlib import Path
import json
ROOT=Path(__file__).resolve().parents[1]
annex=json.loads((ROOT/'database/competition-references/br-copa-annex-2026.json').read_text(encoding='utf-8'))
pairs=[['Ji-Paraná-RO','FC Pantanal-MS'],['Independente-AP','Ivinhema-MS'],['Madureira-RJ','Baré-RR'],['Gama-DF','Monte Roraima-RR'],['Galvez-AC','Guaporé-RO'],['Velo Clube-SP','Vasco-AC'],['Primavera-SP','Araguaína-TO'],['Sampaio Corrêa-RJ','Desportiva Ferroviária-ES'],['Betim-MG','Piauí-PI'],['Tirol-CE','América-SE'],['Santa Catarina-SC','IAPE-MA'],['Porto-BA','Serra Branca-PB'],['Maguary-PE','Laguna-RN'],['Bragantino-PA','Primavera-MT']]
pots={
'A':['Fortaleza-CE','Juventude-RS','Atlético-GO','América-MG','Ceará-CE','Cuiabá-MT','Goiás-GO','Sport-PE','CRB-AL','Vila Nova-GO','Operário-PR'],
'B':['Avaí-SC','Novorizontino-SP','CSA-AL','Amazonas-AM','Tombense-MG','Volta Redonda-RJ','Guarani-SP','ABC-RN','Londrina-PR','Retrô-PE','Ypiranga-RS'],
'C':['Athletic-MG','Botafogo-PB','Maringá-PR','São Bernardo FC-SP','Caxias-RS','Figueirense-SC','América-RN','Manaus-AM','Altos-PI','Nova Iguaçu-RJ','Águia de Marabá-PA'],
'D':['Anápolis-GO','Sousa-PB','Tocantinópolis-TO','ASA-AL','Porto Velho-RO','Ceilândia-DF','Portuguesa-RJ','Trem-AP','Juazeirense-BA','Itabaiana-SE','Maranhão-MA'],
'E':['Santa Cruz-PE','Tuna Luso-PA','Maracanã-CE','Cianorte-PR','Operário-MS','Jacuipense-BA','Capital-DF','Fluminense-PI','São Luiz-RS','Operário-MT','Manauara-AM'],
'F':['Azuriz-PR','Rio Branco-ES','Boavista-RJ','GAS-RR','Guarany de Bagé-RS','Mixto-MT','Portuguesa-SP','Atlético de Alagoinhas-BA','Lagarto-SE','Capital-TO','Independência-AC'],
'G':['Joinville-SC','Uberlândia-MG','Imperatriz-MA','Penedense-AL','Porto Vitória-ES','Castanhal-PA','Nacional-AM','Oratório-AP']+[f'w{i}' for i in range(1,4)],
'H':[f'w{i}' for i in range(4,15)]}
rank={}
ranked={1:'Flamengo-RJ',2:'Corinthians-SP',3:'Palmeiras-SP',4:'Atlético-MG',5:'São Paulo-SP',6:'Fluminense-RJ',7:'Botafogo-RJ',8:'Athletico-PR',9:'Bahia-BA',10:'Vasco-RJ',11:'Cruzeiro-MG',12:'Grêmio-RS',13:'Fortaleza-CE',14:'Internacional-RS',15:'Red Bull Bragantino-SP',16:'Santos-SP',17:'Juventude-RS',18:'Atlético-GO',19:'América-MG',20:'Vitória-BA',21:'Ceará-CE',22:'Cuiabá-MT',23:'Goiás-GO',24:'Coritiba-PR',25:'Sport-PE',26:'CRB-AL',28:'Mirassol-SP',29:'Vila Nova-GO',30:'Operário-PR',31:'Avaí-SC',32:'Novorizontino-SP',33:'Chapecoense-SC',35:'Paysandu-PA',37:'Remo-PA',38:'Ponte Preta-SP',39:'CSA-AL',40:'Amazonas-AM',42:'Tombense-MG',43:'Volta Redonda-RJ',45:'Guarani-SP',46:'ABC-RN',48:'Londrina-PR',49:'Retrô-PE',50:'Ypiranga-RS',51:'Athletic-MG',52:'Botafogo-PB',53:'Confiança-SE',56:'Maringá-PR',57:'São Bernardo FC-SP',58:'Caxias-RS',59:'Figueirense-SC',62:'América-RN',64:'Manaus-AM',65:'Altos-PI',66:'Nova Iguaçu-RJ',67:'Águia de Marabá-PA',69:'Anápolis-GO',70:'Sousa-PB',71:'Tocantinópolis-TO',74:'ASA-AL',75:'Porto Velho-RO',77:'Ceilândia-DF',78:'Portuguesa-RJ',80:'Trem-AP',83:'Juazeirense-BA',84:'Itabaiana-SE',85:'Maranhão-MA',86:'Santa Cruz-PE',87:'Tuna Luso-PA',89:'Maracanã-CE',90:'Cianorte-PR',93:'Operário-MS',94:'Jacuipense-BA',95:'Capital-DF',97:'Fluminense-PI',98:'São Luiz-RS',99:'Barra-SC',102:'Operário-MT',103:'Manauara-AM',111:'Azuriz-PR',112:'Rio Branco-ES',113:'Boavista-RJ',116:'Guarany de Bagé-RS',119:'Mixto-MT',121:'Portuguesa-SP',123:'Atlético de Alagoinhas-BA',127:'Lagarto-SE',130:'Capital-TO',132:'Independência-AC',136:'Joinville-SC',143:'Uberlândia-MG',144:'Imperatriz-MA',170:'Castanhal-PA',185:'Nacional-AM',198:'Oratório-AP'}
rank={club:r for r,club in ranked.items()};rank.update({'GAS-RR':113,'Penedense-AL':161,'Porto Vitória-ES':161})
# Asterisk denotes home slot, not the upper side in the printed bracket.
homes2=[0,1,0,0,1,0,1,1,0,1,0,0,1,0,1,1,0,1,0,0,1,0,0,1,0,0,1,0,1,1,0,1,0,0,1,1,0,0,1,0,0,0,1,0]
homes3=[0,0,1,1]*6
homes3[11]=homes3[23]=0  # Criterion-2 clubs host groups 70 and 82.
third=[];cursor=15
for block in range(2):
    third.append([f'c{4-block*2}',f'w{cursor}']);cursor+=1
    for i in range(10):third.append([f'w{cursor}',f'w{cursor+1}']);cursor+=2
    third.append([f'c{3-block*2}',f'w{cursor}']);cursor+=1
assert cursor==59 and len(third)==24 and len(homes2)==44
assert len(rank)==98 and len(set(sum(pairs,[])+[x for p in pots.values() for x in p if not x.startswith('w')]))==102
missing=['Vasco-AC','Independente-AP','Atlético de Alagoinhas-BA','Desportiva Ferroviária-ES','Porto Vitória-ES','FC Pantanal-MS','Bragantino-PA','Castanhal-PA','Boavista-RJ','Ji-Paraná-RO','América-SE','Capital-TO']
longs=['Associação Desportiva Vasco da Gama','Independente Esporte Clube','Alagoinhas Atlético Clube','Associação Desportiva Ferroviária Vale do Rio Doce','Porto Vitória Futebol Clube Ltda - Me','Futebol Clube Pantanal SAF','Bragantino Clube do Pará','Castanhal Esporte Clube','Boavista Sport Club S.A.F.','Ji-Paraná Futebol Clube','América Futebol Clube','Ricanato Futebol Clube (Capital Futebol Clube)']
reference_clubs={club:dict(name=club,official_name=long,country='BR',uf=club[-2:],competitions=['br-copa'],game_only=True,rating_origin='existing_game_fallback_not_official_rating',source=annex['source'],source_location='Anexo A PDF pp.19-21') for club,long in zip(missing,longs)}
r=dict(id='cbf-copa-brasil-sports-2026-v1',edition=2026,rec=annex['source'],draw_source='https://www.cbf.com.br/futebol-brasileiro/noticias/selecao-base-masculina/a/sorteio-da-copa-do-brasil-sera-nesta-quarta-feira-confira-os-criterios',rnc_source='https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/RNC_Ranking_Nacional_dos_Clubes_2026_27e24418e7.pdf',consulted_on='2026-10-17',initial_pairs=pairs,pots=pots,rank=rank,home_slots_phase2=homes2,phase3_slots=third,home_slots_phase3=homes3,home_slots_phase4=[1,0]*6,reference_clubs=reference_clubs,simulation=['draw outcomes randomized and persisted; not historical draw','calendar dates simulated','final listed home is administrative only; venue CBF not certified','BID/discipline/registration limits not legally certified'],future_entries='blocked_128_requires_new_regionals_and_state_results')
(ROOT/'database/competition-references/br-copa-sports-2026.json').write_text(json.dumps(r,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
