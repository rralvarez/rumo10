<?php
declare(strict_types=1);
/** Federative venue input for isolated CLI tests; never an automatic public designation. */
final class StateVenueAdministration {
 public static function request(array &$t,array $c,array $phase,array $pairs):?array {
  $scores=array_map(fn($m)=>array_intersect_key($m,array_flip(['phase','round','home','away','status','hg','ag','home_red','away_red','home_yellow','away_yellow','penalties'])),$t['matches']);
  $request=['competition'=>$c['id'],'edition'=>$c['edition'],'phase'=>$phase['name'],'pairs'=>$pairs,'preserve_home'=>!empty($phase['venue_preserve_home']),'legs'=>$phase['legs'],'allowed_venues'=>$phase['venue_options']??[],'venue_scope'=>'entire_tie','contract'=>StateResultProof::digest($c),'results'=>StateResultProof::digest($scores),'source'=>$phase['venue_decision_source']];$key=StateResultProof::digest($request);$d=$t['venue_decisions'][$key]??null;
  if($d===null){$t['administrative_pending']=['key'=>$key,'phase'=>$phase['name'],'teams'=>array_merge(...$pairs),'reason'=>'federative_venue_requires_audited_designation','state'=>'awaiting_federative_venue','simulation_only'=>true,'source'=>$phase['venue_decision_source'],'request'=>$request];return null;}
  self::validate($d,$key,$request);$t['venue_decision_keys'][$key]=true;return $d;
 }
 private static function validate(array $d,string $key,array $request):void {
  $body=$d;unset($body['sha256']);if(($d['key']??null)!==$key||($d['kind']??null)!=='synthetic_test_designation'||!is_string($d['reference']??null)||trim($d['reference'])===''||strlen($d['reference'])>300||!hash_equals($d['sha256']??'',StateResultProof::digest($body))||!is_array($d['games']??null)||!array_is_list($d['games'])||count($d['games'])!==count($request['pairs']))throw new RuntimeException('Designação de recinto inválida.');
  foreach($request['pairs'] as $i=>$pair){$g=$d['games'][$i];if(!empty($request['allowed_venues'])&&!in_array($g['venue']??null,$request['allowed_venues'],true))throw new RuntimeException('Recinto não permitido pelo regulamento.');if(!empty($request['preserve_home'])&&([$g['home']??null,$g['away']??null]!==$pair||($g['neutral']??null)!==false))throw new RuntimeException('A designação não pode retirar o mando comprovado.');$clubs=[$g['home']??null,$g['away']??null];sort($clubs);sort($pair);if($clubs!==$pair||$clubs[0]===$clubs[1]||!is_bool($g['neutral']??null)||!is_string($g['venue']??null)||trim($g['venue'])===''||strlen($g['venue'])>160)throw new RuntimeException('Recinto ou participantes não correspondem ao pedido.');}
 }
 public static function designate(array &$t,array $input):void {
  if(PHP_SAPI!=='cli'||config('APP_ENV')==='production'||config('RUMO10_WORLD_FIXTURES','0')!=='1'||config('RUMO10_ADMIN_TEST_DESIGNATIONS','0')!=='1')throw new RuntimeException('Designação apenas em fixture CLI autorizada.');
  $input['kind']='synthetic_test_designation';unset($input['sha256']);$input['sha256']=StateResultProof::digest($input);$old=$t['venue_decisions'][$input['key']??'']??null;if($old!==null){if($old!==$input)throw new RuntimeException('Designação imutável conflitante.');return;}
  $p=$t['administrative_pending']??throw new RuntimeException('Não há designação pendente.');if(($p['state']??null)!=='awaiting_federative_venue'||($input['key']??null)!==$p['key'])throw new RuntimeException('Designação de outro pedido.');self::validate($input,$p['key'],$p['request']);$t['venue_decisions'][$p['key']]=$input;unset($t['administrative_pending']);
 }
}
