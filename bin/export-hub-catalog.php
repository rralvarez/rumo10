<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
putenv('RUMOHUB_ENABLED=0'); // A exportação inicial usa exclusivamente o catálogo legado com IDs locais.
// Exportação local: somente leitura do catálogo; nenhum dado de usuário ou carreira.
$target=$argv[1]??'';
if(realpath($target)!==realpath('C:/inetpub/wwwroot/rumohub'))throw new RuntimeException('Destino não autorizado.');
$db=Database::connect();$ids=[];foreach($db->query('SELECT id,codigo FROM rumo10_catalogo_clubes') as $c)$ids[$c['codigo']]=(int)$c['id'];
$clubs=Competition::clubs();foreach($clubs as $code=>$club)if(!isset($ids[$code]))throw new RuntimeException('Clube sem ID estável.');
$data=['clubs'=>$clubs,'club_ids'=>array_intersect_key($ids,$clubs),'competitions'=>Competition::catalog(),'badges'=>json_decode(file_get_contents(dirname(__DIR__).'/database/club-badges.json'),true,512,JSON_THROW_ON_ERROR),'player_catalog'=>ClubSquad::catalog(),'checked'=>ClubSquad::catalog()['summary']['checked']];
$folder=$target.'/storage/import';if(!is_dir($folder))mkdir($folder,0770,true);
file_put_contents($folder.'/catalog.json',json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
echo 'OK: catálogo exportado, sem contas ou carreiras. '.count($clubs)." clubes.\n";
