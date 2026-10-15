<?php
declare(strict_types=1);
return [
 1000=>[2=>['success_text'=>'O auxiliar demonstra o exercício e você entende como conduzir a bola no espaço curto.','failure_text'=>'A demonstração não esclarece como você deve mudar de direção com a bola.']],
 1019=>[
  1=>['success_text'=>'Você e seu parceiro ouvem a orientação do auxiliar e acertam o que precisa mudar.','failure_text'=>'A conversa com o auxiliar não resolve a dúvida entre você e seu parceiro.'],
  2=>['effects'=>['entrosamento'=>3,'foco'=>2],'failure_effects'=>['entrosamento'=>-1],'success_text'=>'Você explica a correção em particular e seu parceiro entende a intenção de ajudar.','failure_text'=>'Seu parceiro continua incomodado com a correção, mesmo depois da conversa em particular.']],
 1035=>[0=>['effects'=>['visao_jogo'=>1,'foco'=>2],'failure_effects'=>['foco'=>-1],'success_text'=>'Você mostra ao auxiliar o espaço que percebeu e por que decidiu daquela forma.','failure_text'=>'Você não consegue explicar ao auxiliar o que percebeu antes da jogada.']],
 1056=>[2=>['success_text'=>'Você destaca a preparação dos companheiros sem entrar na provocação do repórter.','failure_text'=>'Sua resposta não desvia da provocação e deixa o nome dos companheiros no meio da discussão.']],
 1058=>[0=>['success_text'=>'O analista esclarece como a mudança na escalação adversária afeta sua tarefa.','failure_text'=>'A conversa com o analista não traz informações suficientes sobre o novo adversário.']],
 1063=>[0=>['effects'=>['lideranca'=>3,'confianca_treinador'=>2,'foco'=>2],'failure_effects'=>['foco'=>-1],'success_text'=>'Você confirma com o treinador quais responsabilidades vai assumir e como cumpri-las.','failure_text'=>'Você aceita ajudar, mas não esclarece quais tarefas o treinador espera de você.']],
 1065=>[3=>['effects'=>['entrosamento'=>2,'moral_pessoal'=>2],'failure_effects'=>[],'success_text'=>'Você compartilha a carta com os companheiros e o apoio da criança anima o vestiário.','failure_text'=>'Os companheiros estão concentrados no aquecimento e você guarda a carta para compartilhar depois.']],
 1094=>[1=>['effects'=>['foco'=>2,'risco_cartao'=>-2],'failure_effects'=>[],'success_text'=>'Você combina que o capitão falará com o árbitro e mantém a atenção na sua tarefa.','failure_text'=>'A combinação com o capitão não fica clara antes da entrada, e você mantém a decisão de evitar discussões.']],
];
