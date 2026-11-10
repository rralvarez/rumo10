# Estabilização v0.4 — entrega local, 16/10/2026

Continuação do checkpoint 43, a partir de `bf70ef6`, na branch
`codex/expansao-narrativa-v1`. Não houve reset, push, deploy, acesso à produção,
edição do Hub ou mudança de schema. Mundialização permanece suspensa.

Commits de implementação: **8bb3cc9** (motor/continuidade/mercado/persistência/testes),
**b85aefe** (interface/espera/testes de navegador) e **a613316**
(bloqueio preventivo de novas estreias), mais **a24e000** (comparação de todos
os vínculos estadual/nacional dos candidatos), **0d1af63** (overall comparável
na permanência) e **3132c31** (ofertas internacionais parciais sem complementos
inventados). Este documento e as evidências
são registrados em commit de documentação separado.

Os JSONs/TXT de evidência citados sem pasta estão em `Docs/estabilizacao-v04/`.
A relação completa de arquivos está em `estabilizacao-v04/arquivos-alterados.txt`.

## 1. Posses e gols

- A primeira ação construtiva bem-sucedida mantém o minuto e oferece continuação
  explícita; a segunda abre a chance clara no mesmo lance. O override transitório
  1301 não integra nem renumera o catálogo de 1.014 cenas.
- A posse guarda minuto, placar e contador limitado a duas ações. Falha, conclusão,
  gol, recuo para reorganizar, intervalo, saída do campo, mudança de placar ou outro
  segmento invalidam o benefício. Ação social não constrói uma chance ofensiva.
- Um chute após a primeira ação recebe `P + (100-P)*0,10`. Após duas ações, a própria
  classe clara já remunera a construção: bônus adicional zero. Nenhum overall-base
  é modificado. Uma chance clara antiga, já apresentada sem testemunha de posse,
  continua consumível uma vez; um contador parcial antigo não cria chance fantasma.
- Fórmula de finalização do checkpoint foi mantida. A parcela coletiva continua
  descontando a expectativa pessoal registrada, sem reaplicar um teto de criação
  sobre uma finalização que já existe. Não foi necessário recalibrar MatchBalance.

Teste novo: **480 partidas completas**, personagem com habilidades 45 ou 85,
  forças 40×80, 60×60 e 80×40, política protagonismo. Nenhuma atribuição de gol ou
  assistência excedeu o placar. Com habilidade 45, o time fraco marcou 0,50 gol/jogo
  e venceu 2,5%; o forte marcou 3,66 e venceu 93,75%. Com habilidade 85, foram 0,625
  e 4,3125 gols/jogo, respectivamente. São amostras aleatórias pequenas, não taxas
  oficiais nem garantia de monotonicidade de resultados entre amostras independentes.

Evidências: `estabilizacao-v04/equilibrio-coletivo.json`, `tests/possession-v04.php`
e `tests/match-momentum.php` (19 e 2.425 verificações na execução registrada).

## 2. Amazonas e auditoria dos vínculos

A lacuna era a ausência de `am-a1` e do par de acesso no catálogo/motor, não uma
denominação incorreta da Série B. O cache local do Hub também não tinha essa A.
Não foi demonstrado qual rotina histórica de importação originou a ausência.

Adicionado complemento **somente no Rumo10**, em `database/local-competition-fixes.json`:
Amazonense Série A, oito participantes, quatro clubes que faltavam no jogo, sem
inventar cidades, ratings publicados, cores ou escudos. Os quatro recebem o fallback
já existente de elenco fictício (24 jogadores, dois goleiros), força estimada e
identificação visual genérica. O Hub continua sendo a fonte dos demais registros.

Fontes primárias consultadas em 16/10/2026:

