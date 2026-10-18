# Entrega 57 — expansão estadual: Bahia executável no piloto local

18/10/2026. Branch `codex/mundializacao-v07`. Base original `b31f14531d139107b2ff20b07a8afce501551b1f`; esta continuação parte do checkpoint parcial `af2e6e256824cfdd91368acb11a72c7ee1944eb9`, confirmado no remoto antes das alterações. O histórico desse checkpoint permanece no Git. A pasta não versionada `Docs/levantamento-2026-10-15/` foi preservada.

## Resultado real e limite

**Uma federação adicional incorporada: Bahia.** Baiano Série A 2026 com dez participantes identificados, tabela inicial, disputa completa, resultado reconstruído e três candidaturas à Série D no mesmo universo. AL/PI/RO permanecem nos adaptadores anteriores. A meta preferencial de seis a oito novas federações **não foi concluída**; ES/PE/RN/DF continuam pesquisados, sem contratos executáveis registrados. Não é conclusão da mundialização.

| Evidência independente | AL | PI | RO | BA | Total estadual | Vagas estaduais pendentes |
|---|---:|---:|---:|---:|---:|---:|
| Contratos, `ba/sports.json` | 2 | 2 | 1 | 3 | 8 | 56 |
| Repository/PDO, `ba/mysql-expresso.json` | 2 | 2 | 2 | 3 | 9 | 55 |

As linhas são saves distintos e **não são somadas**. A tabela completa das três execuções Game está em `ba/game-modes.json`; cada execução mantém seus próprios candidatos e totais. As 64 vagas estaduais usam quotas de referência de 2026 para a política futura simulada aprovada; não se apresentam como inscrição oficial de 2027. A matriz das 27 federações (`ba/federations.csv/json`) deriva exclusivamente da execução final `sports.json`: oito candidatos, 56 pendentes. RO pode fornecer um ou dois candidatos conforme os resultados; BA fornece três em ambas as coortes.

Na fixture PDO: 28 méritos nacionais + nove estaduais = 37 candidatos potenciais, déficit total 59 = **55 estaduais + quatro residuais**. Estas últimas não são inscritas automaticamente. O acréscimo da Bahia é de três candidatos; variações anteriores de RO não são progresso ou regressão de cobertura. **Zero inscrições nacionais e zero renovações aplicadas.**

## Bahia: contrato e regras

