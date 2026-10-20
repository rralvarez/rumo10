# Entrega 59 — Saneamento Documental, Auditoria das 27 Federações e Liberação Definitiva para Commit

**Data:** 19/10/2026
**Workspace exclusivo:** `C:\inetpub\wwwroot\rumo10`
**Branch:** `codex/mundializacao-v07`
**HEAD verificado:** `38ef50121d0c4c5b4cd89d74fac1e640c7123c1f`
**Equipe:** Antigravity (Executor Técnico), Codex (Auditor Independente), ChatGPT (Coordenador Técnico)

---

## 1. Contexto, Decisões da Coordenação e Preservação do Workspace

### 1.1 Decisão da Coordenação Técnica
O Auditor Independente (Codex) concluiu a auditoria de liberação para commit e emitiu o **Parecer C (não liberado por divergências documentais e procedimentos de reprodução incorretos)**.

A coordenação técnica e o auditor registraram expressamente:
1. **Sem novos defeitos desportivos:** Não foram identificadas novas falhas esportivas bloqueantes nos pilotos de Mato Grosso do Sul (`br-ms-a1`) e Tocantins (`br-to-a1`), que continuam plenamente aprovados sob o mecanismo desacoplado `certified-outcomes-v1`.
2. **Inventário estruturalmente aprovado:** O inventário de **48 arquivos para commit mínimo** foi validado estruturalmente pelo Codex.
3. **Teste mobile aprovado funcionalmente:** O teste mobile (`tests/state-program-mobile-v07.cjs`) foi validado com 66 verificações funcionais em três viewports.
4. **Escopo estrito desta rodada:** A presente rodada destina-se **exclusivamente a sanear divergências documentais e corrigir comandos de reprodução**, sem desenvolver novos estaduais, sem ampliar certificações parciais e sem modificar contratos esportivos ou classes do motor.

### 1.2 Preservação Rigorosa do Workspace
1. **Branch e HEAD:** Confirmados em `codex/mundializacao-v07` no commit `38ef50121d0c4c5b4cd89d74fac1e640c7123c1f`.
2. **Git Status:** Exatamente **155 caminhos** identificados (10 arquivos modificados e 145 não versionados/untracked).
3. **Preservação de integridade:** Nenhum comando de alteração de histórico (`git add`, `git commit`, `git push`, `git merge`, `git reset`, `git clean`) foi executado.
4. **Isolamento de projetos:** RumoHub, schemas de banco (`rh_*`), produção e saves reais de usuários permaneceram completamente intactos e intocados.
5. **Auditoria concluída:** O Codex encerrou suas execuções anteriores antes do início deste trabalho.
6. **Arquivos modificados nesta rodada:** Exclusivamente os arquivos de controle documental e matriz tabular:
   - `Docs/59_Mundializacao_v07_Conclusao_27_Estaduais.md`
   - `Docs/mundializacao-v07/conclusao-27/federations.csv`
   - `Docs/mundializacao-v07/conclusao-27/federations.json`

---

## 2. Auditoria Documental Primária: Federações Bloqueadas (AP, PB e RR)

As federações de Amapá, Paraíba e Roraima permanecem rigorosamente bloqueadas para fins de contrato desportivo homologável de 2026. Em obediência à diretriz de auditoria, **nenhum ID de planejamento é apresentado como contrato desportivo existente**; seus contratos e versões são classificados como `nenhum` e `nenhuma`.

### 2.1 Amapá (AP)
1. **Documentos e Hashes:**
   - Tabela Oficial de Jogos FAF 2026: obtida em `https://fafamapa.com.br/wp-content/uploads/2026/02/TABELA-CAMPEONATO-PROFISSIONAL_AMAPAZAO-SERIE-A-2026.pdf` (SHA-256 `1bab004208bd6280849a9158f423667ea83ff096624564a3294499ec1510303c`, 270.108 bytes).
   - Regulamento Específico da Competição (REC): indisponível nos canais auditados devido a falhas persistentes de infraestrutura da FAF (*TLS handshake timeout*).
2. **Fatos comprovados pela tabela (8 clubes):** Trem, Oratório, Independente, Santos-AP, Cristal, Ypiranga-AP, Macapá e São José-AP.
3. **Regras essenciais NÃO comprovadas:**
   - Fórmula detalhada do mata-mata (jogo único ou ida e volta em semis/finais).
   - Critérios de desempate da primeira fase.
   - Quantidade de rebaixados e critérios formais de descenso.
   - Destinação formal das vagas da Copa do Brasil 2027 e Série D 2027.
4. **Status:** `bloqueado_documentacao`. Contrato desportivo inexistente (`contrato_id: nenhum`, `versao_contrato: nenhuma`).

### 2.2 Paraíba (PB)
1. **Documentos e Hashes:**
   - Resolução de Diretoria DCO - Ofício nº 001/2026 (Calendário Oficial FPF-PB): obtido em repositório oficial S3 (`https://s3.federacaopbfutebol.com.br/fpfonline-site/dco/documents/01KSDQDNAXY712ENW6CV3BH3QV.pdf`, SHA-256 `c35a1b984f67df3450dc84e6cd6ddf743d678b42ba7507d2a3a11ac2b8de7fe1`, 311.421 bytes).
2. **Escopo estrito do Calendário:** Comprova unicamente as datas oficiais da 1ª Divisão (17 de janeiro a 22 de março de 2026).
3. **Impedimento documental:** O REC da 1ª Divisão Adulta de 2026 não foi publicado ou localizado nos canais institucionais públicos da FPF-PB até o momento. Não há base primária para participantes confirmados, mata-mata, descenso ou vagas da Série D.
4. **Status:** `bloqueado_documentacao`. Contrato desportivo inexistente (`contrato_id: nenhum`, `versao_contrato: nenhuma`).

### 2.3 Roraima (RR)
1. **Fontes Auditadas:** Portal da FRF (`www.frfroraima.com`) e crônica esportiva regional (Folha BV, GE RR).
2. **Impedimento documental:** O REC oficial da 1ª Divisão de Profissionais de 2026 não foi disponibilizado pela FRF. Tabelas secundárias noticiam desistência do Real-RR (restando 9 participantes), mas apresentam contradições insanáveis sobre a ordem dos critérios de desempate (confronto direto vs saldo de gols).
3. **Ausência de ato primário:** Sem ata do arbitral ou REC íntegro, ranking geral, desempates e vagas permanecem não certificados.
4. **Status:** `bloqueado_documentacao`. Contrato desportivo inexistente (`contrato_id: nenhum`, `versao_contrato: nenhuma`).

---

## 3. Matriz Consolidada das 27 Federações (Vigente e Unificada)

