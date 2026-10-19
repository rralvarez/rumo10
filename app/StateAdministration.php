<?php
declare(strict_types=1);
/** No public administration endpoint. Decisions are fixture inputs, never attributed to the real federation. */
final class StateAdministration {
    public static function settle(array &$t):void {
        foreach($t['matches'] as $index=>$m){$key=$m['administrative_key']??null;if(!$key||$m['status']!=='finalizada'||$m['hg']!==$m['ag']||isset($m['penalties']))continue;$decision=$t['administrative_decisions'][$key]??[];if(($decision['draw_rule']??null)!=='penalties')continue;
            $extra=['matches'=>[$m+['tie'=>0]],'phase'=>$m['phase'],'ties'=>[['a'=>$m['home'],'b'=>$m['away'],'winner'=>null,'rule'=>'penalties']]];Competition::resolve($extra);$t['matches'][$index]=$extra['matches'][0];unset($t['matches'][$index]['tie']);
        }
    }
    public static function rank(array $t,array $rows,string $phase,bool $certify):array {
        $teams=array_column($rows,'team');$canonical=$teams;sort($canonical,SORT_STRING);
        $scores=array_values(array_filter($t['matches'],fn($m)=>$m['phase']===$phase));
        $key=StateResultProof::digest(['phase'=>$phase,'teams'=>$canonical,'results'=>array_map(fn($m)=>[$m['round'],$m['home'],$m['away'],$m['hg'],$m['ag'],$m['home_red']??0,$m['away_red']??0,$m['home_yellow']??0,$m['away_yellow']??0],$scores)]);
        $decision=$t['administrative_decisions'][$key]??null;
        if($decision){$body=$decision;unset($body['sha256']);if(!hash_equals($decision['sha256']??'',StateResultProof::digest($body)))throw new RuntimeException('Auditoria da designação corrompida.');$pair=[$decision['home']??null,$decision['away']??null];sort($pair,SORT_STRING);if($pair!==$canonical||($decision['key']??null)!==$key||($decision['kind']??null)!=='synthetic_test_designation'||empty($decision['reference'])||empty($decision['venue']))throw new RuntimeException('Decisão incompatível com o desempate.');$m=null;foreach($t['matches'] as $candidate)if(($candidate['administrative_key']??null)===$key)$m=$candidate;
            if($m&&($m['administrative_decision_digest']??null)!==($decision['sha256']??null))throw new RuntimeException('Jogo extra sem vínculo à decisão auditada.');
            if($m&&([$m['home'],$m['away'],$m['neutral']??null,$m['venue']??null]!==[$decision['home'],$decision['away'],$decision['neutral'],$decision['venue']]))throw new RuntimeException('Jogo extra diverge da designação.');
            if($m&&$m['status']==='finalizada'){$winner=null;if($m['hg']!==$m['ag'])$winner=$m['hg']>$m['ag']?$m['home']:$m['away'];elseif(isset($m['penalties'])&&is_array($m['penalties'])&&count($m['penalties'])===2&&is_int($m['penalties'][0])&&is_int($m['penalties'][1])&&min($m['penalties'])>=0&&$m['penalties'][0]!==$m['penalties'][1]&&$decision['draw_rule']==='penalties')$winner=$m['penalties'][0]>$m['penalties'][1]?$m['home']:$m['away'];
                if($winner){usort($rows,fn($a,$b)=>($a['team']===$winner?-1:1));return $rows;}
            }
        }
        if(!$certify)return $rows; // Provisional display only; next() cannot certify it.
        throw new StateAdministrativeTie(['key'=>$key,'phase'=>$phase,'teams'=>$canonical,'reason'=>count($teams)===2?'extra_game_requires_federation_designation':'multi_club_extra_format_requires_federation_decision','state'=>'awaiting_federation_decision','source'=>'REC DF2026 art.17','simulation_only'=>true]);
    }
    /** Explicit CLI fixture decision. Not a claim about an actual federation act. */
    public static function schedule(array &$t,array $decision):void {
        if(PHP_SAPI!=='cli'||config('APP_ENV')==='production'||config('RUMO10_WORLD_FIXTURES','0')!=='1'||config('RUMO10_ADMIN_TEST_DESIGNATIONS','0')!=='1')throw new RuntimeException('Decisão somente em fixture CLI.');
        $old=$t['administrative_decisions'][$decision['key']??'']??null;
        if($old!==null){$auditBody=$old;unset($auditBody['sha256']);if(!hash_equals($old['sha256']??'',StateResultProof::digest($auditBody)))throw new RuntimeException('Decisão administrativa corrompida.');$prior=$old;$request=$decision;foreach(['schema','designated_at','sha256'] as $audit){unset($prior[$audit],$request[$audit]);}if(StateResultProof::digest($prior)!==StateResultProof::digest($request))throw new RuntimeException('Replay administrativo divergente.');return;}
        $decision['schema']='federation-fixture-designation-v1';$decision['designated_at']=gmdate('Y-m-d\TH:i:s\Z');$decision['sha256']=StateResultProof::digest($decision);
        self::replay($t,$decision);
    }
    public static function replay(array &$t,array $decision):void {
        $hash=$decision['sha256']??'';$body=$decision;unset($body['sha256']);if(($decision['schema']??null)!=='federation-fixture-designation-v1'||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',$decision['designated_at']??'')||!hash_equals($hash,StateResultProof::digest($body)))throw new RuntimeException('Auditoria da designação inválida.');
        $p=$t['administrative_pending']??throw new RuntimeException('Sem pendência administrativa.');
        if(($p['reason']??null)==='ma_permanence_tie_rule_not_verified')throw new RuntimeException('O critério MA requer fonte federativa; designar jogo extra DF não resolve esta pendência.');
        if(count($p['teams'])!==2||($decision['key']??null)!==$p['key']||($decision['kind']??null)!=='synthetic_test_designation'||empty($decision['reference'])||empty($decision['venue'])||!is_bool($decision['neutral']??null)||!in_array($decision['draw_rule']??null,['penalties','await_decision'],true))throw new RuntimeException('Designação federativa incompleta ou formato múltiplo não definido.');
        $pair=[$decision['home']??null,$decision['away']??null];sort($pair,SORT_STRING);if($pair!==$p['teams'])throw new RuntimeException('Clubes divergentes da pendência.');
        if(isset($t['administrative_decisions'][$p['key']])||count($t['administrative_decisions']??[])>=8)throw new RuntimeException('Decisão já registrada ou orçamento de jogos extras esgotado.');
        $round=max(array_column($t['matches'],'round'))+1;
        $t['matches'][]=Schedule::match($round,$decision['home'],$decision['away'])+['phase'=>'desempate_administrativo','group'=>'extra','administrative_key'=>$p['key'],'administrative_decision_digest'=>$hash,'neutral'=>$decision['neutral'],'venue'=>$decision['venue']];
        $t['phase_end']=$round;$t['administrative_decisions'][$p['key']]=$decision;unset($t['administrative_pending']);
    }
}
