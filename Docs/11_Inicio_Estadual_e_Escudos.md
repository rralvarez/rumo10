# Início estadual, proximidade e escudos — versão 0.4

Pesquisa de participantes em 12/10/2026; implementação e verificação concluídas em 13/10/2026. O cadastro jogável tem 462 clubes e 35 competições/etapas. O catálogo nacional e paulista anterior é mantido; foram adicionadas 241 participações nas outras 26 UFs. Resultados históricos reais não são importados. Cada carreira cria seu próprio universo e conserva seu sorteio, ofertas, calendário e placares.

## Fluxo

1. Nome, UF, município válido e posição. Nenhum seletor livre de clube.
2. Roleta de perfil no servidor; quatro perfis com os pesos já existentes. Em SP, Talento de rua/Disciplina de base abrem A3; Recomeço difícil/Explosão e ansiedade abrem A4. Nas demais UFs, todos os perfis iniciam na mesma divisão de entrada.
3. Ordenação por distância em linha reta e desempate pelo nome; três clubes distintos, ou todos os participantes quando houver menos de três.
4. Escolha de uma proposta persistida. O servidor rejeita índices inexistentes, sorteios repetidos e ações em fases incorretas; CSRF, versão e chave idempotente seguem obrigatórios.
5. Primeiro treino do clube e seu primeiro jogo, respeitando folgas.

## Divisões cadastradas

