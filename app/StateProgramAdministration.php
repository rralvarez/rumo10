<?php
declare(strict_types=1);

/** Explicit test inputs for a club's documented choice of home-leg order. No public endpoint. */
final class StateProgramAdministration {
    public static function request(array &$t,array $c,array $phase,array $pool):?array {
        if(empty($phase['home_decision_required']))return null;
        $pairs=array_map(fn($pair)=>[$pool[$pair[0]],$pool[$pair[1]]],$phase['pairs']);
        $scores=array_map(fn($m)=>array_intersect_key($m,array_flip(['phase','round','home','away','status','hg','ag','home_red','away_red','home_yellow','away_yellow','penalties'])),$t['matches']);
        $request=['competition'=>$c['id'],'edition'=>$c['edition'],'phase'=>$phase['name'],'pairs'=>$pairs,'legs'=>$phase['legs'],'contract'=>StateResultProof::digest($c),'results'=>StateResultProof::digest($scores),'source'=>$phase['decision_source']];$key=StateResultProof::digest($request);
        $decision=$t['program_decisions'][$key]??null;
        if($decision===null){$t['administrative_pending']=['key'=>$key,'phase'=>$phase['name'],'teams'=>array_merge(...$pairs),'reason'=>'program_home_leg_choice_requires_audited_input','state'=>'awaiting_club_home_choice','simulation_only'=>true,'source'=>$phase['decision_source'],'request'=>$request];return null;}
        self::validate($decision,$key,$request);$t['program_decision_keys'][$key]=true;return $decision;
    }
    private static function validate(array $d,string $key,array $request):void {
        $body=$d;unset($body['sha256']);
        if(($d['key']??null)!==$key||($d['kind']??null)!=='synthetic_test_designation'||empty($d['reference'])||!is_string($d['reference'])||strlen($d['reference'])>300||!hash_equals($d['sha256']??'',StateResultProof::digest($body))||!is_array($d['home_clubs']??null)||count($d['home_clubs'])!==count($request['pairs']))throw new RuntimeException('Escolha de mando sem auditoria válida.');
        foreach($request['pairs'] as $i=>$pair)if(!in_array($d['home_clubs'][$i],$pair,true))throw new RuntimeException('Escolha de mando fora do confronto.');
    }
    public static function designate(array &$t,array $input):void {
        if(PHP_SAPI!=='cli'||config('APP_ENV')==='production'||config('RUMO10_WORLD_FIXTURES','0')!=='1'||config('RUMO10_ADMIN_TEST_DESIGNATIONS','0')!=='1')throw new RuntimeException('Escolha permitida somente em fixture CLI autorizada.');
        $input['kind']='synthetic_test_designation';unset($input['sha256']);$input['sha256']=StateResultProof::digest($input);
        $old=$t['program_decisions'][$input['key']??'']??null;if($old!==null){if($old!==$input)throw new RuntimeException('Escolha imutável em conflito.');return;}
        $pending=$t['administrative_pending']??throw new RuntimeException('Sem escolha pendente.');
        if(($pending['state']??null)!=='awaiting_club_home_choice'||($input['key']??null)!==$pending['key'])throw new RuntimeException('A escolha não corresponde à pendência.');
        self::validate($input,$pending['key'],$pending['request']);
        $t['program_decisions'][$pending['key']]=$input;unset($t['administrative_pending']);
    }
}
