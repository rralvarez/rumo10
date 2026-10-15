# Pacote de correções e crescimento v1.0 — entrega local

Data: 15/10/2026. Requisito: TXT v1.0 fornecido pelo proprietário, lido integralmente.
Base conferida: jogo `45b0703`, Hub `d2407a6`; READMEs e documentação vigente de publicação/identidade.
Branch local nos dois repositórios: `codex/correcoes-crescimento-v1`. Nenhum push, deploy,
SQL de produção ou alteração de usuário real em produção foi feito nesta rodada.

Commits de implementação do jogo: `c19b83d` (navegação), `dc76120` (motor/troféus),
`7b8f5cf` (calibração e textos), `d5fc788` (integração de direitos/créditos), `afcff80`
(auditoria das decisões automáticas). Hub: `bfd5a03` (direitos, administração, SQL e documentação).
Evidências e relatório são registrados em um commit documental posterior, sem publicar a branch.

## Situação por ambiente

| Entrega | Código | IIS local | Apache/UOL nesta rodada | Publicado |
|---|---|---|---|---|
| Navegação, residência e troféus | Implementado | Verificado | Não testado | Não |
| Partidas, evolução, idade e transferências | Implementado | Fluxos e suites locais | Não testado | Não |
| Direitos, admin, convites e créditos | Implementado | Integração dos dois sites e MySQL | Não testado | Não |
| Migration incremental | Preparada e aplicada localmente | Banco físico `rumohub` | Não importada | Não |
| Cortesia da conta real solicitada | Fluxo disponível | Somente contas sintéticas em QA | Pendente de conta confirmada e autorização | Não concedida |

Roteamento permanece nos front controllers PHP, com `public/` como raiz. `web.config` e
`.htaccess` vigentes continuam protegendo arquivos internos. Não foi necessário reconfigurar
bindings, handlers PHP ou outros sites IIS. Nenhuma alegação deste relatório constitui teste
real do Apache; os testes antigos de produção não validam o pacote atual.

## Navegação, residência e histórico

- `/classificacao` tem CTA GET para `/carreira`, antes de qualquer tipo de tabela. Não executa
  comandos nem repete sorteios. Clube e fase identificam o contexto.
- Menu distingue Carreira da lista Carreiras. Reserva de rodapé acompanha altura real da barra,
  viewport e safe area. Mobile mantém quatro destinos e foco acessível.
- Criação separa residência de país de estreia. Gratuito continua estreando no Brasil; pago e
  cortesia podem usar os mercados disponíveis. Residência não define nacionalidade.
- Novas residências brasileiras validam município/UF; estrangeiras usam cidade/região declaradas,
  sem inventar município brasileiro nem distância. A lista de países usa a cobertura já existente.
- Legados conservam `city/uf` e mostram Cidade informada. Edição voluntária em
  `/jogador/localizacao` confirma residência atual e preserva a referência anterior no snapshot.
  Não pode ser feita no meio da partida, na entrada por roleta ou em resumo imutável aposentado.
- Sala de Troféus em `/carreira/trofeus`; aposentado pode ser consultado pelo link em seu resumo.
  Somente resultados salvos, com clube, competição e temporada do universo; não reconstruímos
  campeões ausentes. Não apresentamos a temporada simulada como um ano civil sincronizado.
- Títulos curtos e campeão principal são deduplicados conforme o formato. Categorias estadual,
  nacional e regional refletem os torneios existentes; não criamos títulos internacionais fictícios.
  Adaptações do calendário continuam identificadas. Premiações individuais ficam **em preparação**:
  não há comparação NPC suficientemente confiável para concedê-las neste pacote.

## Motor: fórmulas e transição de saves

Versão de regras `1.0.0`, modelo de placar `MatchBalance::VERSION=3`, progressão de temporada 2.
Partidas finalizadas não são recalculadas. Partida já iniciada sem modelo novo termina pelo motor
anterior; o novo orçamento começa na próxima preparação pré-jogo. Compatibilidade com modelo 2
também foi preservada para snapshots locais intermediários. Não houve reescrita dos saves reais.

`Probability::chance` mantém a base ponderada, limitada a 5–95%:

```text
p = 50 + 0,65(A−50) + 0,12(C−50) + 0,10(E−50) + 0,10(R−50)
       − 0,55(D−50) − 0,30(O−50)
```

