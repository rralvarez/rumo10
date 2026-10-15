<?php
declare(strict_types=1);
final class Trophies {
 public static function collect(array $g):array {
  if(isset($g['retired_summary']['trophies']))return $g['retired_summary']['trophies'];
  $retired=$g['retired_summary']??null;$editions=[];$clubs=[];
  if($retired){foreach($retired['competitions']??[] as $r){$editions[$r['season']][$r['competition']]=$r;$clubs[$r['season']]=$r['club'];}}
  else{
   foreach($g['history']??[] as $h){$clubs[$h['season']]=$h['club'];foreach($h['competitions']??[] as $id=>$r)$editions[$h['season']][$id]=$r;}
   foreach($g['competition_history']??[] as $h)foreach($h['results'] as $id=>$r)$editions[$h['season']][$id]=$r;
   foreach($g['competition_results']??[] as $id=>$r)$editions[$g['season']][$id]=$r;$clubs[$g['season']]=$g['club'];
  }
  $out=[];$catalog=Competition::catalog();
  foreach($editions as $season=>$results)foreach($results as $id=>$r){
   $club=$clubs[$season]??null;if(!$club)continue;$c=$catalog[$id]??[];$titles=$r['titles']??[];
   $country=TransferMarket::country($club);$category=str_starts_with($id,'br-')?'nacional':(in_array($c['family']??'', ['sp','state'],true)?'estadual':'nacional');
   $base=['season'=>(int)$season,'competition'=>$id,'competition_name'=>$r['name']??$c['name']??$id,'club'=>$club,'country'=>$country,'category'=>$category,'reference_edition'=>$c['season_label']??$c['edition']??null,'universe_edition'=>'Temporada '.$season,'adapted'=>!empty($c['rules']['note']),'source'=>'competition_result'];
   $namedChampion=in_array($id,['ar-1','py-1','us-1','ca-1'],true) || str_starts_with($c['rules']['system']??'','short_');
   if(($r['champion']??null)===$club && (!$titles || !$namedChampion)){$name=$r['name']??$c['name']??$id;$out[$season.'|'.$id.'|champion']=$base+['title'=>$name,'kind'=>'champion'];}
   foreach($titles as $title=>$winner)if($winner===$club)$out[$season.'|'.$id.'|'.$title]=$base+['title'=>$title,'kind'=>'stage_title'];
  }
  // Resumos antigos sem resultados suficientes: preservar apenas conquistas já registradas.
  if(!$editions && $retired)foreach($retired['titles']??[] as $r){$key=$r['season'].'|'.$r['competition'].'|'.$r['title'];$out[$key]=$r+['country'=>TransferMarket::country($r['club']),'category'=>'regional','universe_edition'=>'Temporada '.$r['season'],'source'=>'legacy_retired_summary'];}
  ksort($out);return array_values($out);
 }
 public static function render(array $g):void {
  $items=self::collect($g);echo '<section class="panel trophy-room"><p class="tag">SUA HISTÓRIA</p><h1>Sala de Troféus</h1><a class="btn btn-primary" href="/carreira">Voltar à carreira</a><p>'.count($items).' conquistas coletivas registradas.</p><p class="subtle">Temporadas são ciclos do universo do jogo. A edição de referência não representa datas reais sincronizadas. Só exibimos resultados preservados; não reconstruímos conquistas ausentes de saves antigos.</p>';
  $groups=[];foreach($items as $t)$groups[$t['competition']][]=$t;
  foreach($groups as $group){echo '<article class="panel"><h2>'.e($group[0]['competition_name']??$group[0]['competition']).' · '.count($group).'</h2><ol class="trophy-list">';foreach($group as $t)echo '<li><strong>'.e($t['title']).'</strong><br>'.e($t['universe_edition']??'Temporada '.$t['season']).' · '.e(ClubBadge::name($t['club'])).' · '.e(EntryCountries::name($t['country'])).'<br><small>'.e($t['category']).' · fonte: resultado salvo'.(!empty($t['adapted'])?' · calendário adaptado':'').'</small></li>';echo '</ol></article>';}
  if(!$items)echo '<p>Ainda não há títulos verificáveis nesta carreira.</p>';
  echo '<h2>Premiações individuais</h2><p>Em preparação. Artilharia, assistências e melhor jogador dependem de estatísticas comparáveis de todos os atletas. Nenhum prêmio individual foi concedido sem essa comprovação.</p></section>';
 }
}
