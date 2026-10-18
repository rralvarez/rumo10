"""Reproduce Entrega 57 source inventory. Private downloads are never published.

Does not install a competition, query a database or grant a qualification.
Run after downloading the listed primary documents to storage/qa/state57.
"""
from pathlib import Path
import csv, hashlib, json

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'Docs/mundializacao-v07/expansao-estadual'
ROWS = [
    dict(uf='BA', edition=2026, participants=10, expected_games=48,
         source='https://fbf.org.br/documentos/noticia/202511032238reg_seriea_2026_atualizado%20em%20031125.pdf',
         file='ba-rec.pdf', locator='arts. 3, 6, 8-18',
         format='Liga única; semifinal e final únicas; mando final por campanha acumulada',
         blockers='Desempate por confronto direto múltiplo ainda não contratado; identidade/IDs e tabela não integrados; campanha final e ordenação de semifinalistas não certificadas no motor'),
    dict(uf='ES', edition=2026, participants=10, expected_games=59,
         source='https://futebolcapixaba.com/site/wp-content/uploads/2025/12/Regulamento-Serie-A-2026.pdf',
         file='es-rec.pdf', locator='arts. 8-15',
         format='Liga; quartas com vantagem; semifinais fixas; final; mata-matas ida/volta',
         blockers='Tabela inicial/IDs não conciliados; confronto direto por pontos e prefixo de classificação não contratados; segunda vaga estadual depende de outra competição'),
    dict(uf='PE', edition=2026, participants=8, expected_games=38,
         source='https://www.fpf-pe.com.br/assets/uploads/176499150263.pdf?v=17696371365',
         file='pe-rec.pdf', locator='art. 11 e Anexo A',
         format='Liga; dois byes; playoff; semifinal com chave fixa; final; sem descenso em 2026',
         blockers='Tabela inicial/IDs não conciliados; ranking de eliminados por classificação inicial ainda não contratado; não aplicar descenso genérico'),
    dict(uf='RN', edition=2026, participants=8, expected_games=38,
         source='https://www.fnf.org.br/files/REC_Campeonato_Potiguar_2026.pdf',
         file='rn-rec.pdf', locator='arts. 11-16 e Anexo I',
         format='Liga; dois byes; playoff; semifinal reordenada; final',
         blockers='Tabela inicial/IDs não conciliados; campanha exclui playoff em critérios específicos; confirmação do caminho nacional e ranking final necessária'),
    dict(uf='DF', edition=2026, participants=10, expected_games=50,
         source='https://ffdf.com.br/assets/uploads/176245464195.pdf?v=17652869932',
         file='df-rec.pdf', locator='arts. 17 e 22; capítulos do sistema de disputa',
         format='Liga; semifinal ida/volta com vantagem; final única em local designado',
         blockers='Desempate final pode exigir jogo extra por decisão federativa; não substituir por sorteio; mando neutro, tabela/IDs e ranking dos semifinalistas pendentes'),
]
OUT.mkdir(parents=True, exist_ok=True)
for row in ROWS:
    path = ROOT / 'storage/qa/state57' / row['file']
    if not path.is_file() or not path.read_bytes().startswith(b'%PDF'):
        raise SystemExit('Fonte local PDF ausente: ' + row['file'])
    row.update(sha256=hashlib.sha256(path.read_bytes()).hexdigest(),
               consulted='2026-10-18', status='researched_not_executable',
               new_proven_candidates=0, installed=False)
execution = OUT / 'ba/sports.json'
proof = json.loads(execution.read_text(encoding='utf-8')) if execution.exists() else None
if proof:
    ba = next(row for row in ROWS if row['uf'] == 'BA')
    ba.update(status='local_fixture_executable_public_disabled', installed=True,
              new_proven_candidates=proof['rows']['BA']['candidate_count'],
              blockers='Renovação estadual sem Série B recíproca; calendário obrigatório futuro incompleto; BID/licenciamento não verificados; runtime público desativado')
payload = dict(schema=1, base='b31f14531d139107b2ff20b07a8afce501551b1f',
               status='partial_checkpoint_not_delivery_accepted', sources=ROWS,
               excluded_source=dict(file='ac-rec.pdf', reason='electoral_regulation_not_football_rules'),
               newly_executable_federations=1 if proof else 0,
               new_proven_candidates=proof['rows']['BA']['candidate_count'] if proof else 0,
               national_renewals=0,
               note='Expected game counts are format arithmetic; ba/sports.json is separate execution evidence. No source hash by itself certifies identity, eligibility or a result.')
(OUT / 'sources.json').write_text(json.dumps(payload, ensure_ascii=False, indent=2)+'\n', encoding='utf-8')
with (OUT / 'sources.csv').open('w', encoding='utf-8-sig', newline='') as f:
    writer=csv.DictWriter(f, fieldnames=list(ROWS[0]));writer.writeheader();writer.writerows(ROWS)
if proof:
    matrix=[]
    for uf, stats in proof['admission']['federations'].items():
        receipt=proof['rows'].get(uf,{})
        matrix.append(dict(uf=uf,quota=stats['reference_2026_quota'],
                           proven_candidates=stats['accepted'],pending=stats['missing'],
                           competition=receipt.get('competition','not_certified'),
                           source=receipt.get('source',''),status=stats['state'],entries=0,
                           career_id=proof['admission']['career_id']))
    assert len(matrix)==27 and sum(row['quota'] for row in matrix)==64
    with (OUT/'ba/federations.csv').open('w',encoding='utf-8-sig',newline='') as f:
        writer=csv.DictWriter(f,fieldnames=list(matrix[0]));writer.writeheader();writer.writerows(matrix)
    (OUT/'ba/federations.json').write_text(json.dumps(dict(evidence='sports.json',same_career=True,rows=matrix,
        state_pending=sum(row['pending'] for row in matrix),residual_pending=4,national_entries=0),ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print('5 regulamentos inventariados; Bahia executável somente no piloto local.' if proof else '5 regulamentos inventariados; nenhum resultado local exportado.')