Fonte: [REC FBF 2026](https://fbf.org.br/documentos/noticia/202511032238reg_seriea_2026_atualizado%20em%20031125.pdf), arts. 3, 6, 8–10, 16–19; [tabela básica FBF de 03/11/2025](https://fbf.org.br/documentos/noticia/202511032317tab_basica_seriea_2026_031125.pdf), páginas 1–2. URLs, hashes dos PDFs e aliases estão no contrato `database/competition-references/br-state-expansion-2026-v1.json`. Datas de consulta: 18/10/2026.

- 45 confrontos de liga transcritos da tabela; semifinais 1×4/2×3 e final em partidas únicas: 48 jogos.
- Mando semifinal pela liga; mando final pela campanha liga+semifinal. Empate no mata-mata resolvido por pênaltis gravados.
- Campeão/vice; semifinalistas eliminados ordenados pela campanha acumulada; demais pela liga. Desempates do art. 6, incluindo confronto direto, cartões e sorteio persistido.
- Três candidaturas D2027 por classificação, excluindo divisões superiores e méritos nacionais já contemplados, com repasse pelo ranking.
- Descenso é mérito da edição, sem troca de memberships: o art. 19 condiciona a composição seguinte à realização da Série B. Sem divisão recíproca e calendário obrigatório completos, a continuidade permanece bloqueada.

A resolução do art. 6 usa confronto direto entre os clubes ainda empatados, por pontos e saldo, não comparações par a par não transitivas. Essa interpretação do texto está explícita no contrato; não se atribui à FBF uma tabela histórica produzida pelo simulador. A tabela básica fixa confrontos/mandos, enquanto datas do calendário são adaptação simulada com descanso de três dias. Alterações oficiais posteriores não são importadas automaticamente.

Alias Atlético → `Atlético-BA` e Bahia → `Bahia-BA`, preservando identidades existentes. Bahia de Feira, Barcelona, Galícia e Jequié têm referências locais de participante com fonte, sem editar Hub, criar rating oficial ou sobrescrever ratings existentes. BID, licenciamento, decisões administrativas e desistências não são presumidos.

## Código e persistência

- `StateExpansionTrial`: montagem CLI explícita, doze competições/até **2.018 partidas**, processamento NPC em lotes de oito. A exceção de orçamento exige marcador e contrato exato; teto genérico e lote antigo permanecem iguais.
- `ConfiguredStateCompetition`: critérios de confronto direto por pontos/saldo disponíveis apenas em v2. Reutiliza v1, sorteios persistidos, classificação e resolução existentes; não altera força, XP ou probabilidades.
- `StateQualification`: inclui a política BA e exige igualdade do contrato certificado, pin válido, participantes corretos e prova reconstruída. Recibo inclui carreira, edição, origem, digests, candidatos e rejeições. `StateResultProof` reutilizado do checkpoint.
- O teste PDO detectou conflito no replay de `FutureSerieDAdmission`: o MySQL reordena chaves de UFs no JSON. A auditoria agora ordena as UFs antes de formar listas/digest. Bug reproduzido na primeira execução, corrigido e repetido com sucesso; nenhuma inscrição ou resultado foi alterado para contornar a falha.
- Recibos são gerados explicitamente pelo serviço no piloto e persistidos pelo chamador na transação do Repository. Não há job global, backfill, escrita automática ao abrir página, migração de saves reais ou habilitação pública. Nenhum contrato de 2026 anterior foi regravado.

## Testes e evidências

Tudo em `Docs/mundializacao-v07/expansao-estadual/ba/`, salvo os testes de roteamento prévios, reexecutados e copiados para `ba/rechecks`.

| Teste | Resultado |
|---|---|
| `tests/state-expansion-v07.php` | 22 verificações: 48 jogos, mandos, ranking, três candidatos, versão, adulteração, conflito de recibo, orçamento, empate triplo e ordem das UFs. |
| `tests/state-expansion-runtime-v07.php` | 12.150 verificações, três modos, 2.018 jogos por fixture; Galícia joga na Bahia em Normal/Rápido; CRB e Copa do Brasil no Expresso. Reload JSON por comando. |
| `tests/state-expansion-mysql-v07.php --local-rollback` | 41 verificações após correção: uma edição Expresso com Repository/PDO, recarga, dois usuários descartáveis, crash/retry, replay, lock real, recibo BA persistido e rollback integral. |
| `tests/state-expansion-http-v07.php --local-fixtures` | 30 verificações, incluindo classificação da Bahia, controller, autenticação, CSRF e duplo POST; fixture removida. Servidor PHP loopback, não IIS. |
| `tests/state-expansion-mobile-v07.cjs` | 84 verificações; Chrome headless 320/390/1280 com HTML autenticado da fixture, teclado, classificação BA e ausência de overflow. Não dispositivo físico. |
| `bin/test-narrative.py` | 24 suítes passaram; 214 snapshots antigos + 800 novas cenas preservadas. |
| `tests/configured-state-v07.php` / `tests/state-routing-v07.php` | 55 / 22 verificações: pilotos anteriores e roteamento sintético preservados. |
| `tests/post-match-v06.php` / `achievements-v06.php` / `growth-v061.php` | 215 / 96 / 32 verificações; seis posições, três modos, catálogo 50/1.000 R e feedback/reconciliação. |

Conquistas ficam desativadas na fixture PDO descartável: essa coorte não prova concessão de R. Não houve cinco temporadas nacionais consecutivas; uma edição por modo e uma edição PDO independente, zero renovações. Não se declara cobertura de copas obrigatórias futuras, nem habilitação de mercado para calendário inexistente.

## Desempenho

PDO local do lote ampliado: **215 comandos, 7.528 consultas**, p50 **401,82 ms**, p95 **723,94 ms**, p99 **1.515,81 ms**, pico/fechamento **1.626,69 ms**, total **102.677,76 ms**. Snapshot final **1.763.854 bytes**, pico **2.150.179 bytes**, memória do processo **155.189.248 bytes**, incluindo harness.

Entrega 56: 205 comandos, 7.178 consultas, p95 856,58 ms, pico de snapshot 2.050.545 bytes. Acréscimos observados: 48 jogos, dez comandos, 350 consultas, 99.634 bytes de pico. São coortes com resultados/campanhas diferentes, sem controle de seed/carga: não provam ganho causal de latência. O volume não justifica migração estrutural nesta etapa. Não se mediu UOL/Apache/HTTPS.

Mantidos lotes NPC de oito; comparação de digests e reconstrução nacional compartilhadas no lote estadual. Otimização futura: evitar contratos estáticos duplicados e reconstruções repetidas mediante cache versionado validável, reservando mudança de persistência à v0.8. Nenhum worker permanente adicionado.

## Pendências e recuperação

Faltam cinco a sete federações para a meta preferencial: concluir tabelas/IDs, critérios especiais, provas e testes por estado. ES tem caminho parcial distinto da segunda vaga; PE tem byes/ranking e ausência de descenso em 2026; RN exclui playoff de campanhas específicas; DF pode exigir jogo extra federativo como desempate. Não substituir regras por sorteio nem anunciar esses estados como incorporados.

Permanecem 55 vagas estaduais, quatro residuais e obrigações futuras de copas/continuidade pendentes nesta coorte. Não há renovação parcial. Flags persistentes, Hub/rh_*, contas reais, produção, IIS/Apache, workflows e main intactos. Sem DDL ou dump. Todas as escritas MySQL foram de fixtures, revertidas ou removidas pelo harness.

Rollback não destrutivo: voltar os arquivos deste lote ao checkpoint `af2e6e2`; nenhum save real depende do novo contrato. Preservar ledgers e históricos; não restaurar dumps sobre progresso posterior. Os workflows foram conferidos: push desta branch não dispara publicação, limitada a main. SHA remoto será confirmado no encerramento e informado ao proprietário.

## Reprodução

PHP `C:/PHP/php-8.4.26/php.exe`, opções `-n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring -d memory_limit=768M`; acrescentar `-d extension=pdo_mysql` aos testes MySQL/HTTP. Executar os scripts acima na raiz. MySQL exige host local e banco `rumohub`; nunca produção. Node/Playwright: `tests/state-expansion-mobile-v07.cjs` depois do HTTP. `V06_EVIDENCE_DIR=Docs/mundializacao-v07/expansao-estadual/ba/regressao` direciona a regressão. `python -X utf8 bin/audit-state-expansion.py` reproduz matriz e inventário usando os PDFs privados já baixados e `ba/sports.json`. Evidências antigas regeneradas foram copiadas, preservando os relatórios históricos.

Commits deste lote: implementação `902b98b658629ff1c0e9e72f8fda7f6f25bfc638`; testes de integração `36f49714d996ed0584371640943f52880043281d`. Documentação/evidências em commit separado; SHA remoto final informado após o push, sem deploy.
