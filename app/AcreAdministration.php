<?php
declare(strict_types=1);
/** Explicit local fixture decisions. No public endpoint, implied neutrality or invented federation act. */
final class AcreAdministration {
 public static function pending(array $t,string $reason,string $phase,array $teams,array $context=[]):array {
  $teams=array_values($teams);sort($teams,SORT_STRING);$basis=['competition'=>$t['id'],'reason'=>$reason,'phase'=>$phase,'teams'=>$teams,'context'=>$context];
  return ['key'=>StateResultProof::digest($basis),'reason'=>$reason,'phase'=>$phase,'teams'=>$teams,'context'=>$context,'state'=>'awaiting_federation_decision','source'=>'REC AC2026 arts.7/9; designation not inferred','simulation_only'=>true];
 }
 public static function designate(array &$t,array $input):void {
  if(PHP_SAPI!=='cli'||config('APP_ENV')==='production'||config('RUMO10_WORLD_FIXTURES','0')!=='1'||config('RUMO10_ADMIN_TEST_DESIGNATIONS','0')!=='1')throw new RuntimeException('Designação AC somente em fixture CLI local.');
  $old=$t['ac_decisions'][$input['key']??'']??null;if($old){$audit=$old;unset($audit['sha256']);if(!hash_equals($old['sha256']??'',StateResultProof::digest($audit)))throw new RuntimeException('Decisão AC persistida corrompida.');$body=$old;foreach(['schema','recorded_at','sha256'] as $f)unset($body[$f]);if(StateResultProof::digest($body)!==StateResultProof::digest($input))throw new RuntimeException('Replay AC divergente.');return;}
  $input['schema']='acre-fixture-decision-v1';$input['recorded_at']=gmdate('Y-m-d\TH:i:s\Z');$input['sha256']=StateResultProof::digest($input);self::replay($t,$input);
 }
 public static function replay(array &$t,array $d):void {
  if(($t['adapter_version']??null)!==ConfiguredStateCompetition::ACRE_VERSION)throw new RuntimeException('Decisão fora do contrato AC.');$p=$t['administrative_pending']??throw new RuntimeException('Sem pendência AC.');$body=$d;unset($body['sha256']);
  if(($d['schema']??null)!=='acre-fixture-decision-v1'||($d['kind']??null)!=='synthetic_test_designation'||empty($d['reference'])||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',$d['recorded_at']??'')||($d['key']??null)!==$p['key']||!hash_equals($d['sha256']??'',StateResultProof::digest($body)))throw new RuntimeException('Auditoria AC inválida.');
  if(isset($t['ac_decisions'][$p['key']]))throw new RuntimeException('Decisão AC já aplicada.');
  if($p['reason']==='ac_final_venues_require_designation'){
   $keys=array_keys($d['games']??[]);sort($keys);if($keys!==['bronze','final'])throw new RuntimeException('Final e bronze devem ser designados juntos.');
   foreach($d['games'] as $phase=>$game){self::venue($game);$pair=[$game['home'],$game['away']];sort($pair);$expected=$p['context'][$phase];sort($expected);if($pair!==$expected)throw new RuntimeException('Participantes AC divergentes.');}
  }elseif($p['reason']==='ac_extra_game_requires_designation'){
   if(count($p['teams'])!==2||count(array_filter($t['matches'],fn($m)=>isset($m['ac_administrative_key'])))>=8)throw new RuntimeException('Formato extra múltiplo não definido ou orçamento esgotado.');
   $game=$d['game']??[];self::venue($game);$pair=[$game['home'],$game['away']];sort($pair);if($pair!==$p['teams'])throw new RuntimeException('Extra AC com clubes divergentes.');
   $round=max(array_column($t['matches'],'round'))+1;$t['matches'][]=Schedule::match($round,$game['home'],$game['away'])+['phase'=>'desempate_administrativo','group'=>'extra','ac_administrative_key'=>$p['key'],'ac_decision_digest'=>$d['sha256'],'neutral'=>$game['neutral'],'venue'=>$game['venue']];$t['phase_end']=$round;
  }else throw new RuntimeException('Pendência AC não suportada.');
  $t['ac_decisions'][$p['key']]=$d;unset($t['administrative_pending']);
 }
 private static function venue(array $game):void {if(empty($game['home'])||empty($game['away'])||$game['home']===$game['away']||!is_bool($game['neutral']??null)||!is_string($game['venue']??null)||trim($game['venue'])===''||mb_strlen($game['venue'])>150)throw new RuntimeException('Mando e recinto AC devem ser explícitos.');}
 public static function rank(array $t,array $rows,string $phase,bool $certify):array {
  $teams=array_column($rows,'team');$scores=[];foreach($t['matches'] as $m)if($phase==='campaign'||$m['phase']===$phase)$scores[]=[$m['round'],$m['home'],$m['away'],$m['hg'],$m['ag'],$m['home_red']??0,$m['away_red']??0,$m['home_yellow']??0,$m['away_yellow']??0];
  $p=self::pending($t,'ac_extra_game_requires_designation',$phase,$teams,['results'=>StateResultProof::digest($scores)]);$d=$t['ac_decisions'][$p['key']]??null;
  if($d){$body=$d;unset($body['sha256']);if(!hash_equals($d['sha256']??'',StateResultProof::digest($body)))throw new RuntimeException('Decisão extra AC corrompida.');foreach(array_merge($t['matches'],$t['ac_extra_matches']??[]) as $m)if(($m['ac_administrative_key']??null)===$p['key']&&$m['status']==='finalizada'){
   $game=$d['game'];if([$m['home'],$m['away'],$m['neutral']??null,$m['venue']??null,$m['ac_decision_digest']??null]!==[$game['home'],$game['away'],$game['neutral'],$game['venue'],$d['sha256']])throw new RuntimeException('Extra diverge da decisão AC.');
   $winner=$m['hg']>$m['ag']?$m['home']:($m['ag']>$m['hg']?$m['away']:null);if(!$winner){$pens=$m['penalties']??null;if(is_array($pens)&&count($pens)===2&&is_int($pens[0])&&is_int($pens[1])&&min($pens)>=0&&$pens[0]!==$pens[1])$winner=$pens[0]>$pens[1]?$m['home']:$m['away'];}elseif(isset($m['penalties']))throw new RuntimeException('Pênaltis AC indevidos.');
   if($winner){usort($rows,fn($a,$b)=>$a['team']===$winner?-1:1);return $rows;}
  }}
  if(!$certify)return $rows;throw new StateAdministrativeTie($p);
 }
 public static function settle(array &$t,bool $proof):void {
  foreach($t['matches'] as &$m)if(isset($m['ac_administrative_key'])&&$m['status']==='finalizada'&&$m['hg']===$m['ag']){
   if(!isset($m['penalties'])){if($proof)throw new RuntimeException('Pênaltis extra AC não comprovados.');$view=['phase'=>$m['phase'],'matches'=>[$m+['tie'=>0]],'ties'=>[['a'=>$m['home'],'b'=>$m['away'],'winner'=>null,'rule'=>'penalties']]];Competition::resolve($view);$m['penalties']=$view['matches'][0]['penalties'];}
  }unset($m);
 }
}
