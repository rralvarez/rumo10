<?php
declare(strict_types=1);
// Somente GET: não cria usuários, não altera carreiras nem roda fixtures em produção.
if(PHP_SAPI!=='cli')exit(1);
$base=rtrim($argv[1]??'','/');$project=$argv[2]??'';
if(!preg_match('#^https://[a-zA-Z0-9.-]+$#',$base) || !in_array($project,['rumo10','rumohub'],true))throw new RuntimeException('Uso: production-smoke.php https://dominio rumo10|rumohub');
$cases=['/'=>[200],'/.env'=>[403,404],'/.git/config'=>[403,404],'/storage/private/clients.php'=>[403,404],'/app/bootstrap.php'=>[403,404],'/README.md'=>[403,404],'/assets/clubs/test.png'=>[403,404]];
$cases[$project==='rumo10'?'/login':'/admin/login']=[200];
if($project==='rumohub')$cases['/api/v1/catalog']=[401];
foreach($cases as $path=>$expected){
 $curl=curl_init($base.$path);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false]);
 $body=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
 if($body===false || !in_array($status,$expected,true))throw new RuntimeException('Smoke falhou em '.$path.' (HTTP '.$status.').');
 echo $path.' : '.$status.' OK'.PHP_EOL;
}
echo "Smoke público HTTPS passou. Ainda confira login, cookie Secure, jogo e API assinada no servidor.\n";
