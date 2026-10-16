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
        if($chance=MatchMomentum::situation($g))return $chance;
        if($scene=Scenes::current($g))return $scene;
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
        $failures=require dirname(__DIR__).'/database/scene-failures.php';
        return ['id'=>$id,'text'=>$s[1],'options'=>array_map(fn($o,$i)=>SceneConsequences::option(['text'=>$o[0],'effects'=>$o[1],'failure_text'=>$failures[$id][$i],'action'=>self::optionType($id,$i)],$g['stage'],$id,$i),$s[2],array_keys($s[2]))];
    }
    private static function optionType(int $id,int $i): string {
        return match($id) {7=>['chute','passe','drible','social'][$i],9=>['drible','social','social','desarme'][$i],1=>$i===1?'chute':'social',2=>'passe',default=>'social'};
    }
    public static function choices(array $g): array {
        $s=self::situation($g); if($s) return $g['stage']==='partida'?ActionResolution::options($s['options'],$s['id']):$s['options'];
        if($g['stage']==='partida') {
            $def=in_array($g['position'],['zagueiro','volante','lateral'],true);
            return ActionResolution::options([['text'=>$def?'Antecipar e desarmar':'Finalizar no canto','effects'=>[],'action'=>$def?'desarme':'chute'],['text'=>'Passar para o companheiro','effects'=>[],'action'=>'passe'],['text'=>'Avançar no drible','effects'=>[],'action'=>'drible'],['text'=>'Chutar de longe','effects'=>[],'action'=>'chute']],0);
        }
        return [];
    }
    public static function chance(array $g,array $o): float {
        return ActionResolution::trace($g,$o)['chance'];
    }
    public static function command(array $g,string $action,array $input=[],bool $automated=false,?callable $rng=null): array {
        $previousStage=$g['stage'];
        if($g['stage']==='aposentado')throw new InvalidArgumentException('Esta carreira está encerrada. Você pode consultar o histórico ou criar outro jogador.');
        if($g['age']>=40 || ($g['age']===39 && in_array($g['stage'],['resumo','oferta'],true))){$g['age']=max(40,$g['age']);Retirement::close($g,'mandatory');$g['version']++;return $g;}
        if($action==='retire') {
            if(!Retirement::eligible($g) || ($input['confirm']??'')!=='retire')throw new InvalidArgumentException('A aposentadoria não está disponível para esta carreira ou falta a confirmação.');
            Retirement::close($g,'voluntary');$g['version']++;return $g;
        }
        if($action==='mode')return PlayMode::change($g,(string)($input['mode']??''));
        if($action==='relocate'){
            if($g['stage']==='partida' || !empty($g['starting']))throw new InvalidArgumentException('Atualize a residência depois da partida e do primeiro contrato.');
            if(($g['location_kind']??'legacy')==='legacy')$g['legacy_location_record']??=['city'=>$g['city'],'uf'=>$g['uf'],'kind'=>'informada_no_cadastro_anterior'];
            $g=CurrentLocation::apply($g,(string)($input['residence_country']??'BR'),trim((string)($input['city']??'')),(string)(($input['residence_country']??'BR')==='BR'?($input['uf']??''):($input['residence_region']??'')),$g['entry_uf']??'SP');
            $g['version']++;$g['feedback']=['text'=>'Sua residência atual foi confirmada. A carreira e os resultados foram preservados.'];return $g;
        }
        if($action==='simulate')return PlayMode::simulate($g,(string)($input['plan']??''));
        if(!$automated && PlayMode::name($g)!=='normal' && in_array($action,['choice','advance'],true))throw new InvalidArgumentException('Escolha a estratégia do seu modo de jogo.');
        $g['feedback']=null;
        if($action==='handicap' && $g['stage']==='handicap') {
            $h=Probability::weighted(Rules::HANDICAPS); $g['handicap']=$h['nome'];
            $initialEffects=InitialProfile::effects($g,$h['efeitos']);
            foreach($h['efeitos'] as $k=>$v) {
                if(isset($g['skills'][$k])) $g['skills'][$k]['value']=self::clamp($g['skills'][$k]['value']+$v); else $g['states'][$k]=self::clamp($g['states'][$k]+$v);
            }
            $g['initial_profile']=['name'=>$h['nome'],'effects'=>$initialEffects];
            $g['feedback']=['text'=>'Perfil inicial: '.$h['nome'],'effects'=>array_map(fn($v)=>$v['delta'],$initialEffects),'attribute_changes'=>$initialEffects]; $g['stage']='treino';
            $g['season_skill_baseline']=array_sum(array_column($g['skills'],'value'))/9;
            if(!empty($g['starting'])) {
                $country=$g['starting_country']??'BR';$g['entry_division']=Onboarding::division($g['entry_uf']??$g['uf'],$h['nome'],$country);
                $g['initial_candidates']=$country==='BR'?Onboarding::candidates($g['origin'],$g['entry_division']):EntryCountries::candidates($country,$g['entry_division'],$g['entry_seed']??$g['name']);
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
            $o=$choices[$n];$probabilityTrace=ActionResolution::trace($g,$o);$roll=Probability::roll($probabilityTrace['chance'],$rng); $effects=self::effects($o['effects'],$roll,$g,$o);
            $s=self::situation($g);
            Scenes::remember($g,$s);
            $outcome=isset($o['success_text'],$o['failure_text'])?NarrativeCatalog::outcome($o,$roll):($roll['success']?'a intenção deu certo.':'a intenção não teve o efeito esperado.');
            $g['feedback']=$roll+['text'=>$o['text'].': '.$outcome,'effects'=>$effects,'situation'=>$s['id']??0,'option'=>$n,'rules'=>Rules::VERSION,'probability_trace'=>$probabilityTrace];
            if(($g['active_situation']['id']??null)===($s['id']??0) && isset($g['active_situation']['selection']))$g['feedback']['narrative_selection']=$g['active_situation']['selection'];
            elseif(in_array($s['id']??0,[1300,1301],true))$g['feedback']['narrative_selection']=['override'=>'momentum','situation'=>$s['id']];
            if($g['stage']==='treino') $g['stage']='pre_jogo';
            elseif($g['stage']==='pre_jogo') {
                $i=self::currentIndex($g); $m=&$g['matches'][$i];
                TeamStrength::snapshot($m,$g);
                $bench=($s['bench']??($s['id']===6)); $absent=$g['states']['energia']<15 || $g['states']['risco_lesao']>85;
                $m['lineup']=$absent?'não relacionado':($bench?'reserva':'titular');
                $m['entry']=$absent?90:($bench?60:0); $m['exit']=90; $m['status']='pre_jogo'; $g['stage']='partida';
                MatchBalance::prepare($m,$g);$g['progression_version']=2;
                MatchExperience::begin($g);
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
            CompetitionContinuity::reconcileAmazonas($g);
            $g['market_draw']=TransferInterest::draw($g);$g['offers']=$g['market_draw']['offers'];
            $g['stage']='oferta';$g['feedback']=['text'=>count($g['offers']).' proposta(s) nesta janela. Escolha um clube ou permaneça.'];
        } elseif($action==='transfer' && $g['stage']==='oferta' && in_array($input['accept']??'', ['yes','no'],true)) {
            unset($g['pace_report']);
            $old=$g['club']; $summary=self::summary($g); $summary['season']=$g['season']; $summary['club']=$old; $summary['table']=Schedule::table($g['matches'],$g['teams']);
            $summary['evaluation']=SeasonEvaluation::evaluate($g);
            $summary['technical_average']=array_sum(array_column($g['skills'],'value'))/9;
            if(!empty($g['official']))$summary['competitions']=$g['competition_results'];
            $selected=current(array_filter($g['offers'],fn($o)=>$o['selected']));
            if(($g['market_draw']['schema']??1)===2){
                $selected=null;
                if($input['accept']==='yes'){
                    $index=filter_var($input['option']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>2]]);
                    if($index===false||$index===null||!isset($g['offers'][$index]))throw new InvalidArgumentException('Escolha uma proposta válida.');
                    $selected=$g['offers'][$index];
                }
                $summary['market']=$g['market_draw'];$summary['market']['choice']=$selected['club']??$old;
            }
            if($input['accept']==='yes') { if(!$selected)throw new InvalidArgumentException('Não há proposta para aceitar nesta janela.');$g['club']=$selected['club']; $g['tier']=$selected['tier']; }
            $summary['next_club']=$g['club']; $g['history'][]=$summary;
            $teams=Rules::TEAMS; if(!in_array($g['club'],$teams,true)) $teams[0]=$g['club'];
            $g['teams']=$teams; $g['season']++; $g['age']++; $g['round']=1; $g['stage']='treino'; $g['matches']=Schedule::generate($teams); $g['offers']=[]; $g['states']['energia']=85;
            if(!empty($g['official']))Competition::newSeason($g);
            $ageEffects=Evolution::birthday($g);
            $g['season_skill_baseline']=array_sum(array_column($g['skills'],'value'))/9;
            $g['feedback']=['text'=>'Contrato renovado para a temporada '.$g['season'].'. Clube: '.$g['club'],'effects'=>$ageEffects];
        } elseif($action==='continue' && $g['stage']==='competicao_resumo' && !empty($g['official'])) {
            Competition::continueSeason($g);
        } else throw new InvalidArgumentException('Comando incompatível com a etapa atual.');
        if($g['stage']==='resumo')Progression::season($g);
        AchievementEvents::capture($g,$previousStage);
        if($g['age']>=40 || ($g['age']===39 && $g['stage']==='resumo')){$g['age']=max(40,$g['age']);Retirement::close($g,'mandatory');}
        $g['version']++;$selectionStart=RequestMetrics::start();Scenes::prepare($g);RequestMetrics::end('scene_selection',$selectionStart); return $g;
    }
    private static function clamp(float|int $v): int { return (int)max(0,min(100,round($v))); }
    private static function effects(array $effects,array $roll,array &$g,array $choice=[]): array {
        $applied=[];
        foreach($effects as $k=>$v) {
            $cost=($v<0 && !str_starts_with($k,'risco_')) || (str_starts_with($k,'risco_') && $v>0);
            if(!$cost && !$roll['success']) continue;
            $delta=$cost?$v:(int)round($v*($roll['class']==='excepcional'?1.25:1));
            if(isset($g['skills'][$k])) {
                if($delta<0){$before=$g['skills'][$k]['value'];$g['skills'][$k]['value']=self::clamp($before+$delta);$applied[$k]=$g['skills'][$k]['value']-$before;}
                else $applied['XP '.$k]=Evolution::gain($g,$k,$delta);
            } else { $before=$g['states'][$k]; $g['states'][$k]=self::clamp($before+$delta); $applied[$k]=$g['states'][$k]-$before; }
        }
        if(!$roll['success']) {
            $failure=$choice['failure_effects']??['confianca'=>$roll['class']==='falha_grave'?-5:-2];
            if($roll['class']==='falha_grave')$failure=$choice['severe_failure_effects']??$failure;
            foreach($failure as $key=>$value){$before=$g['states'][$key];$g['states'][$key]=self::clamp($before+$value);$applied[$key]=($applied[$key]??0)+$g['states'][$key]-$before;}
        }
        return $applied;
    }
    private static function fieldDecision(array &$g,array $o,array $roll,int $sid,int $choice): void {
        $i=self::currentIndex($g); $m=&$g['matches'][$i]; $st=&$m['stats']; $own=$m['home']===$g['club']?'hg':'ag';
        $type=$o['action'];$clearChance=!empty($o['clear_chance']); $onBall=$clearChance || $sid===0 || $sid===7 || ($sid>=1100 && $sid<=1139) || !empty($o['match_event']);
        $text=$g['feedback']['text']; $beforeStates=$g['states'];
        $auditScore=$m[$own];$auditActions=MatchMomentum::builtActions($g);
        if($onBall) {
            $conversion=$m['pass_conversion']??(int)round(max(12,min(32,18+.25*(Probability::effective($g['skills'],'passe')-45))));
            if(!empty($o['end_sequence']))$conversion=0;
            if(($m['balance_version']??0)>=2 && !$clearChance && $type==='passe')$conversion=min($conversion,100*MatchBalance::eventChance($g,100)/max(1,$roll['chance']));
            $scoringProbability=($type==='chute' || ($clearChance && in_array($type,['drible','passe'],true)))?$roll['chance']/100:($type==='passe'?$roll['chance']*$conversion/10000:0);
            MatchBalance::book($m,$scoringProbability);
            if($type==='passe') { $st['passes']++; if($roll['success']) { $st['completed']++;$conversionRoll=$clearChance?null:random_int(1,10000);$g['feedback']['conversion_chance']=$clearChance?100:$conversion;$g['feedback']['conversion_roll']=$conversionRoll; if($clearChance || $conversionRoll<=round($conversion*100)) { $m[$own]++; $st['assists']++; $g['feedback']['highlight']='assist'; $text.=' O companheiro marca: assistência!'; } } }
            if($type==='chute') { $st['shots']++; if($roll['success']) { $m[$own]++; $st['goals']++; $g['feedback']['highlight']='goal'; $text.=' Gol do seu jogador!'; } else $text.=' A bola não entrou.'; }
            if($type==='desarme' && $roll['success']) $st['tackles']++;
            if($sid>=1125 && $sid<=1139 && $roll['success'] && in_array($type,['passe','drible'],true)){
                $st['tackles']++;foreach(self::effects(['desarme'=>1],$roll,$g) as $k=>$v)$g['feedback']['effects'][$k]=($g['feedback']['effects'][$k]??0)+$v;
            }
            if($roll['success'] && in_array($type,['passe','chute','drible','desarme'],true)){
                $skill=['passe'=>'passe','chute'=>'finalizacao','drible'=>'drible','desarme'=>'desarme'][$type];
                foreach(self::effects([$skill=>2],$roll,$g) as $k=>$v)$g['feedback']['effects'][$k]=($g['feedback']['effects'][$k]??0)+$v;
            }
            if($clearChance && $type==='drible' && $roll['success']){$st['shots']++;$st['goals']++;$m[$own]++;$g['feedback']['highlight']='goal';$text.=' Gol do seu jogador!';}
            // Simulação e agressão podem gerar falta/cartão; nunca dão gol automático.
            if($sid===7 && $choice===3 && !$roll['success'] && ($roll['class']==='falha_grave' || random_int(1,100)<=25)) { $st['fouls']++; $st['cards']++; $text.=' O árbitro pune a simulação com cartão.'; }
        }
        if($m[$own]>$auditScore)GoalAudit::score($m,$own,$g['club'],($g['feedback']['highlight']??'')==='goal',['shot_kind'=>$g['feedback']['probability_trace']['kind']??null,'valid_sequence_actions'=>$auditActions]);
        if($sid===8 && in_array($choice,[0,2],true)) { $st['fouls']++; if(random_int(1,100)<=65) { $st['cards']++; $text.=' Cartão pela reação.'; } }
        if(!$roll['success'] && $roll['class']==='falha_grave' && $onBall && $type==='desarme') { $st['fouls']++;$text.=' Você chega atrasado e comete falta.'; if(random_int(1,100)<=30){$st['cards']++;$text.=' Cartão pelo desarme atrasado.';} }
        if(!empty($o['card_risk']) && random_int(1,100)<=min(50,$o['card_risk']+intdiv($g['states']['risco_cartao'],5))){$st['cards']++;$text.=' Cartão pela discussão.';}
        if(isset($g['feedback']['highlight'])){
            $before=$g['states']['confianca'];$g['states']['confianca']=self::clamp($before+($g['feedback']['highlight']==='goal'?4:3));
        }
        $g['states']['energia']=self::clamp($g['states']['energia']-3);
        if($st['cards']>=2) { $m['exit']=$m['minute']; $text.=' Expulso: segundo cartão.'; }
        elseif(random_int(1,1000)<=$g['states']['risco_lesao']) { $m['exit']=$m['minute']; $text.=' Lesão: você deixa o campo.';$before=$g['skills']['resistencia']['value'];$g['skills']['resistencia']['value']=max(0,$before-1);$g['feedback']['effects']['resistencia']=($g['feedback']['effects']['resistencia']??0)+$g['skills']['resistencia']['value']-$before; }
        elseif($g['states']['energia']<15) { $m['exit']=$m['minute']; $text.=' Substituído por desgaste.'; }
        $constructive=$onBall && in_array($type,['passe','drible','desarme'],true) && empty($o['end_sequence']);
        $created=MatchMomentum::record($m,$roll['success'],$constructive,isset($g['feedback']['highlight']));
        if($m['minute']>=$m['exit']){MatchMomentum::reset($m);$created=false;}
        if($created)$text.=!empty($m['clear_chance'])?' A sequência abriu a defesa: você tem uma chance clara de gol!':' A bola continua com você: escolha como seguir este lance.';
        $g['feedback']['text']=$text; foreach($beforeStates as $k=>$v) if($g['states'][$k]!==$v) $g['feedback']['effects'][$k]=($g['feedback']['effects'][$k]??0)+$g['states'][$k]-$v; $m['log'][]=['minute'=>$m['minute'],'text'=>$text];
        if(!$created)self::nextSegment($g);
    }
    private static function nextSegment(array &$g): void {
        $m=$g['matches'][self::currentIndex($g)]; $next=match($m['minute']) {15=>35,35=>45,60=>70,70=>80,80=>90,default=>90};
        self::segment($g,$next);
    }
    private static function segment(array &$g,int $next): void {
        $i=self::currentIndex($g); $m=&$g['matches'][$i]; $span=$next-$m['minute'];
        // Outro segmento representa outra janela de jogo, nunca a mesma posse.
        MatchMomentum::reset($m);
        $own=$m['home']===$g['club']?'hg':'ag';
        TeamStrength::snapshot($m,$g);$means=TeamStrength::expectations($m['home_overall'],$m['away_overall']);
        if(($m['balance_version']??0)<2){$difference=max(-60,min(60,$m['home_overall']-$m['away_overall']));$means=[max(.25,min(3.5,1.35*exp($difference/55))),max(.25,min(3.5,1.10*exp(-$difference/55)))];}
        foreach(['hg','ag'] as $index=>$side) {
            $mean=($m['balance_version']??0)>=2?MatchBalance::collectiveMean($m,$side,$g['club'],$m['minute'],$next):$means[$index]*$span/90;
            $goals=TeamStrength::goals($mean);
            for($goal=0;$goal<$goals;$goal++) { $m[$side]++; $auditMinute=$m['minute'];$m['minute']=$next;GoalAudit::score($m,$side,$g['club']);$m['minute']=$auditMinute; $m['log'][]=['minute'=>$next,'text'=>$side===$own?'Gol da equipe em jogada coletiva.':'Gol adversário em contra-ataque.']; }
        }
        $m['minute']=$next; $m['log'][]=['minute'=>$next,'text'=>'Placar: '.$m['hg'].' × '.$m['ag'].'.'];
        if($next===45) $m['status']='intervalo';
        if($next===90) {
            MatchMomentum::reset($m);
            $m['status']='finalizada'; $m['stats']['minutes']=max(0,$m['exit']-$m['entry']);
            $s=&$m['stats']; $def=in_array($g['position'],['zagueiro','volante','lateral'],true);
            $s['rating']=$s['minutes']===0?0:round(max(0,min(10,6+$s['goals']*.7+$s['assists']*.5+$s['tackles']*($def?.35:.15)+$s['completed']*.1-($s['passes']-$s['completed'])*.15-$s['cards']*.6)),1);
            Progression::match($g,$m);
            MatchExperience::finish($g);
            unset($m,$s);
            if(!empty($g['official'])) {
                $ownIndex=self::currentIndex($g);$played=&$g['matches'][$ownIndex];
                foreach(['home','away'] as $side) {$played[$side.'_yellow']=random_int(0,4);$played[$side.'_red']=0;}
                $side=$played['home']===$g['club']?'home':'away';$played[$side.'_yellow']+=$played['stats']['cards'];$played[$side.'_red']+=(int)($played['stats']['cards']>=2);unset($played);
            }
            foreach($g['matches'] as &$other) if($other['round']===$g['round'] && $other['status']!=='finalizada') Competition::simulate($other,$g); unset($other);
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
