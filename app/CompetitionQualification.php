<?php
declare(strict_types=1);
/** Versioned admission ledger, fail closed. No guessed quota or automatic promotion. */
final class CompetitionQualification {
 public static function evaluate(array $policy,array $claims,array $knownClubs):array {
  $required=['id','version','edition','target','capacity','country'];foreach($required as $key)if(!isset($policy[$key]))throw new InvalidArgumentException('Política de inscrição incompleta.');
  $out=['policy'=>$policy['id'],'version'=>$policy['version'],'edition'=>$policy['edition'],'target'=>$policy['target'],'accepted'=>[],'rejected'=>[],'blocked'=>true,'missing'=>[]];
  if(($policy['status']??'blocked')!=='verified'||empty($policy['sources'])){$out['missing']=$policy['missing']??['politica_sem_validacao'];return $out;}
  if(!is_string($policy['country'])||!preg_match('/^[A-Z]{2}$/D',$policy['country']))throw new InvalidArgumentException('Associação nacional não identificada.');
  $capacity=(int)$policy['capacity'];if($capacity<2)throw new InvalidArgumentException('Capacidade inválida.');
  $quotas=$policy['quotas']??[];foreach($quotas as $number)if(!is_int($number)||$number<0)throw new InvalidArgumentException('Quota inválida.');if(array_sum($quotas)!==$capacity)throw new InvalidArgumentException('As vagas da edição não fecham.');$seen=[];$used=[];$keys=[];
  usort($claims,fn($a,$b)=>[$a['priority']??PHP_INT_MAX,$a['rank']??PHP_INT_MAX,$a['club']??'']<=>[$b['priority']??PHP_INT_MAX,$b['rank']??PHP_INT_MAX,$b['club']??'']);
  foreach($claims as $claim){$club=$claim['club']??'';$bucket=$claim['quota']??'';$reason=null;
   if(!isset($knownClubs[$club]))$reason='clube_ausente';elseif(($knownClubs[$club]['country']??null)!==($policy['country']??null))$reason='associacao_incompativel';
   elseif(($claim['target_edition']??null)!==$policy['edition'])$reason='edicao_incompativel';elseif(($claim['finalized']??false)!==true||empty($claim['evidence_hash'])||empty($claim['source_id']))$reason='evidencia_nao_verificavel';
   elseif(!in_array($claim['source_id'],$policy['allowed_evidence_sources']??[],true))$reason='fonte_nao_permitida';elseif(!preg_match('/^[a-f0-9]{64}$/',$claim['evidence_hash']))$reason='digest_invalido';
   elseif(isset($seen[$club]))$reason='clube_ja_classificado';elseif(!isset($quotas[$bucket]))$reason='vaga_nao_prevista';elseif(($used[$bucket]??0)>=$quotas[$bucket])$reason='quota_esgotada';
   if($reason){$out['rejected'][]=['club'=>$club,'reason'=>$reason];continue;}
   $seen[$club]=true;$used[$bucket]=($used[$bucket]??0)+1;$key=hash('sha256',json_encode([$policy['id'],$policy['version'],$policy['edition'],$policy['target'],$club],JSON_THROW_ON_ERROR));$out['accepted'][]=['club'=>$club,'quota'=>$bucket,'source_id'=>$claim['source_id'],'evidence_hash'=>$claim['evidence_hash'],'key'=>$key];
  }
  foreach($quotas as $bucket=>$count)if(($used[$bucket]??0)<$count)$out['missing'][$bucket]=$count-($used[$bucket]??0);
  $out['blocked']=count($out['accepted'])!==$capacity||!empty($out['missing']);return $out;
 }
}