A = atributo efetivo pelos pesos da ação; C/E/R = condição/energia/ritmo;
D = dificuldade; O = força adversária. Pesos completos estão em `app/Rules.php`.
Backend sorteia `random_int(1,10000)` para sucesso e outro roll para intensidade:
15% excepcional dentro dos sucessos, 15% grave dentro das falhas. Custos da tentativa
e efeitos de desfecho são separados; técnicos positivos são XP, não pontos garantidos.
Auditoria persistida contém regras, chance, rolls, classe e antes/depois.
Nos modos automáticos, cada escolha mantém chance, rolls, contexto e efeitos em `pace_report.attempts`,
incluído na auditoria transacional da simulação. Passe comum registra também o roll de conversão.

Força do clube: referência existente, variação sazonal limitada a ±4 da âncora e forma dos
últimos cinco jogos limitada a ±3. Não existe promoção automática para nota da Série A.
Fallback de nota já existente para registro sem overall permanece identificado como estimativa.

```text
diff = clamp(OVR_mandante − OVR_visitante, −60, 60)
xG_casa = clamp(1,35 × exp(diff/35), 0,15, 4)
xG_fora = clamp(1,10 × exp(−diff/35), 0,15, 4)
```

Influência técnica do protagonista: multiplicador limitado a ±12%, proporcional aos minutos
previstos. Não substitui a força do elenco. Gols coletivos usam Poisson por intervalo.
Fonte não fornece uma escalação dinâmica confiável: não há ajuste novo por lesões dos NPCs.

Uma tentativa de gol usa a chance técnica de finalização e uma quota do xG da própria equipe:
`xG × participação_por_posição / 7 × (0,4 + 0,6 × chance_técnica/100)`.
Participação: centroavante 0,60; ponta 0,55; meia 0,48; lateral 0,35; demais 0,30.
O limite usa o orçamento esperado ainda disponível. A expectativa da tentativa é reservada
independentemente de acertar e descontada dos **intervalos coletivos futuros**. Assim, gol
individual compõe o placar previsto, sem somar uma segunda ofensiva nem retirar gol já narrado.
Não reservamos ataque para ações que não podem produzir gol.

Passe em chance clara: sucesso é assistência com gol do companheiro no mesmo placar.
Passe comum: primeiro precisa ser concluído, depois exige conversão do companheiro,
com probabilidade condicional de 12–32% (ajustada pelo passe e limitada pela quota).
A expectativa descontada é `P(passe) × P(conversão | passe)`. Não há assistência sem gol.
Chute bem-sucedido segue sendo gol; o balanceamento atua **antes** do roll.

Normal, Rápido e Expresso executam os mesmos comandos, probabilidades, placar e XP.
Modo fica fixo desde a criação. Estratégias diferentes podem produzir resultados diferentes;
não há bônus oculto por modo. Rápido escolhe foco por jogo, Expresso por temporada,
Normal resolve cada situação. Trocas de contrato continuam manuais.

## Evolução e aposentadoria

XP por atributo: limiar `20 + intdiv(nível,5)`. Multiplicadores por idade: 18–23 = 1,4;
24–26 = 1,2; 27–32 = 1; 33–34 = 0,8; 35+ = 0,6. Sem multiplicador por ritmo.
Teto formativo sobe de 65 aos 18 até 100 aos 27; auge 27–32.
Legados acima do novo teto conservam o valor; não ganham pontos adicionais acima dele.

XP de partida pondera minutos/90, nota (normalização entre 5,8 e 8), gols, assistências e
desarmes, com contribuição limitada a três XP-base para evitar exploração por placares anormais.
Fechamento da temporada exige seis partidas novas e 360 minutos. Qualidade combina nota e
produtividade por 90 minutos, incluindo desarmes para defensores; participação normaliza a 900 min.
Meta de XP = `(100 + 650 × qualidade) × participação × fator_idade × variação(0,85–1,15)`;
deduz XP já ganho na temporada. É um alvo de desempenho, não aumento automático de OVR.
Distribuição prioriza três atributos da posição e contempla os nove atributos.

Título verificado com participação dá XP-base por nível: 1=90, 2=70, 3=55, 4=40,
estadual/regional=25. Títulos registrados como etapas curtas recebem metade; apoio da torcida
e imprensa recebem +2/+2 (curto) ou +4/+3 (principal). Chaves por carreira/temporada/competição/
título impedem repetição. Sem prêmio de artilharia/assistência inventado.

