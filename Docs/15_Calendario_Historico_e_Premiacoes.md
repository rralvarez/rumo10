# Análise: calendário, duas janelas, histórico e premiações

Consulta em 13/10/2026. Este documento distingue a análise do motor futuro da alteração já implementada no histórico.

## Problemas encontrados no jogo

`Competition::completeWorld` finaliza todo o universo ao encerrar o calendário do personagem. `Game::transfer` reinicia os campeonatos e aumenta idade/temporada ao responder ao contrato. As partidas têm rodada, mas não data. Existe uma única janela ao final. Isso funciona como ciclo abstrato de RPG; não representa o calendário sincronizado Brasil–Europa.

Também são jogados estaduais e Brasileirão sequencialmente. Em 2026 a Série A brasileira começa em janeiro e se sobrepõe aos estaduais. A transição para Europa hoje inicia um ano europeu inteiro, mesmo quando deveria chegar a um campeonato já em andamento. Não há mecanismo para substituir o clube do personagem no meio do calendário conservando os resultados e pontuações anteriores.

**Esses problemas de calendário não foram corrigidos nesta alteração.** Foi entregue o histórico acumulado descrito abaixo; a reorganização temporal exige mudança coordenada de fixtures, fases, transferências, progressão de idade, modos rápido/expresso, armazenamento e auditoria.

## Evidência e consequências

| Exemplo | Competição | Janelas verificadas |
| --- | --- | --- |
| Brasil 2026 | Ano civil; Série A começa em 28/01, calendário até dezembro; estaduais sobrepostos | 05/01–03/03 e 20/07–11/09 |
| Inglaterra 2026/27 | Abertura da Premier League em 21/08/2026; encerramento 30/05/2027 | 15/06–01/09/2026 e 01/01–01/02/2027 |

As janelas brasileiras são do calendário profissional masculino da CBF; as inglesas foram consultadas na EFL. Não generalizar as datas inglesas para os outros nove países. Associações definem suas próprias janelas e podem alterar datas entre edições. Ainda é necessária a conferência individual das janelas dos demais mercados antes de implementá-las.

Os mercados sul-americanos cadastrados usam edição anual, com formatos diferentes: Apertura/Clausura na Argentina e Paraguai; Apertura/Intermedio/Clausura e definição anual no Uruguai. Torneio curto e janela de registro não são o mesmo evento. As duas principais divisões dos dez países europeus cadastrados usam 2026/27. Os meses exatos de cada liga devem ser metadados próprios.

Exemplos a testar no novo motor:

1. Brasileiro sai em julho de 2026 para Portugal: seus números de janeiro–julho ficam no antigo clube; chega à preparação/início de 2026/27, sem reiniciar o campeonato brasileiro.
2. Brasileiro sai em janeiro de 2027 para Inglaterra: chega no meio de 2026/27, com tabela e resultados europeus já existentes. Não ganha automaticamente uma temporada inteira nem um aniversário.
3. Atleta sai da Europa em julho para Brasil: entra no meio de 2027, preservando classificação e partidas já jogadas no Brasil.
4. A janela do destino está fechada: proposta pode ser negociada, mas ingresso/registro fica agendado para abertura, sujeito à validade da proposta/contrato. Não permitir que um clique antecipado o escale imediatamente.

Para a regra comum de registro, verificar a associação do destino e suas exceções; não exigir automaticamente que a janela de origem também esteja aberta. Oferecer contrato, aceitar, efetivar transferência e ficar elegível para jogar são estados diferentes. Regras completas de visto, licença, inscrição, atletas livres e limite de clubes por temporada não devem ser presumidas sem modelagem.

## Modelo necessário

