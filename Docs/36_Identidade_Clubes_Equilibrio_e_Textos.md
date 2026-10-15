# Atualização: conta Rumo, clubes e decisões — 14/10/2026

## Migração e acesso compartilhado

O usuário importou com sucesso `atualizacao-20261014-identidade-clubes.sql` no banco de produção: 1.860 consultas informadas pelo phpMyAdmin. O arquivo permanece em `C:\inetpub\wwwroot\rumohub\database\production\atualizacao-20261014-identidade-clubes.sql` e no GitHub do Hub. Não importar o SQL inicial novamente. As mudanças de texto, modo e probabilidades são código; não exigem uma segunda importação.

Ativados HUB_IDENTITY_ENABLED=1 no Hub e RUMOHUB_IDENTITY=1 no jogo, com escopo identity no cliente autenticado. Segredo HMAC, IP permitido e banco preservados. Verificado pelo PHP do Rumo10 em produção: identidade habilitada, uma conta do jogo vinculada à identidade central, nenhuma conta sem vínculo e rejeição de token inválido pela API assinada. Nenhuma conta ou sessão pessoal foi criada para testes de produção. A tela pública de conta Rumo foi aberta no navegador por HTTPS.

O mesmo e-mail e senha funciona no Hub e no Rumo10, com sessões próprias por site. Os dois jogos futuros poderão consumir essa identidade ao serem implementados e autorizados como clientes. Não há compartilhamento de cookies nem login automático entre sites. Cadastro comum não concede administração. Plano gratuito/pago vem do Hub e continua determinando 3/10 carreiras e países disponíveis. Contas antigas mantêm os IDs e carreiras. Em caso de e-mail existente nas duas aplicações com senhas diferentes, a migração usa a senha do administrador do Hub; a senha do jogador não pode conceder administração. Ao ativar a identidade, a sessão antiga do jogo pode exigir um novo login.

Recuperação de senha e verificação de e-mail não foram implementadas nessa etapa. Os hashes antigos permanecem nas tabelas de compatibilidade, mas a autenticação habilitada consulta o Hub; contas novas do jogo recebem somente um hash aleatório inutilizável localmente. O vínculo de uma conta antiga sem migração é recusado, em vez de atribuir sua carreira apenas por coincidência de e-mail.

## Clubes

Dentro do editor de clube, o elenco vinculado aparece com links para editar jogadores e voltar ao clube após salvar. A busca geral permanece. Pesquisadas 703 cidades com fonte identificada; 1.216 ainda pendentes. Foram preenchidas as 1.146 notas ausentes, sem substituir notas ou edições manuais. Corinthians: São Paulo, overall estimado 82 pela campanha de 2025. O valor é do modelo de jogo, não uma nota publicada pela EA. As cidades de estádio são identificadas como tal; não foram inventadas coordenadas de sede.

## Modo e probabilidades

Modo escolhido somente no cadastro do jogador, preservado durante toda a carreira. A interface de troca foi retirada de todos os modos; /carreira/modo retorna 404 e o comando antigo também é recusado no motor.

O bônus de chance clara caiu para 8 pontos nas opções de gol; o passe ao companheiro livre recebe 18. Finalizações aplicam ajuste adicional dependente da habilidade de finalização e um teto crescente com essa habilidade. Condição física, confiança, ritmo, dificuldade e força adversária continuam no cálculo. No teste de iniciante contra adversário fraco, a chance clara de chute ficou em 60,23% e a de assistência em 82,5%; não são percentuais fixos para todas as situações. Um atleta com finalização alta tem chances maiores. O sucesso de uma finalização continua sendo gol, com o mesmo sorteio exibido na tela; não foi criada uma segunda rolagem para negar um chute já bem-sucedido.

Passes comuns continuam distintos de assistências: após um passe bem executado, a conversão do companheiro considera a habilidade de passe do personagem. Na chance clara do passe ao companheiro livre, sucesso continua significando assistência. Normal, rápido e expresso usam os mesmos cálculos de Game::chance e fieldDecision; os modos automáticos não recebem chance especial de gol.

## Respostas por contexto

As 50 situações de fim de semana têm 200 pares próprios de respostas de sucesso/falha, em weekend-outcomes.php. Também foram ajustadas respostas específicas de treino e pré-jogo em context-outcomes.php. Familiares, amigos, companheiros, torcedores, comissão e agente são identificados quando necessário. Efeitos de contato pessoal usam moral/confiança/foco apropriados, sem conceder entrosamento do elenco para uma visita à família.

Em Oferecer companhia ao colega com problemas pessoais, a falha agora informa que ele prefere ficar sozinho e que você respeita a decisão. Essa recusa não retira entrosamento, nem concede liderança automaticamente em caso de sucesso. As demais opções dessa situação respeitam o tempo do colega. Desfechos e efeitos já gravados em decisões anteriores não são reescritos nem sorteados outra vez; a revisão vale para as próximas decisões.

## Validação

Executados localmente: 94 verificações HTTP no IIS do Rumo10, 33 de administração do Hub, 22 da API de catálogo, 11 de identidade, 12 de autenticação compartilhada entre os sites, 549 dos três modos, 35 de equilíbrio, 2.425 de chances construídas, 511 editoriais e mais de 177 mil verificações de efeitos nas 856 alternativas. O motor completou 30 temporadas em seis posições (27.979 verificações). Fixtures restritas ao ambiente local e removidas ao concluir.

Produção: workflows de publicação e smokes HTTPS, páginas públicas, sessão segura, bloqueios de arquivos internos, catálogo/escudos/API assinada e rejeição de token de identidade inválido. Não foi realizado login com a senha pessoal do usuário nem disputada uma partida em produção. Testar com sua conta: entrar no jogo com a conta Rumo, abrir a carreira existente, conferir ausência da troca de modo e jogar as próximas situações. No Hub, abrir o clube e conferir o elenco no fim do formulário.

Documentação da identidade e SQL: [RumoHub](https://github.com/rralvarez/rumohub/blob/main/IDENTIDADES_E_CLUBES.md). Fonte de São Paulo: [endereço oficial do Corinthians](https://ouvidoria.corinthians.com.br/hc/pt-br/articles/200322766-Como-chegar-no-Parque-S%C3%A3o-Jorge-de-carro).
