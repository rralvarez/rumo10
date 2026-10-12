# Carreira e temporadas
Regra confirmada: criação → handicap → treino → pré-jogo → partida → fim de semana → próxima rodada → resumo → ofertas → próxima temporada.

Proposta provisória: campeonato regional fictício, equipes de app/Rules.php, oito equipes por temporada, 56 partidas. Circle method gera sete rodadas e as mesmas sete invertendo mando. Desempate por pontos, vitórias, saldo, gols pró e nome. XP retido ao renovar contrato e idade aumenta em um ano.

Implementação atual: resumo de gols/assistências/minutos/jogos/nota. Roleta oferece sete equipes fictícias com peso 30 cada e, se nota >=6 e média técnica >=45, academia de nível seguinte com peso dependente de nota e apoio. A academia substitui uma equipe no próximo campeonato. Limite tier 4. Seleção única persistida; usuário pode aceitar ou permanecer. Histórico e transferências preservam temporada/clube e resumo final, enquanto os jogos antigos continuam relacionais no banco.

Pendências: elegibilidade por posição e reputação mais ampla; tier representa progresso fictício, não divisões CBF. Sem campeonatos profissionais reais.

Critérios de teste: conferir 14 rodadas sem repetição do mando, concluir temporada com seis posições e simular propostas aceitas/recusadas. Mudanças de carreira não afetam outras contas.