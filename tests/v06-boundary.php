<?php
declare(strict_types=1);
$root=dirname(__DIR__);$files=['app/Rules.php','app/Progression.php','app/ActionResolution.php','app/TeamStrength.php','app/MatchBalance.php','app/MatchMomentum.php','app/Competition.php','app/TransferMarket.php','app/Identity.php','app/CareerBenefits.php'];
foreach($files as $file){$old=shell_exec('git show c08ab56:'.$file);$now=file_get_contents($root.'/'.$file);
if($file==='app/Competition.php'){
 $now=implode("\n",array_filter(explode("\n",str_replace("\r\n","\n",$now)),static fn($line)=>!str_starts_with($line,'        if(self::$referenceClubs)')&&!str_starts_with($line,"        if(\$c['format']==='alagoas'")&&!str_starts_with($line,"        if(\$t['format']==='alagoas'")));

 // Exact configurable-state dispatch only. Removing these hooks must restore legacy bytes.
 foreach([
 "        if(\$c['format']==='configured_state')return ConfiguredStateCompetition::create(\$c,\$teams);",
 "        if(\$t['format']==='configured_state')return ConfiguredStateCompetition::standings(\$t,\$teams,\$phase);",
 "        if(\$t['format']==='configured_state'){ConfiguredStateCompetition::next(\$t);return;}",
 "        if(\$t['format']==='configured_state'&&!empty(\$t['done']))return ['name'=>self::catalog()[\$t['id']]['name'],'champion'=>\$t['champion'],'promoted'=>false,'relegated'=>false,'position'=>array_search(\$club,\$t['final_ranking'],true)+1,'group'=>null,'access_pending'=>in_array(\$club,\$t['promoted'],true),'continuity'=>['valid'=>false,'reason'=>'state_future_memberships_and_mandatory_calendar_pending'],'relegation_pending'=>in_array(\$club,\$t['relegated'],true),'titles'=>[]];"
 ] as $hook){if(substr_count($now,$hook)!==1)throw new RuntimeException('Dispatch estadual divergente.');$now=str_replace($hook."\n",'',$now);}
 // Exact opt-in C adapter hooks: removing them must restore the entire legacy file.
 foreach(["        if(\$c['format']==='national_c')return NationalCCompetition::create(\$c,\$teams);", "        if(\$t['format']==='national_c')return NationalCCompetition::standings(\$t,\$teams,\$phase);", "        if(\$t['format']==='national_c'){NationalCCompetition::next(\$t);return;}", "        if(\$t['format']==='national_c')return NationalCCompetition::result(\$t,\$club);"] as $hook)$now=str_replace($hook."\n",'', $now);
 foreach(["        if(\$c['format']==='national_d')return NationalDCompetition::create(\$c,\$teams);","        if(\$t['format']==='national_d')return NationalDCompetition::standings(\$t,\$teams,\$phase);","        if(\$t['format']==='national_d'){NationalDCompetition::next(\$t);return;}","        if(\$t['format']==='national_d')return NationalDCompetition::result(\$t,\$club);"] as $hook)$now=str_replace($hook."\n",'',$now);
 foreach(["        if(\$c['format']==='nordeste')return NordesteCompetition::create(\$c,\$teams);","        if(\$t['format']==='nordeste')return NordesteCompetition::standings(\$t,\$teams,\$phase);","        if(\$t['format']==='nordeste'){NordesteCompetition::next(\$t);return;}","        if(\$t['format']==='nordeste')return NordesteCompetition::result(\$t,\$club);"] as $hook)$now=str_replace($hook."\n",'',$now);
 foreach(["        if(\$c['format']==='copa_brasil')return CopaBrasilCompetition::create(\$c,\$teams);","        if(\$t['format']==='copa_brasil')return CopaBrasilCompetition::standings(\$t,\$teams,\$phase);","        if(\$t['format']==='copa_brasil'){CopaBrasilCompetition::next(\$t);return;}","        if(\$t['format']==='copa_brasil')return CopaBrasilCompetition::result(\$t,\$club);"] as $hook)$now=str_replace($hook."\n",'',$now);
 $now=preg_replace('/    private static \?array \$referenceCatalog=null;.*?(?=    public static function catalog\(\))/s','',$now,1);
 foreach(["        if(self::\$referenceCatalog!==null)return self::\$referenceCatalog;\n","        if(isset(\$g['world_runtime'])){WorldSeason::afterWeekend(\$g);return;}\n","        if(isset(\$g['world_runtime'])){WorldSeason::next(\$g);return;}\n","        if(isset(\$g['world_runtime'])){WorldSeason::renew(\$g);return;}\n"] as $hook)$now=str_replace($hook,'',str_replace("\r\n","\n",$now));
}
if(str_replace("\r\n","\n",$old??'')!==str_replace("\r\n","\n",$now))throw new RuntimeException('Mecânica fora do escopo alterada: '.$file);}
$old=str_replace("\r\n","\n",shell_exec('git show c08ab56:app/Evolution.php')??'');$now=str_replace("\r\n","\n",file_get_contents($root.'/app/Evolution.php'));$now=str_replace("        MatchExperience::record(\$g,\$skill,\$earned);\n",'',$now);if($old!==$now)throw new RuntimeException('Fórmula Evolution alterada');echo "OK: 11 fronteiras v0.6; fórmulas e XP-base preservados.\n";