No aniversário: físicos velocidade/resistência/força −1 aos 33–34, −2 aos 35–37, −3 aos
38–39; aumento físico bloqueado desde 33. Visão e posicionamento sem queda automática;
demais técnicos perdem 1/ano somente desde 38. Marcador impede dupla aplicação por aniversário.
Voluntária desde 35, confirmação explícita e fora de jogo; compulsória aos 40 no fluxo de fechamento.
Resumo compacto preserva totais por clube/temporada e troféus. Não ocupa vaga e não pode ser retomado.

## Mercado de transferências

Até oito clubes válidos/distintos. Temporadas 1–3: alvo 6 país + 2 outros países da mesma
confederação; temporada 4+: 3 país + 3 confederação + 2 fora. Prioriza países distintos nos grupos
internacionais. Falta de cobertura/elegibilidade usa alternativa válida e registra a distribuição efetiva.
Tabela explícita em `FootballGeography.php`: Guiana/Suriname na Concacaf, Israel/Turquia/Azerbaijão
na UEFA, Austrália na AFC, Nova Zelândia na OFC. São filiações, não apenas continentes cartográficos.

Perfil usa técnica média, idade/potencial, minutos, nota, gols/assistências/desarmes por posição,
crescimento da temporada, títulos verificáveis e reputação. Exterior exige pelo menos três jogos,
180 minutos e mérito mínimo 0,20. Capacidade relativa `técnica + 10 + 18×mérito + bônus_crescimento
+ 2×potencial`; não é um bloqueio por tier. Preferência inicial de OVR é −10/+10; excepcional pode
ampliar e a temporada 4+ expande gradualmente. Pesos premiam mérito e penalizam distância da faixa;
nenhuma proposta sempre é possível (peso de 3–25 conforme perfil).

Sorteio e candidatos/pesos ficam salvos no PHP com identidade e temporada. Roleta só anima.
Tela distingue posição 01–08 do nível esportivo (Brasil: A/B/C/D/estaduais = 1/2/3/4/5),
competição, país por extenso, cidade existente e diferença real de OVR. Aceitar ou ficar usa
o lock/idempotência da carreira; reload não recria proposta.

## Benefícios por jogo e indicações

Autoridade é o Hub via API autenticada, vinculada ao identificador central. Campo global `plano`
não libera outros produtos. Benefícios: compra, cortesia ou **legado** (preservação, sem alegar compra),
por game_id, vigência, estado, motivo, concedente e trilha. Interface do cliente recebe o plano daquele jogo.
Valores de `rumo10_conta_planos` existentes são migrados somente para Rumo10. Marcador de migração
evita reimportar cache local depois de uma revogação. Contas globais pagas sem vínculo comprovado
ao Rumo10 precisam de revisão administrativa; não ganham licença universal.

Admin Hub `/admin/users`: busca POST por e-mail/nome público/ID, seleção de conta confirmada,
jogos explicitamente marcados, motivo e validade UTC opcional. CSRF, admin ativo, confirmação de
revogação e auditoria. Nenhum checkout nem meio de pagamento implementado.
Revogar/expirar não apaga carreiras: impede novas acima dos limites permitidos.

Gratuito: duas vagas-base simultâneas, até três extras por indicação, teto cinco (ativas + arquivadas).
Pago/cortesia **Rumo10**: dez; não consome créditos. Antigas vagas extras são LEGADO, preservadas
e contadas, sem criar benefício renovável. Resumos aposentados ficam fora da ocupação.

Convite público usa 32 bytes aleatórios, primeiro link da sessão prevalece. Conta precisa ser nova;
cadastro atribui, **primeira carreira persistida** qualifica. Comprovante do jogo e registro da primeira
carreira impedem formulário falso/replay. Índice único impede dois beneficiários para o mesmo convidado.
Cadastro tem limite de 20 por origem em 15 min; acesso a convite 60/IP em 15 min; não confiamos
em cabeçalhos de IP enviados pelo navegador. Autorindicação é recusada.

Crédito segue disponível → reservado → consumido. Lock de conta do jogo e do crédito no Hub,
nonce único e outbox transacional conciliam os dois sites. Falha antes do commit libera reserva;
queda depois do commit conserva prova e repete confirmação, sem criar carreira ou crédito duplicado.
Reservas sem prova podem ser recuperadas após 15 minutos na próxima consulta de status. Provas são
mantidas após excluir carreira para impedir reembolso. Aposentadoria/exclusão nunca devolvem crédito.

