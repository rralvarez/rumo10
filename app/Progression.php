<?php
declare(strict_types=1);
final class Progression {
 public static function season(array &$g):void {
  if(PlayerDevelopment::enabled($g)){PlayerDevelopment::season($g);return;}
  $key='development:'.$g['season'];if(isset($g['reward_ledger'][$key]))return;
  $games=0;$minutes=0;$ratings=0.;$contributions=0.;$def=in_array($g['position'],['zagueiro','lateral','volante'],true);
  foreach(Competition::playerMatches($g) as $m){$s=$m['stats']??[];if(($m['balance_version']??0)!==MatchBalance::VERSION || $m['status']!=='finalizada' || !($s['minutes']??0) || !in_array($g['club'],[$m['home'],$m['away']],true))continue;
   $games++;$minutes+=$s['minutes'];$ratings+=$s['rating'];$contributions+=$s['assists']+$s['goals']*($def?.6:1)+($def?$s['tackles']*.12:0);
  }
  if($games<6 || $minutes<360)return;
  $quality=max(0,min(1,($ratings/$games-6)/2+min(1,$contributions*90/$minutes)*.15));
  $rate=$g['age']<=23?1.4:($g['age']<=26?1.2:($g['age']<=32?1:($g['age']<=34?.8:.6)));
  $target=(100+650*$quality)*min(1,$minutes/900)*$rate*(random_int(85,115)/100);
  $remaining=max(0,(int)round(($target-($g['season_xp_earned'][$g['season']]??0))/$rate));
  self::distribute($g,$remaining,$key);$g['reward_ledger'][$key]+=['quality'=>round($quality,3),'qualified_games'=>$games,'minutes'=>$minutes];
 }
 public static function distribute(array &$g,int $total,string $key):void {
  if(PlayerDevelopment::enabled($g)){PlayerDevelopment::distribute($g,$total,$key);return;}
  if(isset($g['reward_ledger'][$key]))return;$g['reward_ledger'][$key]=['base_xp'=>$total,'rules'=>Rules::VERSION];
  $order=match($g['position']){'zagueiro'=>['desarme','posicionamento','forca'],'lateral'=>['velocidade','passe','resistencia'],'volante'=>['posicionamento','desarme','passe'],'meia'=>['passe','visao_jogo','posicionamento'],'ponta'=>['drible','velocidade','finalizacao'],default=>['finalizacao','posicionamento','forca']};
  $skills=array_values(array_unique(array_merge($order,Rules::SKILLS)));
  foreach($skills as $i=>$skill){$xp=intdiv($total,count($skills))+(int)($i<$total%count($skills));if($xp)Evolution::gain($g,$skill,$xp);}
 }
 public static function match(array &$g,array $m):void {
  if((PlayerDevelopment::enabled($g)?!in_array($m['balance_version']??0,[2,3],true):($m['balance_version']??0)!==MatchBalance::VERSION) || !$m['stats']['minutes'])return;
  $s=$m['stats'];$quality=max(0,min(1,($s['rating']-5.8)/2.2));
  $total=(int)round(($s['minutes']/90)*(2+18*$quality)+min(3,$s['goals']+$s['assists']+$s['tackles']*.35));
  self::distribute($g,$total,'match:'.$g['season'].':'.($g['competition_id']??'legacy').':'.$m['round'].':'.$m['home'].':'.$m['away']);
 }
 public static function competition(array &$g,string $id,array $result):void {
  if(($g['progression_version']??0)!==2)return;
  $level=TransferMarket::sportingLevel($id);$xp=match($level){1=>90,2=>70,3=>55,4=>40,default=>25};
  $played=array_filter($g['matches'],fn($m)=>$m['status']==='finalizada' && ($m['stats']['minutes']??0)>0 && in_array($g['club'],[$m['home'],$m['away']],true));
  if(!$played)return;
  $view=$g;$view['competition_results'][$id]=$result;
  foreach(Trophies::collect($view) as $award){if($award['season']!==$g['season'] || $award['competition']!==$id || $award['club']!==$g['club'])continue;
   $key='title:'.$g['season'].':'.$id.':'.hash('sha256',$award['kind'].'|'.$award['title']);if(isset($g['reward_ledger'][$key]))continue;
   $short=$award['kind']==='stage_title';self::distribute($g,(int)round($xp*($short?.5:1)),$key);$g['states']['apoio_torcida']=min(100,$g['states']['apoio_torcida']+($short?2:4));$g['states']['confianca_imprensa']=min(100,$g['states']['confianca_imprensa']+($short?2:3));
  }
 }
}
