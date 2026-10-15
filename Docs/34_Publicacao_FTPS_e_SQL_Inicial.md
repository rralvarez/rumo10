# Publicação FTPS e SQL inicial — 14/10/2026

Alternativa escolhida ao SSH: GitHub Actions com FTPS explícito (FTP + TLS), certificado validado, porta 21. As duas contas autenticaram e alcançaram as raízes dos sites em um teste somente de leitura. Não houve upload à UOL nem criação/importação de banco remoto.

## Contas e destinos

As contas `rumo10git@rumoacamisa10.com.br` e `rumohubgit@rumohub.rumoacamisa10.com.br` têm diretório `/home/rumoacame144a99e`. Nesse FTP, `/` corresponde à pasta da conta; caminhos enviados pelo workflow são relativos a ela. As senhas fornecidas foram guardadas fora do Git, em arquivo local com ACL restrita ao usuário. Após autorização explícita, os Secrets UOL_FTP_USERNAME/UOL_FTP_PASSWORD foram cadastrados e conferidos nos respectivos GitHubs. UOL_DEPLOY_ENABLED=false nos dois projetos; senhas não estão no código nem no SQL.

| Projeto | Código privado | Raiz pública |
|---|---|---|
| Rumo10 | `apps/rumo10/current/` | `public_html/` |
| Hub | `apps/rumohub/current/` | `rumohub.rumoacamisa10.com.br/` |

Este modo não exige shell, symlink, Bash ou PHP CLI na hospedagem. `current` é um diretório comum. Uma ponte PHP na raiz pública carrega o front controller privado; assets são copiados para a raiz pública, enquanto escudos do Hub continuam em storage privado. Caminhos absolutos preservam o código e o IIS local. PHP web precisa poder ler `apps/` e ter `pdo_mysql`, `mbstring`, `curl`, versão 8.2+ e HTTPS corretamente identificado.

Durante cada transferência, a ponte retorna 503 quando existe `apps/<projeto>/deployment.lock`. Arquivos usam upload temporário seguido de rename; não é uma troca atômica do conjunto de arquivos. Requisições já iniciadas antes da manutenção podem terminar com a versão anterior. Em falha de transferência, a manutenção permanece: corrigir e repetir o deploy completo antes de remover o marcador. Não existe remoção automática de código, cache, contas, sessões, uploads ou dados de banco; somente o marcador criado pelo deploy é removido no sucesso. Backup/rollback manual da pasta privada e assets é necessário; o esquema de releases/symlinks do guia SSH não se aplica a esse modo.

## SQL único para a primeira instalação

Arquivo no repositório **RumoHub**: `database/production/rumohub-inicial.sql.gz`, com recibo `rumohub-inicial.sql.gz.json`. Mesmo banco para os dois projetos; importar **uma vez**, não uma vez por jogo. A compressão reduz a carga para aproximadamente 9 MB. O phpMyAdmin aceita importação de SQL gzip quando o servidor dispõe de zlib; conferir limite de upload/tempo do painel.

1. No cPanel, criar o banco de produção e seu usuário. Guardar o nome completo com prefixo da conta; o SQL não fixa um nome de banco.
2. Abrir phpMyAdmin e **selecionar esse banco vazio**.
3. Abrir **Importar**, selecionar `rumohub-inicial.sql.gz` baixado do repositório e executar a importação. Abrir o arquivo no computador não executa SQL.
4. Conferir 42 tabelas (`rh_*` + `rumo10_*`), 2.160 clubes no Hub, 63.186 jogadores, 19.789 ratings e 211 seleções. A carga tem 150.471 registros de referência e três jogos; nenhuma conta, carreira, senha/hash de usuário, administrador, token ou nonce local.

O arquivo contém estrutura corrente obtida do MySQL local e seeds; não exige os instaladores PHP na hospedagem. Os INSERTs são agrupados em lotes de até 250 registros para reduzir o tempo de importação. Não contém DROP, USE ou CREATE DATABASE. CREATE TABLE não usa IF NOT EXISTS: importar em instalação existente deve falhar, para impedir que uma atualização seja confundida com carga inicial. DDL não é transacional no MySQL. Se interromper, não repetir às cegas; conferir o estado e recuperar a instalação vazia antes de outra tentativa. Atualizações futuras usarão migrations separadas revisadas, nunca essa carga completa. Testado restaurando em MySQL 8 local descartável e removendo somente esse banco de teste; MySQL/MariaDB e limites do painel UOL ainda não validados.

