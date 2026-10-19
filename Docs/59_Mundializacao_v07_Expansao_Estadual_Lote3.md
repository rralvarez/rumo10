# Entrega59 — primeiro checkpoint: Rio Grande do Sul executável

19/10/2026. Base local/remota de entrada `c67a00da1cbe9d704a7ad2a1ce8b1d7062eacb4f`, branch `codex/mundializacao-v07`. Relatório58 lido integralmente, inclusive histórico; contratos v1–v7 preservados. Pasta alheia `Docs/levantamento-2026-10-15/` intocada. **A Entrega59 permanece aberta: este checkpoint integra uma UF, não quatro a seis.**

## Resultado real e portão de aceite

**13 federações técnicas:** AL,PI,RO,BA,ES,PE,RN,DF,PR,GO,MA,AC e RS. Restam14 sem integração. RS executou64 partidas pelo Game em cada um dos três modos e64 pelo Repository/PDO Expresso. Não são campeonatos apenas cadastrados. A prova reconstrói grupos,mandos,resultados,ranking,campeão,permanência,Farroupilha e méritos secundários.

O mínimo do primeiro checkpoint foi demonstrado; **a meta preferencial16–18 ainda não foi atingida**. Nenhuma nova competição pública ou flag foi ativada. Zero inscrições nacionais e zero renovações; cinco temporadas nacionais não foram executadas. Game/PDO estadual isolado é distinguido da coorte NPC com21 competições.

## Seleção documental e fontes

Triagem focal das seis UFs, com links e próximos passos em `mundializacao-v07/expansao-estadual/lote3/research.json`. Não houve nova auditoria mundial.

|UF|Situação neste checkpoint|
|---|---|
|RS|REC e tabela conciliados;implementação/testes completos do percurso estadual isolado.|
|SP|REC,64 confrontos iniciais e nota de duas finais encontrados. Não implementado: falta conciliação do RGC/ranking e restrições de recinto, além do suporte à liga parcial. A página RGC retornou403 na obtenção local; não é prova de inexistência documental.|
|CE|REC lido: precisa de fase intra-grupos, segunda fase intergrupos com mandos próprios,permanência em turno único e disputas3º/5º. Tabela detalhada e roteamento ainda não conciliados para contrato executável.|
|SC|REC oficial localizado; resolução teve timeout. Documentos,atualizações e tabela não integralmente conciliados; sem ativação.|
|MG|Portal oficial,REC/RGC e atos de classificação localizados; anexos e caminhos completos ainda não conciliados.|
|RJ|Portal e notícia oficial localizados. Documento de SérieA2 não substitui REC SérieA; ramos e tabela ainda não conciliados.|

Essas são pendências de implementação/conciliação deste checkpoint, não alegação de impossibilidade dos cinco estados restantes. Próximo lote pode priorizar SP e CE com as extensões estritamente necessárias; não copiar o quadrangular gaúcho para competições de regras diferentes.

