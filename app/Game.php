<?php
declare(strict_types=1);
final class Game {
    public static function create(string $name,string $city,string $uf,string $position,?string $club=null): array {
        $g=self::legacyCreate($name,$city,$uf,$position);
        if (BalanceService::isDevelopmentV3Active()) PlayerDevelopment::initialize($g);
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
        if(($g['stage']??'')==='partida') {
            $m=$g['matches'][self::currentIndex($g)];
            if(!empty($m['active_match_penalty'])) {
                if(($m['active_match_penalty']['status']??'')==='pending_request') return MatchPenalty::triggerRequestSituation($m, $m['active_match_penalty']['victim']??'companheiro');
                if(($m['active_match_penalty']['status']??'')==='taking_kick') return MatchPenalty::triggerKickSituation($m, $m['active_match_penalty']['context_message']??'O árbitro aponta para a marca da cal. A responsabilidade da cobrança é sua.');
            }
        }
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
        if(($g['stage']??'')==='penaltis')return class_exists('PenaltyShootout')?PenaltyShootout::choices($g):[];
        if(($g['stage']??'')==='fim_semana') {
            $m = !empty($g['matches']) ? ($g['matches'][self::currentIndex($g)] ?? null) : null;
            if ($m && class_exists('PenaltyShootout') && PenaltyShootout::requiresShootout($g, $m)) {
                return [];
            }
        }
        $s=self::situation($g); if($s) return $g['stage']==='partida'?ActionResolution::options($s['options'],$s['id']):$s['options'];
        if($g['stage']==='partida') {
            $def=in_array($g['position'],['zagueiro','volante','lateral'],true);
            return ActionResolution::options([['text'=>$def?'Antecipar e desarmar':'Finalizar no canto','effects'=>[],'action'=>$def?'desarme':'chute'],['text'=>'Passar para o companheiro','effects'=>[],'action'=>'passe'],['text'=>'Avançar no drible','effects'=>[],'action'=>'drible'],['text'=>'Chutar de longe','effects'=>[],'action'=>'chute']],0);
        }
        return [];
    }
    public static function chance(array $g,array $o): float {
        if(($g['stage']??'')==='penaltis'&&isset($o['chance']))return (float)$o['chance'];
        return ActionResolution::trace($g,$o)['chance'];
    }
    public static function command(array $g,string $action,array $input=[],bool $automated=false,?callable $rng=null): array {
        foreach ($g['matches'] as &$match) {
            self::reconcileSoundEvents($match, $g);
        }
        unset($match);
        return self::applyCommand($g, $action, $input, $automated, $rng);
    }

