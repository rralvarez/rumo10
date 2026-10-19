<?php
declare(strict_types=1);
/** Universo independente por carreira. Cadastro de 2026; resultados sempre simulados. */
final class Competition {
    private static ?array $referenceCatalog=null;
    private static array $referenceClubs=[];
    public static function referenceScope(array $definitions,callable $operation,array $clubs=[]):mixed {
        $previous=self::$referenceCatalog;$previousClubs=self::$referenceClubs;self::$referenceCatalog=$definitions;self::$referenceClubs=$clubs;
        try{return $operation();}finally{self::$referenceCatalog=$previous;self::$referenceClubs=$previousClubs;}
    }
    public static function referencePairs():array {
        $pairs=[];foreach(self::$referenceCatalog??[] as $id=>$c)if(!empty($c['test_fixture'])&&isset($c['progression_upper']))$pairs[]=[$c['progression_upper'],$id];return $pairs;
    }
    public static function catalog(): array {
        if(self::$referenceCatalog!==null)return self::$referenceCatalog;
        if(RumoHub::enabled())return LocalCompetitionCatalog::competitions(RumoHub::catalog()['competitions']);
        static $data; return $data??=LocalCompetitionCatalog::competitions(array_column(array_merge(json_decode(file_get_contents(dirname(__DIR__).'/database/competitions-2026.json'),true,512,JSON_THROW_ON_ERROR),json_decode(file_get_contents(dirname(__DIR__).'/database/state-competitions-2026.json'),true,512,JSON_THROW_ON_ERROR),json_decode(file_get_contents(dirname(__DIR__).'/database/international-competitions-2026.json'),true,512,JSON_THROW_ON_ERROR),json_decode(file_get_contents(dirname(__DIR__).'/database/europe-competitions-2026.json'),true,512,JSON_THROW_ON_ERROR),json_decode(file_get_contents(dirname(__DIR__).'/database/concacaf-competitions-2026.json'),true,512,JSON_THROW_ON_ERROR),json_decode(file_get_contents(dirname(__DIR__).'/database/africa-south-competitions-2026.json'),true,512,JSON_THROW_ON_ERROR),json_decode(file_get_contents(dirname(__DIR__).'/database/top250-competitions-2026.json'),true,512,JSON_THROW_ON_ERROR)),null,'id'));
    }
    public static function clubs(): array {
        if(self::$referenceClubs){$clubs=self::$referenceClubs;self::$referenceClubs=[];try{return array_replace(self::clubs(),$clubs);}finally{self::$referenceClubs=$clubs;}}
        if(RumoHub::enabled()){static $hubClubs;return $hubClubs??=LocalCompetitionCatalog::clubs(RumoHub::catalog()['clubs']);}
        static $data;
        if($data===null) {
            $data=array_column(json_decode(file_get_contents(dirname(__DIR__).'/database/official-clubs-2026.json'),true,512,JSON_THROW_ON_ERROR),null,'name');
            foreach(json_decode(file_get_contents(dirname(__DIR__).'/database/state-clubs-2026.json'),true,512,JSON_THROW_ON_ERROR) as $club) {
                $old=$data[$club['name']]??[];$club['competitions']=array_values(array_unique(array_merge($old['competitions']??[],$club['competitions'])));
                $data[$club['name']]=array_replace($old,$club);
            }
            foreach(array_merge(json_decode(file_get_contents(dirname(__DIR__).'/database/international-clubs-2026.json'),true,512,JSON_THROW_ON_ERROR),json_decode(file_get_contents(dirname(__DIR__).'/database/europe-clubs-2026.json'),true,512,JSON_THROW_ON_ERROR),json_decode(file_get_contents(dirname(__DIR__).'/database/concacaf-clubs-2026.json'),true,512,JSON_THROW_ON_ERROR),json_decode(file_get_contents(dirname(__DIR__).'/database/africa-south-clubs-2026.json'),true,512,JSON_THROW_ON_ERROR),json_decode(file_get_contents(dirname(__DIR__).'/database/top250-clubs-2026.json'),true,512,JSON_THROW_ON_ERROR)) as $club)$data[$club['name']]=$club;
        $data=LocalCompetitionCatalog::clubs($data);}return $data;
    }
    public static function attach(array $g,string $club): array {
        if(!isset(self::clubs()[$club])) throw new InvalidArgumentException('Escolha um clube participante de 2026.');
        $g['club']=$club; $g['official']=true; $g['edition']=2026;
        $g['memberships']=array_map(fn($c)=>$c['teams'],self::catalog());
        $g['competitions']=[]; $g['competition_history']=[];
        self::startSeason($g); return $g;
    }
    public static function startSeason(array &$g): void {
        $ids=[]; foreach(['state','sp','br','international'] as $family) foreach(self::catalog() as $id=>$c) if($c['family']===$family && in_array($g['club'],$g['memberships'][$id]??[],true)) $ids[]=$id;
        if(!$ids) throw new LogicException('Clube sem competição no universo.');
        $g['season_competitions']=$ids; $g['competition_cursor']=0; $g['competitions']=[]; $g['competition_results']=[];
        self::activate($g,$ids[0]);
    }
    private static function activate(array &$g,string $id): void {
        $g['competition_id']=$id; $g['tier']=self::catalog()[$id]['tier'];
        $g['competitions'][$id]=self::tournament($id,$g['memberships'][$id]);
        $g['teams']=$g['memberships'][$id]; $g['matches']=$g['competitions'][$id]['matches']; $g['round']=1;
        foreach($g['matches'] as $m)if(in_array($g['club'],[$m['home'],$m['away']],true)){$g['round']=$m['round'];break;}
    }
    public static function tournament(string $id,?array $teams=null): array {
        $c=self::catalog()[$id]; $teams??=$c['teams'];
        if($c['format']==='configured_state')return ConfiguredStateCompetition::create($c,$teams);
        if($c['format']==='alagoas')return AlagoasCompetition::create($c,$teams);
        if($c['format']==='amazonense')return AmazonenseCompetition::create($c,$teams);
        if($c['format']==='state')return StateCompetition::create($c,$teams);
        if($c['format']==='copa_brasil')return CopaBrasilCompetition::create($c,$teams);
        if($c['format']==='nordeste')return NordesteCompetition::create($c,$teams);
        if($c['format']==='national_d')return NationalDCompetition::create($c,$teams);
        if($c['format']==='national_c')return NationalCCompetition::create($c,$teams);
        if($c['format']==='concacaf')return ConcacafCompetition::create($c,$teams);
        if($c['format']==='world')return WorldCompetition::create($c,$teams);
        if($c['format']==='european')return EuropeanCompetition::create($c,$teams);
        if($c['format']==='international')return InternationalCompetition::create($c,$teams);
        $groups=[$teams];
        if(in_array($c['format'],['d_knockout','sp_groups'],true)) {
            $groups=$teams===$c['teams']?$c['groups']:array_chunk($teams,6);
            // Última chave pode variar quando a pirâmide nacional cresce nas temporadas simuladas.
            foreach($groups as &$group) if(count($group)%2) $group[]='__folga__'; unset($group);
        }
        $lots=$teams; shuffle($lots);
        $t=['id'=>$id,'teams'=>$teams,'format'=>$c['format'],'phase'=>'liga','groups'=>$groups,'initial_groups'=>$groups,'matches'=>[],'phase_start'=>1,'phase_end'=>0,'done'=>false,'champion'=>null,'promoted'=>[],'relegated'=>[],'ties'=>[],'lots'=>array_flip($lots),'rounds'=>[]];
        if($c['format']==='paulista_swiss' && $teams===$c['teams']) {
            foreach($c['fixtures'] as $fixture) $t['matches'][]=Schedule::match($fixture['round'],$fixture['home'],$fixture['away'])+['phase'=>'liga','group'=>'1'];
        } elseif($c['format']==='paulista_swiss') {
            // Novos participantes: oito rodadas sem repetição, incluindo os clássicos disponíveis.
            $calendar=Schedule::generate($teams,false);
            $t['matches']=array_values(array_filter($calendar,fn($m)=>$m['round']<=8));
            foreach($t['matches'] as &$m) $m+=['phase'=>'liga','group'=>'1']; unset($m);
        } else {
            foreach($groups as $i=>$group) foreach(Schedule::generate($group,in_array($c['format'],['league','b_playoff','d_knockout','sp_groups'],true)) as $m) {
                if(in_array('__folga__',[$m['home'],$m['away']],true)) continue;
                $t['matches'][]=$m+['phase'=>'liga','group'=>(string)($i+1)];
            }
        }
        $t['phase_end']=max(array_column($t['matches'],'round'));
        usort($t['matches'],fn($a,$b)=>$a['round']<=>$b['round']); return $t;
    }
    public static function standings(array $t,?array $teams=null,?string $phase=null): array {
        if($t['format']==='copa_brasil')return CopaBrasilCompetition::standings($t,$teams,$phase);
        if($t['format']==='nordeste')return NordesteCompetition::standings($t,$teams,$phase);
        if($t['format']==='national_d')return NationalDCompetition::standings($t,$teams,$phase);
        if($t['format']==='national_c')return NationalCCompetition::standings($t,$teams,$phase);
        if($t['format']==='configured_state')return ConfiguredStateCompetition::standings($t,$teams,$phase);
        if($t['format']==='alagoas')return AlagoasCompetition::standings($t,$teams,$phase);
        $teams??=$t['teams'];$cross=$t['format']==='amazonense' || ($t['format']==='state' && !empty(self::catalog()[$t['id']]['rules']['cross'])) || ($t['format']==='international' && str_starts_with($t['id'],'ar-'));
        $split=($t['id']==='sc-1' || $t['format']==='world') && $phase==='split';if($split)$cross=true;
        if($t['id']==='us-1')$cross=true;
        $matches=array_values(array_filter($t['matches'],fn($m)=>(!$phase || $m['phase']===$phase || ($split && $m['phase']==='liga')) && ($cross?(in_array($m['home'],$teams,true) || in_array($m['away'],$teams,true)):(in_array($m['home'],$teams,true) && in_array($m['away'],$teams,true)))));
        $rows=Schedule::table($matches,$teams);
        $overall=$phase==='quadrangular' && $t['id']==='sp-a2'?array_column(self::standings($t),null,'team'):[];
        foreach($rows as &$row) {
            $row['yellow']=0; $row['red']=0;
            foreach($matches as $m) if($m['status']==='finalizada') foreach(['home','away'] as $side) if($m[$side]===$row['team']) {
                $row['yellow']+=$m[$side.'_yellow']??0; $row['red']+=$m[$side.'_red']??0;
            }
        } unset($row);
        $counts=[]; foreach($rows as $r) {$key=implode(':',[$r['points'],$r['wins'],$r['gd'],$r['gf']]);$counts[$key]=($counts[$key]??0)+1;}
        usort($rows,function($a,$b)use($t,$matches,$counts,$overall,$phase) {
            if(in_array($t['id'],['pr-entrada','rs-entrada'],true))foreach(['points','wins','gd','gf'] as $key) {
                $cmp=($b[$key]/max(1,$b['played']))<=>($a[$key]/max(1,$a['played']));if($cmp)return $cmp;
            }
            $cmp=($b['points']<=>$a['points'])?:($b['wins']<=>$a['wins'])?:($b['gd']<=>$a['gd'])?:($b['gf']<=>$a['gf']); if($cmp)return $cmp;
            if($overall) {
                $aTotal=$overall[$a['team']];$bTotal=$overall[$b['team']];
                $cmp=($bTotal['points']<=>$aTotal['points'])?:($bTotal['wins']<=>$aTotal['wins'])?:($bTotal['gd']<=>$aTotal['gd'])?:($bTotal['gf']<=>$aTotal['gf']);if($cmp)return $cmp;
            }
            $key=implode(':',[$a['points'],$a['wins'],$a['gd'],$a['gf']]);
            if(str_starts_with($t['id'],'br-') && ($phase==='liga' || in_array($t['id'],['br-a','br-b'],true)) && $counts[$key]===2) {
                $gd=0; foreach($matches as $m) if($m['status']==='finalizada' && in_array($m['home'],[$a['team'],$b['team']],true) && in_array($m['away'],[$a['team'],$b['team']],true)) $gd+=($m['home']===$a['team']?1:-1)*($m['hg']-$m['ag']);
                if($gd)return -$gd;
            }
            return ($a['red']<=>$b['red'])?:($a['yellow']<=>$b['yellow'])?:($t['lots'][$a['team']]<=>$t['lots'][$b['team']]);
        }); return $rows;
    }
    public static function rank(array $t,array $teams,?string $phase=null): array {
        $rows=self::standings($t,$phase?$teams:null,$phase);
        return array_values(array_filter(array_column($rows,'team'),fn($team)=>in_array($team,$teams,true)));
    }
    public static function simulate(array &$m,array $g=[]): void {
        if($m['status']==='finalizada')return;
        TeamStrength::snapshot($m,$g);[$home,$away]=MatchVenue::expectations($m);
        $m['hg']=TeamStrength::goals($home); $m['ag']=TeamStrength::goals($away); $m['status']='finalizada';$m['minute']=90;
        $m['home_yellow']=random_int(0,5);$m['away_yellow']=random_int(0,5);$m['home_red']=random_int(0,10)===0?1:0;$m['away_red']=random_int(0,10)===0?1:0;
    }
    public static function leaguePhase(array &$t,array $groups,string $phase,bool|array $return=true): void {
        $start=$t['phase_end']+1; $t['phase_start']=$start; $t['phase']=$phase; $t['groups']=$groups; $t['ties']=[];
        foreach($groups as $i=>$group) {
            if(count($group)%2)$group[]='__folga__';
            foreach(Schedule::generate($group,is_array($return)?$return[$i]:$return) as $m) {
                if(in_array('__folga__',[$m['home'],$m['away']],true))continue;
                $m['round']+=$start-1;$t['matches'][]=$m+['phase'=>$phase,'group'=>(string)($i+1)];
            }
        }
        $t['phase_end']=max(array_column($t['matches'],'round')); usort($t['matches'],fn($a,$b)=>$a['round']<=>$b['round']);
    }
    public static function knockout(array &$t,array $pairs,string $phase,int $legs=2,string $tieRule='penalties'): void {
        $start=$t['phase_end']+1; $t['phase_start']=$start; $t['phase_end']=$start+$legs-1; $t['phase']=$phase; $t['groups']=$pairs; $t['ties']=[];
        foreach($pairs as $i=>[$better,$other]) {
            $t['ties'][]=['a'=>$better,'b'=>$other,'winner'=>null,'rule'=>$tieRule];
            $t['matches'][]=Schedule::match($start,$legs===1?$better:$other,$legs===1?$other:$better)+['phase'=>$phase,'group'=>(string)($i+1),'tie'=>$i];
            if($legs===2)$t['matches'][]=Schedule::match($start+1,$better,$other)+['phase'=>$phase,'group'=>(string)($i+1),'tie'=>$i];
        }
        usort($t['matches'],fn($a,$b)=>$a['round']<=>$b['round']);
    }
    public static function seededPairs(array $teams): array {
        $out=[];for($i=0;$i<count($teams)/2;$i++)$out[]=[$teams[$i],$teams[count($teams)-1-$i]];return $out;
    }
    public static function resolve(array &$t): array {
        $winners=[];
        foreach($t['ties'] as $i=>&$tie) {
            if(!$tie['winner']) {
                $gd=0;$points=0;$last=null;
                foreach($t['matches'] as $j=>$m) if($m['phase']===$t['phase'] && ($m['tie']??null)===$i) {
                    if($m['status']!=='finalizada')throw new LogicException('Confronto incompleto.');
                    $delta=($m['home']===$tie['a']?1:-1)*($m['hg']-$m['ag']);$gd+=$delta;$points+=$delta>0?3:($delta<0?-3:0);$last=$j;
                }
                if($points || $gd)$tie['winner']=($points?:$gd)>0?$tie['a']:$tie['b'];
                elseif($tie['rule']==='advantage')$tie['winner']=$tie['a'];
                else {
                    $tie['winner']=random_int(0,1)?$tie['a']:$tie['b'];
                    $loserScore=random_int(2,4);$m=&$t['matches'][$last];$homeWins=$m['home']===$tie['winner'];
                    $m['penalties']=[$homeWins?$loserScore+1:$loserScore,$homeWins?$loserScore:$loserScore+1];
                    $m['log'][]=['minute'=>90,'text'=>'Nos pênaltis: '.$m['home'].' '.$m['penalties'][0].' × '.$m['penalties'][1].' '.$m['away'].'.'];unset($m);
                }
            }
            $winners[]=$tie['winner'];
        } unset($tie); return $winners;
    }
    public static function nextPhase(array &$t): void {
        foreach($t['matches'] as $m) if($m['status']!=='finalizada')throw new LogicException('Fase incompleta.');
        if($t['format']==='copa_brasil'){CopaBrasilCompetition::next($t);return;}
        if($t['format']==='nordeste'){NordesteCompetition::next($t);return;}
        if($t['format']==='national_d'){NationalDCompetition::next($t);return;}
        if($t['format']==='national_c'){NationalCCompetition::next($t);return;}
        if($t['format']==='configured_state'){ConfiguredStateCompetition::next($t);return;}
        if($t['format']==='alagoas'){AlagoasCompetition::next($t);return;}
        if($t['format']==='amazonense'){AmazonenseCompetition::next($t);return;}
        if($t['format']==='state'){StateCompetition::next($t);return;}
        if($t['format']==='concacaf'){ConcacafCompetition::next($t);return;}
        if($t['format']==='world'){WorldCompetition::next($t);return;}
        if($t['format']==='international'){InternationalCompetition::next($t);return;}
        if($t['format']==='european'){EuropeanCompetition::next($t);return;}
        $format=$t['format'];$phase=$t['phase'];
        if($phase==='liga') {
            $ranking=self::rank($t,$t['teams'],'liga');$t['initial_ranking']=$ranking;
            if(str_starts_with($t['id'],'sp-') && $t['id']!=='sp-segunda')$t['relegated']=array_slice($ranking,-2);
            elseif($t['id']==='br-a' || $t['id']==='br-b')$t['relegated']=array_slice($ranking,-4);
            elseif($t['id']==='br-c')$t['relegated']=array_slice($ranking,-2);
            if($format==='league') {$t['champion']=$ranking[0];$t['done']=true;return;}
            if($format==='b_playoff') {$t['champion']=$ranking[0];$t['promoted']=array_slice($ranking,0,2);self::knockout($t,[[$ranking[2],$ranking[5]],[$ranking[3],$ranking[4]]],'playoff',2,'advantage');return;}
            if($format==='quadrangular') {
                $groups=$t['id']==='br-c'?[[$ranking[0],$ranking[3],$ranking[4],$ranking[7]],[$ranking[1],$ranking[2],$ranking[5],$ranking[6]]]:[[$ranking[0],$ranking[2],$ranking[5],$ranking[7]],[$ranking[1],$ranking[3],$ranking[4],$ranking[6]]];
                self::leaguePhase($t,$groups,'quadrangular');return;
            }
            if($format==='d_knockout') {
                $ranks=[]; foreach($t['groups'] as $group)$ranks[]=array_slice(self::rank($t,array_values(array_diff($group,['__folga__'])),'liga'),0,4);
                $pairs=[];for($i=0;$i<count($ranks);$i+=2) {
                    $a=$ranks[$i];$b=$ranks[$i+1];
                    $pairs[]=[$a[0],$b[3]];$pairs[]=[$b[1],$a[2]];$pairs[]=[$b[0],$a[3]];$pairs[]=[$a[1],$b[2]];
                }
                self::knockout($t,$pairs,'segunda');return;
            }
            if($format==='sp_groups') {
                $qualified=[];foreach($t['groups'] as $group)$qualified=array_merge($qualified,array_slice(self::rank($t,$group,'liga'),0,4));
                self::knockout($t,self::seededPairs(self::rank($t,$qualified)),'oitavas');return;
            }
            self::knockout($t,self::seededPairs(array_slice($ranking,0,8)),'quartas',$format==='paulista_swiss'?1:2,$format==='sp_advantage'?'advantage':'penalties');return;
        }
        if($phase==='quadrangular') {
            $groups=[];foreach($t['groups'] as $group)$groups[]=self::rank($t,$group,'quadrangular');
            if($t['id']==='br-c') {
                $t['promoted']=[$groups[0][0],$groups[0][1],$groups[1][0],$groups[1][1]];
                self::knockout($t,[self::rank($t,[$groups[0][0],$groups[1][0]])],'final');
            } else {
                $pairs=[self::rank($t,[$groups[0][0],$groups[1][1]]),self::rank($t,[$groups[1][0],$groups[0][1]])];
                self::knockout($t,$pairs,'semifinal',2,'advantage');
            }return;
        }
        $winners=self::resolve($t);
        if($phase==='playoff') {$t['promoted']=array_merge($t['promoted'],$winners);$t['done']=true;return;}
        if($phase==='final') {$t['champion']=$winners[0];$t['done']=true;return;}
        if($phase==='semifinal') {
            if($format==='d_knockout') {
                $gained=[];$finalists=[];
                foreach($t['ties'] as $tie) if(!empty($tie['access_playoff']))$gained[]=$tie['winner'];else $finalists[]=$tie['winner'];
                $t['promoted']=array_merge($t['promoted'],$gained);$winners=$finalists;
            }
            if(str_starts_with($t['id'],'sp-') && $t['id']!=='sp-a1')$t['promoted']=$winners;
            self::knockout($t,[self::rank($t,$winners)],'final',2,($format==='sp_advantage' || $t['id']==='sp-a2')?'advantage':'penalties');return;
        }
        if($format==='d_knockout') {
            if($phase==='quartas') {
                $t['promoted']=$winners;$losers=array_values(array_diff(array_merge(...$t['groups']),$winners));
                // Playoffs de acesso paralelos às semifinais, com os quatro eliminados nas quartas.
                $losers=self::rank($t,$losers);$t['playoff_pairs']=self::seededPairs($losers);
                $pairs=[self::rank($t,[$winners[0],$winners[1]]),self::rank($t,[$winners[2],$winners[3]])];
                self::knockout($t,$pairs,'semifinal');
                $mainTies=$t['ties'];$offset=count($mainTies);
                foreach($t['playoff_pairs'] as $i=>[$a,$b]) {
                    $t['ties'][]=['a'=>$a,'b'=>$b,'winner'=>null,'rule'=>'advantage','access_playoff'=>true];
                    $t['matches'][]=Schedule::match($t['phase_start'],$b,$a)+['phase'=>'semifinal','group'=>'P'.($i+1),'tie'=>$offset+$i];
                    $t['matches'][]=Schedule::match($t['phase_end'],$a,$b)+['phase'=>'semifinal','group'=>'P'.($i+1),'tie'=>$offset+$i];
                }return;
            }
            $next=match($phase){'segunda'=>'terceira','terceira'=>'oitavas','oitavas'=>'quartas',default=>throw new LogicException('Fase inválida')};
            $ordered=$next==='quartas'?self::rank($t,$winners):$winners;
            if($next==='quartas')$pairs=[[$ordered[0],$ordered[7]],[$ordered[3],$ordered[4]],[$ordered[1],$ordered[6]],[$ordered[2],$ordered[5]]];
            else {
                $pairs=[];foreach(array_chunk($ordered,8) as $block) foreach([[0,5],[1,4],[2,7],[3,6]] as [$a,$b])$pairs[]=[$block[$a],$block[$b]];
            }
            foreach($pairs as &$pair)$pair=self::rank($t,$pair);unset($pair);
            self::knockout($t,$pairs,$next);return;
        }
        self::knockout($t,self::seededPairs(self::rank($t,$winners)),$phase==='oitavas'?'quartas':'semifinal',$format==='paulista_swiss'?1:2,$format==='sp_advantage'?'advantage':'penalties');
    }
    public static function finish(array &$t,array $g=[]): void {
        $guard=0;while(!$t['done'] && $guard++<16){foreach($t['matches'] as &$m)self::simulate($m,$g);unset($m);self::nextPhase($t);}
        if(!$t['done'])throw new LogicException('Competição não finalizou.');
    }
    public static function afterWeekend(array &$g): void {
        if(isset($g['world_runtime'])){WorldSeason::afterWeekend($g);return;}
        $id=$g['competition_id'];$t=&$g['competitions'][$id];$t['matches']=$g['matches'];
        while(true) {
            foreach($t['matches'] as &$m) if($m['round']<=$g['round'])self::simulate($m,$g);unset($m);
            $next=null;foreach($t['matches'] as $m)if($m['status']!=='finalizada' && in_array($g['club'],[$m['home'],$m['away']],true)){$next=$m['round'];break;}
            if($next!==null){$g['matches']=$t['matches'];$g['round']=$next;$g['stage']='treino';return;}
            // Eliminação não cancela os jogos dos demais clubes nem inventa adversário.
            foreach($t['matches'] as &$m)self::simulate($m,$g);unset($m);
            if($t['done'])break;
            self::nextPhase($t);
            if($t['done'])break;
        }
        $g['matches']=$t['matches'];$g['competition_results'][$id]=self::result($t,$g['club']);
        Progression::competition($g,$id,$g['competition_results'][$id]);
        $g['stage']='competicao_resumo';$g['feedback']=null;
    }
    public static function result(array $t,string $club): array {
        if($t['format']==='copa_brasil')return CopaBrasilCompetition::result($t,$club);
        if($t['format']==='nordeste')return NordesteCompetition::result($t,$club);
        if($t['format']==='national_d')return NationalDCompetition::result($t,$club);
        if($t['format']==='national_c')return NationalCCompetition::result($t,$club);
        if($t['format']==='configured_state'&&!empty($t['done']))return ['name'=>self::catalog()[$t['id']]['name'],'champion'=>$t['champion'],'promoted'=>false,'relegated'=>false,'position'=>array_search($club,$t['final_ranking'],true)+1,'group'=>null,'access_pending'=>in_array($club,$t['promoted'],true),'continuity'=>['valid'=>false,'reason'=>'state_future_memberships_and_mandatory_calendar_pending'],'relegation_pending'=>in_array($club,$t['relegated'],true),'titles'=>[]];
        if($t['format']==='alagoas'&&!empty($t['done']))return ['name'=>self::catalog()[$t['id']]['name'],'champion'=>$t['champion'],'promoted'=>in_array($club,$t['promoted'],true),'relegated'=>in_array($club,$t['relegated'],true),'position'=>array_search($club,$t['final_ranking'],true)+1,'group'=>null,'access_pending'=>false,'continuity'=>CompetitionContinuity::status($t['id']),'relegation_pending'=>false,'titles'=>[]];
        $position=array_search($club,$t['initial_ranking'],true)+1;$group=null;
        if(count($t['initial_groups'])>1)foreach($t['initial_groups'] as $i=>$teams)if(in_array($club,$teams,true)) {
            $ranking=$t['format']==='international'?InternationalCompetition::ranking($t,$teams,'liga'):self::rank($t,array_values(array_diff($teams,['__folga__'])),'liga');$position=array_search($club,$ranking,true)+1;$group=$i+1;
        }
        $continuity=CompetitionContinuity::status($t['id']);$promoted=in_array($club,$t['promoted'],true) && $continuity['valid'];
        return ['name'=>self::catalog()[$t['id']]['name'],'champion'=>$t['champion'],'promoted'=>$promoted,'relegated'=>in_array($club,$t['relegated'],true),'position'=>$position,'group'=>$group,'access_pending'=>false,'continuity'=>$continuity,'relegation_pending'=>$t['format']==='international' && str_ends_with($t['id'],'-2'),'titles'=>$t['titles']??[]];
    }
    public static function continueSeason(array &$g): void {
        if(isset($g['world_runtime'])){WorldSeason::next($g);return;}
        if(++$g['competition_cursor']<count($g['season_competitions'])){self::activate($g,$g['season_competitions'][$g['competition_cursor']]);$g['stage']='treino';$g['states']['energia']=85;}
        else {$g['stage']='resumo';self::completeWorld($g);}
    }
    public static function completeWorld(array &$g): void {
        CompetitionContinuity::extend($g);
        // A expansão entra na próxima janela de mercado, sem refazer campeonatos já salvos.
        foreach(self::catalog() as $id=>$c)if($c['family']==='international' && !isset($g['memberships'][$id]))$g['memberships'][$id]=$c['teams'];
        foreach($g['memberships'] as $id=>$teams) {
            if(!isset($g['competitions'][$id]))$g['competitions'][$id]=self::tournament($id,$g['memberships'][$id]);
            self::finish($g['competitions'][$id],$g);
            self::compactResults($g['competitions'][$id],$g['club']);
        }
        if(count($g['memberships']['br-c'])>=28)$g['competitions']['br-c']['relegated']=array_slice($g['competitions']['br-c']['initial_ranking'],-6);
        EuropeanCompetition::barrages($g);
        foreach($g['season_competitions'] as $id)$g['competition_results'][$id]=self::result($g['competitions'][$id],$g['club']);
        $g['next_memberships']=$g['memberships'];
        foreach(CompetitionContinuity::pairs() as [$upper,$lower]) {
            if(!isset($g['competitions'][$upper],$g['competitions'][$lower]))continue;
            $down=$g['competitions'][$upper]['relegated'];$up=$g['competitions'][$lower]['promoted'];
            $g['next_memberships'][$upper]=array_values(array_diff($g['next_memberships'][$upper],$down));
            $g['next_memberships'][$lower]=array_values(array_diff($g['next_memberships'][$lower],$up));
            $g['next_memberships'][$upper]=array_merge($g['next_memberships'][$upper],$up);
            $g['next_memberships'][$lower]=array_merge($g['next_memberships'][$lower],$down);
        }
        // Série D conserva acesso nacional para todos os clubes do universo jogável.
        // Licenciamento e novas vagas oriundas de outros estaduais não são simulados.
        $national=array_merge($g['next_memberships']['br-a'],$g['next_memberships']['br-b'],$g['next_memberships']['br-c'],$g['next_memberships']['br-d']);
        foreach(['sp-a1','sp-a2','sp-a3','sp-a4','sp-segunda'] as $id) foreach($g['competitions'][$id]['initial_ranking'] as $club) {
            if(count($g['next_memberships']['br-d'])>=96)break 2;
            if(!in_array($club,$national,true)){$g['next_memberships']['br-d'][]=$club;$national[]=$club;}
        }
        TeamStrength::nextSeason($g);
    }
    /** Partidas alheias concluídas: manter resultados, eliminar somente estruturas vazias do personagem. */
    public static function compactResults(array &$t,string $club):void {
        if(empty($t['done']))return;
        foreach($t['matches'] as &$match){
            if(in_array($club,[$match['home'],$match['away']],true))continue;
            // Dados futuros de NPCs não podem ser descartados pela compactação.
            if(!empty($match['log']) || array_filter($match['stats']??[],fn($v)=>$v!==0 && $v!==0.0 && $v!==null))continue;
            unset($match['stats'],$match['log'],$match['lineup'],$match['entry'],$match['exit'],$match['minute']);
        }unset($match);
    }
    public static function offerPool(array $g): array {
        return TransferMarket::pool($g);
    }
    public static function newSeason(array &$g): void {
        if(isset($g['world_runtime'])){WorldSeason::renew($g);return;}
        CompetitionContinuity::reconcileAmazonas($g);
        $g['club_strengths']=$g['next_club_strengths']??($g['club_strengths']??[]);unset($g['next_club_strengths']);
        $g['competition_history'][]=['season'=>$g['season']-1,'results'=>$g['competition_results']];
        $g['memberships']=$g['next_memberships'];unset($g['next_memberships']);
        CompetitionContinuity::extend($g);
        self::startSeason($g);
    }
    public static function label(array $g): string {
        if(empty($g['official']))return 'Liga regional';
        if(!empty($g['starting']))return isset($g['entry_division'])?self::catalog()[$g['entry_division']]['name']:'Seu primeiro contrato';
        return self::catalog()[$g['competition_id']]['name'];
    }
    public static function phaseLabel(array $g): string {
        if(empty($g['official']))return 'Rodada '.$g['round'].' de 14';
        if(!empty($g['starting']))return 'Início da carreira';
        $t=$g['competitions'][$g['competition_id']];
        if($t['done'])return 'Competição encerrada';
        if(in_array($t['adapter_version']??null,[ConfiguredStateCompetition::INTERGROUP_VERSION,ConfiguredStateCompetition::CONDITIONAL_VERSION,ConfiguredStateCompetition::MARANHENSE_VERSION,ConfiguredStateCompetition::ACRE_VERSION,ConfiguredStateCompetition::PARALLEL_VERSION],true)){$current=$t['phase'];foreach($g['matches'] as $match)if($match['round']===$g['round']&&in_array($g['club'],[$match['home'],$match['away']],true)){$current=$match['phase'];break;}return ($current==='descenso'?'Permanência / descenso':($current==='liga'?(in_array($t['adapter_version']??null,[ConfiguredStateCompetition::MARANHENSE_VERSION,ConfiguredStateCompetition::ACRE_VERSION],true)?'Primeira fase · pontos corridos':'Primeira fase intergrupos'):ucfirst($current))).' · rodada simulada '.$g['round'];}
        if($t['format']==='amazonense')return ($t['phase']==='am_final'?'Final geral':($t['am_turn'].'º turno · '.(['liga'=>'Primeira fase','am_playoff'=>'Repescagem','am_semifinal'=>'Semifinal','am_turn_final'=>'Final'][$t['phase']]??'Partida'))).' · jogo '.($g['round']-$t['phase_start']+1).' de '.($t['phase_end']-$t['phase_start']+1);
        $phase=['liga'=>'Primeira fase','quadrangular'=>'Quadrangular','hexagonal'=>'Hexagonal final','segunda'=>'Segunda fase','terceira'=>'Terceira fase','oitavas'=>'Oitavas de final','quartas'=>'Quartas de final','semifinal'=>'Semifinais / playoffs','final'=>'Final','playoff'=>'Repescagem','clausura'=>'Clausura','intermedio'=>'Intermedio','intermedio_final'=>'Final do Intermedio','competencia_final'=>'Final do Competencia','regular'=>'Fase regular','nacional'=>'Grupos nacionais','acesso'=>'Final pelo acesso','reduzido'=>'Playoffs pelo segundo acesso','wild_card'=>'Wild card','serie_1'=>'Série MLS · partida 1','serie_2'=>'Série MLS · partida 2','serie_3'=>'Série MLS · desempate','semifinal_conferencia'=>'Semifinal de conferência','final_conferencia'=>'Final de conferência','mls_cup'=>'MLS Cup','split'=>'Fase de grupos finais'][$t['phase']]??ucfirst(str_replace('_',' ',$t['phase']));
        return $phase.' · jogo '.($g['round']-$t['phase_start']+1).' de '.($t['phase_end']-$t['phase_start']+1);
    }
    public static function playerMatches(array $g): array {
        if(empty($g['official']))return $g['matches'];
        $all=[];foreach($g['season_competitions'] as $id) {
            $matches=$id===$g['competition_id']?$g['matches']:($g['competitions'][$id]['matches']??[]);
            foreach($matches as $m)if(in_array($g['club'],[$m['home'],$m['away']],true))$all[]=$m+['competition'=>$id];
        }return $all;
    }
}
