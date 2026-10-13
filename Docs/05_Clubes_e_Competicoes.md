# Clubes e competições — versão 0.3

Nova solicitação do usuário substitui a liga fictícia nas **novas carreiras**. Recorte confirmado: participantes de 2026 das competições masculinas profissionais Brasileirão A/B/C/D e Paulista A1/A2/A3/A4/Segunda Divisão Sub-23. São 244 participações, **225 clubes distintos**. Clubes inativos, licenciados somente para base, futebol feminino, Copa Paulista e Copa do Brasil não integram este recorte.

| Competição | Clubes | Disputa implementada | Acesso / descenso |
|---|---:|---|---|
| Brasileirão A | 20 | 38 rodadas | 4 rebaixados |
| Brasileirão B | 20 | 38 rodadas + playoffs 3º×6º / 4º×5º, ida e volta | 2 diretos + 2 playoffs; 4 rebaixados |
| Brasileirão C | 20 | 19 rodadas; quadrangulares 1/4/5/8 e 2/3/6/7; final ida e volta | 4 acessos; 2 rebaixados em 2026 |
| Brasileirão D | 96 | 16 grupos oficiais de 6; 10 rodadas; mata-matas com chaveamento do REC; playoffs dos eliminados nas quartas | 4 semifinalistas + 2 playoffs |
| Paulista A1 | 16 | 8 rodadas com adversários do sorteio oficial; quartas e semifinal únicas; final ida e volta | 2 rebaixados |
| Paulista A2 | 16 | 15 rodadas; quadrangulares 1/3/6/8 e 2/4/5/7; semifinal/final ida e volta | 2 acessos; 2 rebaixados |
| Paulista A3 | 16 | 15 rodadas + quartas, semifinal e final ida e volta; empate decidido nos pênaltis | 2 acessos; 2 rebaixados |
| Paulista A4 | 16 | 15 rodadas + quartas, semifinal e final ida e volta; empate favorece melhor campanha | 2 acessos; 2 rebaixados |
| Segunda Divisão Sub-23 | 24 | 4 grupos oficiais de 6; 10 rodadas; oitavas em diante com reordenamento por campanha e ida/volta | 2 acessos |

Na B e nos playoffs da D, empate no agregado favorece a melhor campanha. Na D fora dos playoffs, empate no agregado vai aos pênaltis. Na A2, igualdade nos mata-matas favorece a melhor campanha acumulada. Classificação usa pontos, vitórias, saldo, gols; os critérios específicos incluem confronto direto quando aplicável, cartões e sorteio persistido. Pênaltis ficam registrados separadamente do placar e não contam como gols do jogador.

Fontes consultadas em 12/10/2026:

- [CBF: participantes da Série A](https://www.cbf.com.br/futebol-brasileiro/times/campeonato-brasileiro/serie-a/2026).
- [CBF: regulamento da Série B](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Brasileiro_Serie_B_2026_85d55b9f72.pdf).
- [CBF: participantes da Série C na rodada completa](https://www.cbf.com.br/futebol-brasileiro/noticias/noticias-campeonato-brasileiro-serie-c/a/serie-c-times-do-norte-tentam-manter-protagonismo-na-rodada) e [quadrangulares](https://www.cbf.com.br/futebol-brasileiro/noticias/campeonato-brasileiro-serie-c/a/segunda-fase-da-serie-c-comeca-neste-fim-de-semana).
- [CBF: grupos da Série D](https://www.cbf.com.br/futebol-brasileiro/noticias/undefined/ferroviario-x-4-de-julho/cbf-divulga-grupos-da-serie-d-de-2026) e [REC, incluindo Anexo B](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Brasileiro_Serie_D_2026_2def4ff8f2.pdf).
- [FPF: REC A1](https://futebolpaulista.com.br/Repositorio/Competicao/Regulamento/1551/1551_639002025107690404.pdf), [REC A2](https://futebolpaulista.com.br/Repositorio/Noticia/30545/30545_2122025135816_1.pdf), [REC A3](https://futebolpaulista.com.br/Repositorio/Noticia/30576/30576_0.pdf), [REC A4](https://futebolpaulista.com.br/Repositorio/Noticia/30603/30603_1.pdf).
- [FPF: grupos da Segunda Divisão](https://www.futebolpaulista.com.br/Noticias/Detalhe.aspx?Noticia=31961).
- [FPF: jogos A4 com Jacareí e ECUS](https://www.futebolpaulista.com.br/Noticias/Detalhe.aspx?Noticia=30879); [Prefeitura de Jacareí confirma ex-Joseense](https://www.jacarei.sp.gov.br/estadio-municipal-du-cambusano-recebera-jogos-do-jacarei-futebol-clube-pelo-campeonato-paulista/).

Cada clube tem UF no identificador. Nacional-SP/AM, São José-SP/RS, Primavera-SP/MT, Botafogo-SP/RJ/PB são distintos. Mauá não é Mauaense; EC São Bernardo não é São Bernardo FC. O cadastro e suas fontes estão nos arquivos database/competitions-2026.json e database/official-clubs-2026.json e nas tabelas rumo10_competicoes e rumo10_clubes_oficiais.

## Fluxo e limites da simulação

Escolha o clube de início. A carreira começa pelo estadual paulista, quando houver, e depois joga o nacional ao qual o clube pertence. Um clube sem divisão nacional joga apenas seu estadual. Cada campeonato avança conforme os resultados: classificação, eliminação, pênaltis, acesso e título. Ao fim do ano, propostas vêm de clubes reais; aceitar ou permanecer mantém progressão e histórico. O mundo de cada conta é independente.

O calendário é gerado para jogar: datas/horários oficiais, viagens, sobreposição de estadual/nacional, elenco completo, licenciamento, vagas da Copa do Brasil e quotas etárias de inscrição não são simulados. No A1 de 2026 os adversários são os oficiais, mas ordem e mandos são gerados e balanceados em quatro jogos em casa por clube. Placar dos demais clubes e cartões coletivos usam simulação simplificada. Os nomes não implicam patrocínio ou afiliação do jogo aos clubes.

Temporadas seguintes aplicam os acessos e descensos do mundo do jogador, não os resultados históricos reais. A Série C cresce para 24 e depois 28 clubes; o protótipo mantém grupo único e quadrangulares depois de 2026 e usa seis descensos quando atinge 28. Isso é adaptação jogável, **não reprodução integral de regulamento de 2028**. A Série D é reposta até 96 com clubes paulistas disponíveis por campanha; licenciamento e classificações de outras federações não estão implementados. Os clubes atuais da D não perdem a vaga por critérios estaduais ainda não simulados.

A carreira antiga permanece com sua liga de 14 rodadas. Criar nova carreira arquiva a anterior sem apagar dados; restaurá-la arquiva a atual. Não há conversão de partidas antigas para clubes reais.

Cores do catálogo anterior de 21 clubes são preservadas como referências separadas. A versão 0.3 prioriza nomes, participações e competição jogável; identidade visual dos 225 clubes ainda não foi pesquisada/certificada e não foi inventada.
