<?php
declare(strict_types=1);
final class Game {
    public static function create(string $name,string $city,string $uf,string $position,?string $club=null): array {
        $g=self::legacyCreate($name,$city,$uf,$position);
        return $club!==null?Competition::attach($g,$club):Onboarding::prepare($g);
    }
    public static function legacyCreate(string $name,string $city,string $uf,string $position): array {
        if(!in_array($position,Rules::POSITIONS,true) || mb_strlen($name)<2 || mb_strlen($name)>60 || mb_strlen($city)<2 || mb_strlen($city)>80 || !in_array($uf,explode(' ','AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO'),true)) throw new InvalidArgumentException('Confira nome, cidade, UF e posição.');
        $skills=[]; foreach(Rules::SKILLS as $k) $skills[$k]=['value'=>45,'xp'=>0];
        $main=['zagueiro'=>'desarme','lateral'=>'velocidade','volante'=>'posicionamento','meia'=>'passe','ponta'=>'drible','centroavante'=>'finalizacao'][$position]; $skills[$main]['value']=55;
        $states=array_fill_keys(Rules::STATES,50); $states['energia']=85;
        foreach(Rules::STATES as $k) if(str_starts_with($k,'risco_')) $states[$k]=5;
        return ['name'=>$name,'city'=>$city,'uf'=>$uf,'position'=>$position,'age'=>Rules::AGE,'season'=>1,'round'=>1,'stage'=>'handicap','version'=>0,'club'=>Rules::TEAMS[0],'tier'=>1,'handicap'=>null,'skills'=>$skills,'states'=>$states,'teams'=>Rules::TEAMS,'matches'=>Schedule::generate(Rules::TEAMS),'history'=>[],'offers'=>[],'feedback'=>null];
    }
    public static function currentIndex(array $g): int {
        foreach($g['matches'] as $i=>$m) if($m['round']===$g['round'] && in_array($g['club'],[$m['home'],$m['away']],true)) return $i;
        throw new LogicException('Partida não encontrada.');
    }
    public static function situation(array $g): ?array {
        $all=require dirname(__DIR__).'/database/situations.php'; $r=$g['round'];
        $id=match($g['stage']) {
            'treino'=>1+(($r-1)%3),
            'pre_jogo'=>($g['states']['energia']<40 || $g['states']['confianca_treinador']<35 || $r%4===0)?6:($r%2===0?5:4),
            // O universo piloto não presume patrocínio nem rival; S10/S11 permanecem no catálogo.
            'fim_semana'=>12,
            default=>0
        };
        if($g['stage']==='partida') {
            $m=$g['matches'][self::currentIndex($g)];
            if($m['minute']===80 && $m['hg']===$m['ag']) $id=7;
            elseif($m['minute']===35) $id=8;
            elseif(array_filter($m['log'],fn($l)=>$l['minute']===$m['minute'] && str_contains($l['text'],'Gol adversário'))) $id=9;
        }
        if(!$id) return null; $s=$all[$id];
        return ['id'=>$id,'text'=>$s[1],'options'=>array_map(fn($o,$i)=>['text'=>$o[0],'effects'=>$o[1],'action'=>self::optionType($id,$i)],$s[2],array_keys($s[2]))];
    }
    private static function optionType(int $id,int $i): string {
        return match($id) {7=>['chute','passe','drible','social'][$i],9=>['drible','social','social','desarme'][$i],1=>$i===1?'chute':'social',2=>'passe',default=>'social'};
    }
    public static function choices(array $g): array {
        $s=self::situation($g); if($s) return $s['options'];
        if($g['stage']==='partida') {
            $def=in_array($g['position'],['zagueiro','volante','lateral'],true);
            return [['text'=>$def?'Antecipar e desarmar':'Finalizar no canto','effects'=>[],'action'=>$def?'desarme':'chute'],['text'=>'Passe para o companheiro','effects'=>[],'action'=>'passe'],['text'=>'Avançar no drible','effects'=>[],'action'=>'drible'],['text'=>'Chutar de longe','effects'=>[],'action'=>'chute']];
        }
        return [];
    }
    public static function chance(array $g,array $o): float {
        $type=$o['action']; $d=['social'=>42,'passe'=>43,'chute'=>65,'drible'=>60,'desarme'=>53][$type];
        return Probability::chance(Probability::effective($g['skills'],$type),$g['states']['confianca'],$g['states']['energia'],$g['states']['ritmo_jogo'],$d,$g['stage']==='partida'?45+$g['tier']*3:50);
    }
    public static function command(array $g,string $action,array $input=[]): array {
        $g['feedback']=null;
        if($action==='handicap' && $g['stage']==='handicap') {
            $h=Probability::weighted(Rules::HANDICAPS); $g['handicap']=$h['nome'];
            foreach($h['efeitos'] as $k=>$v) {
                if(isset($g['skills'][$k])) $g['skills'][$k]['value']=self::clamp($g['skills'][$k]['value']+$v); else $g['states'][$k]=self::clamp($g['states'][$k]+$v);
            }
            $g['feedback']=['text'=>'Perfil inicial: '.$h['nome'],'effects'=>$h['efeitos']]; $g['stage']='treino';
            if(!empty($g['starting'])) {
                $g['entry_division']=Onboarding::division($g['uf'],$h['nome']);
                $g['initial_candidates']=Onboarding::candidates($g['origin'],$g['entry_division']);
                $g['stage']='clube_inicio';
            }
        } elseif($action==='join' && $g['stage']==='clube_inicio' && !empty($g['starting'])) {
            $n=filter_var($input['option']??null,FILTER_VALIDATE_INT);
            if($n===false || !isset($g['initial_candidates'][$n]))throw new InvalidArgumentException('Escolha um dos clubes oferecidos.');
            $g=Competition::attach($g,$g['initial_candidates'][$n]['club']);
            $g['starting']=false;$g['stage']='treino';
            $g['feedback']=['text'=>'Você chegou ao '.$g['club'].'. É hora de conquistar seu espaço.'];
        } elseif($action==='choice' && in_array($g['stage'],['treino','pre_jogo','fim_semana','partida'],true)) {
            $choices=self::choices($g); $n=filter_var($input['option']??null,FILTER_VALIDATE_INT);
            if($n===false || !isset($choices[$n])) throw new InvalidArgumentException('Escolha inválida.');
            if($g['stage']==='partida') {
                $m=$g['matches'][self::currentIndex($g)];
                if($m['status']!=='em_andamento' || $m['minute']<$m['entry'] || $m['minute']>=$m['exit']) throw new InvalidArgumentException('Você não está em campo.');
            }
            $o=$choices[$n]; $roll=Probability::roll(self::chance($g,$o)); $effects=self::effects($o['effects'],$roll,$g);
            $s=self::situation($g); if($s && $s['id']===7 && $n===0 && !$roll['success']) { $g['states']['entrosamento']=self::clamp($g['states']['entrosamento']-10); $effects['entrosamento']=-10; }
            $g['feedback']=$roll+['text'=>$o['text'].': '.($roll['success']?'a intenção deu certo.':'a intenção não teve o efeito esperado.'),'effects'=>$effects,'situation'=>$s['id']??0,'option'=>$n,'rules'=>Rules::VERSION];
            if($g['stage']==='treino') $g['stage']='pre_jogo';
            elseif($g['stage']==='pre_jogo') {
                $i=self::currentIndex($g); $m=&$g['matches'][$i];
                $bench=($s['id']===6); $absent=$g['states']['energia']<15 || $g['states']['risco_lesao']>85;
                $m['lineup']=$absent?'não relacionado':($bench?'reserva':'titular');
                $m['entry']=$absent?90:($bench?60:0); $m['exit']=90; $m['status']='pre_jogo'; $g['stage']='partida';
            } elseif($g['stage']==='fim_semana') {
                $g['states']['energia']=self::clamp($g['states']['energia']+20); $g['states']['risco_lesao']=self::clamp($g['states']['risco_lesao']-10); $g['states']['risco_cartao']=self::clamp($g['states']['risco_cartao']-10);
                if(!empty($g['official'])) Competition::afterWeekend($g);
                elseif($g['round']===14) $g['stage']='resumo'; else { $g['round']++; $g['stage']='treino'; }
            } else self::fieldDecision($g,$o,$roll,$s['id']??0,$n);
        } elseif($action==='advance' && $g['stage']==='partida') {
            $i=self::currentIndex($g); $m=&$g['matches'][$i];
            if($m['status']==='pre_jogo') { $m['status']='em_andamento'; self::segment($g,15); }
            elseif($m['status']==='intervalo') { $m['status']='em_andamento'; self::segment($g,60); }
            elseif($m['minute']<$m['entry'] || $m['minute']>=$m['exit']) { self::nextSegment($g); }
            else throw new InvalidArgumentException('Resolva a decisão em campo.');
        } elseif($action==='offers' && $g['stage']==='resumo') {
            $performance=self::summary($g); $pool=[];
            if(!empty($g['official'])) $pool=Competition::offerPool($g);
            else {
            foreach(Rules::TEAMS as $t) if($t!==$g['club']) $pool[]=['club'=>$t,'tier'=>$g['tier'],'peso'=>30];
            if($g['tier']<4 && $performance['average']>=6 && array_sum(array_column($g['skills'],'value'))/9>=45) $pool[]=['club'=>'Academia Regional '.($g['tier']+1),'tier'=>$g['tier']+1,'peso'=>max(5,(int)(($performance['average']-5)*20+$g['states']['apoio_torcida']/5))];
            }
            $chosen=Probability::weighted($pool); $g['offers']=$pool;
            foreach($g['offers'] as &$offer) $offer['selected']=($offer['club']===$chosen['club']); unset($offer);
            $g['stage']='oferta'; $g['feedback']=['text'=>'Proposta recebida: '.$chosen['club'],'weights'=>$pool];
        } elseif($action==='transfer' && $g['stage']==='oferta' && in_array($input['accept']??'', ['yes','no'],true)) {
            $old=$g['club']; $summary=self::summary($g); $summary['season']=$g['season']; $summary['club']=$old; $summary['table']=Schedule::table($g['matches'],$g['teams']);
            $selected=current(array_filter($g['offers'],fn($o)=>$o['selected']));
            if($input['accept']==='yes') { $g['club']=$selected['club']; $g['tier']=$selected['tier']; }
            $summary['next_club']=$g['club']; $g['history'][]=$summary;
            $teams=Rules::TEAMS; if(!in_array($g['club'],$teams,true)) $teams[0]=$g['club'];
            $g['teams']=$teams; $g['season']++; $g['age']++; $g['round']=1; $g['stage']='treino'; $g['matches']=Schedule::generate($teams); $g['offers']=[]; $g['states']['energia']=85;
            if(!empty($g['official']))Competition::newSeason($g);
            $g['feedback']=['text'=>'Contrato renovado para a temporada '.$g['season'].'. Clube: '.$g['club']];
        } elseif($action==='continue' && $g['stage']==='competicao_resumo' && !empty($g['official'])) {
            Competition::continueSeason($g);
        } else throw new InvalidArgumentException('Comando incompatível com a etapa atual.');
        $g['version']++; return $g;
    }
    private static function clamp(float|int $v): int { return (int)max(0,min(100,round($v))); }
    private static function effects(array $effects,array $roll,array &$g): array {
        $applied=[];
        foreach($effects as $k=>$v) {
            $cost=($k==='energia' && $v<0) || (str_starts_with($k,'risco_') && $v>0);
            if(!$cost && !$roll['success']) continue;
            $delta=$cost?$v:(int)round($v*($roll['class']==='excepcional'?1.25:1));
            if(isset($g['skills'][$k])) {
                $delta=max(0,$delta); $g['skills'][$k]['xp']+=$delta;
                while($g['skills'][$k]['value']<100 && $g['skills'][$k]['xp']>=Rules::xpThreshold($g['skills'][$k]['value'])) {
                    $g['skills'][$k]['xp']-=Rules::xpThreshold($g['skills'][$k]['value']); $g['skills'][$k]['value']++;
                }
                $applied['XP '.$k]=$delta;
            } else { $before=$g['states'][$k]; $g['states'][$k]=self::clamp($before+$delta); $applied[$k]=$g['states'][$k]-$before; }
        }
        if(!$roll['success']) { $v=$roll['class']==='falha_grave'?-5:-2; $g['states']['confianca']=self::clamp($g['states']['confianca']+$v); $applied['confianca']=$v; }
        return $applied;
    }
    private static function fieldDecision(array &$g,array $o,array $roll,int $sid,int $choice): void {
        $i=self::currentIndex($g); $m=&$g['matches'][$i]; $st=&$m['stats']; $own=$m['home']===$g['club']?'hg':'ag';
        $type=$o['action']; $onBall=$sid===0 || $sid===7;
        $text=$g['feedback']['text']; $beforeStates=$g['states'];
        if($onBall) {
            if($type==='passe') { $st['passes']++; if($roll['success']) { $st['completed']++; if(random_int(1,100)<=15) { $m[$own]++; $st['assists']++; $g['feedback']['highlight']='assist'; $text.=' O companheiro marca: assistência!'; } } }
            if($type==='chute') { $st['shots']++; if($roll['success'] && random_int(1,100)<=60) { $m[$own]++; $st['goals']++; $g['feedback']['highlight']='goal'; $text.=' Gol do seu jogador!'; } else $text.=' A bola não entrou.'; }
            if($type==='desarme' && $roll['success']) $st['tackles']++;
            if($type==='drible' && $roll['success']) foreach(self::effects(['drible'=>2],$roll,$g) as $k=>$v) $g['feedback']['effects'][$k]=($g['feedback']['effects'][$k]??0)+$v;
            // Simulação e agressão podem gerar falta/cartão; nunca dão gol automático.
            if($sid===7 && $choice===3) { $st['fouls']++; $st['cards']++; $text.=' O árbitro pune a simulação com cartão.'; }
        }
        if($sid===8 && in_array($choice,[0,2],true)) { $st['fouls']++; if(random_int(1,100)<=65) { $st['cards']++; $text.=' Cartão pela reação.'; } }
        if(!$roll['success'] && $roll['class']==='falha_grave' && $onBall) { $st['fouls']++; if(random_int(1,100)<=30) $st['cards']++; }
        $g['states']['energia']=self::clamp($g['states']['energia']-3);
        if($st['cards']>=2) { $m['exit']=$m['minute']; $text.=' Expulso: segundo cartão.'; }
        elseif(random_int(1,1000)<=$g['states']['risco_lesao']) { $m['exit']=$m['minute']; $text.=' Lesão: você deixa o campo.'; }
        elseif($g['states']['energia']<15) { $m['exit']=$m['minute']; $text.=' Substituído por desgaste.'; }
        $g['feedback']['text']=$text; foreach($beforeStates as $k=>$v) if($g['states'][$k]!==$v) $g['feedback']['effects'][$k]=($g['feedback']['effects'][$k]??0)+$g['states'][$k]-$v; $m['log'][]=['minute'=>$m['minute'],'text'=>$text]; self::nextSegment($g);
    }
    private static function nextSegment(array &$g): void {
        $m=$g['matches'][self::currentIndex($g)]; $next=match($m['minute']) {15=>35,35=>45,60=>70,70=>80,80=>90,default=>90};
        self::segment($g,$next);
    }
    private static function segment(array &$g,int $next): void {
        $i=self::currentIndex($g); $m=&$g['matches'][$i]; $span=$next-$m['minute'];
        $own=$m['home']===$g['club']?'hg':'ag';
        foreach(['hg','ag'] as $side) {
            $chance=(int)round($span*1.2+($side==='hg'?3:0)+($side===$own?($g['tier']-1)*2:0));
            if(random_int(1,100)<=$chance) { $m[$side]++; $m['log'][]=['minute'=>$next,'text'=>$side===$own?'Gol da equipe em jogada coletiva.':'Gol adversário em contra-ataque.']; }
        }
        $m['minute']=$next; $m['log'][]=['minute'=>$next,'text'=>'Placar: '.$m['hg'].' × '.$m['ag'].'.'];
        if($next===45) $m['status']='intervalo';
        if($next===90) {
            $m['status']='finalizada'; $m['stats']['minutes']=max(0,$m['exit']-$m['entry']);
            $s=&$m['stats']; $def=in_array($g['position'],['zagueiro','volante','lateral'],true);
            $s['rating']=$s['minutes']===0?0:round(max(0,min(10,6+$s['goals']*.7+$s['assists']*.5+$s['tackles']*($def?.35:.15)+$s['completed']*.1-($s['passes']-$s['completed'])*.15-$s['cards']*.6)),1);
            unset($m,$s);
            if(!empty($g['official'])) {
                $ownIndex=self::currentIndex($g);$played=&$g['matches'][$ownIndex];
                foreach(['home','away'] as $side) {$played[$side.'_yellow']=random_int(0,4);$played[$side.'_red']=0;}
                $side=$played['home']===$g['club']?'home':'away';$played[$side.'_yellow']+=$played['stats']['cards'];$played[$side.'_red']+=(int)($played['stats']['cards']>=2);unset($played);
            }
            foreach($g['matches'] as &$other) if($other['round']===$g['round'] && $other['status']!=='finalizada') Competition::simulate($other); unset($other);
            $g['stage']='fim_semana'; $g['states']['ritmo_jogo']=self::clamp($g['states']['ritmo_jogo']+3);
        }
    }
    public static function summary(array $g): array {
        $out=['goals'=>0,'assists'=>0,'minutes'=>0,'games'=>0,'average'=>0]; $sum=0;
        foreach(Competition::playerMatches($g) as $m) if(in_array($g['club'],[$m['home'],$m['away']],true) && $m['status']==='finalizada') {
            foreach(['goals','assists','minutes'] as $k) $out[$k]+=$m['stats'][$k]; if($m['stats']['minutes']>0) { $out['games']++; $sum+=$m['stats']['rating']; }
        }
        $out['average']=$out['games']?round($sum/$out['games'],2):0; return $out;
    }
}