    private static function applyCommand(array $g,string $action,array $input,bool $automated,?callable $rng): array {
        $previousStage=$g['stage'];
        if($g['stage']==='aposentado')throw new InvalidArgumentException('Esta carreira está encerrada. Você pode consultar o histórico ou criar outro jogador.');
        if($g['age']>=40 || ($g['age']===39 && in_array($g['stage'],['resumo','oferta'],true))){$g['age']=max(40,$g['age']);if(PlayerDevelopment::enabled($g))Evolution::birthday($g);Retirement::close($g,'mandatory');$g['version']++;return $g;}
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
        } elseif($action==='choice' && in_array($g['stage'],['treino','pre_jogo','fim_semana','partida','penaltis'],true)) {
            if($g['stage']==='penaltis') {
                $n=filter_var($input['option']??null,FILTER_VALIDATE_INT);
                if($n===false)throw new InvalidArgumentException('Escolha de pênalti inválida.');
                PenaltyShootout::decide($g,$n);
                $g['version']++; return $g;
            }
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
                MatchParticipation::prepare($m, $g, $s);
                $m['status']='pre_jogo'; $g['stage']='partida';
                MatchBalance::prepare($m,$g);$g['progression_version']=2;
                MatchExperience::begin($g);
            } elseif($g['stage']==='fim_semana') {
                $g['states']['energia']=self::clamp($g['states']['energia']+20); $g['states']['risco_lesao']=self::clamp($g['states']['risco_lesao']-10); $g['states']['risco_cartao']=self::clamp($g['states']['risco_cartao']-10);
                if(!empty($g['official'])) Competition::afterWeekend($g);
                elseif($g['round']===14) $g['stage']='resumo'; else { $g['round']++; $g['stage']='treino'; }
            } elseif(($s['id']??0)===MatchPenalty::SITUATION_REQUEST) {
                $i=self::currentIndex($g); $m=&$g['matches'][$i];
                if($n===0) {
                    $conf = $g['states']['confianca'] ?? 50;
                    $coach = $g['states']['confianca_treinador'] ?? 50;
                    $lead = $g['states']['lideranca'] ?? 50;
                    $accepted = ($conf >= 55 || $coach >= 55 || $lead >= 55) && (random_int(1,100) <= 75);
                    if ($accepted) {
                        $m['active_match_penalty'] = ['status' => 'taking_kick', 'context_message' => 'O companheiro aceita seu pedido e entrega a bola. A cobrança é sua.'];
                        $g['feedback']['text'] = 'O companheiro autorizou sua cobrança. Prepare-se para bater o pênalti.';
                    } else {
                        $g['feedback']['text'] = 'O cobrador oficial preferiu manter a responsabilidade da cobrança.';
                        MatchPenalty::resolveTeammatePenalty($g, $m, false);
                        self::nextSegment($g);
                    }
                } elseif($n===1) {
                    $g['feedback']['text'] = 'Você respeita a hierarquia da equipe.';
                    MatchPenalty::resolveTeammatePenalty($g, $m, false);
                    self::nextSegment($g);
                } else {
                    $g['feedback']['text'] = 'Você incentiva o companheiro na cobrança.';
                    MatchPenalty::resolveTeammatePenalty($g, $m, true);
                    self::nextSegment($g);
                }
            } elseif(($s['id']??0)===MatchPenalty::SITUATION_KICK) {
                $i=self::currentIndex($g); $m=&$g['matches'][$i]; $st=&$m['stats']; $own=$m['home']===$g['club']?'hg':'ag';
                $st['shots']++;
                $st['penalties_taken'] = ($st['penalties_taken'] ?? 0) + 1;
                unset($m['active_match_penalty']);
                if($roll['success']) {
                    $m[$own]++; $st['goals']++; $st['penalties_scored'] = ($st['penalties_scored'] ?? 0) + 1;
                    $g['feedback']['highlight'] = 'goal';
                    $g['feedback']['sound_event_id'] = self::emitSoundEvent($m, $g, 'GOAL_SCORED', (int)$m['minute']);
                    $g['feedback']['text'] = 'GOL DE PÊNALTI! Você cobra com frieza e balança as redes!';
                    $m['log'][] = ['minute' => $m['minute'], 'text' => "{$m['minute']}' | GOL DE PÊNALTI! Cobrança impecável!"];
                } else {
                    $st['penalties_missed'] = ($st['penalties_missed'] ?? 0) + 1;
                    $missKind = match(random_int(1,3)) { 1 => 'saved', 2 => 'post', default => 'wide' };
                    if ($missKind === 'saved') {
                        $g['feedback']['text'] = 'DEFENDE O GOLEIRO! O arqueiro acerta o canto e espalma o pênalti!';
                        $m['log'][] = ['minute' => $m['minute'], 'text' => "{$m['minute']}' | Pênalti desperdiçado: o goleiro defende sua finalização."];
                    } elseif ($missKind === 'post') {
                        self::emitSoundEvent($m, $g, 'BALL_HIT_POST', (int)$m['minute']);
                        $g['feedback']['text'] = 'NA TRAVE! A bola explode no poste e não entra!';
                        $m['log'][] = ['minute' => $m['minute'], 'text' => "{$m['minute']}' | Pênalti desperdiçado: finalização carimba o poste!"];
                    } else {
                        $g['feedback']['text'] = 'PRA FORA! A bola sai raspando a trave!';
                        $m['log'][] = ['minute' => $m['minute'], 'text' => "{$m['minute']}' | Pênalti desperdiçado: finalização pela linha de fundo."];
                    }
                }
                self::nextSegment($g);
            } else self::fieldDecision($g,$o,$roll,$s['id']??0,$n);
        } elseif($action==='shootout_start') {
            $i=self::currentIndex($g); $m=&$g['matches'][$i];
            if(!class_exists('PenaltyShootout') || !PenaltyShootout::requiresShootout($g,$m)) {
                throw new InvalidArgumentException('Disputa de pênaltis não aplicável nesta partida.');
            }
            if(!isset($g['shootout']) || ($g['shootout']['status'] ?? '') !== 'in_progress') {
                PenaltyShootout::start($g, $m);
            }
            $g['stage'] = 'penaltis';
            unset($m);
            $g['version']++; return $g;
        } elseif($action==='shootout_finish') {
            if($g['stage']==='penaltis') {
                PenaltyShootout::complete($g);
                $g['version']++; return $g;
            }
        } elseif(($action==='advance' || $action==='shootout_advance') && ($g['stage']==='partida' || $g['stage']==='penaltis')) {
            if($g['stage']==='penaltis') {
                if (($g['shootout']['status'] ?? '') === 'completed') {
                    PenaltyShootout::complete($g);
                    $g['version']++; return $g;
                }
                $mode = $input['mode'] ?? 'one';
                if ($mode === 'to_protagonist') {
                    PenaltyShootout::advanceUntilProtagonistOrEnd($g);
                } else {
                    PenaltyShootout::advanceOneKick($g);
                }
                $g['version']++; return $g;
            }
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
        if($g['age']>=40 || ($g['age']===39 && $g['stage']==='resumo')){$g['age']=max(40,$g['age']);if(PlayerDevelopment::enabled($g))Evolution::birthday($g);Retirement::close($g,'mandatory');}
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
            if($type==='passe') {
                $st['passes']++;
                if($roll['success']) {
                    $st['completed']++;
                    $posAssistRate=match($g['position']){'meia'=>1.0,'ponta'=>0.85,'centroavante'=>0.55,'lateral'=>0.40,'volante'=>0.25,'zagueiro'=>0.08,default=>0.50};
                    $effConversion=(int)round($conversion*$posAssistRate);
                    $conversionRoll=$clearChance?null:random_int(1,10000);
                    $g['feedback']['conversion_chance']=$clearChance?100:$effConversion;
                    $g['feedback']['conversion_roll']=$conversionRoll;
                    if($clearChance || $conversionRoll<=round($effConversion*100)) {
                        $m[$own]++;
                        $st['assists']++;
                        $g['feedback']['highlight']='assist';
                        $g['feedback']['sound_event_id'] = self::emitSoundEvent($m, $g, 'ASSIST_RECORDED', (int)$m['minute']);
                        $text.=' O companheiro marca: assistência!';
                    }
                }
            }
            if($type==='chute') {
                $st['shots']++;
                if($roll['success']) {
                    $m[$own]++;
                    $st['goals']++;
                    $g['feedback']['highlight']='goal';
                    $g['feedback']['sound_event_id'] = self::emitSoundEvent($m, $g, 'GOAL_SCORED', (int)$m['minute']);
                    $text.=' Gol do seu jogador!';
                } else $text.=' A bola não entrou.';
            }
            if($type==='desarme' && $roll['success']) $st['tackles']++;
            if($sid>=1125 && $sid<=1139 && $roll['success'] && in_array($type,['passe','drible'],true)){
                $st['tackles']++;foreach(self::effects(['desarme'=>1],$roll,$g) as $k=>$v)$g['feedback']['effects'][$k]=($g['feedback']['effects'][$k]??0)+$v;
            }
            if($roll['success'] && in_array($type,['passe','chute','drible','desarme'],true)){
                $skill=['passe'=>'passe','chute'=>'finalizacao','drible'=>'drible','desarme'=>'desarme'][$type];
                foreach(self::effects([$skill=>2],$roll,$g) as $k=>$v)$g['feedback']['effects'][$k]=($g['feedback']['effects'][$k]??0)+$v;
            }
            if($clearChance && $type==='drible' && $roll['success']){
                $st['shots']++;
                $st['goals']++;
                $m[$own]++;
                $g['feedback']['highlight']='goal';
                $g['feedback']['sound_event_id'] = self::emitSoundEvent($m, $g, 'GOAL_SCORED', (int)$m['minute']);
                $text.=' Gol do seu jogador!';
            }
            // Simulação e agressão podem gerar falta/cartão; nunca dão gol automático.
            if($sid===7 && $choice===3 && !$roll['success'] && ($roll['class']==='falha_grave' || random_int(1,100)<=25)) {
                $st['fouls']++;
                $foulRes = MatchDiscipline::recordFoul($g, $m, 'simulation');
                $text .= ' ' . $foulRes['text'];
            }
        }
        if($m[$own]>$auditScore)GoalAudit::score($m,$own,$g['club'],($g['feedback']['highlight']??'')==='goal',['shot_kind'=>$g['feedback']['probability_trace']['kind']??null,'valid_sequence_actions'=>$auditActions]);
        if($sid===8 && in_array($choice,[0,2],true)) {
            $st['fouls']++;
            if(random_int(1,100)<=65) {
                $foulRes = MatchDiscipline::recordFoul($g, $m, 'dissent');
                $text .= ' ' . $foulRes['text'];
            }
        }
        if(!$roll['success'] && $roll['class']==='falha_grave' && $onBall && $type==='desarme') {
            $st['fouls']++;
            $text.=' Você chega atrasado e comete falta.';
            if(random_int(1,100)<=30){
                $foulRes = MatchDiscipline::recordFoul($g, $m, 'late_tackle');
                $text .= ' ' . $foulRes['text'];
            }
        }
        if(!empty($o['card_risk']) && random_int(1,100)<=min(50,$o['card_risk']+intdiv($g['states']['risco_cartao'],5))){
            $foulRes = MatchDiscipline::recordFoul($g, $m, 'argument');
            $text .= ' ' . $foulRes['text'];
        }
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

        // Check for in-match penalty event during segment (idempotente e avaliado uma única vez por janela lógica)
        $m['evaluated_penalties'] = $m['evaluated_penalties'] ?? [];
        $windowKey = $m['minute'] . '-' . $next;
        if (empty($m['active_match_penalty']) && !in_array($windowKey, $m['evaluated_penalties'], true)) {
            $m['evaluated_penalties'][] = $windowKey;
            $isProtagonistOnField = ($m['entry'] <= $m['minute'] && $m['minute'] < $m['exit']);
            $penaltyRoll = random_int(1, 100);
            if ($penaltyRoll <= 5) {
                // Team penalty awarded!
                self::emitSoundEvent($m, $g, 'WHISTLE_FOUL', (int)$m['minute']);
                $victim = $g['name'];
                $isOfficialTaker = MatchPenalty::isProtagonistOfficialTaker($g, $m);
                if ($isProtagonistOnField && $isOfficialTaker) {
                    $m['active_match_penalty'] = [
                        'status' => 'taking_kick',
                        'victim' => $victim,
                        'window' => $windowKey,
                        'context_message' => 'PÊNALTI A FAVOR! Como cobrador oficial da equipe, você assume a marca da cal.'
                    ];
                    $m['log'][] = ['minute' => $m['minute'], 'text' => "{$m['minute']}' | Pênalti a favor da sua equipe! Você se posiciona na marca da cal."];
                    return;
                } elseif ($isProtagonistOnField) {
                    $m['active_match_penalty'] = [
                        'status' => 'pending_request',
                        'victim' => 'companheiro',
                        'window' => $windowKey
                    ];
                    $m['log'][] = ['minute' => $m['minute'], 'text' => "{$m['minute']}' | Pênalti a favor da sua equipe! O cobrador oficial pega a bola."];
                    return;
                } else {
                    MatchPenalty::resolveTeammatePenalty($g, $m, false);
                }
            } elseif ($penaltyRoll >= 96) {
                // Opponent penalty
                MatchPenalty::resolveOpponentPenalty($g, $m);
            }
        }

        TeamStrength::snapshot($m,$g);$means=TeamStrength::expectations($m['home_overall'],$m['away_overall']);
        if(($m['balance_version']??0)<2){$difference=max(-60,min(60,$m['home_overall']-$m['away_overall']));$means=[max(.25,min(3.5,1.35*exp($difference/55))),max(.25,min(3.5,1.10*exp(-$difference/55)))];}
        foreach(['hg','ag'] as $index=>$side) {
            $mean=($m['balance_version']??0)>=2?MatchBalance::collectiveMean($m,$side,$g['club'],$m['minute'],$next):$means[$index]*$span/90;
            $goals=TeamStrength::goals($mean);
            for($goal=0;$goal<$goals;$goal++) { $m[$side]++; $auditMinute=$m['minute'];$m['minute']=$next;GoalAudit::score($m,$side,$g['club']);$m['minute']=$auditMinute; $m['log'][]=['minute'=>$next,'text'=>$side===$own?'Gol da equipe em jogada coletiva.':'Gol adversário em contra-ataque.']; }
        }
        $m['minute']=$next; MatchParticipation::sync($m, $g, $next); $m['log'][]=['minute'=>$next,'text'=>'Placar: '.$m['hg'].' × '.$m['ag'].'.'];
        if($next===45) $m['status']='intervalo';
        if($next===90) {
            MatchMomentum::reset($m);
            $m['status']='finalizada'; $m['stats']['minutes']=max(0,$m['exit']-$m['entry']);
            $s=&$m['stats']; $def=in_array($g['position'],['zagueiro','volante','lateral'],true);
            $rParams = class_exists('BalanceService') ? BalanceService::params('match.rating') : [];
            $rBase = (float)($rParams['base'] ?? 6.0);
            $wGoal = (float)($rParams['w_goal'] ?? 0.70);
            $wAssist = (float)($rParams['w_assist'] ?? 0.50);
            $wTackle = $def ? (float)($rParams['w_tackle_def'] ?? 0.35) : (float)($rParams['w_tackle_off'] ?? 0.15);
            $wPassOk = (float)($rParams['w_pass_ok'] ?? 0.10);
            $wPassErr = (float)($rParams['w_pass_err'] ?? 0.15);
            $wCard = (float)($rParams['w_card'] ?? 0.60);
            $s['rating']=$s['minutes']===0?0:round(max(0,min(10,$rBase+$s['goals']*$wGoal+$s['assists']*$wAssist+$s['tackles']*$wTackle+$s['completed']*$wPassOk-($s['passes']-$s['completed'])*$wPassErr-$s['cards']*$wCard)),1);
            Progression::match($g,$m);
            MatchExperience::finish($g);
            MatchDiscipline::processMatchEnd($g, $m);
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

    /**
     * Registra evento sonoro autoritativo persistente com ID único imutável
     */
    public static function emitSoundEvent(array &$m, array $g, string $type, int $minute): string {
        $m['sound_events'] = $m['sound_events'] ?? [];
        $m['sound_event_counters'] = $m['sound_event_counters'] ?? [];
        // Reconcilia com eventos existentes que já possuam seq para evitar reuso
        if (!isset($m['sound_event_counters'][$type])) {
            $maxSeq = 0;
            foreach ($m['sound_events'] as $sev) {
                if (($sev['type'] ?? '') === $type && isset($sev['seq'])) {
                    $maxSeq = max($maxSeq, (int)$sev['seq']);
                }
            }
            $m['sound_event_counters'][$type] = $maxSeq;
        }
        $seq = $m['sound_event_counters'][$type] = ($m['sound_event_counters'][$type] ?? 0) + 1;
        $saveId = $g['id'] ?? '0';
        $season = $g['season'] ?? 1;
        $compId = $m['competition'] ?? ($g['competition_id'] ?? 'c');
        $round = $m['round'] ?? ($g['round'] ?? 1);
        $matchPair = ($m['home'] ?? 'h') . '-' . ($m['away'] ?? 'a');
        $eventId = "{$saveId}:s{$season}:{$compId}:m{$round}:{$matchPair}:min{$minute}:{$type}:{$seq}";
        $m['sound_events'][] = [
            'id' => $eventId,
            'type' => $type,
            'minute' => $minute,
            'seq' => $seq
        ];
        return $eventId;
    }

    /**
     * Reconcilia eventos sonoros em uma partida, garantindo IDs persistidos imutáveis para legados
     */
    public static function reconcileSoundEvents(array &$m, array $g): void {
        if (empty($m['sound_events']) || !is_array($m['sound_events'])) return;
        $saveId = $g['id'] ?? '0';
        $season = $g['season'] ?? 1;
        $compId = $m['competition'] ?? ($g['competition_id'] ?? 'c');
        $round = $m['round'] ?? ($g['round'] ?? 1);
        $matchPair = ($m['home'] ?? 'h') . '-' . ($m['away'] ?? 'a');

        $m['sound_event_counters'] = $m['sound_event_counters'] ?? [];
        $usedIds = [];
        $maxSeqPerType = [];
        $seenIds = [];

        // Passo 1: Catalogar todos os IDs válidos únicos e a maior sequência já existente
        foreach ($m['sound_events'] as $idx => $sev) {
            $type = $sev['type'] ?? 'GENERIC';
            if (!empty($sev['id'])) {
                if (isset($seenIds[$sev['id']])) {
                    // ID duplicado
                } else {
                    $seenIds[$sev['id']] = $idx;
                    $usedIds[$sev['id']] = true;
                }
                if (preg_match('/:(\d+)$/', (string)$sev['id'], $matchSeq)) {
                    $maxSeqPerType[$type] = max($maxSeqPerType[$type] ?? 0, (int)$matchSeq[1]);
                }
            }
            if (isset($sev['seq'])) {
                $sVal = (int)$sev['seq'];
                $maxSeqPerType[$type] = max($maxSeqPerType[$type] ?? 0, $sVal);
            }
        }
        foreach ($maxSeqPerType as $t => $ms) {
            $m['sound_event_counters'][$t] = max($m['sound_event_counters'][$t] ?? 0, $ms);
        }

        // Passo 2: Atribuir IDs únicos e imutáveis
        $assignedIds = [];
        $usedSeqsInMatch = [];
        foreach ($m['sound_events'] as $idx => &$sev) {
            $type = $sev['type'] ?? 'GENERIC';
            $minute = (int)($sev['minute'] ?? 0);

            if (!empty($sev['id']) && !isset($assignedIds[$sev['id']])) {
                $assignedIds[$sev['id']] = true;
                if (isset($sev['seq'])) {
                    $usedSeqsInMatch[$type][(int)$sev['seq']] = true;
                }
                continue;
            }

            // Para quem não tem ID:
            // Se já tem um seq explícito e esse seq ainda não colidiu nesta partida, tenta preservá-lo
            $seq = null;
            if (isset($sev['seq']) && empty($usedSeqsInMatch[$type][(int)$sev['seq']])) {
                $candidateId = "{$saveId}:s{$season}:{$compId}:m{$round}:{$matchPair}:min{$minute}:{$type}:" . (int)$sev['seq'];
                if (!isset($usedIds[$candidateId])) {
                    $seq = (int)$sev['seq'];
                }
            }

            if ($seq === null) {
                do {
                    $seq = ($m['sound_event_counters'][$type] ?? 0) + 1;
                    $m['sound_event_counters'][$type] = $seq;
                    $candidateId = "{$saveId}:s{$season}:{$compId}:m{$round}:{$matchPair}:min{$minute}:{$type}:{$seq}";
                } while (isset($usedIds[$candidateId]) || !empty($usedSeqsInMatch[$type][$seq]));
            } else {
                $candidateId = "{$saveId}:s{$season}:{$compId}:m{$round}:{$matchPair}:min{$minute}:{$type}:{$seq}";
            }

            $sev['seq'] = $seq;
            $sev['id'] = $candidateId;
            $usedIds[$candidateId] = true;
            $assignedIds[$candidateId] = true;
            $usedSeqsInMatch[$type][$seq] = true;
        }
        unset($sev);
    }
}
