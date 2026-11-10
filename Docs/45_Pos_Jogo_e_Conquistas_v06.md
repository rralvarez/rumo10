# Pós-jogo e conquistas v0.6 — 16/10/2026

## Entrega e limites

Base confirmada: `c08ab566872bd8c4fef80a1e68f8ad4594f692a8`, branch
`codex/expansao-narrativa-v1`, remoto `https://github.com/rralvarez/rumo10.git`.
A branch de trabalho ainda não existia no remoto na consulta inicial; o main remoto
`c3d19ca7f3e627ace22c03239249ab0c25153741` era ancestral da base local.
A pasta não versionada `Docs/levantamento-2026-10-15/` foi preservada e excluída dos commits.

Implementação local do resumo e do sistema base de conquistas. **Não houve deploy,
merge, FTP, acesso de teste à produção, reinício de serviço ou alteração do Hub.**
O envio autorizado é exclusivamente para a branch de trabalho, depois dos testes e
da revisão de segredos; o SHA remoto é conferido ao concluir o push.
Os dois workflows de publicação foram inspecionados: o gatilho de push está
restrito a `main`. O envio da branch de trabalho não aciona esses deploys.

Commits de implementação:

- `9e5713b`: XP real por partida, snapshots e apresentação nos três modos.
- `465ae83`: catálogo, eventos esportivos, persistência e interface de conquistas.
- O commit seguinte reúne testes, evidências e este documento; consulte `git log -3 --oneline`.

## Pós-jogo

`MatchExperience` começa a medição **depois** da decisão pré-jogo e antes dos lances.
`Evolution::gain` informa ao observador exatamente o inteiro que já retorna; sua
fórmula, multiplicadores, tetos e limiares não foram alterados. Inclui concessões
durante os lances e `Progression::match`, não treino, fim de semana ou XP anual.
O snapshot final é capturado após a recompensa da partida. A persistência usa o
mesmo universo JSON, transação, lock e chave idempotente de `Repository::command`.
Consultar ou renderizar não chama `gain`.

O resumo existente mostra minutos, nota, gols, assistências, finalizações, passes,
desarmes e cartões. Exibe o XP total real, destaca aumentos e recolhe os nove
detalhes de atributos para reduzir scroll no celular: valor antes/depois, XP
concedido, saldo após a partida, limiar e percentual faltante. Uma penalidade direta
de atributo continua distinta de ganho de XP. O inteiro concedido pelo motor pode
ter parte do saldo limitada pelo teto: não se confunde saldo final com XP concedido.

Rápido conserva o snapshot no resumo da partida; Expresso o conserva para cada jogo
em “Ver jogos e destaques”. Nenhum total da temporada é apresentado como XP de
uma partida. Partidas já em andamento na atualização e resumos antigos sem medição
informam ausência de delta confiável. Não houve backfill ou migração em massa de saves.

## Catálogo e fontes

São **50 conquistas / 1.000 G**, com os cinco subtotais canônicos:

| Categoria | G |
|---|---:|
| Carreira | 200 |
| Desempenho | 250 |
| Títulos | 250 |
| Social | 150 |
| Especiais | 150 |

**33 estão ativas, somando 620 G. As outras 17, somando 380 G, são futuras.**
Não é possível completar 1.000 G nesta versão. Não há paywall nas condições;
os bloqueios são funcionais, não comerciais. A conta gratuita pode validar carreiras,
excluí-las e criar outras sem perder o contador de carreiras válidas ou seus G.
Completar o catálogo depende de futuras fontes verificáveis, fora desta fase.

Matriz completa por ID, condição, G, estado, segredo e motivo:
[`pos-jogo-v06/conquistas.csv`](pos-jogo-v06/conquistas.csv).
Contagem verificável: [`catalogo.json`](pos-jogo-v06/catalogo.json).

