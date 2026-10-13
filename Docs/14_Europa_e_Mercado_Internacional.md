# Europa e propostas internacionais — versão 0.7

## Cadastro e acesso

Incluídas as **duas divisões principais**, edição **2026/27**, de dez mercados selecionados para o jogo: Inglaterra, Espanha, Alemanha, Itália, França, Portugal, Países Baixos, Bélgica, Turquia e Escócia. Essa seleção não é apresentada como um ranking oficial da UEFA.

São **365 novos clubes**, **20 campeonatos**, escudos locais em PNG e duas ou três cores HEX aproximadas extraídas dos escudos. O universo passa a **1.036 clubes e 63 competições/etapas**. **278 campanhas completas de 2025/26** importadas da ESPN influenciam o overall inicial. Os clubes sem dados históricos correspondentes continuam com uma base provisória por divisão. Paleta de escudo não certifica cores de uniforme; parte das imagens provém de acervos comunitários.

| Mercado | Primeira / participantes | Segunda / participantes |
| --- | --- | --- |
| Inglaterra | Premier League / 20 | Championship / 24 |
| Espanha | LaLiga / 20 | Hypermotion / 22 |
| Alemanha | Bundesliga / 18 | 2. Bundesliga / 18 |
| Itália | Serie A / 20 | Serie B / 20 |
| França | Ligue 1 / 18 | Ligue 2 / 18 |
| Portugal | Liga Portugal / 18 | Liga Portugal 2 / 18 |
| Países Baixos | Eredivisie / 18 | Eerste Divisie / 20 |
| Bélgica | Jupiler Pro League / 18 | Challenger Pro League / 15 |
| Turquia | Süper Lig / 18 | TFF 1. Lig / 20 |
| Escócia | Premiership / 12 | Championship / 10 |

Não é necessário reiniciar uma carreira oficial. Os novos campeonatos entram no universo no fechamento da temporada atual, antes das propostas. Resultados antigos e propostas já sorteadas são preservados. Ao aceitar proposta de outro país, a temporada seguinte usa a divisão efetiva do novo clube, após acessos/rebaixamentos, conservando jogador, atributos, história e modo de jogo.

As carreiras antigas da liga regional fictícia permanecem com o seu mercado anterior. O mercado internacional desta expansão pertence às carreiras oficiais. Nova carreira continua começando no estado brasileiro do jogador e nos três clubes oferecidos pela roleta/proximidade, sem seleção direta de clubes europeus.

## Propostas

`TransferMarket` considera partidas, minutos, nota média, média técnica das nove habilidades e crescimento técnico na temporada. Atletas precisam de pelo menos seis jogos, 360 minutos, nota 6,5, técnica 50 e crescimento de 0,75 ponto na média técnica (ou técnica já igual/superior a 58) para entrar no mercado estrangeiro.

Europa exige nota pelo menos 6,7 e 600 minutos. O clube interessado exige técnica de no mínimo `max(50, overall - 14)`; clubes com overall 85 ou mais exigem nota 7. São critérios provisórios do jogo, não regras reais de transferências, vistos ou federações. A nota usa o mesmo motor das partidas; gols não são requisito, permitindo evolução de defensores.

O conjunto da roleta contém até oito candidatos. Havendo atletas e destinos elegíveis, até quatro vagas são reservadas para clubes estrangeiros e países distintos são priorizados. Isso evita que os muitos clubes brasileiros diluam a chance internacional. O peso estrangeiro cresce com desempenho e evolução; nenhuma proposta específica é garantida. O usuário continua recebendo uma proposta sorteada e decide aceitar ou ficar. A tela informa país e campeonato do destino, além do motivo do interesse estrangeiro.

O crescimento inicial é comparado aos atributos após a roleta; o início de cada nova temporada passa a ser a nova referência. Carreiras anteriores sem essa referência usam o perfil inicial conhecido como aproximação. A janela continua no fim do ano: não foram introduzidas transferências no meio da temporada.

