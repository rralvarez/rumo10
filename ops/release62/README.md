# Entrega 62 — controles isolados

O TAR é uma cópia binária exata do pacote aprovado, não um build deste checkout. SHA-256 caec057a8acc65ce58df9f7f4243082fdd8fe069e445c57f937b68609d633378. Git blob + commit identificam a origem; o controlador verifica novamente o digest antes de qualquer acesso remoto.

Workflow exclusivamente manual/reutilizável, sem push/schedule/PR. verify-only não importa nem invoca o escritor e só usa leituras FTPS. Os dois workflows antigos precisam permanecer disabled_manually. A execução inicial é apenas verify-only.

Deploy exige mode=deploy, RELEASE62_ALLOW_DEPLOY=true, approval=PUBLISH:<SHA aprovado>, manutenção externa ativa e recibo de backup fresco. O recibo é atestado do operador, não prova independente de que um backup existiu: produzir e restaurar isoladamente os backups antes de fornecê-lo. Não inserir credenciais, caminhos privados ou dumps no input. A revisão humana é obrigatória antes de habilitar o gate.

Schema do recibo: schema=release62-backup-v1, created_at_unix, production_manifest_sha256, maintenance_sha256, database_archive_sha256, files_archive_sha256, recovery_archive_sha256, isolated_restore_verified=true, writes_drained_verified=true. Janela máxima de 30 minutos. Hashes reais exigidos. O runner compara controle de manutenção e base, mas não recebe os backups privados.

O deploy mantém maintenance.lock; QA autenticada, flag v3 e liberação posterior são etapas operacionais separadas. Nenhuma remoção de manutenção em always. Em erro o diário e lock de deploy devem ser preservados. Não restaurar dumps antigos nem escritor antigo de XP.

GitHub requer a entrada workflow_dispatch na default branch. Esta referência isolada não autoriza alterar main. Preparar uma entrada mínima que chame este workflow com commit SHA fixo, secrets herdados e default verify-only. Nenhuma aplicação deve ser recompilada ou publicada pelo workflow antigo.
