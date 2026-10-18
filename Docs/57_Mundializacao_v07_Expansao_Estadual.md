# Entrega 57 — checkpoint parcial da expansão estadual

18/10/2026. Branch `codex/mundializacao-v07`. Base local e remota conferida: `b31f14531d139107b2ff20b07a8afce501551b1f`. **O critério de aceite desta entrega ainda não foi atingido.** Não foram incorporadas seis a oito federações, nem reduzidas vagas por novos estaduais. Este checkpoint registra implementação e evidências aproveitáveis para continuar o lote.

## Estado esportivo real

| Item | Resultado deste checkpoint |
|---|---|
| Novas federações executáveis/certificadas | **0** |
| Regulamentos adicionais pesquisados e inventariados | BA, ES, PE, RN, DF |
| Novos candidatos comprovados por essas cinco federações | **0** |
| Novas inscrições na Série D | **0** |
| Renovações nacionais aplicadas | **0** |
| Federações anteriores | AL, PI e RO preservadas |

Na nova execução PDO do **piloto anterior**, o mesmo save produziu AL=2, PI=2, RO=1: cinco candidatos estaduais, **59 de 64 vagas estaduais ainda pendentes**. Somados aos 28 méritos nacionais, são 33 candidatos e déficit total 63, incluindo as quatro residuais separadas. Na Entrega 56, outra execução PDO produziu seis candidatos/58 pendentes; a diferença é resultado esportivo em RO, não remoção de cobertura. Não se somam candidatos entre execuções. O teste de contratos deste checkpoint produziu seis/58; é outro universo, separado do PDO. Nenhuma candidatura é inscrição efetiva.

## Implementação concluída: roteamento versionado

`ConfiguredStateCompetition` aceita explicitamente `configured-state-v2`, mantendo `configured-state-v1` e os contratos anteriores. **Nenhum contrato oficial novo foi registrado no runtime.** A extensão foi exercitada somente em fixtures sintéticas:

- Entrada por posição da classificação inicial, permitindo dois clubes entrarem diretamente na semifinal.
- Entrada por vencedor de uma chave anterior, preservando o cruzamento mesmo após uma zebra.
- Entrada por vencedores reordenados pela classificação inicial, quando o contrato exigir esse critério.
- Separação entre seleção dos adversários e ordenação do mando por classificação inicial/campanha.
- Histórico de vencedores por fase, reconstruído por `StateResultProof`; a ordem das listas é significativa, a ordem das chaves de objetos JSON não é.
- Rejeição de fase futura, origem em fase de liga como se fosse vencedor de mata-mata, índices inválidos, classificado duplicado ou não utilizado e uso do novo roteamento em contrato v1.

Não é suporte completo aos regulamentos pesquisados. Não foram implementados ranking final especial por competição, exclusão de fases da campanha, decisão federativa de jogo extra, mando neutro ou novos critérios de confronto direto. Os contratos v1 não receberam campos novos de histórico. Não há migração, conversão de saves ou backfill.

## Matriz de fontes e impedimentos

O inventário reproduzível está em `Docs/mundializacao-v07/expansao-estadual/sources.csv` e `sources.json`: URL primária, edição, localização, hash do PDF consultado, formato e pendências. PDFs ficam em `storage/qa/state57`, ignorado pelo Git. Hash comprova quais bytes foram consultados; não certifica inscrição, identidade ou resultado.

