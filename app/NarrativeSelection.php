<?php
declare(strict_types=1);
/** PRNG de seleção apenas; probabilidade de resultado permanece random_int no motor existente. */
final class NarrativeSelection {
 public static function select(array $g,array $pool,array $catalog):array {
  if(!$pool){$s=self::fallback($g['stage']);return ['id'=>$s['id'],'snapshot'=>$s,'selection'=>['schema'=>1,'candidates'=>[],'weights'=>[],'roll'=>0,'pool_total'=>0,'fallback'=>['pool_vazio_neutro'],'module'=>$s['module'],'family'=>$s['family']]];}
  $original=$pool;$memory=$g['narrative_memory']??[];$recent=$g['seen_situations'][$g['stage']]??[];$fallback=[];
  $rarityRoll=hexdec(substr(hash('sha256',$g['story_seed'].':rarity:'.Scenes::key($g).':'.($g['competition_id']??'legacy')),0,7))%10000;
  $common=array_values(array_filter($pool,fn($id)=>($catalog[$id]['rarity']??'common')==='common'));
  $rare=array_values(array_filter($pool,fn($id)=>($catalog[$id]['rarity']??'common')==='rare'));
  // Frequência/novidade nunca elevam a porta de entrada das raras acima de 10%.
  $channel=$common && $rare?($rarityRoll<1000?'rare':'common'):($common?'common':'rare');
  $pool=$channel==='rare'?$rare:$common;
  $fresh=array_values(array_diff($pool,array_slice($recent,-10)));
  if($fresh)$pool=$fresh;else$fallback[]='ids_esgotados';
  $fresh=array_values(array_filter($pool,function($id)use($g,$catalog,$memory){
   $family=$catalog[$id]['family']??'legacy.'.$id;
   foreach(array_slice($memory,-40) as $seen)if($seen['family']===$family && $seen['season']===$g['season'] && ($seen['competition']??null)===($g['competition_id']??null) && $g['round']-$seen['round']>=0 && $g['round']-$seen['round']<max(2,$catalog[$id]['cooldown']??2))return false;
   return true;
  }));
  if($fresh)$pool=$fresh;else$fallback[]='familias_esgotadas';
  $counts=$g['narrative_counts'][$g['season']]??[];$modules=[];
  foreach($pool as $id){$module=$catalog[$id]['module']??'legacy';$modules[$module]=($modules[$module]??0)+1;}
  $weights=[];foreach($pool as $id){$s=$catalog[$id];$weights[$id]=max(1,(int)round(10000*($s['weight']??10)* (($s['rarity']??'common')==='rare'?.10:1)/$modules[$s['module']??'legacy']/(1+($counts[$id]??0))/(1+.1*($g['narrative_lifetime_counts'][$id]??0))));}
  $sum=array_sum($weights);$digest=hash('sha256',$g['story_seed'].':'.Scenes::key($g).':'.($g['competition_id']??'legacy'));$roll=hexdec(substr($digest,0,7))%$sum;$cursor=0;$chosen=$pool[0];
  foreach($weights as $id=>$w){$cursor+=$w;if($roll<$cursor){$chosen=$id;break;}}
  return ['id'=>$chosen,'selection'=>['schema'=>1,'candidates'=>$pool,'weights'=>$weights,'roll'=>$roll,'rarity_roll'=>$rarityRoll,'rarity_channel'=>$channel,'pool_total'=>count($original),'fallback'=>$fallback,'module'=>$catalog[$chosen]['module']??'legacy','family'=>$catalog[$chosen]['family']??'legacy.'.$chosen]];
 }
 public static function fallback(string $stage):array {
  $module=match($stage){'treino'=>'T02','pre_jogo'=>'P01','partida'=>'J03','fim_semana'=>'F02',default=>throw new LogicException('Fallback fora das etapas narrativas')};
  $text=match($stage){'treino'=>'Você organiza sua participação na sessão de treino. Escolha uma referência para trabalhar com atenção.','pre_jogo'=>'Antes da partida, você pode organizar sua atenção. Escolha uma referência simples para se preparar.','partida'=>'Você está em campo e acompanha a continuidade do jogo. Escolha como organizar sua próxima movimentação.','fim_semana'=>'Você dispõe de um momento para organizar sua rotina. Escolha um ponto que queira esclarecer.'};
  $options=[];
  foreach([
   ['disciplina','concentracao','Escolher uma tarefa por vez','Você define uma prioridade sem acumular tarefas.','Você tenta priorizar tudo e continua sem uma tarefa clara.'],
   ['visao','observar','Observar antes de se comprometer','Você identifica uma referência antes de iniciar o movimento.','Você observa sem definir o que procura e perde a referência.'],
   ['apoio','ouvir','Ouvir a orientação de um companheiro','A orientação ajuda a esclarecer o que você pode oferecer.','A conversa não esclarece a orientação de que precisava.'],
   ['social','comunicar','Explicar sua dúvida de forma direta','Sua dúvida fica clara e pode ser discutida com calma.','Sua explicação fica vaga e a dúvida permanece.'],
  ] as [$code,$intent,$label,$success,$failure])$options[]=['code'=>$code,'action'=>'social','text'=>$label,'success_text'=>$success,'failure_text'=>$failure,'effects'=>$code==='apoio'?['entrosamento'=>1]:['foco'=>1],'failure_effects'=>['foco'=>-1],'severe_failure_effects'=>['foco'=>-2],'semantic_intent'=>$intent,'match_event'=>false];
  return ['id'=>2990+array_search($stage,['treino','pre_jogo','partida','fim_semana'],true),'stage'=>$stage,'module'=>$module,'family'=>$module.'.fallback','tags'=>['concentracao'],'gating'=>[],'weight'=>1,'rarity'=>'common','cooldown'=>2,'text'=>$text,'options'=>$options,'source_type'=>'ficcao_editorial','schema_version'=>1,'enabled'=>true];
 }
 public static function remember(array &$g,array $s):void {
  $raw=Scenes::catalog()[$s['id']]??$s;$g['narrative_memory'][]=['id'=>$s['id'],'family'=>$raw['family']??'legacy.'.$s['id'],'season'=>$g['season'],'round'=>$g['round'],'competition'=>$g['competition_id']??null];
  $g['narrative_memory']=array_slice($g['narrative_memory'],-40);
  $g['narrative_counts'][$g['season']][$s['id']]=($g['narrative_counts'][$g['season']][$s['id']]??0)+1;
  $g['narrative_lifetime_counts'][$s['id']]=($g['narrative_lifetime_counts'][$s['id']]??0)+1;
  foreach(array_keys($g['narrative_counts']) as $season)if($season!=$g['season'])unset($g['narrative_counts'][$season]);
 }
}
