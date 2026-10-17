<?php
declare(strict_types=1);

/** REC 2026 sports adapter. Draw outcomes/dates are simulated and pinned per save. */
final class CopaBrasilCompetition {
    private static function draw(array $t,array $clubs,string $label):array {
        usort($clubs,static fn($a,$b)=>strcmp(hash('sha256',$t['draw_seed'].'|'.$label.'|'.$a),hash('sha256',$t['draw_seed'].'|'.$label.'|'.$b)));return $clubs;
    }
    private static function flip(array $t,string $label):bool {return hexdec(substr(hash('sha256',$t['draw_seed'].'|'.$label),0,2))%2===1;}
    private static function ranked(array $t,array $clubs):array {
        $r=$t['sports_contract']['rank'];usort($clubs,static fn($a,$b)=>(($r[$a]??9999)<=>($r[$b]??9999))?:($t['lots'][$a]<=>$t['lots'][$b]));return $clubs;
    }
    public static function create(array $c,array $teams,?string $seed=null):array {
        $r=$c['sports_contract']??[];$expected=array_column(CopaBrasilReference::reference()['participants'],'club');$a=$teams;$b=$expected;sort($a);sort($b);
        if(($c['edition']??null)!==2026||($r['id']??null)!=='cbf-copa-brasil-sports-2026-v1'||$a!==$b||count(array_unique($teams))!==126)throw new RuntimeException('Copa do Brasil: edição/inscrições divergentes.');
        $first=array_merge(...$r['initial_pairs']);$slots=array_merge(...array_values($r['pots']));$late=array_values(array_filter($slots,fn($x)=>!preg_match('/^w\d+$/D',$x)));$prior=array_column(array_filter(CopaBrasilReference::reference()['participants'],fn($row)=>$row['criterion']!==3),'club');
        if(count($first)!==28||count(array_unique($first))!==28||count($slots)!==88||count(array_unique($slots))!==88||count($late)!==74||array_intersect($first,$late)||count(array_unique(array_merge($first,$late,$prior)))!==126)throw new RuntimeException('Potes/primeira fase incompatíveis.');
        foreach($r['pots'] as $pot)if(count($pot)!==11)throw new RuntimeException('Pote incompleto.');
        foreach(array_merge($late,$prior) as $club)if(!is_int($r['rank'][$club]??null)||$r['rank'][$club]<1)throw new RuntimeException('RNC incompleto.');
        foreach([2=>44,3=>24,4=>12] as $phase=>$count){$homes=$r['home_slots_phase'.$phase]??[];if(count($homes)!==$count||array_diff($homes,[0,1]))throw new RuntimeException('Matriz de mandos incompleta.');}
        $seed??=bin2hex(random_bytes(16));if(!preg_match('/^[a-f0-9]{32}$/D',$seed))throw new RuntimeException('Seed do sorteio inválida.');
        $t=['id'=>$c['id'],'teams'=>$teams,'format'=>'copa_brasil','phase'=>'fase1','groups'=>[],'initial_groups'=>[$teams],'initial_ranking'=>$teams,'matches'=>[],'phase_start'=>1,'phase_end'=>0,'done'=>false,'champion'=>null,'promoted'=>[],'relegated'=>[],'ties'=>[],'rounds'=>[],'draw_seed'=>$seed,'sports_contract'=>$r,'winners_by_group'=>[],'eliminated'=>[],'phase_number'=>1,'lots'=>[],'draws'=>[]];
        $t['lots']=array_flip(self::draw($t,$teams,'rank-tie'));
        $entry=[];foreach(CopaBrasilReference::reference()['participants'] as $row)$entry[$row['club']]=$row['entry_phase']??2;
        foreach(array_merge(...$r['initial_pairs']) as $club){if(!isset($entry[$club])||$entry[$club]!==2)throw new RuntimeException('Primeira fase sem inscrição estadual.');$entry[$club]=1;}
        if(count(array_filter($entry,fn($p)=>$p===1))!==28)throw new RuntimeException('Primeira fase exige 28 clubes distintos.');
        $t['entry_phases']=$entry;$pairs=$r['initial_pairs'];foreach($pairs as $i=>&$p)if(self::flip($t,'home1:'.$i))$p=array_reverse($p);unset($p);
        self::schedule($t,$pairs,1);return $t;
    }
    private static function schedule(array &$t,array $pairs,int $phase):void {
        $all=array_merge(...$pairs);$new=array_values(array_filter($all,fn($club)=>$t['entry_phases'][$club]===$phase));$w=array_values(array_diff($all,$new));CopaBrasilReference::validateEntry($phase,$w,$new,$t['entry_phases']);
        $legs=CopaBrasilReference::phases()[$phase][3];Competition::knockout($t,$pairs,'fase'.$phase,$legs);$t['phase_number']=$phase;$t['draws'][(string)$phase]=$pairs;
        if($phase===9)foreach($t['matches'] as &$m)if($m['phase']==='fase9')$m['venue_basis']='CBF designation not certified; administrative home slot';unset($m);
    }
    private static function slot(array $t,string $slot):string {
        if($slot[0]==='w')return $t['winners_by_group'][substr($slot,1)]??throw new RuntimeException('Grupo anterior sem vencedor.');
        $c=self::ranked($t,array_keys(array_filter($t['entry_phases'],fn($p)=>$p===3)));return $c[(int)substr($slot,1)-1];
    }
    public static function next(array &$t):void {
        if($t['done'])return;
        foreach($t['matches'] as $m)if($m['status']!=='finalizada')throw new RuntimeException('Fase da Copa do Brasil incompleta.');
        $n=$t['phase_number'];$participants=array_merge(...array_map(fn($p)=>[$p['a'],$p['b']],$t['ties']));
        // Same score/penalty resolver as D; two-leg points/GD is equivalent for two games.
        foreach($t['ties'] as $i=>&$tie){$gd=0;$indices=[];foreach($t['matches'] as $j=>$m)if($m['phase']===$t['phase']&&($m['tie']??null)===$i){if(!is_int($m['hg'])||!is_int($m['ag'])||$m['hg']<0||$m['ag']<0)throw new RuntimeException('Placar da copa inválido.');$gd+=($m['home']===$tie['a']?1:-1)*($m['hg']-$m['ag']);$indices[]=$j;}
            foreach($indices as $j)if(isset($t['matches'][$j]['penalties'])&&($gd!==0||$j!==end($indices)))throw new RuntimeException('Pênaltis fora de contexto.');
            $tie['winner']=null;
        }unset($tie);
        $w=NationalDCompetition::resolve($t);$start=[1=>1,2=>15,3=>59,4=>83,5=>95,6=>111,7=>119,8=>123,9=>125][$n];
        foreach($w as $i=>$club)$t['winners_by_group'][(string)($start+$i)]=$club;
        $t['eliminated'][(string)$n]=self::ranked($t,array_values(array_diff($participants,$w)));
        if($n===9){$t['champion']=$w[0];$rank=$w;for($i=9;$i>=1;$i--)$rank=array_merge($rank,$t['eliminated'][(string)$i]);if(count(array_unique($rank))!==126||count($t['matches'])!==155)throw new RuntimeException('Copa sem encerramento íntegro.');$t['final_ranking']=$rank;$t['done']=true;return;}
        $next=$n+1;$pairs=[];$r=$t['sports_contract'];
        if($next===2){$pots=[];foreach($r['pots'] as $label=>$slots){$clubs=array_map(fn($s)=>$s[0]==='w'?self::slot($t,$s):$s,$slots);$pots[$label]=self::draw($t,$clubs,'pot:'.$label);}
            for($i=0;$i<11;$i++)foreach([['A','H'],['B','G'],['C','F'],['D','E']] as [$a,$b])$pairs[]=[$pots[$a][$i],$pots[$b][$i]];
            foreach($pairs as $i=>&$pair)if($r['home_slots_phase2'][$i]===1)$pair=array_reverse($pair);unset($pair);
        }elseif($next===3){foreach($r['phase3_slots'] as $i=>$slots){$p=array_map(fn($s)=>self::slot($t,$s),$slots);$pairs[]=$r['home_slots_phase3'][$i]===1?array_reverse($p):$p;}
        }elseif($next===4){for($i=0;$i<12;$i++){$p=[$w[$i*2],$w[$i*2+1]];$pairs[]=$r['home_slots_phase4'][$i]===1?array_reverse($p):$p;}
        }elseif($next===5){$new=array_keys(array_filter($t['entry_phases'],fn($p)=>$p===5));$rank=self::ranked($t,array_merge($w,$new));$a=self::draw($t,array_slice($rank,0,16),'pot5:a');$b=self::draw($t,array_slice($rank,16),'pot5:b');for($i=0;$i<16;$i++)$pairs[]=[$a[$i],$b[$i]];
        }elseif($next===6||$next===7){$order=self::draw($t,$w,'phase:'.$next);$pairs=array_chunk($order,2);
        }else{$pairs=array_chunk($w,2);}
        if($next>=5)foreach($pairs as $i=>&$p)if(self::flip($t,'home'.$next.':'.$i))$p=array_reverse($p);unset($p);
        self::schedule($t,$pairs,$next);
    }
    public static function standings(array $t,?array $teams=null,?string $phase=null):array {
        $copy=$t;if($phase!==null)$copy['matches']=array_values(array_filter($t['matches'],fn($m)=>$m['phase']===$phase));
        $rows=NationalCCompetition::standings($copy);$rows=array_values(array_filter($rows,fn($r)=>in_array($r['team'],$teams??$t['teams'],true)));
        if($t['done']&&$phase===null){$order=array_flip($t['final_ranking']);usort($rows,fn($a,$b)=>$order[$a['team']]<=>$order[$b['team']]);}return $rows;
    }
    public static function result(array $t,string $club):array {
        if(!$t['done']||!in_array($club,$t['teams'],true))throw new RuntimeException('Copa sem resultado final verificável.');
        $reached=9;foreach($t['eliminated'] as $phase=>$clubs)if(in_array($club,$clubs,true))$reached=(int)$phase;
        return ['name'=>Competition::catalog()[$t['id']]['name'],'champion'=>$t['champion'],'position'=>array_search($club,$t['final_ranking'],true)+1,'group'=>null,'sports_format'=>'copa_brasil','phase_reached'=>$reached,'ranking_basis'=>'elimination_round_then_reference_rnc_for_presentation_not_official_full_ranking','promoted'=>false,'relegated'=>false,'access_pending'=>false,'relegation_pending'=>false,'continuity'=>['valid'=>false,'reason'=>'future_cup_entries_pending'],'titles'=>[]];
    }
    public static function verify(array $t):bool {
        if(empty($t['done'])||count($t['matches'])!==155)return false;
        $saved=[];$key=fn($m)=>implode('|',[$m['phase'],$m['round'],$m['home'],$m['away']]);foreach($t['matches'] as $m){if(isset($saved[$key($m)])||$m['status']!=='finalizada')return false;$saved[$key($m)]=$m;}
        try{$r=self::create(Competition::catalog()[$t['id']],$t['teams'],$t['draw_seed']);while(!$r['done']){foreach($r['matches'] as &$m){if($m['status']==='finalizada')continue;$s=$saved[$key($m)]??null;if(!$s)return false;foreach(['status','hg','ag','penalties'] as $f)if(isset($s[$f]))$m[$f]=$s[$f];}unset($m);self::next($r);}
            return $r['champion']===$t['champion']&&$r['final_ranking']===$t['final_ranking']&&$r['draws']===$t['draws']&&$r['winners_by_group']===$t['winners_by_group'];
        }catch(RuntimeException $e){return false;}
    }
}
