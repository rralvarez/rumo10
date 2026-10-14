# Administração do Hub e expansão do catálogo — 14/10/2026

## Acesso local

- Jogos: http://rumohub.local/ — nome, logo e link público. Técnico e agente são projetos futuros com nomes provisórios.
- Administração: http://rumohub.local/admin/login. O primeiro administrador escolhe seu próprio e-mail e senha pelo link privado criado por `rumohub/bin/prepare-admin.php`, válido por 24 horas e uma utilização. O arquivo local fica em `C:\inetpub\wwwroot\rumohub\storage\private\configurar-admin.html`; não publicar esse arquivo.
- Jogo: http://rumo10.local/. Elenco em `/elenco`; personagem e totais em `/jogador`; carreiras em `/carreiras`.

O painel permite buscar e editar clubes, três cores HEX, cidade, coordenadas, overall e escudo; jogadores, posição, nascimento, nacionalidade e vínculo corrente; seleções e emblemas; jogos, logos e links. Identificadores permanecem estáveis. Alterações manuais são guardadas separadamente e reaplicadas depois das importações. Ratings originais EA podem ser consultados sem alteração na aba Catálogo EA. Não há senha padrão nem credencial de administrador no repositório.

## Dados publicados nesta instalação

O Hub contém 2.160 clubes, 154 competições/circuitos e 63.186 identidades de jogadores; 61.246 vínculos correntes de referência. Há 2.100 escudos de clubes; 60 ainda estão indisponíveis, incluindo 30 lacunas desta expansão. Imagens genéricas dos provedores foram rejeitadas. Imagens são arquivos privados, não BLOBs do MySQL.

A coleta pública [EA SPORTS FC](https://www.ea.com/games/ea-sports-fc/ratings) identificou a edição FC27, 198 páginas e 19.789 IDs únicos: 17.849 masculinos e 1.940 femininos. Todos os ratings foram armazenados em `rh_ratings_ea`; 19.379 identidades foram conciliadas e 410 seguem pendentes, visíveis no painel. Mulheres não são automaticamente vinculadas aos clubes masculinos. Clube licenciado na EA não substitui um contrato existente; novas referências licenciadas são identificadas como tal. A coleção inclui os ratings básicos publicados, não cartas especiais, ícones ou atualização de desempenho em tempo real.

As [211 associações da FIFA](https://inside.fifa.com/associations) e seus 211 emblemas foram importados. Os códigos FIFA diferenciam Inglaterra, Escócia, País de Gales e Irlanda do Norte, embora o país ISO seja GB. Ainda não existem convocação, jogos internacionais do personagem ou premiações individuais globais implementadas. Esse cadastro prepara essas mecânicas; não as ativa.

Foram acrescentados 406 clubes em relação ao catálogo anterior e preparados 418 participantes em 27 divisões: primeiras e segundas de Índia, China, Tailândia, Emirados, Finlândia, Irlanda, Austrália, Polônia, Coreia do Sul, Romênia, Chipre e Hungria; terceira e quarta inglesas; terceira alemã. Algumas competições substituem circuitos anteriores de poucos clubes, portanto o total cresce de 130 para 154, não 157. Os 670 clubes masculinos licenciados nos filtros EA foram conciliados com códigos do Hub. A segunda divisão indiana está expressamente marcada como provisória: o diretório anterior e Mohammedan são compatíveis com o total de 11 anunciado pela AIFF, mas a lista nominal atualizada ainda precisa de confirmação. Fonte, edição e observações ficam no catálogo.

Datas e formatos completos desses novos mercados não são reproduzidos: a simulação adicional usa ida e volta, sem playoff, acesso, rebaixamento administrativo ou vagas continentais. Austrália inclui os clubes neozelandeses na pirâmide australiana; Derry City disputa o mercado irlandês. País geográfico é registrado separadamente. Carreiras mantêm campeonatos já disputados; a expansão internacional fica disponível na janela seguinte, conforme o comportamento existente do motor.

## Integração, instalação e armazenamento

Os dados compartilhados usam `rumohub.rh_*`; usuários e carreiras continuam em `ecohub.rumo10_*`. Não houve migração ou exclusão das carreiras humanas. Cada jogo usa credencial HMAC própria e IP autorizado; CORS e Referer não substituem autenticação. O painel tem sessão própria, CSRF, limitação de login e histórico das edições. Em produção, configure HTTPS e a raiz `public/` no Apache; o IIS local já usa essa raiz. As regras de proteção dos diretórios internos existem nos dois servidores.

Rumo10 consulta a versão do Hub a cada 60 segundos por padrão (`RUMOHUB_CACHE_TTL`). Atualizações são gravadas atomicamente; cache anterior continua disponível quando o Hub está temporariamente offline. Escudos e elencos são buscados por clube; caches antigos gerados pela ponte são removidos na sincronização ou, após uma hora, na atualização automática. Nomes de exibição e notas manuais aparecem na interface sem renomear os códigos internos.

No final da temporada, partidas concluídas das outras equipes perdem somente os campos vazios destinados ao personagem. Placares, classificação, cartões e campeões são preservados; partidas do personagem e futuros dados não vazios de NPCs permanecem completos. Essa compactação corrigiu o estouro de memória encontrado no IIS de 128 MB durante o teste de transição da temporada. NPCs ainda não têm estatísticas individuais de gols/assistências atribuídas nem regeneração após aposentadoria.

Para instalação e sequência dos imports, leia o README no repositório RumoHub. Guarde separadamente `.env`, `clients.php`, backups do banco e uploads `admin-*`: esses arquivos privados não são enviados ao GitHub.

## Verificação executada

No IIS local: 79 verificações HTTP do Rumo10; no Hub, 22 HTTP e 32 de administração, usando registros descartáveis removidos ao terminar. Também passaram 15 verificações de autenticação, comparação dos 19.789 ratings à coleta, códigos/emblemas nacionais, separação de gênero e unicidade das identidades conciliadas; 27 calendários adicionais até campeão; 144 verificações de evolução/aposentadoria; preservação de estatísticas na compactação; apresentação e escaping; sintaxe PHP. A home foi inspecionada no navegador em largura de 390 px, sem rolagem horizontal na tabela de jogos.

Apache, HTTPS de produção e carga concorrente não foram testados. Os testes de importação avaliam integridade das fontes coletadas; não tornam provisórios e formatos adaptados equivalentes aos regulamentos oficiais.
