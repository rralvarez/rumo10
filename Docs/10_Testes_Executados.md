# Evidências de execução — 12/10/2026

Ambiente acessado de fato: Windows local, IIS 10 no site rumo10, PHP 8.4.26 FastCGI/CLI, MySQL local com banco ecohub. Apache/produção/HTTPS não foram acessados e **não foram testados**.

## Executados

- Leitura do briefing v0.2 e inspeção do repositório remoto: vazio, branch main. Pasta local inicial continha somente web.config; checkout posteriormente instalado nela.
- Confirmação do binding `*:80:rumo10.local`, pool rumo10, módulo URL Rewrite e PHP 8.4.26 via FastCGI. Raiz pública ajustada para public/ pelo usuário em PowerShell administrativo.
- Conexão com configuração local de UcronIA, sem imprimir credenciais. MySQL inicialmente retornou 1049 para ecohub. Banco criado após autorização explícita; inventário inicial zero tabelas externas. Migration 001 e seed executados; segunda instalação idempotente concluída, sem dados dos demais projetos alterados.
- Lint de todos os 15 arquivos PHP finais, sem erro sintático. Warnings antigos de php_gd2/php_xmlrpc existem no php.ini global; CLI de verificação executada com `-n` e somente extensões necessárias, sem alterar configuração compartilhada.
- `tests/run.php`: aproximadamente 28 mil verificações, 30 temporadas completas (cinco por cada uma das seis posições). Faixas, limites/monotonicidade, 20 mil sorteios empíricos, 56 jogos/14 rodadas, pares/mando, todos os times uma vez por rodada, tabela, stats de campo, handicap/proposta não repetíveis, 12×4 opções e 21 clubes/HEX.
- `tests/run.php --mysql`: carreira descartável completou 14 rodadas com reload após cada comando, replay sem duplicação, token com payload alterado rejeitado, versão obsoleta rejeitada, proposta e transferência persistidas. Segunda conta preservada enquanto a primeira avançava. Cleanup limitado às contas descartáveis dessa execução.
- `tests/http-smoke.ps1 -BaseUrl http://rumo10.local`: GET /, /login, /clubes e três assets retornam 200; API sem login 401; rota inexistente e /.env, /.git/config, /app/Config, /database/migrations, /tests/run.php e /README.md retornam 404; POST sem CSRF 403; PUT /login 405.
- `tests/http.php http://rumo10.local`: 23 verificações de cadastro/login, cookie HttpOnly/SameSite, criação, handicap, replay via POST real, treino/pré-jogo/partida/fim de semana, rodada 2, classificação, logout, novo login com estado preservado, CSRF inválido sem alteração. Conta gerada e removida pelo próprio teste; não há credencial demo pública.
- Servidor PHP local em loopback 127.0.0.1:8010: mesma lista de rotas/proteções passou. Isso não substitui teste Apache.
- Inspeção visual no navegador in-app: landing em largura desktop 1280×900 e override mobile 390×844, com leitura DOM de largura efetiva 375/scrollWidth 375, sem overflow horizontal na landing. Overrides restaurados. Não houve teste em dispositivo físico nem inspeção de todas as etapas visuais.
- `.env` confirmado ignorado; storage de sessões/logs ignorado, apenas .gitkeep versionado. Assets Bootstrap locais sem dependência de CDN durante execução.

## Problemas observados e corrigidos

O retorno de comando em memória tinha ordenação/tipos diferentes do estado recarregado: corrigido para retornar estado canônico lido dentro da transação. S9 verificava só a última linha do log, que era placar; agora detecta gol adversário no segmento atual. Tier final não oferece academia acima do teto.

IIS inicialmente retornou 500.0/0x80070003 por monitorChangesTo e depois 0x80070005 por execução negada. Handler do jogo foi associado à instância PHP 8.4 alternativa já registrada, pelo usuário com `-RepairFastCgi`. ACLs confirmavam apenas UcronIAPool na pasta PHP; foi acrescentado RX a rumo10, sem remover ACLs existentes. Sessões/logs passaram a storage fora de public, com M limitado para pool/IUSR (impersonação FastCGI local). O diagnóstico detalhado temporário do IIS foi removido; respostas finais usam PassThrough e mensagens da aplicação sem stack trace.

## Pendentes / limites