Revisão administrativa de fraude bloqueia indicação e créditos ainda disponíveis; conserva créditos
já consumidos e carreiras. Reservas em trânsito são conciliadas pela prova de commit, sem apagamento
automático. Bloqueio da conta impede novas qualificações/reservas. Antifraude mínimo não substitui
verificação de identidade: múltiplas contas com e-mails diferentes ainda exigem revisão humana.

Status público mostra referência opaca ou apelido consentido em `/conta` do Hub. Revogar consentimento
remove o nome na próxima consulta. Não mostramos e-mail do amigo. WhatsApp abre apenas mediante
clique do próprio usuário; não acessamos agenda nem enviamos mensagens automaticamente.
Ledger/comprovantes necessários permanecem mínimos mesmo após exclusão da carreira. Exclusão de
conta/LGPD requer processo futuro para anonimizar vínculos consumidos sem invalidar auditoria.

Indisponibilidade: sem direito central confirmado, novas alocações falham de forma fechada; cache local
não concede licença. Identidade/API indisponível pode impedir acesso temporariamente, sem apagar saves.
Outbox repete pendências em requisições autenticadas (até 20 por requisição); não existe worker novo.
O Hub verifica a prova no **mesmo banco físico compartilhado**. Separar bancos exigirá protocolo novo.

## Arquivos e migrations

| Etapa | Arquivos centrais | Impacto |
|---|---|---|
| A | CurrentLocation, CreationForm, PlayerProfile, Onboarding, EntryCountries, view, game.css/js, router | Labels/fluxos; city/uf compatíveis |
| B | Game, MatchBalance, TeamStrength, Evolution, Progression, Scenes, SceneConsequences, TransferMarket, FootballGeography, outcomes | Novas regras somente para operações futuras |
| C | Trophies, RetiredCareer, router e links do perfil | Campo aditivo em resumo, sem reabrir aposentados |
| D jogo | CareerBenefits, CareerSlots, Identity, RumoHub, Repository, router | Autoridade central, origem e outbox |
| D Hub | HubBenefits, HubReferrals, HubBenefitsUi, HubUsers, HubAccountUi, HubUi, router/css | Benefícios/admin/consentimento e API assinada |
| Schema | Hub `database/005-beneficios.sql`; jogo `database/005-growth.sql` | Apenas CREATE IF NOT EXISTS/INSERT IGNORE |

Novas tabelas centrais: rh_migracoes_negocio, rh_beneficios_jogo, rh_beneficios_audit,
rh_perfis_publicos, rh_convites, rh_indicacoes, rh_primeiras_carreiras, rh_creditos_indicacao,
rh_indicacoes_ledger. Jogo: rumo10_origem_vagas, rumo10_criacoes, rumo10_beneficios_outbox.
Sem DROP/TRUNCATE/UPDATE amplo de planos. Nenhuma tabela de catálogo foi alterada.

Arquivo **combinado para atualização existente**, no repositório do Hub:
`database/production/atualizacao-20261015-crescimento.sql`.
Contém as duas migrations e backfill da primeira carreira antiga, sem conceder indicações retroativas.
Não contém usuários pessoais nem senhas, não cria banco e não executa USE. Selecionar o banco correto
no phpMyAdmin. **Não importar a carga inicial outra vez.**

## Testes reproduzíveis e limites das evidências

Windows local: PHP 8.4.26 com pdo_mysql, mbstring, curl; MySQL do banco `rumohub`.
Playwright com Chrome instalado, contra os dois IIS. Nenhum teste abaixo deve apontar à produção.
Fixtures usam e-mails `.invalid`, segredos aleatórios efêmeros e limpeza em finally.

```powershell
cd C:\inetpub\wwwroot\rumo10
$taskPhp = 'C:\PHP\php-8.4.26\php.exe'
$taskOpts = @('-n','-d','extension_dir=C:\PHP\php-8.4.26\ext','-d','extension=pdo_mysql','-d','extension=mbstring','-d','extension=curl','-d','memory_limit=768M')
& $taskPhp @taskOpts tests/package-navigation.php
& $taskPhp @taskOpts tests/package-engine.php
& $taskPhp @taskOpts tests/package-cohorts.php
& $taskPhp @taskOpts tests/package-seasons.php
& $taskPhp @taskOpts tests/package-benefits-mysql.php
& $taskPhp @taskOpts tests/package-http.php
& $taskPhp @taskOpts tests/package-reference-audit.php
& $taskPhp @taskOpts tests/package-mobile-fixture.php
& 'C:\Users\rodri\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe' tests/package-mobile.cjs
& $taskPhp @taskOpts tests/package-mobile-fixture.php --clean
```

