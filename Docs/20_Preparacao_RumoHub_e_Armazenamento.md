# Preparação do RumoHub e limites de armazenamento

## Primeiro passo implementado localmente

Em 14/10/2026, adicionados os limites de **3 carreiras para gratuito e 10 para pago**, contando a selecionada e todas as arquivadas. A listagem em `/carreiras` permite selecionar uma das existentes, criar outra se houver vaga ou confirmar a exclusão permanente em `/carreira/excluir?career=ID`.

O servidor verifica o limite dentro da transação e bloqueia a conta durante a criação. O plano não é recebido do formulário: `rumo10_conta_planos` guarda a atribuição administrativa; ausência de registro significa gratuito. Não há checkout, cobrança ou habilitação automática de plano pago nesta entrega. Um futuro serviço de assinatura deve atualizar essa atribuição após verificar o pagamento e seu período de vigência; não confiar em uma seleção feita pelo cliente. A redução do plano preserva as carreiras, mas bloqueia criação enquanto o uso exceder o limite.

Contas já acima do limite não tiveram carreiras apagadas. A migração 003 apenas cria a tabela de planos e registra a versão. Para uma instalação existente:

```powershell
& C:\PHP\php-8.4.26\php.exe -n -d extension_dir=C:\PHP\php-8.4.26\ext -d extension=pdo_mysql bin/install-career-limits.php
```

A exclusão exige sessão, CSRF e confirmação explícita. A propriedade é conferida novamente sob lock. Remove somente a carreira indicada: universo JSON, arquivo, atributos, estados, decisões, transferências e as dependências de partidas/temporadas do armazenamento legado. Contas, catálogo de clubes/jogadores, escudos e outras carreiras permanecem. Repetir a exclusão informa que a carreira não foi encontrada. Nenhuma exclusão foi feita nas carreiras do usuário durante instalação/testes.

O conceito de limite será inicialmente aplicado ao Rumo à Camisa 10, único jogo existente. Antes de conectar os próximos jogos, definir se a assinatura concede 3/10 vagas por jogo ou um orçamento conjunto da plataforma. Não criar outros jogos para contornar o limite.

## Próximos passos, ainda não implementados

1. **Histórico compacto de aposentadoria:** produzir e validar totais por clube, temporada e competição, títulos e premiações; salvar resumo permanente no histórico da conta e, na mesma transação, remover partidas, eventos, decisões e universo detalhado. Um resumo encerrado não é retomável. Definir sua exclusão pelo usuário e liberação da vaga. Não apagar retroativamente detalhes existentes sem uma migração específica validada.
2. **Estatísticas normalizadas:** uma linha por universo/carreira, edição da competição, clube e jogador, contendo jogos, minutos, gols e assistências. Chave única para impedir duplicação e fechamento de partida idempotente. IDs internos estáveis; não repetir nomes/cores/escudos nem criar linhas vazias para todos os atletas. Persistir campeões e premiações por edição. O motor atual ainda não simula a autoria individual de gols dos NPCs.
3. **Calendário e renovação de elencos:** datas globais, aniversário/declínio individual e encerramentos locais de cada liga. Aposentar NPC somente naquele universo e criar substituto fictício com novo ID, nome, nascimento, perfil e potencial. Preservar o histórico do aposentado. O catálogo de referência não envelhece nem se modifica por uma carreira.
4. **RumoHub separado:** migrar o catálogo para `rumohub`/`rh_*`, preservando IDs, e disponibilizar API de servidor para servidor. Escolher domínio, hospedagem, acesso MySQL e estratégia de backups antes do corte. A proposta de tabelas está no documento 19; nenhuma renomeação de banco foi aplicada.
5. **Conectar os jogos:** credenciais separadas, permissões por jogo, rotação/revogação e restrição por IP quando os servidores tiverem saída fixa. HTTPS e autenticação obrigatórios; CORS, Referer e nome do domínio informado não são autenticação. Validar o endereço de origem conforme a configuração real de proxy, sem confiar arbitrariamente em X-Forwarded-For. Nenhuma chave de servidor no navegador, nenhuma conexão ao MySQL exposta pelos sites.

## Escudos e mídia

Escudos continuam locais em `public/assets/clubs`, com caminhos e fontes em `database/club-badges.json`. As imagens não são armazenadas como BLOB/Base64 no MySQL. Para os três sites, publicar uma origem comum de arquivos estáticos no Hub e guardar só caminho/URL e metadados no catálogo. Escudos públicos não dão acesso à API privada nem ao banco. O CSP atual do jogo autoriza imagens de origem local; a origem externa deverá ser adicionada explicitamente ao configurar o Hub, não com um wildcard geral.

Não haverá três cópias permanentes do catálogo ou das imagens. Os três jogos podem usar cache, com versão de catálogo e invalidação. Alterações de uma carreira não escrevem na referência mundial. Os dados exclusivos do jogo ficam em seus próprios universos; um servidor de jogo não deve ler ou alterar progresso de outro jogo.

Na passagem futura jogador→técnico, guardar apenas um registro de transição com experiência/reputação/conquistas relevantes. Não transportar todas as partidas nem criar automaticamente uma carreira de técnico sem escolha do usuário e verificação de vaga.

## Validação e limites desta etapa

`tests/career-slots.php` verifica 3/10 vagas, arquivadas, rejeição da carreira excedente, preservação da selecionada, propriedade, confirmação, exclusão e dependências, reposição, troca de carreira e redução de plano. Usa duas contas descartáveis e apaga somente os próprios fixtures.

Validação local: **22 verificações de vagas/propriedade/exclusão** passaram no MySQL e **70 verificações HTTP** passaram no IIS, incluindo confirmação e remoção do universo da carreira descartável. O teste HTTP usou cliente PHP com 256 MiB; configuração do IIS preservada. `tests/presentation.php`, lint dos arquivos PHP alterados e `git diff --check` passaram. Os testes foram corrigidos quando necessário e repetidos; não foram contados resultados de execuções malsucedidas.

A compactação de aposentadoria, estatísticas de NPCs, serviço remoto, pagamento e migração para `rumohub` continuam pendentes. Nesta primeira etapa, uma carreira aposentada ainda contém seu universo e conta no limite até a compactação ser implementada. Apache e hospedagens externas não foram acessados.
