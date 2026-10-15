<?php
declare(strict_types=1);
$file=$argv[1]??'';if(!is_file($file))throw new RuntimeException('Informe o .tar.gz produzido pelo build.');
$tar=new PharData($file);$manifest=json_decode($tar['release.json']->getContent(),true,512,JSON_THROW_ON_ERROR);$count=0;
foreach(new RecursiveIteratorIterator($tar) as $item){
 $path=str_replace('\\','/',$item->getPathname());$path=substr($path,strpos($path,'.tar.gz/')+8);
 if($path==='release.json')continue;
 if(!isset($manifest['files'][$path]) || hash('sha256',$item->getContent())!==$manifest['files'][$path])throw new RuntimeException('Manifesto divergente: '.$path);
 if(str_contains($path,'/.env') || preg_match('#(^|/)(\.git|private|sessions|logs|backups)(/|$)#',$path) || str_starts_with($path,'public/assets/clubs/'))throw new RuntimeException('Conteúdo proibido: '.$path);
 if(str_starts_with($path,'storage/') && !str_starts_with($path,'storage/media/'))throw new RuntimeException('Storage privado indevido.');
 if($manifest['project']==='rumo10' && (str_starts_with($path,'database/rosters/') || $path==='database/player-catalog.json'))throw new RuntimeException('Catálogo duplicado; produção usa o Hub.');
 $count++;
}
if($count!==count($manifest['files']) || empty($manifest['files']['public/index.php']) || empty($manifest['files']['bin/production-check.php']))throw new RuntimeException('Pacote incompleto.');
echo 'OK: '.$manifest['project'].' — '.$count.' arquivos e hashes; sem credenciais, sessões, backups ou escudos públicos legados.'.PHP_EOL;