| IDs futuros | Motivo |
|---|---|
| TIT03, TIT05 | Premiações individuais ainda não implementadas |
| TIT06 | Calendário com três títulos distintos por temporada não validado; condição projetada, concessão bloqueada |
| TIT08 | Competição continental sem adaptador auditável |
| TIT09, ESP10 | Não há Bola de Ouro implementada; nove alegações/eventos desconhecidos não concedem GOAT |
| SOC01/03/05/07/09 | API sem amizades recíprocas aceitas e ativas |
| SOC02/04/06/08/10 | A consulta atual `HubReferrals::status` chama recuperação e pode criar convite; não é estritamente somente leitura |
| ESP03 | Não há eleição persistida de destaque da partida |

Não se consultou essa API de indicações para implementar conquistas nem se escreveu
em `rh_*`. Referrals não foram contados como amizades. Nenhuma raridade foi inventada.
Secretas não desbloqueadas ocultam nome/descrição na interface, inclusive futuras.

Fontes ativas:

- Carreira válida: partida oficial concluída, com medição nova e minutos > 0.
  O ID de carreira é o identificador persistido do Rumo10; não resetar AUTO_INCREMENT.
- Gols, assistências e desarmes: estatísticas finais do personagem; totais mantidos
  separadamente por carreira. Uma conta não combina gols de personagens distintos.
- Temporadas, clubes e títulos: eventos de encerramento com participação comprovada.
  Títulos vêm de `Trophies::collect`, não do texto narrativo. Turnos comuns não contam
  como campeonato completo; títulos curtos nomeados seguem a distinção já existente
  no motor. Edição = temporada + competição + título; cada edição conta uma vez.
- ESP01/04/05/08: testemunhas estruturadas de placar, minuto, autoria, classe de chute
  e ações válidas da posse. Nada é extraído por busca textual no rádio.
- ESP06: promoção com continuidade reconhecida e partida com minutos no clube,
  divisão superior e temporada imediatamente seguinte. Não libera destinos bloqueados.
- ESP07: calendário concluído, sem derrota do clube e participação em ao menos metade
  dos jogos das competições do personagem. ESP09 usa países dos títulos preservados.

## XP bônus

**Todos os bônus de conquistas ficam em zero nesta entrega.** Não foi implementada
ativação de bônus positivos: os candidatos 10/20/30/10 XP continuam proposta de
balanceamento. `xp_bonus` é separado de G no catálogo/ledger e dos ganhos naturais
no resumo, evitando prometer ou duplicar recompensa. `Rules::xpThreshold(55)=31`;
aos 18 anos, 10 XP-base virariam 14 XP com o multiplicador natural. Portanto 30
XP-base poderiam ultrapassar um nível: não se considerou o bônus insignificante
sem calibração. Não foi alterada a economia de XP publicada.

## Persistência, instalação e recuperação

SQL: `database/migrations/006-achievements.sql`.
Cria apenas `rumo10_conquistas`, `rumo10_conquistas_carreiras` e
`rumo10_conquistas_eventos`, com chaves únicas e índices lógicos pelo ID global.
Sem FK em `rh_*`, sem novo login e sem CASCADE da carreira para reputação.
Cada unlock guarda carreira e evento fonte, G, XP bônus separado e notificação.
Eventos e contadores mínimos permanecem após excluir a carreira para impedir farming.
Não armazenam nome/e-mail do jogador ou de amigos. Exclusão integral de conta,
caso implementada futuramente, deverá incluir esses registros identificáveis por ID.

**Aplicado somente no banco `rumohub` em loopback local.** Antes do DDL foi gerado
backup consistente das 31 tabelas `rumo10_*`: 52.098.140 bytes,
SHA-256 `c76f2710e2f7d32f11324f39a074f5bf75da981d993b09a5f216e83995a3c6f8`.
Dump e recibo estão em `storage/private/achievements-v06`, ignorados pelo Git,
fora de `public/`. Não foi ensaiada restauração integral desse dump.
A flag `RUMO10_ACHIEVEMENTS=1` foi ativada somente no `.env` local, com cópia privada
anterior. Nenhuma conquista retroativa foi concedida na instalação.

