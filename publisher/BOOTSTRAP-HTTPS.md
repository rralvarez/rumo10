# Bootstrap privado do receptor HTTPS

O ZIP é para a ativação inicial do receptor. Ele contém o bootstrap, a entrada oficial e a geração de bibliotecas. Nenhum runtime de aplicação, perfil privado ou segredo integra o ZIP. O workflow consolidado e o artefato UI-001 permanecem intactos.

## Pré-condições administrativas

- Titular deve autorizar a janela produtiva. Confirmar a identidade do recibo ativo, o diretório configurado em private, a cobertura dos dois projetos no RequestDrain, ausência de sessão pendente e hooks/pins existentes. Confirmar que os crons do publicador anterior e do wrapper estão desarmados e que nenhum agendamento alternativo inicia esses processos.
- Preservar receiver-config.json e seus tokens. Em main_automation, manter o owner_id e o token de status de deploy separado do token de leitura. Configurar public_smoke_urls com chaves rumo10 e rumohub, cada uma com URL HTTPS canônica de host distinto que responda diretamente 200. O token de leitura precisa ler artefatos, PRs/comentários, commit statuses e o ruleset completo, inclusive bypass_actors.
- Para o piloto, habilitar allow_ui001_bootstrap exclusivamente na configuração privada. O procedimento excepcional exige um novo backup manual e a declaração exata abaixo, criada sem edição pelo proprietário no PR #8. Ela registra autorização posterior ao merge.

    RUMO10 BACKUP CONFIRMADO UI-001 POS-MERGE PR #8 fb03b614c337845671cf6cbf1eb36abe01308bfd RUN 43929021873 ARTEFATO 14257772333

## Ativação inicial pelo cPanel

Usar as Tarefas Cron temporárias de execução única do cPanel. Conferir o SHA-256 do ZIP localmente antes de transferir. Extrair em diretório privado separado do publisher ativo. O exemplo usa /home/rumoacame144a99e/dep058-staging/receiver-bootstrap. Manter o Cron contínuo desarmado durante o bootstrap.

    /opt/alt/php84/usr/bin/php /home/rumoacame144a99e/dep058-staging/receiver-bootstrap/publisher/bin/install-and-publish.php --config=/home/rumoacame144a99e/rumo10-production-v062-ee8f175/receiver-config.json --manifest-sha256=<SHA256_DO_MANIFESTO>

Remover a tarefa temporária assim que houver resultado. Para impedir repetição, prefixar cada comando temporário com um mkdir de marcador exclusivo na pasta privada de staging seguido de &&. Usar marcadores distintos para bootstrap, apply e recuperação. Conferir installed-held. O bootstrap troca atomicamente a entrada existente para uma geração verificada em private. O backup privado e o journal bootstrap.json registram a entrada anterior e os estados do receptor. Um bootstrap interrompido antes da publicação pode ser retomado com o mesmo comando e manifesto. Para reverter a entrada antes do apply, repetir com --rollback. O próprio bootstrap exige o gate aberto, nenhum lock pendente e os estados originais do receptor intactos para reverter.

Após a autorização produtiva, a declaração do proprietário e a conferência de que a main ainda aponta para o release aprovado, usar a entrada oficial:

    /opt/alt/php84/usr/bin/php -d memory_limit=512M /home/rumoacame144a99e/rumo10-production-v062-ee8f175/publisher/bin/controlled-pull.php --config=/home/rumoacame144a99e/rumo10-production-v062-ee8f175/receiver-config.json --mode=apply --discover --ui001-bootstrap

O piloto exige Classe A determinada pelo inventário instalado e pela política real. Uma baseline que transforme o delta em Classe B ou C bloqueia a exceção. O receptor verifica o trio esperado antes do apply. Em falha posterior ao início de apply, conservar a geração instalada e usar a recuperação oficial com --mode=apply --recover --ui001-bootstrap após verificar a sessão. Conferir installed.json, journal.json, drain-session.json, ausência das travas e os dois HTTP 200. Só então retirar allow_ui001_bootstrap. Se a main avançar antes do piloto, o comando excepcional recusa o release em vez de selecionar outro artefato.

## Controle pré-merge para releases seguintes

1. Integrar o workflow manual-backup-approval.yml em mudança de infraestrutura autorizada, após o piloto, preservando o workflow de produção existente. Ele roda em issue_comment na branch padrão main. Criar uma GitHub App dedicada a este repositório, com Commit statuses write, e guardar sua chave em segredo do environment rumo10-backup-approval. Restringir o environment a refs/heads/main. Definir RUMO10_OWNER_ID e RUMO10_BACKUP_APP_ID. A chave da App de backup fica no GitHub, separada dos tokens do receptor.
2. No ruleset de main, manter PR obrigatório, Create a merge commit, atualização da base, rumo10-ci-gate e lista de bypass vazia. Adicionar rumo10/manual-backup como status obrigatório e selecionar a App dedicada como origem. Configurar backup_app_id, backup_app_bot_id e backup_ruleset_id com os IDs reais na política privada. Confirmar via GET /repos/rralvarez/rumo10/rulesets/<ID> que a credencial de leitura consegue ler a regra ativa completa.
3. Demonstrar em PR de teste que, mesmo com CI verde, a proteção impede o merge antes do status da App. Após testes e aprovação do SHA, solicitar backup manual ao proprietário. Ele registra o comentário abaixo com número e HEAD atuais. A declaração é também a autorização explícita de merge para esse SHA. A App só emite o status se houver um único PR aberto para aquele HEAD e destino no momento da confirmação. O workflow exige CI anterior bem-sucedido para a PR e registra o status com link ao comentário imutável. Um novo SHA requer nova confirmação. O proprietário usa Create a merge commit.
4. Antes de ativar releases Classe B, apontar o hook privado de backup existente para bin/mysql-backup-hook.php dentro da geração registrada no recibo do bootstrap e aplicar o SHA-256 do manifesto. Preservar os argumentos do perfil. Conferir em ensaio local a exportação completa com os privilégios reais. Quando houver arquivo de opções, utilizar um único grupo [client] com parâmetros de conexão.
5. O receiver lê novamente a origem, o ruleset, o status e o comentário pela API; o status é por SHA no GitHub e a publicação também exige que seu vínculo corresponda ao PR efetivamente mesclado. Ativar o Cron contínuo somente após provar o controle. A classe real é avaliada por release e não altera o Cron nem reinstala o publicador.

    RUMO10 BACKUP CONFIRMADO E MERGE AUTORIZADO PR #<NUMERO> HEAD <SHA_COMPLETO>

Cron contínuo com caminho de log privado do perfil, sem timeout externo que mate um apply e sem wrapper:

    /opt/alt/php84/usr/bin/php -d memory_limit=512M /home/rumoacame144a99e/rumo10-production-v062-ee8f175/publisher/bin/controlled-pull.php --config=/home/rumoacame144a99e/rumo10-production-v062-ee8f175/receiver-config.json --mode=apply --discover >> <LOG_PRIVADO> 2>&1

O tempo limite do ambiente e a possibilidade de interrupção do processo pelo host ainda precisam ser verificados. Um erro retém o journal e as travas que o procedimento oficial exige. O receptor só publica status de sucesso após recibo, journal, gate e smoke públicos coerentes.
