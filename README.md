# Rumo à Camisa 10 — protótipo 0.2

RPG textual em português, PHP 8.2+, PDO MySQL, Bootstrap 5.3.3 local e JavaScript nativo. A liga regional é **fictícia**. Os 21 clubes reais são catálogo de referências, sem escudos e sem afirmações de divisão vigente. Nenhuma probabilidade ou alteração definitiva é decidida no navegador.

## Instalação Windows / IIS

O binding existente é `*:80:rumo10.local`, site/pool `rumo10`. PHP 8.4.26 e URL Rewrite foram identificados no computador. Não recrie o site. O document root deve ser `C:\inetpub\wwwroot\rumo10\public`.

```powershell
cd C:\inetpub\wwwroot\rumo10
Copy-Item .env.example .env # SOMENTE se .env ainda não existir
# Edite .env localmente; nunca publique usuário/senha.
& C:\PHP\php-8.4.26\php.exe bin/install.php
# PowerShell como administrador, se o caminho público ainda não estiver correto:
.\bin\configure-iis.ps1
Start-Process http://rumo10.local
```

O instalador **não cria bancos**. Confirme/crie `ecohub` previamente com um administrador MySQL autorizado: `CREATE DATABASE IF NOT EXISTS ecohub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`. A aplicação nunca executa DROP/ALTER/TRUNCATE em tabelas externas. `bin/install.php` faz migrations idempotentes e seed sem substituir cores já editadas. Faça backup apenas de `rumo10_*` antes de uma atualização. Não há reset global nem rollback destrutivo automático.

Caso o PHP local emita avisos de extensões antigas, use o modo isolado, sem alterar o php.ini dos outros projetos:

```powershell
& C:\PHP\php-8.4.26\php.exe -n -d extension_dir=C:\PHP\php-8.4.26\ext -d extension=pdo_mysql bin/install.php
& C:\PHP\php-8.4.26\php.exe -n -d extension_dir=C:\PHP\php-8.4.26\ext -d extension=pdo_mysql -d extension=mbstring tests/run.php --mysql
```

Nesta máquina, a instância original FastCGI apresentou erro de monitoramento e a pasta PHP dava acesso apenas ao pool do UcronIA. O reparo foi restrito ao handler do jogo; foram acrescentadas permissões RX para seu pool no PHP e M apenas em storage para pool/IUSR (PHP local usa impersonação). Para reproduzir em PowerShell administrativo:

```powershell
.\bin\configure-iis.ps1 -RepairFastCgi -SetupRuntimePermissions
```

Em outro IIS, use a instância registrada nesse ambiente. Prefira autenticação anônima com identidade do pool em produção, evitando compartilhamento de IUSR entre sites.
## Jogar e testar