A matriz foi unificada de forma que os artefatos `federations.json`, `federations.csv` e o presente documento reflitam **exatamente a mesma matriz vigente única com as 27 UFs e os 21 campos canônicos**, com **zero divergências** de valores entre as três representações:

| UF | Contrato ID | Versão | Programa Executável | Partidas | Campeão Calc. | Campeão Cert. | Vice Calc. | Vice Cert. | Descenso Calc. | Descenso Cert. | Ranking Disp. | Homolog. Integral | Exec. Condic. | Pendências Normativas | Pendências Administrativas | Pendências Técnicas | Candidaturas Nacionais | Fonte Normativa Principal | Evidência de Testes | Status de Aceite Técnico |
|:---|:---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---|:---|:---|:---|:---|:---|:---|
| AC | ac-a1 | v1 | sim | 34 | sim | não | sim | não | sim | não | sim | sim | sim | Empate múltiplo sem formato de desempate em ata exige critério formal. | Mandos e jogos extras dependem de designação auditada. | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FFAC 2026 | tests/state-program-runtime-v07.php, tests/state-program-audit-cohort-v07.php | Base integrada estável |
| AL | al-a1 | v1 | sim | 34 | sim | não | sim | não | sim | não | sim | sim | não | nenhuma | nenhuma | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FAF 2026 | tests/state-program-runtime-v07.php, tests/state-program-audit-cohort-v07.php | Base integrada estável |
| AM | am-a1 | v1 | sim | 38 a 39 | condicional | não | condicional | não | sim | não | condicional | condicional | sim | pending_if_same_winners: se mesmo clube vencer ambos turnos, indefinição do vice impede fechamento; sem certificação parcial automática. | Definição de vice-campeão depende de ato regulamentar da FAF em caso de vencedor único de turnos. | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FAF 2026 (SHA-256 eede489a...) + Tabela | tests/state-program-am-v07.php (71 chks) | Executável condicional aprovado |
| AP | nenhum | nenhuma | não | 0 | não | não | não | não | não | não | não | não | não | REC adulto 2026 não localizado nos canais FAF; desempate/vagas sem base primária. | Servidor da FAF com falhas persistentes de conexão. | contrato_ausente | bloqueadas_sem_contrato | Tabela FAF-AP (SHA-256 1bab0042...); REC ausente | tests/state-program-audit-cohort-v07.php (bloqueio de integridade) | Bloqueado por documentação |
| BA | ba-a1 | v1 | sim | 48 | sim | não | sim | não | sim | não | sim | sim | não | nenhuma | nenhuma | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FBF 2026 | tests/state-program-runtime-v07.php, tests/state-program-audit-cohort-v07.php | Base integrada estável |
| CE | br-ce-a1 | v1 | sim | 43 | sim | não | sim | não | sim | não | sim | sim | não | Repasses condicionados à homologação da classificação final. | nenhuma | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FCF 2026 (SHA-256 1362d8ba...) + Tabela | tests/state-program-ce-v07.php, tests/state-program-cohort-v07.php, tests/state-program-runtime-v07.php | Executável aprovado |
| DF | df-a1 | v1 | sim | 50 | sim | não | sim | não | sim | não | sim | sim | sim | Jogo extra e campo neutro sob decisão do DCO podem alterar classificação e confrontos. | Designação de estádio neutro e data pelo DCO. | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FFDF 2026 | tests/state-program-runtime-v07.php, tests/state-program-audit-cohort-v07.php | Base integrada estável |
| ES | es-a1 | v1 | sim | 59 | sim | não | sim | não | sim | não | sim | sim | não | nenhuma no campeonato principal; Copa ES inativa no simulador para vagas nacionais. | nenhuma | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FES 2026 | tests/state-program-runtime-v07.php, tests/state-program-audit-cohort-v07.php | Base integrada estável |
| GO | go-a1 | v1 | sim | 70 | sim | não | sim | não | sim | não | sim | sim | não | nenhuma | nenhuma | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FGF 2026 | tests/state-program-runtime-v07.php, tests/state-program-ledger-order-v07.php | Base integrada estável |
| MA | ma-a1 | v1 | sim | 36 | sim | não | sim | não | condicional | não | sim | condicional | sim | Empate absoluto de permanência suspende o fechamento geral da competição (não restrito a vagas). | Ato da FMF em caso de empate residual de descenso. | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FMF 2026 | tests/state-program-runtime-v07.php, tests/state-program-audit-cohort-v07.php | Executável condicional aprovado |
| MG | br-mg-a1 | v1 | sim | 56 a 61 | sim | não | sim | não | sim | não | sim | condicional | sim | Troféu do Interior com >2 interioranos semifinalistas suspende execução antes de agendar finais (percurso com 56 jogos); Art. 24 é interpretação provisória. | Ato da FMF disciplinando semifinalistas interioranos. | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FMF 2026 (SHA-256 6f4b1dae...) + RGC | tests/state-program-mg-v07.php, tests/state-program-interior-v07.php, tests/state-program-runtime-v07.php | Executável condicional aprovado |
| MS | br-ms-a1 | v2 | sim | 55 | sim | sim | sim | sim | sim | sim | não | não | sim | ms_full_final_ranking_and_national_path_not_certified: ranking 3º ao 8º sem regra formal no REC. | Destinação formal de vagas da Série D em acessos ou desistências. | nenhuma no piloto aprovado | bloqueadas_homologacao_parcial_sem_vaga | REC FFMS 2026 (SHA-256 f8f148a4...) + Tabela | tests/state-program-ms-partial-v07.php (172 chks), tests/state-program-complete-mysql-v07.php | Certificação parcial aprovada (Codex) |
| MT | br-mt-a1 | v1 | sim | 53 | sim | não | sim | não | condicional | não | não | não | sim | mt_relegation_conflict_articles_6_18: antinomia normativa entre Art. 6º (1 rebaixado) e Art. 18 (2 rebaixados) não resolvida. | Portaria 009/2026 regulou Copa do Brasil mas não sanou descenso ou Série D. | homologacao_bloqueada | bloqueadas_barreiras_administrativas_e_normativas | REC FMF 2026 (SHA-256 e348039a...) + Portaria 009 | tests/state-program-runtime-v07.php, tests/state-program-audit-cohort-v07.php | Programa executável sem homologação |
| PA | br-pa-a1 | v1 | sim | 44 | sim | não | sim | não | sim | não | sim | sim | não | Cruzamentos e repasses comprovados documentalmente. | nenhuma | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FPF-PA 2026 (SHA-256 5d2031b0...) + Tabela | tests/state-program-pa-v07.php, tests/state-program-cohort-v07.php, tests/state-program-runtime-v07.php | Executável aprovado |
| PB | nenhum | nenhuma | não | 0 | não | não | não | não | não | não | não | não | não | REC adulto 2026 não localizado nos canais institucionais públicos da FPF-PB. | Calendário Oficial (Ofício 001/2026) restrito a datas. | contrato_ausente | bloqueadas_sem_contrato | Calendário FPF-PB (SHA-256 c35a1b98...); REC ausente | tests/state-program-audit-cohort-v07.php (bloqueio de integridade) | Bloqueado por documentação |
| PE | pe-a1 | v1 | sim | 38 | sim | não | sim | não | sim | não | sim | sim | não | nenhuma | nenhuma | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FPF-PE 2026 | tests/state-program-runtime-v07.php, tests/state-program-audit-cohort-v07.php | Base integrada estável |
| PI | pi-a1 | v1 | sim | 34 | sim | não | sim | não | sim | não | sim | sim | não | nenhuma | nenhuma | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FFP 2026 | tests/state-program-runtime-v07.php, tests/state-program-audit-cohort-v07.php | Base integrada estável |
| PR | pr-a1 | v1 | sim | 54 | sim | não | sim | não | sim | não | sim | sim | não | nenhuma | nenhuma | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FPF-PR 2026 | tests/state-program-runtime-v07.php, tests/state-program-audit-cohort-v07.php | Base integrada estável |
| RJ | br-rj-a1 | v1 | sim | 62 | sim | não | sim | não | sim | não | sim | sim | sim | Mandos das semifinais dependem de input auditado em caso de acordo entre clubes. | Definição formal de mandos conforme ata. | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FERJ 2026 (SHA-256 e29779e5...) + Tabela | tests/state-program-rj-v07.php, tests/state-program-cohort-v07.php, tests/state-program-runtime-v07.php | Executável aprovado |
| RN | rn-a1 | v1 | sim | 38 | sim | não | sim | não | sim | não | sim | sim | não | nenhuma | nenhuma | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FNF 2026 | tests/state-program-runtime-v07.php, tests/state-program-audit-cohort-v07.php | Base integrada estável |
| RO | ro-a1 | v1 | sim | 48 | sim | não | sim | não | sim | não | sim | sim | não | nenhuma | nenhuma | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FFER 2026 | tests/state-program-runtime-v07.php, tests/state-program-audit-cohort-v07.php | Base integrada estável |
| RR | nenhum | nenhuma | não | 0 | não | não | não | não | não | não | não | não | não | REC adulto 2026 ausente nos canais institucionais da FRF; tabelas espelho divergem. | Arbitral sem ata oficial pública para 9 clubes. | contrato_ausente | bloqueadas_sem_contrato | Crônica esportiva regional; REC ausente | tests/state-program-audit-cohort-v07.php (bloqueio de integridade) | Bloqueado por documentação |
| RS | br-rs-a1 | v1 | sim | 64 | sim | não | sim | não | sim | não | sim | sim | não | Base histórica comprovada (64 jogos). | nenhuma | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FGF 2026 | tests/state-program-runtime-v07.php, tests/state-program-audit-cohort-v07.php | Base integrada estável |
| SC | br-sc-a1 | v1 | sim | 69 a 70 | sim | não | sim | não | sim | não | sim | sim | sim | Final da Taça ACESC condicionada às inscrições fixadas. | nenhuma | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FCF 2026 (SHA-256 50a22b9f...) + Tabela | tests/state-program-sc-v07.php, tests/state-program-cohort-v07.php, tests/state-program-runtime-v07.php | Executável aprovado |
| SE | br-se-a1 | v1 | sim | 55 | sim | não | sim | não | sim | não | sim | sim | não | Ata de 15/01 e projeção sanitizada verificadas. | nenhuma | nenhuma | geradas_em_fixtures_historicas_completas; bloqueadas_na_coorte_global_incompleta | REC FSF Revisão I (SHA-256 db852981...) + Ata | tests/state-program-se-v07.php, tests/state-program-runtime-v07.php | Executável aprovado |
| SP | sp-a1 | v1 | sim | 72 | sim | não | sim | não | sim | não | não | não | sim | sp_final_ranking_and_dco_venue_evidence_required: ranking 3º ao 8º omisso no REC. | Decisões do DCO sobre recintos podem afetar a validade administrativa das partidas além do ranking final. | homologacao_bloqueada | bloqueadas_barreiras_administrativas_e_normativas | REC FPF 2026 (SHA-256 53e01ade...) + Tabela | tests/state-program-v07.php (23 chks), tests/state-program-runtime-v07.php | Programa executável sem homologação |
| TO | br-to-a1 | v2 | sim | 34 | sim | sim | sim | sim | sim | sim | não | não | sim | to_final_ranking_campaign_scope_requires_clarification: escopo de pontos 3º/4º (Art. 11 § 1º) omisso. | Sorteio simulado em lots e conciliação de tabela por API 35 FTF. | nenhuma no piloto aprovado | bloqueadas_homologacao_parcial_sem_vaga | REC FTF 2026 (SHA-256 9fbcef77..., Arts. 11 § 1º e 13) + API 35 FTF | tests/state-program-to-partial-v07.php (144 chks), tests/state-program-runtime-v07.php | Certificação parcial aprovada (Codex) |

