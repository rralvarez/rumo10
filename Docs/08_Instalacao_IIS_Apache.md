# Instalação IIS e Apache
Regra confirmada: document root public/ nos dois servidores, mesmas rotas e proteção adicional. IIS tem web.config; Apache tem .htaccess; o front controller implementa headers, sessão, CSRF, 404/405 e autenticação.

Proposta provisória: implantação no domínio raiz; TLS terminado diretamente ou FastCGI com HTTPS configurado de modo confiável. Sem confiar em X-Forwarded-Proto enviado pelo cliente.

Implementação atual: veja comandos completos no README. bin/configure-iis.ps1 verifica binding e index antes de alterar somente o caminho físico. -RepairFastCgi usa uma instância PHP 8.4 já registrada sem monitorChangesTo e altera apenas o handler local. Não há mudanças globais no php.ini. Permissões de execução foram acrescentadas somente ao pool rumo10 no PHP; gravação limitada a storage/sessions e storage/logs para pool/IUSR. Root web.config bloqueia o repositório caso o caminho público esteja incorreto; public/web.config limpa regras herdadas para aplicação própria. Apache exige mod_rewrite e permissões FileInfo/Options/Indexes; configure equivalentes no VirtualHost se hospedagem bloquear Options. Apenas index.php pode ser executado no public. Arquivos internos, ponto, SQL/INI/log/backup e listagem são bloqueados.

Cookies HttpOnly/SameSite=Lax, Secure em HTTPS; strict session mode/regeneração pós-login; HSTS somente em produção HTTPS. CSP self, assets locais e escaping HTML. Produção HTTP retorna indisponibilidade até TLS correto. Rate limit 10 tentativas por IP+e-mail/15 minutos para cadastro/login. Logs contêm somente classe/código de erro, sem senha, DSN ou stack trace.

Pendências: Apache e TLS de produção não disponíveis para teste. Provedor deve ajustar permissões e HTTPS antes de deploy. Não criar publicação insegura com internos dentro da raiz fixa.

Critérios de teste: tests/http-smoke.ps1 com BaseUrl IIS/Apache, login autenticado, CSRF inválido, rota/método inválidos, assets e arquivos internos protegidos. Registrar comportamento Secure/HSTS em ambiente HTTPS real antes de afirmar validação de produção.