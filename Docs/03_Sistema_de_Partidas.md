# Sistema de partidas
Regra confirmada: minutos 0–90, estados agendada/pre_jogo/em_andamento/intervalo/finalizada e decisões que não podem ser puladas quando em campo. Universo separado por carreira.

Proposta provisória: titulares decidem aos 15, 35, 60, 70 e 80. Reserva entra aos 60. Energia muito baixa/risco alto pode impedir convocação. Desgaste, lesão e dois cartões retiram o jogador; minutos são saída−entrada, sem estatísticas de campo para não relacionado. Nota pondera desarmes mais fortemente para defensor, além de gols, assistências, passes e cartões.

Implementação atual: cada segmento sorteia gols coletivos; mando adiciona chance e tier fictício aumenta força da própria equipe. Chute bem resolvido tem segunda chance 60% de gol; passe certo tem 15% de assistência. Gol do jogador/assistência sempre aumenta placar próprio. NPCs sorteiam placar 0–3 em ambos os lados ao concluir a rodada. Ao minuto 45 há intervalo obrigatório. Gol adversário habilita S9, igualdade aos 80 habilita S7; S8 é reação à falta narrada na decisão. Calendário completo, estatísticas e log de lances em tabelas relacionais.

Pendências: posse e estratégias coletivas não implementadas; estatísticas de equipe/NPC ainda simplificadas. Não há escalação/elenco detalhado ou suspensão entre jogos. Cartões e lesão afetam apenas a participação imediata; riscos são contextuais recuperáveis.

Critérios de teste: placar >= gols do jogador, passes certos <= tentados, 90 minutos ao finalizar, todos os quatro jogos da rodada finalizados juntos, 14 jogos por equipe. Recarregar no intervalo e manter placar/minuto.