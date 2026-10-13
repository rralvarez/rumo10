# Elencos reais, notas publicadas e complemento fictício

Catálogo de referência consultado em **13/10/2026**, para edições **2026** na América do Sul e **2026/27** na Europa.

## Entrega

- **1.036 elencos**, um para cada clube oficial cadastrado.
- **14.437 atletas reais** em **448 clubes** com fonte de elenco identificada.
- **14.126 fictícios** para os clubes sem cobertura e complementos necessários.
- **3.949 overalls publicados do EA SPORTS FC 27**, associados a atletas reais pela identidade.
- **24.614 overalls estimados pelo Rumo à Camisa 10**, incluindo reais sem nota verificada e fictícios.
- **28.563 identidades únicas**. Elencos com pelo menos 24 atletas e dois goleiros.
- Os **40 clubes das Séries A e B brasileiras** têm jogadores reais importados (1.862 no total). Outros clubes brasileiros têm complemento fictício nesta etapa.

Não existe uma nota universal oficial de futebol para cada atleta. “Publicado · FC 27” significa nota publicada daquele produto/edição; “Estimado” significa avaliação inicial do nosso modelo. As duas origens aparecem explicitamente na interface e nos dados.

## Como consultar pelo site

Abra **Meu jogador → Ver companheiros de elenco**, ou a rota autenticada **http://rumo10.local/elenco**. A página mostra somente o clube do personagem, com nome, posição, origem real/fictícia e overall publicado/estimado. Não foi devolvido ao usuário o menu de configuração dos clubes.

Não é necessário reiniciar a carreira. A consulta é somente leitura e não muda atributos, resultados, decisões ou propostas. Antes da escolha do primeiro clube o link ainda não aparece.

## Fontes e associação

Os elencos usam o catálogo/roster público da ESPN. Equipes são identificadas por nome/alias explícito, país e competição; não foi usado pareamento aproximado livre entre nomes parecidos. Clube e edição da resposta são conferidos. Registros sem identidade/posição, jogadores de outro clube informado no `defaultTeam`, gêneros incompatíveis e afiliações ambíguas são excluídos.