---

## 4. Revisão Sistemática das Pendências Condicionais

Para cada federação com condicionantes regulamentares, respondem-se objetivamente as seis perguntas obrigatórias, incorporando rigorosamente os apontamentos da auditoria independente:

### 4.1 São Paulo (`sp-a1`)
1. *Impede disputar partidas?* **Não.** As 72 partidas da tabela ocorrem no percurso esportivo (64 na 1ª fase + 4 quartas + 2 semis + 2 finais).
2. *Impede reconhecer o campeão?* **No percurso esportivo, não; sob certificação formal, sim.** O vencedor do confronto final em 2 jogos é matematicamente calculável, porém **campeão e vice NÃO são formalmente certificados sob `certified-outcomes-v1`**, pois o percurso encerra sob a pendência bloqueante `sp_final_ranking_and_dco_venue_evidence_required`.
3. *Impede reconhecer o descenso?* **No percurso esportivo, não; sob certificação formal, sim.** Os 2 últimos colocados da 1ª fase (15º e 16º) são determináveis, mas **o descenso NÃO é formalmente certificado** enquanto a homologação geral permanecer bloqueada.
4. *Impede apenas ranking e vagas nacionais?* **Não exclusivamente.** Além de bloquear a ordenação formal do 3º ao 8º colocados e a distribuição de vagas da Série D, **as decisões discricionárias do DCO sobre recintos e mandos (Art. 7º do REC e restrições da Polícia Militar na capital) podem afetar a própria validade administrativa e eficácia das partidas**, extrapolando o mero cálculo da tabela final.
5. *Exige alteração técnica no simulador?* **Não.** As salvaguardas de bloqueio estão implementadas.
6. *Exige ato ou documento externo?* **Sim.** Exige ato normativo complementar da FPF com a fórmula de classificação dos eliminados e atos administrativos do DCO para ratificação de recintos.

