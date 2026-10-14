# Banco único RumoHub — 14/10/2026

O banco dos projetos de futebol agora é **rumohub**. Foram movidas todas as 27 tabelas `rumo10_*` do antigo `ecohub`, reunindo-as com as 15 tabelas `rh_*` já existentes: 42 tabelas no total.

- `rh_*`: catálogo comum, clubes, jogadores, competições, seleções e administração/API do Hub.
- `rumo10_*`: contas, planos, carreiras, resultados, decisões, atributos, estados, histórico e tabelas legadas de compatibilidade do jogo.
- Os próximos jogos terão prefixos operacionais próprios no mesmo banco e reutilizarão o catálogo do Hub. Compartilhar o banco não exige misturar tabelas de carreiras dos jogos.

Os catálogos legados `rumo10_*` também foram movidos, preservando a compatibilidade dos instaladores e referências antigas. Não foram apagados ou fundidos nesta migração; o catálogo central em uso continua sendo `rh_*` pelo Hub. Uma futura remoção dos caches/legados exige conferir suas dependências separadamente.

## Execução local e preservação

Ambiente confirmado: MySQL 8.0.44 local; ambos os sites usam o mesmo servidor. Não havia triggers ou views dependentes das tabelas do jogo nem vínculos com tabelas de outros projetos. Não havia colisões de nomes no destino.

`bin/migrate-to-rumohub.php` fez o inventário, ativou manutenção temporária somente no Rumo10 e gerou backup privado com `mysqldump` oficial. Movimentou as 27 tabelas em uma única instrução `RENAME TABLE`, preservando IDs, índices, dados e relacionamentos. O conteúdo de cada tabela, ordenado por chave primária, foi comparado por SHA-256 antes/depois. Todas as comparações passaram. Os vínculos apontam para `rumohub`, sem registros órfãos. A manutenção foi removida após atualizar e validar a conexão.

O arquivo `.env` local do Rumo10 agora usa `DB_DATABASE=rumohub`. A configuração do Hub já usava esse banco. Credenciais, dados de usuários, backup SQL e cópia da configuração não são publicados nem versionados. O padrão de conexão e `.env.example` foram atualizados.

Backup nesta máquina: `C:\inetpub\wwwroot\rumo10\storage\backups\migration-20261014-211610-0501eff8\`. Contém `rumo10.sql`, `.env.before`, manifestos de contagens/hashes e confirmação de conclusão. Fica fora de `public/`, ignorado pelo Git. O arquivo temporário de credenciais do dump foi removido. Não foi realizado um ensaio de restauração do dump; a validação executada conferiu a migração e o conteúdo preservado.

`ecohub` ficou sem tabelas. O schema vazio foi preservado; nenhum banco de outros projetos foi apagado. Não há duas cópias ativas das tabelas do Rumo10. Não foi necessário alterar bindings, PHP, raiz pública, `.htaccess` ou `web.config`. IIS local continua funcionando; Apache e produção não foram acessados.

## Uso do procedimento

Em uma instalação antiga equivalente, com origem `ecohub`, destino `rumohub` já instalado e MySQL 8 no mesmo servidor:

```powershell
php bin/migrate-to-rumohub.php
php bin/migrate-to-rumohub.php --apply
```

O primeiro comando é somente leitura. O segundo exige permissão de leitura/movimentação das tabelas e gravação da configuração/backup locais. Em outra máquina, informe `--mysqldump=/caminho/do/mysqldump` oficial. O procedimento recusa colisões, engines não suportados e dependências externas, faz backup antes de mover e tenta retornar as tabelas à origem se a validação falhar. Se esse retorno não concluir, mantém a manutenção para investigação. Não executa DROP DATABASE ou renomeia tabelas de outros projetos. Não é um migrador entre servidores diferentes.

O procedimento local já foi executado; repeti-lo com `.env` apontando para `rumohub` não move as tabelas novamente. Instalações novas devem usar `rumohub` desde o início. A configuração de produção e eventuais prefixos impostos pela hospedagem precisam ser validados antes da implantação. Restrinja cada usuário MySQL às tabelas necessárias ao seu jogo/serviço; a unificação não exige privilégios globais.

## Testes após a migração

- Rumo10 `tests/unified-database.php`: 66 verificações de banco, 27+15 tabelas, ausência de cópia antiga, referências e integridade.
- Rumo10 `tests/http.php http://rumo10.local`: 79 verificações de cadastro, autenticação, carreira, partidas, replay, temporadas, modos, transferências, limites e encerramento no IIS.
- Rumo10 `tests/hub.php`: integração com catálogo, elenco e escudo de Messi passou.
- Rumo10 `tests/match-momentum.php --mysql`: 2.425 verificações de oportunidades e persistência/replay passaram.
- Rumo10 `tests/retirement-storage.php`: 28 verificações do histórico e encerramento passaram.
- RumoHub `tests/http.php http://rumohub.local`: 22 verificações de endpoints, proteção e replay passaram.
- RumoHub `tests/imports.php`: 21.079 verificações de catálogo, identidade, ratings, seleções e mercados passaram.

As contas descartáveis dos testes foram removidas. Carreiras humanas foram preservadas, sem simulação ou reset. Os testes podem alterar sequências AUTO_INCREMENT ao criar/remover fixtures; isso não muda os IDs dos registros existentes.

Fonte técnica do procedimento: [MySQL 8.0 — RENAME TABLE](https://dev.mysql.com/doc/refman/8.0/en/rename-table.html).

## Acesso

Continue normalmente em [Rumo à Camisa 10](http://rumo10.local) e [RumoHub](http://rumohub.local). Contas, senhas e carreiras existentes são as mesmas. Não é necessário iniciar outra carreira.
