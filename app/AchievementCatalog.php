<?php
declare(strict_types=1);
final class AchievementCatalog {
 public static function all():array {
  $groups=[
   'CARREIRA'=>[
    ['CAR01','Primeiros Passos',5,'Criar a primeira carreira válida.'],
    ['CAR02','Primeiro Apito',10,'Completar a primeira partida com minutos jogados.'],
    ['CAR03','Dupla Jornada',10,'Criar 2 carreiras únicas válidas na conta.'],
    ['CAR04','Três Caminhos',15,'Criar 3 carreiras únicas válidas na conta.'],
    ['CAR05','Temporada Inteira',15,'Concluir 1 temporada em uma carreira.'],
    ['CAR06','Colecionador de Histórias',20,'Criar 5 carreiras únicas válidas na conta.'],
    ['CAR07','Pé na Estrada',20,'Defender 3 clubes na mesma carreira, jogando em cada um.'],
    ['CAR08','Veterano',25,'Concluir 5 temporadas na mesma carreira.'],
    ['CAR09','Dez Vidas de Bola',30,'Criar 10 carreiras únicas válidas na conta.'],
    ['CAR10','Uma Vida de Futebol',50,'Concluir 15 temporadas na mesma carreira.']],
   'DESEMPENHO'=>[
    ['DES01','Primeiro Grito',5,'Marcar o primeiro gol em partida oficial.'],
    ['DES02','Primeiro Garçom',10,'Registrar a primeira assistência em partida oficial.'],
    ['DES03','Jogo Decisivo',10,'Participar diretamente de 2 gols na mesma partida oficial.'],
    ['DES04','Hat-trick',15,'Marcar 3 gols em uma única partida oficial.'],
    ['DES05','Dobradinha',20,'Registrar gol e assistência na mesma partida oficial.'],
    ['DES06','Muralha',20,'Realizar 100 desarmes em uma única carreira.'],
    ['DES07','Garçom de Elite',25,'Acumular 50 assistências na mesma carreira.'],
    ['DES08','Artilheiro Centenário',35,'Marcar 100 gols na mesma carreira.'],
    ['DES09','Lenda da Marcação',40,'Realizar 500 desarmes na mesma carreira.'],
    ['DES10','Máquina de Gols',70,'Marcar 300 gols na mesma carreira.']],
   'TITULOS'=>[
    ['TIT01','Primeira Taça',5,'Conquistar o primeiro título de competição na carreira.'],
    ['TIT02','Campeão de Novo',10,'Conquistar 2 títulos distintos na mesma carreira.'],
    ['TIT03','Artilheiro da Temporada',10,'Receber prêmio oficial simulado de artilheiro.'],
    ['TIT04','Bicampeão',15,'Conquistar duas edições da mesma competição na mesma carreira.'],
    ['TIT05','Rei das Assistências',20,'Receber prêmio oficial simulado de maior assistente.'],
    ['TIT06','Tríplice Coroa',20,'Conquistar 3 títulos distintos na mesma temporada e carreira.'],
    ['TIT07','Campeão de Elite',25,'Conquistar competição de primeira divisão reconhecida.'],
    ['TIT08','Rei do Continente',35,'Conquistar competição continental implementada.'],
    ['TIT09','Bola de Ouro',40,'Receber 1 Bola de Ouro reconhecida pelo motor.'],
    ['TIT10','Colecionador de Taças',70,'Conquistar 10 títulos de edições distintas na mesma carreira.']],
   'SOCIAL'=>[
    ['SOC01','Primeiro Amigo',5,'Ter 1 amizade real aceita e ativa.'],
    ['SOC02','Primeiro Convite',5,'Registrar 1 indicação válida convertida.'],
    ['SOC03','Meu Time',10,'Ter 5 amigos distintos aceitos e ativos simultaneamente.'],
    ['SOC04','Rede Crescendo',10,'Registrar 3 indicações válidas e únicas.'],
    ['SOC05','Grupo Fechado',10,'Ter 10 amigos distintos aceitos e ativos simultaneamente.'],
    ['SOC06','Olheiro Digital',15,'Registrar 5 indicações válidas e únicas.'],
    ['SOC07','Arquibancada Cheia',15,'Ter 25 amigos distintos aceitos e ativos simultaneamente.'],
    ['SOC08','Ponte de Talentos',20,'Registrar 10 indicações válidas e únicas.'],
    ['SOC09','Torcida Organizada',25,'Ter 50 amigos distintos aceitos e ativos simultaneamente.'],
    ['SOC10','Embaixador do Jogo',35,'Registrar 25 indicações válidas e únicas.']],
   'ESPECIAIS'=>[
    ['ESP01','Virada de Mesa',5,'Vencer após estar perdendo, com participação em campo.'],
    ['ESP02','Fiel à Camisa',5,'Completar 3 temporadas consecutivas no mesmo clube e carreira.'],
    ['ESP03','Superação',5,'Ser eleito destaque da partida começando como reserva.'],
    ['ESP04','Sem Medo',5,'Converter finalização classificada como difícil em jogo oficial.'],
    ['ESP05','Oportunista',5,'Marcar após 2 ações bem-sucedidas na mesma posse válida.'],
    ['ESP06','Da Base ao Topo',5,'Concluir promoção reconhecida e jogar na divisão superior na temporada seguinte.'],
    ['ESP07','Invencível',5,'Concluir temporada oficial invicta participando de ao menos metade dos jogos.'],
    ['ESP08','Herói da Última Hora',5,'Marcar gol de vitória aos 80 minutos ou depois, com placar verificável.'],
    ['ESP09','Ídolo de Dois Mundos',10,'Conquistar títulos oficiais em 2 países na mesma carreira.'],
    ['ESP10','The GOAT',100,'Conquistar 9 Bolas de Ouro verificadas em temporadas distintas na MESMA carreira.']]];
  $inactive=['TIT03'=>'Premiação individual ainda não implementada.','TIT05'=>'Premiação individual ainda não implementada.','TIT06'=>'Calendário com três títulos distintos na mesma temporada ainda não validado.','TIT08'=>'Competição continental sem adaptador auditável.','TIT09'=>'Bola de Ouro ainda não implementada.','ESP03'=>'Eleição de destaque ainda não implementada.','ESP10'=>'Bola de Ouro ainda não implementada.'];
  foreach(['SOC01','SOC03','SOC05','SOC07','SOC09'] as $id)$inactive[$id]='Amizades recíprocas aceitas indisponíveis na API atual.';
  foreach(['SOC02','SOC04','SOC06','SOC08','SOC10'] as $id)$inactive[$id]='API atual de indicações pode escrever no Hub; aguarda consulta somente de leitura.';
  $out=[];foreach($groups as $category=>$rows)foreach($rows as [$id,$name,$g,$description])$out[$id]=compact('id','name','g','description','category')+['secret'=>$category==='ESPECIAIS','active'=>!isset($inactive[$id]),'inactive_reason'=>$inactive[$id]??null,'xp_bonus'=>0];return $out;
 }
}