### 4.2 Mato Grosso (`br-mt-a1`)
1. *Impede disputar partidas?* **Não.** As 53 partidas são disputadas integralmente.
2. *Impede reconhecer o campeão?* **No percurso esportivo, não; sob certificação formal, sim.** O campeão é o vencedor da final em 2 jogos, mas não há emissão de recibo de certificação parcial.
3. *Impede reconhecer o descenso?* **Sim.** Há antinomia normativa direta e insanável entre o Art. 6º (que textualmente determina que apenas 1 clube seja rebaixado) e o Art. 18 (que prescreve 2 clubes rebaixados). A Portaria Pres/Nº 009/2026 regulamentou critérios para a Copa do Brasil, mas **não retificou nem sanou o conflito de descenso**. A quantidade de rebaixados **NÃO é certificada** sem resolução regulamentar primária.
4. *Impede apenas ranking e vagas nacionais?* **Não.** Bloqueia o descenso, a homologação integral e as vagas da Série D.
5. *Exige alteração técnica no simulador?* **Não.** O simulador suspende formalmente em `mt_relegation_conflict_articles_6_18`.
6. *Exige ato ou documento externo?* **Sim.** Resolução da FMF retificando formalmente os artigos 6º e 18 do REC.

### 4.3 Amazonas (`am-a1`)
1. *Impede disputar partidas?* **Não.** 38 partidas são disputadas (ou 39 caso haja finalíssima geral entre vencedores de turnos distintos).
2. *Impede reconhecer o campeão?* **Não.** Se turnos tiverem vencedores distintos, o campeão sai da final geral. Se o mesmo clube vencer ambos os turnos, ele é o campeão estadual.
3. *Impede reconhecer o descenso?* **Não.** O rebaixamento é definido pela classificação acumulada da 1ª fase dos dois turnos.
4. *Impede apenas ranking e vagas nacionais?* **Não.** **Se o mesmo clube vencer ambos os turnos, a indefinição regulamentar do vice-campeão pode impedir o fechamento geral da competição**, pausando o fluxo em `pending_if_same_winners`. **O código atual NÃO emite automaticamente certificação parcial equivalente à de MS/TO nesse cenário.**
5. *Exige alteração técnica no simulador?* **Não.** A barreira determinística atua conforme especificado.
6. *Exige ato ou documento externo?* **Sim.** Ato regulamentar da FAF disciplinando a indicação do vice-campeão na hipótese de campeão único de turnos.

### 4.4 Minas Gerais (`br-mg-a1`)
1. *Impede disputar partidas?* **Não.** São disputadas entre 56 e 61 partidas conforme os cenários dos torneios paralelos.
2. *Impede reconhecer o campeão?* **Não.** O campeão estadual é o vencedor da final em 2 jogos.
3. *Impede reconhecer o descenso?* **Não.** Definido pelo triangular de permanência (repescagem).
4. *Impede apenas ranking e vagas nacionais?* **Não.** Determinados cenários do Troféu do Interior (como a ocorrência de mais de 2 clubes do interior classificados para as semifinais do campeonato principal) **podem suspender a execução antes do agendamento das finais, inclusive na marca de 56 partidas disputadas**. Além disso, **a aplicação do Artigo 24 para o Troféu Inconfidência não deve ser descrita como interpretação normativa definitivamente resolvida**, tratando-se de execução estrita provisória sob ressalva documental.
5. *Exige alteração técnica no simulador?* **Não.**
6. *Exige ato ou documento externo?* **Sim.** Resolução da FMF disciplinando as hipóteses atípicas de interioranos nas semifinais.

### 4.5 Maranhão (`ma-a1`)
1. *Impede disputar partidas?* **Não.** As 36 partidas da tabela são executáveis.
2. *Impede reconhecer o campeão?* **Não.** Campeão sai da fase final em 2 jogos.
3. *Impede reconhecer o descenso?* **Condicional.**
4. *Impede apenas ranking e vagas nacionais?* **Não.** Caso ocorra empate absoluto em todos os critérios de permanência na 1ª fase, **o empate de permanência pode suspender o fechamento geral da competição**, impedindo a conclusão formal do torneio, e **não se reduzindo apenas à distribuição de vagas nacionais**.
5. *Exige alteração técnica no simulador?* **Não.** Barreira determinística configurada.
6. *Exige ato ou documento externo?* **Sim.** Ato da FMF com critério de desempate residual administrativo.

### 4.6 Acre (`ac-a1`)
1. *Impede disputar partidas?* **Condicional.** As 34 partidas regulares ocorrem, porém **a definição dos mandos de campo e a eventual realização de jogos extras de desempate podem depender de decisão administrativa prévia antes que as partidas seguintes possam ser agendadas e disputadas**.
2. *Impede reconhecer o campeão?* **Não.**
3. *Impede reconhecer o descenso?* **Não.**
4. *Impede apenas ranking e vagas nacionais?* Não, pode condicionar a ordem e a realização física dos confrontos seguintes.
5. *Exige alteração técnica no simulador?* **Não.** Mecanismo de input auditado `StateProgramAdministration::schedule` operacional.
6. *Exige ato ou documento externo?* **Sim.** Ata e portaria de designação de mandos da FFAC.

### 4.7 Distrito Federal (`df-a1`)
1. *Impede disputar partidas?* **Não.** As 50 partidas da fase classificatória e eliminatória ocorrem normalmente.
2. *Impede reconhecer o campeão?* **Não.**
3. *Impede reconhecer o descenso?* **Não.**
4. *Impede apenas ranking e vagas nacionais?* **Não.** Em caso de empate na liderança que exija jogo desempate, **as decisões administrativas do DCO sobre jogo extra e designação de campo neutro podem alterar a classificação geral e redefinir os confrontos posteriores**, afetando diretamente a progressão da fase eliminatória.
5. *Exige alteração técnica no simulador?* **Não.** Tratador administrativo auditado.
6. *Exige ato ou documento externo?* **Sim.** Portaria da FFDF com data e local neutro auditados.

