<?php
declare(strict_types=1);
/** Complemento mínimo de regras/participantes do jogo; não modifica o Hub. */
final class LocalCompetitionCatalog {
 public static function data():array{static $d;return $d??=json_decode(file_get_contents(dirname(__DIR__).'/database/local-competition-fixes.json'),true,512,JSON_THROW_ON_ERROR);}
 public static function competitions(array $base):array{$c=self::data()['competition'];$base[$c['id']]??=$c;return $base;}
 public static function clubs(array $base):array {
  foreach(self::data()['missing_clubs'] as $name)$base[$name]??=['name'=>$name,'display_name'=>$name,'country'=>'BR','uf'=>'AM','competitions'=>['am-a1'],'game_only'=>true,'source'=>self::data()['sources'][0],'rating_origin'=>'unrated_collective_fallback','badge_path'=>null];
  foreach(self::data()['competition']['teams'] as $name)$base[$name]['competitions']=array_values(array_unique(array_merge($base[$name]['competitions']??[],['am-a1'])));
  return $base;
 }
}