Para outra instalação local:

```powershell
php -d extension=pdo_mysql bin/achievements-local.php --backup-local
php -d extension=pdo_mysql bin/achievements-local.php --install-local
# Depois, definir RUMO10_ACHIEVEMENTS=1 no .env local.
```

O utilitário recusa outro banco/host, valida hash e idade do backup e não muda a flag.
Não executar esses comandos na UOL. Uma publicação futura requer aprovação própria,
backup coordenado e aplicação do SQL incremental no banco físico correto do cPanel.
Não reimportar carga inicial. PHP 8.2+, PDO MySQL e MySQL com JSON/InnoDB; IIS e Apache
continuam usando os mesmos front controller e regras existentes, sem nova extensão web.

**Rollback reversível sem apagar dados:** desativar `RUMO10_ACHIEVEMENTS`, voltar ao
código anterior e manter as três tabelas. Reativação recupera o histórico. Não há
script de DROP nem downgrade destrutivo: desfazer fisicamente o schema não é
necessário para voltar ao código v0.4. Restaurar dump deve ser uma ação separada,
validada e autorizada, para não sobrescrever partidas posteriores ao backup.

## Testes e evidências

`regressao/suites.json`: **24 suítes aprovadas** (as 23 anteriores e a suíte adicional
de países de entrada). Inclui 1.014 cenas preservadas, elegibilidade, probabilidades,
XP, lifecycle, continuidade, mercado, roletas e fronteiras mecânicas.
`incrementais.json` registra os testes adicionais; contagens podem variar conforme
partidas e fases sorteadas. Última execução da suíte ampliada de modos: 667 checks.

| Teste | Resultado |
|---|---|
| `tests/post-match-v06.php` | 215 checks; seis posições, três modos, execução real de Rápido/Expresso, deltas, level-up e legado |
| `tests/achievements-v06.php` | 96 checks; catálogo, 15 temporadas, promoção, placar, escopo de carreira e fontes inativas |
| `tests/achievements-mysql-v06.php --local-rollback` | 22 checks; unlocks simultâneos, duplicação, duas carreiras, isolamento, exclusão, restauração, toast, XP atômico e lock concorrente |
| `tests/persistence-v04.php --local-rollback` | 17 checks PDO; saves e replay preservados |
| `tests/v06-boundary.php` | 11 fronteiras: XP-base, força, chances, continuidade, identidade e benefícios intactos |
| `tests/v06-mobile.cjs` | 18 checks, 320/390/1280 px; seis screenshots, sem overflow, reduced-motion |
| `tests/ux-v04.cjs` | 21 checks; carregamento, HTTP/timeout/network error, clique único, retry e roletas |
| `tests/http-smoke.ps1` | IIS local: 15 caminhos públicos, método e CSRF |

O teste antigo `play-modes` pressupunha no máximo duas chances construídas por jogo.
A v0.4 já substituíra essa quota por cinco janelas, cada uma com duas construções e
uma conclusão. Atualizou-se apenas o teste: até três ações por janela e 18 decisões
totais, incluindo treino/pré-jogo/fim de semana. O motor de posse não foi modificado.
O primeiro launcher usou Hub habilitado e o teste antigo falhou; o harness final usa
Hub desabilitado e a expectativa temporal correta. Todos os resultados finais são verdes.

Os testes MySQL usam fixtures locais em transação integralmente revertida. A concorrência
é comprovada por segunda conexão com lock timeout, não por dois POSTs simultâneos de
navegador nem por simulação de crash. Não houve login em contas reais para testar este
pacote. Os testes de autorização cobrem vínculo local/ID global e isolamento das consultas;
não equivalem a um teste ponta a ponta do login central com usuário real.

