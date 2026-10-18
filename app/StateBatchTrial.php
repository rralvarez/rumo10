<?php
declare(strict_types=1);

/** Local-only pilot builder. Never merged into the public catalogue or new-career route. */
final class StateBatchTrial {
    public static function reference():array {
        static $r;return $r??=json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-state-batch-2026-v1.json'),true,512,JSON_THROW_ON_ERROR);
    }
    public static function create(int $id,string $mode='normal',string $club='CRB-AL'):array {
        if(PHP_SAPI!=='cli'||config('RUMO10_WORLD_FIXTURES','0')!=='1')throw new RuntimeException('Lote estadual somente em fixture local CLI.');
        $g=NationalTrial::createWithCopaBrasil($id,$mode);$defs=array_map(fn($p)=>$p['definition'],$g['world_reference']);
        foreach(self::reference()['definitions'] as $key=>$c){if(isset($defs[$key]))throw new RuntimeException('Competição estadual duplicada.');$defs[$key]=$c;}
        if(!in_array($club,array_merge(...array_column($defs,'teams')),true))throw new RuntimeException('Clube não inscrito no lote.');$g['club']=$club;$g['state_batch_trial']='pi-ro-2026-v1';
        return WorldSeason::attachFixture($g,$defs,array_map(fn($c)=>$c['teams'],$defs),['max_matches'=>1970,'budget'=>8,'sized_contract'=>'abcd-ne-cdb-states-2026-v1']);
    }
    public static function validatesBudget(array $g,array $definitions,array $members):bool {
        if(($g['state_batch_trial']??null)!=='pi-ro-2026-v1'||($g['national_trial']['schema']??null)!=='abcd-closure-v1'||array_keys($definitions)!==['al-a1','al-entrada','al-copa','br-b','br-a','br-c','br-d','br-nordeste','br-copa','pi-a1','ro-a1'])return false;
        foreach(self::reference()['definitions'] as $id=>$c)if($c!==$definitions[$id]||$members[$id]!==$c['teams'])return false;
        return count($members['br-d'])===96&&count($members['br-nordeste'])===20&&count($members['br-copa'])===126;
    }
}