| UF | Divisão/etapa de entrada | Clubes | Fonte de participantes/formato |
| --- | --- | ---: | --- |
| AC | Acreano Série B | 4 | [Fonte](https://ge.globo.com/ac/futebol/acreano-segunda-divisao/noticia/2026/08/11/guia-do-acreano-serie-b-regulamento-clubes-participantes-e-mais-detalhes-sobre-a-2a-divisao.ghtml) |
| AL | Alagoano Segunda Divisão | 6 | [Fonte](https://futeboldealagoas.net/assets/uploads/177223121748.pdf) |
| AM | Amazonense Série B | 7 | [Fonte](https://www.fafamazonas.com.br/site/noticia/faf-divulga-clubes-da-serie-b-do-barezao/) |
| AP | Amapaense Série B | 12 | [Fonte](https://santanaesporteclube.com.br/noticia_view.php?id_noticia=10) |
| BA | Baiano Série B | 10 | [Fonte](https://www.fbf.org.br/noticias/detalhe/12835) |
| CE | Cearense Série C | 7 | [Fonte](https://futebolcearense.com.br/2011/noticia_ver.asp?id=18795) |
| DF | Candangão Série B | 7 | [Fonte](https://www.ffdf.com.br/pt/noticias/view.php?q=1268) |
| ES | Capixaba Série B | 10 | [Fonte](https://futebolcapixaba.com/2026/04/51396/) |
| GO | Goiano Terceira Divisão | 7 | [Fonte](https://opopular.com.br/esporte/com-sete-clubes-3-divis-o-do-goiano-comeca-neste-final-de-semana-1.3466649) |
| MA | Maranhense Série B | 12 | [Fonte](https://imirante.com/esporte/sao-luis/2026/03/22/fmf-confirma-participantes-e-formula-de-disputa-do-maranhense-serie-b-2026) |
| MG | Mineiro Segunda Divisão | 12 | [Fonte](https://www.fmf.com.br/Competicoes/Equipes.aspx?d=3) |
| MS | Sul-Mato-Grossense Série B | 8 | [Fonte](https://ge.globo.com/google/amp/ms/futebol/campeonato-sul-mato-grossense/noticia/2026/04/01/sul-mato-grossense-serie-b-veja-os-jogos-datas-e-formato.ghtml) |
| MT | Mato-grossense Segunda Divisão | 10 | [Fonte](https://fmfmt.com.br/pt/noticias/view.php?id=2276) |
| PA | Paraense Série A3 | 12 | [Fonte](https://www.fpfpara.com.br/competicao/112) |
| PB | Paraibano Segunda Divisão | 10 | [Fonte](https://ge.globo.com/pb/futebol/paraibano-segunda-divisao/noticia/2026/09/11/guia-da-2a-divisao-do-paraibano-2026-tudo-o-que-voce-precisa-saber-sobre-a-competicao.ghtml) |
| PE | Pernambucano 2026/27 — primeiro turno | 24 | [Fonte](https://fpf-pe.com.br/pt/noticias/view.php?q=12567) |
| PI | Piauiense Série B | 6 | [Fonte](https://interativos.ge.globo.com/pi/futebol/campeonato-piauiense/guia/guia-do-piauiense-serie-b-2026) |
| PR | Paranaense Terceira Divisão | 11 | [Fonte](https://federacaopr.com.br/noticias/paranaense/fpf-divulga-as-datas-dos-jogos-da-terceira-divisao-paranaense-2026/) |
| RJ | Carioca Série C | 16 | [Fonte](https://rsssfbrasil.com/tablesr/rj2026l5.htm) |
| RN | Potiguar Segunda Divisão | 5 | [Fonte](https://fnf.org.br/campeonato-potiguar-segunda-divisao-2026-cinco-clubes-disputarao-a-taca-a-partir-de-7-de-outubro) |
| RO | Rondoniense Série B | 2 | [Fonte](https://ge.globo.com/ro/futebol/campeonato-rondoniense/noticia/2026/08/31/rolim-de-moura-goleia-vec-por-4-a-1-e-conquista-titulo-inedito-da-serie-b.ghtml) |
| RR | Roraimense | 9 | [Fonte](https://www.futebolroraimense.com.br/post/campeonato-roraimense-giroagro-2026) |
| RS | Gaúcho Série B | 10 | [Fonte](https://fgf.com.br/noticia/fgf-divulga-regulamento-especifico-e-tabela-basica-do-gauchao-serie-b-2026) |
| SC | Catarinense Série C | 4 | [Fonte](https://fcf.com.br/divulgada-a-tabela-e-o-regulamento-especifico-da-serie-c-do-catarinense/) |
| SE | Sergipano Série A2 | 12 | [Fonte](https://fsf-se.com.br/congresso-tecnico-define-formato-do-sergipano-da-serie-a2-2/) |
| TO | Tocantinense Segunda Divisão | 8 | [Fonte](https://ge.globo.com/google/amp/to/futebol/tocantinense-segunda-divisao/noticia/2026/09/05/guia-da-segundona-tocantinense-2026-tudo-sobre-o-torneio-que-inicia-neste-sabado.ghtml) |

## Casos especiais e limites

- SP: A4/A3 por decisão de design solicitada; a Segunda Divisão paulista continua cadastrada, sem entrada pela roleta.
- SE: A2 provisória autorizada enquanto a A3 de 2026 recebe inscrições. Não foram inventados inscritos na A3. [Comunicado da FSF](https://fsf-se.com.br/serie-a3-inscricoes-para-o-campeonato-sergipano-estao-abertas/).
- RO: apenas Rolim de Moura e Vilhena EC na Série B; mostrar duas propostas.
- RR: uma única divisão profissional, usada como porta de entrada.
- PE: primeira etapa do unificado 2026/27, com grupos, oitavas e quartas em jogo único e quadrangular final. Não se apresenta o vencedor dessa etapa como campeão de todo o estadual. Os três classificados para janeiro de 2027 não disputam esse turno no protótipo; os sete clubes com entrada direta em 2027 não compõem as propostas iniciais.
- PA: Parauapebas foi confirmado na fase principal pela tabela oficial de setembro; Tiradentes, eliminado na Pré-A3, não é oferecido como integrante dos grupos principais.
- AL: Sporting é o nome adotado em março pelo antigo Sete de Setembro, com sede em Teotônio Vilela. O REC anterior ainda usava o nome antigo; [reportagem sobre a mudança](https://ge.globo.com/al/noticia/2026/04/09/conheca-o-sueco-que-se-inspira-em-azarao-da-champions-para-transformar-clube-de-alagoas.ghtml).

Os calendários reproduzem a estrutura pesquisada (turno/returno, grupos, aproveitamento no PR, repescagens, decisões e número de acessos), com ordem de rodadas gerada pelo jogo. Datas, estádios, vantagem específica de mando e todos os critérios administrativos/judiciais não são reproduzidos integralmente. Desempates do motor usam vitórias, saldo, gols, cartões e sorteio; situações excepcionais de WO, punições e inscrições Sub-23 não são simuladas. O Grupo X carioca é jogado, mas seu efeito sobre a fase preliminar de 2027 não está conectado. Chaveamentos posteriores podem usar reordenamento por campanha em vez de conservar todos os cruzamentos da tabela real.

As divisões superiores dos novos estaduais ainda não estão cadastradas como pirâmides completas. O acesso esportivo é registrado e essa limitação aparece no resumo: permanecer no mesmo clube repete sua divisão no ano seguinte. Evolução pessoal, histórico e propostas reais continuam funcionando; transferir-se pode levar a competições nacionais ou paulistas já disponíveis. Acesso/rebaixamento nacional e paulista seguem conectados como antes. Não se afirma implementação integral das pirâmides das 27 UFs.

## Cidades e imagens

`database/municipalities.json` contém os centros municipais derivados do [conjunto de municípios brasileiros com códigos IBGE](https://github.com/kelvins/municipios-brasileiros). `database/starting-clubs.json` registra o município usado em cada proposta. A distância é Haversine, aproximada, entre centros urbanos; não consulta localização do navegador. Regiões administrativas do DF são representadas por Brasília quando não há município independente; GREVAL e Luziânia usam suas cidades de Goiás, embora participem da federação do DF. Clubes que atuam fora da sede podem ter distância diferente até o campo de jogo.

`database/club-badges.json` relaciona os 462 clubes a arquivos em `public/assets/clubs/`, com fonte, URL de download e data. Fontes: Federação Paulista (88 clubes), Federações Cearense, Paranaense e Paraibana para imagens disponíveis em fichas oficiais, e [Escudos FC](https://escudosfc.com.br/) para o acervo restante. Arquivos foram validados por assinatura e `getimagesize`; imagens WebP oficiais foram convertidas em PNG preservando seu conteúdo. O arquivo original pode representar variante histórica: não se certificou manualmente a identidade visual vigente de todos os 462 clubes. Marcas e direitos pertencem aos respectivos titulares; não se concede licença de exploração comercial.

O instalador oficial continua realizando upserts exclusivamente em `rumo10_competicoes` e `rumo10_clubes_oficiais`, sem apagar aliases/cadastros anteriores. O catálogo JSON determina os clubes disponíveis no jogo; registros antigos no banco podem permanecer para compatibilidade. As carreiras anteriores conservam suas participações; ampliar o catálogo não insere novos campeonatos dentro de um universo já salvo.
