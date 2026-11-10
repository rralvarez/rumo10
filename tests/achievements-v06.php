<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');require dirname(__DIR__).'/app/bootstrap.php';$checks=0;
function check(bool $ok,string $why):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($why);}
$all=AchievementCatalog::all();check(count($all)===50,'50 achievements');check(array_sum(array_column($all,'g'))===1000,'1000 G');$sums=[];foreach($all as $a){$sums[$a['category']]=($sums[$a['category']]??0)+$a['g'];check($a['xp_bonus']===0,'no unapproved XP');}check(array_values($sums)===[200,250,250,150,150],'category totals');
$e=['type'=>'match','season'=>1,'club'=>'Fixture','stats'=>['minutes'=>90,'goals'=>3,'assists'=>1,'tackles'=>2]];$s=Achievements::apply([],$e);$p=Achievements::progress($s,1);foreach(['CAR01','CAR02','DES01','DES02','DES03','DES04','DES05'] as $id)check($p[$id][0]>=$p[$id][1],$id);
check($p['DES08'][0]===3,'one career only');check(Achievements::progress([],1)['DES08'][0]===0,'cannot combine careers');$empty=$e;$empty['stats']['minutes']=0;check(Achievements::apply([],$empty)['matches']===0,'no minutes no career qualification');
foreach(['TIT03','TIT05','TIT08','TIT09','ESP03','ESP10','SOC01','SOC03','SOC05','SOC07','SOC09'] as $id)check(!$all[$id]['active'],'unsupported source inactive '.$id);
$forged=['type'=>'ballon_dor','season'=>1,'club'=>'Fixture','count'=>9];$s=Achievements::apply($s,$forged);check(!isset(Achievements::progress($s,10)['ESP10']),'GOAT cannot infer award');
$s=[];
for($year=1;$year<=15;$year++){
 $s=Achievements::apply($s,['type'=>'match','season'=>$year,'club'=>'Fixture','competition'=>'br-a','stats'=>['minutes'=>90,'goals'=>20,'assists'=>5,'tackles'=>40],'flags'=>['ESP04'=>true,'ESP05'=>true]]);
 $title=['season'=>$year,'competition'=>'br-a','title'=>'Brasileirão Série A','country'=>'BR'];
 $s=Achievements::apply($s,['type'=>'season','season'=>$year,'club'=>'Fixture','titles'=>[$title],'invincible'=>true]);
}
$p=Achievements::progress($s,10);foreach(['CAR10','DES06','DES07','DES08','DES09','DES10','TIT01','TIT04','TIT07','TIT10','ESP02','ESP04','ESP05','ESP07'] as $id)check($p[$id][0]>=$p[$id][1],'long career '.$id);
$promotion=Achievements::apply([],['type'=>'match','season'=>1,'club'=>'Fixture','competition'=>'am-entrada','stats'=>['minutes'=>90,'goals'=>0,'assists'=>0,'tackles'=>0]]);
$promotion=Achievements::apply($promotion,['type'=>'season','season'=>1,'club'=>'Fixture','titles'=>[],'promotions'=>[['upper'=>'am-a1','season'=>1,'club'=>'Fixture']]]);
$next=['type'=>'match','season'=>2,'club'=>'Fixture','competition'=>'am-a1','stats'=>['minutes'=>90,'goals'=>0,'assists'=>0,'tackles'=>0]];
check(Achievements::progress(Achievements::apply($promotion,$next),1)['ESP06'][0]===1,'recognized upper after promotion');$next['club']='Other';check(Achievements::progress(Achievements::apply($promotion,$next),1)['ESP06'][0]===0,'transfer cannot fabricate promotion');
$m=['home'=>'Fixture','away'=>'Other','hg'=>0,'ag'=>0,'entry'=>0,'exit'=>90,'minute'=>20,'stats'=>['minutes'=>90]];$m['ag']++;GoalAudit::score($m,'ag','Fixture');$m['minute']=60;$m['hg']++;GoalAudit::score($m,'hg','Fixture');$m['minute']=80;$m['hg']++;GoalAudit::score($m,'hg','Fixture',true,['shot_kind'=>'difficult','valid_sequence_actions'=>2]);$f=GoalAudit::flags($m,'Fixture');foreach(['ESP01','ESP04','ESP05','ESP08'] as $id)check(!empty($f[$id]),'structured score '.$id);$m['ag']=2;check(empty(GoalAudit::flags($m,'Fixture')['ESP08']),'late equalizer not winner');
echo "OK: $checks verificações catálogo/projeção; persistência exige suíte MySQL própria.\n";
