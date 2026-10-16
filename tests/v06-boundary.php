<?php
declare(strict_types=1);
$root=dirname(__DIR__);$files=['app/Rules.php','app/Progression.php','app/ActionResolution.php','app/TeamStrength.php','app/MatchBalance.php','app/MatchMomentum.php','app/Competition.php','app/TransferMarket.php','app/Identity.php','app/CareerBenefits.php'];
foreach($files as $file){$old=shell_exec('git show c08ab56:'.$file);$now=file_get_contents($root.'/'.$file);
if($file==='app/Competition.php'){
 $now=preg_replace('/    private static \?array \$referenceCatalog=null;.*?(?=    public static function catalog\(\))/s','',$now,1);
 foreach(["        if(self::\$referenceCatalog!==null)return self::\$referenceCatalog;\n","        if(isset(\$g['world_runtime'])){WorldSeason::afterWeekend(\$g);return;}\n","        if(isset(\$g['world_runtime'])){WorldSeason::next(\$g);return;}\n","        if(isset(\$g['world_runtime'])){WorldSeason::renew(\$g);return;}\n"] as $hook)$now=str_replace($hook,'',str_replace("\r\n","\n",$now));
}
if(str_replace("\r\n","\n",$old??'')!==str_replace("\r\n","\n",$now))throw new RuntimeException('Mecânica fora do escopo alterada: '.$file);}
$old=str_replace("\r\n","\n",shell_exec('git show c08ab56:app/Evolution.php')??'');$now=str_replace("\r\n","\n",file_get_contents($root.'/app/Evolution.php'));$now=str_replace("        MatchExperience::record(\$g,\$skill,\$earned);\n",'',$now);if($old!==$now)throw new RuntimeException('Fórmula Evolution alterada');echo "OK: 11 fronteiras v0.6; fórmulas e XP-base preservados.\n";
