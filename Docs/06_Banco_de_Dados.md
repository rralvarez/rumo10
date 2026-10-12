# Banco de dados
Regra confirmada: banco ecohub compartilhável, apenas tabelas rumo10_ e sem reset global. Credenciais locais ignoradas pelo Git, arquivos internos fora de public.

Proposta provisória: schema relacional enxuto, identidade do jogador na carreira; valores técnicos e estados em linhas consultáveis por habilidade/indicador. Estatísticas e partidas não são JSON. Histórico resumido e auditoria usam JSON como snapshots.

Implementação atual: migration 001 com InnoDB/utf8mb4, contas, limites de login, clubes, carreiras, atributos, estados, temporadas, times, partidas, estatísticas, eventos, situações, opções, decisões, ofertas e transferências. Chaves únicas por e-mail, conta ativa, temporada, partida/mando, comando e chave idempotente. FKs garantem times/temporada e entidades relacionadas. CHECKs limitam estados, atributos, minutos, passes e oposição diferente. DDL idempotente; seed transacional. Toda operação da carreira usa prepared statements e bloqueio de linha. Instalação lista quantidade de tabelas externas sem imprimir nomes ou dados. Registro da migration aplicado só após concluir CREATEs.

O banco ecohub não existia no servidor indicado pelo .env de UcronIA. Criado com autorização explícita; inicialmente zero tabelas externas. Não foram alterados outros bancos. As credenciais foram copiadas somente para .env local e não constam no Git.

Pendências: usuário MySQL dedicado com menor privilégio; versão 002 para futuras alterações, ferramenta de backup por prefixo e rollback aditivo manual. MySQL DDL não oferece transação global, por isso instalação retoma CREATE IF NOT EXISTS. Nenhum script DROP de banco é fornecido.

Critérios de teste: duas instalações consecutivas, invariantes das tabelas e carreira descartável com cleanup filtrado. Nunca rodar migrations contra banco desconhecido sem revisão local.