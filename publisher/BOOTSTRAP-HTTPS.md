# Bootstrap privado do receptor HTTPS | Sequência corrigida

Esta é a única instalação excepcional do receptor. Depois dela, cada release
funcional deve seguir: PR e CI aprovados, backup manual confirmado pelo proprietário,
status obrigatório da GitHub App, merge na `main`, Actions e Cron HTTPS automático.

**Nunca mesclar a PR #9 antes do piloto UI-001.** A exceção UI-001 só é válida
enquanto a ponta da `main` for
`fb03b614c337845671cf6cbf1eb36abe01308bfd` (run `43929021873`,
artefato `14257772333`).

## Preparação sem alterações produtivas

1. Revisar o estado real do receptor, `installed.json`, journal, RequestDrain, locks,
   hooks, Cron e caminhos dos dois projetos. Confirmar que não existe sessão pendente.
2. Conferir que `receiver-config.json` e os arquivos de token são privados (0600),
   que `main_automation` está configurado e que `public_smoke_urls` contém
   **dois hosts HTTPS distintos e reais** para `rumo10` e `rumohub`, respondendo
   diretamente HTTP 200. Se o segundo host não existir, não inventar um endereço
   e não ativar o receptor.
3. Conferir que o pacote de bootstrap vem do **HEAD final da PR #9**, sem alterações
   posteriores. Construir o ZIP com `publisher/bin/package-receiver-bootstrap.py`,
   conferir o manifesto e os SHA-256. O ZIP não deve ser extraído sobre
   `publisher/bin` ou `publisher/lib` ativos.
4. Verificar que nenhum Cron antigo ou wrapper poderá iniciar outra publicação
   durante a janela. Não remover locks ou arquivos de sessão pendentes manualmente.

## Fase A: publicar UI-001 ANTES do merge de PR #9

5. Solicitar ao proprietário um **backup manual real** e a autorização específica
   para o piloto, sem presumir que um backup anterior foi realizado.
6. Enquanto `main` continuar em `fb03b614...`, o proprietário publica o comentário
   exato abaixo no PR #8, após confirmar o backup. Não editar o comentário:

   ```text
   RUMO10 BACKUP CONFIRMADO UI-001 POS-MERGE PR #8 fb03b614c337845671cf6cbf1eb36abe01308bfd RUN 43929021873 ARTEFATO 14257772333
   ```

7. Com a autorização produtiva específica, extrair o ZIP em diretório privado
   **separado** do publisher ativo, por exemplo
   `/home/rumoacame144a99e/dep058-staging/receiver-bootstrap`.
   Configurar `allow_ui001_bootstrap=true` temporariamente no
   `receiver-config.json`, somente após validar a integridade do arquivo.
8. Executar o bootstrap uma única vez pelo Cron temporário do cPanel:

   ```sh
   /opt/alt/php84/usr/bin/php /home/rumoacame144a99e/dep058-staging/receiver-bootstrap/publisher/bin/install-and-publish.php --config=/home/rumoacame144a99e/rumo10-production-v062-ee8f175/receiver-config.json --manifest-sha256=<SHA256_REAL_DO_MANIFESTO>
   ```

   Verificar `installed-held`, journal e hash da entrada antes de prosseguir.
   Uma falha exige inspeção do journal e do procedimento de recuperação, nunca
   repetição indiscriminada nem substituição manual de arquivos ativos.

9. Com `main` ainda apontando à UI-001, executar **uma vez**:

   ```sh
   /opt/alt/php84/usr/bin/php -d memory_limit=512M /home/rumoacame144a99e/rumo10-production-v062-ee8f175/publisher/bin/controlled-pull.php --config=/home/rumoacame144a99e/rumo10-production-v062-ee8f175/receiver-config.json --mode=apply --discover --ui001-bootstrap
   ```

   Verificar no receptor commit, run e artefato exatos, `installed.json`,
   `journal.json`, `drain-session.json`, RequestDrain aberto, travas ausentes
   e HTTP 200 real de Rumo10 e RumoHub. Remover `allow_ui001_bootstrap`
   depois do sucesso. Não declarar sucesso sem essas provas.

