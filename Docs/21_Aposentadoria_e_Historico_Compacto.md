# Aposentadoria e histórico compacto — 14/10/2026

## Comportamento implementado

Ao confirmar aposentadoria voluntária a partir dos 35 anos, ou ao chegar à aposentadoria obrigatória aos 40, o jogo grava um resumo separado e remove os detalhes da carreira na mesma transação. O personagem deixa de ocupar uma das 3/10 vagas, mas permanece no histórico privado do usuário.

O resumo guarda identidade, idade/motivo do encerramento, totais de jogos/minutos/gols/assistências, números por clube e temporada, posições e campeões das competições disputadas e títulos da equipe registrados pelo motor. Troféus nomeados, como MLS Cup ou Apertura/Clausura, não recebem uma segunda contagem genérica de campeão. Premiações individuais competitivas ainda não são simuladas: não foram inventadas nesta entrega.

Foram removidos: universo JSON, calendários/partidas, narrações/eventos de gols, estatísticas detalhadas por partida, escolhas/sorteios, atributos/XP, estados e propostas/transferências detalhadas. A linha básica da carreira permanece para identidade e propriedade; um vínculo de arquivo pode permanecer quando outra carreira é selecionada. Não há cópia de partidas ou do universo de NPCs dentro do resumo.

A tabela `rumo10_historico_encerrado` tem uma linha por carreira, associada ao proprietário e ao ID original, com resumo JSON pequeno. O registro preserva somente a chave/hash do comando final para idempotência: reenviar a confirmação não recria dados nem duplica estatísticas. A ordem de chaves normalizada pelo MySQL é tratada na leitura dos totais.

Um registro mínimo com nível técnico médio e quantidade de temporadas prepara uma futura conversão para técnico. Não cria automaticamente outra carreira e não constitui uma integração com o jogo de técnico, que ainda não existe.

## Carreiras aposentadas anteriores

A instalação cria a tabela, mas **não compacta nem apaga carreiras antigas automaticamente**. Em [Minhas carreiras](http://rumo10.local/carreiras), aposentadorias ainda detalhadas oferecem **Guardar resumo e liberar vaga**. A página explica a remoção permanente e exige confirmação explícita. Carreiras em atividade não podem usar essa ação; propriedade e identidade do snapshot são verificadas antes de qualquer remoção.

Para aposentadorias novas, a própria confirmação de aposentadoria avisa que detalhes serão apagados. A obrigatória mantém a regra de encerramento aos 40 e também gera o histórico compacto. Retirar-se durante uma partida continua bloqueado.

## Acesso e exclusão

Abra [Minhas carreiras](http://rumo10.local/carreiras) → **Histórico de carreiras encerradas**. O resumo também aparece ao consultar a carreira selecionada ou a ficha de um personagem já compactado. A URL `/carreira/resumo?career=ID` verifica o proprietário; conhecer o ID não permite ler o histórico de outra conta.

Um resumo não pode ser retomado. Criar/selecionar outra carreira não apaga o histórico. **Excluir também este resumo** abre a confirmação de exclusão permanente e apaga a identidade da carreira e seu resumo por chave estrangeira. Catálogo mundial, contas, escudos e outras carreiras permanecem intactos.

## Instalação

Banco permanece `ecohub`; apenas tabelas `rumo10_*` foram alteradas. Em uma instalação existente que já tem a migração 003:

```powershell
& C:\PHP\php-8.4.26\php.exe -n -d extension_dir=C:\PHP\php-8.4.26\ext -d extension=pdo_mysql bin/install-retirement-history.php
```

O instalador principal inclui essa migração para instalações novas. A migração 004 foi executada no MySQL local; nenhuma carreira pessoal foi compactada durante o desenvolvimento. O RumoHub, seu repositório, IIS e domínio não foram criados ou configurados por esta etapa.

O expurgo reduz dados lógicos e permite ao MySQL reutilizar espaço. Não promete reduzir imediatamente o tamanho físico dos arquivos do banco; não foi executado OPTIMIZE nem qualquer manutenção global das tabelas.

## Validação

`tests/retirement-storage.php` usa duas contas descartáveis e verifica preservação dos totais/clubes/títulos, liberação de vaga, remoção das dependências, replay, bloqueio de retomada, privacidade, confirmação do legado, compactação idempotente, exclusão do resumo e aposentadoria obrigatória. Também verifica que rollback deixa os detalhes originais e não cria resumo parcial.

Testes do ciclo, histórico por clube, limites 3/10 e apresentação foram executados. O teste HTTP no IIS verifica aposentadoria compacta, leitura do resumo/ficha, vaga liberada, universo apagado, rejeição de ID inválido, replay e exclusão. Cliente de teste com 256 MiB, configuração do IIS preservada. Sem testes em Apache ou hospedagem externa.

Resultados: **28 verificações de armazenamento/aposentadoria**, **76 HTTP no IIS**, 144 de ciclo, 22 de vagas e 9 de histórico passaram. Apresentação, lint PHP, integridade do catálogo compartilhado no MySQL e `git diff --check` também passaram.

Ainda pendentes: estatísticas e autoria individual de gols dos NPCs, premiações individuais, tabelas agregadas normalizadas por jogador/clube/edição para carreiras em andamento, sincronização do calendário mundial e Hub remoto.
