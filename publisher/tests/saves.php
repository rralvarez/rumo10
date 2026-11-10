<?php
declare(strict_types=1);
require dirname(__DIR__).'/lib/Core.php';
$dir=__DIR__.'/v3-runtime-'.bin2hex(random_bytes(4));mkdir($dir,0700);
\Rumo\Publisher\Package::inspect((getenv('REPRESENTATIVE_RUMO10')?:'C:/Users/rodri/AppData/Local/Temp/rumo10-release62-final-786zub0y/rumo10-entrega62-selective.tar.gz'),'rumo10',$dir);
spl_autoload_register(function($n)use($dir){if(preg_match('/^[A-Za-z]+$/D',$n)&&is_file($dir.'/app/'.$n.'.php'))require $dir.'/app/'.$n.'.php';});
function config(string $key,string $default=''):string{return in_array($key,['RUMOHUB_ENABLED','RUMOHUB_BENEFITS','RUMO10_ACHIEVEMENTS','RUMO10_WORLD_CALENDAR'],true)?'0':$default;}
function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
set_error_handler(function($l,$m,$f,$n){throw new ErrorException($m,0,$l,$f,$n);});
$checks=0;function ok(bool $b):void{global $checks;$checks++;if(!$b)throw new RuntimeException('save v3 assertion '.$checks);}
$db=new SQLite3($dir.'/synthetic.sqlite');$db->exec('CREATE TABLE saves(id INTEGER PRIMARY KEY,state TEXT)');
foreach(['normal','rapido','expresso'] as $mode) {
 $g=Game::legacyCreate('Synthetic continuity','São Paulo','SP','meia');
 ok($g['age']===16);$legacy=$g;ok(!PlayerDevelopment::enabled($legacy));
 $g['play_mode']=$mode;PlayerDevelopment::initialize($g,'same-seed');$g['stage']='treino';
 Progression::distribute($g,150,'match:synthetic:1');
 $bytes=json_encode($g,JSON_THROW_ON_ERROR);
 $q=$db->prepare('INSERT INTO saves(state) VALUES(:s)');$q->bindValue(':s',$bytes);$q->execute();$id=$db->lastInsertRowID();
 $loaded=json_decode($db->querySingle('SELECT state FROM saves WHERE id='.$id),true,512,JSON_THROW_ON_ERROR);
 ok($loaded===$g);ok(PlayerDevelopment::enabled($loaded));
 $before=$loaded;Progression::distribute($loaded,150,'match:synthetic:1');ok($loaded===$before);
 ok(array_sum($g['player_development']['received'])===array_sum($g['player_development']['spent'])+array_sum(array_column($g['skills'],'xp')));
 $initial=$loaded['player_development'];Progression::distribute($loaded,150,'match:synthetic:2');
 ok(array_sum($loaded['player_development']['received'])>=array_sum($initial['received']));
}
echo json_encode(['passed'=>$checks,'runtime_package'=>'digest-pinned test input; see runner provenance','database'=>'isolated SQLite JSON; NOT MySQL Repository integration']).PHP_EOL;
