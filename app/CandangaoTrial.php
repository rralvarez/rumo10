<?php
declare(strict_types=1);

/** Isolated 2026 component. Public career creation and legacy saves never call it. */
final class CandangaoTrial {
    public const MAX_MATCHES=2211;
    public static function reference():array {
        static $r;return $r??=json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-candangao-2026-v1.json'),true,512,JSON_THROW_ON_ERROR);
    }
    public static function create(int $id,string $mode='normal',string $club='ARUC-DF'):array {
        if(PHP_SAPI!=='cli'||config('RUMO10_WORLD_FIXTURES','0')!=='1')throw new RuntimeException('PE/RN somente em fixture local CLI.');
        $g=PernambucoPotiguarTrial::create($id,$mode);$defs=array_map(fn($p)=>$p['definition'],$g['world_reference']);
        foreach(self::reference()['definitions'] as $key=>$c){if(isset($defs[$key]))throw new RuntimeException('Competição capixaba duplicada.');$defs[$key]=$c;}
        if(!in_array($club,array_merge(...array_column($defs,'teams')),true))throw new RuntimeException('Clube não inscrito.');
        $g['club']=$club;$g['candangao_trial']='df-2026-v1';
        return WorldSeason::attachFixture($g,$defs,array_map(fn($c)=>$c['teams'],$defs),['max_matches'=>self::MAX_MATCHES,'budget'=>8,'sized_contract'=>'abcd-ne-cdb-df-2026-v1']);
    }
    public static function validatesBudget(array $g,array $definitions,array $members):bool {
        if(($g['candangao_trial']??null)!=='df-2026-v1')return false;
        foreach(self::reference()['definitions'] as $id=>$c){if(($definitions[$id]??null)!==$c||($members[$id]??null)!==$c['teams'])return false;unset($definitions[$id],$members[$id]);}
        return PernambucoPotiguarTrial::validatesBudget($g,$definitions,$members);
    }
}
