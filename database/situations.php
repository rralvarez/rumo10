<?php
// Cada efeito técnico representa XP. Custos físicos negativos ocorrem em toda tentativa.
return [
1=>['treino','A chuva encharcou o campo. O preparador sugere pegar leve na academia.',[
['Fazer um treino leve',['energia'=>10,'risco_lesao'=>-5]],['Treinar finalizações na chuva',['finalizacao'=>2,'risco_lesao'=>15,'energia'=>-15]],['Jogar um rachão na lama',['entrosamento'=>5,'ritmo_jogo'=>2,'energia'=>-20]],['Reclamar e sair mais cedo',['energia'=>20,'confianca'=>-10,'entrosamento'=>-5]]]],
2=>['treino','O treino de passes curtos está repetitivo e o elenco perdeu o interesse.',[
['Levar o exercício a sério',['passe'=>2,'confianca'=>5]],['Fazer brincadeiras durante o treino',['entrosamento'=>5,'confianca_treinador'=>-10]],['Fazer o exercício sem se esforçar',['energia'=>5,'ritmo_jogo'=>-5]],['Chamar um companheiro para uma disputa',['ritmo_jogo'=>5,'entrosamento'=>2,'energia'=>-10]]]],
3=>['treino','O lateral está errando os cruzamentos e começa a ficar frustrado.',[
['Ajudar depois do treino',['entrosamento'=>10,'energia'=>-10]],['Fazer alongamento sem se envolver',['energia'=>5,'risco_lesao'=>-5]],['Pedir para trocar o lateral',['confianca'=>5,'entrosamento'=>-15]],['Tirar sarro dos erros dele',['entrosamento'=>-10,'confianca'=>5]]]],
4=>['pre_jogo','O clima no vestiário está tenso antes do confronto regional.',[
['Puxar um grito de união ou uma oração com quem quiser participar',['entrosamento'=>10,'confianca'=>5]],['Ficar na sua, ouvindo música',['confianca'=>10,'entrosamento'=>-5]],['Fazer piadas para quebrar o clima',['entrosamento'=>5,'confianca_treinador'=>-5]],['Caprichar no aquecimento',['risco_lesao'=>-10,'ritmo_jogo'=>5]]]],
5=>['pre_jogo','Saiu uma notícia falsa dizendo que você está insatisfeito no clube.',[
['Dar uma entrevista desmentindo a notícia',['confianca'=>10,'foco'=>-5]],['Ignorar e focar no jogo',['ritmo_jogo'=>5,'foco'=>10]],['Reclamar com a diretoria',['energia'=>-5,'confianca_treinador'=>-10]],['Mostrar seu carinho pelo clube nas redes',['apoio_torcida'=>15,'entrosamento'=>5]]]],
6=>['pre_jogo','O treinador avisa que você vai começar no banco.',[
['Apoiar quem vai começar jogando',['entrosamento'=>10,'confianca_treinador'=>10]],['Ficar sozinho e evitar o grupo',['confianca'=>-10,'entrosamento'=>-10]],['Aquecer forte para estar pronto',['energia'=>-15,'ritmo_jogo'=>5,'confianca'=>5]],['Reclamar da decisão',['confianca_treinador'=>-20,'entrosamento'=>-5]]]],
7=>['partida',"O jogo está empatado aos 80 minutos. Você recebe na entrada da área, com dois zagueiros pela frente.",[
['Chutar no ângulo',['confianca'=>20]],['Tocar para o volante',['entrosamento'=>5,'ritmo_jogo'=>5]],['Tentar driblar os dois',['risco_lesao'=>15,'energia'=>-10,'drible'=>2]],['Tentar cavar uma falta',['confianca'=>5,'risco_lesao'=>5]]]],
8=>['partida','Um companheiro leva uma falta dura e o clima esquenta.',[
['Empurrar o adversário',['entrosamento'=>10,'risco_cartao'=>30,'confianca_treinador'=>-10]],['Ajudar o companheiro e acalmar a situação',['entrosamento'=>5,'confianca'=>5]],['Cobrar um cartão gritando com o árbitro',['risco_cartao'=>20,'ritmo_jogo'=>5]],['Não se envolver e tomar água',['energia'=>5,'entrosamento'=>-10]]]],
9=>['partida','Seu time sofre um gol no contra-ataque e a torcida começa a vaiar.',[
['Tentar resolver sozinho',['energia'=>-15,'confianca'=>10,'entrosamento'=>-5]],['Gritar com os jogadores da defesa',['lideranca'=>5,'entrosamento'=>-5]],['Ajudar o time a se reorganizar',['entrosamento'=>10,'confianca_treinador'=>10]],['Recuar para ajudar na marcação',['desarme'=>2,'energia'=>-10,'ritmo_jogo'=>5]]]],
10=>['fim_semana','Um patrocinador convida você para um evento beneficente na comunidade.',[
['Ir bem vestido ao evento',['apoio_torcida'=>10,'confianca_diretoria'=>10,'energia'=>-5]],['Ficar em casa e descansar',['energia'=>20,'confianca_diretoria'=>-10]],['Passar no evento por alguns minutos',['energia'=>5,'apoio_torcida'=>5]],['Faltar sem avisar para jogar videogame',['energia'=>15,'confianca_diretoria'=>-20]]]],
11=>['fim_semana','Torcedores estão compartilhando memes depois da derrota de um rival local.',[
['Compartilhar memes',['apoio_torcida'=>15,'risco_polemica'=>20]],['Ficar calado',['foco'=>5,'energia'=>5]],['Pedir respeito',['confianca_imprensa'=>10,'apoio_torcida'=>-5]],['Jantar fora',['energia'=>10,'risco_encontrar_rivais'=>10]]]],
12=>['fim_semana','Seu antigo treinador da base pede ajuda para pensar em algumas jogadas.',[
['Ajudar por telefone',['lideranca'=>5,'energia'=>-5,'visao_jogo'=>2]],['Dizer que está ocupado',['energia'=>5,'moral_pessoal'=>-5]],['Conversar e desenhar as jogadas',['visao_jogo'=>5,'energia'=>-10,'confianca'=>5]],['Prometer enviar o material depois',[]]]],
];
