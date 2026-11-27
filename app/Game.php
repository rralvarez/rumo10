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
            if(!MatchParticipation::canActOnField($m)) return null;
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
        if(($g['stage']??'')==='partida' && !MatchParticipation::canActOnField($g['matches'][self::currentIndex($g)])) return [];
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
        $g['current_command_sound_event_ids'] = [];
        return self::applyCommand($g, $action, $input, $automated, $rng);
    }

    private static function applyCommand(array $g,string $action,array $input,bool $automated,?callable $rng): array {
        $previousStage=$g['stage'];
        if($g['stage']==='aposentado')throw new InvalidArgumentException('Esta carreira está encerrada. Você pode consultar o histórico ou criar outro jogador.');
        if($action==='retire') {
            if(!Retirement::eligible($g) || ($input['confirm']??'')!=='retire')throw new InvalidArgumentException('A aposentadoria não está disponível para esta carreira ou falta a confirmação.');
            if(Retirement::mandatory($g)){
                $g['age']=max(40,$g['age']);
                if(PlayerDevelopment::enabled($g))Evolution::birthday($g);
                Retirement::close($g,'mandatory');
            } else {
                Retirement::close($g,'voluntary');
            }
            $g['version']++;return $g;
        }
        if($g['age']>=40)throw new InvalidArgumentException('Limite de idade atingido. Conclua a aposentadoria obrigatória para encerrar a carreira.');
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
                if(!MatchParticipation::canActOnField($m)) throw new InvalidArgumentException('Você não está em campo.');
            }
            $s=self::situation($g);
            if($g['stage']==='partida' && in_array($s['id']??0,[MatchPenalty::SITUATION_REQUEST,MatchPenalty::SITUATION_KICK],true)) {
                $i=self::currentIndex($g); $m=&$g['matches'][$i];
                if(MatchPenalty::isTeamOccurrenceResolved($m)) {
                    unset($m['active_match_penalty']);
                    $g['feedback']=['text'=>'Ocorrência de pênalti já resolvida anteriormente; estado preservado.','effects'=>[],'situation'=>$s['id']??0,'option'=>$n,'rules'=>Rules::VERSION];
                    $g['version']++;
                    return $g;
                }
            }
            $o=$choices[$n];$probabilityTrace=ActionResolution::trace($g,$o);$roll=Probability::roll($probabilityTrace['chance'],$rng); $effects=self::effects($o['effects'],$roll,$g,$o);
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
                $i=self::currentIndex($g); $m=&$g['matches'][$i]; $m['participated_on_field']=true; $m['entered_on_field']=true;
                $resumeWindow = (string)($m['active_match_penalty']['window'] ?? '');
                if($n===0) {
                    $conf = $g['states']['confianca'] ?? 50;
                    $coach = $g['states']['confianca_treinador'] ?? 50;
                    $lead = $g['states']['lideranca'] ?? 50;
                    $accepted = ($conf >= 55 || $coach >= 55 || $lead >= 55) && (random_int(1,100) <= 75);
                    if ($accepted) {
                        $m['active_match_penalty'] = ['status' => 'taking_kick', 'window' => $resumeWindow !== '' ? $resumeWindow : null, 'context_message' => 'O companheiro aceita seu pedido e entrega a bola. A cobrança é sua.'];
                        $g['feedback']['text'] = 'O companheiro autorizou sua cobrança. Prepare-se para bater o pênalti.';
                    } else {
                        $g['feedback']['text'] = 'O cobrador oficial preferiu manter a responsabilidade da cobrança.';
                        MatchPenalty::resolveTeammatePenalty($g, $m, false);
                        self::nextSegment($g, $resumeWindow);
                    }
                } elseif($n===1) {
                    $g['feedback']['text'] = 'Você respeita a hierarquia da equipe.';
                    MatchPenalty::resolveTeammatePenalty($g, $m, false);
                    self::nextSegment($g, $resumeWindow);
                } else {
                    $g['feedback']['text'] = 'Você incentiva o companheiro na cobrança.';
                    MatchPenalty::resolveTeammatePenalty($g, $m, true);
                    self::nextSegment($g, $resumeWindow);
                }
            } elseif(($s['id']??0)===MatchPenalty::SITUATION_KICK) {
                $i=self::currentIndex($g); $m=&$g['matches'][$i];
                $m['resolved_penalties'] = $m['resolved_penalties'] ?? [];
                $resumeWindow = (string)($m['active_match_penalty']['window'] ?? '');
                $occId = MatchPenalty::occurrenceId($m);
                $alreadyResolved = MatchPenalty::isTeamOccurrenceResolved($m, $occId);
                unset($m['active_match_penalty']);
                if (!$alreadyResolved) {
                    $m['participated_on_field']=true; $m['entered_on_field']=true; $st=&$m['stats']; $own=$m['home']===$g['club']?'hg':'ag';
                    $m['resolved_penalties'][] = $occId;
                    $st['shots']++;
                    $st['penalties_taken'] = ($st['penalties_taken'] ?? 0) + 1;
                    if($roll['success']) {
                        $m[$own]++; $st['goals']++; $st['penalties_scored'] = ($st['penalties_scored'] ?? 0) + 1;
                        $g['feedback']['highlight'] = 'goal';
                        $beforeConf = $g['states']['confianca'];
                        $g['states']['confianca'] = self::clamp($beforeConf + self::goalConfidenceDelta($g, (int)$st['goals']));
                        $g['feedback']['effects']['confianca'] = ($g['feedback']['effects']['confianca'] ?? 0) + ($g['states']['confianca'] - $beforeConf);
                        foreach (self::effects(['finalizacao' => 2], $roll, $g) as $k => $v) {
                            $g['feedback']['effects'][$k] = ($g['feedback']['effects'][$k] ?? 0) + $v;
                        }
                        $g['feedback']['sound_event_id'] = self::emitSoundEvent($m, $g, 'GOAL_SCORED', (int)$m['minute']);
                        $g['feedback']['text'] = 'GOL DE PÊNALTI! Você cobra com frieza e balança as redes!';
                        $m['log'][] = ['minute' => $m['minute'], 'text' => "{$m['minute']}' | GOL DE PÊNALTI! Cobrança impecável!"];
                    } else {
                        $st['penalties_missed'] = ($st['penalties_missed'] ?? 0) + 1;
                        $g['feedback']['sound_event'] = 'NO_GOAL';
                        $g['feedback']['sound_event_id'] = self::emitSoundEvent($m, $g, 'NO_GOAL', (int)$m['minute']);
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
                }
                $confBeforeSeg = $g['states']['confianca'];
                self::nextSegment($g, $resumeWindow);
                if ($g['states']['confianca'] !== $confBeforeSeg) {
                    $g['feedback']['effects']['confianca'] = ($g['feedback']['effects']['confianca'] ?? 0) + ($g['states']['confianca'] - $confBeforeSeg);
                }
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
            $confBeforeAdv=$g['states']['confianca'];
            if($m['status']==='pre_jogo') { $m['status']='em_andamento'; self::segment($g,15); }
            elseif($m['status']==='intervalo') { $m['status']='em_andamento'; self::segment($g,60); }
            elseif(!MatchParticipation::canActOnField($m)) { self::nextSegment($g); }
            else throw new InvalidArgumentException('Resolva a decisão em campo.');
            unset($m);
            if($g['stage']==='fim_semana' && $g['states']['confianca']!==$confBeforeAdv) {
                $deltaAdv=$g['states']['confianca']-$confBeforeAdv;
                $g['feedback']=['text'=>$deltaAdv>0?'Fim de jogo: sua atuação fortaleceu sua confiança.':'Fim de jogo: a atuação abaixo do esperado pesou na sua confiança.','effects'=>['confianca'=>$deltaAdv]];
            }
        } elseif($action==='offers' && $g['stage']==='resumo') {
            if($g['age']>=39)throw new InvalidArgumentException('Limite de idade atingido. Conclua a aposentadoria obrigatória com confirmação explícita.');
            CompetitionContinuity::reconcileAmazonas($g);
            $g['market_draw']=TransferInterest::draw($g);$g['offers']=$g['market_draw']['offers'];
            $g['stage']='oferta';$g['feedback']=['text'=>count($g['offers']).' proposta(s) nesta janela. Escolha um clube ou permaneça.'];
        } elseif($action==='transfer' && $g['stage']==='oferta' && in_array($input['accept']??'', ['yes','no'],true)) {
            $isSchema2=(($g['market_draw']['schema']??1)>=2);
            $selected=$isSchema2?null:current(array_filter($g['offers'],fn($o)=>!empty($o['selected'])));
            if($input['accept']==='yes'){
                if($isSchema2){
                    $maxIdx=max(0,count($g['offers'])-1);
                    $index=filter_var($input['option']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>$maxIdx]]);
                    if($index===false||$index===null||!isset($g['offers'][$index]))throw new InvalidArgumentException('Escolha uma proposta válida.');
                    $selected=$g['offers'][$index];
                } elseif(isset($input['option']) && $input['option']!=='') {
                    $index=filter_var($input['option'],FILTER_VALIDATE_INT);
                    if($index===false||!isset($g['offers'][$index]))throw new InvalidArgumentException('Escolha uma proposta válida.');
                    $selected=$g['offers'][$index];
                }
                if(!$selected)throw new InvalidArgumentException('Não há proposta para aceitar nesta janela.');
            }
            if($g['age']>=39)throw new InvalidArgumentException('Limite de idade atingido. Conclua a aposentadoria obrigatória com confirmação explícita.');
            unset($g['pace_report']);
            $old=$g['club']; $oldSeason=(int)$g['season']; $summary=self::summary($g); $summary['season']=$oldSeason; $summary['club']=$old; $summary['table']=Schedule::table($g['matches'],$g['teams']);
            $summary['evaluation']=SeasonEvaluation::evaluate($g);
            $summary['technical_average']=array_sum(array_column($g['skills'],'value'))/9;
            if(!empty($g['official']))$summary['competitions']=$g['competition_results'];
            if($isSchema2){
                $summary['market']=$g['market_draw'];$summary['market']['choice']=$selected['club']??$old;
            }
            if($input['accept']==='yes') { $g['club']=$selected['club']; $g['tier']=$selected['tier']; }
            $summary['next_club']=$g['club']; $g['history'][]=$summary;
            $teams=Rules::TEAMS; if(!in_array($g['club'],$teams,true)) $teams[0]=$g['club'];
            $g['teams']=$teams; $g['season']++; $g['age']++; $g['round']=1; $g['stage']='treino'; $g['matches']=Schedule::generate($teams); $g['offers']=[]; $g['states']['energia']=85;
            unset($g['confidence_streak']);
            if(!empty($g['official']))Competition::newSeason($g);
            $ageEffects=Evolution::birthday($g);
            $offseasonConf=self::applyOffseasonConfidenceTransition($g,$oldSeason,(string)($summary['evaluation']['band']??'regular'));
            if($offseasonConf!==0)$ageEffects['confianca']=($ageEffects['confianca']??0)+$offseasonConf;
            $g['season_skill_baseline']=array_sum(array_column($g['skills'],'value'))/9;
            $g['feedback']=['text'=>'Contrato renovado para a temporada '.$g['season'].'. Clube: '.$g['club'],'effects'=>$ageEffects];
        } elseif($action==='continue' && $g['stage']==='competicao_resumo' && !empty($g['official'])) {
            Competition::continueSeason($g);
        } else throw new InvalidArgumentException('Comando incompatível com a etapa atual.');
        if($g['stage']==='resumo'){Progression::season($g);self::applySeasonAchievementConfidence($g);}
        AchievementEvents::capture($g,$previousStage);
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
        if($roll['success'] && ($roll['class']??'')==='excepcional' && ($g['stage']??'')==='treino' && !isset($effects['confianca'])) {
            $trainCode=$choice['code']??($choice['action']??'');
            $trainCap=class_exists('BalanceService')?(int)BalanceService::param('confidence.balance.v2','exceptional_training_cap',42):42;
            $trainBonus=class_exists('BalanceService')?(int)BalanceService::param('confidence.balance.v2','exceptional_training_bonus',1):1;
            if(in_array($trainCode,['passe','chute','drible','desarme','tecnica','tatico','posicionamento','visao'],true) && $g['states']['confianca']<$trainCap && $trainBonus>0){
                $before=$g['states']['confianca'];
                $g['states']['confianca']=self::clamp($before+$trainBonus);
                $applied['confianca']=($applied['confianca']??0)+($g['states']['confianca']-$before);
            }
        }
        if(!$roll['success']) {
            $fp=SceneConsequences::failureParams();
            $techNormal=$fp['tech_fail_normal'];
            $techSevere=$fp['tech_fail_severe'];
            $decNormal=$fp['decisive_fail_normal'];
            $decSevere=$fp['decisive_fail_severe'];
            $isDecisive=!empty($choice['decisive_opportunity']) || !empty($choice['clear_chance']);
            $defaultFail=$isDecisive
                ?['confianca'=>$roll['class']==='falha_grave'?$decSevere:$decNormal]
                :['confianca'=>$roll['class']==='falha_grave'?$techSevere:$techNormal];
            $failure=array_key_exists('failure_effects',$choice) && is_array($choice['failure_effects'])?$choice['failure_effects']:$defaultFail;
            if($roll['class']==='falha_grave'){
                if(array_key_exists('severe_failure_effects',$choice) && is_array($choice['severe_failure_effects'])){
                    $failure=$choice['severe_failure_effects'];
                } elseif(!array_key_exists('failure_effects',$choice)){
                    $failure=$defaultFail;
                }
            }
            if(($g['stage']??'')==='partida' && !$isDecisive){
                if(($choice['shot_kind']??'')==='long'){
                    if($roll['class']==='falha_grave'){
                        $longSevere=min($techNormal,max($techSevere,-2));
                        if(!array_key_exists('confianca',$failure) || $failure['confianca']===$techSevere || $failure['confianca']===-4 || $failure['confianca']===-3){
                            $failure['confianca']=$longSevere;
                        }
                    } else {
                        if(($failure['confianca']??null)===$techNormal || ($failure['confianca']??null)===-2 || ($failure['confianca']??null)===-1){
                            unset($failure['confianca']);
                        }
                        $failure['foco']=min((int)($failure['foco']??0),-1);
                    }
                } elseif(in_array($choice['action']??'',['passe','chute','drible','desarme'],true)){
                    if(($failure['confianca']??null)===-2 || ($failure['confianca']??null)===-1)$failure['confianca']=$techNormal;
                    elseif(($failure['confianca']??null)===-4 || ($failure['confianca']??null)===-3)$failure['confianca']=$techSevere;
                }
            }
            if(isset($failure['confianca']) && $failure['confianca']<0 && !isset($effects['confianca'])){
                $curConf=$g['states']['confianca'];
                $floorNormal=class_exists('BalanceService')?(int)BalanceService::param('confidence.balance.v2','zero_trap_floor_normal',10):10;
                $floorSevere=class_exists('BalanceService')?(int)BalanceService::param('confidence.balance.v2','zero_trap_floor_severe',8):8;
                if($roll['class']!=='falha_grave' && !$isDecisive && $failure['confianca']>=$techNormal && $curConf<=$floorNormal){
                    unset($failure['confianca']);
                } elseif($curConf<=$floorSevere && $failure['confianca']<=-2){
                    $failure['confianca']=max(-2,(int)floor($failure['confianca']/2));
                }
            }
            foreach($failure as $key=>$value){$before=$g['states'][$key];$g['states'][$key]=self::clamp($before+$value);$applied[$key]=($applied[$key]??0)+$g['states'][$key]-$before;}
        }
        return $applied;
    }
    private static function fieldDecision(array &$g,array $o,array $roll,int $sid,int $choice): void {
        $i=self::currentIndex($g); $m=&$g['matches'][$i]; $m['participated_on_field']=true; $m['entered_on_field']=true; $st=&$m['stats']; $own=$m['home']===$g['club']?'hg':'ag';
        $type=$o['action'];$clearChance=!empty($o['clear_chance']); $onBall=$clearChance || $sid===0 || $sid===7 || ($sid>=1100 && $sid<=1139) || !empty($o['match_event']);
        $text=$g['feedback']['text']; $beforeStates=$g['states'];
        $wasSentOffBefore=MatchParticipation::isSentOff($m);
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
                } else {
                    $g['feedback']['sound_event'] = 'NO_GOAL';
                    $g['feedback']['sound_event_id'] = self::emitSoundEvent($m, $g, 'NO_GOAL', (int)$m['minute']);
                    $text.=' A bola não entrou.';
                }
            }
            if($type==='desarme' && $roll['success']) {
                $st['tackles']++;
            }
            if($sid>=1125 && $sid<=1139 && $roll['success'] && in_array($type,['passe','drible'],true)){
                $st['tackles']++;foreach(self::effects(['desarme'=>1],$roll,$g) as $k=>$v)$g['feedback']['effects'][$k]=($g['feedback']['effects'][$k]??0)+$v;
            }
            if(in_array($g['position'],['zagueiro','volante','lateral'],true)) {
                if($st['tackles']>=2 && empty($st['tackle_confidence_awarded'])) {
                    $st['tackle_confidence_awarded']=true;
                    $g['states']['confianca']=self::clamp($g['states']['confianca']+self::defensiveTierDelta($g['states']['confianca'],1));
                }
                if($st['tackles']>=4 && empty($st['tackle_dominance_awarded'])) {
                    $st['tackle_dominance_awarded']=true;
                    $g['states']['confianca']=self::clamp($g['states']['confianca']+self::defensiveTierDelta($g['states']['confianca'],2));
                }
            }
            if($g['position']==='goleiro') {
                $savesCount=(int)($st['saves']??0);
                if($savesCount>=2 && empty($st['save_confidence_awarded'])) {
                    $st['save_confidence_awarded']=true;
                    $g['states']['confianca']=self::clamp($g['states']['confianca']+self::defensiveTierDelta($g['states']['confianca'],1));
                }
                if($savesCount>=4 && empty($st['save_dominance_awarded'])) {
                    $st['save_dominance_awarded']=true;
                    $g['states']['confianca']=self::clamp($g['states']['confianca']+self::defensiveTierDelta($g['states']['confianca'],2));
                }
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
            if($roll['success'] && ($roll['class']??'')==='excepcional' && in_array($type,['passe','drible','desarme'],true) && !isset($g['feedback']['highlight']) && empty($st['exceptional_play_awarded'])) {
                $st['exceptional_play_awarded']=true;
                $excCap=class_exists('BalanceService')?(int)BalanceService::param('confidence.balance.v2','exceptional_play_cap',65):65;
                $excBonus=class_exists('BalanceService')?(int)BalanceService::param('confidence.balance.v2','exceptional_play_bonus',1):1;
                if($g['states']['confianca']<$excCap && $excBonus>0) {
                    $g['states']['confianca']=self::clamp($g['states']['confianca']+$excBonus);
                }
            }
            // Simulação e agressão podem gerar falta/cartão; nunca dão gol automático.
            if($sid===7 && $choice===3 && !$roll['success'] && ($roll['class']==='falha_grave' || random_int(1,100)<=25)) {
                $foulRes = MatchDiscipline::recordFoul($g, $m, 'simulation');
                if ($foulRes['text'] !== '') $text .= ' ' . $foulRes['text'];
            }
        }
        if($m[$own]>$auditScore)GoalAudit::score($m,$own,$g['club'],($g['feedback']['highlight']??'')==='goal',['shot_kind'=>$g['feedback']['probability_trace']['kind']??null,'valid_sequence_actions'=>$auditActions]);
        if($sid===8 && in_array($choice,[0,2],true)) {
            if(random_int(1,100)<=65) {
                $foulRes = MatchDiscipline::recordFoul($g, $m, 'dissent');
                if ($foulRes['text'] !== '') $text .= ' ' . $foulRes['text'];
            } else {
                $st['fouls'] = ($st['fouls'] ?? 0) + 1;
            }
        }
        if(!$roll['success'] && $roll['class']==='falha_grave' && $onBall && $type==='desarme') {
            $text.=' Você chega atrasado e comete falta.';
            if(random_int(1,100)<=30){
                $foulRes = MatchDiscipline::recordFoul($g, $m, 'late_tackle');
                if ($foulRes['text'] !== '') $text .= ' ' . $foulRes['text'];
            } else {
                $st['fouls'] = ($st['fouls'] ?? 0) + 1;
            }
        }
        if(!empty($o['card_risk']) && random_int(1,100)<=min(50,$o['card_risk']+intdiv($g['states']['risco_cartao'],5))){
            $foulRes = MatchDiscipline::recordFoul($g, $m, 'argument');
            if ($foulRes['text'] !== '') $text .= ' ' . $foulRes['text'];
        }
        if(isset($g['feedback']['highlight'])){
            $before=$g['states']['confianca'];
            $hlDelta=$g['feedback']['highlight']==='goal'
                ?self::goalConfidenceDelta($g,(int)($st['goals']??1))
                :self::assistConfidenceDelta($g,(int)($st['assists']??1));
            $g['states']['confianca']=self::clamp($before+$hlDelta);
        }
        $g['states']['energia']=self::clamp($g['states']['energia']-3);
        if (MatchParticipation::isSentOff($m)) {
            MatchParticipation::recordExit($m, $g, (int)$m['minute'], 'sent_off');
        } elseif ((int)($st['cards'] ?? 0) >= 2) {
            $st['red_cards'] = max(1, (int)($st['red_cards'] ?? 0));
            MatchParticipation::recordExit($m, $g, (int)$m['minute'], 'sent_off');
            $text .= ' Expulso: segundo cartão.';
        } elseif (random_int(1,1000)<=$g['states']['risco_lesao']) {
            MatchParticipation::recordExit($m, $g, (int)$m['minute'], 'injury');
            $text .= ' Lesão: você deixa o campo.';
            $before=$g['skills']['resistencia']['value'];
            $g['skills']['resistencia']['value']=max(0,$before-1);
            $g['feedback']['effects']['resistencia']=($g['feedback']['effects']['resistencia']??0)+$g['skills']['resistencia']['value']-$before;
        } elseif ($g['states']['energia']<15) {
            MatchParticipation::recordExit($m, $g, (int)$m['minute'], 'fatigue');
            $text .= ' Substituído por desgaste.';
        }
        if (!$wasSentOffBefore && MatchParticipation::isSentOff($m) && empty($st['red_card_confidence_penalized'])) {
            $st['red_card_confidence_penalized'] = true;
            $redPen = class_exists('BalanceService') ? (int)BalanceService::param('confidence.balance.v2', 'sent_off_penalty', -2) : -2;
            $floorSev = class_exists('BalanceService') ? (int)BalanceService::param('confidence.balance.v2', 'zero_trap_floor_severe', 8) : 8;
            if ($g['states']['confianca'] <= $floorSev && $redPen < -1) $redPen = -1;
            $g['states']['confianca'] = self::clamp($g['states']['confianca'] + $redPen);
        }
        $constructive=$onBall && in_array($type,['passe','drible','desarme'],true) && empty($o['end_sequence']);
        $created=MatchMomentum::record($m,$roll['success'],$constructive,isset($g['feedback']['highlight']));
        if($m['minute']>=$m['exit'] || in_array($m['participation_state']??'',[MatchParticipation::STATE_SENT_OFF,MatchParticipation::STATE_SUBSTITUTED_OUT],true)){MatchMomentum::reset($m);$created=false;}
        if($created)$text.=!empty($m['clear_chance'])?' A sequência abriu a defesa: você tem uma chance clara de gol!':' A bola continua com você: escolha como seguir este lance.';
        $g['feedback']['text']=$text; foreach($beforeStates as $k=>$v) if($g['states'][$k]!==$v) $g['feedback']['effects'][$k]=($g['feedback']['effects'][$k]??0)+$g['states'][$k]-$v;
        $logEntry=['minute'=>$m['minute'],'text'=>$text];
        if(in_array($m['participation_state']??'',[MatchParticipation::STATE_SENT_OFF,MatchParticipation::STATE_SUBSTITUTED_OUT],true)){$logEntry['source']='engine';}
        $m['log'][]=$logEntry;
        if(!$created){
            $confBeforeSeg=$g['states']['confianca'];
            self::nextSegment($g);
            if($g['states']['confianca']!==$confBeforeSeg){
                $g['feedback']['effects']['confianca']=($g['feedback']['effects']['confianca']??0)+($g['states']['confianca']-$confBeforeSeg);
            }
        }
    }
    private static function nextSegment(array &$g, ?string $resumeWindow = null): void {
        $i = self::currentIndex($g);
        $m = &$g['matches'][$i];
        $cur = (int)($m['minute'] ?? 0);
        if ($resumeWindow !== null && $resumeWindow !== '' && preg_match('/^\d+-(\d+)$/', $resumeWindow, $wm)) {
            $next = max($cur, (int)$wm[1]);
            $effectiveWindowKey = $resumeWindow;
        } else {
            $next = match ($cur) {0=>15,15=>35,35=>45,45=>60,60=>70,70=>80,80=>90,default=>max(90,$cur)};
            $effectiveWindowKey = $cur . '-' . $next;
        }
        if ($resumeWindow !== null && $next > $cur) {
            $m['evaluated_penalties'] = $m['evaluated_penalties'] ?? [];
            if (!in_array($effectiveWindowKey, $m['evaluated_penalties'], true)) {
                $m['evaluated_penalties'][] = $effectiveWindowKey;
            }
        }
        unset($m);
        self::segment($g, $next);
    }
    private static function segment(array &$g,int $next): void {
        $i=self::currentIndex($g); $m=&$g['matches'][$i];
        if(($m['status']??'')==='finalizada' || !empty($m['match_end_processed'])) {
            $g['stage']='fim_semana';
            return;
        }
        $next=max((int)$m['minute'],$next);
        $span=max(0,$next-(int)$m['minute']);
        // Outro segmento representa outra janela de jogo, nunca a mesma posse.
        MatchMomentum::reset($m);
        $own=$m['home']===$g['club']?'hg':'ag';

        // Invariante: segmento com duração zero não pode gerar eventos esportivos dependentes de tempo transcorrido
        if ($span > 0) {
            // Check for in-match penalty event during segment (idempotente e avaliado uma única vez por janela lógica)
            $m['evaluated_penalties'] = $m['evaluated_penalties'] ?? [];
            $windowKey = $m['minute'] . '-' . $next;
            if (empty($m['active_match_penalty']) && !in_array($windowKey, $m['evaluated_penalties'], true)) {
                $m['evaluated_penalties'][] = $windowKey;
                $isProtagonistOnField = MatchParticipation::canActOnField($m);
                $segProb = class_exists('BalanceService') ? (float)BalanceService::param('match.penalties.in_match', 'segment_team_prob', 0.025) : 0.025;
                $permille = max(0, min(499, (int)round($segProb * 1000)));
                $penaltyRoll = random_int(1, 1000);
                if ($permille > 0 && $penaltyRoll <= $permille) {
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
                } elseif ($permille > 0 && $penaltyRoll >= (1001 - $permille)) {
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
        }
        $m['minute']=$next;
        if($next===45) $m['status']='intervalo';
        if($next>=90) {
            // Reconcilia entrada formal comprovada atravessada no último segmento antes da consolidação terminal
            if ($span > 0 && ($m['participation_state'] ?? null) === MatchParticipation::STATE_ON_BENCH) {
                MatchParticipation::sync($m, $g, $next);
            }
            MatchMomentum::reset($m);
            $m['status']='finalizada';
        }
        MatchParticipation::sync($m, $g, $next);
        $m['log'][]=['minute'=>$next,'text'=>'Placar: '.$m['hg'].' × '.$m['ag'].'.'];
        if($next>=90) {
            $m['stats']['minutes']=MatchParticipation::playedMinutes($m);
            $s=&$m['stats'];
            $s['rating']=$s['minutes']===0?0:MatchRating::calculate($m,$g);
            $s['rating_version']=MatchRating::VERSION;
            $ledgerKey='bal001:confidence:'.$g['season'].':'.($g['competition_id']??'legacy').':'.$m['round'].':'.$m['home'].':'.$m['away'];
            if(!isset($g['reward_ledger'][$ledgerKey])){
                $beforeConf=$g['states']['confianca'];
                $mins=(int)$s['minutes'];
                $r=(float)$s['rating'];
                $ratingNominal=self::postMatchRatingNominal($r,$beforeConf,$mins);
                $gkSaveBonus=0;
                $cleanSheetBonus=0;
                $regularityBonus=0;
                if($mins>0){
                    $pos=strtolower((string)($g['position']??'meia'));
                    $isDef=in_array($pos,['goleiro','zagueiro','lateral','volante'],true);
                    $projConf=self::clamp($beforeConf+$ratingNominal);
                    if($pos==='goleiro'){
                        $saves=(int)($s['saves']??0);
                        if($saves>=2 && empty($s['save_confidence_awarded'])){
                            $s['save_confidence_awarded']=true;
                            $d1=self::defensiveTierDelta($projConf,1);
                            $gkSaveBonus+=$d1;$projConf=self::clamp($projConf+$d1);
                        }
                        if($saves>=4 && empty($s['save_dominance_awarded'])){
                            $s['save_dominance_awarded']=true;
                            $d2=self::defensiveTierDelta($projConf,2);
                            $gkSaveBonus+=$d2;$projConf=self::clamp($projConf+$d2);
                        }
                    }
                    $ga=($m['home']??'')===($g['club']??'')?(int)($m['ag']??0):(int)($m['hg']??0);
                    $csCap=class_exists('BalanceService')?(int)BalanceService::param('confidence.balance.v2','clean_sheet_cap',78):78;
                    $csVal=class_exists('BalanceService')?(int)BalanceService::param('confidence.balance.v2','clean_sheet_bonus',1):1;
                    if($isDef && $mins>=60 && $ga===0 && !MatchParticipation::isSentOff($m) && (((int)($s['tackles']??0)+(int)($s['saves']??0))>=2) && $projConf<$csCap){
                        $cleanSheetBonus=$csVal;
                        $projConf=self::clamp($projConf+$cleanSheetBonus);
                    }
                    $regMinMins=class_exists('BalanceService')?(int)BalanceService::param('confidence.balance.v2','regularity_min_minutes',45):45;
                    $regMinRat=class_exists('BalanceService')?(float)BalanceService::param('confidence.balance.v2','regularity_min_rating',6.7):6.7;
                    $regStep=max(2,class_exists('BalanceService')?(int)BalanceService::param('confidence.balance.v2','regularity_streak_step',3):3);
                    $regCap=class_exists('BalanceService')?(int)BalanceService::param('confidence.balance.v2','regularity_cap',75):75;
                    $regVal=class_exists('BalanceService')?(int)BalanceService::param('confidence.balance.v2','regularity_bonus',1):1;
                    if($mins>=$regMinMins && $r>=$regMinRat){
                        $g['confidence_streak']=((int)($g['confidence_streak']??0))+1;
                        if($g['confidence_streak']>=$regStep && ($g['confidence_streak']%$regStep)===0 && $projConf<$regCap){
                            $regularityBonus=$regVal;
                        }
                    } elseif($mins>=$regMinMins || $r<5.8){
                        $g['confidence_streak']=0;
                    }
                }
                $nominalBonus=$ratingNominal+$gkSaveBonus+$cleanSheetBonus+$regularityBonus;
                $appliedBonus=0;
                if($mins>0 && $nominalBonus!==0){
                    $newConf=self::clamp($beforeConf+$nominalBonus);
                    $appliedBonus=$newConf-$beforeConf;
                    $g['states']['confianca']=$newConf;
                }
                $g['reward_ledger'][$ledgerKey]=[
                    'schema'=>2,
                    'nominal'=>$nominalBonus,
                    'rating_nominal'=>$ratingNominal,
                    'gk_save_bonus'=>$gkSaveBonus,
                    'clean_sheet_bonus'=>$cleanSheetBonus,
                    'regularity_bonus'=>$regularityBonus,
                    'streak'=>(int)($g['confidence_streak']??0),
                    'applied'=>$appliedBonus,
                    'rating'=>$r,
                    'minutes'=>$mins,
                    'conf_before'=>$beforeConf,
                    'conf_after'=>$g['states']['confianca'],
                    'ts'=>time()
                ];
            }
            $rhythmMinutes = (int)$s['minutes'];
            $rhythmKey = 'f04:rhythm:'.$g['season'].':'.($g['competition_id']??'legacy').':'.$m['round'].':'.$m['home'].':'.$m['away'];
            if($rhythmMinutes > 0 && !isset($g['reward_ledger'][$rhythmKey])){
                $g['states']['ritmo_jogo']=self::clamp($g['states']['ritmo_jogo']+3);
                $g['reward_ledger'][$rhythmKey]=['delta'=>3,'minutes'=>$rhythmMinutes];
            }
            Progression::match($g,$m);
            MatchExperience::finish($g);
            MatchDiscipline::processMatchEnd($g, $m);
            $m['match_end_processed'] = true;
            unset($m,$s);
            if(!empty($g['official'])) {
                $ownIndex=self::currentIndex($g);$played=&$g['matches'][$ownIndex];
                foreach(['home','away'] as $side) {$played[$side.'_yellow']=random_int(0,4);$played[$side.'_red']=0;}
                $side=$played['home']===$g['club']?'home':'away';
                $stPlayed=$played['stats']??[];
                $playerYellows=array_key_exists('yellow_cards',$stPlayed)?(int)$stPlayed['yellow_cards']:(int)($stPlayed['cards']??0);
                $playerRed=MatchParticipation::isSentOff($played)?1:0;
                $played[$side.'_yellow']+=$playerYellows;
                $played[$side.'_red']+=$playerRed;
                unset($played);
            }
            foreach($g['matches'] as &$other) if($other['round']===$g['round'] && $other['status']!=='finalizada') Competition::simulate($other,$g); unset($other);
            $g['stage']='fim_semana';
        }
    }
    public static function summary(array $g): array {
        $out=['goals'=>0,'assists'=>0,'minutes'=>0,'games'=>0,'average'=>0]; $sum=0;
        foreach(Competition::playerMatches($g) as $m) if(in_array($g['club'],[$m['home'],$m['away']],true) && $m['status']==='finalizada') {
            foreach(['goals','assists','minutes'] as $k) $out[$k]+=$m['stats'][$k]; if($m['stats']['minutes']>0) { $out['games']++; $sum+=$m['stats']['rating']; }
        }
        $out['average']=$out['games']?round($sum/$out['games'],2):0; return $out;
    }

    private static function goalConfidenceDelta(array $g, int $matchGoalsAfter = 1): int {
        $p = class_exists('BalanceService') ? BalanceService::params('confidence.balance.v2') : [];
        $conf = (int)($g['states']['confianca'] ?? 50);
        $pos = strtolower((string)($g['position'] ?? 'meia'));
        $isDef = in_array($pos, ['goleiro', 'zagueiro', 'lateral', 'volante'], true);
        if ($conf >= 95) {
            return $matchGoalsAfter >= 2 ? (int)($p['goal_very_high'] ?? 1) : 0;
        }
        if ($conf >= 88) {
            return (int)($p['goal_very_high'] ?? 1);
        }
        if ($conf >= 75) {
            return (int)($p['goal_high'] ?? 2);
        }
        if ($conf >= 55) {
            return $isDef ? (int)($p['goal_def_mid'] ?? 4) : (int)($p['goal_att_mid'] ?? 3);
        }
        return $isDef ? (int)($p['goal_def_low'] ?? 5) : (int)($p['goal_att_low'] ?? 4);
    }

    private static function assistConfidenceDelta(array $g, int $matchAssistsAfter = 1): int {
        $p = class_exists('BalanceService') ? BalanceService::params('confidence.balance.v2') : [];
        $conf = (int)($g['states']['confianca'] ?? 50);
        $pos = strtolower((string)($g['position'] ?? 'meia'));
        $isCreator = in_array($pos, ['meia', 'lateral', 'volante', 'ponta'], true);
        if ($conf >= 88) {
            return $matchAssistsAfter >= 2 ? (int)($p['assist_high'] ?? 1) : 0;
        }
        if ($conf >= 75) {
            return (int)($p['assist_high'] ?? 1);
        }
        if ($conf >= 55) {
            return $isCreator ? (int)($p['assist_creator_mid'] ?? 3) : (int)($p['assist_other_mid'] ?? 2);
        }
        return ($isCreator && $conf < 40) ? (int)($p['assist_creator_very_low'] ?? 4) : (int)($p['assist_low'] ?? 3);
    }

    private static function defensiveTierDelta(int $conf, int $tier): int {
        $p = class_exists('BalanceService') ? BalanceService::params('confidence.balance.v2') : [];
        if ($tier === 1) {
            $cap = (int)($p['def_tier1_cap'] ?? 75);
            if ($conf >= $cap) {
                return 0;
            }
            return $conf < 40 ? (int)($p['def_tier1_low'] ?? 2) : (int)($p['def_tier1_mid'] ?? 1);
        }
        $cap = (int)($p['def_tier2_cap'] ?? 85);
        if ($conf >= $cap) {
            return 0;
        }
        return $conf < 65 ? (int)($p['def_tier2_low'] ?? 2) : (int)($p['def_tier2_high'] ?? 1);
    }

    private static function postMatchRatingNominal(float $r, int $conf, int $mins): int {
        if ($mins <= 0) {
            return 0;
        }
        $ceiling = class_exists('BalanceService') ? (int)BalanceService::param('confidence.balance.v2', 'post_ceiling', 90) : 90;
        $fullMin = max(30.0, class_exists('BalanceService') ? (float)BalanceService::param('confidence.balance.v2', 'post_full_minutes', 60.0) : 60.0);
        if ($r < 4.6) {
            $raw = $conf > 25 ? -2 : ($conf > 10 ? -1 : 0);
        } elseif ($r < 5.3) {
            $raw = $conf > 18 ? -1 : 0;
        } elseif ($r < 6.0) {
            $raw = 0;
        } elseif ($r < 6.7) {
            $raw = $conf < 30 ? 2 : ($conf < 52 ? 1 : 0);
        } elseif ($r < 7.5) {
            $raw = $conf < 35 ? 3 : ($conf < 55 ? 2 : ($conf < 68 ? 1 : 0));
        } elseif ($r < 8.5) {
            $raw = $conf < 35 ? 4 : ($conf < 58 ? 3 : ($conf < 72 ? 2 : ($conf < 78 ? 1 : 0)));
        } elseif ($r < 9.3) {
            $raw = $conf < 35 ? 5 : ($conf < 58 ? 4 : ($conf < 75 ? 3 : ($conf < $ceiling ? 2 : 0)));
        } else {
            $raw = $conf < 35 ? 6 : ($conf < 58 ? 5 : ($conf < 75 ? 4 : ($conf < 84 ? 3 : ($conf < $ceiling ? 2 : 0))));
        }
        $factor = min(1.0, $mins / $fullMin);
        $scaled = (int)round($raw * $factor);
        if ($scaled > 0) {
            if ($conf >= $ceiling) {
                return 0;
            }
            $scaled = min($scaled, $ceiling - $conf);
        }
        return $scaled;
    }

    private static function applySeasonAchievementConfidence(array &$g): void {
        $seasonNum = (int)($g['season'] ?? 1);
        $ledgerKey = 'bal001:season_achievement:' . $seasonNum;
        if (isset($g['reward_ledger'][$ledgerKey])) {
            return;
        }
        $sum = self::summary($g);
        $minutes = max(0, (int)($sum['minutes'] ?? 0));
        $before = (int)($g['states']['confianca'] ?? 50);
        if ($minutes <= 0) {
            $g['reward_ledger'][$ledgerKey] = [
                'schema' => 2,
                'season' => $seasonNum,
                'minutes' => 0,
                'nominal' => 0,
                'applied' => 0,
                'conf_before' => $before,
                'conf_after' => $before,
                'ts' => time(),
            ];
            return;
        }

        $p = class_exists('BalanceService') ? BalanceService::params('confidence.balance.v2') : [];
        $club = (string)($g['club'] ?? '');
        $seasonTitles = [];
        if (class_exists('Trophies')) {
            foreach (Trophies::collect($g) as $t) {
                if ((int)($t['season'] ?? 0) === $seasonNum && ($t['club'] ?? '') === $club) {
                    $seasonTitles[] = $t;
                }
            }
        }
        if (empty($g['official']) && !$seasonTitles && !empty($g['teams']) && !empty($g['matches'])) {
            $teams = array_values($g['teams']);
            $expected = count($teams) >= 2 && count($teams) % 2 === 0 ? count($teams) * (count($teams) - 1) : 0;
            $doneCount = count(array_filter($g['matches'], static fn($m) => ($m['status'] ?? '') === 'finalizada'));
            if ($expected > 0 && $doneCount === $expected) {
                $tbl = Schedule::table($g['matches'], $teams);
                if (($tbl[0]['team'] ?? '') === $club) {
                    $seasonTitles[] = ['season' => $seasonNum, 'club' => $club, 'title' => 'Liga regional', 'kind' => 'champion'];
                }
            }
        }

        $majorCount = 0;
        $stageCount = 0;
        foreach ($seasonTitles as $st) {
            if (($st['kind'] ?? 'champion') === 'stage_title') {
                $stageCount++;
            } else {
                $majorCount++;
            }
        }

        $promoted = false;
        $relegated = false;
        foreach (($g['competition_results'] ?? []) as $res) {
            if (!empty($res['promoted'])) {
                $promoted = true;
            }
            if (!empty($res['relegated']) && empty($res['relegation_pending'])) {
                $relegated = true;
            }
        }

        $posRaw = 0;
        if ($majorCount > 0) {
            $posRaw += (int)($p['season_title_major'] ?? 5) + max(0, $majorCount - 1) * 2;
        }
        if ($stageCount > 0) {
            $posRaw += $stageCount * (int)($p['season_title_stage'] ?? 2);
        }
        if ($promoted) {
            $posRaw += (int)($p['season_promotion'] ?? 4);
        }

        $cap = (int)($p['season_achievement_cap'] ?? 6);
        $presence = min(1.0, $minutes / 360.0);
        $posScaled = (int)round(min($cap, $posRaw) * $presence);
        if ($posScaled > 0) {
            if ($before >= 94) {
                $posScaled = min(1, $posScaled);
            } elseif ($before >= 86) {
                $posScaled = (int)ceil($posScaled * 0.5);
            }
        }

        $negScaled = 0;
        if ($relegated && $posScaled === 0 && $before > 12) {
            $evalBand = class_exists('SeasonEvaluation') ? (string)(SeasonEvaluation::current($g)['band'] ?? 'regular') : 'regular';
            if (in_array($evalBand, ['ruim', 'regular'], true)) {
                $rawNeg = (int)($p['season_relegation_penalty'] ?? -2);
                $negScaled = $before <= 20 ? -1 : (int)round($rawNeg * $presence);
            }
        }

        $nominal = $posScaled + $negScaled;
        $after = self::clamp($before + $nominal);
        $applied = $after - $before;
        $g['states']['confianca'] = $after;
        $g['reward_ledger'][$ledgerKey] = [
            'schema' => 2,
            'season' => $seasonNum,
            'minutes' => $minutes,
            'major_titles' => $majorCount,
            'stage_titles' => $stageCount,
            'promoted' => $promoted,
            'relegated' => $relegated,
            'nominal' => $nominal,
            'applied' => $applied,
            'conf_before' => $before,
            'conf_after' => $after,
            'ts' => time(),
        ];

        if ($applied !== 0) {
            if (isset($g['feedback']) && is_array($g['feedback'])) {
                $g['feedback']['effects']['confianca'] = ($g['feedback']['effects']['confianca'] ?? 0) + $applied;
            } else {
                $g['feedback'] = [
                    'text' => $applied > 0
                        ? 'As conquistas coletivas da temporada fortaleceram sua confiança.'
                        : 'O desfecho difícil da temporada pesou no ambiente.',
                    'effects' => ['confianca' => $applied],
                ];
            }
        }
    }

    private static function applyOffseasonConfidenceTransition(array &$g, int $closedSeason, string $evalBand): int {
        $ledgerKey = 'bal001:offseason:' . $closedSeason;
        if (isset($g['reward_ledger'][$ledgerKey])) {
            return 0;
        }
        $p = class_exists('BalanceService') ? BalanceService::params('confidence.balance.v2') : [];
        $before = (int)($g['states']['confianca'] ?? 50);
        $targetRef = (int)($p['offseason_target_ref'] ?? 42);
        $coolThreshold = (int)($p['offseason_cool_threshold'] ?? 88);
        $nominal = 0;

        if ($before < $targetRef) {
            $isPoor = ($evalBand === 'ruim');
            $rate = $isPoor ? (float)($p['offseason_rate_poor'] ?? 0.15) : (float)($p['offseason_rate_normal'] ?? 0.25);
            $cap = $isPoor ? (int)($p['offseason_cap_poor'] ?? 3) : (int)($p['offseason_cap_normal'] ?? 6);
            $nominal = min($cap, max(1, (int)round(($targetRef - $before) * $rate)));
        } elseif ($before > $coolThreshold) {
            $coolCap = (int)($p['offseason_cool_cap'] ?? 3);
            $rawCool = min($coolCap, max(1, (int)round(($before - $coolThreshold) * 0.25)));
            $nominal = -min($rawCool, $before - $coolThreshold);
        }

        $after = self::clamp($before + $nominal);
        $applied = $after - $before;
        $g['states']['confianca'] = $after;
        $g['reward_ledger'][$ledgerKey] = [
            'schema' => 2,
            'closed_season' => $closedSeason,
            'band' => $evalBand,
            'nominal' => $nominal,
            'applied' => $applied,
            'conf_before' => $before,
            'conf_after' => $after,
            'ts' => time(),
        ];
        return $applied;
    }

    /**
     * Registra evento sonoro autoritativo persistente com ID único imutável
     */
    public static function emitSoundEvent(array &$m, array &$g, string $type, int $minute): string {
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
        $g['current_command_sound_event_ids'] = $g['current_command_sound_event_ids'] ?? [];
        if (!in_array($eventId, $g['current_command_sound_event_ids'], true)) {
            $g['current_command_sound_event_ids'][] = $eventId;
        }
        if (isset($g['feedback']) && is_array($g['feedback'])) {
            $g['feedback']['sound_event_ids'] = $g['feedback']['sound_event_ids'] ?? [];
            if (!in_array($eventId, $g['feedback']['sound_event_ids'], true)) {
                $g['feedback']['sound_event_ids'][] = $eventId;
            }
            if (empty($g['feedback']['sound_event_id'])) {
                $g['feedback']['sound_event_id'] = $eventId;
            }
        }
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
