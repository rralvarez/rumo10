# Produção v1.0 — backup e plano para aprovação

15/10/2026. Este documento apresenta a preparação autorizada; **não registra publicação concluída**.
O proprietário pediu os resultados do backup e a sequência exata antes da migration ou substituição
dos arquivos da hospedagem. Nenhum push, maintenance lock, SQL ou atualização de .env foi aplicado.

## Estado confirmado

- Remotos main: jogo `45b0703e0cf53aaa0c3e06d9b6b60606ca48f00f`, Hub
  `d2407a6429c7e2fd6fe5874a71670bdf59a0779d`. Últimos workflows FTPS dessas versões tiveram sucesso;
  nenhuma execução estava ativa na conferência. Deploy automático está habilitado: **não dar push antes do gate**.
- Código local preparado: jogo `48bc10f`, Hub `90715a6`, na branch `codex/correcoes-crescimento-v1`.
  Incluem v1.0 e a proteção de manutenção coordenada descrita abaixo. Commits documentais posteriores
  não alteram o pacote executável. IIS local continua separado da produção.
- Ambos os .env remotos identificam `rumoacame144a99e_rumohub`. Flags novas de benefício estão desligadas.
- HTTPS válido e HTTP 200 no login público do jogo e na home do Hub. Esses GETs validam a versão
  antiga ainda instalada, não as regras novas. Não foi iniciada manutenção.
- MySQL remoto recusou esta origem com **1130**. Não foi liberado host/coringa, nem desativada verificação TLS.
  O proprietário baixou o backup pelo cPanel. Importação será pelo phpMyAdmin; não há shell remoto necessário.

## Backups concluídos

