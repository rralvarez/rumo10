<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function afCheck(bool $v,string $label):void{global $checks;$checks++;if(!$v)throw new RuntimeException($label);}
$comps=json_decode(file_get_contents(dirname(__DIR__).'/database/africa-south-competitions-2026.json'),true);$clubs=json_decode(file_get_contents(dirname(__DIR__).'/database/africa-south-clubs-2026.json'),true);$logos=json_decode(file_get_contents(dirname(__DIR__).'/database/club-badges.json'),true);
foreach($clubs as $c){afCheck(isset(Competition::clubs()[$c['name']]),'Cadastro');afCheck(TeamStrength::initial($c['name'])>=20 && TeamStrength::initial($c['name'])<=95,'Overall');if(($c['badge_status']??'')==='pending_verified_asset')afCheck(!isset($logos[$c['name']]),'Não exibe foto/ícone genérico como escudo');else afCheck(isset($logos[$c['name']]) && is_file(dirname(__DIR__).'/public'.$logos[$c['name']]['path']),'Escudo com arquivo');}
foreach($comps as $c){$t=Competition::tournament($c['id']);Competition::finish($t);afCheck($t['done'] && in_array($t['champion'],$c['teams'],true),'Campeonato/circuito completo');foreach($t['matches'] as $m)afCheck($m['status']==='finalizada','Partida finalizada');}
foreach(['BR','AR','BO','CL','CO','EC','PY','PE','UY','VE','GY','SR'] as $cc)afCheck((bool)array_filter(Competition::clubs(),fn($c)=>($c['country']??'BR')===$cc),'País sul-americano '.$cc);
echo "OK: $checks verificações África/América do Sul.\n";
