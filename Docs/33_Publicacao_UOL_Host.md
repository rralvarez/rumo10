# Publicação UOL Host / cPanel — preparação em 14/10/2026

Base: código, README e documentação atuais dos dois projetos. O IIS local mantém suas raízes `public/`, `.env`, banco e bindings. Esta preparação não publicou sites na UOL nem ativou o workflow.

## Atualização pelo GitHub

A opção preparada é GitHub Actions → SSH → versão privada → ativação. Cada repositório possui `.github/workflows/production.yml`, acionado por push em `main` ou manualmente. O job **só executa** quando a variável de repositório `UOL_DEPLOY_ENABLED` for exatamente `true`. Sem isso, pushes não alteram produção.

Clonar o GitHub no Git Version Control do cPanel não significa atualizar automaticamente a cada push no GitHub. O deploy automático nativo exige push no repositório gerenciado pelo cPanel e `.cpanel.yml`; o fluxo de pull requer Update from Remote e Deploy HEAD Commit. Não configuramos ambos os mecanismos simultaneamente.

Fontes: [cPanel deployment](https://docs.cpanel.net/knowledge-base/web-services/guide-to-git-deployment/), [GitHub environments](https://docs.github.com/en/actions/how-tos/deploy/configure-and-manage-deployments/manage-environments), [UOL cPanel / SSH](https://faq.uol.com.br/uolhost/content/category/uol-host/hospedagem-cpanel/).

## Conferir na hospedagem antes de ativar

1. Solicitar à UOL **login SSH com autenticação por chave** na conta `rumoacame144a99e`. A página Gerenciar Chaves SSH, sozinha, não comprova que o shell está liberado. Confirmar host, porta e fingerprint da chave do servidor com a UOL.
2. Confirmar PHP web **e CLI** 8.2+ com `pdo_mysql`, `mbstring`, `curl`, `Phar` e `zlib`; MySQL 8+ é a referência testada do jogo. Se o painel oferecer MariaDB, validar esquema e comportamento antes de publicar. Verificar `php -v`, `php -m` e caminho do CLI da versão escolhida; não presumir que `php` corresponde ao PHP web.
3. Confirmar Bash, `tar`, `flock`, `cp`, `mv` GNU e symlinks permitidos. Apache deve seguir o link de assets e PHP deve poder ler `/home/rumoacame144a99e/apps/` (`open_basedir`). Configurar PHP web inicialmente com pelo menos 128 MB; importações/instalação CLI podem exigir 512 MB. Conferir cotas de banco, disco e inodes.
4. Confirmar HTTPS/AutoSSL em ambos os domínios, DNS e `mod_rewrite`. PHP precisa receber `HTTPS=on` do servidor; não liberar produção HTTP nem confiar em headers arbitrários de proxy. Ativar o redirecionamento HTTPS no cPanel e verificar se a API recebe HTTPS diretamente, sem redirecionar suas chamadas assinadas.
5. Criar **um banco de produção** e usuários novos pelo cPanel. Nome previsto `rumoacame144a99e_rumohub` é apenas exemplo: usar o nome completo efetivamente apresentado. Os prefixos continuam `rh_*` e `rumo10_*`. Não usar credenciais locais/UcronIA nem executar a migração local ecohub→rumohub na UOL.

Não há acesso à conta UOL nesta etapa: recursos acima ainda precisam ser confirmados.

## Layout com as raízes fixas contratadas

```text
/home/rumoacame144a99e/
  apps/
    rumo10/
      releases/<SHA>/        código completo, incluindo public/ para caminhos internos
      current -> releases/<SHA>
      previous -> releases/<SHA-anterior>
      shared/.env            configuração exclusiva de produção
      shared/storage/        sessões, logs, cache persistentes
      incoming/<SHA>/        pacote recebido e extraído, privado
    rumohub/                 mesmo layout; shared/storage/media guarda escudos
  public_html/
    index.php                ponte para apps/rumo10/current/public/index.php
    .htaccess                regras do public/ do jogo
    assets -> ../apps/rumo10/current/public/assets
  rumohub.rumoacamisa10.com.br/
    index.php                ponte para apps/rumohub/current/public/index.php
    .htaccess
    assets -> ../apps/rumohub/current/public/assets
```

Somente a ponte, regras e assets ficam nas raízes públicas. Escudos legados em `rumo10/public/assets/clubs` são excluídos do pacote de produção; o jogo exige a API do Hub. O Hub mantém escudos em `shared/storage/media`, entregues somente pelos endpoints autenticados. Não criar link público para storage. Não enviar todo o repositório para `public_html`.

O deploy usa ponte e symlinks para preservar os caminhos `dirname(__DIR__)` do código atual, sem alterar o IIS. Troca de `current` é atômica no Linux. Configuração, sessões, mídia e banco não são substituídos a cada push. O GitHub runner não recebe credenciais MySQL nem segredo HMAC.

## Primeira preparação privada

Com SSH confirmado, criar em **cada** projeto `apps/<projeto>/shared/storage/{logs,sessions,private}`; no Hub criar também `storage/media`, no jogo `storage/hub-cache`. Diretórios privados com acesso somente à conta/PHP; configuração `.env` e `clients.php` preferencialmente `0600`, diretórios `0700` quando o PHP roda como a mesma conta. Não usar `0777`.

Usar `deploy/production.env.example` de cada repositório para montar o respectivo `.env` em `shared/`, preenchendo credenciais **diretamente no servidor**. Confirmar o host MySQL no painel. Ambos apontam ao mesmo banco, com usuários separados se o painel permitir. O jogo recebe `RUMOHUB_ENABLED=1` e URL HTTPS do Hub. Privilégios de instalação não devem permanecer desnecessariamente no usuário web; acesso por tabela pode exigir ajuda do provedor, pois cPanel frequentemente gerencia permissões por banco. O administrador do Hub precisa escrever suas tabelas, além da API registrar nonces.

Gerar localmente o pacote de código de cada repo com `php -d memory_limit=512M bin/build-release.php`; requer arquivos de preparação já versionados. Conferir com `php -d memory_limit=512M tests/deployment-package.php CAMINHO_DO_PACOTE`. Saída em `storage/deploy/*.tar.gz`, ignorada pelo Git. O pacote utiliza exclusivamente arquivos rastreados, inclui manifesto SHA-256 e não inclui `.env`, clients.php, sessões, logs, backups, contas ou carreiras. Arquivos de imagens versionados do Hub ficam privados no pacote. Nenhum SQL de instalação é executado no build.

Enviar os pacotes para `apps/<projeto>/incoming/<SHA>/`, extrair, e preparar uma versão inicial privada antes de ativar o site. Para executar os instaladores nessa pasta, criar links `.env` para `shared/.env` e `storage` para `shared/storage`: primeiro mover a pasta storage de mídia do pacote para outro nome e copiar sua mídia para o storage compartilhado. A pasta extraída usada posteriormente por `deploy-cpanel.sh` deve continuar sem esses links; execute a preparação inicial em uma **cópia de instalação separada** em `apps/<projeto>/bootstrap/`. Não instalar arquivos dentro da raiz pública.

### Catálogo sem dados de teste

Decisão do usuário: produção começa sem contas e carreiras locais.

No computador local, executar no Hub:

```powershell
cd C:\inetpub\wwwroot\rumohub
& C:\PHP\php-8.4.26\php.exe -n -d extension_dir=C:\PHP\php-8.4.26\ext -d extension=pdo_mysql -d memory_limit=512M bin/export-production-reference.php
```

A saída privada `storage/production-export/<data>/` contém `reference.sql.gz`, `media.tar.gz` e recibo com contagens e hashes. O SQL tem somente INSERT em `rh_clubes`, `rh_competicoes`, `rh_jogadores`, `rh_elenco_vinculos`, `rh_catalogo_metadata`, `rh_ratings_ea` e `rh_selecoes`, preservando IDs e notas. Não contém CREATE DATABASE, USE, DROP, senhas, administradores, auditorias, nonces nem `rumo10_*`. Mídia inclui uploads editoriais. Os ajustes atuais já materializados nos dados seguem na exportação; o histórico de overrides ligado ao administrador local não é transferido. Recriar ajustes permanentes no painel de produção antes de futuras reimportações.

Com configuração privada preparada, executar **uma vez**, com PHP CLI correto e memória de instalação adequada:

1. Hub: `php bin/install.php` — cria tabelas no banco existente do cPanel; não exige CREATE DATABASE. A opção `--create-database` serve somente para um administrador que explicitamente queira criar o banco configurado.
2. Importar `reference.sql.gz` pelo phpMyAdmin, **apenas em tabelas de catálogo vazias**. Para arquivos acima da cota do painel, usar o cliente MySQL por SSH com arquivo privado de configuração `--defaults-extra-file`, sem senha na linha de comando. Se houver falha, investigar antes de repetir; nunca apagar tabelas de contas/carreiros para reiniciar a carga.
3. Extrair `media.tar.gz` dentro de `apps/rumohub/shared/storage/` e conferir recibo/quantidade. Não importar mídia em public_html. A exportação SQL é uma fotografia transacional; alterações de imagens durante a cópia exigem nova exportação em janela sem edição.
4. Hub: `php bin/client.php register rumo10 IP_DE_SAIDA_REAL` — IP(s) exatos do servidor do jogo, descobertos/verificados com a hospedagem. Não usar IP do navegador, GitHub runner, domínio ou um IP presumido. Copiar o segredo de clients.php ao `.env` privado do jogo por canal seguro. HMAC, escopos e restrição por IP permanecem ativos.
5. Jogo: `php bin/install.php` — instala e semeia somente `rumo10_*`, sem contas demo. Hub mantém seus próprios IDs. Não restaurar dump do banco local inteiro.
6. Hub: `php bin/prepare-admin.php` — administrador novo para produção; usar o link privado de configuração após ativar o site. Nunca publicar `configurar-admin.html`.
7. Publicar **Hub primeiro**, executar smoke HTTPS e verificar administração; depois publicar Rumo10. O pré-check do jogo exige API assinada funcionando e sincroniza apenas o cache privado.

A publicação inicial recusa substituir conteúdo existente nas raízes públicas, exceto `.well-known`, `.user.ini` e `cgi-bin`, que são preservados. Fazer backup privado dos arquivos iniciais da hospedagem e revisar antes de retirá-los. Se o `.htaccess` existente contém o handler PHP gerado pelo cPanel, preservar seu bloco verificado na versão das regras de deploy; não remover às cegas. O deploy recusa divergência posterior de `.htaccess`, exigindo revisão manual. Não ativar o workflow até resolver a configuração real de handler/Options/symlinks.

Ativação manual, a partir de cada pacote extraído sem alterações:

```bash
bash bin/deploy-cpanel.sh rumohub SHA_COMPLETO DIRETORIO_EXTRAIDO PHP_CLI
bash bin/deploy-cpanel.sh rumo10 SHA_COMPLETO DIRETORIO_EXTRAIDO PHP_CLI
```

Os scripts verificam manifesto, PHP/extensões, banco, permissões e catálogo antes de apontar `current`. Não executam migrations, não alteram bindings, não trocam credenciais e não fazem limpeza destrutiva de versões. Atualizações que introduzam migrations precisam de backup e execução manual revisada antes de ativar o código.

## Habilitar GitHub Actions após a primeira validação

Nos **dois repositórios**, em Settings → Secrets and variables → Actions:

| Tipo | Nome | Valor |
|---|---|---|
| Secret | `UOL_SSH_PRIVATE_KEY` | chave privada exclusiva de deploy; pública autorizada no cPanel |
| Secret | `UOL_SSH_KNOWN_HOSTS` | linha(s) known_hosts com chave/fingerprint confirmado pela UOL |
| Variable | `UOL_SSH_PORT` | porta confirmada, padrão 22 somente se confirmado |
| Variable | `UOL_PHP_CLI` | caminho CLI correto, por exemplo fornecido pelo cPanel |
| Variable | `UOL_DEPLOY_ENABLED` | `true` somente quando decidir ativar publicação automática |

Nunca enviar chave privada pelo chat ou commit. Não desativar verificação da chave do servidor e não confiar apenas em ssh-keyscan sem conferir o fingerprint. Autorizar no cPanel chave exclusiva para estes projetos; a conta SSH terá alcance à conta de hospedagem, portanto manter GitHub protegido e acesso restrito. Host e usuário estão fixados nos workflows conforme os dados fornecidos, ainda não verificados por conexão.

O workflow referencia ambiente `production`. Configurar regras de revisão/branch quando disponíveis no plano GitHub. A variável de habilitação deve ser do **repositório**, pois é avaliada antes de iniciar o job. Começar por execução manual e conferir Hub, jogo e banco. Em seguida novos pushes main publicam código; commit apenas local não publica. Runner precisa PHP com Phar/zlib para o build e cURL para smoke; o pacote não precisa de Composer/Node. Chaves e configuração de banco não entram em artefatos públicos.

## Testar e reverter

```bash
php bin/production-smoke.php https://rumohub.rumoacamisa10.com.br rumohub
php bin/production-smoke.php https://www.rumoacamisa10.com.br rumo10
```

Smoke faz só GET, mantém validação TLS e recusa redirecionamentos inesperados. Conferir também login, cookies Secure/HttpOnly/SameSite, cadastro de uma conta de produção autorizada, nova carreira, roleta, escudo, decisão e persistência. Testes completos `tests/http.php` e seus fixtures permanecem restritos ao local; não executá-los contra produção.

Rollback de código: em `apps/<projeto>`, conferir que `previous` aponta a uma versão válida, executar `ln -s "$(readlink previous)" rollback-next` e `mv -Tf rollback-next current`; repetir smoke. Não reverte migrations nem dados. Se smoke automático falhar após ativação, workflow fica vermelho, mas não executa rollback do banco/código automaticamente; investigar e aplicar rollback revisado.

Manter backup privado diário do banco único, shared/.env, clients.php e mídia/uploads, além de ao menos duas versões de código. Limpar incoming/bootstrap/versões antigas apenas após validação, conferindo caminhos e retendo versão atual/anterior; workflow não apaga esses dados automaticamente. Não colocar dumps/backups em raízes públicas nem no GitHub.

## Estado dos testes

Executados localmente em 14/10/2026: Rumo10 HTTP/IIS (91 verificações); Hub HTTP/IIS (22); autenticação do Hub (15); sintaxe dos PHP alterados e Bash (`bash -n`); pacotes de ambos os projetos com manifesto/hash e ausência de configurações privadas (1.874 arquivos do jogo e 2.358 do Hub); exportação SQL/mídia com sete tabelas, 146.747 registros e 2.311 imagens, conferidos por `tests/production-reference.php`. A verificação de arquivo comprimido do Hub requer 512 MB no CLI; o primeiro teste com 128 MB excedeu o limite e foi repetido com sucesso. Isso não altera o limite web do IIS.

Exportação concluída: `C:\inetpub\wwwroot\rumohub\storage\production-export\20261014-215138\`. Arquivos ignorados pelo Git, mantidos privados. Exportações futuras têm outro diretório; somente usar uma exportação com receipt.json e teste de integridade aprovado. A restauração do SQL em outro servidor ainda não foi executada.

Apache/Linux, execução real do deploy/symlinks, login SSH, handler, TLS, cPanel, banco e deploy UOL **ainda não foram testados**. Os workflows não foram executados no GitHub. Esta preparação não confirma publicação. Os testes locais não equivalem à validação da hospedagem.
