# Validação da publicação UOL — 14/10/2026

Este registro atualiza os estados históricos dos guias 33 e 34. A publicação escolhida é GitHub Actions com FTPS explícito e TLS validado; SSH e symlinks não são usados. O IIS local mantém configuração, arquivos privados e banco próprios.

## Infraestrutura confirmada

- HTTPS com certificado aceito nos domínios principal, www e Hub; DNS autoritativo aponta para 186.234.81.62.
- PHP web 8.4.26, extensões pdo_mysql, mbstring, curl e zip disponíveis. Configuração privada legível e storage gravável.
- MySQL 8.0.46-cll-lve em localhost; banco único rumoacame144a99e_rumohub com 42 tabelas. Prefixos rh_* e rumo10_* preservados.
- Importação inicial concluída pelo usuário no phpMyAdmin, com 659 consultas. Conferência pelo PHP confirmou 2.160 clubes e 63.186 jogadores, sem contas ou administradores locais importados. Não repetir a carga inicial em atualizações.
- IP de saída do PHP do jogo para o Hub observado: 186.234.81.62. Cliente habilitado somente para esse IP, com HMAC e escopos preservados.
- As 2.311 imagens locais do Hub estão versionadas e incluídas no pacote privado. Banco guarda caminhos; imagens não ficam em colunas binárias nem em diretório público.

## Publicação automática

Os repositórios usam production-ftps.yml, acionado em push de main ou manualmente. O job só publica com UOL_DEPLOY_ENABLED=true e UOL_DEPLOY_METHOD=ftps. Configuração MySQL e segredo HMAC permanecem fora do GitHub; somente credenciais FTPS estão nos Secrets autorizados.

UOL_FTP_ADDRESS=186.234.81.62 fixa a rota do servidor confirmado no runner. O workflow acrescenta os quatro nomes próprios verificados ao /etc/hosts temporário do runner, pois a resolução DNS falhou nas primeiras execuções. Certificados continuam validados pelo nome original, sem desativar TLS. Rever esse IP se a UOL mover a hospedagem.

Código privado em apps/<projeto>/current; somente ponte PHP, regras Apache e assets ficam em public_html ou rumohub.rumoacamisa10.com.br. A publicação preserva os blocos PHP gerados pelo cPanel. Arquivos inalterados são pulados pelo manifesto; configuração, sessões, mídia editorial e banco são preservados. Push não executa SQL.

## Primeiro administrador

O SQL privado de configuração inicial foi importado e o acesso inicial já foi utilizado: há um administrador ativo. Use /admin/login com seu e-mail e senha pessoais. Não importar o SQL inicial nem o de configuração do administrador novamente. Os arquivos configurar-admin-producao.* permanecem privados e o link já utilizado não deve ser compartilhado.

## Verificações e limites

Os testes locais e de empacotamento estão documentados nos guias anteriores. O workflow executa sintaxe PHP, integridade do pacote, planejamento de transferência e nove testes de deploy com FTP simulado antes de enviar arquivos. Após transferir, faz smoke HTTPS de páginas públicas e bloqueios de arquivos internos.

A carga inicial do Hub transferiu os arquivos, mas perdeu a conexão de controle ociosa antes de salvar o manifesto. A manutenção permaneceu ativa. A recuperação conferiu SHA-256 de todos os 2.360 arquivos e das cópias públicas de assets antes de gravar o manifesto e liberar o site. A sonda temporária foi removida e a ponte original restaurada. O deploy foi corrigido para reconectar por TLS antes de finalizar; um teste reproduz a expiração da sessão de controle.

O pacote de produção do Rumo10 agora exclui database/rosters e player-catalog.json, usados somente no modo local sem Hub. O pacote passou de 1.877 para 122 arquivos; os arquivos do checkout IIS continuam disponíveis. Produção exige a API do Hub, conforme production-check.php. Arquivos privados legados eventualmente enviados na primeira carga não são removidos pelo deploy automático.

Hub publicado e aberto no navegador: página inicial com jogos disponível. Verificados por HTTPS: login administrativo 200, redirecionamento de /admin sem sessão para /admin/login, CSRF no formulário, cookie Secure/HttpOnly/SameSite=Lax e bloqueio de .env, .git/config e storage/media/clubs. Início e login dos dois sites locais IIS também responderam 200 após a preparação de produção.

## Publicação concluída

- Hub: [workflow 38662933281 aprovado](https://github.com/rralvarez/rumohub/actions/runs/38662933281), código 6ceea45. O smoke PHP do runner Ubuntu passou com TLS validado.
- Rumo10: [workflow 38662952193 aprovado](https://github.com/rralvarez/rumo10/actions/runs/38662952193), código 4c13716. A carga inicial antiga terminou com a mesma expiração da conexão de controle; a atualização corrigida aguardou na fila e concluiu sem cancelar a execução anterior. O smoke PHP do runner também passou.
- API assinada testada pelo PHP do próprio site Rumo10, com seu módulo e configuração privados: banco acessível, 2.160 clubes, 154 competições, elenco com 46 atletas, escudo PNG recebido, 211 seleções e emblema do Brasil recebido. HMAC, escopos e IP permitido permaneceram ativos. Cache privado sincronizado. Sonda temporária autenticada removida e ponte original restaurada.
- Rumo10: login 200 com CSRF e cookie Secure/HttpOnly/SameSite=Lax; /carreira sem sessão redireciona para /login. .env, .git/config e storage não expostos. Início e cadastro abertos no navegador; aparência do formulário conferida. Acesso público pelo endereço HTTPS abaixo.
- Conferência final do banco: zero contas do jogo; um administrador ativo no Hub e nenhum token inicial pendente. A configuração inicial do administrador já foi utilizada; entrar pelo login normal. Nenhuma conta/carreira de teste foi criada pelos testes de produção.

O PHP CLI local executado com -n não tem uma CA configurada e retornou cURL 60 ao usar o smoke HTTPS. A validação equivalente usou Python com TLS do sistema, navegador e PHP do GitHub Actions, que passaram; a chamada PHP servidor a servidor também passou. Não foi desativada a verificação de certificado nem alterado o PHP dos sites locais para contornar isso.

Não houve envio de formulário de cadastro/login pessoal ou partida completa em produção nesta etapa; os fixtures que criam contas permanecem restritos ao ambiente local. A verificação de conta pessoal, criação de jogador e primeira partida deve ser feita pelo usuário.

## Acessar e testar

1. Abrir [Rumo à Camisa 10](https://www.rumoacamisa10.com.br), clicar em Começar minha história e criar a conta pessoal.
2. Criar o jogador, girar a roleta e escolher um dos clubes oferecidos. Conferir escudo, treino, partida, resultado e persistência ao sair/entrar.
3. Para administrar os dados, abrir [login do Hub](https://rumohub.rumoacamisa10.com.br/admin/login) com o acesso pessoal já criado. A [página inicial](https://rumohub.rumoacamisa10.com.br/) mostra os jogos.
4. Futuras alterações: commit e push em main do repositório correspondente iniciam o deploy FTPS. Aguardar o workflow verde antes de considerar a atualização publicada. Banco não é migrado automaticamente; alterações de esquema exigem SQL incremental revisado, nunca reimportação da carga inicial.

O ambiente local continua em http://rumo10.local e http://rumohub.local, no IIS. Produção usa Apache/cPanel e o banco próprio da hospedagem, sem importar as contas e carreiras locais.
