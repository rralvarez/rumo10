# Pacote de correções e crescimento — 15/10/2026

Fonte de requisitos: `Rumo10_Especificacao_Correcoes_Regras_e_Crescimento_v1_0.txt`.
Branches locais `codex/correcoes-crescimento-v1` nos dois projetos. Nenhum push/publicação autorizado.
Cabeças anteriores: jogo `45b0703`; Hub `d2407a6`. Catálogo, ratings e imagens fora do escopo.

## Etapa A — navegação e residência

CTA GET `/classificacao` → `/carreira` antes das tabelas, menu com Carreira e Carreiras distintos,
reserva dinâmica da altura do menu mobile (ResizeObserver, safe area, viewport), alvos e foco.
Criação distingue país da residência e país de estreia; municípios brasileiros são validados;
residência internacional aceita cidade/região declaradas e não inventa distâncias.
`city/uf` permanecem compatíveis (`EX` para residência internacional; detalhes no snapshot).
Novas carreiras têm `location_kind=current`; legadas mostram Cidade informada.
Sem migration nesta etapa. Testes: `tests/package-navigation.php`, `tests/presentation.php`.

## Próximas etapas em execução

B: placar, força relativa sem editar notas-base, progressão igual por modo, idade e mercado.
C: sala de troféus verificável, inclusive resumo aposentado.
D: direitos por jogo no Hub, cortesias, indicações e origem das vagas, migrations aditivas.
E: integração/concorrência/mobile/Monte Carlo; procedimentos de backup, publicação e rollback.
Identificar a conta de Raphael no painel e confirmar antes de qualquer concessão; nenhum nome
ou dado pessoal será inferido em scripts ou fixtures. Produção exige aprovação posterior.
