<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';$source=json_decode(file_get_contents('C:/inetpub/wwwroot/rumohub/database/ea-markets-2026.json'),true,512,JSON_THROW_ON_ERROR);$checks=0;
foreach($source['competitions'] as $id=>$c){$t=Competition::tournament($id);$n=count($c['teams']);if(count($t['matches'])!==$n*($n-1))throw new RuntimeException('Calendário incompleto: '.$id);foreach($t['matches'] as &$m){$m['status']='finalizada';$m['hg']=1;$m['ag']=0;}unset($m);WorldCompetition::next($t);if(!$t['done'] || !in_array($t['champion'],$c['teams'],true))throw new RuntimeException('Competição não finalizada: '.$id);$checks++;}
echo "OK: $checks campeonatos adicionais com calendário completo e campeão simulado, sem alterar carreiras.\n";