Fontes RS: [REC FGF2026](https://www.fgf.com.br/public/uploads/noticias/693caa950a28d-Gaucha%CC%83o%20SUPERBET%202026%20-%20REC_251212_205059.pdf), arts.4,6,9–20,pp.3–12; [tabela detalhada oficial](https://fgf.com.br/Layout/documentos/69aefa8ba061ftabela-detalhada-gaucha%CC%83o-2026-2.pdf). Os36 confrontos iniciais e mandos foram extraídos; nenhum placar histórico entrou no contrato. SHA256,bytes,consulta e localizadores em `rs/sources.csv` e no contrato. Datas são simuladas, não agenda histórica. BID/licenciamento e processos disciplinares reais não certificados.

Doze participantes conciliados: Internacional,Juventude,Avenida,São José,Guarany de Bagé,São Luiz;Grêmio,Caxias,Novo Hamburgo,Monsoon,Internacional de Santa Maria,Ypiranga. Oito IDs de catálogo e quatro referências locais sem ratings/cidades/escudos/elencos novos. Internacional de Santa Maria não foi fundido com Internacional de Porto Alegre; Guarany não é Guarani-SP. ID `br-rs-a1` evita colisão com `rs-1`, que é liga sérvia no catálogo. Nenhum dado Hub foi criado.

## Implementação e adaptações declaradas

Novo `configured-state-v8-parallel`, restrito ao contrato RS2026. Reutiliza tabela/resolve/knockout/calendário existentes; não altera os interpretadores congelados. Estende a barreira de ramos para quadrangular completo, classificação própria, roteamento por posição de grupo e reordenamento por campanha. Valida36 pares intergrupos,6 rodadas,3 mandos por clube; quadrangular exige12 confrontos dirigidos,6 rodadas e sem duplicação de clube por rodada.

O percurso disputa liga36,quartas4,permanência12,semifinais principais4,Farroupilha semifinais4 e finais2+2: **64 jogos**. Quartas dentro do próprio grupo1×4/2×3; demais eliminatórias reordenam/classificam por campanha das fases pertinentes. Empate do mata-mata usa pontos,saldo,pênaltis persistidos. Liga/grupos usam pontos,vitórias,saldo,gols pró,vermelhos,amarelos e sorteio terminal simulado persistido, identificado como adaptação e não ata FGF.

Permanência inicia com zero,sem carregar pontos da liga. Seus dois últimos produzem mérito de descenso; nenhuma promoção estadual para divisão nacional é criada. O ranking final separa campeão/vice,semifinalistas,Farroupilha e quadrangular. Campeão Farroupilha e campeão do interior ficam em `secondary_merits`, sem duplicar o título principal,XP ou R. Méritos secundários ainda não foram publicados como novos troféus/conquistas. Classificação externa continua pendente.

**Adaptação de agenda:** a barreira com quartas e quadrangular precisa estar encerrada antes de liberar as semifinais. Isso pode retardá-las em relação à sobreposição real2026; é calendário simulado conservador com descanso, não reprodução das datas oficiais. Não omite partidas nem altera mérito. O builder completo tem21 competições e teto específico2477 (=2413 anterior+64),budget NPC8; limite genérico preservado. Os testes Game/PDO de temporada são RS isolado, não esse universo inteiro pelo Game.

## Problemas detectados e correções restritas

A prova do Game detectou sobrescrita do descenso: `WorldSeason::advance` reaplicava os dois últimos da liga sobre os rebaixados do quadrangular. **V8 passa a preservar o resultado certificado do adaptador.** A exceção é somente opt-in v8; contratos antigos não foram reinterpretados. O comportamento genérico anterior merece revisão separada em outros adaptadores com descenso por ramo,sem migrar resultados encerrados nesta entrega.

Também foram testados pontos zerados,quartas por grupo,reordenação semifinal,barreira incompleta atômica,Farroupilha em5º/6º,interior com exclusões e adulterações de placar,mando,rodada,pênaltis,ranking e méritos. Um erro inicial do harness PDO usava `choice` no Expresso; foi corrigido para a ação válida do próprio modo,sem mudar regras do jogo.

Mercado e renovação da fixture isolada estão bloqueados no Game,WorldSeason e apresentação: não há proposta que prometa calendário nacional inexistente. Nenhuma fórmula esportiva foi modificada.

## Candidaturas e matriz nacional — não somar execuções

O caminho RS limita a **duas indicações da liga**, conforme art.6,condicionadas à disponibilização CBF. Quota de referência estadual3 é mantida; o terceiro caminho não é preenchido pela liga por inferência. Excluem-se méritos nacionais comprovados. O contrato não homologa participantes reais2027.

**Evidência final `864001`:** RS64 com prova válida e12 estaduais com provas concluídas; Maranhão ficou pendente pelo empate da permanência. O snapshot completo foi persistido/recarregado no MySQL com mesmo ID descartável,sem sobrescrever carreira existente. `StateQualification` recusou gerar recibo enquanto o obrigatório estivesse pendente. **Candidaturas desta coorte não foram apuradas; o déficit estadual atual também não foi apurado.** Zero novos recibos nacionais,zero inscrições,zero renovações. Não interpretar campos técnicos0 com `candidacy_assessed=false` como ausência de mérito esportivo.

Os24 candidatos/40 vagas da Entrega58 são histórico de outro save; não foram carregados nesta contagem. Execuções intermediárias naturais chegaram a encerrar o universo ou pararam em MA; não foram combinadas nem usadas para substituir o resultado final persistido. Não houve alteração de placar,RNG ou busca em loop por homologação favorável; repetições ocorreram por mudanças nos testes de prova/exportação/persistência.

`rs/federations.csv` e `renewal-dependencies.json` separam: resultado comprovado;candidatura válida;inscrição autorizada;composição futura integral;renovação. Não há inscrição parcial. Preservados D96,C24 futura,CDB128/157 e **quatro residuais**. Permanecem pendentes classificatórias/seletivas e contratos/inscrições futuras de copas,obrigações Sul-Sudeste/regionais por clube e continentais não implementadas. Informação desconhecida não vira dispensa. Copa ES,pendências MA/AC/DF e política RN seguem intactas.

## Testes e evidências

Pasta `Docs/mundializacao-v07/expansao-estadual/lote3/rs/`.

|Suíte|Resultado e escopo|
|---|---|
|gaucho-v07|103 verificações;fronteiras sintéticas rotuladas,64 jogos,regras/provas/replay/adulteração.|
|gaucho-runtime-v07|433 verificações;Game Normal/Rápido/Expresso,64 por execução,9/11/11 partidas do personagem,XP por ledger real,JSON recarregado a cada comando.|
|gaucho-mysql-v07 --local-rollback|29 verificações;64 jogos/12 do personagem,Expresso,recarga por comando,crash depois do save antes do ledger,replay,nonce divergente,segunda conta,lock real1205 e rollback integral.|
|gaucho-cohort-v07|15 verificações na execução final;21 competições NPC,RS completo e pendência MA preservada. Não são21 competições inteiras no Game.|
|gaucho-proof-mysql-v07 --local-rollback|5 verificações finais;mesmo snapshot NPC no MySQL,prova RS,recibo bloqueado,adulteração recusada e rollback.|
|gaucho-http-v07 --local-fixtures|28 verificações;PHP loopback,login descartável,CSRF,isolamento,duploPOST e token divergente,ramos quartas/permanência e HTML completo. Fixture removida.|
|gaucho-mobile-v07.cjs|48 verificações,15 capturas320/390/1280,teclado/Escape,reduced-motion,sem overflow/erros JS. Ramos320 inspecionados visualmente.|
|bin/test-narrative.py|24 suítes passaram;1.014 cenas,nove atributos,probabilidades/XP e mecânicas protegidas.|
|Rechecks|AC51,MA150,DF45,pós-jogo215(seis posições/três modos),conquistas96(catálogo50/1.000R),feedback/v0.6.1 32.|

Conquistas desligadas nas fixtures PDO/HTTP: não alegar concessão de R nessas execuções. Seis posições são cobertura do pós-jogo,não seis temporadas RS completas. HTTP não testa identidade central real,IIS,Apache,HTTPS ou produção. Chrome sobre HTML autenticado capturado não equivale a aparelho físico/leitor de tela. Capturas usam atleta fictício; HTML,CSRF,senhas,PDFs e snapshots privados permanecem ignorados em storage.

## Métricas

Game isolado: Normal99 comandos,p951,92ms,pior33,98ms,pico146775bytes;Rápido13,p9515,57ms,pico180589;Expresso2,p9548,69ms,pico338519. Os placares naturais variam por execução;não são temporadas consecutivas.

PDO Expresso:3 comandos medidos,200 consultas incluindo harness,crash/retry/lock e verificações;p50143,38ms,p95/p99/pior174,72ms,total2352,14ms,snapshot final/pico375822bytes,memória30MiB. A primeira ação de retry é validada fora da medição de comandos naturais. Valores locais,não UOL. Não comparar RS64 isolado com o mundo nacional anterior como se fosse otimização. Não há medição de crescimento entre temporadas ou reestruturação v0.8.

## Reprodução, recuperação e Git

PHP8.4: `-n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring -d memory_limit=768M`,mais `-d extension=pdo_mysql` para MySQL/HTTP. Executar scripts da tabela,com as flags explícitas indicadas. `gaucho-cohort` antes de `gaucho-proof-mysql`;este aceita tanto encerramento quanto pendência,sem exigir resultado favorável. Não executar HTTP e PDO simultaneamente. HTTP antes de mobile. Exportador: `python -X utf8 bin/audit-gaucho-v07.py`. Regressão: `V06_EVIDENCE_DIR=Docs/mundializacao-v07/expansao-estadual/lote3/rs/regressao`,depois `python -X utf8 bin/test-narrative.py`.

Nenhuma DDL/migration,conversão de save real ou alteração em Hub/rh,credenciais,workflows,serviços,IIS/Apache,UOL/produção. Gravações PDO revertidas e HTTP removidas. Flags públicas permanecem desligadas. Rollback não destrutivo: retornar à base para runtime público,mantendo leitor v8 para snapshots locais novos; não restaurar dump sobre progresso posterior. Os contratos antigos continuam legíveis e congelados. V0.8 não iniciada.

Commits separados por implementação,testes e evidências;push somente `codex/mundializacao-v07`,sem force/merge/deploy. Workflows main-only/manual verificados antes do push. SHA remoto será confirmado no resumo da entrega.

Commits de implementação e testes: `1da6c71` e `a575c42`; evidências em commit separado. O SHA remoto final é informado no resumo após conferência.
