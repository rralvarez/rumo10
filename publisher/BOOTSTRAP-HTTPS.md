# Receptor HTTPS do Rumo10 | Ativação inicial

## Regra irrevogável para releases comuns

**O backup manual é responsabilidade do proprietário e ocorre antes do merge.**
O publicador NÃO solicita confirmação de backup, não consulta comentário de
backup, não exige status `rumo10/manual-backup` e não executa `mysqldump`
para releases Classe A. Não há GitHub App de backup a instalar.
A autorização do merge é uma decisão humana; o receptor verifica o merge
realizado pelo proprietário, a política da release e o artefato oficial.

O fluxo permanente é: PR revisada, CI aprovado, backup manual quando o
proprietário decidir, merge na `main`, Actions e publicação HTTPS automática
via Cron do receptor. Sem ZIP e sem ajustes de Cron a cada release.

Mudanças de banco de dados (Classe B) conservam salvaguardas de integridade
e precisam de procedimento de migração autorizado separadamente; **isso
não pode bloquear a UI-001, que não tem migrations**.

## O que já está aprovado e o que não deve ser repetido

- UI-001 já está na `main`: `fb03b614c337845671cf6cbf1eb36abe01308bfd`.
- Workflow oficial: run `43929021873`.
- Artefato oficial: `14257772333`.
- O proprietário informou que **o backup manual já foi realizado**.
  Não pedir outro backup nem criar declaração artificial em PR #8.
- Manter PR #9 em draft e NÃO mesclar antes do piloto UI-001.

## Pré-verificação da UOL, antes de qualquer escrita

Verificar o estado real de `receiver-config.json`, `installed.json`,
`journal.json`, `drain-session.json`, mutex, RequestDrain, Crons e
permissões. Confirmar o estado estável e a cobertura dos dois projetos.
Não apagar locks nem `closed.json` manualmente. Os endpoints públicos
`public_smoke_urls.rumo10` e `public_smoke_urls.rumohub` devem ser
HTTPS reais, em hosts distintos, com HTTP 200 direto.

O token de leitura do GitHub deve comprovar a release e o merge do
proprietário. O token para reportar status de implantação é separado.
Não cadastrar GitHub App ou status check de backup. As permissões
dos arquivos privados permanecem 0600. A configuração deve conter
`main_automation`, com os campos exigidos pelo código, sem atribuir
permissões ou IDs inventados.

## Fase 1: bootstrap isolado e piloto UI-001, sem merge

O pacote de bootstrap é gerado do HEAD **validado** da PR #9 usando
`publisher/bin/package-receiver-bootstrap.py`, após CI aprovado.
Confirmar SHA-256 do ZIP e do manifesto. Extraí-lo somente em uma
pasta privada separada, jamais sobre `publisher/bin` e
`publisher/lib` ativos, por exemplo:

`/home/rumoacame144a99e/dep058-staging/receiver-bootstrap`

Desarmar e verificar ausência de Crons paralelos durante a intervenção
autorizada. Na configuração privada, habilitar temporariamente
`main_automation.allow_ui001_bootstrap=true`. Essa flag autoriza apenas
o trio explícito da UI-001, sem exigir qualquer declaração de backup.

Executar o bootstrap UMA vez usando uma tarefa temporária do cPanel:

```sh
/opt/alt/php84/usr/bin/php /home/rumoacame144a99e/dep058-staging/receiver-bootstrap/publisher/bin/install-and-publish.php --config=/home/rumoacame144a99e/rumo10-production-v062-ee8f175/receiver-config.json --manifest-sha256=<HASH_REAL_DO_MANIFESTO>
```

Exigir resultado `installed-held`, identidade do manifesto e
journal sem inconsistências. Não prosseguir em caso de erro.

Enquanto a ponta de `main` for exatamente a UI-001, executar uma
única vez pelo Cron temporário:

```sh
/opt/alt/php84/usr/bin/php -d memory_limit=512M /home/rumoacame144a99e/rumo10-production-v062-ee8f175/publisher/bin/controlled-pull.php --config=/home/rumoacame144a99e/rumo10-production-v062-ee8f175/receiver-config.json --mode=apply --discover --ui001-bootstrap
```

Para comprovar sucesso, validar `installed.json`, `journal.json`,
`drain-session.json`, commit, run, artefato, RequestDrain reaberto,
locks liberados e ambos os URLs públicos retornando HTTP 200.
Depois do piloto, retirar `allow_ui001_bootstrap`.

Se ocorrer falha após início da publicação, usar somente a recuperação
oficial `--mode=apply --recover`, com inspeção da sessão; não
substituir arquivos operacionais nem eliminar travas manualmente.

## Fase 2: integrar a PR #9 como infraestrutura, nunca como release do jogo

Com UI-001 instalada e validada, revisar o HEAD e o CI da PR #9.
O proprietário autoriza o merge via **Create a merge commit**.
O receptor reconhece estritamente esse PR de infraestrutura quando
o primeiro pai é a UI-001, o segundo pai é o HEAD revisado, a árvore
de merge é idêntica à da PR e a comparação não inclui alterações de
aplicação ou banco. O resultado `infrastructure-held` **não**
significa aplicação publicada e não produz recibo fictício.

## Fase 3: automação permanente

Preservar ruleset de `main` exigindo PR e `rumo10-ci-gate`,
sem adicionar `rumo10/manual-backup`. Nenhuma GitHub App de backup
é requerida. Cada release comum segue a revisão e a autorização
humana antes do merge; o publicador não reabre a discussão de backup.

Após validar configuração, estado do receptor, estabilidade dos dois sites
e ausência de Cron duplicado, habilitar somente o Cron oficial:

```sh
/opt/alt/php84/usr/bin/php -d memory_limit=512M /home/rumoacame144a99e/rumo10-production-v062-ee8f175/publisher/bin/controlled-pull.php --config=/home/rumoacame144a99e/rumo10-production-v062-ee8f175/receiver-config.json --mode=apply --discover >> <LOG_PRIVADO_REAL> 2>&1
```

A partir daí não há nova instalação de publisher por release.
Não afirmar disponibilidade produtiva apenas com CI verde: ela deve
ser comprovada por recibo e HTTP reais dos dois sites.

**Limites:** os testes de CI não substituem a verificação das condições
reais do cPanel: paths, permissões, limites de PHP, Cron, endpoints,
sessões, mutex e integridade atual. Não agir em produção sem autorização.