Apache/mod_rewrite/AllowOverride e HTTPS/Secure/HSTS de produção, concorrência simultânea em múltiplos processos, pentest, carga e dispositivos móveis reais não foram testados. NPCs/posse/estratégias são simplificados; S10/S11 continuam inelegíveis por ausência de patrocinador/rival, sem remoção do seed. Cores podem ser editadas via CLI, não existe painel web administrativo. Sem animação longa: roletas mostram fatias proporcionais e resultado imediato persistido.

Comandos de reprodução completos no README. Para teste local HTTP com modo CLI isolado, carregue `pdo_mysql`, `mbstring` e `curl`; para motor/MySQL, apenas as duas primeiras. Não declare sucesso de produção a partir destas evidências locais.

## Correção do cadastro

O botão Criar conta compartilhava o formulário de login: clicar sem senha acionava a validação nativa, sem abrir uma tela de registro. Agora GET /register tem formulário próprio e Criar conta é um link de navegação. A senha mínima de 8 caracteres está visível; erro de cadastro preserva formulário/e-mail, sem preencher senha.

Validação executada no IIS: tests/http.php passou com 26 verificações, incluindo GET /register, navegação independente de formulário, senha inválida com cadastro preservado e cadastro válido. Smoke HTTP passou incluindo /register=200. No navegador, clicar Criar conta com login vazio abriu a página Crie sua conta. Nenhuma conta pessoal foi alterada.

## Interface mobile e desempenho do jogador

Resultado decisório substituído por card com ícone e cores verde/vermelha, animação de apresentação de 650 ms, botão para pular e respeito a movimento reduzido. O número bruto do sorteio e os efeitos ficam em detalhes recolhidos; os sorteios continuam somente no PHP. SessionStorage marca a apresentação já vista; refresh não gera novo sorteio nem repete animação quando o armazenamento está disponível. Sem JS, o resultado permanece visível.

Durante partidas: gols, assistências, finalizações e passes certos em faixa compacta; habilidades e estados recolhidos. Após partida: nota/minutos, total de gols/assistências/jogos na temporada e lista por rodada/adversário, antes da escolha de fim de semana. Total do campeonato inclui apenas partidas finalizadas; contador da partida mostra os lances em andamento. Estatísticas e regras existentes não foram alteradas, sem migration.

Mobile: navegação inferior fixa, alvos de toque de pelo menos 44 px, cabeçalho compacto e cards responsivos. Estilos e JS recebem versão baseada no arquivo para evitar cache antigo após atualização.

Executados: lint das views/helper, tests/presentation.php (totais finalizados, resultado de falha, escaping, ordenação do histórico), tests/http.php com 29 verificações no IIS incluindo comparação dos gols exibidos com o estado persistido, smoke HTTP de segurança. Prévia visual isolada com dados fictícios em largura 390×844 (largura DOM 375 sem overflow) e 1280×900. Refresh da prévia manteve total 4 e não repetiu animação. Nenhuma carreira pessoal foi usada na revisão visual.

## Escolhas com narrativa direta

Removida a referência redundante à posição no lance genérico: agora O adversário se aproxima. O que você faz? Todas as escolhas de partida, treino, pré-jogo e fim de semana exibem somente o nome da ação, sem probabilidades ou deltas de atributos/XP previstos. O resultado também não mostra chance nem número bruto do sorteio; efeitos efetivamente aplicados continuam disponíveis em Ver efeitos. Cálculos e auditoria no servidor foram preservados.

Executados: lint da view, tests/presentation.php incluindo ausência de chance/sorteio/posição redundante e de metadados nas escolhas; tests/http.php com 29 verificações no IIS.

## Partida compacta e configuração interna

Efeitos efetivamente aplicados aparecem diretamente no resultado (sem Ver efeitos). Placar horizontal e contadores de gols/assistências do personagem em um único card; dados extras e histórico ficam fora da tela de partida em andamento. Rádio removido da partida e disponível apenas após finalizar, recolhido no pós-jogo. Menu reduzido a Carreira/Tabela/Sair; rota /clubes removida (404), catálogo mantido apenas como configuração/seed/CLI.

Corrigida a propagação do texto dos lances para feedback persistido: gol e assistência pessoais agora geram destaque explícito. Gols coletivos não geram destaque pessoal. Custos reais de energia e XP extra do lance agora também constam nos efeitos mostrados; regras e probabilidades mantidas, sem migration ou reset de carreira.