## Fase B: integrar PR #9 como atualização EXCLUSIVA da infraestrutura

10. Com UI-001 validada em produção, solicitar novo backup manual e autorização
    antes do merge da PR #9. Marcar a PR como pronta somente após a revisão do HEAD
    final e CI verde. Mesclar via **Create a merge commit**, sem squash.
11. O merge da PR #9 muda a ponta de `main`, mas **não corresponde a outra
    atualização de aplicação**. O receptor tem uma exceção de no-op restrita a:
    PR #9 mesclada pelo proprietário, primeiro pai UI-001, segundo pai HEAD
    revisado da PR, árvore do merge idêntica à da PR e comparação sem arquivos
    de aplicação, banco ou outras superfícies não autorizadas. Exige também
    recibo da UI-001 validado e os dois sites disponíveis.
    O resultado é `infrastructure-held` e status de implantação **pending**,
    nunca um recibo fictício de aplicação publicada.
12. Se essa identidade não for comprovada, não ativar o Cron contínuo. Não
    alterar o `installed.json` manualmente. Após a PR #9, nenhuma outra
    PR recebe essa exceção.

## Fase C: ativar o bloqueio efetivo de backup antes de merges futuros

13. Criar e instalar uma GitHub App dedicada ao repositório, com apenas
    **Commit statuses: Read and write**; armazenar sua chave privada no segredo
    `RUMO10_BACKUP_APP_KEY` do environment `rumo10-backup-approval`,
    limitado à branch padrão. Configurar `RUMO10_BACKUP_APP_ID` e
    `RUMO10_OWNER_ID=35331184`. Nunca compartilhar a chave no chat.
14. O workflow `.github/workflows/manual-backup-approval.yml` passa a existir
    na branch padrão após o merge da PR #9. Em uma PR descartável de teste,
    validar a emissão do status da App para o HEAD exato após o comentário
    autenticado e depois habilitar no ruleset de `main` o status obrigatório
    `rumo10/manual-backup` associado à própria App, preservando
    `rumo10-ci-gate`, PR obrigatória, política strict e bypass vazio.
    Demonstrar que outro HEAD sem comentário/status fica impedido de mesclar.
    Não mesclar a PR de teste.
15. Configurar no receptor os IDs reais de App, bot e ruleset. Conferir por
    leitura autenticada a regra ativa, sem bypass e com a App fixada como
    origem do status. Uma release funcional futura deve comprovar o mesmo
    PR e SHA antes de ser publicada.

## Fase D: Cron permanente, sem reinstalação por release

16. Só depois das fases anteriores, ativar o Cron oficial direto:

    ```sh
    /opt/alt/php84/usr/bin/php -d memory_limit=512M /home/rumoacame144a99e/rumo10-production-v062-ee8f175/publisher/bin/controlled-pull.php --config=/home/rumoacame144a99e/rumo10-production-v062-ee8f175/receiver-config.json --mode=apply --discover >> <CAMINHO_REAL_DE_LOG_PRIVADO> 2>&1
    ```

    Remover os Crons temporários e verificar que nenhuma tarefa duplicada existe.
    Não colocar um timeout externo capaz de encerrar uma sessão em manutenção.

Para uma release Classe A futura, nenhum dump MySQL é executado após o merge;
para uma Classe B, o preflight integral do banco, backup consistente e regras de
recuperação continuam exigidos. O Cron não é alterado por release.

**Limites de validação:** o CI e as fixtures locais não comprovam permissões,
quota, limites de execução nem endpoints reais da UOL. A instalação inicial
depende da conferência dos dois sites e dos recibos produtivos, sem afirmar
zero indisponibilidade quando o RequestDrain efetivamente fecha o gate.
