<?php
declare(strict_types=1);
/** Synthetic competitions, existing clubs. Never imported into reference catalog. */
function worldFixture(int $id=700001,string $position='meia',string $mode='normal'):array {
 $clubs=array_slice(Competition::catalog()['sp-a4']['teams'],0,8);$g=Game::legacyCreate('Atleta de teste','São Paulo','SP',$position);$g['id']=$id;$g['club']=$clubs[0];$g['play_mode']=$mode;$g['handicap']='Equilibrado';$g['season_skill_baseline']=array_sum(array_column($g['skills'],'value'))/9;
 $base=['edition'=>2026,'country'=>'BR','uf'=>'SP','family'=>'state','tier'=>1,'format'=>'state','test_fixture'=>true,'scope'=>'state','source'=>'isolated synthetic fixture; not an official competition','rules'=>['return'=>true,'qualify'=>0,'legs'=>1,'final_legs'=>1,'access'=>0]];
 $a=$base+['id'=>'fx-1','name'=>'Liga de teste — elite','teams'=>array_slice($clubs,0,4),'groups'=>[array_slice($clubs,0,4)]];$a['rules']['relegation']=1;
 $b=$base+['id'=>'fx-2','name'=>'Liga de teste — acesso','teams'=>array_slice($clubs,4,4),'groups'=>[array_slice($clubs,4,4)],'progression_upper'=>'fx-1'];$b['rules']['access']=1;
 $cup=$base+['id'=>'fx-cup','name'=>'Copa de teste — não oficial','teams'=>$clubs,'groups'=>[$clubs],'competition_type'=>'cup'];$cup['rules']['return']=false;$cup['rules']['qualify']=4;
 return WorldSeason::attachFixture($g,['fx-1'=>$a,'fx-2'=>$b,'fx-cup'=>$cup],['fx-1'=>$a['teams'],'fx-2'=>$b['teams'],'fx-cup'=>$clubs]);
}
