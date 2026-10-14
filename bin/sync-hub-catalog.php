<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';ini_set('zend.exception_ignore_args','1');
try{$r=RumoHub::fetch('/api/v1/catalog');$c=json_decode($r['body'],true,512,JSON_THROW_ON_ERROR);if(($c['schema']??0)!==1 || !preg_match('/^[a-f0-9]{64}$/',$c['version']??'') || empty($c['clubs']) || empty($c['competitions']) || empty($c['player_catalog']['clubs']))throw new RuntimeException('Catálogo inválido.');
 $ids=[];foreach($c['clubs'] as $code=>$club){if(($club['name']??'')!==$code || !is_int($club['hub_id']??null) || isset($ids[$club['hub_id']]))throw new RuntimeException('IDs divergentes.');$ids[$club['hub_id']]=true;}
 foreach($c['competitions'] as $comp)foreach($comp['teams'] as $club)if(!isset($c['clubs'][$club]))throw new RuntimeException('Participante ausente.');
 RumoHub::save(RumoHub::folder().'/catalog.json',$r['body']);
 // Remover somente caches gerados pela ponte, de versões antigas. Nunca arquivos de carreira.
 $valid=[];foreach($c['clubs'] as $club=>$data){$hash=hash('sha256',$c['version'].'|'.$club);$valid['squad-'.$hash.'.json']=true;if($data['badge_path']??null)$valid['badge-'.$hash.'.'.strtolower(pathinfo($data['badge_path'],PATHINFO_EXTENSION))]=true;}
 foreach(new DirectoryIterator(RumoHub::folder()) as $file)if($file->isFile() && !$file->isLink() && preg_match('/^(squad-[a-f0-9]{64}\.json|badge-[a-f0-9]{64}\.(png|webp|gif|jpg|jpeg|svg))$/',$file->getFilename()) && !isset($valid[$file->getFilename()]))unlink($file->getPathname());
 echo 'OK: '.count($c['clubs']).' clubes e '.count($c['competitions'])." competições sincronizados.\n";
}catch(Throwable $e){fwrite(STDERR,'Sincronização não concluída: '.$e::class.' código '.$e->getCode().PHP_EOL);exit(1);}