Benefícios/HTTP requerem ambas flags locais habilitadas e Hub acessível. Testes HTTP criam sete
cadastros por execução; respeitar o limite de 20/15min, sem remover limites de usuários reais.
Teste de referência exige o recibo privado do backup anterior; pode substituir por snapshot de referência
feito antes do teste em outra instalação. Testes legados de vagas/aposentadoria precisam das flags
`RUMOHUB_ENABLED=0` e `RUMOHUB_BENEFITS=0` **apenas no processo CLI**, não no .env do IIS.
Os caminhos do Chrome/Playwright são específicos deste computador e devem ser adaptados em outra máquina.

Resultados e métricas sem dados pessoais: [evidencias-crescimento-v1](evidencias-crescimento-v1/).
As 24 suites de regressão abrangem cenas, contextos sociais, finalização, momentum, estatísticas,
XP, ciclo, apresentação, roleta, modos, entrada, calendário internacional, Europa, aposentadoria e vagas.
Também passaram 35 checks MySQL de benefícios e 61 checks HTTP reais, com duas sessões concorrentes
disputando um crédito. Incluem expiração/revogação, plano por cliente, consentimento, falso comprovante,
aposentadoria/exclusão sem reembolso e deduplicação. Layout: 19 checks em 320×640, 390×844,
844×390 e 320×360, zoom 150% e viewport reduzido com campo focado. Isso **não** comprova teclado
físico iOS/Android, leitor de tela real, carga massiva ou falhas reais de rede interrompendo processos.

Monte Carlo: 45 mil confrontos (15 mil por par 40×80, 60×60, 80×40).
Critérios técnicos: azarão 40×80 deve vencer menos de 15%, favorito 80×40 mais de 65%,
preservando vitórias ocasionais do azarão. Não há bloqueio de placar para forçar esses resultados.
Também foram executados dois cenários de 300
miniligas sintéticas cada, oito equipes, fraco 40 contra 70–85, com/sem protagonista: campeão,
top2 e sobrevida top6 observados zero nesta amostra extrema; isso não elimina a chance matemática.
Top2/sobrevida são **proxies**, não uma simulação completa do sistema mundial de promoção/rebaixamento.
Coortes controladas: 480 campanhas, seis posições, crescimento médio aproximado fraca 0, regular 1,
boa 2,18 e excepcional 3,51. São testes da curva; não uma promessa de ganho garantido.
90 temporadas completas legadas de 14 jogos, política equilíbrio igual nos três modos: evolução média
0,11–0,12 e placar reconciliado. Essa política prefere ações seguras e não finalizações: zero gols é
um resultado do roteiro, **não uma amostra representativa da produtividade de um atacante**.
Estatísticas variam a cada execução porque o motor usa random_int.

Antes/depois técnico: diferença exponencial usa divisor 35 em vez de 55 nas novas partidas;
Normal perdeu o bônus arbitrário 2×; ataque pessoal agora consome o orçamento coletivo futuro;
mercado tem metas 6+2/3+3+2. Não há comparação experimental antes/depois com o mesmo seed:
random_int não é semeado. Relatório não reinterpreta a carreira real citada no requisito.

Auditoria: seis tabelas de referência têm SHA-256 idêntico ao backup anterior (clubes,
competições, jogadores, vínculos, EA e seleções). York City-EN: 26 atletas, **um goleiro**;
lacuna relatada para pacote de catálogo futuro. Mínimo continua 24/2, sem completar elenco nesta rodada.

## Backup, implantação e rollback

Backup completo local de rh_* e rumo10_* anterior à migration guardado em
`storage/private/package-v1-*/backup-local.sql.gz`, com recibo/hashes. Configurações anteriores
guardadas em `storage/private/package-v1-env/`. Esses arquivos têm dados privados e não entram no Git,
FTPS ou documentação pública. Código anterior preservado nos commits-base. Não restaurar backup
automaticamente sobre carreiras usadas depois dele.

