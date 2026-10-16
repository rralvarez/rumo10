# Acabamento, conquistas e feedback — v0.6.1

Base: 2534b53, branch codex/expansao-narrativa-v1. Implementação local; publicação de produção autorizada posteriormente pelo proprietário, ainda pendente de backup atual e instalação coordenada.

## Implementação

Reconciliação por conta autenticada e uma carreira por requisição, com checkpoint de versão, transação e evidência estruturada. Não combina gols de carreiras diferentes. Dry-run disponível em bin/reconcile-achievements.php --account=ID_LOCAL --dry-run; --apply somente em ambiente local. Não usa textos de rádio como prova, não concede XP histórico nem ativa objetivos futuros. Dados de partidas divergentes são descartados conservadoramente. Histórico compacto sem detalhes de jogadas, temporadas conflitantes e eventos antigos sem autoria não permitem recuperar todos os feitos. A data desconhecida não é inventada: usa “Reconhecida em”.

Catálogo preservado: 50 conquistas, 1.000 R; 33 ativas, 620 R; 17 futuras, 380 R. Matriz por objetivo: pos-jogo-v061/auditoria-33.csv. Nome público alterado de G para R; campos internos pontos_g preservados para compatibilidade. Datas exibidas em America/Sao_Paulo; persistência UTC.

Fixtures: sete reconhecimentos na primeira carreira, um adicional na segunda, oito únicos/85 R; repetição e isolamento testados. Acesso autenticado autorizado no IIS reconheceu seis conquistas/55 R em uma conta: apenas contagens agregadas em reconhecimento-local-agregado.json. Não são contagens globais atuais de produção.

Correção necessária encontrada no IIS: o parser .env recusava nomes contendo números, ignorando RUMO10_*. Corrigido exclusivamente o identificador permitido, com teste estrito contra a base. Isso explica a mensagem de persistência indisponível apresentada inicialmente ao titular.

Cards com arte vetorial original, categorias recolhíveis, filtros e informação inequívoca de 620/1.000 R. Toasts em fila, aproximadamente cinco segundos, pausam no foco/hover; históricos usam aviso consolidado. Falha de transporte depois da confirmação pode perder o toast, mas não a conquista. Redução de movimento e foco preservados. Nova marca/favicon, menu com ícones e “Mais” no celular, lista de carreiras com modo persistido, perfil com hierarquia renovada. Saves anteriores sem play_mode seguem o padrão Normal do motor; nenhuma troca de modo foi introduzida.

Feedback autenticado com nota 1–5, categoria opcional, comentário até 1.000 caracteres, CSRF, consultas parametrizadas, chave idempotente e limite de cinco novos envios/24 h por conta. Identidade obtida da sessão/mapeamento próprio. Sem IP, anexo, e-mail adicional ou recompensa. Não existe role administrativa confiável no Rumo10: nenhum endpoint administrativo foi criado. Consulta futura somente por operador autorizado no banco, com saída escapada se virar interface. Checkbox de contato omitido porque não existe rotina de contato.

## Schema, backup e recuperação

Migration 007 adiciona apenas rumo10_conquistas_reconciliacoes, rumo10_conquistas_fontes e rumo10_feedback. Instalada localmente após backup de 34 tabelas rumo10_*: 61.403.902 bytes, SHA256 9ad0f8c553240e4b23deb8032b7a24b37fbe8f0ba9e8e50e92ce7219bc19236e. Dump e recibo privados em storage/private/growth-v061, ignorados pelo Git. Restauração integral não ensaiada. Nenhuma tabela RH foi alterada.

Flags locais: RUMO10_ACHIEVEMENTS=1, RUMO10_RECONCILIATION=1 e RUMO10_FEEDBACK=1. Novas flags no exemplo ficam desligadas. Ausência de tabela desabilita recurso com aviso, sem executar DDL em GET.

