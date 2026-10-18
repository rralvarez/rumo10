# Entrega 58 — checkpoint de pesquisa e diagnóstico, expansão ainda não concluída

18/10/2026. Base local/remota conferida: `b21170ecacbdd47d3ec94c094ab9e6dde3fadc8d`, branch `codex/mundializacao-v07`. Este documento **não encerra a Entrega58 como lote esportivo**. Não foram implementadas novas federações nesta execução. Não confundir os testes abaixo com aceitação de campeonatos novos.

## Estado real e escopo preservado

Oito UFs continuam integradas no piloto anterior: AL,PI,RO,BA,ES,PE,RN,DF. Permanecem19 sem integração. Os motores, contratos esportivos, fontes fixadas e snapshots anteriores não foram modificados. DF conserva50 jogos-base, neutralidade, simultaneidade e pendência administrativa. A política RN não mudou. A pasta preexistente `Docs/levantamento-2026-10-15/` foi preservada.

Foram acrescentados um manifesto documental, um exportador de matriz e testes que reproduzem lacunas concretas do interpretador. **Nenhum deles registra candidaturas, altera o banco, modifica regras de jogo ou habilita flags.** Não houve DDL, migração, escrita no Hub, acesso à UOL ou produção.

## Lote de trabalho selecionado e impedimentos específicos

GO,PR,MA,AC constituem um conjunto de quatro UFs para desenvolvimento, **não quatro implementações concluídas**. GO/PR compartilham a necessidade de adversários intergrupos; GO/PR/MA precisam de ramos esportivos paralelos. AC expõe diferenças que impedem copiar o adaptador genérico de semifinal. A seleção poderá ser revista se documentos complementares demonstrarem melhor viabilidade de outro percurso.

