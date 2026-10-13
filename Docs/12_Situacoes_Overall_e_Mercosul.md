# Versão 0.5 — situações, força dos clubes e Mercosul

Implementado e verificado localmente em 13/10/2026. PHP 8.4.26, IIS `http://rumo10.local`, MySQL `ecohub`. Nenhuma carreira pessoal foi simulada ou reiniciada nesta atualização. Os calendários e placares já finalizados permanecem salvos.

## Situações e escolhas

O motor usa 214 situações e 856 alternativas: 12 anteriores, 200 novas situações escritas individualmente e duas variações de início no banco. As novas estão nos arquivos `database/scenes-*.tsv`, reunidos em `database/scenes.json`. São 50 de treino, 50 de pré-jogo, 50 de partida e 50 de vida fora de campo. Há decisões técnicas, táticas, disciplina, imprensa, convivência, descanso e recuperação. Quatro alternativas por evento preservam a tela compacta de celular.

`app/Scenes.php` filtra situações pela etapa, posição, minuto, condição física e presença em campo. Evita os últimos dez eventos de cada etapa quando há candidatos suficientes; o conjunto de decisões de reserva tem apenas três eventos. Mantém até 24 IDs recentes por etapa. Patrocínio e rivalidade sem vínculo cadastrado continuam excluídos. Uma decisão social não vira automaticamente finalização ou passe decisivo.

A seleção ocorre no servidor, após o comando anterior, a partir de uma semente por carreira e uma chave com temporada, rodada, etapa e minuto. O ID ativo, a semente e a memória são persistidos no snapshot transacional. Recarregar não troca a situação. Uma carreira anterior mantém a decisão pendente e passa a receber as novas situações após a próxima ação. A auditoria dos novos comandos registra regras `0.5.0`.

Alternativas usam mecânicas compartilhadas, mas textos e contextos são próprios. Não se criaram centenas de combinações artificiais do mesmo enunciado. As consequências continuam probabilísticas, com limites de atributos, XP, fadiga e riscos. Há nove famílias de textos de sucesso e um texto básico de falha; a variedade das consequências narradas pode ser ampliada futuramente. Chances e atributos das alternativas permanecem ocultos antes da escolha; somente efeitos efetivos aparecem depois.

## Overall: modelo de jogo

`app/TeamStrength.php` atribui uma força entre 20 e 95. É uma nota calibrada para o protótipo, não um ranking oficial nem avaliação do elenco atual.

| Competição | Base |
|---|---:|
| Brasileiro A / B / C / D | 84 / 72 / 60 / 48 |
| Paulista A1 / A2 / A3 / A4 / Segunda | 64 / 53 / 45 / 38 / 30 |
| Outros estaduais de entrada / Roraima | 32 / 48 |
| Argentina primeira / segunda | 81 / 63 |
| Uruguai primeira / segunda | 64 / 48 |
| Paraguai primeira / segunda | 65 / 46 |
| Bolívia primeira / segunda | 58 / 39 |

O clube usa a maior base das competições em que participa. A campanha anterior gera um indicador de 0–100: 70% de aproveitamento de pontos e 30% de posição relativa na tabela. O ajuste é `base + 0,20 × (indicador − 50)`. A forma dos últimos cinco jogos finalizados do calendário atual acrescenta de −3 a +3 pontos. Na abertura da partida, as duas notas são congeladas no próprio registro; resultados já encerrados não são recalculados.

