<?php
declare(strict_types=1);
namespace Rumo\Publisher;
require_once __DIR__.'/Core.php';
/** Shared by ALL web/CLI writers in one hosting account. Never auto-clear a closed gate. */
final class RequestDrain {
 private string $root;private mixed $exclusive=null;private ?string $owner=null;
 private static array $requestLeases=[];
 public static function retainThroughRequestShutdown($lease):void{
  need(is_resource($lease),'Request lease missing');self::$requestLeases[]=$lease;
  // No explicit fclose callback: retain through user shutdown functions and object destructors.
 }

 public static function provision(string $root):void{
  need(!file_exists($root),'Provision only a NEW private gate directory');privateDir($root);
  $f=fopen($root.'/writers.lock','xb');need($f!==false,'Gate creation failed');chmod($root.'/writers.lock',0600);fflush($f);fsync($f);fclose($f);
  $s=lstat($root.'/writers.lock');record($root.'/identity.json',['dev'=>$s['dev'],'ino'=>$s['ino']]);
 }
 public function __construct(string $root){
  need(is_dir($root)&&!is_link($root),'Existing physical gate required');$this->root=$root;$this->identity();
 }
 private function identity():array{
  clearstatcache(true,$this->root.'/writers.lock');$p=$this->root.'/writers.lock';
  need(is_file($p)&&!is_link($p),'Gate file missing/linked');$s=lstat($p);$id=json(file_get_contents($this->root.'/identity.json'));
  need($s['nlink']===1 && $s['dev']===$id['dev'] && $s['ino']===$id['ino'],'Gate identity changed');return $s;
 }
 private function open(){
  $s=$this->identity();$f=fopen($this->root.'/writers.lock','r+b');need($f!==false,'Cannot open gate');$fd=fstat($f);
  need($fd['dev']===$s['dev']&&$fd['ino']===$s['ino'],'Gate changed while opening');return $f;
 }
 public function closed():bool{clearstatcache(true,$this->root.'/closed.json');return file_exists($this->root.'/closed.json');}
 /** Caller MUST retain this resource through the final DB/file write, until request shutdown. */
 public function enter(){
  need(!$this->closed(),'Maintenance: new writers refused');$f=$this->open();
  if(!flock($f,LOCK_SH|LOCK_NB)){fclose($f);throw new \RuntimeException('Maintenance: installer owns gate');}
  if($this->closed()){flock($f,LOCK_UN);fclose($f);throw new \RuntimeException('Maintenance: gate closed during admission');}
  return $f;
 }
 public function close(string $owner):void{
  need(preg_match('/^[a-f0-9]{32}$/D',$owner)===1,'Invalid attempt');$this->identity();
  $p=$this->root.'/closed.json';$f=fopen($p,'xb');need($f!==false,'Pending/foreign gate retained');chmod($p,0600);
  $bytes=json_encode(['schema'=>'rumo-request-drain-v1','owner'=>$owner],JSON_THROW_ON_ERROR);
  need(fwrite($f,$bytes)===strlen($bytes)&&fflush($f)&&fsync($f),'Cannot durably close gate');fclose($f);$this->owner=$owner;
 }
 public function resume(string $owner):void{
  $this->identity();$j=json(file_get_contents($this->root.'/closed.json'));need(($j['owner']??null)===$owner,'Foreign gate owner');$this->owner=$owner;
 }
 public function drain(float $timeout):void{
  need($this->owner!==null && $this->closed(),'Close gate before draining');$this->assertOwner();$f=$this->open();$deadline=microtime(true)+max(0,$timeout);
  do{if(flock($f,LOCK_EX|LOCK_NB)){$this->exclusive=$f;$this->assertOwner();return;}usleep(10000);}while(microtime(true)<$deadline);
  fclose($f);throw new \RuntimeException('Drain timeout: maintenance retained; no installation permitted');
 }
 public function assertDrained():void{need(is_resource($this->exclusive),'Exclusive drain lease missing');$this->identity();$this->assertOwner();}
 private function assertOwner():void{need($this->owner!==null && (json(file_get_contents($this->root.'/closed.json'))['owner']??null)===$this->owner,'Gate ownership lost');}
 public function release():void{
  $this->assertDrained();need(unlink($this->root.'/closed.json'),'Cannot release own gate');flock($this->exclusive,LOCK_UN);fclose($this->exclusive);$this->exclusive=null;$this->owner=null;
 }
 public function __destruct(){if(is_resource($this->exclusive)){flock($this->exclusive,LOCK_UN);fclose($this->exclusive);}/* Marker intentionally survives errors/process death. */}
}