Rollback não destrutivo: desligar reconciliação/feedback, manter tabelas, conquistas e feedback existentes. Manter a correção do parser para as flags antigas continuarem legíveis. Não restaurar dump por cima de partidas posteriores; restauração é procedimento separado de recuperação. Em produção, não executar backfill global implicitamente.

## Evidências e reprodução

24 suítes de regressão narrativa/motor concluídas na etapa principal; evidências em pos-jogo-v061/regressao. Fórmulas permanecem iguais. Após alterações posteriores: 32 verificações puras, quatro do bootstrap real, 215 pós-jogo, 96 de conquistas, 11 fronteiras v0.6 e 97 arquivos de fronteira mecânica. MySQL: 25 verificações com rollback integral. HTTP local: 17 verificações com fixtures descartáveis; processo PHP isolado, não login central IIS. Chrome headless: 46 verificações em 320/390/1280 px, sem erros JS ou overflow nos fluxos cobertos. Evidências visuais usam fixtures; não equivalem a leitor de tela humano ou aparelho físico. O perfil recebeu alterações posteriores e não está coberto por essas capturas de conquistas/feedback.

Com PHP 8.4 e mbstring: tests/growth-v061.php, tests/bootstrap-v061.php. Com pdo_mysql local: tests/growth-mysql-v061.php --local-rollback e tests/http-v061.php --local-fixtures. Testes recusam uso produtivo. Chrome/Playwright: node tests/mobile-v061.cjs. Não executar testes com fixtures no banco produtivo.

Acessos locais: http://rumo10.local/conquistas, /feedback, /carreiras, /jogador. Reconciliação limitada às carreiras do titular; havendo pendências, usar o botão para continuar análise. Feedback não muda XP/R/benefícios.

## Publicação autorizada em 16/10/2026

Somente Rumo10; Hub preservado. Workflows automáticos publicam main, não a branch de trabalho. Plano: backup atual validado, manutenção do jogo, backup final/arquivos e configuração, conferir schema produtivo, importar somente SQL aditivo necessário, publicar release verificável, ativar flags explicitamente, smoke Apache/HTTPS e retirar manutenção. Não publicar versão parcial nem carregar snapshots locais. SQL combinado aditivo: database/production/atualizacao-20261016-v061.sql (006 + 007; sem dados pessoais). Nome físico de produção: rumoacame144a99e_rumohub.

Situação nesta revisão: ainda sem deploy, sem migration de produção e sem alteração do Hub. Autorização recebida não substitui validação do backup. Push não é deploy. O resultado efetivo de produção será registrado separadamente após execução.

Próxima fase: apenas inventário em pos-jogo-v061/proxima-fase-inventario.md. Mundialização e 23 vínculos seguem bloqueados.

## Preparação efetiva da produção

Backup fornecido em 16/10/2026 validado: 7.896.050 bytes comprimidos, 59.422.449 bytes expandidos; 58 tabelas (27 rh_*, 31 rumo10_*). SHA256 f207fddcd618cb5ab581139e0948d514cc0bb42865340ca2507fa8a587db2626. Nenhuma das seis tabelas de conquistas/feedback presente nesse dump. Banco físico conferido na configuração remota: rumoacame144a99e_rumohub.

Backup FTPS de 150 arquivos, incluindo configuração privada e bridge, com todos os 136 hashes do manifesto instalado conferidos. Arquivo privado de 796.004 bytes, SHA256 98ee4107c44b33c9a440a479b4cf04c148f5797dcd83e63502d07d2838c2e73f. Recibos e cópias somente em storage/private/production-v061, fora do Git.

Rumo10 colocado em manutenção e HTTP 503 confirmado; Hub não alterado. Importação incremental solicitada pelo phpMyAdmin; aguardando confirmação. Ajustado deploy FTPS para copiar também os dois favicons à raiz pública. Onze testes simulados: dez passaram, um dependente de ambiente foi ignorado. Nenhuma transferência de release executada nesta etapa.
