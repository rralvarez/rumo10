<?php
// Cada efeito técnico representa XP. Custos físicos negativos ocorrem em toda tentativa.
return [
1=>['treino','Chuva forte no centro de treino; campo pesado; preparador sugere academia leve.',[
['Treino leve',['energia'=>10,'risco_lesao'=>-5]],['Finalizações na chuva',['finalizacao'=>2,'risco_lesao'=>15,'energia'=>-15]],['Rachão na lama',['entrosamento'=>5,'ritmo_jogo'=>2,'energia'=>-20]],['Reclamar e sair cedo',['energia'=>20,'confianca'=>-10,'entrosamento'=>-5]]]],
2=>['treino','Circuito repetitivo de passes curtos e elenco entediado.',[
['Seriedade',['passe'=>2,'confianca'=>5]],['Brincar no treino',['entrosamento'=>5,'confianca_treinador'=>-10]],['Pouca vontade',['energia'=>5,'ritmo_jogo'=>-5]],['Desafiar companheiro',['ritmo_jogo'=>5,'entrosamento'=>2,'energia'=>-10]]]],
3=>['treino','Lateral erra cruzamentos e está frustrado.',[
['Ajudar após treino',['entrosamento'=>10,'energia'=>-10]],['Alongar e ignorar',['energia'=>5,'risco_lesao'=>-5]],['Pedir outro lateral',['confianca'=>5,'entrosamento'=>-15]],['Zombar',['entrosamento'=>-10,'confianca'=>5]]]],
4=>['pre_jogo','Vestiário tenso antes de confronto regional.',[
['Liderar grito de união ou oração opcional',['entrosamento'=>10,'confianca'=>5]],['Isolar-se com fones',['confianca'=>10,'entrosamento'=>-5]],['Piadas',['entrosamento'=>5,'confianca_treinador'=>-5]],['Aquecimento muscular intenso',['risco_lesao'=>-10,'ritmo_jogo'=>5]]]],
5=>['pre_jogo','A imprensa local publica notícia falsa de insatisfação.',[
['Entrevista negando',['confianca'=>10,'foco'=>-5]],['Ignorar e focar',['ritmo_jogo'=>5,'foco'=>10]],['Reclamar com diretoria',['energia'=>-5,'confianca_treinador'=>-10]],['Demonstrar amor ao clube nas redes',['apoio_torcida'=>15,'entrosamento'=>5]]]],
6=>['pre_jogo','Treinador informa que o jogador ficará no banco.',[
['Apoiar titular',['entrosamento'=>10,'confianca_treinador'=>10]],['Isolar-se',['confianca'=>-10,'entrosamento'=>-10]],['Aquecer intensamente',['energia'=>-15,'ritmo_jogo'=>5,'confianca'=>5]],['Reclamar',['confianca_treinador'=>-20,'entrosamento'=>-5]]]],
7=>['partida',"Empate aos 80 minutos; bola na entrada da área com dois zagueiros.",[
['Chute no ângulo',['confianca'=>20]],['Passe para volante',['entrosamento'=>5,'ritmo_jogo'=>5]],['Drible duplo',['risco_lesao'=>15,'energia'=>-10,'drible'=>2]],['Tentar cavar falta',['confianca'=>5,'risco_lesao'=>5]]]],
8=>['partida','Falta dura em companheiro importante.',[
['Empurrar adversário',['entrosamento'=>10,'risco_cartao'=>30,'confianca_treinador'=>-10]],['Ajudar e acalmar',['entrosamento'=>5,'confianca'=>5]],['Pedir cartão agressivamente',['risco_cartao'=>20,'ritmo_jogo'=>5]],['Ignorar e beber água',['energia'=>5,'entrosamento'=>-10]]]],
9=>['partida','Time sofre gol em contra-ataque e torcida vaia.',[
['Resolver sozinho',['energia'=>-15,'confianca'=>10,'entrosamento'=>-5]],['Gritar com defesa',['lideranca'=>5,'entrosamento'=>-5]],['Reorganizar equipe',['entrosamento'=>10,'confianca_treinador'=>10]],['Recuar e marcar',['desarme'=>2,'energia'=>-10,'ritmo_jogo'=>5]]]],
10=>['fim_semana','Patrocinador convida para gala comunitária.',[
['Comparecer elegante',['apoio_torcida'=>10,'confianca_diretoria'=>10,'energia'=>-5]],['Descansar',['energia'=>20,'confianca_diretoria'=>-10]],['Presença casual breve',['energia'=>5,'apoio_torcida'=>5]],['Faltar sem avisar para jogar videogame',['energia'=>15,'confianca_diretoria'=>-20]]]],
11=>['fim_semana','Torcedores publicam memes após derrota de rival local.',[
['Compartilhar memes',['apoio_torcida'=>15,'risco_polemica'=>20]],['Ficar calado',['foco'=>5,'energia'=>5]],['Pedir respeito',['confianca_imprensa'=>10,'apoio_torcida'=>-5]],['Jantar fora',['energia'=>10,'risco_encontrar_rivais'=>10]]]],
12=>['fim_semana','Ex-treinador de base pede conselhos táticos.',[
['Ajudar ao telefone',['lideranca'=>5,'energia'=>-5,'visao_jogo'=>2]],['Dizer estar ocupado',['energia'=>5,'moral_pessoal'=>-5]],['Conversar e desenhar jogadas',['visao_jogo'=>5,'energia'=>-10,'confianca'=>5]],['Prometer mandar material depois',[]]]],
];
