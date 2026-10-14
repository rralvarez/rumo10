# RumoHub e catálogo mundial: proposta e expansão de 14/10/2026

## Decisão sobre o banco: proposta, sem migração aplicada

O nome `rumohub` faz sentido para o banco que atenderá a família de jogos Rumo. O catálogo de futebol deve ter um prefixo neutro, `rh_`. O nome do jogo continua identificando apenas suas regras e seu progresso. Não é necessário escolher agora os nomes comerciais dos jogos de técnico e agente.

| Camada | Exemplos propostos | Responsabilidade |
|---|---|---|
| Catálogo compartilhado | `rh_paises`, `rh_clubes`, `rh_jogadores` | Identidades permanentes; nomes, país do clube, posição, nascimento, origem real/fictícia |
| Competições | `rh_competicoes`, `rh_edicoes`, `rh_inscricoes` | Liga e país organizador, divisão, edição, participantes e regulamento de referência |
| Elencos | `rh_elenco_vinculos` | Jogador, clube, edição, fonte, período conhecido e situação da verificação |
| Avaliações | `rh_avaliacoes_clubes`, `rh_avaliacoes_jogadores` | Nota, escala, método, fornecedor, edição e data da consulta |
| Fontes e arquivos | `rh_fontes`, `rh_identificadores_externos`, `rh_midias_clubes` | IDs ESPN/EA/Opta, aliases, escudos, licença/proveniência e cores aproximadas |
| Plataforma | `rh_usuarios`, `rh_jogos` | Login comum e identificação do jogo, se futuramente desejados |
| Progresso de Rumo à Camisa 10 | `rumo10_*` | Carreiras, escolhas, partidas, estatísticas e evolução do personagem |
| Próximos jogos | Prefixo próprio após definir o nome | Técnico: escalações/táticas; agente: contratos, empresas, patrimônio e negociações |

`rumo10_` permanece um prefixo adequado para dados exclusivos deste jogo; o problema é usá-lo para entidades que pertencem a toda a plataforma. Se preferirmos abreviar todos os produtos, `r10_` é uma opção, mas mudar esse prefixo agora só acrescentaria migração sem benefício funcional.

### Identidade e histórico

- Clube e jogador devem ter IDs internos imutáveis. Nome, apelido, escudo e ID do fornecedor não são chaves primárias universais. Já há IDs numéricos estáveis em `rumo10_catalogo_clubes`; eles deverão ser preservados na futura migração.
- Uma pessoa aparece uma vez em `rh_jogadores`; mudanças de equipe entram em vínculos de elenco. O atual catálogo mantém um vínculo corrente por atleta, mas o modelo futuro deve guardar o histórico de vínculos, sem apagar o anterior.
- Uma consulta feita em outubro não prova que o contrato começou em outubro. Guardar `consultado_em` separadamente de `vigente_desde`; período desconhecido permanece desconhecido.
- País do clube e país da competição são campos diferentes. Monaco disputa a liga francesa, Swansea a inglesa e Vaduz a suíça. Não criar uma liga doméstica inexistente para forçar essa relação.
- Overall EA do atleta e nota Opta do clube são avaliações diferentes. Guardar fonte, escala e método; a conversão para a mecânica de um jogo pertence a esse jogo.
- Jogador fictício deve ter origem explícita e ID próprio. Não apresentá-lo como jogador real nem reutilizar seu nome para conciliar identidade.

### Cada jogo possui seu universo

Uma compra feita no jogo de agente, uma transferência no Rumo à Camisa 10 ou uma lesão no jogo de técnico **não altera o catálogo mundial de referência**. Ao começar a partida, o jogo referencia uma edição do catálogo e cria seu estado com `universo_id`. Contratos, donos dos clubes, evolução, aposentadoria, gols e assistências simulados pertencem a esse universo.

Um mundo compartilhado entre os três jogos seria outro produto: exigiria transações, permissões, resolução de conflitos e regras de sincronização. Não é consequência automática de usar o mesmo banco.

### Situação atual e migração futura

Atualmente o banco continua `ecohub` e as tabelas continuam `rumo10_*`. As tabelas de referência existentes são `rumo10_clubes_oficiais`, `rumo10_catalogo_clubes`, `rumo10_competicoes`, `rumo10_jogadores_catalogo` e `rumo10_elenco_jogadores`. `rumo10_elencos_catalogo` é um cache de compatibilidade, não uma segunda identidade de atleta.