Produção depende de **nova confirmação do proprietário**. Após aprovação:

1. Confirmar backup do banco físico do cPanel e arquivos privados/saves; registrar horário e tamanho.
   Nome esperado da instalação anterior: `rumoacame144a99e_rumohub`, a conferir no .env privado.
2. Impedir gravações nos dois sites (manutenção). Jogo reconhece `storage/database-migration.lock`;
   Hub deve ficar restrito/manutenção pela hospedagem durante a troca. Conservar acesso privado ao backup.
3. Manter flags de benefícios novas desligadas, importar **somente** o SQL combinado acima no banco
   correto pelo phpMyAdmin e conferir erros/contagem. DDL MySQL tem commits implícitos: uma falha parcial
   exige diagnosticar e reaplicar os INSERT IGNORE/CREATE, não presumir rollback SQL total.
4. Publicar commits coordenados dos dois repositórios por FTPS, preservando .env, segredo dos clientes,
   sessões/storage e imagens. Não colocar código privado dentro da raiz pública. Os workflows de main
   podem disparar publicação automática; não dar push antes de aprovar o rollout.
5. No Hub configurar `HUB_BENEFITS_ENABLED=1`; no jogo `RUMOHUB_BENEFITS=1`, conservando as flags
   existentes de catálogo/identidade e URLs HTTPS. Ativar primeiro o Hub, depois o jogo, sob manutenção.
6. Smoke real autorizado em Apache: login compartilhado, carreira legada, tabela/retorno, admin por jogo,
   convite/cadastro/primeiro save, limite e telas mobile. Remover manutenção só após validar. Não usar
   as fixtures locais em produção nem conceder cortesia a conta real sem identificação e confirmação.

Requisitos cPanel: PHP >=8.2, pdo_mysql/mbstring/curl, HTTPS válido, Apache mod_rewrite e AllowOverride
para regras atuais; MySQL 8+ (ROW_NUMBER do backfill), InnoDB/utf8mb4, as duas aplicações no mesmo banco,
API Hub acessível pelo servidor. Usuário de migration com CREATE/SELECT/INSERT; runtime precisa dos
privilégios de leitura/gravação existentes. Não ampliar acesso remoto MySQL nem abrir novos caminhos públicos.

Rollback de código **antes de uso das novas regras/licenças**: manutenção, restaurar os dois commits
anteriores e .env anterior de forma coordenada; conservar tabelas aditivas. Sem script DROP de rollback.
**Depois** de créditos/cortesias/carreiras novas em uso: não voltar silenciosamente ao plano global antigo,
pois isso perderia autoridade e poderia reabrir créditos. Pausar novas criações e corrigir adiante mantendo
o Hub como autoridade; retorno de código precisa de versão de compatibilidade que respeite os novos direitos.
Restauração integral do backup é procedimento de desastre, com autorização explícita e análise das gravações
posteriores. Regras antigas não devem reprocessar partidas novas nem apagar ledgers.

## Como conferir agora no computador

1. Abrir `http://rumo10.local/carreiras`: vagas, origem e botão de convite; criar jogador escolhe o ritmo
   uma vez. Não é necessário começar outra carreira para continuar uma antiga.
2. Em `http://rumo10.local/classificacao`, usar Voltar à carreira; a etapa fica igual.
3. Perfil `/jogador`: localização legada ou atual, edição voluntária e Sala de Troféus.
4. Testar ações de passe/finalização numa carreira de teste nova; gol/assistência devem aparecer no
   mesmo placar. Saves já no meio do jogo podem terminar pelo modelo anterior.
5. No fim da temporada, conferir mérito, candidatos/número da roleta, nível e país. Resultado pode
   legitimamente ser nenhuma proposta. Aposentadoria libera vaga e guarda resumo/troféus.
6. No Hub `http://rumohub.local/admin/users`, conferir a conta correta antes de conceder. Motivo,
   jogos e validade ficam auditados. Nenhuma senha/admin novo padrão foi criado.

Pendências deliberadas: identificação da conta real de cortesia; autorização/publicação e QA Apache;
teclado/leitor de tela físicos; prêmios individuais auditáveis; catálogo York City; processo LGPD e
antifraude ampliado. Sistema de Continuidade no Drive não foi atualizado nesta execução: não foi
identificado um documento de destino; este relatório registra somente o que foi realizado nos repositórios.