Executados: 30 temporadas nas seis posições; 200 lances de teste comparando highlights com gols/assistências nas estatísticas; temporada inteira com MySQL, replay, isolamento de contas e transferência; testes de apresentação para efeitos diretos, ausência do rádio ao vivo e destaque de gol/assistência; 29 verificações HTTP IIS e smoke com /clubes=404. Prévia fictícia 390×844: quatro escolhas inteiramente acima do menu, sem overflow horizontal (último botão em 575 px; menu em 779 px). Layout de 320×640 também ajustado mantendo botões com pelo menos 44 px; narrativas longas, fontes ampliadas ou telas menores ainda podem precisar de rolagem. Sem teste em dispositivo físico.

## 12/10/2026 — roleta e continuação compacta

Roleta em SVG com setores ponderados, giro de 3,8 segundos e desaceleração, ponteiro fixo e resultado alinhado ao setor salvo no servidor. Pular animação, recarregar e preferência por movimento reduzido preservam a proposta. Os botões de resposta ficam bloqueados durante o giro.

Pós-jogo: resultado e decisão antes do histórico/radio. Encerramento: próxima proposta antes dos detalhes recolhidos da temporada. Narrativa baseada em classificação, pontos, jogos, gols e assistências reais da carreira. Nenhuma carreira do usuário foi avançada nesta alteração.

