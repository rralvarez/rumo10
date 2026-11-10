<?php
declare(strict_types=1);
/** Opt-in local diagnostics. Never records SQL, parameters, names or credentials. */
final class RequestMetrics {
 private static array $samples=[];
 public static function start():int{return config('RUMO10_TIMING')==='1'?hrtime(true):0;}
 public static function end(string $name,int $start):void {if(!$start)return;$r=self::$samples[$name]??['count'=>0,'ms'=>0.];$r['count']++;$r['ms']+=(hrtime(true)-$start)/1e6;self::$samples[$name]=$r;}
 public static function read():array{return self::$samples;}
 public static function reset():void{self::$samples=[];}
 public static function headers():void {if(headers_sent()||config('RUMO10_TIMING')!=='1')return;$parts=[];foreach(self::$samples as $name=>$r)$parts[]=$name.';dur='.round($r['ms'],2);if($parts)header('Server-Timing: '.implode(', ',$parts));}
}
