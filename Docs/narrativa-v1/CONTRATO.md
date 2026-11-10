# Contrato de dados narrativos — schema 1

Implementado em `app/NarrativeCatalog.php`, `NarrativeContext.php` e `NarrativeSelection.php`.
PHP valida arquivos novos ao carregar. Um erro interrompe o carregamento; não é substituído
silenciosamente por texto genérico. Os 214 IDs antigos conservam seu resolvedor.

## Cena e alternativa

Cena: `id` inteiro reservado a partir de 2000; `stage` nos quatro valores existentes;
`module` em T01/T02/P01/J01/J02/J03/F01/F02; `family` no formato `MOD.slug_ascii`;
`tags` não vazias no vocabulário fechado; `gating` objeto; `weight` 1–100;
`rarity` common/rare; `cooldown` 1–20 rodadas; `text` 20–450 caracteres;
`source_type=ficcao_editorial`, `schema_version=1`, `enabled` booleano; quatro `options`.

Cada opção contém código/ação reconhecidos, rótulo, intenção semântica, texto de sucesso,
texto de falha e três mapas de efeitos: normal, falha e falha grave. As quatro intenções,
rótulos, sucessos e falhas dentro da mesma cena precisam ser distintos. Não se permite
atributo desconhecido, perda de habilidade-base nem modificador novo de chance.
Mapas coincidentes entre cenas não são evidência de originalidade editorial.

`exceptional_text` e `severe_failure_text` são opcionais. Neste catálogo novo, excepcional
reutiliza a descrição coerente de sucesso e falha grave reutiliza a de falha; a intensidade
mecânica continua sendo a do motor publicado. Isso consta no CSV de alternativas: não há
quatro mil desfechos excepcionais inéditos ocultos. Não existe fallback genérico para uma
opção nova sem sucesso/falha próprios.

`match_event=true` identifica passe, chute, drible e desarme de campo. Essas opções não
carregam XP técnico próprio: o motor existente registra a ação, o placar e seu XP.
Comunicação e movimentação mantêm os efeitos de estado/habilidade declarados. Conversas
F01/F02 não treinam finalização, passe ou força. Contato familiar não penaliza entrosamento
do elenco; overrides literais resolvem exceções, sem mudar fórmulas globais.

## Fontes dos filtros ativos

| Filtro | Fonte real |
|---|---|
| positions_allow / positions_deny | `g.position`, seis posições; protagonista goleiro não existe |
| starter / bench | Durante partida/pós: `match.lineup`; pré: regra publicada de disponibilidade e banco |
| bench_confirmed | Pré-jogo: rodada múltipla de quatro e disponibilidade; evita que bônus da escolha invalide previsão |
| home_away | Comparação de `match.home` com `g.club` |
| score_state / margin_max | `hg/ag`, convertidos para a perspectiva do clube do personagem |
| minute_min / minute_max | Minuto salvo da partida atual |
| played_minutes_min / max | Entrada/saída durante a partida; `stats.minutes` após encerramento |
| finished | `status=finalizada`, sem antecipar resultado |
| own_goals_min / max | `stats.goals` da partida atual |
| own_cards_min | `stats.cards` da partida atual |
| own_assists/shots/completed/tackles_min | Campo correspondente em `match.stats` |
| injury_risk_min | Indicador `states.risco_lesao`; não é diagnóstico de lesão |
| disciplinary_risk_min | Indicador `states.risco_cartao`; não prova cartão ou expulsão |
| recent_form | Média das últimas três notas salvas de jogos encerrados em que atuou: <6 ruim, >=7 boa |
| exit_reason | Marcadores exatos do log publicado para lesão, desgaste ou segundo cartão; ausência não confirma saída |

Qualquer cena de partida também exige `status=em_andamento`, minuto >= entrada e < saída.
O contexto é calculado uma vez por pool, não uma vez por cena. Cenários de elegibilidade
e testemunha de cada ID ativo estão em `elegibilidade-real.csv` e `elegibilidade-por-id.json`.
Uma testemunha prova alcançabilidade do filtro, não a qualidade literária da cena.

## Contextos explicitamente sem adaptador

`weather`, `rivalry_verified`, `table_context`, `competition_type`, `knockout_round`, `leg`,
`active_benefit`, `pressure`, `squad_morale`, `set_piece`, `match_period`, `aggregate_state`,
`player_balance`, `verified_event`, `future_competition`.

Esses metadados são reservas editoriais **inativas**. Eles não leem o Hub nem criam faltas,
pênaltis, acréscimos, prorrogações, revisão, jogadores expulsos ou classificação. Não basta
retirar o filtro para ativar a cena: será necessário adaptar um evento persistido, validar
o regulamento e sua coerência com o fluxo, e testar sua ausência/presença. Eventos de
substituição/penalidade também exigirão validar o instante de seleção e a disponibilidade
do protagonista, sem furar a regra de quem já saiu de campo.

Os 45 IDs 2755–2799 estão bloqueados. Desses, 2780–2794 dependem de competições internacionais
futuras; os demais dependem de eventos/regulamentos específicos ainda sem adaptador. Essa
distinção impede anunciar 800 cenas novas ativas: são 755 ativas e 45 reservadas.

## Seleção, memória e auditoria

Seleção determinística a partir de seed, temporada/rodada/stage/minuto e competição.
Ela não substitui `random_int` usado nos resultados das ações. Primeiro filtra contexto;
depois escolhe o canal comum/raro, evita os últimos dez IDs quando o pool permite,
aplica cooldown da família e pesa distribuição por módulo e frequência.

Se os dois canais existirem, raro recebe uma porta de 10%; novidade não aumenta essa taxa.
As cenas novas deste lote são comuns; o teste de raridade usa um pool sintético explícito.
Não anunciar 10% de eventos raros reais nesta versão.

Memória guarda quarenta eventos compactos, contagem da temporada atual e contagem acumulada
por ID do catálogo. Auditoria inclui pool, pesos, rolagem de seleção, módulo/família e fallback.
Ela aparece no feedback Normal e nas tentativas simuladas. O override de chance construída
1300 é identificado como `momentum`; sua fórmula não foi alterada.

O snapshot integral da cena selecionada fica no JSON da carreira. Recarga não sorteia outra
cena. Uma revisão de arquivo não reescreve alternativa já apresentada. Respostas antigas
continuam gravadas como estavam. Não há migration nem conversão em massa de saves.
