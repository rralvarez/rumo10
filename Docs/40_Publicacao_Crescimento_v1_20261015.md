# Publicação coordenada do crescimento v1.0 — registro de execução

Data: 15/10/2026. **Publicado e liberado ao público às 18h59, horário de Brasília.**
Este registro complementa o plano histórico do documento 39. O proprietário aprovou
explicitamente a sequência e o início da manutenção antes do SQL e da troca dos arquivos.

## Versões efetivamente enviadas

| Projeto | Commit do código publicado | Workflow com sucesso |
|---|---|---|
| Hub | `3b11f285dac1f80870054a115277a4db2c4c45e7` | [38791552914](https://github.com/rralvarez/rumohub/actions/runs/38791552914) |
| Jogo | `c3d19ca7f3e627ace22c03239249ab0c25153741` | [38791798000](https://github.com/rralvarez/rumo10/actions/runs/38791798000) |

Publicação Hub → jogo, FTPS com TLS validado, sem SQL automático. Manifestos instalados:
2.371 arquivos no Hub e 136 no jogo. Treze fontes críticas foram lidas por FTPS e comparadas
com os objetos Git dos commits acima e com seus manifestos. Comparação dos 35 arquivos de
referência JSON/escudos presentes nos manifestos anteriores: nenhuma alteração.

A primeira execução do Hub enviou o código, mas falhou no smoke: a página de manutenção
respondeu 503 também aos caminhos bloqueados, por encaminhamento do tratamento de erro Apache.
O teste foi corrigido nos dois projetos para aceitar, **somente em manutenção**, 503 cujo corpo
seja exatamente a mensagem da ponte. O teste normal continua exigindo 403/404 nesses caminhos.
Não houve afrouxamento da autenticação nem exposição dos arquivos.

## Banco e recuperação disponível

Banco físico confirmado na configuração e no dump: `rumoacame144a99e_rumohub`.
Backup final feito pelo cPanel com os dois sites em manutenção:

- Arquivo privado `production-database-final-before-v1.sql.gz`, 8.329.648 bytes.
- SHA-256 `26caccbcac336ea7afb276f0b580eab1d50713aacea4ccfa91a48551ae996607`.
- 46 tabelas anteriores; 151.001 registros; 2 usuários Hub, 2 contas do jogo,
  3 carreiras, 2 entradas de arquivamento e nenhum resumo aposentado.
- gzip/CRC e conteúdo conferidos integralmente; as linhas das tabelas coincidiam com o primeiro backup.
- Backup dos arquivos: 4.445 membros, 112.328.621 bytes comprimidos, SHA-256
  `2da34627452bce6eaef2b8869d68333929bdebbd31f1cdbf347b22ff403d8db3`.
- Não foi realizado ensaio de restauração integral do dump.

As cópias e recibos estão exclusivamente em
`C:\inetpub\wwwroot\rumo10\storage\private\production-v1-preparation`, fora do Git e da raiz pública.

O proprietário confirmou no phpMyAdmin a aplicação de
`rumohub/database/production/atualizacao-20261015-crescimento.sql`: 17 comandos sem erros,
3 origens de vagas, 2 primeiras carreiras legadas e 1 marcador de migration inseridos.
Nenhum benefício pago ou cortesia foi concedido pela migration (zero linhas nesse backfill).
Uma tentativa anterior fora de um banco selecionado retornou 1046 e foi interrompida.
Não houve reimportação da carga inicial. Esperam-se 58 tabelas; a contagem final não foi
consultada diretamente pelo agente, pois o MySQL remoto recusou conexão.

## Configuração e integridade

`HUB_BENEFITS_ENABLED=1` no Hub e depois `RUMOHUB_BENEFITS=1` no jogo.
Configurações anteriores foram comparadas byte a byte antes da inclusão dessas flags;
demais valores preservados. Nenhuma credencial foi adicionada ao Git.

Não se alteraram catálogo, cidades, ratings, imagens, planos globais, senhas ou contas reais.
Nenhum administrador adicional foi criado. Nenhuma cortesia foi concedida a Raphael.
O proprietário confirmou que suas carreiras continuam acessíveis e que jogou após a atualização;
essas novas gravações legítimas devem ser preservadas em qualquer recuperação.

## Testes reais no Apache/HTTPS até este registro

- Cadastro e login de contas exclusivamente descartáveis com identidade central do Hub.
- Duas vagas-base, terceira bloqueada sem crédito e teto gratuito de cinco.
- Link único, botão WhatsApp e cópia sem envio automático.
- Cadastro isolado sem crédito; primeira carreira persistida qualifica indicação.
- Duas sessões concorrentes: uma única criação extra aprovada para o único crédito.
- Reenvio idempotente sem duplicar carreira; exclusão da fixture sem restituir crédito.
- Créditos acumulados preservados quando a criação é barrada pelo teto.
- Partida Normal completa, placar consistente com os totais individuais e CTA da classificação.
- Modo Rápido: uma partida, oito decisões internas e oito registros de auditoria, avanço à rodada 2.
- Modo Expresso: diversas temporadas e roletas pelo fluxo público, sem editar idade no banco.
- Foram concluídas 17 temporadas; aposentadoria voluntária aos 35 anos, no começo da temporada 18.
  Resumo compacto e Sala de Troféus aposentada acessíveis. Crédito não foi devolvido;
  uma nova extra consumiu somente o saldo disponível e a tentativa seguinte foi bloqueada.
- Cortesia concedida e revogada pelo administrador titular na fixture, com motivo e auditoria.
  Somente Rumo10 selecionado; o titular confirmou os demais jogos sem benefício pago vigente.
  Dez carreiras criadas, décima primeira bloqueada. Revogação reconhecida pela API preservou
  as dez carreiras e bloqueou nova criação. Nenhuma concessão nominal a uma pessoa real.
- Sala de Troféus ativa e perfil acessíveis; ritmo fixo sem opção de troca.
- Chrome headless contra Apache: 19 verificações de viewport, menu, scroll, CTA, foco com
  viewport reduzida e navegação GET sem mudar o save. Repetidas com clube/classificação completa.
  Não equivale a teste em aparelho físico, teclado físico ou leitor de tela.
- GET dos caminhos `.env`, `.git/config`, `storage/private/clients.php`, `app/bootstrap.php`,
  `README.md` e escudo inexistente: 404 nos dois sites com a exceção de QA ativa.
- API de catálogo do Hub sem autenticação: 401. Cadastro, benefícios e indicação demonstram
  comunicação autenticada jogo → Hub. HTTPS validado sem desabilitar verificação TLS.
- Cookie `rumo10_session`: Secure, HttpOnly, SameSite=Lax. CSRF inválido rejeitado com 403,
  com estado da fixture idêntico antes/depois. Escudo binário via proxy autenticado retornou 200.

O ledger privado registra 208 verificações aprovadas, além das 19 verificações mobile,
do roteiro Rápido e das verificações de segurança. Esses números incluem comandos/asserções
repetidos e não representam 208 cenários independentes.

Uma falha do próprio roteiro de QA tentou restaurar a carreira já selecionada (rota aceita
somente arquivadas); o roteiro foi corrigido e retomado, sem alteração na aplicação.
Após uma pausa, a lista de QA expirou e o IP mudou. O proprietário autorizou explicitamente
consultar o IP atual; liberação temporária renovada somente para QA e o servidor do jogo.

## Liberação e dados descartáveis

Após os testes, removidos os dois `maintenance.lock` e os dois arquivos temporários de IPs.
`UOL_DEPLOY_HOLD=false` nos dois repositórios; publicação FTPS continua habilitada.
Conferência pública sem exceção de QA: **25 GETs aprovados**, incluindo domínio com/sem www,
Hub, logins, caminhos privados 404 e API não assinada 401, com TLS validado.

Na limpeza final, 16 carreiras descartáveis ativas foram excluídas pelas telas normais com
verificação de proprietário; outra carreira descartável já fora excluída no teste de crédito.
Nenhuma exclusão SQL ou exclusão de conta real. Ficaram **6 contas sintéticas de QA**, identificadas
no recibo privado, uma cortesia de teste revogada e **1 resumo aposentado de teste** com sua auditoria.
Não existe fluxo público seguro para excluir essas contas; não se executou SQL amplo para limpá-las.

Pendências: confirmação de login pelo segundo titular real, identificação expressa da conta de
Raphael antes de qualquer cortesia, contagem final das tabelas pelo cPanel e teste em celular físico.
A aposentadoria compulsória aos 40 e as simulações estatísticas amplas foram verificadas localmente
na etapa anterior; **não foram repetidas até os 40 nesta execução Apache**. O teste natural Apache
encerrou voluntariamente aos 35. Não há pagamento implementado.

Não executar novamente o SQL, conceder cortesia nominal ou restaurar o dump para cumprir testes.
Não importar fixtures locais. Contas descartáveis sem fluxo público de exclusão serão identificadas
em recibo privado; resumos aposentados, inclusive os descartáveis, não serão apagados.

## Recuperação e cPanel

Requisitos vigentes: Apache com mod_rewrite/AllowOverride, PHP >=8.2 com pdo_mysql/mbstring/curl,
MySQL 8/InnoDB/utf8mb4 e HTTPS. IIS local permanece com `web.config`; produção usa `.htaccess`
e somente o conteúdo público nas raízes dos domínios. Aplicações privadas ficam em `apps/`.

Em falha relevante, manter/recolocar `maintenance.lock` nos dois projetos e pausar Actions.
Durante transferência, `deployment.lock` tem prioridade inclusive sobre o acesso de QA.
Não remover tabelas aditivas ou ledgers. Preservar o estado atual antes de qualquer intervenção.
Depois de novos créditos/carreiras ou partidas legítimas, preferir correção adiante mantendo a
autoridade do Hub. **Não restaurar o snapshot pré-migration sobre o banco atual.**

Rollback de código exige versão compatível com benefícios já usados e restauração coordenada
dos arquivos/configurações; a ponte antiga precisa de `deployment.lock` para manter o bloqueio.
Não sobrescrever sessões, uploads, mídias ou saves atuais. Restaurar dados integrais é recuperação
de desastre, requer nova autorização e avaliação das gravações posteriores ao backup.
