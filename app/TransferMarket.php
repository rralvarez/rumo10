<?php
declare(strict_types=1);
final class TransferMarket {
     public const COUNTRIES=['EN'=>'Inglaterra','ES'=>'Espanha','DE'=>'Alemanha','IT'=>'Itália','FR'=>'França','PT'=>'Portugal','NL'=>'Países Baixos','BE'=>'Bélgica','TR'=>'Turquia','SC'=>'Escócia','AR'=>'Argentina','UY'=>'Uruguai','PY'=>'Paraguai','BO'=>'Bolívia','BR'=>'Brasil','US'=>'Estados Unidos','CA'=>'Canadá','MX'=>'México','CR'=>'Costa Rica','HN'=>'Honduras','GT'=>'Guatemala','SV'=>'El Salvador','NO'=>'Noruega','DK'=>'Dinamarca','GR'=>'Grécia','CY'=>'Chipre','RU'=>'Rússia','SA'=>'Arábia Saudita','EC'=>'Equador','CO'=>'Colômbia','CZ'=>'Tchéquia','PL'=>'Polônia','HU'=>'Hungria','HR'=>'Croácia'];
    public const EXTRA_COUNTRIES=['NI'=>'Nicarágua','PA'=>'Panamá','HT'=>'Haiti','JM'=>'Jamaica','DO'=>'República Dominicana','SR'=>'Suriname','CL'=>'Chile','PE'=>'Peru','VE'=>'Venezuela','GY'=>'Guiana','ZA'=>'África do Sul','NG'=>'Nigéria','GH'=>'Gana','KE'=>'Quênia','MA'=>'Marrocos','EG'=>'Egito','DZ'=>'Argélia','TN'=>'Tunísia','TZ'=>'Tanzânia','CD'=>'RD Congo','AO'=>'Angola','CI'=>'Costa do Marfim','ZM'=>'Zâmbia','UG'=>'Uganda','RW'=>'Ruanda','CM'=>'Camarões','SN'=>'Senegal','ML'=>'Mali','ET'=>'Etiópia','LY'=>'Líbia','AT'=>'Áustria','AZ'=>'Azerbaijão','CH'=>'Suíça','LI'=>'Liechtenstein','UA'=>'Ucrânia','RS'=>'Sérvia','JP'=>'Japão','IL'=>'Israel','BG'=>'Bulgária','SE'=>'Suécia','IN'=>'Índia','CN'=>'China','TH'=>'Tailândia','AE'=>'Emirados Árabes Unidos','FI'=>'Finlândia','IE'=>'Irlanda','AU'=>'Austrália','NZ'=>'Nova Zelândia','KR'=>'Coreia do Sul','RO'=>'Romênia'];
    public const EUROPE=['EN','ES','DE','IT','FR','PT','NL','BE','TR','SC','NO','DK','GR','CY','CZ','PL','HU','HR','RU','AT','AZ','CH','LI','UA','RS','IL','BG','SE','FI','IE','RO'];
 public static function country(string $club):string{return Competition::clubs()[$club]['country']??'BR';}
 public static function sportingLevel(string $id):int {
  if(isset(['br-a'=>1,'br-b'=>2,'br-c'=>3,'br-d'=>4][$id]))return ['br-a'=>1,'br-b'=>2,'br-c'=>3,'br-d'=>4][$id];
  if(str_starts_with($id,'sp-') || str_ends_with($id,'-entrada'))return 5;
  return preg_match('/-(\d+)$/',$id,$m)?min(5,(int)$m[1]):5;
 }
 public static function profile(array $g):array {
  $s=Game::summary($g);$technical=array_sum(array_column($g['skills'],'value'))/9;$growth=max(0,$technical-($g['season_skill_baseline']??$technical));
  $tackles=0;foreach(Competition::playerMatches($g) as $m)if($m['status']==='finalizada' && in_array($g['club'],[$m['home'],$m['away']],true))$tackles+=$m['stats']['tackles']??0;
  $def=in_array($g['position'],['zagueiro','lateral','volante'],true);
  $production=($s['goals']*($def?.6:1)+$s['assists']+($def?$tackles*.12:0))*90/max(90,$s['minutes']);
  $merit=max(0,min(1.5,($s['average']-5.8)/2+$production*.3+$growth*.1));
  return $s+['technical'=>$technical,'growth'=>$growth,'production'=>$production,'merit'=>$merit,'abroad'=>$s['games']>=3 && $s['minutes']>=180 && $merit>=.20];
 }
 public static function pool(array $g):array {
  $p=self::profile($g);$members=$g['next_memberships']??$g['memberships'];$origin=self::country($g['club']);$region=FootballGeography::region($origin);
  $current=$g['next_club_strengths'][$g['club']]??TeamStrength::rating($g['club'],$g);$groups=['country'=>[],'region'=>[],'world'=>[]];
  $preferred=10+max(0,$g['season']-3)*min(2,$p['merit'])+($p['merit']>=1?8:0);
  foreach(Competition::clubs() as $club=>$data){
   if($club===$g['club'] || !empty($data['reserve']))continue;$ids=[];
   foreach($members as $id=>$teams)if(in_array($club,$teams,true))$ids[]=$id;if(!$ids)continue;
   usort($ids,fn($a,$b)=>(self::sportingLevel($a)<=>self::sportingLevel($b))?:strcmp($a,$b));$id=$ids[0];
   $country=self::country($club);$foreign=$country!==$origin;if($foreign && !$p['abroad'])continue;
   $ovr=$g['next_club_strengths'][$club]??TeamStrength::initial($club);
   $capacity=$p['technical']+10+18*$p['merit']+min(8,$p['growth']*2)+($g['age']<=26?2:0);
   if($ovr>$capacity)continue;
   $delta=$ovr-$current;$weight=max(3,(int)round(12+25*$p['merit']-max(0,$delta-$preferred)*2-max(0,-$delta-10)));
   $bucket=$foreign?(FootballGeography::region($country)===$region?'region':'world'):'country';
   $groups[$bucket][]=['club'=>$club,'tier'=>Competition::catalog()[$id]['tier'],'peso'=>$weight,'country'=>$country,'competition'=>$id,'sporting_level'=>self::sportingLevel($id),'overall'=>round($ovr,1),'overall_delta'=>round($delta,1),'international'=>$foreign,'region'=>$bucket,'city'=>$data['municipality']??$data['city']??null,'reason'=>'A proposta considera seu desempenho, participação e o nível atual das equipes.','preferred'=>abs($delta)<=($delta>0?$preferred:10)];
  }
  $targets=$g['season']<=3?['country'=>6,'region'=>2,'world'=>0]:['country'=>3,'region'=>3,'world'=>2];$pool=[];
  foreach($groups as &$list){shuffle($list);usort($list,fn($a,$b)=>$b['preferred']<=>$a['preferred']);}unset($list);
  foreach($targets as $bucket=>$n)for($i=0;$i<$n && $groups[$bucket];$i++)$pool[]=array_shift($groups[$bucket]);
  foreach(['region','country','world'] as $bucket)while(count($pool)<8 && $groups[$bucket])$pool[]=array_shift($groups[$bucket]);
  return $pool;
 }
 public static function distribution(array $g,array $pool):array {
  $counts=['country'=>0,'region'=>0,'world'=>0];foreach($pool as $o)$counts[$o['region']]++;
  $target=$g['season']<=3?['country'=>6,'region'=>2,'world'=>0]:['country'=>3,'region'=>3,'world'=>2];
  return ['target'=>$target,'actual'=>$counts,'limited'=>$counts!==$target,'policy'=>'confederation-v1'];
 }
 public static function render(array $g):void {
  $chosen=current(array_filter($g['offers'],fn($o)=>$o['selected']));
  if($chosen){Wheel::render($g['offers'],$chosen['club'],'offer:'.$g['id'].':'.$g['season']);self::describe($chosen);}
  else echo '<p>Nenhuma proposta se concretizou. Seu próximo capítulo pode continuar no clube atual.</p>';
  if(!empty($g['market_draw']['distribution']['limited']))echo '<p class="subtle">A cobertura ou elegibilidade não permitiu a distribuição geográfica prevista. Usamos os destinos válidos disponíveis.</p>';
  echo '<div class="transfer-actions">';form('/transferencia/responder',$g);
  if($chosen)echo '<button class="btn btn-primary" name="accept" value="yes">Aceitar proposta</button>';
  echo '<button class="btn btn-outline-light" name="accept" value="no">Ficar no meu clube</button></form></div>';
  if($g['offers']){echo '<details><summary>Destinos da roleta e níveis esportivos</summary>';foreach($g['offers'] as $i=>$offer){echo '<article><h3>Posição '.sprintf('%02d',$i+1).' na roleta · '.e(ClubBadge::name($offer['club'])).'</h3>';self::describe($offer);echo '</article>';}echo '</details>';}
 }
 public static function describe(array $offer):void {
  $country=$offer['country']??self::country($offer['club']);echo '<p class="offer-destination">';ClubBadge::render($offer['club']);echo ' <strong>'.e(EntryCountries::name($country)).'</strong>';
  if(!empty($offer['city']))echo ' · '.e($offer['city']);echo '</p>';
  if(isset($offer['competition']))echo '<p>'.e(Competition::catalog()[$offer['competition']]['name']).' · nível esportivo '.e($offer['sporting_level']??self::sportingLevel($offer['competition'])).' no país</p>';
  if(isset($offer['overall']))echo '<p>Overall '.e($offer['overall']).' · diferença '.e(($offer['overall_delta']??0)>=0?'+':'').e($offer['overall_delta']??0).' em relação ao seu clube</p>';
 }
}