- [Tabela e participantes da A 2026, FAF](https://fafamazonas.com.br/site/noticia/faf-divulga-tabela-completa-de-jogos-do-barezao-2026/).
- [Regulamento da A 2026, FAF](https://fafamazonas.com.br/site/arquivos/download/arqeditor/REG.pdf),
  conferido visualmente: dois turnos, quatro jogos cruzados no primeiro e três
  dentro da chave no segundo; líderes avançam às semifinais; segundos enfrentam
  terceiros em playoff, com vantagem de empate. No returno, semifinal cruza as
  chaves. Campeões de turno decidem o título geral; campeão dos dois dispensa final.
  Rebaixa-se um clube, protegendo os campeões dos turnos. Páginas físicas 8–14.
- [Acesso único da B 2026, FAF](https://www.fafamazonas.com.br/site/noticia/serie-b-do-barezao-faf-e-clubes-alinham-ultimos-ajustes-em-iii-conselho-tecnico/).
- [Participantes da B 2026, FAF](https://www.fafamazonas.com.br/site/noticia/faf-divulga-clubes-da-serie-b-do-barezao/).

O regulamento 2026 é reutilizado nas edições futuras **simuladas**; não se afirma
que participantes ou regras de 2027 em diante reproduzam o calendário oficial.
Microcritérios de empate e mando ainda usam a infraestrutura geral do simulador;
não é reprodução integral de logística, estádios e disciplina da FAF.

O par A/B conserva oito/sete clubes nas temporadas testadas. Janela antiga com B
já encerrada, acesso persistido e A completamente ausente pode reconciliar apenas
a A faltante, uma vez, sem reexecutar a B nem alterar snapshots/históricos.
Esse complemento fica identificado em `continuity_reconciliations`. Caso já exista
uma A salva ou dados contraditórios, a rotina não a sobrescreve.

Dos **24 vínculos superiores ausentes**, um foi resolvido e **23 permanecem
bloqueados para novas propostas e novas estreias**. Países de estreia sem
continuidade também ficam indisponíveis. A rejeição acontece antes da criação
ou consumo de vaga; residência e nacionalidade não são alteradas. Ofertas de
estreia já apresentadas a legados continuam consumíveis. Saves nesses campeonatos continuam jogáveis
no teto implementado, com aviso e sem promoção fictícia. Também são bloqueados
vínculos inferiores entre países ainda não implementados, duplicatas e ciclos.
Não houve importação massiva nem renomeação das divisões.

A auditoria cobre **131 competições dos JSONs do jogo**. O cache do Hub contém
154 competições; seu conteúdo foi apenas lido, não atualizado nem certificado
por uma chamada à API. Não confundir esses dois universos.

Evidências: `auditoria-24-vinculos.json`, `competicoes-continuidade-atual.json`,
`continuidade.json`. `cinco-temporadas-oficiais.json` registra **cinco temporadas
consecutivas pelo motor completo**, acesso B→A, mercado, permanência, calendário
e histórico; placares do clube da fixture foram controlados para garantir o acesso.
Os demais jogos foram simulados em memória. Esse teste não usa um save real.

## 3. Avaliação e sete cenários de mercado

`SeasonEvaluation` soma apenas partidas finalizadas com participação real:

```
taxa90 = total * 90 / minutos
confiança_amostral = minutos / (minutos + 900)
produção = gols90*G + assistências90*A + min(2,desarmes90)*D*0,35
contexto = média por minutos de clamp(1 + (OVR_rival-OVR_próprio)/200, 0,85, 1,15)
escore = clamp((nota_por_minutos-6)/2 + produção*0,65*contexto, -0,5, 1,5)
         * confiança_amostral
```

Pesos `(G,A,D)`: centroavante `(0,70;0,28;0,02)`, ponta `(0,50;0,45;0,05)`,
meia `(0,30;0,55;0,15)`, volante `(0,15;0,35;0,50)`, lateral `(0,20;0,45;0,35)`,
zagueiro `(0,15;0,35;0,50)`. São coeficientes de jogo provisórios, não benchmarks
estatísticos oficiais por liga. Sem snapshot de força adversária, contexto neutro,
explicitamente auditado. Finalizações, conversão, passes e acertos ficam registrados;
não se inventam passes decisivos, interceptações, duelos, defesas ou gols evitados.
Não existe posição de goleiro jogável nesta rodada.

Histórico disponível: atual 70%, anterior 20%, retrasada 10%, normalizados quando
faltam snapshots. Temporadas futuras são ignoradas. Históricos antigos sem avaliação
não recebem estatísticas reconstruídas. A avaliação e seus componentes são salvos
na janela e no resumo da temporada.

`TransferInterest` separa mérito, compatibilidade dos clubes e sorteio do cenário.
Para `m=clamp(escore_ponderado,0,1,5)`, pesos brutos:

| Cenário | Peso antes dos gates | Ofertas |
|---|---|---|
| Sem interesse | max(3, round(65−35m)) | 0 |
| Uma proposta | 30+round(10m) | 1 |
| Duas propostas | 10+round(18m) | 2 distintas |
| Mercado nacional | 5+round(25m) | até 3: mesma, superior, inferior |
| Mercado continental | 2+round(15m) | até 3: mesma, superior, equivalente regional |
| Mercado internacional | 1+round(8m) | até 3: superior nacional, superior regional, Europa |
| Destaque internacional | 1+round(5m) | até 3, exigindo salto de patamar sustentável |

As três últimas dependem de amostra, mérito e destinos. Elite exige overall ≥75,
confiança amostral ≥0,60, mérito ≥0,85; ofertas elite exigem clube ≥70 e diferença
≥8. Continental exige confiança ≥0,20 e mérito ≥0,20; internacional ≥0,40/0,45,
além de pelo menos um destino estrangeiro elegível (regional superior ou europeu).
Ausências nas demais preferências reduzem o número de ofertas; não são preenchidas
com clubes inventados. A categoria elite também exige ao menos um estrangeiro
que passe pelo seu filtro de patamar. Pesos inelegíveis viram zero,
com razão auditável. Região utiliza as confederações operacionais já existentes
em `FootballGeography`, inclusive filiações transcontinentais.

O filtro de capacidade já existente usa a nova avaliação; apenas clubes cadastrados,
com continuidade válida atual e seguinte, entram. Divisões são comparadas dentro
da mesma pirâmide (nacional/estadual), sem chamar A3 paulista de quinta divisão oficial.
Equivalência estrangeira é aproximada por diferença de overall de até dez pontos;
isso é regra da simulação, não equivalência oficial entre ligas.

O PHP usa `random_int`; RNG controlado é opção exclusiva de chamadas de teste.
UI desenha os pesos efetivos e para no resultado salvo. Nunca transfere automaticamente.
Faltando destinos para alguma preferência, reduz a quantidade; não inventa oferta.
Permanecer está sempre disponível. Janelas antigas mantêm sua oferta selecionada
e o formato anterior de aceitação. Novas janelas usam schema 2 e escolha explícita.

Teste: **100 fechamentos isolados completos**, aceitação/permanência, zero a três
ofertas e recarga; sete cenários forçados por RNG controlado sobre clubes existentes
produziram `0,1,2,3,3,3,3`. Também foram verificados 5 gols/450 min = 1,00 gol/90;
5/2.400 = 0,1875; e regularização de 1 gol/10 min. Evidência: `mercado.json`.

## 4. Projeções de utilização

Cada oferta e permanência salva calendário mínimo conservador, participações,
percentual e minutos, concorrência da posição e premissas. Não presume finais.
Amazonense A conta sete jogos classificatórios garantidos, além de eventuais jogos
não garantidos que não entram na estimativa mínima. Aviso de calendário curto: <12.

Para `d=OVR_personagem−melhor_concorrente_da_posição`:

```
P(relacionado) = 0,65 + 0,33*sigmoid(d/12)
P(titular|relacionado) = sigmoid((d+2)/9)
P(entrar|reserva relacionado) = 0,80
fração = P(relacionado) * [P(titular)*78 + (1−P(titular))*0,80*23] / 90
minutos = round(jogos_do_clube * 90 * percentual_exibido / 100)
```

Perfil por fração: <25% reserva eventual, <45% reserva utilizado, <65% disputa,
demais titular provável. Trata-se de modelo conservador com uma vaga de referência;
não modela ainda formação específica, vagas múltiplas ou envelhecimento futuro
individual dos concorrentes. Usa o elenco de referência disponível, sintético no
fallback, sem prometer escalação. Melhoria do atleta aumenta a projeção; melhoria
do concorrente reduz. Premissas ficam recolhidas em “Como estimamos” no celular.

## 5. Roleta inicial, espera e persistência

- Lista antes do giro: todos os quatro perfis, percentuais, efeitos e valores
  antes/depois. Pesos **25/30/20/25** e efeitos foram preservados: variação máxima
  de dez pontos em estado e oito em habilidade, sem tornar a estreia dominante.
  10.000 sorteios conferidos, tolerância de 2,5 pontos percentuais por resultado.
- Antes/depois efetivamente aplicado fica salvo; não é fabricado para personagens
  antigos. Clamps são considerados. Fatia única de mercado sem candidatos agora
  tem SVG de círculo completo, em vez de arco degenerado.
- Decisões, avanço, simulação, clubes e janela/virada de temporada têm status
  imediato e acessível. Alternativas ficam bloqueadas durante o POST. Não há
  percentual de progresso fictício. Preferência por movimento reduzido preservada.
- Fetch confirma a resposta do servidor. Erro HTTP/rede ou **45 segundos** de
  espera liberam controles e oferecem retry com mesmo token/escolha, ou consulta
  do estado salvo. Timeout do navegador **não garante cancelamento no servidor**.
- Locks de conta, versão e chave idempotente do Repository continuam autoritativos.
  Não foi criada fila externa nem alterada a API do Hub.

Gargalo reproduzido: a persistência legada regravava partidas normalizadas apesar
de já usar snapshot. Saves modernos agora seguem o caminho JSON autoritativo já
utilizado pelo jogo oficial. Dados relacionais antigos permanecem preservados;
não se apagam tabelas nem históricos. Metadata SQL da carreira segue atualizada.

Medição PDO local, fixture pequena e amostra individual comparável:

| Comando | Consultas antes → depois | Tempo antes → depois |
|---|---|---|
| Cena legada | 213 → 35 | 90,82 → 12,72 ms |
| Cena 2000 | 213 → 35 | 85,14 → 7,21 ms |
| Ofertas | 214 → 35 | 119,18 → 12,04 ms |
| Próxima temporada | 215 → 35 | 117,69 → 7,84 ms |

Não extrapolar esses tempos para universos grandes, usuários reais ou produção.
Também há 12 amostras por operação PHP pura (escolha, chute, rodada, Rápido,
mercado, temporada e renderização) em `desempenho-antes.json`/`desempenho-atual.json`.
O primeiro benchmark antigo tinha extração sem assets e não servia para comparar
renderização; foi preservado como `desempenho-antes-incompleto.json`. A referência
foi completada com os assets originais de `a5bb59e` e somente o benchmark leve foi
repetido para gerar os dois arquivos comparáveis atuais.
O fechamento do mundo completo continua síncrono; as cinco temporadas oficiais
de teste, com cerca de 23 mil jogos de mundo por temporada, levaram ~10,7 s ao todo.

`RUMO10_TIMING=1` habilita medição opt-in de SQL, load, engine, seleção, save e render,
com `Server-Timing`; não registra SQL, parâmetros ou dados pessoais. Tempos desses
componentes se sobrepõem (SQL está dentro de load/save), não devem ser somados.
O benchmark puro não inclui MySQL nem rede. A configuração local não foi alterada.

## 6. Testes e preservação

- Matriz executada nesta continuação: **23 suítes PHP aprovadas**, incluindo
  **89.821 verificações narrativas**, **162 temporadas/2.268 partidas** e
  **1.350.000 sorteios/135 cenários** de execução. Evidência: `regressao/suites.json`.
  A matriz pesada não foi repetida após cada ajuste de UI/instrumentação; as suites
  afetadas foram executadas novamente em `verificacao-final.json`, com lint.
- Coorte narrativa: 77 gols, 236 assistências, 443 finalizações e 200.155 minutos,
  nos eventos do personagem. **Não são os totais de gols das partidas**. A política
  equilíbrio de outra coorte segue sem gols pessoais nessa amostra; não se declara
  equivalência estatística absoluta dos modos. As fórmulas e regras são compartilhadas.
- Complementares: 480 partidas de força/posse; cinco temporadas pelo mundo oficial;
  cinco temporadas isoladas A/B; 100 fechamentos de mercado; 10 mil perfis iniciais.
- **17 verificações PDO/MySQL local**, só em fixture transacional revertida: cena
  legada e 2000, retries, tokens conflitantes, mercado/virada únicos, metadados, rejeição de ingresso órfão sem consumir carreira e
  contenção real de lock entre duas conexões. Commits/rollbacks internos da fixture
  são suprimidos e rollback externo é obrigatório. Não são dois POSTs HTTP reais
  simultâneos nem teste de recuperação após queda de processo. Nenhum dado da
  fixture foi confirmado; contagens originais preservadas.
- Adicionalmente, `tests/entry-countries.php` passou em 1.060 verificações de
  catálogo/planos/entradas válidas e bloqueios; `package-navigation.php` passou.
  “74 países inventariados” não significa 74 países liberados para novas carreiras.
- Chrome headless: **33 verificações narrativas mobile** e **21 novas verificações**
  de roletas/espera/erro/retry/timeout, 320 e 390 px e desktop 1280 px. HTML PHP real,
  transporte em memória. Capturas e JSON em `regressao/mobile/`. Não é celular físico.
- IIS `rumo10.local`: 15 rotas públicas/arquivos e dois bloqueios (CSRF/método)
  aprovados em `tests/http-smoke.ps1`. Não foi realizado fluxo autenticado pelo IIS
  com conta real ou nova conta do Hub. Apache, HTTPS produtivo e API remota não
  foram testados nesta rodada, nem era autorizada publicação.
- Fronteira de 97 arquivos e contratos narrativos verificada; **214 legadas + 800
  novas**, 755 novas ativas e 45 inativas mantidas. Fontes editoriais, gates,
  snapshots e seleção narrativa não foram reescritos. Ratings-base, XP/aging e
  tabelas/identidade/benefícios do Hub não foram editados. Não há migration SQL.

## 7. Reprodução e limites

No PowerShell, a partir da raiz do repositório:

```powershell
$env:RUMOHUB_ENABLED='0'; $env:RUMOHUB_BENEFITS='0'
$php='C:\PHP\php-8.4.26\php.exe'
$opts=@('-n','-d','extension_dir=C:\PHP\php-8.4.26\ext','-d','extension=mbstring','-d','memory_limit=768M')
foreach($suite in @('possession-v04','continuity-v04','market-v04','initial-profile-v04','collective-v04','official-seasons-v04','mechanics-boundary')) {
  & $php @opts "tests/$suite.php"
  if($LASTEXITCODE){throw "Falha em $suite"}
}
& $php @opts '-d' 'extension=pdo_mysql' tests/persistence-v04.php --local-rollback
node tests/ux-v04.cjs
$env:NARRATIVE_EVIDENCE_DIR="$PWD\Docs\estabilizacao-v04\regressao"
node tests/narrative-mobile.cjs
& tests/http-smoke.ps1
```

O teste PDO recusa host não local ou banco diferente de `rumohub`. As suítes puras
nunca conectam ao banco/Hub. Chrome/Node/Playwright usam os caminhos locais já
documentados nos scripts. Ao terminar, remover essas variáveis do terminal antes
de executar comandos normais de integração. A matriz completa continua disponível
em `bin/test-narrative.py`; não é necessário repeti-la para verificar uma alteração
apenas documental. Não reexecutar o capturador da baseline por cima do arquivo atual.

Para experimentar: abrir **http://rumo10.local/carreira**; recarregar para obter os
assets novos. Personagem em partida preserva a etapa; novas sequências têm continuação
no mesmo minuto. Ao encerrar a temporada, consultar o mercado, comparar as projeções
e escolher oferta ou permanência. Para ver o painel inicial, criar carreira dentro
das vagas disponíveis; as suítes usam fixtures e não exigem apagar carreiras existentes.

Pendências conhecidas: 23 acessos estaduais ainda sem superior jogável; microregras
esportivas e previsão de utilização simplificadas conforme descrito acima; perfis
sem métricas antigas usam histórico disponível; fechamento global continua síncrono.
Regras novas só são gravadas no save ao executar um comando; nenhum save real foi
migrado em lote. A publicação e a mundialização dependem de aprovação posterior.

Retomada: revisar esses limites e a validação local com o proprietário. Não reabrir
expansão mundial nem alterar Hub/produção automaticamente. A pasta preexistente
`Docs/levantamento-2026-10-15/` ficou intacta e fora dos commits deste trabalho.
