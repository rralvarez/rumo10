<?php
declare(strict_types=1);

/** Shared CLI builder; documentary prefixes are deliberately not certified editions. */
final class StateFederationsTrial {
    public static function reference():array {
        static $r;if($r!==null)return $r;$r=json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-state-program-2026-v1.json'),true,512,JSON_THROW_ON_ERROR);$revision=json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-state-program-reviewed-2026-v2.json'),true,512,JSON_THROW_ON_ERROR);foreach($revision['definitions'] as $id=>$c){if(!in_array($id,$revision['revision_scope'],true)||!isset($r['definitions'][$id])||$c['id']!==$id)throw new RuntimeException('Revisão de contrato não declarada.');$r['definitions'][$id]=$c;}$r['sources']=array_merge($r['sources'],$revision['sources']);return $r;
    }
    public const COHORT_COMPETITIONS=['br-rj-a1','br-sc-a1','br-ce-a1','br-pa-a1'];
    public const MAX_MATCHES=2696; // Frozen2477 prior ceiling +62RJ +70SC maximum +43CE +44PA.
    public static function cohort(int $id,string $mode='expresso'):array {
        $g=GauchoTrial::create($id,$mode);$defs=array_map(fn($p)=>$p['definition'],$g['world_reference']);
        foreach(self::COHORT_COMPETITIONS as $key){if(isset($defs[$key]))throw new RuntimeException('Competição duplicada.');$defs[$key]=self::reference()['definitions'][$key];}
        $g['state_program_cohort']='states-program-2026-v1';
        return WorldSeason::attachFixture($g,$defs,array_map(fn($c)=>$c['teams'],$defs),['max_matches'=>self::MAX_MATCHES,'budget'=>8,'sized_contract'=>'abcd-state-program-2026-v1']);
    }
    public static function validatesBudget(array $g,array $definitions,array $members):bool {
        if(($g['state_program_cohort']??null)!=='states-program-2026-v1')return false;
        foreach(self::COHORT_COMPETITIONS as $id){$c=self::reference()['definitions'][$id];if(($definitions[$id]??null)!==$c||($members[$id]??null)!==$c['teams'])return false;unset($definitions[$id],$members[$id]);}
        return GauchoTrial::validatesBudget($g,$definitions,$members);
    }
    public const AUDIT_MAX_MATCHES=3065; //2477 +62+70+43+44+61+39+55+72+53+34+55; explicit CLI contract only.
    public static function auditCohort(int $id):array {
        $g=GauchoTrial::create($id,'expresso');$defs=array_map(fn($p)=>$p['definition'],$g['world_reference']);
        foreach(self::reference()['definitions'] as $key=>$c){if(isset($defs[$key]))throw new RuntimeException('Competição duplicada na auditoria.');$defs[$key]=$c;}
        $g['state_program_audit']='all-available-states-2026-v1';
        return WorldSeason::attachFixture($g,$defs,array_map(fn($c)=>$c['teams'],$defs),['max_matches'=>self::AUDIT_MAX_MATCHES,'budget'=>8,'sized_contract'=>'all-available-states-2026-v1']);
    }
    public static function validatesAuditBudget(array $g,array $definitions,array $members):bool {
        if(($g['state_program_audit']??null)!=='all-available-states-2026-v1')return false;
        foreach(self::reference()['definitions'] as $id=>$c){if(($definitions[$id]??null)!==$c||($members[$id]??null)!==$c['teams'])return false;unset($definitions[$id],$members[$id]);}
        return GauchoTrial::validatesBudget($g,$definitions,$members);
    }
    public static function isolated(int $id,string $competition,string $mode='normal',?string $club=null):array {
        $c=self::reference()['definitions'][$competition]??throw new InvalidArgumentException('Contrato não preparado.');$club??=$c['teams'][0];if(!in_array($club,$c['teams'],true)||!in_array($mode,['normal','rapido','expresso'],true))throw new InvalidArgumentException('Clube/modo inválido.');
        $g=Game::legacyCreate('Atleta de teste','Localidade de teste',$c['uf'],'meia');$g['id']=$id;$g['club']=$club;$g['state_documentary_trial']=true;$g['play_mode']=$mode;$g['handicap']='Equilibrado';$g['season_skill_baseline']=array_sum(array_column($g['skills'],'value'))/9;
        return WorldSeason::attachFixture($g,[$competition=>$c],[$competition=>$c['teams']],['max_matches'=>128,'budget'=>8]);
    }
}
