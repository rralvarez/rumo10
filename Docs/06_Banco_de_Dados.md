# Banco de dados
Regra confirmada: banco ecohub compartilhável, apenas tabelas rumo10_ e sem reset global. Credenciais locais ignoradas pelo Git, arquivos internos fora de public.

Proposta provisória: schema relacional enxuto, identidade do jogador na carreira; valores técnicos e estados em linhas consultáveis por habilidade/indicador. Estatísticas e partidas não são JSON. Histórico resumido e auditoria usam JSON como snapshots.

Implementação atual: migration 001 com InnoDB/utf8mb4, contas, limites de login, clubes, carreiras, atributos, estados, temporadas, times, partidas, estatísticas, eventos, situações, opções, decisões, ofertas e transferências. Chaves únicas por e-mail, conta ativa, temporada, partida/mando, comando e chave idempotente. FKs garantem times/temporada e entidades relacionadas. CHECKs limitam estados, atributos, minutos, passes e oposição diferente. DDL idempotente; seed transacional. Toda operação da carreira usa prepared statements e bloqueio de linha. Instalação lista quantidade de tabelas externas sem imprimir nomes ou dados. Registro da migration aplicado só após concluir CREATEs.

O banco ecohub não existia no servidor indicado pelo .env de UcronIA. Criado com autorização explícita; inicialmente zero tabelas externas. Não foram alterados outros bancos. As credenciais foram copiadas somente para .env local e não constam no Git.

Pendências: usuário MySQL dedicado com menor privilégio; versão 002 para futuras alterações, ferramenta de backup por prefixo e rollback aditivo manual. MySQL DDL não oferece transação global, por isso instalação retoma CREATE IF NOT EXISTS. Nenhum script DROP de banco é fornecido.

Critérios de teste: duas instalações consecutivas, invariantes das tabelas e carreira descartável com cleanup filtrado. Nunca rodar migrations contra banco desconhecido sem revisão local.
## Versão 0.3 / migration 002

A migration 002 cria rumo10_competicoes, rumo10_clubes_oficiais, rumo10_universos e rumo10_carreiras_arquivadas. Torna rumo10_carreiras.conta_id anulável: a restrição UNIQUE continua permitindo uma carreira ativa por conta, enquanto as anteriores mantêm todos seus registros e ownership em rumo10_carreiras_arquivadas. Restaurar exige correspondência da conta e bloqueia a transação.

Carreiras oficiais usam snapshot JSON do universo no servidor, com fases, calendários, participações e resultados. Isso substitui a representação exclusivamente relacional das partidas para novas carreiras; atributos, estados, metadados da carreira e decisões auditadas continuam relacionais. A gravação do snapshot e da auditoria ocorre na mesma transação; versão, chave idempotente e row lock continuam obrigatórios. A carreira legada usa o schema 001 integralmente.

Instalação existente: php bin/install-official.php. Instalação nova: php bin/install.php aplica as duas versões. A alteração é limitada a rumo10_*; não muda configuração IIS nem credenciais. Não há limpeza de contas ou carreiras durante instalação.
