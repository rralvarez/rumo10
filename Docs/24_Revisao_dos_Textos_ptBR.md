# Revisão editorial das situações — 14/10/2026

Revisadas as 214 situações e 856 alternativas do catálogo: 200 entradas do JSON/quatro arquivos TSV, 12 situações originais e duas situações de reserva. A revisão usa português brasileiro e ações concretas de futebol, mantendo os identificadores e as mecânicas existentes.

Na expansão de 200 situações, 35 enunciados e 126 alternativas foram alterados. As 12 situações originais e os textos do banco também receberam ajustes, junto às mensagens de resultado dos presets. Exemplos:

| Antes | Depois |
| --- | --- |
| Primeiro poste | Primeira trave |
| Gala comunitária | Evento beneficente na comunidade |
| Servir a corrida | Lançar o companheiro que está correndo |
| Reciclar a posse | Voltar o passe para manter a bola |
| Abordar o portador | Dar o bote em quem está com a bola |
| Somar-se à reclamação | Reclamar junto com ele |

`bin/revise-scenes-ptbr.py` mantém JSON e TSV sincronizados para esta revisão editorial. As escolhas usam verbos de ação e evitam termos burocráticos quando uma descrição direta resolve. Não houve mudança de probabilidades, XP, custos, riscos, seleção de situações, ordem das alternativas ou efeitos. Uma carreira em andamento conserva o ID da situação e recebe o texto atual quando a tela é recarregada; mensagens já registradas no histórico permanecem como foram gravadas.

Verificação: comparação estrutural do JSON com o commit anterior e dos efeitos/IDs das situações originais; correspondência exata das quatro fontes TSV; `tests/scenes-strength.php` com 3.143 verificações aprovadas, incluindo 214 situações/856 alternativas; `tests/presentation.php` aprovado; sintaxe PHP e `git diff --check` aprovados. Os testes do motor usaram o cache local do catálogo; não foi reexecutada a suíte HTTP completa para esta alteração exclusivamente editorial.