## Formatos e adaptações

Todos os campeonatos usam calendário de partidas gerado, classificação, campeão, acesso/rebaixamento e estatísticas pelo motor atual, nos três modos. Datas reais, sorteios reais de fixtures, copas nacionais e competições UEFA não foram incluídos.

- Inglaterra: 38/46 jogos regulares, três descidas na primeira; duas subidas diretas e playoff com seis clubes da segunda conforme a mudança de 2026/27 (5º–8º preliminar única, 3º/4º nas semifinais em duas mãos, final única).
- Espanha: 38/42 jogos, duas subidas diretas e playoff 3º–6º em duas mãos; três descidas na primeira e quatro na segunda. Filiais não sobem. A regra completa de desempate espanhola e prolongamento ainda usam as adaptações do motor.
- Alemanha: 34 jogos, duas subidas/descidas diretas e barrage do 16º da primeira com o 3º da segunda.
- Itália: 38 jogos, duas subidas diretas e playoff 3º–8º; dispensa quando a diferença 3º–4º supera 14 pontos. Preliminar única, semifinal/final em duas mãos; vantagem do melhor classificado no agregado. Playout de permanência da Serie B ainda não foi modelado: são registradas as três últimas posições de queda.
- França: 34 jogos; duas subidas/descidas diretas; 4º–5º e vencedor–3º da segunda em jogos únicos, seguidos de barrage com o 16º da primeira.
- Portugal: 34 jogos; duas subidas/descidas diretas e barrage do terceiro elegível da segunda com o 16º da primeira. Benfica B, Porto B e Sporting B não sobem.
- Países Baixos: 34/38 jogos; duas subidas/descidas diretas. Playoff de acesso adaptado com seis elegíveis da segunda pela classificação geral e barrage com o 16º da primeira. Não são usadas vagas de campeões de período nem o chaveamento cruzado integral do regulamento. Jong não sobe; não há queda da segunda para uma terceira divisão cadastrada.
- Bélgica: primeira com 18 clubes, 34 jogos e sem playoffs, duas quedas. Segunda com 15 clubes, 28 jogos por clube em 30 rodadas com folgas; campeão elegível sobe e 2º–5º elegíveis disputam outro acesso. U23 não sobe.
- Turquia: 34/38 jogos; duas subidas diretas; terceiro aguarda final única, 4º–7º disputam preliminar única e semifinal em duas mãos; quatro últimas posições da segunda são registradas como queda.
- Escócia: Premiership com 33 jogos antes do split e cinco depois, pontos anteriores mantidos e classificação final separada em grupos de seis. Championship com 36 jogos e campeão promovido; 3º–4º e vencedor–2º em duas mãos, seguido de barrage com o 11º da primeira.

**Barragens entre divisões são simuladas automaticamente no fechamento do universo, com overall, ida/volta e desempate do motor. Não geram decisões nem estatísticas pessoais adicionais.** Elas ficam em `barrage` nos snapshots das competições e decidem a composição do ano seguinte. A próxima etapa poderá integrá-las à sequência jogável do personagem. Terceiras divisões europeias não estão cadastradas: queda da segunda é registrada, mas o clube continua na segunda se o jogador ficar, conforme a limitação anterior da expansão internacional. Não são simulados licenciamento, restrições de idade de U23 ou deslocamento de filiais quando o clube principal desce.

O motor resolve empates com vantagem ou pênaltis, conforme a fase, sem simulação de prorrogação. O split escocês usa o gerador de calendário e não reproduz exatamente o equilíbrio de mandos publicado pela liga. Os limites foram preservados explicitamente em vez de apresentar o protótipo como homologação integral dos regulamentos.

## Fontes e reprodução

`bin/build-europe.py --download` reconstitui os arquivos `database/europe-clubs-2026.json`, `europe-competitions-2026.json`, `europe-results-2025.json` e escudos. Requer Python/Pillow e rede apenas para dados/assets públicos, sem ler .env ou acessar MySQL. Cada registro tem procedência. Receipts ficam em storage/europe-sources, ignorados pelo Git.