| UF | Fonte primária 2026 | Principal trabalho ainda necessário |
|---|---|---|
| BA | [REC FBF](https://fbf.org.br/documentos/noticia/202511032238reg_seriea_2026_atualizado%20em%20031125.pdf), arts. 3, 6, 8–18 | Conciliar IDs/tabela; contratar confronto direto múltiplo e campanha para mando/classificação final. A tabela básica também foi obtida da FBF. |
| ES | [REC FES](https://futebolcapixaba.com/site/wp-content/uploads/2025/12/Regulamento-Serie-A-2026.pdf), arts. 8–15 | Conciliar tabela/IDs; implementar confronto direto por pontos e limite de posições certificadas. A segunda vaga estadual não deve ser presumida como oriunda da liga. |
| PE | [REC FPF](https://www.fpf-pe.com.br/assets/uploads/176499150263.pdf?v=17696371365), art. 11 e Anexo A | Conciliar tabela/IDs; aplicar classificação inicial aos eliminados; manter ausência de descenso em 2026. O mecanismo de byes/cruzamento está apenas testado sinteticamente. |
| RN | [REC FNF](https://www.fnf.org.br/files/REC_Campeonato_Potiguar_2026.pdf), arts. 11–16 e Anexo I | Conciliar tabela/IDs; excluir playoff de campanhas específicas; concluir o contrato de indicação nacional e ranking. |
| DF | [REC FFDF](https://ffdf.com.br/assets/uploads/176245464195.pdf?v=17652869932), arts. 17 e 22 | O último desempate pode exigir jogo extra determinado pela federação: não substituir por sorteio. Faltam tabela/IDs, tratamento de local neutro e ranking de semifinalistas. |

As contagens 48/59/38/38/50 jogos no inventário são cálculos dos formatos pesquisados, **não torneios executados**. O eventual jogo extra do DF não está incluído nos 50. A vinculação de participantes a IDs imutáveis, alterações posteriores de tabela e elegibilidade ainda precisa ser concluída antes de inserir contratos certificáveis. O documento encontrado pelo link geral “Regulamento” da FFAC era eleitoral: foi excluído como prova esportiva. Nenhum campeonato foi criado com ele.

Essas pendências são trabalho de implementação/validação, não declaração de inexistência de documentos oficiais. SP, RJ, MG, PR, RS, SC e CE não são considerados certificados por esta pesquisa. Não foi repetida a auditoria mundial nem modificada a matriz anterior das 27 federações para fingir cobertura nova.

## Testes efetivamente executados

| Suíte | Resultado / limite |
|---|---|
| `tests/state-routing-v07.php` | 22 verificações; sintéticas, incluindo reconstrução e reordenação de chaves JSON. Sem Game/PDO para o novo contrato v2. |
| `bin/test-narrative.py` | 24 suítes passaram; 214 snapshots antigos + 800 cenas novas preservados. Evidências próprias em `expansao-estadual/regressao`. |
| `tests/configured-state-v07.php` | 55 verificações; prova/candidaturas do piloto AL/PI/RO, sem novos estados. |
| `tests/configured-state-runtime-v07.php` | 11.850 verificações; três modos, uma edição de 1.970 partidas por fixture. Não são temporadas consecutivas da mesma carreira. |
| `tests/configured-state-mysql-v07.php --local-rollback` | 38 verificações; uma edição Expresso pelo Repository/PDO com reload, crash/retry, replay, isolamento, lock real e rollback integral. Conquistas desativadas nessa fixture: não prova concessão de R. |
| `tests/configured-state-http-v07.php --local-fixtures` | 29 verificações; servidor PHP loopback, conta descartável removida. Não IIS/Apache/produção. |
| `tests/configured-state-mobile-v07.cjs` | 72 verificações; Chrome headless 320/390/1280, HTML autenticado capturado da fixture, teclado e ausência de overflow. Capturas em `expansao-estadual/mobile`; não dispositivo real. |
| `tests/post-match-v06.php` | 215 verificações; seis posições e três modos, sem banco. |
| `tests/achievements-v06.php` | 96 verificações; catálogo de 50/1.000 R; não backfill ou concessão em conta real. |
| `tests/growth-v061.php` | 32 verificações puras de reconciliação/feedback. |

Os resultados reexecutados dos testes antigos foram copiados para `expansao-estadual/rechecks`, preservando os relatórios históricos originais. O CSV/JSON de pesquisa não é contrato de runtime e não gera candidaturas. O gzip da coorte foi comparado ao JSON antes de remover apenas a cópia bruta regenerável.

## Desempenho e alcance da comparação

Nova execução PDO do piloto anterior: 207 comandos, 7.248 consultas, p50 **329,16 ms**, p95 **698,75 ms**, p99 **951,83 ms**, pior comando **1.283,63 ms**; fechamento **527,75 ms**; total **81.929,05 ms**. Snapshot final **1.591.205 bytes**, pico **2.112.176 bytes**; pico de processo **142.606.336 bytes**, incluindo o harness.

Referência da Entrega 56: 205 comandos/7.178 consultas, p95 856,58 ms, snapshot final 1.709.041/pico 2.050.545 bytes. As execuções não são controladas por seed nem carga e os resultados esportivos variam: **não se conclui melhoria ou regressão causal**. Não existe medição da ampliação a seis/oito federações, pois ela não foi integrada. Nenhum worker ou reestruturação de persistência foi introduzido.

## Segurança, recuperação e próxima intervenção

Sem DDL, schema, alteração de flags persistentes, saves reais, Hub/rh_*, produção, FTP, IIS, Apache ou workflows. Flags de fixture são definidas apenas nos processos dos testes, sem habilitar novas carreiras no site. Os workflows existentes publicam em push de `main`, não desta branch.

Rollback: restaurar os dois arquivos de motor/prova da base anterior; nenhum dado real depende da v2 neste checkpoint. Não apagar históricos nem restaurar dump sobre progresso posterior. Contratos oficiais de 2026 e planos futuros C24/D96/Copa128 continuam intactos. Não há renovação parcial.

Próxima intervenção necessária: concluir um contrato real v2 com tabela e IDs conciliados, classificação final e elegibilidade exatas; exercitá-lo em Game/Repository nos três modos e produzir recibo estadual; depois ampliar o lote. ES/PE são candidatos de continuidade, sem ativação automática. A Série D futura continua bloqueada e as quatro vagas residuais não são preenchidas por catálogo, overall ou sorteio. **Esta entrega permanece parcial; mundialização e meta de seis/oito federações não concluídas.**

## Reprodução

PHP 8.4.26 local, `-n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring -d memory_limit=768M`; acrescentar `-d extension=pdo_mysql` somente aos testes MySQL/HTTP. Executar os comandos de testes acima na raiz. O teste MySQL exige banco local `rumohub`, nunca produção; preserva fixture apenas dentro da transação revertida. `python -X utf8 bin/audit-state-expansion.py` reproduz hashes/matriz com os PDFs privados previamente baixados nas URLs listadas. `V06_EVIDENCE_DIR` direciona a regressão à pasta própria deste checkpoint.