| UF | Evidência primária localizada | O que impede usar o motor atual sem alteração |
|---|---|---|
| GO | [REC Goianão2026](https://www.fgf.esp.br/assets/uploads/file/Regulamento%20Goian%C3%A3o%202026.pdf), arts.5–24 | Primeira fase intergrupos; playoff5º–8º; permanência condicionada a diferença de pontos e possibilidade de dispensar a volta. Não equivale a uma liga seguida apenas da chave do título. |
| PR | [REC Paranaense2026](https://federacaopr.sfo3.digitaloceanspaces.com/wp-content/uploads/2023/09/19162139/REC-1a-Divisao-2026.pdf), arts.8–19 e anexoI | Grupos enfrentam o grupo oposto; descenso paralelo dos quatro não classificados; ranking separa vencedores/perdedores do descenso. RGCP e tabela precisam de conciliação própria. |
| MA | [REC definitivo Maranhense2026](https://www.futebolmaranhense.com.br/assets/uploads/17651550147.pdf?v=177021254186), art.4 e arts.11–14 | Oitavo desce direto; sexto/sétimo disputam permanência. Repasse nacional pode exigir opção do clube entre vagas. Falta contrato para esses ramos/decisões, além da conciliação de tabela e identidades. |
| AC | [REC Acreanão2026](https://www.ffac.com.br/competicoes/regulamento/43), arts.6–10, PDF pp.6–7 | Semifinal aplica vantagem em igualdade de pontos, antes de saldo; há disputa de terceiro lugar e desempate por partida extra. Esses caminhos não existem no adaptador corrente. |

Os PDFs públicos baixados ficam ignorados em `storage/qa/state58/`; hashes, tamanhos, edição, consulta e localizadores estão em `lote2/research.json`. `research.csv` é o índice legível. Não versionar HTML bruto, cookies ou documentos eleitorais como regulamentos esportivos.

GO: a permanência dos arts.19–20 usa campanha acumulada e limiar específico, inclusive após a ida; não modelar como pênaltis convencionais de mata-mata. PR: os jogos contra o outro grupo contam na classificação de cada grupo; filtrar somente confrontos entre seus integrantes produz tabela vazia/incorreta. MA: a [tabela básica](https://www.futebolmaranhense.com.br/conteudo/27/14) foi localizada no anexo `176374611975.pdf`, mas seus28 jogos ainda não foram conciliados com IDs. O REC remete omissões ao DCO; não supor desempate da permanência ou escolha de vaga ausentes do contrato.

AC: o PDF é composto por imagens. Foi gerado OCR privado e conferida visualmente a página6: art.7§1 realmente dá preferência à melhor campanha inicial quando há igualdade de pontos, e §2 prevê bronze. A [notícia da FFAC](https://ffac.com.br/noticia/ffac-divulga-a-tabela-detalhada-do-campeonato-acreano2026) anuncia33 jogos, enquanto28+4+bronze+final somam34 pelo REC. A página pública de partidas também foi localizada. Falta conciliar integralmente a tabela autorizada, eventuais adendos, mandos de bronze/final e procedimento de jogo extra. Não descartar o bronze para fazer a contagem coincidir com a notícia.

MT foi examinado como alternativa, mas não entrou no lote: o PDF oficial consultado apresenta art.6 com um descenso e art.18 com dois; essa divergência precisa de esclarecimento/adendo. A obtenção direta de alguns anexos de MT/PB falhou. O domínio `www` da PB apresentou certificado incompatível; não foi desativada a validação TLS. Isso é limitação de obtenção nesta execução, não prova de inexistência de regulamento. Notícias, referências de busca e catálogo não substituem sua leitura integral.

## Diagnósticos executáveis adicionados

`tests/state-lot2-preflight-v07.php` reproduz três impedimentos com fixtures **sintéticas e isoladas**, sem simular um estadual oficial:

1. A tabela completa de adversários intergrupos é recusada pelo validador atual, que exige liga completa de grupo único para fixtures explícitas.
2. No confronto ida3–0/volta0–1, cada equipe tem uma vitória. O `advantage` atual resolve pelo saldo; a redação acreana exige a equipe de melhor campanha inicial nessa igualdade de pontos. Usar o adaptador atual sem extensão mudaria o regulamento.
3. O roteamento atual rejeita seleção dos perdedores da semifinal para disputa de bronze.

São **três diagnósticos confirmados**, não três correções implementadas. Uma primeira execução do harness mostrou aviso por `phase_end` ausente na fixture; o inicializador foi corrigido e a repetição passou sem aviso. Nenhuma mudança de fórmula foi feita para fazer o teste passar.

`bin/audit-state-lot2.py` confere os quatro hashes quando recebe `--raw-dir`, valida a distinção pesquisa/implementação, lê a coorte-base sem somar saves e compara dez arquivos do motor com a base aprovada. Exporta `audit.json`, `research.csv` e `federations.csv`. Não constitui um certificador automático de regulamentos ou mecanismo de inscrição.

## Contagens e testes — limites explícitos

**Zero novas federações integradas; zero novos jogos de competição oficial; zero novos recibos de candidatura; zero inscrições nacionais; zero renovações nacionais.** Não foi executada nova coorte Game/PDO da Entrega58. Não existem métricas novas de consultas, memória, latência ou snapshot de competições adicionais.

A matriz27UF reproduz, com rótulo de origem, **somente** `df/sports.json` da Entrega57, carreira de fixture857500:14 candidatos estaduais,50 vagas estaduais pendentes e4 residuais separadas. Esses valores não são resultado novo da Entrega58 nem déficit universal de qualquer carreira. Não houve redução demonstrada nesta execução.

As24 suítes de `bin/test-narrative.py` passaram nesta revisão, com resultados em `lote2/regressao/`. Essa regressão protege a base existente; não prova execução de GO/PR/MA/AC. Não foram repetidas coortes volumosas MySQL, HTTP ou mobile sem alteração do runtime/interface. Testes de novas competições nos três modos, persistência, concorrência, crash/retry, rollback e interface **continuam pendentes e obrigatórios antes de homologar o lote**. Não alegar IIS, Apache ou UOL testados.

## Próxima implementação necessária — trabalho ainda não realizado

1. Conciliar participantes/IDs e tabelas oficiais do primeiro par, incluindo documentos complementares e critérios de vagas. Não criar IDs por semelhança de nome.
2. Introduzir uma versão opt-in do interpretador para adversários intergrupos, incluindo validação de cobertura e ranking por grupo com adversários externos. Preservar v1/v2/v3.
3. Implementar ramos de classificação/descenso com condições e provas de encerramento. A competição não pode homologar enquanto houver ramo obrigatório pendente.
4. Para AC, separar vantagem por pontos da vantagem após saldo, seleção de perdedores e ranking por disputa de posição. Concluir conferência dos artigos/tabela antes de ativar contrato.
5. Executar o primeiro conjunto completo no Game/Repository, três modos, provas por carreira e matriz de candidaturas; só depois integrar o conjunto seguinte e repetir a regressão pertinente.

Essas são pendências de desenvolvimento e conciliação, **não uma declaração de impossibilidade técnica**. O checkpoint não satisfaz o critério de ampliação estadual efetiva e a Entrega58 permanece aberta.

## Copa ES, nacional, Git e recuperação

Copa ES permanece inativa. Não foi localizada nem aplicada nesta execução retificação que concilie composição9→8 ou ano da vaga; a investigação anterior está preservada. Sem candidatura pelo segundo caminho capixaba.

D96, quatro residuais iniciais, C24 e CDB128/157 permanecem sujeitos a inscrições/obrigações completas e contratos futuros rotulados como simulação. Nada foi renovado parcialmente. Copas regionais/continentais ausentes continuam impedimentos; nenhuma foi dispensada silenciosamente.

Ambos os workflows de produção foram conferidos: push apenas em `main`, além de disparo manual. Este checkpoint só pode ser enviado à branch `codex/mundializacao-v07`; sem merge/deploy/flags públicas. Rollback consiste em remover/reverter os novos arquivos de diagnóstico/documentação; não há dados nem schema a restaurar. A v0.8 não foi iniciada.

Reprodução: PHP8.4 com `-n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring tests/state-lot2-preflight-v07.php`; Python `-X utf8 bin/audit-state-lot2.py`, opcionalmente `--raw-dir storage/qa/state58`. Para a regressão, usar `V06_EVIDENCE_DIR=Docs/mundializacao-v07/expansao-estadual/lote2/regressao`. O SHA remoto deste checkpoint será informado após a conferência do push; não representa uma entrega esportiva concluída.
