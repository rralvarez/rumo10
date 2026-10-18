<?php
declare(strict_types=1);

/** Isolated 2026 component. Public career creation and legacy saves never call it. */
final class PernambucoPotiguarTrial {
    public const MAX_MATCHES=2153;
    public static function reference():array {
        static $r;return $r??=json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-pe-rn-2026-v1.json'),true,512,JSON_THROW_ON_ERROR);
    }
    public static function create(int $id,string $mode='normal',string $club='Jaguar-PE'):array {
        if(PHP_SAPI!=='cli'||config('RUMO10_WORLD_FIXTURES','0')!=='1')throw new RuntimeException('PE/RN somente em fixture local CLI.');
        $g=CapixabaTrial::create($id,$mode);$defs=array_map(fn($p)=>$p['definition'],$g['world_reference']);
        foreach(self::reference()['definitions'] as $key=>$c){if(isset($defs[$key]))throw new RuntimeException('Competição capixaba duplicada.');$defs[$key]=$c;}
        if(!in_array($club,array_merge(...array_column($defs,'teams')),true))throw new RuntimeException('Clube não inscrito.');
        $g['club']=$club;$g['pe_rn_trial']='pe-rn-2026-v1';
        return WorldSeason::attachFixture($g,$defs,array_map(fn($c)=>$c['teams'],$defs),['max_matches'=>self::MAX_MATCHES,'budget'=>8,'sized_contract'=>'abcd-ne-cdb-pe-rn-2026-v1']);
    }
    public static function validatesBudget(array $g,array $definitions,array $members):bool {
        if(($g['pe_rn_trial']??null)!=='pe-rn-2026-v1')return false;
        foreach(self::reference()['definitions'] as $id=>$c){if(($definitions[$id]??null)!==$c||($members[$id]??null)!==$c['teams'])return false;unset($definitions[$id],$members[$id]);}
        return CapixabaTrial::validatesBudget($g,$definitions,$members);
    }
}
