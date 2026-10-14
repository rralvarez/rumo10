# África, América do Sul, ciclo de carreira e catálogo compartilhável

## Cobertura em 13/10/2026

Foram adicionados **188 clubes em 24 mercados**, sendo **20 africanos**: África do Sul, Nigéria, Gana, Quênia, Marrocos, Egito, Argélia, Tunísia, Tanzânia, RD Congo, Angola, Costa do Marfim, Zâmbia, Uganda, Ruanda, Camarões, Senegal, Mali, Etiópia e Líbia. Esta primeira etapa **não cobre todas as 54 federações da CAF**. A pergunta sobre ampliar a todas as federações foi enviada durante o trabalho; enquanto sem resposta, adotou-se a opção de principais mercados.

Chile, Peru e Venezuela completam os dez países da CONMEBOL. Guiana foi incluída como circuito; Suriname já estava cadastrado na etapa Concacaf. Portanto os **12 países independentes da América do Sul** têm clubes no universo. Guiana Francesa e outros territórios não são países independentes e não foram acrescentados. “Todos” refere-se aqui a países, não a todos os clubes de todas as divisões do continente.

O total é **1.486 clubes e 112 competições/etapas/circuitos em 64 mercados**. Chile, Peru, Venezuela, África do Sul, Nigéria, Gana e Quênia usam listas ESPN da temporada consultada. África restante e Guiana usam quatro clubes de referência de uma mesma liga no snapshot público Opta, identificados na interface como **circuitos adaptados**. Peru e Venezuela também trazem “formato adaptado” no nome: a implementação é liga ida/volta, sem suas fases/títulos distintos. Não se afirma filiação administrativa verificada só porque o publicador listou o clube. O catálogo contém fontes e grau de cobertura em `database/africa-south-coverage-2026.json`.

