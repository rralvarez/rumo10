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
