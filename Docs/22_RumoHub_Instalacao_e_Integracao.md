# RumoHub: instalação e integração

O repositório independente está em `C:\inetpub\wwwroot\rumohub`, remoto `https://github.com/rralvarez/rumohub.git`. A raiz pública para IIS e Apache é `public/`; a documentação completa está no README desse repositório.

Nesta etapa, o catálogo foi copiado para `rumohub.rh_*`, preservando IDs de clubes e jogadores. O `ecohub` e todas as tabelas atuais `rumo10_*` foram preservados. Contas, carreiras e aposentadorias continuam no banco atual do jogo. A migração futura dos dados de cada jogo exige etapa própria, com backup e validação; não foi feita silenciosamente nesta entrega.

O Rumo10 pode consumir o Hub com:

```text
RUMOHUB_ENABLED=1
RUMOHUB_URL=http://rumohub.local
RUMOHUB_CLIENT=rumo10
RUMOHUB_SECRET=SEGREDO_PRIVADO
```

Esses valores pertencem ao `.env`, nunca ao Git. O script local `rumohub/bin/connect-rumo10.php` pode preparar os valores sem exibir o segredo. Execute `bin/sync-hub-catalog.php` após verificar o Hub. Em produção use HTTPS e cadastre no Hub o IP de saída real do servidor do jogo. Cada futuro jogo recebe outra credencial revogável.

Com o Hub habilitado, `Competition` lê o catálogo sincronizado; `ClubSquad` busca o elenco solicitado. `ClubBadge` renderiza `/escudo?club=…`: sessão autenticada no jogo, busca assinada no servidor e cache privado, sem credencial enviada ao navegador. Os escudos no Hub ficam em `storage/media/clubs/`, fora da raiz pública e fora do MySQL. Nenhum usuário precisa navegar para `rumohub.local` para jogar.

`storage/hub-cache/` é ignorado pelo Git. O catálogo funciona a partir da última sincronização; elencos/escudos já buscados permanecem disponíveis no cache daquela versão. Um clube ainda não buscado exige Hub disponível. Após uma nova versão, a busca usa novo cache; a sincronização remove somente caches gerados pela ponte de versões antigas. O cliente verifica a versão informada pela API antes de guardar um elenco ou escudo. Não remova os arquivos e tabelas antigos durante esta transição. Para desabilitar a ponte, use `RUMOHUB_ENABLED=0`.

Verificação da ponte: `php tests/hub.php http://rumohub.local`. O teste compara participantes e dados das competições, verifica os IDs do Hub, elenco e escudo do Inter Miami. Apache e hospedagem de produção precisam de verificação própria; os testes locais não comprovam esses ambientes.

Em 14/10/2026 a ponte foi habilitada neste computador após o Hub passar em 17 verificações HTTP no IIS. O Rumo10 passou em 79 verificações HTTP com o Hub habilitado, incluindo escudo autenticado; a evolução/aposentadoria passou em 144 verificações e a apresentação de estatísticas foi validada. Contas descartáveis dos testes foram removidas. O detalhamento está em `rumohub/TESTES.md`. Para jogar, continue acessando `http://rumo10.local`; `http://rumohub.local` é apenas o serviço de referência.
