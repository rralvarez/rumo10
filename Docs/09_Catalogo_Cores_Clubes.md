# Catálogo de cores — 21 clubes

Regra confirmada: cores tradicionais separadas dos HEX aproximados de interface. Terceira cor preservada; nenhuma paleta é certificada.

Proposta provisória: pares de cores do briefing v0.2, sem importar identidades visuais ou escudos.

Implementação atual: seed database/clubs.json e rumo10_clubes; pares apresentados em cards. Fontes fornecidas no briefing foram consultadas em 12/10/2026. Lista agregada Wikipedia, Mauá, Nacional, SPFC, Juventus, Ituano, Portuguesa Santista, Santo André, Santos, manual Guarani, Novorizontino, Jabaquara e Ferroviária retornaram conteúdo. PDF municipal Botafogo e link Mirassol falharam na consulta; registros permanecem provisórios. HEX foram preservados como escolhas de design, sem dedução de código oficial.

| Clube / cidade | Cores aproximadas | Terceira | Fonte |
|---|---|---|---|
| São Caetano Futebol / São Caetano do Sul | Azul #0066B3; Branco #FFFFFF |   | [Referência](https://en.wikipedia.org/wiki/List_of_football_clubs_in_S%C3%A3o_Paulo_(state)) |
| EC Santo André / Santo André | Azul #0865B1; Branco #FFFFFF |   | [Referência](https://www.dgabc.com.br/Noticia/4182851/santo-andre-lanca-novos-uniformes-para-temporada-2025) |
| São Bernardo FC / São Bernardo do Campo | Amarelo #F5C518; Preto #111111 |   | [Referência](https://en.wikipedia.org/wiki/List_of_football_clubs_in_S%C3%A3o_Paulo_(state)) |
| EC Água Santa / Diadema | Azul #0D5EA9; Branco #FFFFFF |   | [Referência](https://en.wikipedia.org/wiki/List_of_football_clubs_in_S%C3%A3o_Paulo_(state)) |
| Mauá Futebol Clube / Mauá | Amarelo #F5C518; Preto #111111 |   | [Referência](https://pt.wikipedia.org/wiki/Mau%C3%A1_Futebol_Clube) |
| SC Corinthians Paulista / São Paulo | Preto #111111; Branco #FFFFFF |   | [Referência](https://en.wikipedia.org/wiki/List_of_football_clubs_in_S%C3%A3o_Paulo_(state)) |
| SE Palmeiras / São Paulo | Verde #006437; Branco #FFFFFF |   | [Referência](https://en.wikipedia.org/wiki/List_of_football_clubs_in_S%C3%A3o_Paulo_(state)) |
| São Paulo FC / São Paulo | Vermelho #C8102E; Branco #FFFFFF | Preto #111111 | [Referência](https://www.saopaulofc.net/o-clube/simbolos/) |
| CA Juventus / São Paulo | Grená #781A2E; Branco #FFFFFF |   | [Referência](https://www.juventus.com.br/clube/historia/1924-a-1961/) |
| Nacional AC / São Paulo | Azul #1454A8; Branco #FFFFFF | Vermelho #D32F2F | [Referência](https://pt.wikipedia.org/wiki/Nacional_Atl%C3%A9tico_Clube_%28S%C3%A3o_Paulo%29) |
| Santos FC / Santos | Preto #111111; Branco #FFFFFF |   | [Referência](https://www.santosfc.com.br/sucesso-de-critica-e-publico-santos-fc-e-umbro-lancam-novo-uniforme-no-business-center-em-sao-paulo/) |
| AA Portuguesa / Santos | Verde #006B3B; Vermelho #C8102E |   | [Referência](https://www.portuguesasantista.com.br/o-clube/historia/) |
| Jabaquara AC / Santos | Vermelho #C8102E; Amarelo #F7C531 |   | [Referência](https://ge.globo.com/sp/santos-e-regiao/futebol/times/jabaquara/noticia/2026/01/31/jabaquara-apresenta-elenco-e-novos-uniformes-para-disputa-do-paulistao-a4.ghtml) |
| AA Ponte Preta / Campinas | Preto #111111; Branco #FFFFFF |   | [Referência](https://en.wikipedia.org/wiki/List_of_football_clubs_in_S%C3%A3o_Paulo_(state)) |
| Guarani FC / Campinas | Verde #006B3B; Branco #FFFFFF |   | [Referência](https://guaranifc.com.br/wp-content/uploads/2023/10/Manual_Identidade_Guarani.pdf) |
| Red Bull Bragantino / Bragança Paulista | Branco #FFFFFF; Vermelho #D61F26 | Azul #0C2B4E | [Referência](https://en.wikipedia.org/wiki/List_of_football_clubs_in_S%C3%A3o_Paulo_(state)) |
| Ituano FC / Itu | Vermelho #C8102E; Preto #111111 |   | [Referência](https://ituanofc.com/clube/) |
| Botafogo FC / Ribeirão Preto | Vermelho #C8102E; Preto #111111 | Branco #FFFFFF | [Referência](https://www.ribeiraopreto.sp.gov.br/portal/pdf/cultura849.pdf) |
| Mirassol FC / Mirassol | Amarelo #F5C518; Verde #157A3E | Branco #FFFFFF | [Referência](https://ge.globo.com/sp/tem-esporte/futebol/times/mirassol/noticia/2026/02/11/mirassol-lanca-novo-uniforme-de-2026-que-vestira-o-time-na-libertadores-veja-fotos.ghtml) |
| Grêmio Novorizontino / Novo Horizonte | Amarelo #F5C518; Preto #111111 |   | [Referência](https://www.gremionovorizontino.com.br/2025/12/31/gremio-novorizontino-lanca-novos-uniformes-oficiais-para-temporada-2026/) |
| Associação Ferroviária de Esportes / Araraquara | Grená #782138; Branco #F6F1EA |   | [Referência](https://ge.globo.com/sp/ribeirao-preto-e-regiao/futebol/times/ferroviaria/noticia/2026/01/30/ferroviaria-homenageia-anos-1980-em-uniformes-para-temporada-2026-veja-fotos.ghtml) |

Pendências: revisão de Botafogo/Mirassol e das terceiras cores em fontes oficiais; painel administrativo e versionamento de identidades. Status conservador de seed: briefing_provisorio; sem divisões oficiais de 2026.

Critérios de teste: 21 registros, slugs distintos, HEX válidos; seed idempotente e sem substituir cores alteradas.