Fontes: [associações CAF](https://www.cafonline.com/en/inside-caf/about-us/official-documents/member-associations/), [CAF Champions League 2026/27](https://www.cafonline.com/en/caf-champions-league/news/africa-s-elite-learn-their-path-to-the-group-stages-of-the-totalenergies-caf-champions-league-202627/), [Opta](https://theanalyst.com/articles/who-are-the-best-football-team-in-the-world-opta-power-rankings), URLs ESPN e das federações registradas em cada clube/competição. A referência CAF confirma clubes e países relevantes; a copa continental em si ainda não está implementada.

**173 dos 188 novos clubes têm arquivo de escudo identificado**. Quinze permanecem pendentes de um ativo verificável; imagens genéricas, mapas e brasões de cidade encontrados nos publicadores/acervos foram retirados, e a interface usa iniciais. A lista está em `database/africa-badge-overrides.json`. Para esses quinze, cores também estão pendentes, com paleta neutra explicitamente provisória. Outros HEX continuam aproximados, extraídos das imagens. Acervos comunitários são identificados como tal e não representam licença comercial.

Elencos: **23.300 reais em 718 clubes**, **18.454 fictícios**, **41.754 identidades vinculadas**, **4.876 ratings publicados FC 27** e **36.878 estimados**. Os overalls globais são referências; personagens e atletas de uma simulação devem ter atributos próprios. Foram excluídas 227 associações reais ambíguas entre clubes; isso explica pequenas diferenças nas contagens publicadas anteriores. Identidade e origem da nota continuam explícitas.

## Evolução e aposentadoria

A idade começa em 18 e avança um ano ao iniciar a temporada seguinte. Aos 39, completar a última temporada encerra a carreira com idade 40, sem roleta de proposta nem renovação. Um personagem legado que já tenha 40 ou mais encerra ao tentar a próxima ação; sua idade não é reduzida. A aposentadoria voluntária fica em **Meu jogador → Encerrar minha carreira**, aos 35 ou mais, fora de uma partida em andamento. O site mostra explicação e uma confirmação obrigatória. O histórico fica consultável; a carreira aposentada não volta a jogar, inclusive após arquivamento/restauração. Outra carreira pode ser criada normalmente.

Os **32 anos são o auge potencial de jogo**, não um diagnóstico biológico nem uma garantia de atingir 100. O teto jovem parte de 65 aos 18 e sobe até 100 aos 32: `65 + floor((idade − 18) × 35 / 14)`. Somente XP eleva os atributos. Não há bônus automático de habilidade por aniversário. Ganho de XP: 1,2 até 23, 1 até 28, 0,8 até 32, 0,6 até 34 e 0,4 depois disso. XP excedente no teto não fica acumulando infinitamente.

Após 32, a cada nova temporada: velocidade, resistência e força perdem 2; passe, finalização, drible e desarme perdem 1; visão e posicionamento perdem 1 a cada três anos. O teto também diminui nesses ritmos. Escolhas com efeitos negativos de habilidades passam a efetivamente reduzir a habilidade, em vez de terem sua penalidade descartada pelo motor. Situações, confiança, energia, risco de lesão e cartões continuam influenciando a trajetória. Um jogador prejudicado pode ter um pico efetivo abaixo do teto; um atributo individual ainda pode melhorar depois dos 32 quando estiver abaixo dele.

Legados acima dos novos tetos jovens não são rebaixados retroativamente; ficam sem crescimento adicional acima do teto e passam a sofrer o envelhecimento gradual nas próximas temporadas. Normal, Rápido e Expresso usam a mesma curva. Simulação expressa termina corretamente na aposentadoria.

Lesões em campo também podem reduzir a resistência em 1, com efeito visível. A curva de aposentadoria é do personagem desta carreira; os atletas reais/fictícios do catálogo global continuam referências, sem aposentadoria global automática que afetaria os outros jogos.

## Como os três jogos podem compartilhar dados

O banco continua sendo **ecohub**, e todas as tabelas criadas nesta tarefa mantêm o prefixo autorizado **rumo10_**. Não foram renomeadas tabelas nem alterados outros projetos. O prefixo não impede que sejam lidas pelos outros jogos; o importante é separar **referência global** de **estado de uma partida/carreira**.

| Tabela global | Conteúdo e uso |
|---|---|
| `rumo10_clubes_oficiais` | Cadastro, país/UF, cores, fontes, competições e metadados em JSON. `codigo` atualmente é o identificador textual do clube. |
| `rumo10_catalogo_clubes` | Novo `id` numérico estável, associado ao código. Outros jogos devem referenciar este ID em vez do nome exibido. Reinstalar preserva IDs existentes. Renomes/fusões futuros precisam atualizar o vínculo existente, nunca apagar/recriar sua identidade. |
| `rumo10_competicoes` | Competições e formatos de referência; os resultados simulados não ficam aqui. |
| `rumo10_jogadores_catalogo` | Novo cadastro individual: ID, nome, nascimento, posição, origem real/fictícia, overall de referência, origem da nota e fontes. Identidades reais usam provedor, como `espn:45843` para Messi; fictícias têm ID determinístico. |
| `rumo10_elenco_jogadores` | Novo vínculo jogador–clube–edição do catálogo atual. Permite buscar elencos em SQL sem decodificar um JSON grande. Ainda não é histórico real completo de transferências. |
| `rumo10_elencos_catalogo` | Cache JSON por clube, mantido por compatibilidade e exportação. |

`rumo10_clubes` é o catálogo antigo do piloto. `rumo10_times` contém inscrições de uma **temporada de carreira**, não o catálogo compartilhado de clubes. Não devem ser confundidos com `rumo10_clubes_oficiais`.

**Não compartilhar como estado global:** `rumo10_carreiras`, `rumo10_universos`, `rumo10_temporadas`, `rumo10_partidas`, `rumo10_estatisticas`, `rumo10_atributos`, `rumo10_estados`, ofertas, gols, suspensões, lesões e evolução. Cada jogo/sessão precisa de cópias próprias, referenciando IDs do catálogo. Assim Messi pode evoluir ou mudar de clube num jogo sem mudar o elenco inicial dos outros dois.

Consulta de exemplo para outro jogo:

```sql
SELECT c.id AS clube_id, o.nome AS clube,
       p.id AS jogador_id, p.nome, p.posicao,
       p.overall_referencia, p.origem_overall, e.edicao
FROM rumo10_catalogo_clubes c
JOIN rumo10_clubes_oficiais o ON o.codigo = c.codigo
JOIN rumo10_elenco_jogadores e ON e.clube = c.codigo
JOIN rumo10_jogadores_catalogo p ON p.id = e.jogador_id
WHERE c.codigo = 'Inter Miami CF-US';
```

O instalador é a autoridade para atualizar as referências. A aplicação atual mantém arquivos JSON locais como cache de leitura do motor; as novas tabelas normalizadas já estão preenchidas para consumo pelos próximos jogos. Os outros jogos devem começar uma simulação com um snapshot/versionamento do catálogo. No futuro, pode haver serviço/API neutra e tabelas com prefixo neutro, com migração coordenada e compatibilidade; não é necessário duplicar agora os atletas para cada jogo. Nenhum usuário SQL, permissão nova, senha compartilhada ou endpoint público foi criado nesta tarefa.

## Instalação e verificações

```powershell
php bin/install-official.php
php bin/install-players.php
php tests/lifecycle.php
php tests/africa-south.php
php tests/shared-catalog.php
php -d memory_limit=256M tests/http.php http://rumo10.local
```

Use PHP com mbstring, pdo_mysql e curl conforme README. A memória extra do último comando é **somente para o cliente de testes**, que retém várias respostas/snapshots; a configuração IIS não foi alterada. Houve um teste interrompido por memória no cliente, e sua conta descartável foi removida. A repetição concluiu **64 verificações HTTP**, incluindo confirmação, persistência, replay e totais da aposentadoria.

Os testes de ciclo verificam tetos por idade, chegada possível a 100 aos 32, ausência de bônus automático, declínio físico, bloqueio abaixo de 35, aposentadoria voluntária, encerramento aos 40, recusa de novas ações e término do Expresso. Também foram conferidos calendários dos 24 mercados, 12 países sul-americanos, integridade dos elencos, IDs globais e Messi no clube correto. Os testes usam carreiras descartáveis; a campanha do usuário não foi aposentada.

Para reconstruir a expansão: Python/Pillow `bin/build-africa-south-america.py`, exportação de clubes, `bin/build-player-catalog.py --fetch` e os dois instaladores. Não foi implementada sincronização automática. Relógio global, duas janelas reais, autoria de gols por NPC e premiações estatísticas continuam pendentes, conforme documentos anteriores. Nenhum teste em Apache/produção foi declarado.