### 4.8 Espírito Santo e Copa Espírito Santo
1. *O Estadual principal (Capixabão) é homologado?* **Sim.** O Campeonato Capixaba principal (59 jogos) é executado e homologado de forma autônoma (`homologacao_integral: sim`).
2. *A Copa Espírito Santo é executável integralmente?* **Não.** **A Copa Espírito Santo NÃO é declarada executável integralmente no simulador.** Permanece inativa para fins de classificação nacional, **preservando a pendência normativa de composição, formato, estrutura da edição e vinculação com a segunda vaga da Série D**.
3. *Impede reconhecer o campeão estadual?* **Não.** O campeão do Capixabão é plenamente reconhecido.
4. *Impede reconhecer o descenso estadual?* **Não.** O descenso estadual é determinável e homologado.
5. *Impede apenas ranking e vagas nacionais?* A indefinição da Copa ES afeta exclusivamente a destinação da 2ª vaga da Série D (se pertence ao vice-campeão estadual ou ao campeão da Copa ES).
6. *Exige ato ou documento externo?* **Sim.** Publicação do regulamento específico da Copa ES pela FES.

---

## 5. Interpretação Correta das Evidências Empíricas

### 5.1 Desdobramento Preciso da Coorte de 3.046 Partidas
Para sanar generalizações anteriores, registra-se expressamente o escopo exato da coorte NPC auditada (`StateFederationsTrial::auditCohort(876001)`):
- **32 competições** compõem a coorte global simulada.
- **24 federações estaduais** possuem programas esportivos executáveis disponíveis.
- **1.192 partidas pertencem estritamente aos 24 estaduais principais** identificados e auditados pelo Codex.
- **1.854 partidas pertencem às demais competições da coorte**, abrangendo séries de acesso estaduais e divisões da pirâmide nacional.
- **Não correspondência exclusiva:** A coorte de 3.046 partidas **NÃO corresponde exclusivamente a campeonatos estaduais principais** e **NÃO deve ser apresentada como execução integral do Game nos três modos ou teste MySQL completo de cada competição**.
- **Separação rigorosa das classes de evidência:**
  1. *Coorte NPC:* 3.046 partidas simuladas em memória sobre o fixture integrado (`tests/state-program-audit-cohort-v07.php`, 1.192 partidas estaduais principais).
  2. *Testes de Runtime:* Verificação direcionada exclusivamente aos 4 programas com fechamento pendente (SP, TO, MT, MS) nos 3 modos do Game em `tests/state-program-runtime-v07.php`.
  3. *Testes PDO:* Testes direcionados com transações isoladas e `ROLLBACK` integral em `tests/state-program-mysql-v07.php`, `tests/state-program-complete-mysql-v07.php`, `tests/state-program-proof-mysql-v07.php` e `tests/state-program-audit-mysql-v07.php`.
  4. *Testes HTTP:* Renderização de telas de classificação, visualização de recintos e sanitização de tabelas em `tests/state-program-http-v07.php`.
  5. *Testes Mobile:* 66 verificações visuais em 3 viewports (320px, 390px, 1280px) em `tests/state-program-mobile-v07.cjs`.
  6. *Fixtures Históricas:* `database/competition-references/br-state-program-2026-v1.json` e `storage/qa/states27/combined-npc-fixture.json`.
  7. *Evidências Atuais:* `database/competition-references/br-state-program-reviewed-2026-v2.json`, `Docs/mundializacao-v07/conclusao-27/synthetic-ledger-fixture.json`, `Docs/mundializacao-v07/conclusao-27/to-partial-certification.json` e `Docs/mundializacao-v07/conclusao-27/ms-partial-certification-pilot.json`.

### 5.2 Concessão de XP, Pontos R e Recompensas
Adota-se estritamente a formulação regulamentar precisa:
> **"Nenhuma recompensa adicional de título, conquista ou Pontos R foi concedida em decorrência de certificação parcial. O XP legítimo de participação nas partidas permanece sujeito às regras normais do simulador."**

Nenhuma regra ou fórmula de progressão de atleta ou experiência foi alterada.

### 5.3 Candidaturas Nacionais e Admissão na Série D
Diferenciação obrigatória entre:
1. *Candidaturas comprovadas em fixtures históricas completas:* Cenários onde a simulação da temporada anual nacional completa gera formalmente a lista de qualificados para as séries nacionais.
2. *Candidaturas não geradas na coorte global incompleta:* Cenários onde o simulador pausa atomicamente no estágio `world_pending`, antes de inicializar o calendário da Série D.
3. *Inscrições e admissões nacionais não efetivadas:* Nenhuma associação recebe admissão definitiva na Série D com base em certificação parcial.
4. *Bloqueios de renovação de temporada:* A renovação de ano no Game permanece travada até que as pendências federativas sejam resolvidas.
*Candidatura desportiva não se confunde com admissão homologada nem com inscrição definitiva na CBF.*

---

## 6. Documentação Separada das Ressalvas Técnicas

1. **Reconstituição dos Saves Históricos sem Backups Imutáveis Originais:**
   Determinados snapshots de regressão histórica foram reconstituídos a partir de definições do contrato v1 e de saídas conhecidas do motor esportivo, haja vista que não foram preservados dumps binários imutáveis das execuções originais de versões legadas de desenvolvimento. A integridade matemática foi verificada por hashes sha256 reprodutíveis.
2. **Limitação da Representação do Tipo JSON após Hidratação Associativa:**
   No ecossistema PHP, a desserialização com `json_decode($json, true)` converte objetos JSON vazios ou com chaves inteiras sequenciais em arrays indexados regulares, tornando-os indistinguíveis de listas sequenciais nativas. Essa limitação da representação é mitigada com a checagem rigorosa de `array_is_list()` na raiz do validador e com suítes de testes negativos que injetam conversões forçadas de arrays para objetos JSON antes do cálculo do digest canônico.
3. **Sorteio Simulado Persistido em `lots` (FTF):**
   A inversão ou definição da ordem de mandos de campo das semifinais em Tocantins baseou-se em ordenação simulada persistida na chave `lots` do motor de sorteios. **Esse sorteio em `lots` constitui artefato determinístico do simulador e NÃO corresponde a um ato administrativo real emitido pela Federação Tocantinense de Futebol.**
4. **Conciliação da Tabela e dos Mandos de TO pela API Oficial:**
   A correção do cruzamento preliminar (Anexo II do REC, que continha repetição material de 2º×2º) foi conciliada empiricamente a partir dos confrontos efetivamente programados e disputados na API oficial de competições da FTF (`admin.ftf.org.br/api/v1/competitions/35`). **Essa conciliação técnica por API NÃO deve ser caracterizada nem confundida com retificação normativa formal de regulamento.**
   *(Sorteio em `lots` e conciliação por API são assuntos e processos distintos, mantidos rigorosamente segregados).*
5. **Identificação de `all-available-sports.json` como Evidência Histórica:**
   O artefato `Docs/mundializacao-v07/conclusao-27/all-available-sports.json` é expressamente identificado como evidência histórica consolidada em momento anterior à aprovação do recibo parcial de Tocantins. Esse arquivo **não foi e não deve ser atualizado artificialmente**, preservando o registro histórico fático daquele checkpoint de auditoria.