Exemplo: [elenco Arsenal 2026/27, ESPN](https://site.api.espn.com/apis/site/v2/sports/soccer/eng.1/teams/359/roster?season=2026). Cada atleta real mantém `source`, `source_season`, data da consulta e ID ESPN. As fontes publicadas podem incluir atletas de base e reservas; o catálogo de referência não é certificado manualmente como a lista de inscrição de todas as federações.

As notas vêm das páginas públicas da [EA SPORTS FC 27](https://www.ea.com/games/ea-sports-fc/ratings), consultadas por clube, por exemplo [Arsenal](https://www.ea.com/games/ea-sports-fc/ratings/teams-ratings/arsenal/1). São apenas notas base publicadas, com `rating_source` e `rating_external_id`, sem importar cartas especiais, retratos, textos editoriais ou conteúdo promocional. Essas notas não representam forma ao vivo nem a evolução posterior dentro da carreira do RPG.

O cruzamento exige data de nascimento igual e nome completo correspondente, dois componentes do nome coincidentes ou primeiro nome abreviado com sobrenome exato. Associação ambígua mantém a estimativa; não adivinha a nota. Na consulta final, **116 IDs reais** apareceram em mais de um elenco e foram retirados desses vínculos até conferência, evitando duplicação global e transferência fictícia de um atleta real.

Posições genéricas de fonte (`D`, `M`, `F`) ficam preservadas. Quando há correspondência de rating, a posição mais específica publicada pode refinar o papel no catálogo. A interface mostra **DEF/MEI/ATA** para a posição genérica sem refinamento, em vez de fingir saber que todo defensor é zagueiro ou todo atacante é centroavante.

## Estimativa e fictícios

Atletas reais sem rating confirmado usam uma estimativa conservadora:

`overall = clamp(20, 95, round(overall inicial do clube - 16 + variação determinística [-4,+4] - ajuste etário))`.

O ajuste é 12 para menores de 18, 6 para menores de 20 e 8 quando a idade não pôde ser obtida. Quando há data de nascimento e falta idade, calcula-se a idade na data de referência. A variação deriva do ID estável e não muda ao recarregar. Isso é uma aproximação de nível de elenco, não uma avaliação baseada em scouting individual; não usar como prova de habilidade real.

Fictícios usam nomes gerados, posição, idade e overall determinísticos a partir de clube/slot. O modelo usa o nível inicial do clube, com variação limitada; sempre têm `origin=fictional` e `overall_origin=estimated_rumo10_v1`. Não recebem fonte externa ou nota publicada. Complementar não remove atletas reais já obtidos. Os plantéis importados completos podem ultrapassar 24 jogadores, incluindo a base que consta na fonte.

Uma falha temporária de fonte em reconstruções conserva um elenco real anterior disponível localmente. Uma nota publicada anterior não é apagada apenas porque a fonte ficou indisponível. O builder usa cache para esta edição; atualizar edições futuras exige nova conferência e política de versionamento, não simplesmente chamar isso de elenco atual de 2027.

## Armazenamento e instalação

`database/player-catalog.json` contém índice/contadores. `database/rosters/*.json` tem um arquivo por clube e permanece fora da raiz pública. `ClubSquad` carrega somente o elenco solicitado, com cache limitado a quatro clubes, evitando colocar todos os atletas no snapshot de cada carreira.

O catálogo também foi instalado no **ecohub**, apenas em **rumo10_elencos_catalogo**, via `bin/install-players.php`. A tabela contém o elenco de referência por clube em JSON, contadores e data. O instalador faz upsert sem alterar contas, saves ou tabelas de outros projetos. Não executa DROP/TRUNCATE.

Arquivos e ferramentas:

1. `bin/export-player-clubs.php`: exporta clubes/overall inicial para storage/player-sources, sem conectar ao banco.
2. `bin/build-player-catalog.py --fetch`: consulta fontes públicas, usa cache, cruza notas e gera complementos. Requer Python padrão, rede e os dados de clubes do projeto; nenhuma senha/API privada. Se faltar cache EA, busca os filtros públicos automaticamente.
3. `bin/install-players.php`: instala o catálogo na conexão local configurada, exclusivamente com prefixo rumo10_.

Receipts de fontes ficam em storage/player-sources, ignorados no Git. Os arquivos de catálogo gerados necessários à aplicação acompanham o repositório; não é preciso consultar os provedores durante o jogo.

## Limite da integração atual

**Esta entrega importa, persiste e exibe os elencos e overalls de referência.** Não substitui ainda a simulação de placares por uma simulação individual dos plantéis. Os NPCs ainda não têm gols/assistências/minutos/notas da carreira atribuídos a esses IDs, transferências próprias, lesões ou envelhecimento individual. O overall dos clubes continua usando divisão/campanha/forma; não foi trocado silenciosamente pela média dos atletas importados.

A próxima integração de gols/premiações deve usar essas identidades, conservar resultados antigos e não criar retrospectivamente autores para gols que não os tinham. Calendário global e duas janelas seguem a análise em Docs/15 e ainda são trabalho estrutural pendente. Essa entrega não concede prêmios individuais a partir de rankings incompletos.

## Testes executados localmente

- `tests/player-catalog.php`: **150.913 verificações** na execução final, cobrindo todos os clubes, IDs únicos, fontes/origens, intervalos de overall, contadores, mínimo de elenco, goleiros, repetição sem regeneração e render sem mutação. Cruzamento de Bukayo Saka verificado como rating publicado.
- `tests/http.php`: **58 verificações no IIS/MySQL local**, incluindo consulta autenticada ao elenco do próprio clube e carreira intacta após abertura.
- Teste de apresentação e lint PHP aprovados.
- Prévia isolada GET em navegador, **390×844**, 29 atletas do Arsenal, sem imagens quebradas ou overflow horizontal. Evidência local: storage/elenco-mobile.jpg. Nenhuma carreira pessoal alterada.

Não foram acessados Apache/produção nem executados testes em telefone físico. Os jogadores não foram certificados manualmente um por um; procedência e estimativas são explícitas.
