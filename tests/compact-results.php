<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';$t=Competition::tournament('en-2');$club=$t['teams'][0];$human=$npc=null;
foreach($t['matches'] as $i=>&$match){$match['status']='finalizada';$match['hg']=1;$match['ag']=0;if($human===null && in_array($club,[$match['home'],$match['away']],true)){$human=$i;$match['stats']['goals']=1;$match['log']=[['minute'=>10,'text'=>'Gol do personagem.']];}elseif($npc===null && !in_array($club,[$match['home'],$match['away']],true)){$npc=$i;$match['stats']['goals']=2;$match['log']=[['minute'=>20,'text'=>'Dados futuros de outro atleta.']];}}unset($match);
$t['done']=true;$before=Competition::standings($t);$humanData=$t['matches'][$human];$npcData=$t['matches'][$npc];$bytes=strlen(json_encode($t));Competition::compactResults($t,$club);
if(Competition::standings($t)!==$before || $t['matches'][$human]!==$humanData || $t['matches'][$npc]!==$npcData || strlen(json_encode($t))>=$bytes*.8)throw new RuntimeException('Compactação perdeu dados úteis ou não economizou espaço.');
$once=$t;Competition::compactResults($t,$club);if($t!==$once)throw new RuntimeException('Compactação não idempotente.');echo "OK: placares e classificação preservados; dados do personagem e NPCs protegidos; compactação idempotente.\n";
