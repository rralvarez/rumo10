<?php
declare(strict_types=1);
final class GauchoTrial {
    public const MAX_MATCHES=2477; // 2413 existing ceiling + 64 RS games.
    public static function reference():array {
        static $r;return $r??=json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-gaucho-2026-v1.json'),true,512,JSON_THROW_ON_ERROR);
    }
    public static function create(int $id,string $mode='normal',string $club='São Luiz-RS'):array {
        $g=AcreTrial::create($id,$mode);$defs=array_map(fn($p)=>$p['definition'],$g['world_reference']);
        foreach(self::reference()['definitions'] as $key=>$c){if(isset($defs[$key]))throw new RuntimeException('Competição duplicada.');$defs[$key]=$c;}
        if(!in_array($club,array_merge(...array_column($defs,'teams')),true))throw new RuntimeException('Clube não inscrito.');
        $g['club']=$club;$g['gaucho_trial']='rs-2026-v1';
        return WorldSeason::attachFixture($g,$defs,array_map(fn($c)=>$c['teams'],$defs),['max_matches'=>self::MAX_MATCHES,'budget'=>8,'sized_contract'=>'abcd-ne-cdb-rs-2026-v1']);
    }
    public static function isolated(int $id,string $mode='normal',string $club='São Luiz-RS'):array {
        $g=Game::legacyCreate('Atleta de teste','Ijuí','RS','meia');$g['id']=$id;$g['club']=$club;$g['gaucho_isolated_trial']=true;$g['play_mode']=$mode;$g['handicap']='Equilibrado';$g['season_skill_baseline']=array_sum(array_column($g['skills'],'value'))/9;$defs=self::reference()['definitions'];
        return WorldSeason::attachFixture($g,$defs,array_map(fn($c)=>$c['teams'],$defs),['max_matches'=>128,'budget'=>8]);
    }
    public static function validatesBudget(array $g,array $definitions,array $members):bool {
        if(($g['gaucho_trial']??null)!=='rs-2026-v1')return false;
        foreach(self::reference()['definitions'] as $id=>$c){if(($definitions[$id]??null)!==$c||($members[$id]??null)!==$c['teams'])return false;unset($definitions[$id],$members[$id]);}
        return AcreTrial::validatesBudget($g,$definitions,$members);
    }
}