- Uma data global de simulação para toda a carreira e partidas com data e identificador estável.
- Competições identificadas por divisão + edição (`br-a@2026`, `en-1@2026-27`), com início/fim e fases. Data global, temporada da competição e capítulo da carreira são conceitos separados.
- Avançar até a próxima partida/decisão do personagem; simular os outros jogos vencidos até aquela data. Não terminar o mundo todo cada vez que o personagem encerra um campeonato curto.
- Janelas por associação/edição, com fontes e data de conferência. Outras edições futuras podem usar calendário projetado do jogo, claramente identificado, até haver dados reais.
- Registro de contrato/passagem e transferência com clube anterior, destino, data de proposta, aceite e efetivação. Mudança dentro do ano não fecha automaticamente todas as competições nem muda a idade.
- Estatísticas por jogador, clube e edição da competição, com contribuições imutáveis por partida. Trocar de clube nunca reatribui gols anteriores ao clube novo.
- No expresso, uma estratégia continua resolvendo a temporada, mas a simulação pausa nas oportunidades de transferência das duas janelas. Aceitar/recusar contrato é decisão adicional de mercado, como já acordado. A estratégia pode continuar sem uma nova escolha automática ao retomar. No rápido, manter uma abordagem por jogo e interromper para contratos entre jogos.
- Migrar partidas antigas sem inventar datas/autores de gol. É preciso escolher uma política de conversão coerente para as carreiras existentes, mantendo histórico e marcando dados antigos como legado. Não executar um reset silencioso.

Recomendo implementar essa base antes de premiações individuais competitivas. Os testes devem impedir retorno no tempo, partidas duplicadas, dupla inscrição indevida, calendário reiniciado na mudança de clube, XP/estatísticas duplicados em replay e idade aumentada por simples assinatura.

## Histórico entregue agora

Em `/jogador`, a seção **Carreira inteira** mostra jogos, gols, assistências e quantidade de clubes. **Por clube e temporada** agrega as passagens repetidas pelo mesmo clube e mantém linhas por temporada. Soma o histórico encerrado e a temporada atual exatamente uma vez; não mistura carreiras arquivadas, que representam personagens diferentes.

Usa os dados já salvos sem alterar partidas, resultados ou atributos. Ao mudar de clube no mecanismo atual, os totais anteriores continuam intactos. Temporadas futuras também passam a guardar os resultados das competições no resumo histórico, para apoiar uma futura coleção de títulos. Isso não concede troféus pessoais automaticamente.

O histórico existente tem totais por temporada/clube, suficientes para jogos/gols/assistências; não possui detalhes retrospectivos de todas as competições nem datas reais. Não foram inventados. No futuro, com transferência no meio da temporada, o novo registro por passagem/partida substituirá a suposição atual de um clube por temporada.

## CPU/NPCs e premiações

**Sim, são necessários jogadores persistentes das equipes controladas pela CPU para organizar rankings justos de artilharia e assistências.** Hoje os gols adversários e coletivos são placares sem autor, e não existem minutos/notas individuais equivalentes para os rivais. Não é possível afirmar que o personagem foi artilheiro ou melhor jogador do campeonato comparando somente os dados dele.

Recomendo plantéis fictícios de 20–24 atletas por clube real, com IDs estáveis, posição (incluindo goleiros), idade, atributos derivados do overall e evolução. Não é necessário importar jogadores reais para obter coerência. Eles permanecem entre temporadas, podem mudar de clube e aparecem nos rankings. A estrutura deve evitar carregar dezenas de milhares de jogadores completos no snapshot de cada POST: persistência separada/compacta, carregando apenas o necessário, respeitando o limite de memória local.

Cada gol deve registrar competição/edição, partida, minuto, equipe beneficiada, autor, assistente opcional e tipo. Gols contra ficam associados ao responsável sem entrar como gol de artilharia dele. Pênaltis e gols sem passe final não recebem assistência artificial. O autor e o assistente precisam estar em campo e não podem ser o mesmo jogador. Os eventos do personagem e os da CPU usam a mesma contagem, e os gols atribuídos precisam coincidir exatamente com o placar. Não distribuir autores por sorteio independente depois do resultado nem rerrolar ao abrir rankings.

