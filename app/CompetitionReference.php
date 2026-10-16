<?php
declare(strict_types=1);
/** Versioned reference snapshots. No global catalog mutation or save backfill. */
final class CompetitionReference {
 public static function pin(array $definition,string $ruleVersion,string $knowledge='simulation_adaptation'):array {
  if(!preg_match('/^[a-z0-9][a-z0-9_-]*$/D',$definition['id']??'')||$ruleVersion==='')throw new InvalidArgumentException('Identidade e versão da edição são obrigatórias.');
  if(!in_array($knowledge,['official_dated','simulation_adaptation','estimate','unknown'],true))throw new InvalidArgumentException('Classificação de evidência inválida.');
  if($knowledge==='official_dated'&&empty($definition['verified_sources']))throw new InvalidArgumentException('Regra oficial exige fonte primária verificada.');
  $teams=$definition['teams']??[];if(count($teams)<2||count(array_unique($teams))!==count($teams))throw new InvalidArgumentException('Participantes ausentes ou duplicados.');
  $edition=['digest_version'=>2,'definition_id'=>$definition['id'],'reference_edition'=>$definition['edition']??null,'rule_version'=>$ruleVersion,'knowledge'=>$knowledge,'definition'=>$definition,'participation'=>$teams];
  $edition['sha256']=self::digest($edition);return $edition;
 }
 public static function verify(array $edition):bool {$hash=$edition['sha256']??'';unset($edition['sha256']);$version=$edition['digest_version']??1;if(!in_array($version,[1,2],true))return false;return hash_equals($hash,$version===2?self::digest($edition):hash('sha256',json_encode($edition,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)));}
 private static function digest(array $value):string {
  $normalize=function(mixed $v)use(&$normalize):mixed{if(!is_array($v))return $v;if(!array_is_list($v))ksort($v,SORT_STRING);foreach($v as &$item)$item=$normalize($item);unset($item);return $v;};
  return hash('sha256',json_encode($normalize($value),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
 }
 /** Links qualify editions or move divisions; they are never interchangeable. */
 public static function graph(array $definitions,array $links):array {
  $errors=[];$promotions=[];$seen=[];
  foreach($links as $link){$from=$link['from']??'';$to=$link['to']??'';$kind=$link['kind']??'';$key=$from.'|'.$to.'|'.$kind;
   if(isset($seen[$key]))$errors[]=['code'=>'duplicate_link','key'=>$key];$seen[$key]=true;
   if(!isset($definitions[$from],$definitions[$to])){$errors[]=['code'=>'missing_node','key'=>$key];continue;}
   if(!in_array($kind,['promotion','relegation','qualification'],true))$errors[]=['code'=>'invalid_link_kind','key'=>$key];
   if(($link['slots']??0)<1||($link['slots']??0)>count($definitions[$from]['teams']??[]))$errors[]=['code'=>'invalid_slots','key'=>$key];
   if(empty($link['verified_sources']))$errors[]=['code'=>'unverified_link','key'=>$key];
   if($kind==='promotion'){
    if(empty($definitions[$from]['pyramid'])||($definitions[$from]['pyramid']??null)!==($definitions[$to]['pyramid']??null)||($definitions[$from]['scope']??null)!==($definitions[$to]['scope']??null)||($definitions[$from]['country']??null)!==($definitions[$to]['country']??null))$errors[]=['code'=>'different_pyramid','key'=>$key];
    if(array_intersect($definitions[$from]['teams']??[],$definitions[$to]['teams']??[]))$errors[]=['code'=>'club_in_two_divisions','key'=>$key];
    $promotions[$from][]=$to;
   }
  }
  $visit=function(string $id,array $path)use(&$visit,&$errors,$promotions):void{if(isset($path[$id])){$errors[]=['code'=>'promotion_cycle','node'=>$id];return;}$path[$id]=true;foreach($promotions[$id]??[] as $next)$visit($next,$path);};foreach(array_keys($promotions) as $id)$visit($id,[]);
  return $errors;
 }
}