1. Abra [http://rumo10.local](http://rumo10.local), escolha começar e crie uma conta com senha exclusiva (8–72 caracteres). Não existem credenciais demo públicas; o cadastro local é o modo de teste rápido.
2. Crie jogador, cidade/UF e posição. Sorteie o perfil inicial, recarregue e confirme que ele não muda.
3. Escolha uma opção de treino e de pré-jogo. Inicie a partida. Resolva as oportunidades; avance o intervalo e, se estiver no banco ou fora de campo, os segmentos automáticos.
4. Confira placar, estatísticas e `/classificacao`. Resolva o fim de semana para avançar a rodada. A recuperação semanal é mostrada separadamente do sorteio social.
5. Repita até a rodada 14. Sorteie a proposta, aceite ou permaneça. A temporada seguinte inicia com novo calendário e mantém o jogador/XP/histórico.
6. Saia e entre novamente. Crie outra conta para confirmar carreiras independentes. `/api/carreira/estado` retorna apenas a carreira da sessão.

Não há botão que pula rodadas. Para concluir rapidamente a temporada, faça as escolhas sem animação (os resultados são imediatos). Para validar automaticamente 30 temporadas nas seis posições, rode:

```powershell
& C:\PHP\php-8.4.26\php.exe tests/run.php
& C:\PHP\php-8.4.26\php.exe tests/run.php --mysql
& C:\PHP\php-8.4.26\php.exe tests/http.php http://rumo10.local
.\tests\http-smoke.ps1 -BaseUrl http://rumo10.local
```

`--mysql` cria uma conta descartável com e-mail `example.invalid`, testa persistência, replay, versões e transferência, e apaga somente seus próprios registros com filtros por carreira. Nunca usa sua carreira pessoal. O teste sem flag não acessa banco. O teste empírico de probabilidade tem tolerância estatística explícita, sem exigir resultado exato de sorteios.

## Apache / produção

PHP 8.2+ com `pdo_mysql` e `mbstring`, MySQL 8+, `mod_rewrite`, HTTPS e `DocumentRoot /caminho/rumo10/public`. Exemplo dentro do VirtualHost TLS existente:

```apache
DocumentRoot /srv/rumo10/public
<Directory /srv/rumo10/public>
    AllowOverride FileInfo Options Indexes
    Require all granted
</Directory>
```

Ative `APP_ENV=production` e `APP_URL=https://seu-dominio`. FastCGI deve informar `HTTPS=on`; proxy TLS precisa transmitir isso com configuração confiável no servidor (a aplicação não confia em headers arbitrários de proxy). Cookies Secure/HSTS somente em HTTPS; produção em HTTP retorna 503 até o TLS estar configurado. As regras de `public/.htaccess` negam arquivos sensíveis e executam apenas o front controller. Se o provedor negar `Options`, mova `Options -Indexes -MultiViews` para a configuração autorizada do VirtualHost; não remova a proteção sem equivalente. Em hospedagem com raiz fixa, mantenha internos fora dela e publique somente o conteúdo de `public`, ajustando o caminho de bootstrap; valide antes de liberar.

Repita o roteiro HTTP de `tests/http-smoke.ps1 -BaseUrl https://seu-dominio`. Não houve acesso ao Apache de produção; **não foi testado**. Credenciais e logs ficam fora da raiz pública. Prefixo não equivale a segurança: recomenda-se usuário MySQL dedicado com acesso apenas às tabelas `rumo10_*`; as credenciais locais reutilizadas do UcronIA precisam ser substituídas antes de produção.

## Limitações atuais

- NPCs usam placares simplificados; posse, estratégias coletivas e estatísticas completas das equipes ainda não são simuladas. Cinco oportunidades por titular (15, 35, 60, 70 e 80 minutos), menos para reserva/lesão/expulsão.
- S10 e S11 estão preservadas no seed, porém ficam inelegíveis no universo piloto sem patrocinador/rival cadastrado. S12 é o fim de semana válido. A edição de cores é feita via CLI local (bin/club-colors.php), sem painel web administrativo. O catálogo público lê os registros MySQL.
- Transferências são regionais/fictícias e admitem um nível por vez, sem salto para elite. Não há contratos financeiros, goleiros jogáveis, ligas oficiais, recuperação de senha ou verificação de e-mail.
- Sessões e logs usam storage/sessions e storage/logs fora da raiz pública; configure permissões e garbage collection. Sem uploads. URLs supõem instalação na raiz do domínio; APP_URL registra a base por ambiente, links são relativos à raiz.
- Schema concentra identidade de jogador em carreira e usa tabelas de atributos/estados por chave com CHECK/FKs; não há blob opaco de estado. JSON é reservado a efeitos configuráveis, auditoria e resumos históricos.
- Migrations DDL MySQL não são transacionais; comandos CREATE IF NOT EXISTS permitem retomar uma instalação interrompida. A migration 001 é aditiva; futuras mudanças exigem novas versões. Sem downgrade automático.

Evidências de execução e pendências estão em [Docs/10_Testes_Executados.md](Docs/10_Testes_Executados.md). Documentos 01–09 detalham regras e escolhas provisórias.

A interface da carreira mostra resultado verde/vermelho com apresentação breve; detalhes de sorteio podem ser expandidos. Durante a partida, acompanhe seus gols no card de desempenho. Após o jogo, consulte Seus gols no campeonato e Gols por rodada antes de continuar o fim de semana. No celular, o menu fica na parte inferior e as habilidades ficam recolhidas. Para verificar os componentes sem MySQL: `php tests/presentation.php`.

Durante a partida, a tela concentra resultado, efeitos já aplicados, placar, gols/assistências pessoais e a decisão atual. Rádio e estatísticas completas ficam no pós-jogo. O catálogo de clubes é configuração interna via seed/CLI; não há menu nem rota pública /clubes.

### Interface de carreira

A roleta apresenta o resultado salvo pelo servidor com giro e desaceleração. É possível pular a animação; recarregar não sorteia novamente. Preferência por movimento reduzido mostra o resultado diretamente. No pós-jogo e no encerramento, a próxima decisão vem antes dos detalhes recolhidos. O resumo da temporada usa os pontos, posição, gols e assistências da carreira.

Testes adicionais de apresentação: `php tests/wheel.php` e `php tests/season-presentation.php`.
