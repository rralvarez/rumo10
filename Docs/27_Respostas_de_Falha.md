# Respostas de falha — 14/10/2026

“Consultar o clube” agora explica que o jogador procurou a comunicação do clube, mas não definiu como esclarecer uma entrevista tirada de contexto. A consulta sobre um rumor tem outro desfecho: falta de orientação sobre como responder.

São 72 respostas próprias por situação e alternativa em `database/scene-failures.php`, abrangendo as 12 situações originais, duas situações de reserva e quatro situações de consulta/preparação. As demais alternativas sem resposta editorial usam explicações por tipo de ação e, nos passes, chutes, dribles e desarmes, por treino ou partida. As respostas contextuais já existentes são preservadas. Essa mudança elimina a frase padrão “A tentativa não sai como você esperava”; não torna todos os desfechos únicos.

Os textos não aplicam eventos adicionais. Probabilidades, custos, XP, penalidades e registros de resultados permanecem sob as regras existentes. Resultados já salvos na carreira não são reescritos; as novas mensagens aparecem nas próximas decisões.

Verificação local com catálogo independente do Hub, apenas nos processos CLI:

- `php tests/scene-failures.php`: 1.153 verificações das 856 alternativas, respostas específicas, preservação de mensagens anteriores e perda de confiança no resultado de uma consulta.
- `php tests/social-context.php`: contexto familiar e efeitos pessoais/equipe preservados.
- `php tests/scenes-strength.php`: 3.143 verificações de situações, alternativas, atributos e probabilidades.

PHP lint e `git diff --check` também passaram. Não houve migração de banco. Apache e produção não foram acessados para esta alteração.
