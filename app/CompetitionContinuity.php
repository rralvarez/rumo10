<?php
declare(strict_types=1);
final class CompetitionContinuity {
 public static function pairs():array{return array_merge([['am-a1','am-entrada'],['sp-a1','sp-a2'],['sp-a2','sp-a3'],['sp-a3','sp-a4'],['sp-a4','sp-segunda'],['br-a','br-b'],['br-b','br-c'],['br-c','br-d'],['ar-1','ar-2'],['uy-1','uy-2'],['py-1','py-2'],['bo-1','bo-2']],EuropeanCompetition::pairs());}
 public static function upper(string $id):?string{foreach(self::pairs() as [$up,$down])if($down===$id)return $up;return null;}
 public static function status(string $id,?array $members=null):array {
  $c=Competition::catalog()[$id]??null;if(!$c)return ['valid'=>false,'reason'=>'competicao_ausente'];
  $teams=$members[$id]??$c['teams'];if(count(array_unique($teams))<2 || count(array_unique($teams))!==count($teams))return ['valid'=>false,'reason'=>'participantes_insuficientes_ou_duplicados'];
  $up=self::upper($id);$access=(int)($c['rules']['access']??0);
  $visited=[$id=>true];$cursor=$up;
  while($cursor!==null){if(isset($visited[$cursor]))return ['valid'=>false,'reason'=>'ciclo_na_hierarquia'];$visited[$cursor]=true;$cursor=self::upper($cursor);}
  if($id==='am-a1'&&count($teams)!==8)return ['valid'=>false,'reason'=>'amazonense_exige_oito_clubes'];
  if($access>0 && $up===null)return ['valid'=>false,'reason'=>'acesso_sem_superior','simulation_ceiling'=>true];
  if(preg_match('/-2$/',$id)&&isset(Competition::catalog()[substr($id,0,-1).'1'])&&$up===null)return ['valid'=>false,'reason'=>'vinculo_entre_divisoes_nao_implementado'];
  if($up!==null){$upper=Competition::catalog()[$up]??null;if(!$upper||count($members[$up]??$upper['teams'])<2)return ['valid'=>false,'reason'=>'superior_sem_calendario'];if(array_intersect($teams,$members[$up]??$upper['teams']))return ['valid'=>false,'reason'=>'clube_em_duas_divisoes_da_mesma_piramide'];}
  foreach($teams as $club)if(!isset(Competition::clubs()[$club]))return ['valid'=>false,'reason'=>'clube_ausente'];
  return ['valid'=>true,'reason'=>'calendario_simulado_repetivel','upper'=>$up];
 }
 public static function club(string $club,array $g):bool {
  $members=$g['next_memberships']??$g['memberships']??[];$found=false;
  foreach($members as $id=>$teams)if(in_array($club,$teams,true)){$found=true;if(!self::status($id,$members)['valid']||!self::status($id,$g['memberships']??$members)['valid'])return false;}
  return $found;
 }
 public static function extend(array &$g):void {
  // Só se adiciona o campeonato faltante na fronteira da próxima temporada.
  $c=Competition::catalog()['am-a1'];$g['memberships']['am-a1']??=$c['teams'];
 }
 public static function reconcileAmazonas(array &$g):void {
  // Old windows may already contain a completed B but no A. Never replay a saved tournament.
  if(empty($g['official'])||isset($g['memberships']['am-a1'])||isset($g['competitions']['am-a1'])||!isset($g['next_memberships']))return;
  $lower=$g['competitions']['am-entrada']??null;
  if(!$lower||empty($lower['done'])||count($lower['promoted']??[])!==1)return;
  $up=$lower['promoted'];$teams=Competition::catalog()['am-a1']['teams'];
  if(array_intersect($teams,$up)||!in_array($up[0],$g['next_memberships']['am-entrada']??[],true))return;
  $upper=Competition::tournament('am-a1',$teams);Competition::finish($upper,$g);$down=$upper['relegated'];
  if(count($down)!==1)throw new LogicException('Reconciliação amazonense sem queda válida.');
  $g['memberships']['am-a1']=$teams;$g['competitions']['am-a1']=$upper;
  $g['next_memberships']['am-a1']=array_values(array_merge(array_diff($teams,$down),$up));
  $g['next_memberships']['am-entrada']=array_values(array_merge(array_diff($g['next_memberships']['am-entrada'],$up),$down));
  $g['continuity_reconciliations'][]=['schema'=>1,'kind'=>'missing_am_a1','promoted'=>$up,'relegated'=>$down,'basis'=>'Acesso já persistido na Série B; somente a Série A ausente foi simulada. Resultados da B e históricos preservados.'];
 }
}
