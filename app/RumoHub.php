<?php
declare(strict_types=1);
/** Cliente exclusivamente no servidor. Segredo nunca é enviado ao navegador. */
final class RumoHub {
 public static function enabled():bool{return config('RUMOHUB_ENABLED','0')==='1';}
 public static function fetch(string $target,int $limit=8388608):array {
  $base=rtrim(config('RUMOHUB_URL'),'/');$u=parse_url($base);$local=in_array($u['host']??'', ['rumohub.local','127.0.0.1','localhost'],true);
  if(!$u || isset($u['user']) || isset($u['pass']) || isset($u['query']) || isset($u['fragment']) || !empty($u['path']) || !in_array($u['scheme']??'', ['http','https'],true) || (($u['scheme']??'')!=='https' && (!$local || config('APP_ENV')==='production')))throw new RuntimeException('Endereço do RumoHub inválido.');
  if(!str_starts_with($target,'/api/v1/'))throw new RuntimeException('Endpoint inválido.');
  $id=config('RUMOHUB_CLIENT');$secret=config('RUMOHUB_SECRET');if(!preg_match('/^[a-z][a-z0-9_-]{1,31}$/',$id) || !preg_match('/^[a-f0-9]{64}$/',$secret))throw new RuntimeException('Configure o cliente privado do RumoHub.');
  $time=(string)time();$nonce=bin2hex(random_bytes(16));$message=implode("\n",['GET',$target,hash('sha256',''),$id,$time,$nonce]);$body='';$version='';$curl=curl_init($base.$target);
  curl_setopt_array($curl,[CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['X-Rumo-Client: '.$id,'X-Rumo-Timestamp: '.$time,'X-Rumo-Nonce: '.$nonce,'X-Rumo-Signature: '.hash_hmac('sha256',$message,$secret)],CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body,$limit):int{if(strlen($body)+strlen($chunk)>$limit)return 0;$body.=$chunk;return strlen($chunk);}]);
  curl_setopt($curl,CURLOPT_HEADERFUNCTION,static function($ch,string $line)use(&$version):int{if(preg_match('/^X-Rumo-Catalog-Version:\s*([a-f0-9]{64})\s*$/i',$line,$m))$version=$m[1];return strlen($line);});
  $ok=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$type=curl_getinfo($curl,CURLINFO_CONTENT_TYPE);if($ok===false || $status!==200)throw new RuntimeException('RumoHub indisponível (HTTP '.(int)$status.').');return ['body'=>$body,'type'=>$type,'version'=>$version];
 }
 public static function folder():string{$dir=dirname(__DIR__).'/storage/hub-cache';if(!is_dir($dir) && !mkdir($dir,0770,true) && !is_dir($dir))throw new RuntimeException('Cache do RumoHub indisponível.');return $dir;}
 public static function save(string $file,string $body):void{$tmp=tempnam(dirname($file),'hub-');try{if(file_put_contents($tmp,$body)===false || !rename($tmp,$file))throw new RuntimeException('Não foi possível salvar o cache.');}finally{if(is_file($tmp))unlink($tmp);}}
 public static function catalog():array {
  static $data;if($data!==null)return $data;$file=self::folder().'/catalog.json';if(!is_file($file))throw new RuntimeException('Sincronize o catálogo do RumoHub antes de habilitar.');$data=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
  if(($data['schema']??0)!==1 || !preg_match('/^[a-f0-9]{64}$/',$data['version']??'') || empty($data['clubs']) || empty($data['competitions']))throw new RuntimeException('Cache do RumoHub inválido.');return $data;
 }
 public static function players(string $club):array {
  $catalog=self::catalog();if(!isset($catalog['clubs'][$club]))throw new InvalidArgumentException('Clube ausente no Hub.');$file=self::folder().'/squad-'.hash('sha256',$catalog['version'].'|'.$club).'.json';
  if(is_file($file))$body=file_get_contents($file);else{$r=self::fetch('/api/v1/squad?club='.rawurlencode($club),1048576);if($r['version']!==$catalog['version'])throw new RuntimeException('Sincronize o catálogo atualizado do Hub.');$body=$r['body'];$p=json_decode($body,true,512,JSON_THROW_ON_ERROR);if(($p['club']??'')!==$club || !is_array($p['players']??null))throw new RuntimeException('Elenco do Hub divergente.');self::save($file,$body);}
  return json_decode($body,true,512,JSON_THROW_ON_ERROR)['players'];
 }
 public static function badge(string $club):never {
  $c=self::catalog();$path=$c['clubs'][$club]['badge_path']??null;if(!$path){http_response_code(404);exit;}$ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));$type=match($ext){'png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif','jpg','jpeg'=>'image/jpeg','svg'=>'image/svg+xml',default=>throw new RuntimeException('Formato inválido.')};
  $file=self::folder().'/badge-'.hash('sha256',$c['version'].'|'.$club).'.'.$ext;if(!is_file($file)){$r=self::fetch('/api/v1/badge?club='.rawurlencode($club),2097152);if($r['version']!==$c['version'])throw new RuntimeException('Sincronize o catálogo atualizado do Hub.');if($r['type']!==$type)throw new RuntimeException('Mídia divergente.');self::save($file,$r['body']);}
  header('Content-Type: '.$type);header('Cache-Control: private, max-age=86400');header('Content-Length: '.filesize($file));readfile($file);exit;
 }
}