Pasta privada local, com ACL restrita ao usuário Windows:
`C:\inetpub\wwwroot\rumo10\storage\private\production-v1-preparation\`.
Não está versionada nem será enviada no deploy. Contém configurações e dados privados: não publicar.

| Cópia | Resultado |
|---|---|
| `production-database-before-v1.sql.gz` | 8.329.648 bytes; exportado pelo cPanel e conferido localmente |
| Banco identificado no dump | `rumoacame144a99e_rumohub`, versão informada 8.0.46-cll-lve |
| Conteúdo | 46 tabelas, 151.001 registros, 82 INSERTs agrupados; somente rh_* e rumo10_* |
| Contas e carreiras | 2 usuários Hub, 1 administrador, 2 contas do jogo, 3 carreiras, 2 entradas de arquivamento, 0 resumos aposentados |
| Integridade do banco | gzip/CRC lido integralmente; definições e quantidade de linhas em todos os INSERTs analisadas |
| `production-files-before-v1.tar.gz` | 112.328.621 bytes; 4.445 arquivos, 136.300.788 bytes antes da compressão |
| Conteúdo dos arquivos | apps/rumohub, apps/rumo10, public_html e rumohub.rumoacamisa10.com.br |
| Integridade dos arquivos | todos os 4.445 membros do tar conferidos por SHA-256 e gzip/CRC; zero divergência de tamanho durante cópia |
| Manifestos instalados | 127/127 arquivos do jogo e 2.367/2.367 do Hub coincidem com os manifestos remotos |

SHA-256 do backup de banco:
`fb14e3a144280a2cb7067d78b2ba228fc123e60a053ba818589e2f79a8775c9c`.

SHA-256 do backup de arquivos:
`2da34627452bce6eaef2b8869d68333929bdebbd31f1cdbf347b22ff403d8db3`.

Recibos detalhados estão na mesma pasta privada. Não foi realizado ensaio de restauração desse dump;
a verificação acima é de integridade e conteúdo. O export foi feito enquanto o sistema estava disponível:
deve haver um **novo backup final sob manutenção** antes do SQL, para incluir eventuais gravações posteriores.

## SQL incremental a aplicar

Arquivo do Hub: `database/production/atualizacao-20261015-crescimento.sql`.

SHA-256 dos bytes do arquivo:
`3e435b6f234a736233c8f169902bc2d99088baa0b92b0de417a5d65bc84e6cc0`.

- 17 comandos: CREATE TABLE IF NOT EXISTS e INSERT IGNORE.
- 12 novas tabelas; esperado passar de 46 para **58 tabelas**, se não houver outra alteração concorrente.
- Nenhuma das 12 tabelas novas existe no backup atual: pacote ainda não aplicado nesse snapshot.
- Sem DROP, DELETE, TRUNCATE, ALTER destrutivo, UPDATE amplo, USE ou CREATE DATABASE.
- Sem carga inicial, senhas, dados locais de teste, novos clubes/jogadores/ratings ou concessão nominal de cortesia.
- Acesso anterior específico do Rumo10 vira **legado**, não compra. Nenhuma licença automática de técnico/agente.
- O marcador de migration impede promoção posterior do cache local pago após revogação.
- DDL MySQL tem commits implícitos: erro parcial exige parar e conferir, não fingir rollback transacional completo.

## Preparação do deploy testada localmente

O deploy antigo removia sua própria manutenção ao terminar cada repositório. Preparada uma proteção
separada, `apps/<projeto>/maintenance.lock`, que **nenhum dos dois workflows remove**.
`deployment.lock` continua bloqueando todo acesso durante a transferência, inclusive QA.

Depois de ambos os pacotes concluídos, um arquivo privado temporário `maintenance-allow.json` permite
QA somente por endereços IP exatos e por prazo limitado. Usa REMOTE_ADDR, sem confiar em X-Forwarded-For.
O IP de saída do PHP do jogo também precisa estar na lista para testar sua API assinada no Hub.
Isso apenas permite alcançar as telas durante a manutenção: login, CSRF, HMAC, escopos e controle
de proprietário continuam obrigatórios. Lista ausente, inválida ou expirada mantém HTTP 503.
Não há coringa, mudança de senha, novo administrador ou afrouxamento da autenticação.

Variável de Actions `UOL_DEPLOY_HOLD=true` faz o smoke externo exigir manutenção (503), em vez
de considerar isso uma falha e incentivar liberação prematura. O marcador real é o arquivo privado;
a variável sozinha não ativa manutenção.

Passaram **11 testes de deploy por repositório**, incluindo preservação do bloqueio do operador,
expiração, IP diferente e prioridade absoluta do bloqueio de transferência. Testes são locais, com FTP
simulado e PHP CLI: não representam ensaio real Apache dessa nova ponte.
Pacotes locais passaram a verificação de manifesto, hashes, exclusão de credenciais e dry-run:
jogo 136 arquivos (150 transferências planejadas), Hub 2.371 arquivos (2.373 transferências).
O workflow reconstruirá o pacote a partir do commit; arquivos internos de schema não são executados
automaticamente. O SQL manual de produção e o dump inicial ficam fora do pacote de deploy.

## Execução proposta depois da confirmação

1. Conferir novamente HEADs remotos, execuções de Actions, HTTPS e ausência de alteração concorrente.
   Se divergirem, parar para revisão. Manter estes backups privados disponíveis.
2. Configurar UOL_DEPLOY_HOLD=true nos dois repos. Criar maintenance.lock e deployment.lock em ambos
   os diretórios apps. Confirmar 503 nas duas telas de login e aguardar as requisições antigas terminarem.
   Nenhum arquivo da aplicação será substituído para iniciar esse bloqueio: a ponte atual já lê deployment.lock.
3. Com manutenção confirmada, proprietário baixa novo backup do banco pelo cPanel. Conferir esse
   arquivo e guardar como ponto final de recuperação. Não importar até a conferência.
4. No phpMyAdmin, selecionar exatamente rumoacame144a99e_rumohub e importar **só** o SQL incremental.
   Proprietário informa o resultado completo; conferir 58 tabelas e ausência de erro. Se houver erro, parar
   mantendo ambos os sites bloqueados. Não conceder cortesia nominal nem reimportar a carga inicial.
5. Publicar primeiro o Hub por fast-forward de main/Actions, mantendo o bloqueio do operador.
   Aguardar workflow/manifeste íntegros e manter 503 para o público; depois publicar o jogo da mesma forma.
   Preservar .env, clients.php, sessões, uploads/mídia, segredos e blocos PHP do cPanel.
6. Em configuração privada, ativar HUB_BENEFITS_ENABLED=1 no Hub, depois RUMOHUB_BENEFITS=1 no jogo.
   Não alterar demais configurações, senhas, IPs autorizados da API ou permissões globais de usuários.
7. Liberar somente QA temporário, verificar que o público externo continua recebendo 503 e testar
   Apache/HTTPS/autenticação/API. A lista temporária expira; nenhum dado pessoal entra no Git.
8. Executar os fluxos abaixo com contas descartáveis criadas na própria produção. Não importar saves
   locais nem editar/aposentar/excluir as carreiras reais. Confirmar os logins reais com seus titulares,
   sem solicitar senhas pelo chat ou redefinir credenciais.
9. Se os testes essenciais passarem, retirar bloqueios dos dois sites de forma coordenada e conferir
   smoke externo normal. Retirar a lista QA e voltar UOL_DEPLOY_HOLD=false. Se houver falha relevante,
   conservar manutenção e apresentar o problema antes de prosseguir.
10. Registrar commits dos workflows efetivamente instalados, manifestos, resultado do SQL e testes.
    Cortesia da conta real permanece pendente de seleção/identificação expressamente confirmada.

## Interação necessária no cPanel

**Não executar a importação agora.** Após minha confirmação de que ambos os sites estão em manutenção:

1. cPanel → Backup/Cópia de segurança → Baixar backup MySQL → rumoacame144a99e_rumohub.
   Salvar o novo arquivo em Downloads e informar o nome; será conferido antes da importação.
2. Depois da conferência: cPanel → phpMyAdmin → selecionar o banco **rumoacame144a99e_rumohub** →
   Importar → escolher o arquivo incremental localizado em
   `C:\inetpub\wwwroot\rumohub\database\production\atualizacao-20261015-crescimento.sql` → Executar.
3. Informar a mensagem final, inclusive eventuais erros; não repetir carga inicial ou SQL de administrador.
   O phpMyAdmin usa o acesso administrativo do painel; não ampliar privilégios do usuário runtime por antecipação.

## Matriz de testes pós-publicação

| Grupo | Verificação e limite |
|---|---|
| Contas reais | Titulares confirmam login; baseline do banco e carreiras deve permanecer preservado, sem comandos de jogo nos saves reais |
| Navegação | Login, classificação/retorno GET, etapa preservada e Sala de Troféus (inclusive vazia) com fixture |
| Evolução/ciclo | Partida e XP; carreiras descartáveis em Expresso para atingir aposentadoria voluntária/obrigatória pelo fluxo normal, em série para respeitar o limite da hospedagem |
| Mercado | Fechamento de temporada, roleta persistida, aceitar/ficar, níveis/países, distribuição ou limitação de elegibilidade explícita |
| Gratuito/legado | Terceira carreira exige crédito em conta nova; as três carreiras antigas permanecem; arquivadas contam e resumos compactos não |
| Indicação | Link, cadastro isolado sem crédito, primeira carreira persistida concede um, replay e duas sessões não duplicam |
| Consumo | Extra vinculada ao crédito, aposentadoria/exclusão da fixture não reembolsa; nova extra exige novo crédito |
| Cortesias | Administrador titular confirma concessão/revogação apenas na fixture; Rumo10 não libera outro produto; nenhuma concessão à conta real não confirmada |
| Integração | API HMAC/IP/escopos e sessão por usuário; rejeição sem assinatura, assets/escudos, HTTPS e arquivos internos protegidos |

Não haverá fixtures importadas do computador nem endpoint público para alterar idade/resultado.
Se uma simulação atingir timeout/memória/limite da UOL, parar e registrar a incompatibilidade em vez
de aumentar permissões ou usar um teste local como se fosse Apache. Identificadores e credenciais
das fixtures ficam em recibo privado. Limpeza limita-se aos dados comprovadamente criados pelo teste,
pelos fluxos de exclusão/encerramento; nunca excluir usuários ou históricos preexistentes.
Resumos aposentados das próprias fixtures também serão preservados e identificados no recibo de QA.
Se não houver fluxo seguro para remover a conta descartável, mantê-la identificada em vez de executar SQL amplo.

## Recuperação

- Falha no SQL/publicação: não abrir o público, não apagar tabelas ou históricos. Conferir recibo e corrigir
  a operação aditiva ou interromper para decisão do proprietário.
- Antes de uso legítimo dos novos créditos/benefícios: restauração coordenada dos arquivos de código,
  assets/ponte/.htaccess e configuração anterior dos backups; não sobrescrever sessões/uploads atuais.
  Manter deployment.lock enquanto a ponte antiga estiver em uso, pois ela não conhece maintenance.lock.
  Conservar tabelas novas, sem DROP/TRUNCATE e sem reimportar o dump sobre a base em uso.
- Depois de direitos novos em uso: preservar Hub/ledger como autoridade e corrigir adiante sob manutenção;
  não voltar silenciosamente ao plano global antigo. Rollback de dados completo só em incidente,
  com nova autorização explícita e avaliação das gravações posteriores ao backup.

Estado desta entrega: backups e pacotes preparados; publicação, SQL, flags e testes do pacote novo em
Apache **ainda pendentes do gate do proprietário**. O funcionamento do IIS não foi alterado.
