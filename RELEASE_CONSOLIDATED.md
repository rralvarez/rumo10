# Rumo10 consolidated v062 release branch

Authorized production branch: codex/production-consolidated-v062. Main and mundializacao branches are not promoted.

First application package is frozen, 8044ad5ebf8bdf3e6af33cadbae081af65adea5ef02b8b7d2e2720cf4cfb7458. The CI wraps exactly those bytes when the runtime is identical; future changes are built from immutable Git objects and restricted to the registered runtime paths. No private files or tokens are included.

The workflow publishes an immutable Actions artifact only after syntax, targeted regression, native MySQL compatibility and publisher safety succeed. Cron installation and apply are separate operational controls and remain disabled until configured and checked on the host.

Policy A: compatible runtime update, successful compatibility job and HTTP admission control. Policy B: only reviewed incremental SQL hashes, consistent backup and migration journal. Policy C: unknown SQL, deletions or protected contracts stop before install and require a specific safe window. Passing CI does not by itself enable production apply.

Penalty stage is not supported by the previous game. Recovery after this version begins serving must finish this version or use an explicitly compatible forward package; do not restore the previous writer over new penalty saves. No database rollback over recent progress.