Imagens não estão no SQL: permanecem arquivos privados. As imagens versionadas do Hub seguem no pacote; mídia editorial não versionada deve ser transferida de `rumohub/storage/production-export/20261014-215138/media.tar.gz` para `apps/rumohub/current/storage/media/`, preservando as subpastas. Não extrair em uma raiz pública.

## Configuração inicial, antes do workflow

Preparar manualmente pelo Gerenciador de Arquivos/FTPS:

- `apps/rumohub/current/.env` e `apps/rumo10/current/.env`, usando os exemplos `deploy/production.env.example`, com banco/usuário/senha **de produção**. `APP_ENV=production`, URLs HTTPS e Hub habilitado no jogo. Nunca enviar `.env` ao GitHub.
- `apps/rumohub/current/storage/private/clients.php`, com cliente `rumo10`, segredo hexadecimal aleatório de 64 caracteres, escopos `catalog.read`, `squads.read`, `media.read`, enabled true e **IP(s) de saída reais do PHP do jogo**, confirmados no ambiente. Mesmo segredo no `.env` privado do Rumo10. Autenticação/IP não será afrouxada para facilitar a publicação.
- Pastas `storage/logs`, `storage/sessions`, `storage/private` em ambos e `storage/hub-cache` no jogo, com escrita para o PHP e acesso mínimo necessário; não 0777.
- Revisar/guardar fora da raiz pública a página inicial padrão da hospedagem e seu .htaccess. O deploy recusa sobrescrever index.php sem sua marca e regras Apache não gerenciadas. Blocos de configuração PHP/handler delimitados pelos marcadores oficiais do cPanel são conservados junto às regras do projeto, sem duplicação. TLS, mod_rewrite e leitura de apps/ precisam funcionar antes da liberação. Antes de qualquer alteração pública, o script verifica DNS e certificado HTTPS do domínio; erro nessa etapa impede a publicação, sem ignorar a validação TLS.

Para criar o administrador inicial **sem SSH**, executar localmente no Hub `php bin/prepare-admin-offline.php`. Importar o SQL privado gerado `storage/private/configurar-admin-producao.sql` no banco de produção. Depois abrir localmente `configurar-admin-producao.html` e seguir o link HTTPS para escolher a senha no Hub. Link único de 24 horas; administrador ativo impede novo cadastro inicial. Esses arquivos não são versionados e nunca vão para a raiz pública. Não gerar o link dias antes da instalação.

## Configurar Actions

Em cada repositório, Settings → Secrets and variables → Actions:

| Tipo | Nome | Valor |
|---|---|---|
| Secret | `UOL_FTP_USERNAME` | usuário completo correspondente ao projeto |
| Secret | `UOL_FTP_PASSWORD` | senha da conta correspondente |
| Variable | `UOL_FTP_HOST` | `a16-asgard21.hospedagemuolhost.com.br` (testado) |
| Variable | `UOL_FTP_PORT` | `21` (testado) |
| Variable | `UOL_DEPLOY_METHOD` | `ftps` |
| Variable | `UOL_DEPLOY_ENABLED` | `true` somente após configuração/SQL e decisão de publicar |

`production-ftps.yml` publica somente quando método=ftps e enabled=true; o workflow SSH exige método=ssh. Ambos usam grupo de concorrência production por repositório. Hub e jogo são repos independentes: publicar primeiro o Hub e validar sua API, depois o jogo. O segredo HMAC e a senha MySQL permanecem na hospedagem, não nos runners. SQL inicial é excluído do pacote de deploy: **nenhum push executa ou importa SQL**.

