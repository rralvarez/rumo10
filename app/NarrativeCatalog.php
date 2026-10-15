<?php
declare(strict_types=1);
final class NarrativeCatalog {
 public const MODULES=['T01'=>'treino','T02'=>'treino','P01'=>'pre_jogo','J01'=>'partida','J02'=>'partida','J03'=>'partida','F01'=>'fim_semana','F02'=>'fim_semana'];
 public const CODES=['passe','chute','drible','desarme','tecnica','tatico','posicionamento','visao','forca','resistencia','velocidade','recuperar','apoio','liderar','disciplina','imprensa','lazer','ousar','reclamar','social'];
 public static function all():array {
  static $all;if($all!==null)return $all;$all=[];
  foreach(glob(dirname(__DIR__).'/database/scenes/*.json') as $file){
   $batch=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
   foreach($batch as $s){$errors=self::validate($s);if($errors)throw new LogicException(basename($file).': '.implode('; ',$errors));if(isset($all[$s['id']]))throw new LogicException('ID narrativo duplicado');$all[$s['id']]=$s;}
  }return $all;
 }
 public static function validate(array $s):array {
  $errors=[];
  foreach(['id','stage','module','family','tags','gating','weight','rarity','cooldown','text','options','source_type','schema_version','enabled'] as $k)if(!array_key_exists($k,$s))$errors[]='ausente '.$k;
  if($errors)return $errors;
  if(!is_int($s['id']) || $s['id']<2000)$errors[]='ID reservado';
  if((self::MODULES[$s['module']]??null)!==$s['stage'])$errors[]='módulo/stage';
  if(!preg_match('/^[A-Z][0-9]{2}\.[a-z0-9_]+$/',$s['family']) || !str_starts_with($s['family'],$s['module'].'.'))$errors[]='família';
  if($s['source_type']!=='ficcao_editorial' || $s['schema_version']!==1 || !is_bool($s['enabled']))$errors[]='proveniência/schema';
  if(!is_int($s['weight']) || $s['weight']<1 || $s['weight']>100 || !in_array($s['rarity'],['common','rare'],true) || !is_int($s['cooldown']) || $s['cooldown']<1 || $s['cooldown']>20)$errors[]='frequência';
  $tags=['fundamento','coletivo','ataque','defesa','transicao','bola_parada','pressao','recuperacao','imprensa','vida_pessoal','fair_play','concentracao','finalizacao','comunicacao'];
  if(!is_array($s['tags']) || !$s['tags'] || array_diff($s['tags'],$tags))$errors[]='tags';
  $gates=['positions_allow','positions_deny','home_away','score_state','margin_max','minute_min','minute_max','played_minutes_min','starter','bench','bench_confirmed','finished','injury_risk_min','disciplinary_risk_min','recent_form','own_goals_min','own_cards_min','future_competition',...NarrativeContext::UNSUPPORTED];
  if(!is_array($s['gating']))return [...$errors,'gating não é objeto'];
  foreach($s['gating'] as $k=>$v){
   if(!in_array($k,$gates,true)){$errors[]='gating desconhecido '.$k;continue;}
   if((in_array($k,NarrativeContext::UNSUPPORTED,true) || $k==='future_competition') && $s['enabled'])$errors[]='fonte não modelada ativa '.$k;
   if(in_array($k,['positions_allow','positions_deny'],true) && (!is_array($v) || !$v || array_diff($v,Rules::POSITIONS)))$errors[]='posição';
   if(in_array($k,['starter','bench','bench_confirmed','finished'],true) && !is_bool($v))$errors[]='booleano '.$k;
   if(in_array($k,['minute_min','minute_max','margin_max','played_minutes_min','injury_risk_min','disciplinary_risk_min','own_goals_min','own_cards_min'],true) && (!is_int($v) || $v<0 || $v>100))$errors[]='limite '.$k;
   if($k==='score_state' && !in_array($v,['ganhando','empatando','perdendo'],true))$errors[]='placar';
   if($k==='home_away' && !in_array($v,['home','away'],true))$errors[]='mando';
   if($k==='recent_form' && !in_array($v,['boa','regular','ruim'],true))$errors[]='forma';
  }
  if(isset($s['gating']['minute_min'],$s['gating']['minute_max']) && $s['gating']['minute_min']>$s['gating']['minute_max'])$errors[]='intervalo invertido';
  if(!is_array($s['options']) || count($s['options'])!==4)return [...$errors,'quatro alternativas'];
  if(!is_string($s['text']) || mb_strlen($s['text'])<20 || mb_strlen($s['text'])>450)$errors[]='enunciado';
  $intents=[];$labels=[];
  foreach($s['options'] as $o){
   foreach(['code','action','text','effects','failure_effects','severe_failure_effects','success_text','failure_text','semantic_intent','match_event'] as $k)if(!array_key_exists($k,$o))$errors[]='alternativa ausente '.$k;
   if(!in_array($o['code']??null,self::CODES,true) || !in_array($o['action']??null,array_keys(Rules::WEIGHTS),true))$errors[]='ação/código';
   if(($o['action']??null)!==(in_array($o['code']??'', ['passe','chute','drible','desarme'],true)?$o['code']:'social'))$errors[]='ação diverge do código';
   foreach(['difficulty','chance_bonus','exceptional_effects'] as $field)if(isset($o[$field]))$errors[]='modificador mecânico não autorizado '.$field;
   foreach(['text','success_text','failure_text','semantic_intent'] as $k)if(!is_string($o[$k]??null) || mb_strlen($o[$k])<4)$errors[]='texto '.$k;
   $intents[]=$o['semantic_intent']??'';$labels[]=$o['text']??'';
   if(($o['success_text']??'')===($o['failure_text']??''))$errors[]='desfechos iguais';
   foreach(['effects','failure_effects','severe_failure_effects'] as $kind){
    if(!is_array($o[$kind]??null)){$errors[]='efeitos não são objeto';continue;}
    foreach($o[$kind] as $k=>$v)if(!in_array($k,$kind==='effects'?[...Rules::SKILLS,...Rules::STATES]:Rules::STATES,true) || !is_int($v) || abs($v)>20 || ($kind==='effects' && in_array($k,Rules::SKILLS,true) && $v<0))$errors[]='efeito inválido '.$k;
   }
   if(!is_bool($o['match_event']??null) || (($o['match_event']??false) && $s['stage']!=='partida'))$errors[]='evento de partida';
   if($s['stage']==='partida' && in_array($o['action']??'', ['chute','passe','drible','desarme'],true) && empty($o['match_event']))$errors[]='ação de campo sem integração';
   if($s['stage']==='partida' && !empty($o['match_event']) && !empty($o['effects']))$errors[]='XP técnico de campo deve usar motor vigente';
  }
  if(count(array_unique($intents))!==4 || count(array_unique($labels))!==4)$errors[]='intents/alternativas repetidos';
  return $errors;
 }
 public static function outcome(array $o,array $roll):string {
  $field=match($roll['class']){'excepcional'=>'exceptional_text','falha_grave'=>'severe_failure_text',default=>''};
  return $o[$field]??($roll['success']?$o['success_text']:$o['failure_text']);
 }
}