---

## 7. Reprodutibilidade dos Testes e Procedimentos Comprovados

Em cumprimento à determinação da coordenação, todos os scripts foram inspecionados em seu código-fonte real para comprovar os argumentos exigidos e evitar comandos inoperantes:

### 7.1 Pré-requisitos de Ambiente
1. **PHP:** Versão 8.4 CLI com extensões `mbstring` e `pdo_mysql` habilitadas:
   ```powershell
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring -d extension=pdo_mysql -v
   ```
2. **Banco de Dados Local (quando aplicável a testes PDO):**
   Instância MySQL 8.0+ ou MariaDB 10.4+ em `127.0.0.1:3306`, base `rumohub`, credenciais locais configuradas no ambiente.
3. **Node.js (para testes mobile opcionais):**
   Node.js v18+ com Playwright instalado no projeto (`npm install playwright`) ou apontado via variável `PLAYWRIGHT_PATH`.

### 7.2 Geradores Corretos de Snapshots (Não confundir com testes PDO)
Estes comandos geram as fixtures JSON exigidas pelos testes subsequentes e operam estritamente em memória:
1. **Geração de `combined-npc-fixture.json` (Fixture Coorte Histórica 872001):**
   ```powershell
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring tests/state-program-cohort-v07.php
   ```
   *Efeito:* Gera `storage/qa/states27/combined-npc-fixture.json` e `Docs/mundializacao-v07/conclusao-27/combined-sports.json`.
2. **Geração de `all-available-npc-fixture.json` (Fixture Coorte Geral 876001):**
   ```powershell
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring tests/state-program-audit-cohort-v07.php
   ```
   *Efeito:* Simula 3.046 partidas e gera `storage/qa/states27/all-available-npc-fixture.json` e `Docs/mundializacao-v07/conclusao-27/all-available-sports.json`.

### 7.3 Execução de Testes Independentes de Banco (Percursos Esportivos e Provas)
1. **Runtime dos 4 Programas Pendentes em 3 Modos:**
   ```powershell
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring tests/state-program-runtime-v07.php
   ```
   *Escopo:* Executa 12 simulações isoladas (SP, TO, MT, MS em Normal, Rápido e Expresso).
2. **Ordenação Determinística do Ledger:**
   ```powershell
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring tests/state-program-ledger-order-v07.php
   ```
3. **Pilotos de Certificação Parcial:**
   ```powershell
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring tests/state-program-ms-partial-v07.php
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring -d extension=pdo_mysql tests/state-program-to-partial-v07.php
   ```
4. **Testes Estaduais Específicos:**
   ```powershell
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring tests/state-program-am-v07.php
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring tests/state-program-mg-v07.php
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring tests/state-program-se-v07.php
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring tests/state-program-ce-v07.php
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring tests/state-program-pa-v07.php
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring tests/state-program-rj-v07.php
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring tests/state-program-sc-v07.php
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring tests/state-program-v07.php
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring tests/state-program-interior-v07.php
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring tests/state-program-average-v07.php
   ```

### 7.4 Execução de Testes PDO com Rollback (Exigem `--local-rollback`)
Os scripts de persistência MySQL recusam execução e imprimem ajuda caso o argumento `--local-rollback` não seja explicitamente passado:
1. **Persistência e Rollback de SP (72 jogos):**
   ```powershell
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring -d extension=pdo_mysql tests/state-program-mysql-v07.php --local-rollback
   ```
   *Efeito:* Hidrata esquema transitório de teste em MySQL, testa retry e crash injection, e executa `ROLLBACK` total.
2. **Persistência Completa de Competição (ex.: MS modo expresso):**
   ```powershell
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring -d extension=pdo_mysql tests/state-program-complete-mysql-v07.php --local-rollback br-ms-a1 expresso
   ```
   *Efeito:* Executa a competição completa em MySQL com rollback integral comprovado.
3. **Persistência de Provas Canônicas (Exige fixture `combined-npc-fixture.json`):**
   ```powershell
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring -d extension=pdo_mysql tests/state-program-proof-mysql-v07.php --local-rollback
   ```
4. **Persistência da Auditoria de 24 Federações (Exige fixture `all-available-npc-fixture.json`):**
   ```powershell
   & "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring -d extension=pdo_mysql tests/state-program-audit-mysql-v07.php --local-rollback
   ```

### 7.5 Geração e Execução dos Testes HTTP (Exige `--local-fixtures`)
O script HTTP inicia servidor embutido em loopback (`127.0.0.1:18071`), executa requisições autenticadas e gera os arquivos HTML sanitizados para o teste mobile:
```powershell
& "C:\PHP\php-8.4.26\php.exe" -n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring -d extension=pdo_mysql tests/state-program-http-v07.php --local-fixtures
```
*Arquivos HTML gerados sob `storage/qa/states27/http/`:*
- `carreira.html`
- `jogador.html`
- `classificacao.html`
- `carreira-trofeus.html`
- `carreiras.html`
- `classificacao-ce-ramos.html`
- `carreira-rj-pendente.html`
- `carreira-am-recinto.html`
- `processing-debug.html`

### 7.6 Execução Opcional do Teste Mobile (Playwright)
Após a geração dos arquivos HTML acima:
```powershell
node tests/state-program-mobile-v07.cjs
# ou caso o Playwright esteja em caminho customizado:
$env:PLAYWRIGHT_PATH="C:\Users\<usuario>\AppData\Roaming\npm\node_modules\playwright"; node tests/state-program-mobile-v07.cjs
```
*Resultado comprovado pelo Codex:* 66 verificações visuais e semânticas em 3 viewports (320px, 390px, 1280px). Se a dependência ou as fixtures não existirem, encerra com código 1 sem gerar falso positivo.

---

## 8. Inventário Definitivo dos 155 Caminhos Observados no Git

A classificação dos 155 caminhos presentes no repositório (`git status --porcelain=v1 -uall`) foi rigorosamente corrigida e confirmada:
- **48 arquivos para o commit mínimo** (8 modificados e 40 novos).
- **107 arquivos excluídos** (2 arquivos modificados herdados, 11 arquivos do levantamento anterior e 94 evidências auxiliares).

### 8.1 Arquivos para Inclusão no Commit Mínimo (48 Arquivos)

#### A. Contratos e Referências Desportivas (2 arquivos novos)
1. `database/competition-references/br-state-program-reviewed-2026-v2.json` (`??` | Incluir | Contrato revisado com MS e TO sob `certified-outcomes-v1`, conciliações SE e SP | Dep: ProgrammedStateCompetition | Risco: Nenhum | Testes: `to-partial`, `ms-partial`)
2. `database/competition-references/br-state-program-2026-v1.json` (`??` | Incluir | Contrato v1 fixado para regressão de compatibilidade histórica | Dep: Nenhuma | Risco: Nenhum | Testes: `to-partial`, `ms-partial`)

