# Rumo à Camisa 10 — protótipo 0.10

**Textos em português brasileiro:** revisão das 214 situações e 856 alternativas, com escolhas mais claras e termos de futebol mais naturais. [Alterações e verificação](Docs/24_Revisao_dos_Textos_ptBR.md).

**Atualização do RumoHub (14/10):** página de jogos e administração, 2.160 clubes, 154 competições/circuitos, 19.789 ratings FC27 e 211 seleções com emblemas. [Acesso, cobertura, limites e testes](Docs/23_Administracao_RumoHub_e_Catalogo_EA.md). Convocações ainda não estão implementadas; a segunda divisão indiana está sinalizada como provisória e há 60 escudos de clubes pendentes.

**Aposentadoria compacta:** novas aposentadorias preservam totais, clubes, temporadas e conquistas no histórico e liberam a vaga. Os detalhes são removidos definitivamente. Aposentadorias anteriores exigem confirmação em Minhas carreiras. [Funcionamento, instalação e testes](Docs/21_Aposentadoria_e_Historico_Compacto.md). Execute `bin/install-retirement-history.php` ao atualizar uma instalação existente.

**Minhas carreiras:** abra [gerenciar carreiras](http://rumo10.local/carreiras). Limite total de 3 no gratuito e 10 no pago, contando arquivadas; exclusão exige confirmação permanente. [Preparação do Hub e próximos passos de armazenamento](Docs/20_Preparacao_RumoHub_e_Armazenamento.md). Em instalações existentes, execute `bin/install-career-limits.php` antes de atualizar a aplicação.

Top 250 mundial Opta e 250 jogadores masculinos FC 27 pesquisados; nove novos mercados com duas divisões e 268 clubes. [Fontes, cobertura e limites](Docs/19_RumoHub_e_Top250.md). O catálogo compartilhado foi instalado em `rumohub.rh_*`; contas e carreiras permanecem em `ecohub.rumo10_*`. [Instalação e integração com o Hub](Docs/22_RumoHub_Instalacao_e_Integracao.md).

África e todos os países independentes da América do Sul, evolução com auge potencial aos 32, aposentadoria voluntária aos 35 e obrigatória aos 40, e catálogo global normalizado para outros jogos: [cobertura, fontes, instalação e estrutura das tabelas](Docs/18_Africa_Ciclo_e_Catalogo_Compartilhado.md).

RPG de carreira em português, PHP 8.2+, PDO MySQL, Bootstrap local e JavaScript nativo. O cadastro tem **1.754 clubes em 74 países/mercados**, **130 competições/etapas/circuitos**, incluindo o Brasil, América do Sul, Europa, África, Japão e Concacaf. A seleção mundial de 250 clubes usa a fonte pública **Opta**, não um ranking FIFA. MLS e Canadá têm formatos próprios; os nove mercados acrescentados têm todos os participantes de duas divisões e calendário de jogo adaptado. Outros mercados ainda têm cobertura parcial. [Fontes e limites atuais](Docs/19_RumoHub_e_Top250.md). Placares, jogador, propostas e temporadas seguintes são simulados no servidor. Carreiras anteriores permanecem disponíveis.

Para começar outra carreira na conta atual, abra **/carreira/nova**. A anterior será arquivada ao enviar o formulário; pode ser retomada em **/carreira/arquivadas**. Criar outra carreira não apaga a anterior.

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
## Companheiros reais e fictícios

Em `/jogador`, abra **Ver companheiros de elenco**. O catálogo cobre os 1.754 clubes: 24.547 atletas reais em 766 equipes, 23.807 fictícios e 5.325 notas publicadas do EA SPORTS FC 27. Os 250 homens do recorte EA estão conciliados com identidades reais. Messi consta no Inter Miami com nota publicada 89. Os demais overalls são estimados e identificados. [Procedência da integração inicial](Docs/16_Elencos_Reais_e_Ficticios.md) e [cobertura atual](Docs/19_RumoHub_e_Top250.md). Os elencos são a base de referência; autoria de gols e premiações dos NPCs ainda serão integradas ao motor.

Para instalar o catálogo, execute `bin/install-official.php` e depois `bin/install-players.php` com a conexão local configurada. Atualizam somente tabelas `rumo10_*`, incluindo clubes, jogadores e vínculos normalizados; sem reset de carreira ou renomeação de banco.

## Histórico da carreira e próxima evolução

A ficha `/jogador` mostra **Carreira inteira**, com jogos, gols e assistências acumulados, além de números por clube e temporada. [Análise de calendários, duas janelas de transferência e premiações](Docs/15_Calendario_Historico_e_Premiacoes.md). A sincronização de datas Brasil–Europa e as premiações individuais competitivas ainda dependem da evolução do motor; não confundir essa análise com funcionalidades implementadas.

## Europa e mercado internacional

Incluídas duas divisões de Inglaterra, Espanha, Alemanha, Itália, França, Portugal, Países Baixos, Bélgica, Turquia e Escócia: 365 clubes com escudos locais e cores aproximadas. As propostas internacionais nas carreiras oficiais consideram desempenho, minutos, evolução e nível do clube. Aceitar uma proposta muda o campeonato no ano seguinte e preserva a carreira. A Europa entra no fechamento da temporada sem reset. [Funcionamento, fontes e adaptações dos regulamentos](Docs/14_Europa_e_Mercado_Internacional.md).

## Modos de jogo

Escolha **Normal**, **Rápido** ou **Expresso** na criação do jogador ou abra **Modo · alterar ritmo** na carreira antes de um jogo. Normal mantém as decisões por situação; Rápido pede uma abordagem por partida; Expresso pede uma estratégia e simula toda a temporada restante. Contratos são escolhidos por você em todos os modos. Nenhuma carreira precisa ser reiniciada e nenhuma migração de banco é necessária. [Funcionamento e limites](Docs/13_Modos_de_Jogo.md).

Para validar o motor dos modos, execute `tests/play-modes.php` com PHP e mbstring. `tests/http.php` testa também os modos no IIS e MySQL locais com uma conta descartável.

O recorte mundial e as 18 novas ligas são validados por `tests/top250.php`. Elencos e integridade compartilhada: `tests/player-catalog.php` e `tests/shared-catalog.php` (este acessa o MySQL local). O teste HTTP deve ser executado com `-d memory_limit=256M` no cliente de teste, que retém várias respostas completas; isso não altera o PHP do IIS.

## Jogar e testar

1. Abra [http://rumo10.local](http://rumo10.local), escolha começar e crie uma conta com senha exclusiva (8–72 caracteres). Não existem credenciais demo públicas; o cadastro local é o modo de teste rápido.
2. Crie o jogador informando nome, UF, município e posição. Gire a roleta de perfil. Em SP, o resultado abre a A4 ou A3; nas outras UFs, a divisão inferior disponível. Escolha um dos três clubes oferecidos por proximidade da cidade (dois em RO). Recarregar conserva o sorteio e as ofertas.
3. Escolha uma opção de treino e de pré-jogo. Inicie a partida. Resolva as oportunidades; avance o intervalo e, se estiver no banco ou fora de campo, os segmentos automáticos.
4. Confira placar, estatísticas e `/classificacao`. Resolva o fim de semana para avançar a rodada. A recuperação semanal é mostrada separadamente do sorteio social.
5. Complete a competição; fases classificatórias e mata-matas avançam conforme seus resultados. Se o clube também tiver competição nacional, use Continuar para jogá-la. Ao encerrar o ano, sorteie a proposta e aceite ou permaneça. A nova temporada aplica os acessos/rebaixamentos da pirâmide nacional/paulista e mantém jogador, XP e histórico. Nos outros estaduais, consulte a limitação de continuidade descrita abaixo.
6. Saia e entre novamente. Crie outra conta para confirmar carreiras independentes. `/api/carreira/estado` retorna apenas a carreira da sessão.

Para concluir rapidamente a temporada, escolha o modo Expresso antes de iniciar uma partida. Para validar automaticamente 30 temporadas de teste independentes nas seis posições, rode:

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

- Overall usa nível da divisão, campanha anterior verificada quando disponível e forma recente. Gols coletivos/NPCs usam distribuição de Poisson com mando; posse, estratégias coletivas e estatísticas completas das equipes ainda não são simuladas. Cinco oportunidades por titular (15, 35, 60, 70 e 80 minutos), menos para reserva/lesão/expulsão.
- S10 e S11 estão preservadas no seed, porém ficam inelegíveis sem patrocinador/rival cadastrado. O catálogo ampliado tem 214 situações e 856 alternativas, com contexto e memória para reduzir repetição. A edição de cores é feita via CLI local (bin/club-colors.php), sem painel web administrativo. Não existe catálogo público de configuração.
- Transferências usam clubes reais e desempenho; não há contratos financeiros, goleiros jogáveis, recuperação de senha ou verificação de e-mail. Copa do Brasil, Copa Paulista, competições femininas e de base estão fora do escopo desta versão. As divisões superiores dos estaduais adicionados fora de SP ainda não estão conectadas: os acessos são registrados, mas permanecer no clube repete a divisão disponível.
- Sessões e logs usam storage/sessions e storage/logs fora da raiz pública; configure permissões e garbage collection. Sem uploads. URLs supõem instalação na raiz do domínio; APP_URL registra a base por ambiente, links são relativos à raiz.
- Migration 002 adiciona catálogo oficial e um snapshot JSON transacional por carreira para o universo de competições. Atributos/estados e auditoria continuam consultáveis em tabelas relacionais. O snapshot inclui fases, jogos, classificação, mercado e participações; é validado pelo motor no servidor.
- Migrations DDL MySQL não são transacionais; comandos CREATE IF NOT EXISTS permitem retomar uma instalação interrompida. A migration 001 é aditiva; futuras mudanças exigem novas versões. Sem downgrade automático.

Evidências de execução e pendências estão em [Docs/10_Testes_Executados.md](Docs/10_Testes_Executados.md). Documentos 01–09 detalham regras e escolhas provisórias.

A interface mostra o resultado e somente os efeitos efetivos, sem +0 nem botão de pular nos resultados comuns. Durante a partida, acompanhe gols e assistências. Após o jogo, a próxima decisão precede o histórico recolhido. No celular, o menu fica na parte inferior e as habilidades ficam recolhidas. Teste: `php tests/presentation.php`.

Durante a partida, a tela concentra resultado, efeitos já aplicados, placar, gols/assistências pessoais e a decisão atual. Rádio e estatísticas completas ficam no pós-jogo. O catálogo de clubes é configuração interna via seed/CLI; não há menu nem rota pública /clubes.

### Interface de carreira

A roleta apresenta o resultado salvo pelo servidor com giro e desaceleração. É possível pular a animação; recarregar não sorteia novamente. Preferência por movimento reduzido mostra o resultado diretamente. No pós-jogo e no encerramento, a próxima decisão vem antes dos detalhes recolhidos. O resumo da temporada usa os pontos, posição, gols e assistências da carreira.

Testes adicionais de apresentação: `php tests/wheel.php` e `php tests/season-presentation.php`.


### Competições e testes da versão 0.3

Consulte [clubes, formatos, fontes e simplificações](Docs/05_Clubes_e_Competicoes.md). O estadual e o nacional são jogados em sequência; a agenda não reproduz datas/horários oficiais nem sobreposição de torneios. Edições seguintes seguem um universo de carreira, com regras simplificadas para expansão da Série C e reposição de vagas da Série D, não listas oficiais de 2027 em diante.

Em instalação existente, execute `php bin/install-official.php`; numa instalação nova, `php bin/install.php` já aplica 001 e 002. Não há necessidade de refazer o IIS.

- `php tests/official.php`: nove formatos, calendário, acessos, anos completos e mercado.
- `php tests/official-mysql.php`: carreira descartável, persistência de duas competições, transferência, arquivo e restauração.
- `php tests/http.php http://rumo10.local`: fluxo HTTP real no IIS, incluindo nova carreira e restauração.

Os testes usam contas descartáveis e preservam sua conta e sua campanha.

### Início da carreira, cidades e escudos — versão 0.4

Abra [Nova carreira](http://rumo10.local/carreira/nova) no menu. O formulário não permite escolher clube antecipadamente. A carreira anterior só é arquivada após um formulário válido e pode ser retomada pelo site. A roleta sorteia o perfil uma única vez; depois aparecem propostas com escudo, município e distância aproximada, e a escolha inicia o calendário do clube.

Sergipe usa provisoriamente a **A2 confirmada**, por autorização do usuário; a A3 ainda recebia inscrições na data da pesquisa. Rondônia tem **duas** opções porque apenas dois clubes participaram da Série B. Roraima tem uma única divisão profissional. Pernambuco usa o **primeiro turno do estadual unificado 2026/27**, com os 24 clubes que entram nessa etapa; o turno decisivo de 2027 não está simulado. Em SP permanece a entrada A4/A3 solicitada, embora exista a Segunda Divisão abaixo delas.

Proximidade é calculada no servidor pela fórmula de Haversine entre centros dos municípios; não representa trajeto rodoviário nem distância a estádios. A lista de municípios por UF é local. Escudos são arquivos locais com fonte e URL original registrados, sem dependência de sites externos durante o jogo. Marcas pertencem aos respectivos clubes; o cadastro de fontes não constitui licença comercial nem certificação de identidade visual.

Detalhes, fontes e limites dos novos estaduais: [Docs/11_Inicio_Estadual_e_Escudos.md](Docs/11_Inicio_Estadual_e_Escudos.md).

```powershell
& C:\PHP\php-8.4.26\php.exe -n -d extension_dir=C:\PHP\php-8.4.26\ext -d extension=mbstring tests/onboarding.php
& C:\PHP\php-8.4.26\php.exe -n -d extension_dir=C:\PHP\php-8.4.26\ext -d extension=mbstring tests/state-entry.php
& C:\PHP\php-8.4.26\php.exe -n -d extension_dir=C:\PHP\php-8.4.26\ext -d extension=mbstring -d extension=pdo_mysql tests/entry-mysql.php
```


A versão 0.5 está detalhada em [Docs/12_Situacoes_Overall_e_Mercosul.md](Docs/12_Situacoes_Overall_e_Mercosul.md): fontes, fórmula do overall, cobertura provisória de campanhas anteriores, escudos, paletas aproximadas e regras internacionais simplificadas. Novos testes: `php tests/scenes-strength.php` e `php tests/international.php`.


A ficha gráfica do personagem está em **/jogador**: clique no nome na tela de carreira ou em Habilidades e evolução. Mostra nível técnico, condição física, desempenho da temporada e progresso de XP, com detalhes recolhidos para celular. Consultar a ficha não modifica o jogo.

Logo, favicon, interface responsiva e resultados da revisão de 14/10/2026: [Identidade visual e verificação](Docs/26_Identidade_Visual_e_Verificacao.md). Os assets estão em `public/assets/brand/`; `php tests/brand.php http://rumo10.local` verifica a instalação no IIS.

Duas boas ações seguidas com bola podem abrir uma chance clara de gol, nos três modos: [mecânica, probabilidades e testes](Docs/28_Chances_Construidas.md). A oportunidade funciona nas carreiras existentes; não exige migração.

Revisão dos bônus e penalidades, XP em dobro no modo normal e retirada das regras de idade da interface: [Consequências, XP e interface](Docs/30_Consequencias_XP_e_Interface.md). As mudanças valem nas próximas decisões das carreiras existentes.