As telas foram renderizadas com PHP real e Chrome headless com transporte de memória.
Foram inspecionadas visualmente; detalhes de XP recolhidos reduzem scroll. Não houve
ensaio humano com leitor de tela; foram usados HTML semântico, headings, details,
progress acessível e anúncio discreto. **Apache/HTTPS/produção não foram testados.**

Reprodução (PHP local isolado):

```powershell
$env:RUMOHUB_ENABLED='0'; $env:RUMOHUB_BENEFITS='0'
& C:\PHP\php-8.4.26\php.exe -n -d extension_dir=C:\PHP\php-8.4.26\ext -d extension=mbstring tests/post-match-v06.php
& C:\PHP\php-8.4.26\php.exe -n -d extension_dir=C:\PHP\php-8.4.26\ext -d extension=mbstring tests/achievements-v06.php
& C:\PHP\php-8.4.26\php.exe -n -d extension_dir=C:\PHP\php-8.4.26\ext -d extension=mbstring -d extension=pdo_mysql tests/achievements-mysql-v06.php --local-rollback
$env:V06_EVIDENCE_DIR='C:/inetpub/wwwroot/rumo10/Docs/pos-jogo-v06/regressao'
python bin/test-narrative.py
node tests/v06-mobile.cjs
```

## Coorte complementar de equilíbrio

`tests/collective-v06.php`: **6.000 partidas completas**, 1.000 em cada combinação,
personagem 45/85, seis grupos independentes com RNG seguro e política Protagonismo.
Sem banco. Proporção de vitórias com Wilson 95%; médias de gols com aproximação
normal e razão de gols com delta logarítmico pareado, incluindo covariância.
Dados e intervalos completos em `equilibrio-coletivo.json`.

| Personagem | Próprio × rival | Gols próprios/jogo | Sofridos/jogo | Vitórias | IC 95% vitórias |
|---:|---|---:|---:|---:|---|
| 45 | 40 × 80 | 0,417 | 3,492 | 2,7% | 1,9–3,9% |
| 45 | 60 × 60 | 1,270 | 1,082 | 41,5% | 38,5–44,6% |
| 45 | 80 × 40 | 4,161 | 0,353 | 96,0% | 94,6–97,0% |
| 85 | 40 × 80 | 0,589 | 3,400 | 3,5% | 2,5–4,8% |
| 85 | 60 × 60 | 1,534 | 1,140 | 46,1% | 43,0–49,2% |
| 85 | 80 × 40 | 4,534 | 0,359 | 97,1% | 95,9–98,0% |

A dominância extrema persiste na amostra maior; é uma observação relevante de
balanceamento, não prova de defeito matemático. Não se alterou a fórmula. A coorte
usa o personagem como mandante no calendário piloto; há vantagem de mando e uma
política específica, portanto não estima equilíbrio universal de estratégias/mandos.
A proporção de gols individuais não superou o placar do time. Não houve calibração
nova de escalação, chances de gol, XP ou ratings-base.

## Como experimentar localmente

Entre em `http://rumo10.local`, use sua conta existente e conclua uma **nova** partida.
No Normal, confira “Apito final”; nos automáticos, o relatório e os detalhes de cada
jogo. Abra `http://rumo10.local/conquistas` ou “Sala de Conquistas” na carreira.
O primeiro jogo oficial concluído com minutos valida a carreira. Recarregar não
repete XP/G; notificações são marcadas como apresentadas atomicamente. Se uma
resposta for perdida após essa marcação, o toast pode não aparecer, mas o desbloqueio
continua visível na sala. Aposentar/excluir conserva G e a validação da carreira.

Backfill de registros anteriores permanece **não executado**: exigirá inventário
de fontes por partida/edição e execução idempotente auditada, mediante autorização.
A mundialização e os 23 vínculos superiores bloqueados continuam fora do escopo.
