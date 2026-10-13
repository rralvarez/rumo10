# Modos de jogo — versão 0.6

Os modos usam o mesmo motor probabilístico, overall dos clubes, calendário, situações, XP, condição física e estatísticas. Não há bônus de evolução por escolher um modo mais rápido.

| Modo | Controle | Parada |
| --- | --- | --- |
| Normal | Treino, preparação, decisões em campo e fim de semana | Cada situação, como antes |
| Rápido | Uma abordagem por jogo | Resultado do jogo e próxima partida; resumo ao encerrar uma competição |
| Expresso | Uma estratégia por temporada | Resumo da temporada, antes das propostas de contrato |

## Como usar pelo site

Abra http://rumo10.local/carreira. Antes de uma partida, abra **Modo Normal · alterar ritmo**, selecione o modo e salve. Não é necessário criar uma carreira nova. Na criação do jogador também existe o campo **Ritmo da carreira**; roleta e escolha entre três clubes continuam iguais.

As quatro estratégias são protagonismo, coletivo, evolução e equilíbrio. Protagonismo prefere chute nos atacantes e desarme nas posições defensivas; coletivo prefere passes e entrosamento; evolução prioriza XP técnico; equilíbrio prefere recuperação e ações com maior chance de execução. Custos, riscos e energia baixa pesam na seleção automática, que se restringe às alternativas da situação atual. Não há garantia de gol, vitória, titularidade ou transferência.

No rápido, selecionar uma estratégia resolve treino, pré-jogo, todos os lances e recuperação daquele jogo. O resultado aparece antes das opções da partida seguinte, com gols, assistências, minutos e nota. Em **Ver jogos e destaques** ficam os eventos pessoais. O botão de continuação entre competições apenas navega no calendário, sem decisão extra de estratégia.

No expresso, uma estratégia percorre todos os jogos e fases das competições do jogador naquele ano, inclusive mata-matas. Eliminações e competições do restante do universo seguem as regras existentes. Se você mudar para expresso no meio do ano, ele simula somente o restante: partidas já disputadas permanecem iguais. A pergunta sobre o contrato vem primeiro; jogos e evolução ficam em **Ver minha temporada**.

Os contratos continuam manuais em todos os modos. A simulação nunca aceita propostas nem inicia sozinha o ano seguinte. Depois da resposta ao contrato, o modo permanece e o resumo anterior é limpo.

## Persistência e limites

Carreiras sem modo salvo usam Normal. A troca é permitida no início, antes do treino ou na janela de contratos; durante a partida é necessário concluir as decisões pendentes. Não existe troca no meio de um jogo.

Os comandos POST `/carreira/modo` e `/carreira/simular` usam autenticação, CSRF, versão da carreira, chave de idempotência e transação já existentes. Um envio repetido não sorteia novamente. Um lote inteiro salva uma única nova versão da carreira e sua auditoria com estratégia, quantidade de decisões, resultados e evolução. Nenhuma migração SQL é necessária; modo e relatório ficam no snapshot JSON `rumo10_universos`.

`PlayMode` percorre `Game::command` e as situações existentes. Portanto escalação, substituição, expulsão, lesão, fadiga, recuperação, XP e chances não são atalhados. As fases usam o calendário oficial simulado existente, com as limitações descritas em Docs/12. Há um limite de 3.000 passos internos por envio; excedê-lo aborta sem salvar. O lote é síncrono, e o navegador aguarda a resposta; não é uma tarefa em segundo plano.

As estratégias automáticas são heurísticas iniciais e ainda precisam de testes de equilíbrio ao longo de muitas carreiras. Elas não equivalem às decisões de um jogador experiente. Os clubes sem campanha histórica verificada continuam com overall provisório baseado na divisão e forma recente; a implementação dos modos não ampliou os dados históricos nem adicionou Europa.

## Validação local em 13/10/2026

- `tests/play-modes.php`: 523 verificações na execução registrada, seis posições, quatro estratégias, uma partida por comando rápido, temporadas expressas, troca sem reset, bloqueio de comandos inadequados, ausência em campo, entrada por roleta e continuidade do mata-mata.
- Temporadas expressas completas para São Caetano, Flamengo, Bolívar, River Plate, Nacional Montevideo e Olimpia, abrangendo calendários dos cinco países.
- `tests/http.php`: 54 verificações no IIS de rumo10.local e MySQL local, usando conta descartável removida ao final. Cadastro com modo, persistência, rápida e expressa, replay, contrato e próxima temporada, além dos testes anteriores.
- `tests/run.php`: 27.979 verificações e 30 temporadas do motor normal em seis posições.
- Testes de apresentação e roleta aprovados. PHP sem erros de sintaxe e `git diff --check` sem problemas.
- Prévia isolada em navegador, 390×844: quatro alternativas visíveis, sem excesso horizontal; resumo da temporada recolhido e escolha do contrato acessível.

Apache e produção não foram acessados nesta alteração. As rotas novas utilizam os mesmos front controllers de IIS e Apache já configurados.
