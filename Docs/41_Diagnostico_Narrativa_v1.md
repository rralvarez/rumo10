# Diagnóstico inicial — expansão narrativa

Base local conferida: `dbda309`; remoto jogo `c3d19ca`; Hub local/remoto `3b11f28`, limpo.
Branch exclusiva local: `codex/expansao-narrativa-v1`. O levantamento preexistente não foi alterado.
TXT obrigatório e documentos 38/40 lidos. Nenhum acesso à UOL ou banco realizado nesta etapa.
Backup de código anterior em `storage/private/narrative-v1/code-before.zip` (não versionado).

## Inventário efetivo anterior à implementação

| Stage | Cenas | Alternativas |
|---|---:|---:|
| treino | 53 | 212 |
| pre_jogo | 55 | 220 |
| partida | 53 | 212 |
| fim_semana | 53 | 212 |
| Total | 214 | 856 |

São 200 JSON, 12 legadas e duas cenas do banco em PHP. IDs 10/11 desativados explicitamente;
212 potencialmente selecionáveis antes dos demais filtros. Nenhum sucesso/falha está vazio
**após** resolver presets e arquivos de correção. Isso não significa qualidade suficiente.

Auditoria por ID: [auditoria-214.csv](narrativa-v1/auditoria-214.csv).
Texto bruto e alternativas efetivas: [baseline-resolved.json](narrativa-v1/baseline-resolved.json).
Repetições exatas, com IDs e opções: [repeticoes-baseline.json](narrativa-v1/repeticoes-baseline.json).
Há 69 grupos de texto repetido: 23 de sucesso, 28 de falha e 18 de alternativas.
As classes excepcional/grave em geral compartilham o texto normal, embora efeitos possam diferir.

## Coerência e cobertura

- 1100–1124 são excluídas para zagueiro/volante; 1125–1139 para meia/ponta/centroavante.
  Nenhuma posição dispõe de 60 cenas de partida, pois o catálogo inteiro desse stage só tem 53.
- Reservas têm somente 6/1200/1201 no pré-jogo. Em campo exige status/minuto entre entrada/saída.
- ID 8 usa minuto 35 como substituto de falta adversária; o estado não comprova a falta.
  ID 1143 usa risco para presumir fisgada real; 1149 usa minuto para presumir substituição.
- IDs 1/1003/1020/1055/1078 e outros descrevem ambiente sem um campo de clima do motor.
  Contratos de patrocinador, rivalidade, escalação adversária e consultas de agente também não
  possuem fontes verificadas. As ocorrências por ID estão no CSV e no summary JSON.
- Presets têm respostas compartilhadas e atribuem vetores por código; não significam efeitos
  exclusivos por cena. A auditoria exporta os efeitos após todos os overrides.
- `fieldDecision` reconhece ações com bola por faixas de IDs. Cenas novas precisam de metadado
  explícito para passar pelo mesmo placar/xG; só cadastrar JSON novo causaria incoerência.
- `active_situation` guarda ID/chave, não o texto completo. Cenas antigas devem continuar resolvendo
  como antes; cenas novas precisam guardar snapshot editorial para suportar revisão futura.
- Seleção é determinística por seed/chave, evita dez IDs recentes e não considera famílias nem
  frequência sazonal. Na escassez, volta ao pool completo sem explicar o fallback.

## Atribuição provisória dos 214 (não equivale a aprovação editorial)

| Módulo | Antigas atribuídas | Meta |
|---|---:|---:|
| T01 | 20 | 100 |
| T02 | 33 | 100 |
| P01 | 55 | 100 |
| J01 | 25 | 100 |
| J02 | 15 | 100 |
| J03 | 13 | 100 |
| F01 | 22 | 100 |
| F02 | 31 | 100 |

Não aprovar automaticamente os 214 para atingir 800. O bloqueio editorial concreto está nos
textos compartilhados, eventos sem fonte e cobertura por posição demonstrados acima.
Preservar IDs/respostas antigas e desativar futuras seleções incoerentes sem retcon de cenas ativas.

## Etapas e limites da implementação local

1. Contrato versionado, vocabulário fechado, contextos fail-closed e validador de dados.
2. Lote editorial original de 100 cenas distribuídas nos oito módulos; revisão por cenário,
   escolhas e respostas completas, sem preenchimento de contagem por permutação de frases.
3. Carregador e seleção por ID/família/frequência, snapshot e auditoria nos três modos.
4. Regressão pura, elegibilidade real, 500 rodadas e QA visual com fixtures em memória.

Arquivos afetados previstos: `app/Scenes.php`, classes narrativas novas, integração mínima em
`app/Game.php` e auditoria em `app/PlayMode.php`, JSONs por módulo, binários de validação, testes/docs.
Sem migrations previstas: metadados e memória narrativa cabem no estado JSON já existente.
Arcos ficam em proposta controlada, sem bônus novo ou gatilhos clínicos inventados.
Força, xG, XP, evolução, transferências, Hub, usuários e créditos permanecem fora das alterações.

Não há autorização para push/deploy. Lotes incompletos não serão publicados.
