# Expansão mundial e Concacaf — 13/10/2026

## Seleção e cobertura

A solicitação de 100 clubes foi interpretada e confirmada pelo usuário como **seleção mundial**. A seleção auditável está em `database/world-top100-2026.json`: 100 posições da visualização pública masculina da [Opta](https://theanalyst.com/articles/who-are-the-best-football-team-in-the-world-opta-power-rankings), associadas a clubes do jogo sem duplicar homônimos. O bundle consultado não apresenta uma data de atualização explícita: 13/10 é a consulta, não uma data de ranking inventada. Não tratamos essa seleção como top 100 FIFA; o [ranking FIFA consultado](https://inside.fifa.com/fifa-rankings/fifa-club-world-cup/2025) é para qualificação ao Mundial de Clubes 2025.

Foram acrescentados **262 clubes e 25 competições/circuitos**, além dos 1.036 clubes e 63 etapas existentes: **1.298 clubes, 88 etapas em 40 mercados**. Importar uma divisão inteira quando há participantes identificados permite oferecer adversários e destinos além dos 100 selecionados.

- Concacaf: Estados Unidos, Canadá, México, Costa Rica, Honduras, Guatemala, El Salvador, Nicarágua, Panamá, Haiti, Jamaica, República Dominicana e Suriname. São os 13 países representados entre os primeiros 100 do [ranking da Concacaf](https://www.concacaf.com/rankings/club), com data declarada de 12/10/2026; o recibo factual está em `database/concacaf-ranking-2026.json`. Isso **não cobre todas as associações da Concacaf**.
- Mercados mundiais adicionais: Noruega, Dinamarca, Grécia, Chipre, Rússia, Arábia Saudita, Equador, Colômbia, Tchéquia, Polônia, Hungria e Croácia.
- MLS: 30 participantes, incluindo três canadenses. País do clube e país da competição são campos distintos. Canadá também tem os oito participantes da CPL, incluindo Inter Toronto e FC Supra, segundo a [própria liga](https://external-tags.canpl.ca/about-us).
- MLS, México, Costa Rica, Honduras, Guatemala, El Salvador, Noruega, Dinamarca, Grécia, Rússia, Arábia Saudita, Equador e Colômbia usam listas de participantes identificadas pelo publicador ESPN para a temporada consultada, com URLs de liga/federação registradas separadamente. A procedência não equivale a verificação administrativa de cada filiação.
- **Chipre, Tchéquia, Polônia, Hungria, Croácia, Nicarágua, Panamá, Haiti, Jamaica, República Dominicana e Suriname** usam circuitos de clubes de referência. O nome exibido contém “Circuito” e “adaptado”; eles **não reproduzem a liga nacional inteira**. A lista cipriota da ESPN não foi mantida porque não confirmou a edição corrente. Outros dois/três clubes da mesma liga na fonte complementam circuitos que teriam apenas um selecionado.

## Mecânica e calendários

| Mercado | Implementação |
|---|---|
| MLS | 28 jogos contra a própria conferência, seis adversários distintos da outra, 34 por clube e 17 mandos. Nove classificados por conferência; wild card 8º × 9º; série melhor de três por vitórias, sem agregado; semifinais/finais de conferência e MLS Cup em jogo único. Supporters’ Shield separado da MLS Cup. |
| CPL | 28 partidas por clube; quatro classificados, semifinais ida/volta e final única com mando do melhor colocado. CPL Shield separado da North Star Cup. |
| México | Dois torneios de 17 partidas, oito classificados, quartas/semifinais/final ida e volta. Melhor posição resolve empate agregado em quartas/semifinais, pênaltis na final. |
| Costa Rica | Dois turnos de 18 partidas, semifinal/final ida e volta dos quatro melhores. Aproximação, sem final adicional entre vencedor da fase regular e vencedor do mata-mata. |
| Honduras, Guatemala, El Salvador | Dois turnos de 22 partidas e oito classificados para mata-mata ida/volta. Aproximação: regras específicas de repescagem/quadrangulares não reproduzidas. |
| Colômbia | Dois turnos de 19 partidas e oito classificados para mata-mata. **Segundo torneio adaptado**, sem quadrangulares oficiais. |
| Noruega/Rússia/Arábia Saudita | Liga ida e volta. Divisões inferiores e acesso/rebaixamento não disponíveis nestes novos mercados. |
| Dinamarca | 22 partidas, split em dois grupos de seis, mais dez partidas com pontos acumulados. Vagas e playoffs europeus não modelados. |
| Grécia | 26 partidas; split 4/4/6. Aproximação: pontos inteiros conservados nos grupos, sem redução específica da pontuação. |
| Equador | 30 partidas, grupos finais 6/4/6 com ida/volta e pontos acumulados. Sem vagas continentais ou reposição de rebaixados. |
| Circuitos adaptados | Ida/volta entre clubes selecionados. Sem acesso, rebaixamento ou título apresentado como conquista oficial real. |

Fontes dos formatos: [MLS 2026](https://www.mlssoccer.com/league-reports/competition-guidelines/), [playoffs MLS 2026](https://www.mlssoccer.com/playoffs/2026/news/audi-2026-mls-cup-playoffs-format-how-it-works), [CPL](https://external-tags.canpl.ca/about-us), [regulamentos FMF](https://fmf.mx/justicia-deportiva/reglamentos), [Dimayor](https://dimayor.com.co/wp-content/uploads/2026/04/REGLAMENTO-LIGA-2026-V0.pdf), [LigaPro](https://ligapro.ec/tabla-de-posiciones/).

O rodízio da MLS é gerado, não uma cópia dos confrontos/datas reais. Empates em playoffs usam o mecanismo de pênaltis; prorrogação não é disputada. As quantidades e a série de dois triunfos foram testadas. Normal, Rápido e Expresso compartilham esses calendários.

**A expansão não implementa o relógio global e as duas janelas de transferências analisados no documento 15.** O fechamento continua anual por carreira, com outras ligas completadas nesse momento. Edições reais de referência permanecem separadas das temporadas seguintes fictícias. Não foram acrescentadas copas continentais, Champions Cup, Leagues Cup ou Mundial de Clubes.

## Escudos, força e elencos

Todos os 262 clubes novos têm escudo local e dois/três HEX aproximados extraídos da paleta. ESPN, Opta e Concacaf são publicadores das imagens, não licenças comerciais. A fonte do escudo está separada da fonte dos participantes. Imagens genéricas detectadas na conferência foram retiradas do cadastro. O manifesto está em `database/club-badges.json`.

183 clubes novos têm overall inicial derivado do snapshot público Opta, limitado a 20–95 e identificado como nota de jogo. Demais clubes usam a base provisória da competição. Esses valores alimentam a simulação já existente; campanhas simuladas atualizam a força no ano seguinte. Não são notas FIFA ou EA dos clubes. A seleção top 100 contém a nota da fonte para auditoria, sem substituir as forças já salvas nas carreiras antigas.

O catálogo atual contém **36.592 atletas únicos**: **21.121 reais em 654 clubes**, **15.471 fictícios**, **4.883 notas publicadas FC 27** e **31.709 estimadas**. Todos os elencos têm pelo menos 24 atletas e dois goleiros. 174 identidades reais com associação ambígua entre clubes foram excluídas antes do complemento fictício; não foram atribuídas arbitrariamente. Falhas de fonte ficam no resumo do catálogo; a consulta não transforma uma estimativa em nota publicada.

**Lionel Messi** está no **Inter Miami CF-US**, identidade ESPN `45843`, nascimento 24/06/1987, overall **89 publicado FC 27**, vinculado pela identidade/data de nascimento. [Elenco oficial do clube](https://www.mlssoccer.com/clubs/inter-miami-cf/roster/) e [perfil MLS](https://www.mlssoccer.com/players/lionel-messi/). Sua página no jogo é a referência do elenco; gols/assistências de NPCs, escalações individuais e premiações estatísticas **continuam pendentes**.

## Instalação, atualização e teste

O jogo local já foi atualizado. Abra **http://rumo10.local** e continue sua carreira; as novas propostas aparecem conforme desempenho e evolução. Novas carreiras continuam começando no Brasil, pelo formulário/roleta/três propostas locais. Em `/jogador`, “Ver companheiros de elenco” abre `/elenco` com o elenco do seu clube. Uma carreira já encerrada antes da expansão passa a incorporar os novos mercados no próximo fechamento de temporada; não foi ressorteada a proposta já salva.

Os scripts não apagam campanhas. Instalação em outro ambiente exige o `.env` privado e banco `ecohub`; os únicos catálogos alterados são `rumo10_competicoes`, `rumo10_clubes_oficiais` e `rumo10_elencos_catalogo`, com migração existente sob prefixo `rumo10_`. Raiz pública e roteamento IIS/Apache permanecem `public/`, `web.config` e `.htaccess`.

```powershell
php bin/install-official.php
php bin/install-players.php
php -d memory_limit=128M tests/world-markets.php
php tests/player-catalog.php
php tests/official.php
php tests/http.php http://rumo10.local
```

Use o PHP local com mbstring, pdo_mysql e curl habilitados, conforme README. Para reconstruir o catálogo público: Python/Pillow `bin/build-world-markets.py`, `php bin/export-player-clubs.php`, `python bin/build-player-catalog.py --fetch`; então os instaladores. Fontes/cache ficam em `storage/`, fora do Git; não existe sincronização diária automática. O helper `build-concacaf.py` não deve ser executado isoladamente.

Verificação efetuada no computador local: 25 calendários até o fim, MLS com 34/17, CPL com 28, melhor de três decidida por vitórias apesar de saldo contrário, renovação para ano 2, títulos renderizados, 100 clubes associados, elencos/identidades/overalls e fluxo HTTP IIS com conta descartável. Os escudos foram conferidos em painel de imagens, sem imagens genéricas repetidas no catálogo final. A prévia do elenco no servidor PHP auxiliar não abriu no navegador integrado; não foi declarado teste visual móvel dessa página nesta entrega. Os testes não modificam a carreira do usuário. Nenhum teste em Apache/produção foi declarado.