- [Premier League: clubes 2026/27](https://www.premierleague.com/en/clubs), [EFL: mudança de playoff](https://www.efl.com/news/2026/march/05/efl-statement--sky-bet-championship-play-off-format/).
- [LaLiga: clubes](https://www.laliga.com/en-ES/laliga-easports/clubs), [Hypermotion: clubes](https://www.laliga.com/en-ES/laliga-hypermotion/clubs).
- [Bundesliga](https://www.bundesliga.com/en/bundesliga/clubs), [2. Bundesliga](https://www.bundesliga.com/en/2bundesliga/clubs).
- [Lega Serie A](https://www.legaseriea.it/serie-a), [Lega B](https://www.legab.it/seriebkt).
- [LFP: calendário 2026/27](https://www.lfp.fr/article/publication-du-calendrier-general-de-la-ligue-2-bkt-pour-la-saison-2026-2027).
- [Liga Portugal: regulamentos](https://www.ligaportugal.pt/pages/estatutos-regulamentos); a relação da segunda foi cruzada com [participantes 2026/27](https://en.wikipedia.org/wiki/2026%E2%80%9327_Liga_Portugal_2).
- [KNVB: Eerste Divisie](https://www.knvb.nl/competities/keuken-kampioen-divisie).
- [Bélgica: primeira com 18 clubes](https://www.proleague.be/nieuws/vanaf-seizoen-26-27-met-18-clubs-in-de-jupiler-pro-league), [calendário dos 15 da segunda](https://www.proleague.be/nieuws/kalender-2026-2027-nieuwkomers-treffen-elkaar-meteen-in-challenger-pro-league).
- [TFF: segunda divisão](https://www.tff.org/default.aspx?pageID=142), [regulamento 2026/27](https://www.tff.org/Resources/TFF/Auto/18c19be2866242679eb483a7a154e0cf.pdf).
- [SPFL: regulamentos de julho de 2026](https://spfl.co.uk/admin/filemanager/images/shares/pdfs/MASTER%20-%20Rules%20and%20Regulations%20(CLEAN%20-%2029%20July%202026).pdf).
- ESPN: catálogo das equipes e campanhas de 2025/26; URLs por liga e clube nos arquivos JSON. Escudos da ESPN, EscudosFC e fontes identificadas nas exceções (AFS, Lokeren, Mardin 1969, Van Spor e Lusitânia Lourosa). Nem todos os escudos foram certificados manualmente como a última versão de marca.

## Instalação e testes locais

O catálogo foi instalado no **ecohub**, exclusivamente em tabelas **rumo10_**, via `bin/install-official.php`. Nenhuma carreira pessoal foi resetada. No site, continue a temporada e abra as propostas ao final; desempenho elegível pode atrair interesse internacional. Também é possível começar uma nova carreira pelo site, sempre pelo fluxo brasileiro existente.

Em 13/10/2026: 28.858 verificações registradas de `tests/europe.php`, vinte formatos completos e carreiras de cada mercado até o início da segunda temporada; transferência Brasil–Portugal e critérios de interesse. `tests/europe-mysql.php`: sete verificações com conta descartável, proposta internacional persistida, replay e transferência. `tests/scenes-mysql.php`: vinte verificações de persistência e overall após otimização de memória. `tests/http.php`: 54 verificações no IIS/MySQL local, incluindo temporada completa, replay e contratos. Todos os **365 escudos** retornaram HTTP 200 e MIME de imagem pelo IIS.

O teste inicial pelo IIS revelou duplicação de universos completos durante POST/save/reload e estourou o limite local de 128 MiB. A correção libera as cópias antes do próximo carregamento; os testes completos passaram mantendo a configuração do servidor. Não foi acessado Apache nem produção.