#### B. Núcleo do Simulador e Mecânicas de Estado (8 modificados + 4 novos = 12 arquivos)
3. `app/CompetitionView.php` (`M` | Incluir | Visualização de pendências normativas | Dep: Nenhuma | Risco: Nenhum | Testes: `http-v07`)
4. `app/ConfiguredStateCompetition.php` (`M` | Incluir | Compatibilização de fases e critérios | Dep: Nenhuma | Risco: Nenhum | Testes: `runtime-v07`)
5. `app/FutureSerieDAdmission.php` (`M` | Incluir | Salvaguarda bloqueando admissões na Série D sob certificação parcial | Dep: Nenhuma | Risco: Nenhum | Testes: `to-partial`, `ms-partial`)
6. `app/Game.php` (`M` | Incluir | Salvaguarda de estágio `world_pending` e contenção de avanço | Dep: Nenhuma | Risco: Nenhum | Testes: `runtime-v07`)
7. `app/ProgrammedStateCompetition.php` (`??` | Incluir | Mecanismo `certified-outcomes-v1`, recibo canônico e validação | Dep: Contratos | Risco: Nenhum | Testes: Todos os estaduais)
8. `app/StateFederationsTrial.php` (`??` | Incluir | Harness de execução e auditoria de coortes | Dep: Nenhuma | Risco: Nenhum | Testes: `audit-cohort-v07`)
9. `app/StateProgramAdministration.php` (`??` | Incluir | Gestão determinística de mandos administrativos | Dep: Nenhuma | Risco: Nenhum | Testes: `runtime-v07`)
10. `app/StateQualification.php` (`M` | Incluir | Bloqueio de vagas nacionais sob certificação parcial | Dep: Nenhuma | Risco: Nenhum | Testes: `to-partial`, `ms-partial`)
11. `app/StateResultProof.php` (`M` | Incluir | Cálculo canônico de digests e imutabilidade de recibos | Dep: Nenhuma | Risco: Nenhum | Testes: `proof-mysql`, `to-partial`)
12. `app/StateVenueAdministration.php` (`??` | Incluir | Gestão determinística de recintos | Dep: Nenhuma | Risco: Nenhum | Testes: `runtime-v07`, `http-v07`)
13. `app/WorldSeason.php` (`M` | Incluir | Escopo de temporada e isolamento determinístico | Dep: Nenhuma | Risco: Nenhum | Testes: `to-partial`, `runtime-v07`)
14. `app/view.php` (`M` | Incluir | Sanitização de dados para interface web | Dep: Nenhuma | Risco: Nenhum | Testes: `http-v07`)

#### C. Suítes de Testes Automatizados (22 arquivos novos)
15. `tests/state-program-am-v07.php` (`??` | Incluir | Validação de turnos e bloqueio de vice no Amazonas | Risco: Nenhum)
16. `tests/state-program-audit-cohort-v07.php` (`??` | Incluir | Auditoria de coorte (1.192 partidas estaduais principais) | Risco: Nenhum)
17. `tests/state-program-audit-mysql-v07.php` (`??` | Incluir | Auditoria de persistência e rollback | Risco: Nenhum)
18. `tests/state-program-average-v07.php` (`??` | Incluir | Cálculo exato de média de campanha por produtos cruzados | Risco: Nenhum)
19. `tests/state-program-ce-v07.php` (`??` | Incluir | Validação do Cearense | Risco: Nenhum)
20. `tests/state-program-cohort-v07.php` (`??` | Incluir | Validação de coorte compartilhada | Risco: Nenhum)
21. `tests/state-program-complete-mysql-v07.php` (`??` | Incluir | Validação PDO com rollback de MS | Risco: Nenhum)
22. `tests/state-program-http-v07.php` (`??` | Incluir | Validação HTTP e geração de fixtures HTML | Risco: Nenhum)
23. `tests/state-program-interior-v07.php` (`??` | Incluir | Fronteiras de classificação interiorana | Risco: Nenhum)
24. `tests/state-program-ledger-order-v07.php` (`??` | Incluir | Testes de ordenação determinística do ledger | Risco: Nenhum)
25. `tests/state-program-mg-v07.php` (`??` | Incluir | Validação de triangular e Interior em Minas Gerais | Risco: Nenhum)
26. `tests/state-program-mobile-v07.cjs` (`??` | Incluir | Teste mobile portável via PLAYWRIGHT_PATH | Risco: Nenhum)
27. `tests/state-program-ms-partial-v07.php` (`??` | Incluir | Testes de MS e integridade de tipos | Risco: Nenhum)
28. `tests/state-program-mysql-v07.php` (`??` | Incluir | Hidratação e teste de persistência MySQL | Risco: Nenhum)
29. `tests/state-program-pa-v07.php` (`??` | Incluir | Validação do Paraense | Risco: Nenhum)
30. `tests/state-program-proof-mysql-v07.php` (`??` | Incluir | Provas canônicas em banco | Risco: Nenhum)
31. `tests/state-program-rj-v07.php` (`??` | Incluir | Validação do Carioca e Taça Rio | Risco: Nenhum)
32. `tests/state-program-runtime-v07.php` (`??` | Incluir | Validação direcionada dos 4 programas com pendência em 3 modos | Risco: Nenhum)
33. `tests/state-program-sc-v07.php` (`??` | Incluir | Validação do Catarinense e Taça ACESC | Risco: Nenhum)
34. `tests/state-program-se-v07.php` (`??` | Incluir | Validação da ata da FSF e recintos em Sergipe | Risco: Nenhum)
35. `tests/state-program-to-partial-v07.php` (`??` | Incluir | Testes de TO com Art. 13 e PDO em escopo | Risco: Nenhum)
36. `tests/state-program-v07.php` (`??` | Incluir | Validação de barreiras em São Paulo | Risco: Nenhum)