A primeira carga tem campanhas reais verificadas dos 20 clubes do Brasileirão A de 2025, em `database/team-results-2025.json`, com pontos, jogos, posição e URL da [tabela consultada](https://www.futebolinterior.com.br/campeonato/brasileirao-serie-a-2025/). Os demais clubes começam com a base provisória da divisão: **não há posição histórica inventada**. Ainda falta ampliar a pesquisa de classificações anteriores das outras competições. Ao fim de cada ano da carreira, as campanhas simuladas atualizam a força para o próximo ano, já considerando a nova divisão. Isso não altera o cadastro histórico real.

Gols coletivos e jogos dos NPCs usam distribuição de Poisson, com vantagem de mando e diferença de força. Médias de referência por jogo: `1,35 × exp(diferença/55)` para o mandante e `1,10 × exp(−diferença/55)` para o visitante, limitadas a 0,25–3,5. Na partida acompanhada, cada trecho recebe a parcela proporcional ao tempo. Gols e assistências do personagem seguem suas decisões e estatísticas próprias. A força do adversário também entra na fórmula existente de dificuldade das ações, com chance final entre 5% e 95%. Não se simulam posse, onze jogadores, estratégia de equipe ou equilíbrio financeiro.

## Expansão internacional de 2026

Foram adicionados 209 clubes únicos. Somados aos 462 anteriores: **671 clubes**, **43 competições/etapas**. A entrada do jogador continua brasileira, por cidade, roleta e três propostas locais; os clubes estrangeiros entram pelo mercado e respeitam os limites de progressão de divisão. Não há seletor livre de clube no início.

| País | Primeira divisão | Segunda divisão | Clubes novos |
|---|---|---|---:|
| Argentina | Liga Profesional, 30 | Primera Nacional, 36 | 66 |
| Uruguai | Liga AUF Uruguaya, 16 | Segunda Profesional, 14 | 30 |
| Paraguai | Copa de Primera, 12 | Intermedia, 16 | 28 |
| Bolívia | División Profesional, 16 | Simón Bolívar, 69 regionais | 85 |

Participantes são conferidos com as fontes registradas em `database/international-*.json`. Referências: [zonas LPF](https://www.lpf.org.ar/notas/primera/2025/12/10/se-definen-las-zonas-para-la-temporada-20226/), [fixture AFA da Nacional](https://www.afa.com.ar/3423/posts/primera-nacional-fixture-para-la-temporada-2026), [Liga AUF](https://sudamericanos20.auf.org.uy/liga-auf-uruguaya/), [Segunda AUF](https://sudamericanos20.auf.org.uy/segunda-division-profesional/), [calendário APF](https://www.apf.org.py/noticias/listo-calendario-para-la-temporada-2026), [Intermedia APF](https://www.apf.org.py/noticias/asi-iniciara-la-intermedia-2026), [profissional FBF](https://fbf.com.bo/torneos/division-profesional/) e [Simón Bolívar FBF](https://fbf.com.bo/arranca-la-copa-simon-bolivar-2026/).

O motor `app/InternationalCompetition.php` disputa anos completos, fases de grupos, títulos, mata-matas e próxima temporada:

- Argentina primeira: Apertura e Clausura, dois grupos de 15, 16 jogos por clube em cada primeira fase e playoffs de 16 clubes. Registra os dois campeões e o líder anual. Os emparelhamentos interzonais e a ordem de jogos são gerados no protótipo, não uma reprodução dos clássicos ou datas do fixture oficial.
- Primera Nacional: duas zonas de 18, 36 jogos incluindo interzonais de ida/volta com os pares publicados pela AFA; final pelo primeiro acesso e Reducido pelo segundo. Usa vantagem de classificação nas fases pertinentes. Ordenação do Reducido usa pontos gerais, simplificando o critério oficial que prioriza a colocação dentro da zona; não há prorrogação nem jogo extra de desempate de rebaixamento. [Regulamento AFA](https://assets1.afa.com.ar/2026/Lau/Boletin-Especial-6823---Reglamento-del-Campeonato-de-Primera-Nacional-2026.pdf).
- Uruguai primeira: Apertura, Intermedio com dois grupos e final, Clausura, tabela anual e definição do título. Segunda: Competencia com dois grupos de sete e final, fase regular de 26 jogos e playoff dos lugares 3–6 pelo terceiro acesso. A regra de eventual proteção do campeão do Competencia não é modelada; rebaixamentos usam a campanha anual simulada em vez de médias históricas.
- Paraguai primeira: Apertura e Clausura de 22 jogos cada, com ambos os títulos registrados. Intermedia: 30 jogos, dois acessos. O rebaixamento da primeira usa a tabela anual simulada; **a média oficial de temporadas anteriores não foi importada**. [APF sobre o Clausura e médias](https://www.apf.org.py/noticias/la-espera-termino-arranca-el-clausura).
- Bolívia primeira: 30 jogos, campeão por pontos e um descenso direto. Simón Bolívar: nove regiões, dois grupos em cada, quadrangulares regionais, 24 classificados em seis grupos nacionais e mata-mata de 16 em ida/volta. Um acesso direto conecta as divisões. A repescagem contra o penúltimo da primeira e a Copa da División Profesional não estão implementadas. A publicação FBF diverge entre texto e slides sobre acesso indireto e usa 72 no slide geral, embora a relação nominal e o texto tenham 69; o cadastro usa os **69 nomes apresentados**, sem completar com clubes inventados. [FBF sobre a temporada](https://fbf.com.bo/temporada-2026-de-la-division-profesional/).

Na próxima temporada, clubes que sobem e descem são trocados entre as duas divisões de cada país. Grupos são recompostos conservando tamanhos; não há sorteio real, licenciamento, médias de descenso, calendário real de 2027 ou preenchimento de vagas vindo de uma terceira divisão. O descenso da segunda fica registrado, mas permanecer no clube repete a divisão cadastrada; a interface informa essa condição. As limitações das pirâmides estaduais brasileiras anteriores permanecem.

Carreiras existentes recebem as oito competições estrangeiras **na próxima conclusão de temporada**, sem recriar seus campeonatos ou a proposta já sorteada. Novas carreiras já incluem esse universo. Contratos dependem de desempenho e nível; isso não garante uma proposta internacional na primeira temporada.

## Escudos e cores

Todos os 671 clubes têm arquivo local em `public/assets/clubs/`, listado em `database/club-badges.json`, com URL, origem e data. Os 209 novos foram obtidos do [acervo Escudos FC](https://escudosfc.com.br/), com correção de GV San José pela imagem da própria FBF. Não se certificou manualmente a versão vigente de todos os escudos; o acervo inclui variantes históricas. Os 462 anteriores conservam suas fontes oficiais e comunitárias documentadas na versão 0.4.

Os novos clubes têm dois ou três HEX aproximados em `colors`, extraídos da paleta visível do escudo. `colors_status=approximate_badge_palette` distingue isso de cores de uniforme ou de um manual oficial de marca. A terceira cor permanece quando presente na paleta; pequenos contornos e detalhes podem não ser selecionados. Ainda é necessária revisão visual das cores institucionais clube a clube. O download não concedeu licença de uso comercial das marcas.

`bin/build-international.py --download` reconstrói os dados e imagens com Python/Pillow, consultando URLs públicas. Não abre o banco nem lê credenciais. Para atualizar somente o catálogo MySQL, use `php bin/install-official.php`; upserts são restritos a `rumo10_competicoes` e `rumo10_clubes_oficiais`, sem apagar carreiras.

## Como testar localmente

Abra [o jogo](http://rumo10.local/carreira). A próxima ação já pode trazer uma situação nova. Para testar desde o começo, use **Nova carreira** no menu ou [o formulário](http://rumo10.local/carreira/nova); a carreira atual será arquivada somente ao enviar o formulário.

Jogue treino, pré-jogo e partida; recarregue para conferir que o evento não muda. Gols e assistências pessoais ficam no placar, e só depois da escolha aparecem os efeitos. Termine o ano e avalie o mercado: os clubes dos quatro países são elegíveis conforme a progressão. Não é necessário executar PowerShell administrativo para esta atualização.

Com PHP disponível: `php tests/scenes-strength.php`, `php tests/international.php`, `php tests/official.php`, `php tests/state-entry.php`. Os testes de MySQL usam contas temporárias e limpam apenas seus próprios registros. Evidência completa em `Docs/10_Testes_Executados.md`. Apache de produção e telefone físico não foram acessados nem testados.