Executado: PHP lint dos arquivos alterados; tests/presentation.php, tests/wheel.php, tests/season-presentation.php; tests/run.php (27.979 verificações, 30 temporadas em seis posições); tests/http.php (29 verificações no IIS em http://rumo10.local). Verificação visual de propostas, resumo e pós-jogo em navegador com viewport 390 × 844 usando fixtures isoladas sem banco. Confirmado término natural do giro e botões visíveis acima do menu. Pular e recarregar foram conferidos durante o desenvolvimento anterior desta mesma alteração.

Limites: Apache e aparelho celular físico não testados. A animação apresenta o sorteio previamente salvo; o realismo adicional nesta rodada é narrativo e de fluxo, sem novos contratos, salários ou agentes.

## 12/10/2026 — clubes e campeonatos de 2026, versão 0.3

Recorte confirmado pelo usuário: participantes masculinos profissionais de 2026 no Brasileiro A/B/C/D e Paulista A1/A2/A3/A4/Segunda Divisão. Cadastro: 225 clubes distintos, 244 participações e nove competições, fontes registradas em Docs/05_Clubes_e_Competicoes.md e no catálogo JSON. Não foram incluídos clubes inativos. Jacareí FC utiliza a vaga do ex-Joseense; ECUS é outro clube.

Implementação: calendário variável, grupos, quadrangulares, mata-matas, pênaltis, playoffs de B/D, títulos, acesso/descenso, mercado com clubes reais, estadual/nacional em sequência, snapshot transacional e carreira nova com arquivo/restauração por conta. Migration 002 executada em ecohub somente para rumo10_*. Carreira pessoal existente preservada, sem simulação ou conversão nesta atualização.

Executado nesta rodada:

- PHP lint de app, index, instaladores e testes novos; node --check public/assets/game.js.
- tests/official.php: 8.534 verificações; competições concluídas com 380 jogos A, 384 B, 216 C, 610 D, 72 A1, 150 A2, 134 A3, 134 A4 e 150 Segunda; anos completos de São Caetano, Água Santa, Corinthians e Nacional-AM, com mercado e próxima temporada.
- tests/official-mysql.php: 381 verificações na execução final; ano completo de Água Santa com A2 e D, reload exato, replay, transferência, arquivo/restauração e isolamento por conta. Conta temporária descartada pelo próprio teste.
- tests/http.php: 34 verificações reais no IIS em http://rumo10.local; cadastro, rodada, persistência, CSRF, criação de segunda carreira e restauração da primeira.
- tests/http-smoke.ps1: rotas, arquivos privados bloqueados, API protegida, CSRF e método inválido.
- tests/run.php: 30 temporadas completas legadas em seis posições, preservando compatibilidade.
- tests/presentation.php e tests/season-presentation.php: resultado e efeitos diretos; +0 oculto; sem botão Pular animação no feedback comum; ordem da continuação antes dos detalhes.
- tests/wheel.php: setores, alinhamento e resultado persistido preservados.
- Navegador: criação com 225 opções, encerramento de campeonato com ação em destaque, tabela A4 e feedback sem efeito zero; viewport 390 × 844, fixtures sem banco.

Limitações verificáveis: sem acesso/teste em Apache ou celular físico. Datas/horários e simultaneidade real não reproduzidos; formatos e participantes iniciais de 2026 são a base. Temporadas posteriores e reposição de vagas D usam adaptações documentadas; não se afirma regulamentação integral de 2027/2028. Identidade visual dos 225 clubes não foi certificada.

## 12/10/2026 — acesso visível a Nova carreira

O atalho antes ficava em Minha carreira, dentro do painel oculto durante partidas. Agora Nova carreira faz parte do menu principal de todas as páginas autenticadas: topo no desktop, barra inferior de quatro itens no celular. A criação continua arquivando a carreira anterior somente após enviar o formulário.

Verificado: PHP lint, teste de apresentação com link no menu durante partida, 34 verificações HTTP no IIS e clique no menu da prévia em 390 × 844 abrindo Quem entra em campo. Nenhuma carreira pessoal foi criada ou arquivada nesta correção.

## 13/10/2026 — entrada por roleta e clubes próximos, versão 0.4

Executado no checkout do IIS local, PHP 8.4.26:

- tests/run.php: 27.981 verificações, 30 temporadas legadas completas nas seis posições.
- tests/official.php: 8.638 verificações na execução desta rodada; nove formatos nacionais/paulistas, ano, propostas e próxima temporada.
- tests/onboarding.php: 88 verificações de SP, proximidade, escolhas inválidas e escudos.
- tests/state-entry.php: 5.833 verificações; ofertas geográficas nas outras 26 UFs, três simulações de cada campeonato, uma carreira inteira até o fim do ano em cada UF, progressão e segunda temporada. Verificado também que um universo oficial anterior conserva suas nove competições.
- tests/official-mysql.php: 401 verificações na execução desta rodada, ano estadual/nacional completo, reload, mercado, arquivo/restauração.
- tests/entry-mysql.php: 24 verificações de entrada em SE/RO/PR, com roleta e propostas persistidas, replay, contrato e treino. A limpeza inclui as carreiras arquivadas da conta temporária; nenhum registro de conta pessoal foi excluído.
- tests/http.php: 42 verificações no IIS em http://rumo10.local; inclui cadastro sem seletor de clube, roleta, escolha, formulário com clube forjado ignorado, municípios por UF, erro de município sem arquivar carreira anterior, partida, replay e restauração.
- tests/http-smoke.ps1: rotas, método, CSRF, API protegida e bloqueio de .env/.git/app/database/tests/README.
- tests/presentation.php, tests/season-presentation.php e tests/wheel.php: concluídos com sucesso. PHP lint de app/bin/tests/public, node --check e git diff --check concluídos.
- 462 escudos: arquivos válidos por getimagesize; HEAD no IIS respondeu HTTP 200 com MIME de imagem para todos.
- bin/install-official.php: upserts do catálogo de 462 clubes e 35 competições/etapas em ecohub, exclusivamente rumo10_*. Registros antigos não são apagados.
- Navegador: prévia GET isolada sem banco em 390 × 844; três propostas de SP com escudos, nenhuma imagem quebrada, nenhum overflow horizontal. Verificados dois clubes em RO e aviso A2 provisória de SE. A versão desktop foi inspecionada antes do ajuste de viewport. Evidência mobile em storage/inicio-mobile.png (arquivo local ignorado pelo Git).

Não executado: Apache em produção, telefone físico ou certificação visual manual dos 462 escudos. Os campeonatos estaduais usam adaptações documentadas de calendário/desempate. Pirâmides superiores dos novos estaduais e turno decisivo de PE em 2027 permanecem limitações explícitas em Docs/11_Inicio_Estadual_e_Escudos.md e na interface pertinente.

## 13/10/2026 — situações, overall e quatro países, versão 0.5

Executado em PHP 8.4.26 no checkout usado pelo IIS local:

- `tests/scenes-strength.php`: 3.143 verificações; 214 enunciados distintos, 856 alternativas com mecânicas válidas, memória de dez eventos, reload sem novo sorteio, contextos de posição/lesão/reserva e jogo finalizado imutável. Em 6.000 amostras de força 85 × 40: 5.264 vitórias do favorito, 211 zebras e 525 empates, média 3,55 gols na execução final. Esse teste verifica distribuição, não prediz um resultado individual.
- `tests/international.php`: 9.272 verificações na execução; oito divisões, participantes, paletas e escudos locais, jogos iniciais por clube, fases concluídas, acessos e calendários com nova composição. Oito carreiras completas, uma em cada divisão, com mercado e segunda temporada. UY primeira pode ter 298–300 partidas dependendo da necessidade de finais. AR primeira 510, AR segunda 670, UY segunda 231, PY primeira 264, PY segunda 240, BO primeira 240 e BO segunda 408 na composição original.
- `tests/run.php`: 27.979 verificações, 30 temporadas legadas em seis posições; integração MySQL com 14 rodadas, transferência, reload e replay concluída.
- `tests/official.php`: 8.670 verificações, nove formatos nacionais/paulistas e quatro anos completos com progressão. `tests/state-entry.php`: 5.833 verificações, incluindo 26 carreiras estaduais completas e próximas temporadas.
- `tests/official-mysql.php`: 301 verificações na execução, ano estadual/nacional, persistência exata, mercado, arquivo/restauração e isolamento. `tests/entry-mysql.php`: 24 verificações de entrada por roleta em SE/RO/PR.
- `tests/scenes-mysql.php`: 20 verificações de seed, situação ativa, memória, força da partida e replay/reload, em carreira legada e Bolívia. Na primeira execução, a limpeza encontrou FK de temporadas da carreira legada; a limpeza foi corrigida para remover dependências e os dois registros temporários remanescentes foram identificados e removidos em transação. A segunda execução terminou com código zero e limpeza completa. Não se removeu conta pessoal.
- `tests/http.php`: 42 verificações reais em `http://rumo10.local`, incluindo cadastro, escolhas, rodada, replay, refresh, tabela, logout/login e CSRF. Smoke IIS: arquivos internos bloqueados, rota de clubes ausente, API autenticada, métodos e CSRF inválidos recusados.
- `tests/onboarding.php`: 88 verificações. Apresentação, resumo da temporada e roleta: testes concluídos. Lint PHP de 38 arquivos (antes da adição do teste específico de cenas MySQL), `node --check` e `git diff --check` concluídos; lint do novo teste também executado ao final.
- `bin/install-official.php`: catálogo atualizado para 671 clubes e 43 competições/etapas em `ecohub`, somente tabelas `rumo10_*`, sem reset de carreira.
- Todos os 671 escudos responderam HEAD HTTP 200 e MIME de imagem no IIS. Os novos downloads foram decodificados com Pillow; o escudo de GV San José foi corrigido pela fonte FBF.
- Navegador: prévia GET isolada de uma partida da Bolívia, 390 × 844; nenhuma imagem quebrada, largura do documento 390, placar, estatísticas pessoais e quatro opções visíveis na mesma tela. Evidência em `storage/internacional-mobile.jpg`, ignorada pelo Git. Nenhuma conta ou carreira real foi alterada nessa inspeção.

Não executado: Apache de produção, telefone físico, certificação manual de todos os escudos/cores, importação de todas as classificações históricas ou homologação integral dos regulamentos internacionais. Regras adaptadas, fonte das paletas e cobertura provisória de overall estão em `Docs/12_Situacoes_Overall_e_Mercosul.md`.


## 13/10/2026 — ficha gráfica do jogador

Criada a página autenticada `/jogador`, acessível pelo nome do personagem e pelo cartão Habilidades e evolução. Cartão de identidade com escudo, nível técnico derivado da média das nove habilidades, condição física em barras, números da temporada, grupos de habilidades com XP e reputação/riscos recolhidos. Não altera regras, valores, carreira ou decisões pendentes.

Verificado: lint PHP de PlayerProfile/view/index; teste de apresentação existente; 44 verificações HTTP no IIS local, incluindo abertura autenticada da ficha e estado da carreira intacto antes/depois. Prévia GET isolada conferida no navegador em desktop e 390 × 844: sem imagens quebradas ou overflow horizontal, expansão de Técnica com valores e barras de XP. Evidências locais em storage/jogador-mobile.jpg e storage/jogador-desktop.jpg. Não houve teste em telefone físico ou Apache.


## 13/10/2026 — chance de sucesso somente em campo

A pedido do usuário, as opções de partida exibem a porcentagem real de Game::chance, com duas casas decimais. Não há mudança de probabilidade ou de sorteio. Uma legenda esclarece que sucesso da ação não garante gol. Treinos, pré-jogo e fim de semana continuam sem porcentagens e sem atributos previstos.

Verificado: lint da view e teste de apresentação, incluindo correspondência entre valor exibido e cálculo do motor nas quatro opções, além da ausência de chances nas três etapas fora de campo. Prévia GET isolada inspecionada no navegador em 390 × 844: quatro porcentagens visíveis, sem overflow horizontal. Evidência local storage/chances-partida-mobile.jpg. Não se simulou a carreira pessoal.