Primeira execução: Actions → Publicação UOL por FTPS → Run workflow. Depois de validar os sites, pushes em main usam o mesmo mecanismo. Ambiente production permite adicionar proteção/revisão conforme o plano GitHub. Se o smoke HTTPS falhar depois de retirar a manutenção, o workflow informa falha; não há rollback automático. Cadastro/login e integração assinada precisam de verificação própria.

Scripts: `bin/deploy-ftps.py` (Python 3.11+), `bin/build-release.php` e smoke GET HTTPS. Passaram os quatro testes de `tests/ftps-deployment.py`: preservação de configuração, manutenção em falha e recusa de sites PHP/HTML não gerenciados. Passaram os manifestos/hashes dos pacotes finais (1.877 arquivos do jogo, 2.360 do Hub), os dry-runs FTPS e a restauração integral do SQL no banco descartável, incluindo a idempotência do preparo de administrador offline. Esses testes não substituem upload, PHP/Apache, integração API e banco na UOL.

Fontes: [cPanel — FTP com TLS](https://support.cpanel.net/hc/en-us/articles/4413517293719-Use-Web-Disk-or-FTPES-FTP-SSL-to-provide-secure-access-for-file-upload-management-without-sharing-cPanel-login-information), [Python FTP_TLS](https://docs.python.org/3/library/ftplib.html#ftplib.FTP_TLS).

## Preparação efetiva na UOL — 14/10/2026

- Usuário informou conclusão da importação inicial no phpMyAdmin (659 consultas) e concessão de SELECT/INSERT/UPDATE/DELETE ao usuário do banco `rumoacame144a99e_rumohub`. A conexão pelo PHP de produção ainda não foi validada; DB_HOST=localhost é o valor inicial a conferir.
- Feito inventário e backup local das regras iniciais; os dois .htaccess continham somente diretivas PHP geradas pelo cPanel. A preservação/republicação desses blocos foi testada, assim como o bloqueio de publicação com certificado inválido: seis testes FTP simulados passaram.
- Preparadas/enviadas por FTPS as configurações privadas para `apps/<projeto>/current/.env` e `apps/rumohub/current/storage/private/clients.php`, com permissão 0600. Nenhum arquivo público do aplicativo foi enviado. Cliente API permanece desativado e sem IPs autorizados até verificar a origem real das requisições do PHP; não foi afrouxada a autenticação.
- DNS autoritativo da UOL: ns1.cpuh21.hospedagemuolhost.com.br / ns2.cpuh21.hospedagemuolhost.com.br. Domínio, www e rumohub respondem com 186.234.81.62 (www via CNAME para o domínio); houve cache antigo em um resolvedor público durante a mudança.
- As verificações HTTPS dos três nomes ainda encontraram certificado autoassinado. Solicitar/rodar AutoSSL no cPanel, conferir novamente certificados e DNS, só depois publicar Hub e jogo. Não considerar testes FTPS como teste HTTPS, Apache, PHP ou da API em produção.

### Verificação seguinte

AutoSSL passou a fornecer HTTPS aceito pela validação TLS para domínio, www e Hub; as raízes vazias retornaram HTTP 403. Sondas PHP temporárias, autenticadas por token e removidas ao concluir, confirmaram PHP 8.4.26, pdo_mysql/mbstring/curl/zip, leitura da configuração privada e storage gravável. O IP de saída efetivamente observado do PHP do jogo para o Hub é 186.234.81.62.

O MySQL recusou a autenticação com erro 1045 em localhost, 127.0.0.1 e no IP conhecido do servidor. Usuário confirmou que a senha contém barra invertida; configurações foram corrigidas e uma comparação privada confirmou que o PHP lê o valor esperado, mas a recusa persistiu. A redefinição da senha do usuário no cPanel está pendente; não houve nova importação, reset de dados ou liberação pública do aplicativo.

O deploy agora grava o manifesto privado somente depois de concluir todas as transferências. Publicações seguintes pulam arquivos com hash inalterado, preservando .env, sessões e uploads. Em recuperação com manifesto incorreto/arquivos removidos manualmente, fazer backup e retirar somente o manifesto apps/<projeto>/current/release.json para forçar uma transferência completa. Sete testes FTP simulados passaram, incluindo repetição de deploy sem reenviar código inalterado.