Quando a migração for solicitada: inventariar dependências, fazer backup, criar `rumohub`, copiar somente as tabelas deste projeto, preservar IDs e vínculos, validar contagens e integridade, adaptar o repositório de acesso aos dados, testar login/carreiras/instaladores e só então trocar a configuração privada. Não renomear tabelas de outros projetos de `ecohub`. Não duplicar o catálogo para cada jogo. Views temporárias podem facilitar compatibilidade durante a transição; não devem manter duas cópias divergentes.

Nenhuma credencial, novo usuário SQL, renomeação de banco ou migração `rh_` foi criada nesta entrega.

## Critérios da seleção mundial

Escolha confirmada pelo usuário: **250 clubes pelo ranking mundial masculino Opta** e **250 jogadores masculinos na ordenação publicada pelo EA SPORTS FC 27**. Os recortes não são rankings FIFA e não representam uma classificação universal dos melhores atletas pelo desempenho em campo.

- [Opta: metodologia e visualização](https://theanalyst.com/articles/who-are-the-best-football-team-in-the-world-opta-power-rankings), [visualização pública](https://dataviz.theanalyst.com/opta-power-rankings/).
- [EA SPORTS FC: ratings publicados](https://www.ea.com/games/ea-sports-fc/ratings). Foram percorridas seis páginas, removidas as atletas femininas e os IDs repetidos, mantida a ordem de ranking da fonte e recortados 250 homens. Há empates de overall; o corte respeita o desempate da EA.

Consulta em 14/10/2026. O bundle Opta não forneceu uma data explícita de atualização: a data de consulta não é apresentada como data oficial do ranking. Os aliases dos fornecedores são conciliados com os nomes locais, preservando as identidades já usadas pelas carreiras.

Arquivos auditáveis: `database/world-top250-2026.json`, `database/players-top250-2026.json` e `database/top250-coverage-2026.json`. O antigo top 100 permanece como recibo histórico da entrega anterior.

## Novos mercados e participantes

A auditoria encontrou clubes ausentes em nove mercados. Foram acrescentadas **as duas divisões completas**, totalizando 268 clubes e 18 competições. Não se usou um circuito de quatro clubes como substituto da liga completa.

| Mercado | 1ª divisão | 2ª divisão | Participantes |
|---|---|---|---|
| Áustria | Bundesliga | 2. Liga | 12 + 16 |
| Azerbaijão | Misli Premyer Liqası | I Liqa | 12 + 10 |
| Suíça | Brack Super League | dieci Challenge League | 12 + 10 |
| Ucrânia | Premier League | Persha Liga | 16 + 16 |
| Sérvia | SuperLiga | Prva Liga | 14 + 16 |
| Japão | J1 League | J2 League | 20 + 20 |
| Israel | Ligat Ha’al | Liga Leumit | 14 + 16 |
| Bulgária | Parva Liga | Vtora Liga | 14 + 18 |
| Suécia | Allsvenskan | Superettan | 16 + 16 |

Nomes da classificação Opta conciliados com páginas dos organizadores. Removidas quatro entradas antigas da classificação de ligas do fornecedor: Stripfing (AT2), Difai Ağsu (AZ2), Vorskla (UA2) e Dinamo Jug (RS2). Vaduz, de Liechtenstein, completa os 12 participantes da Super League suíça. O recorte dos jogadores não exigiu um país adicional: seus clubes licenciados têm equivalentes no catálogo existente.

Fontes dos participantes e exceções:

- Áustria: [Bundesliga](https://www.bundesliga.at/de/klubs), [16 clubes da 2. Liga](https://www.2liga.at/de/klubs).
- Azerbaijão: [Premier League](https://pfl.az/turnir-cədvəli), [I Liqa](https://liqa1.pfl.az/turnir-cədvəli); API pública utilizada pelas páginas, temporada 76 e ligas 52/53, confirma os 12/10 participantes.
- Suíça: [Super League](https://sfl.ch/de/klubs-brack-super-league), [Challenge League](https://sfl.ch/de/klubs-dieci-challenge-league), [calendário 2026/27](https://sfl.ch/de/articles/sfl-veroffentlichtspielplane-der-saison-202627).
- Ucrânia: [tabela UPL 2026/27](https://upl.ua/ua/tournaments/championship?id=432&tab=table), [16 participantes da Persha Liga](https://pfl.ua/news/1699), [API pública documentada pela PFL](https://www.pfl.ua/custom/6).
- Sérvia: [SuperLiga](https://fss.rs/takmicenje/mozzart-bet-super-liga-srbije-26-27/?script=lat), [Prva Liga](https://fss.rs/takmicenje/mozzart-bet-prva-liga-srbije-26-27/?script=lat).
- Japão: [composição oficial 2026 e 2026/27](https://www.jleague.jp/news/article/32852/?mode=pc). A temporada 2026/27 sucede a competição especial de transição; não é uma segunda temporada anual idêntica ao antigo calendário japonês.
- Israel: [Ligat Ha’al](https://www.football.org.il/en/leagues/league/?league_id=40&season_id=28), [Liga Leumit](https://www.football.org.il/en/leagues/league/?league_id=45&season_id=28), classificação de clubes Opta para conciliar transliterações.
- Bulgária: [tabelas da liga profissional](https://bpfl.bg/ranking), [calendário](https://bpfl.bg/schedule).
- Suécia: [composição oficial das duas divisões em PDF](https://www.svenskfotboll.se/4af842/globalassets/svff/dokumentdokumentblock/tavling/arsplaner--seriesammansattningar/2026/faststalld-seriesammansattning-allsvenskan-och-superettan-2026.pdf).

## Limites importantes

As listas de participantes são completas nas divisões acrescentadas; **os regulamentos simulados continuam adaptados em ida/volta**, conforme aviso de cada competição. Não foram implementados nesta expansão os playoffs/splits de cada país, acessos/rebaixamentos, licenças ou vagas continentais. Datas globais e as duas janelas reais de transferência continuam pendências do motor, descritas no documento 15.

As outras ligas/circuitos cadastrados anteriormente não se tornaram automaticamente completos em duas divisões. “País inteiro” nesta solicitação foi aplicado aos participantes das duas principais divisões dos mercados acionados pela auditoria, não às ligas inferiores, categorias de base ou clubes inativos.

Elencos reais dependem da fonte; onde não há cobertura, continua o complemento fictício identificado. EA pode usar nomes licenciados alternativos e vínculos diferentes da fonte de elenco. Rating conciliado por identidade não transfere um jogador para o clube da licença. NPCs ainda não possuem gols e assistências individuais simulados nem participam de premiações individuais.

Novos mercados entram em novas carreiras e na atualização do universo ao fim da temporada de uma carreira existente. A expansão não reinicia nem simula a carreira do usuário.

## Entrega instalada e como verificar

O catálogo atual tem **1.754 clubes, 130 competições/etapas/circuitos e 74 países/mercados**. Os 1.754 elencos têm 24.547 atletas reais, 23.807 fictícios e 5.325 ratings publicados; os demais são estimativas identificadas. Os **250 atletas do recorte EA estão conciliados**, sem pendências e sem criar uma segunda identidade para os já cadastrados.

Há 252 escudos identificados nos 268 clubes novos. Os 16 pendentes (3 da Sérvia e 13 de Israel) estão listados em `top250-coverage-2026.json` e aparecem por iniciais. Imagens genéricas cinzentas retornadas pelo fornecedor foram removidas. As 15 pendências africanas da entrega anterior continuam separadas. Cores extraídas de escudos são aproximações HEX; para os clubes sem escudo confirmado, a paleta neutra está marcada como pendente, sem alegar que seja a cor oficial.

A conciliação de jogadores exige nascimento e nome, ou um alias específico com ID e nascimento verificados. `defaultTeam.$ref` da ESPN é lido apenas como texto para extrair o ID do clube: **não se acessa o host privado indicado nesse campo**. Isso eliminou vínculos de atletas de outros clubes presentes em algumas respostas de elenco. Roster vazio validado não é tratado como falha para reinserir dados antigos.

Correções auditáveis em `database/player-reference-overrides-2026.json`:

- [Lewis Hall na fonte oficial da Premier League](https://www.premierleague.com/en/players/487838/lewis-hall/video): ID `premierleague:487838`, nascimento 08/09/2004; o homônimo de 1999 com ID ESPN diferente e outro clube não foi associado à sua nota EA.
- [Daniel Muñoz no Nottingham Forest](https://www.nottinghamforest.co.uk/news/2026/october/07/mu-oz-helps-colombia-to-win-as-international-period-ends/): preservado o ID ESPN 146679 e conciliado o rating EA 82; não foi mantido no Palace por causa da licença antiga da EA.
- [Kostas Fortounis](https://www.olympiacos.org/en/player/kostas-fortounis/): nome abreviado conciliado com Konstantinos e o mesmo nascimento. Safonov/Matvei e Frank/Zambo Anguissa têm aliases pontuais com ID e nascimento conferidos nas fontes de elenco.

### Reproduzir a instalação

Com o `.env` privado já configurado, execute em `C:\inetpub\wwwroot\rumo10`:

```powershell
& C:\PHP\php-8.4.26\php.exe -n -d extension_dir=C:\PHP\php-8.4.26\ext -d extension=pdo_mysql -d extension=mbstring bin/install-official.php
& C:\PHP\php-8.4.26\php.exe -n -d extension_dir=C:\PHP\php-8.4.26\ext -d extension=pdo_mysql -d extension=mbstring bin/install-players.php
```

Os dois instaladores foram executados localmente. Atualizaram exclusivamente o catálogo `rumo10_*` em `ecohub`, preservando carreiras e IDs estáveis de clubes. O teste SQL confirmou **48.354 vínculos**, integridade da identidade de Messi e ausência de vínculo múltiplo por jogador no catálogo atual. Não foi criado `rumohub`.

Para refazer a pesquisa a partir dos recibos públicos: `bin/build-top250.py` (Python com Pillow), `bin/export-player-clubs.php`, `bin/build-player-catalog.py --fetch` e `bin/audit-top250-players.py`, nessa ordem. Sem `--fetch`, o catálogo de jogadores usa os caches; as correções oficiais continuam aplicadas. As listas reconciliadas são fixadas em `top250-participants-2026.json`; uma mudança exige nova conferência, não substituição automática por uma classificação de ligas.

Para jogar: abra [Rumo à Camisa 10 local](http://rumo10.local), entre na conta e use [Nova carreira](http://rumo10.local/carreira/nova) quando desejar. O início continua pelas divisões estaduais brasileiras e pelos três clubes oferecidos; as novas ligas entram no universo e no mercado internacional conforme a evolução. Em `/jogador` → **Ver companheiros de elenco**, os jogadores reais/fictícios e ratings publicados/estimados estão identificados.

### Testes desta entrega

- `tests/top250.php`: 14.767 verificações; recortes, aliases, 18 calendários completos, temporadas até o campeão, jogo na J1 e segunda temporada, identidade dos 250 jogadores, Muñoz/Hall e placeholders removidos.
- `tests/official.php`: 9.018 verificações de calendários brasileiros, acessos, temporadas completas e continuidade com o catálogo ampliado.
- `tests/player-catalog.php`: 254.116 verificações de identidade, origem, posições, goleiros, ratings e contadores.
- `tests/play-modes.php`: 525 verificações dos três modos; `tests/lifecycle.php`: 144 de evolução e aposentadoria; `tests/presentation.php`: passou.
- `tests/shared-catalog.php`: passou no MySQL local. Python compilado e `git diff --check` sem erros.
- `tests/http.php http://rumo10.local`: **64 verificações passaram no IIS local**, incluindo cadastro, escolhas, modos, temporada, proposta, renovação, aposentadoria e replay. O cliente de teste usou PHP com `memory_limit=256M` para manter várias respostas; a configuração do PHP do IIS não foi alterada.

O primeiro teste HTTP expôs falta de memória ao salvar a proposta com o universo ampliado. A gravação do snapshot enviava o mesmo JSON duas vezes ao PDO; passou a usar um único parâmetro e o valor inserido no upsert. Depois da correção, o fluxo completo passou, preservando o limite de 128 MiB do IIS identificado pelo log. Nenhum resultado de teste malsucedido foi contado como aprovação.

Os testes do motor não modificam a carreira pessoal. O teste HTTP usa conta descartável. Não houve acesso nem teste em Apache/produção nesta entrega.
