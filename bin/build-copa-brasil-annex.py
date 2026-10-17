"""Transcription of REC 2026 Annex A, PDF pages 19-21 (1-based).

Only reconciles existing IDs. Never adds clubs or changes the public catalogue.
Run PHP tests/copa-brasil-reference-v07.php to verify against the runtime catalogue.
"""
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SOURCE = 'https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Copa_do_Brasil_2026_66989a5426.pdf'
STATE_ROWS = {
    'AC': ['Galvez', 'Independência', 'Vasco'],
    'AL': ['ASA', 'CRB', 'CSA', 'Penedense'],
    'AM': ['Amazonas', 'Manauara', 'Manaus', 'Nacional'],
    'AP': ['Independente', 'Oratório', 'Trem'],
    'BA': ['Atlético', 'Jacuipense', 'Juazeirense', 'Porto'],
    'CE': ['Ceará', 'Fortaleza', 'Maracanã', 'Tirol'],
    'DF': ['Capital', 'Ceilândia', 'Gama'],
    'ES': ['Desportiva', 'Porto Vitória', 'Rio Branco'],
    'GO': ['Anápolis', 'Atlético', 'Goiás', 'Vila Nova'],
    'MA': ['IAPE', 'Imperatriz', 'Maranhão'],
    'MG': ['América', 'Athletic', 'Betim', 'Tombense', 'Uberlândia'],
    'MS': ['FC Pantanal', 'Ivinhema', 'Operário'],
    'MT': ['Cuiabá', 'Mixto', 'Operário VG', 'Primavera'],
    'PA': ['Águia de Marabá', 'Bragantino', 'Castanhal', 'Tuna Luso'],
    'PB': ['Botafogo', 'Serra Branca', 'Sousa'],
    'PE': ['Maguary', 'Retrô', 'Santa Cruz', 'Sport'],
    'PI': ['Altos', 'Fluminense', 'Piauí'],
    'PR': ['Azuriz', 'Cianorte', 'Londrina', 'Maringá', 'Operário'],
    'RJ': ['Boavista', 'Madureira', 'Nova Iguaçu', 'Portuguesa', 'Sampaio Corrêa', 'Volta Redonda'],
    'RN': ['ABC', 'América', 'Laguna'],
    'RO': ['Guaporé', 'Ji-Paraná', 'Porto Velho'],
    'RR': ['Baré', 'GAS', 'Monte Roraima'],
    'RS': ['Caxias', 'Guarany de Bagé', 'Juventude', 'São Luiz', 'Ypiranga'],
    'SC': ['Avaí', 'Figueirense', 'Joinville', 'Santa Catarina'],
    'SE': ['América', 'Itabaiana', 'Lagarto'],
    'SP': ['Guarani', 'Novorizontino', 'Portuguesa', 'Primavera', 'São Bernardo FC', 'Velo Clube'],
    'TO': ['Araguaína', 'Capital', 'Tocantinópolis'],
}
# Explicit identity reconciliation; UF is part of the key, never fuzzy matching.
ALIASES = {'Atlético-BA': 'Atlético de Alagoinhas-BA', 'Desportiva-ES': 'Desportiva Ferroviária-ES', 'Operário VG-MT': 'Operário-MT'}
base = json.loads((ROOT/'database/competition-references/br-cup-obligations-2026.json').read_text(encoding='utf-8'))
rows = []
for index, club in enumerate(base['confirmed_copa_do_brasil'][:20]):
    rows.append(dict(annex_key=f'criterion1:{club}', official_label=club.rsplit('-',1)[0], uf=club[-2:], club=club, criterion=1, entry_phase=5, location='Anexo A PDF p.19', origin='Brasileiro Série A'))
for club, origin in [('Paysandu-PA','Copa Verde'),('Ponte Preta-SP','Brasileiro Série C'),('Confiança-SE','Copa do Nordeste'),('Barra-SC','Brasileiro Série D')]:
    rows.append(dict(annex_key=f'criterion2:{club}', official_label=club.rsplit('-',1)[0], uf=club[-2:], club=club, criterion=2, entry_phase=3, location='Anexo A PDF p.19', origin=origin))
for uf, names in STATE_ROWS.items():
    for name in names:
        key = f'{name}-{uf}'
        rows.append(dict(annex_key=f'criterion3:{key}', official_label=name, uf=uf, club=ALIASES.get(key,key), criterion=3, entry_phase=None, location='Anexo A PDF pp.19-21', origin='Estadual; posição/seletiva individual ainda não certificada'))
for row in rows:
    row.update(source=SOURCE, source_edition=2026, consulted_on='2026-10-17', evidence='annex_participant_only_not_bid_or_full_qualification', replacement_verified=False)
assert len(rows) == 126 and len({r['club'] for r in rows}) == 126
document = dict(id='cbf-copa-brasil-annex-2026-v1', edition=2026, source=SOURCE, participants=rows,
    closed_annex=True, runtime_enabled=False, first_phase_selection='pending_verified_RNC_and_draw',
    future_capacity=128, future_edition=2027, future_entry_policy='blocked_regional_and_state_results_required',
    amendment_review='stadium_amendment_reported_but_primary_notice_not_reconciled; no_stadium_certification')
(ROOT/'database/competition-references/br-copa-annex-2026.json').write_text(json.dumps(document,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