| Reconhecimento | Dados necessários | Política recomendada |
| --- | --- | --- |
| Campeão da competição | Resultado final do torneio | Troféu do clube por edição; contribuição do personagem registrada separadamente |
| Acesso | Resultado de acesso | Marco da carreira, distinguido de título de campeão |
| Artilheiro | Gols de todos os atletas da edição | Aplicar empate/desempate específico da competição; gols contra e pênaltis de desempate não contam |
| Líder de assistências | Eventos de assistência de todos | Regra comum transparente; troféu oficial apenas onde esse prêmio existir |
| Destaque da competição | Notas, minutos, posição e desempenho de todos | Prêmio do jogo com fórmula pública e participação mínima; equilibrar posições |
| Revelação | Idade na data de referência, minutos e desempenho | Regras por prêmio; não conceder automaticamente a todo jovem que sobe atributos |
| Seleção da competição | Comparação por posição, minutos e notas | Precisa de defensores e goleiros avaliados de forma justa |

Na Premier League, Golden Boot é compartilhada entre artilheiros empatados; Playmaker distingue quem deu mais assistências. Não importar esses nomes e regras para todas as ligas. Melhor jogador/revelação reais frequentemente envolvem votação ou avaliação institucional; uma fórmula própria do RPG deve ser apresentada como prêmio do jogo, sem alegar reprodução oficial.

Os prêmios são fechados por edição da competição, não por clube nem por ano pessoal. Se o atleta trocar entre dois clubes da mesma competição/edição, seus gols contam juntos para o ranking e separados por clube no histórico. Se mudar de Brasil 2026 para Europa 2026/27, são rankings distintos; o total da carreira soma ambos. Critérios de medalha/participação devem ser definidos por competição: inscrição no elenco não é automaticamente equivalente a conquistar uma medalha oficial.

## Ordem recomendada

1. Histórico por clube e total da carreira — entregue nesta alteração.
2. Relógio global, temporadas independentes e duas janelas — próximo trabalho estrutural.
3. Plantéis persistentes, escalações, autoria de gols/assistências e minutos da CPU.
4. Títulos coletivos e registros de acesso, depois artilharia e assistências, depois prêmios subjetivos equilibrados por posição.

## Fontes

- [CBF: calendário masculino 2026, com duas janelas](https://www.cbf.com.br/a-cbf/arbitragem/aplicacao-regra-diretrizes-fifa/livro-de-regras-2023-2024-portugues-brasileiro/cbf-oficializa-datas-do-calendario-do-futebol-do-brasil-profissional-masculino-de-2026).
- [CBF: calendário oficial em PDF](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/Calenda%CC%81rio%20do%20Futebol%20do%20Brasil%202026%20-%20Masculino%20Profissional.pdf).
- [Premier League: calendário atualizado 2026/27](https://www.premierleague.com/en/news/4675508/premier-league-fixture-schedulereleased-for-season-202627).
- [EFL: duas janelas 2026/27](https://www.efl.com/supporters/faqs/transfer-window-faqs/).
- [FIFA: períodos definidos por associação](https://ipt.fifa.com/transfer-system/player-transfers).
- [AUF: Apertura, Intermedio e Clausura 2026](https://www.auf.org.uy/noticia-campeonato/3/pag/1/).
- [LPF/AFA: regulamento primeira divisão 2026](https://www.ligaprofesional.ar/wp-content/uploads/2026/01/Reglamento-Torneos-LPF-Primera-2026-1.pdf).
- [Premier League: artilharia e empates](https://www.premierleague.com/en/news/1206108/premier-league-golden-boot-award-winners), [Playmaker](https://www.premierleague.com/en/news/4314803/salah-sweeps-202425-player-of-the-season-golden-boot-and-playmaker-awards).

Validação do histórico: `tests/career-stats.php`, nove verificações com dados sintéticos, retorno ao mesmo clube, preservação após contrato, jogos de outros clubes excluídos, zero antes do primeiro clube, escaping e consulta sem mutação. Calendário global, novas janelas e prêmios acima são proposta técnica, não implementação ou testes realizados.

Também passaram apresentação, lint PHP e 56 verificações HTTP no IIS/MySQL local, incluindo totalizador após contrato e estado intacto ao consultar a ficha.
