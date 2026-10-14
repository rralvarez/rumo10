# Contexto das escolhas sociais — 14/10/2026

O preset de apoio aplicava a mesma mensagem e entrosamento com o elenco a encontros com família, amigos e comunidade. A escolha “Passar o tempo com alguém próximo” não definia a pessoa, mas seu resultado mencionava um grupo, sem contexto.

Agora essa alternativa identifica um familiar; sucesso e falha mencionam a família. Ela melhora moral/confiança quando dá certo, sem aumentar o entrosamento com o clube. Foram contextualizadas 42 mensagens da folga e corrigidos os efeitos de 26 escolhas pessoais/comunitárias. Nas escolhas de apoio a companheiros, o entrosamento permanece e o texto cita a ação escolhida.

Alternativas do JSON podem declarar `success_text`, `failure_text` e `effects` específicos, com o preset como fallback. Isso evita presumir que toda ação social é uma conversa ou envolve companheiros do clube. IDs, seleção das situações, posição das alternativas e cálculo das probabilidades permanecem iguais. Mensagens já gravadas são preservadas; as próximas decisões usam os textos e efeitos atualizados. Os TSV continuam sincronizados com os enunciados e nomes das escolhas; resultados/efeitos específicos ficam no JSON.

Verificado com `tests/social-context.php`: uma decisão completa com o familiar preserva o entrosamento e produz mensagem coerente, tanto para sucesso quanto para falha; escolhas com amigos/família não afetam o elenco; apoio a companheiro conserva sua mecânica. `tests/scenes-strength.php`: 3.143 verificações aprovadas, 214 situações e 856 alternativas. Sintaxe PHP e `git diff --check` aprovados. Não houve reexecução da suíte HTTP nesta alteração.
