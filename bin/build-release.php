<?php
declare(strict_types=1);
// Empacota somente arquivos versionados; nunca lê .env ou o armazenamento privado.
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);$project=is_file($root.'/app/HubDatabase.php')?'rumohub':'rumo10';
$output=$argv[1]??($root.'/storage/deploy/'.$project.'-'.gmdate('Ymd-His').'.tar');
if(!str_ends_with($output,'.tar') || file_exists($output) || file_exists($output.'.gz'))throw new RuntimeException('Informe um arquivo .tar novo.');
$parent=dirname($output);if(!is_dir($parent) && !mkdir($parent,0700,true))throw new RuntimeException('Pasta de saída indisponível.');
$git=proc_open(['git','-C',$root,'ls-files','-z'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
if(!is_resource($git))throw new RuntimeException('Git indisponível.');
$list=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
if(proc_close($git)!==0)throw new RuntimeException('Não foi possível listar arquivos versionados.');
$tar=new PharData($output);$manifest=[];$files=[];
foreach(explode("\0",$list) as $path){
 if(!preg_match('#^(app|bin|database|public|deploy)/#',$path) && !($project==='rumohub' && str_starts_with($path,'storage/media/')))continue;
 // Produção do jogo usa o catálogo autenticado do Hub; arquivos locais continuam no checkout IIS.
 if($project==='rumo10' && (str_starts_with($path,'database/rosters/') || $path==='database/player-catalog.json'))continue;
 // Escudos legados não são publicados por assets.
 if(str_starts_with($path,'public/assets/clubs/') || $path==='public/web.config' || str_ends_with($path,'.example'))continue;
 if(str_starts_with($path,'database/production/'))continue; // SQL manual nunca é enviado pelo deploy.
 if(str_contains($path,'..') || is_link($root.'/'.$path) || !is_file($root.'/'.$path))throw new RuntimeException('Arquivo versionado inválido.');
 $files[$path]=$root.'/'.$path;$manifest[$path]=hash_file('sha256',$root.'/'.$path);
}
foreach(['public/index.php','public/.htaccess','app/bootstrap.php','bin/deploy-cpanel.sh'] as $required)if(!isset($manifest[$required]))throw new RuntimeException('Preparação de deploy precisa estar versionada antes de empacotar.');
$tar->buildFromIterator(new ArrayIterator($files));
$tar->addFromString('release.json',json_encode(['project'=>$project,'files'=>$manifest],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
$tar->compress(Phar::GZ);unset($tar);unlink($output);
echo 'Pacote sem credenciais: '.$output.'.gz'.PHP_EOL;