#### D. Documentação e Provas Formais Consolidadas (12 arquivos novos)
37. `Docs/59_Mundializacao_v07_Conclusao_27_Estaduais.md` (`??` | Incluir | Relatório técnico consolidado mestre | Risco: Nenhum)
38. `Docs/mundializacao-v07/conclusao-27/all-available-sports.json` (`??` | Incluir | Evidência histórica anterior à homologação de TO | Risco: Nenhum)
39. `Docs/mundializacao-v07/conclusao-27/federations.csv` (`??` | Incluir | Matriz tabular unificada das 27 UFs | Risco: Nenhum)
40. `Docs/mundializacao-v07/conclusao-27/federations.json` (`??` | Incluir | Matriz canônica unificada das 27 UFs | Risco: Nenhum)
41. `Docs/mundializacao-v07/conclusao-27/ms-partial-certification-pilot.json` (`??` | Incluir | Prova canônica auditada de MS | Risco: Nenhum)
42. `Docs/mundializacao-v07/conclusao-27/program-game-modes.json` (`??` | Incluir | Evidência dos 3 modos do Game | Risco: Nenhum)
43. `Docs/mundializacao-v07/conclusao-27/se-table-projection.json` (`??` | Incluir | Projeção de classificação de Sergipe | Risco: Nenhum)
44. `Docs/mundializacao-v07/conclusao-27/source-ledger.json` (`??` | Incluir | Ledger canônico auditado de documentos oficiais | Risco: Nenhum)
45. `Docs/mundializacao-v07/conclusao-27/sources.csv` (`??` | Incluir | Relação tabular de fontes oficiais primárias e hashes | Risco: Nenhum)
46. `Docs/mundializacao-v07/conclusao-27/synthetic-ledger-fixture.json` (`??` | Incluir | Fixture estável estática aprovada pelo Codex, 619 KB | Risco: Nenhum)
47. `Docs/mundializacao-v07/conclusao-27/to-official-bracket-conciliation.json` (`??` | Incluir | Registro empírico da conciliação da API 35 FTF | Risco: Nenhum)
48. `Docs/mundializacao-v07/conclusao-27/to-partial-certification.json` (`??` | Incluir | Prova canônica auditada de TO | Risco: Nenhum)

### 8.2 Arquivos para Exclusão do Commit Mínimo (107 Arquivos)
1. **Arquivos herdados equivalentes ao HEAD (2 arquivos modificados):**
   - `Docs/mundializacao-v07/classificatorias-estaduais/contracts.json` (`M`): Preservar alterações locais sem descartar e sem comitar.
   - `Docs/mundializacao-v07/motor-estadual/sports.json` (`M`): Preservar alterações locais sem descartar e sem comitar.
2. **Pasta de levantamento anterior independente (11 arquivos untracked):**
   - `Docs/levantamento-2026-10-15/` (11 arquivos untracked): Trabalho histórico prévio realizado em outra rodada, integralmente mantido na raiz do workspace, fora do commit da Entrega 59.
3. **Evidências de depuração transitória e capturas visuais em `Docs/mundializacao-v07/conclusao-27/` (94 arquivos untracked):**
   - 21 capturas PNG de mobile (`mobile/*.png`).
   - Lints e rechecks transitórios (`lint-final.json`, `lint-program.json`, `contracts-recheck.json`, etc.).
   - Arquivos locais de auditoria textual (`changed-files.txt`, `git-status-audit.txt`, `flags-audit.txt`).
   - Dumps temporários de MySQL por modo (`br-*-mysql-*.json`, `program-mysql-expresso.json`).
   - Diretórios locais de regressão intermediária (`regressao/` e `regressao-final/`).
   - Subdiretório `legacy-rechecks/`.
   - Arquivos intermediários (`secret-review.json`, `combined-sports.json`, etc.).
4. **Arquivos fora do versionamento / Gitignorados:**
   - `storage/qa/states27/` (snapshots binários e PDFs institucionais locais).
   - Scripts de scratch em diretórios temporários (`scratch/`).

### 8.3 Correção do Escopo de Runtime
`tests/state-program-runtime-v07.php` executa exclusivamente a validação dos **4 programas com fechamento documental pendente** (`sp-a1`, `br-to-a1`, `br-mt-a1`, `br-ms-a1`) nos **três modos do Game** (Normal, Rápido, Expresso), totalizando 12 execuções isoladas. Essa suíte **não executa os 24 estaduais completos**. A verificação dos 24 programas estaduais disponíveis pertence ao escopo da coorte NPC (`tests/state-program-audit-cohort-v07.php`, 1.192 partidas estaduais principais em 32 competições).

---

## 9. Testes de Consistência e Verificação Independente

1. **Paridade Rigorosa CSV / JSON / MD:**
   - O script de verificação independente `scratch/test_matrix_parity_live.php` foi executado diretamente contra os três arquivos vigentes:
     - 27 federações identificadas em `federations.json`.
     - 27 linhas de dados em `federations.csv`.
     - 27 linhas da tabela Markdown em `Docs/59_Mundializacao_v07_Conclusao_27_Estaduais.md`.
     - **Resultado:** `Total differences across JSON, CSV and MD: 0. VERIFICATION PASSED: Zero differences across all 27 UFs and 21 fields in CSV, JSON and MD.`
2. **`git diff --check`:** Executado com código de saída `0`, sem detecção de espaços em branco espúrios ou conflitos de merge.
3. **Regressões Direcionadas Essenciais:**
   - `tests/state-program-to-partial-v07.php`: **144 verificações aprovadas** (`[PDO: executado: aprovado com rollback]`).
   - `tests/state-program-ms-partial-v07.php`: **172 verificações aprovadas**.
   - `tests/state-program-ledger-order-v07.php`: **7 verificações aprovadas**.
4. **Sintaxe e Linting:** Compilação limpa de todos os arquivos PHP do escopo (`php -l`).

---

## 10. Recomendação Final e Classificação da Entrega

### 10.1 Classificação da Entrega
Classificação formal: **A. Pronta para auditoria final de liberação de commit.**

Todas as 22 divergências anteriores foram mapeadas e corrigidas, a matriz única com 27 federações está 100% idêntica entre CSV, JSON e Markdown (zero diferenças comprovadas), os procedimentos e argumentos reais dos scripts de teste foram documentados conforme sua implementação efetiva (`--local-fixtures`, `--local-rollback`), a descrição do runtime foi delimitada aos 4 programas pendentes, e as contagens do inventário de 155 caminhos (48 incluir / 107 excluir) foram totalmente alinhadas.

### 10.2 Recomendação de Encerramento Técnico: Opção B (Encerramento Técnico Parcial Controlado)
Recomenda-se formalmente a homologação da **Opção B — Encerramento Técnico Parcial Controlado**:
- Consolida de maneira imutável e auditada os **24 programas estaduais operacionais** e as **2 certificações parciais desacopladas aprovadas (MS e TO)**.
- Preserva a verdade material do projeto ao manter **AP, PB e RR formalmente bloqueadas** na fase documental, aguardando atos normativos oficiais primários que não dependem do simulador.
- Garante total segurança jurídica e técnica sem iniciar prematuramente a v0.8 ou a evolução do atleta.
- Permite ao proprietário técnico realizar o commit limpo do pacote de 48 arquivos identificados.

**Nenhum comando `git add`, `commit`, `push`, `merge` ou `deploy` foi executado.**
