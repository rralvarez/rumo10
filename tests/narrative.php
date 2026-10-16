<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function ncheck(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function ng(string $stage='partida',string $position='meia',int $minute=70,int $delta=0):array {
 $g=Game::legacyCreate('Fixture narrativa','São Paulo','SP',$position);$g['stage']=$stage;$g['story_seed']='narrative-fixed-seed';$g['states']['risco_lesao']=0;
 $i=Game::currentIndex($g);$m=&$g['matches'][$i];$m['minute']=$stage==='fim_semana'?90:$minute;$m['status']=$stage==='fim_semana'?'finalizada':'em_andamento';$m['entry']=0;$m['exit']=90;$m['lineup']='titular';$m['stats']['minutes']=$minute;$m['stats']['rating']=6.5;
 $home=$m['home']===$g['club'];$m[$home?'hg':'ag']=max(0,$delta);$m[$home?'ag':'hg']=max(0,-$delta);return $g;
}
$catalog=Scenes::catalog();$new=NarrativeCatalog::all();ncheck(count($new)>=100,'Lotes de cenas reais, sem contadores vazios');ncheck(count($catalog)===214+count($new),'214 anteriores preservadas mais novas');
$baseline=json_decode(file_get_contents(dirname(__DIR__).'/Docs/narrativa-v1/baseline-resolved.json'),true,512,JSON_THROW_ON_ERROR);
foreach($baseline as $item){$s=$item['raw'];$g=ng($s['stage']);$g['active_situation']=['id'=>$s['id'],'key'=>Scenes::key($g)];ncheck(Scenes::current($g)===$item['resolved'],'Texto/opções anteriores imutáveis '.$s['id']);}
$unique=[];
foreach($new as $id=>$s){ncheck(!isset($unique[$s['text']]),'Enunciado novo não duplicado '.$id);$unique[$s['text']]=true;ncheck(NarrativeCatalog::validate($s)===[],'Contrato '.$id);ncheck(count($s['options'])===4,'Quatro opções '.$id);
 foreach($s['options'] as $o){ncheck(!str_contains($o['failure_text'],'A tentativa não sai'),'Resposta específica');ncheck($o['success_text']!==$o['failure_text'],'Desfechos diferentes');}
}
$sample=reset($new);$bad=$sample;$bad['gating']=['weather'=>'rain'];ncheck((bool)NarrativeCatalog::validate($bad),'Clima ativo sem fonte rejeitado');$bad['enabled']=false;ncheck(NarrativeCatalog::validate($bad)===[],'Conteúdo futuro pode ser mantido inativo');ncheck(!NarrativeContext::allows($bad,ng('treino')),'Inativo nunca elegível');
$bad=$sample;$bad['gating']=['tag_inventada'=>true];ncheck((bool)NarrativeCatalog::validate($bad),'Tag desconhecida rejeitada');ncheck(!NarrativeContext::allows($bad,ng('treino')),'Gating desconhecido falha fechado');
$bad=$sample;$bad['options'][0]['effects']=['dinheiro'=>20];ncheck((bool)NarrativeCatalog::validate($bad),'Efeito desconhecido rejeitado');
$bad=$sample;$bad['options'][0]['effects']=['passe'=>-1];ncheck((bool)NarrativeCatalog::validate($bad),'Perda arbitrária de skill rejeitada');
foreach(NarrativeContext::UNSUPPORTED as $tag){$bad=$sample;$bad['gating']=[$tag=>true];ncheck((bool)NarrativeCatalog::validate($bad),'Fonte sem adaptador ativa rejeitada '.$tag);ncheck(!NarrativeContext::allows($bad,ng('treino')),'Sem fonte não há elegibilidade '.$tag);}
$g=ng('pre_jogo');$g['states']['energia']=10;ncheck(!in_array(2011,Scenes::eligible($g),true),'Não relacionado não é reserva');ncheck(!in_array(6,Scenes::eligible($g),true),'Legado não presume banco para não relacionado');
$g=ng('pre_jogo');$g['states']['energia']=35;ncheck(!in_array(2011,Scenes::eligible($g),true),'Previsão de reserva sem confirmação não sustenta cena');$g['round']=4;ncheck(in_array(2011,Scenes::eligible($g),true),'Regra determinística de reserva confirma cena');
$matrix=[];
$reachability=[];
foreach($new as $id=>$scene){
 if(!$scene['enabled']){ncheck(!NarrativeContext::allows($scene,ng($scene['stage'])),'Conteúdo sem fonte nunca selecionável '.$id);$reachability[]=['id'=>$id,'module'=>$scene['module'],'eligible_fixture'=>false,'reason'=>'inativa_sem_adaptador'];continue;}
 $gate=$scene['gating'];$position=$gate['positions_allow'][0]??'meia';$g=ng($scene['stage'],$position,$gate['minute_min']??min(70,$gate['minute_max']??70));
 $i=Game::currentIndex($g);$m=&$g['matches'][$i];
 if(isset($gate['home_away'])){if(($m['home']===$g['club'])!==($gate['home_away']==='home')){[$m['home'],$m['away']]=[$m['away'],$m['home']];}}
 if(isset($gate['score_state'])){$d=match($gate['score_state']){'ganhando'=>1,'perdendo'=>-1,default=>0};$home=$m['home']===$g['club'];$m[$home?'hg':'ag']=max(0,$d);$m[$home?'ag':'hg']=max(0,-$d);}
 if(!empty($gate['bench'])){$m['lineup']='reserva';if($scene['stage']==='pre_jogo')$g['round']=4;}
 foreach(['goals','cards','assists','shots','completed','tackles'] as $stat)if(isset($gate['own_'.$stat.'_min']))$m['stats'][$stat]=$gate['own_'.$stat.'_min'];
 foreach(['injury_risk_min'=>'risco_lesao','disciplinary_risk_min'=>'risco_cartao'] as $key=>$state)if(isset($gate[$key]))$g['states'][$state]=$gate[$key];
 if(isset($gate['played_minutes_max']))$m['stats']['minutes']=$gate['played_minutes_max'];
 if(isset($gate['played_minutes_min'])){$m['entry']=0;$m['minute']=max($m['minute'],$gate['played_minutes_min']);$m['stats']['minutes']=max($m['stats']['minutes'],$gate['played_minutes_min']);}
 if(isset($gate['recent_form'])){$m['status']='finalizada';$m['stats']['minutes']=70;$m['stats']['rating']=match($gate['recent_form']){'boa'=>8,'ruim'=>5,default=>6.5};if($scene['stage']!=='fim_semana'){$previous=$m;$previous['round']=0;$g['matches'][]=$previous;$m['status']='em_andamento';}}
 if(isset($gate['exit_reason']))$m['log'][]=['minute'=>60,'text'=>match($gate['exit_reason']){'injury'=>'Lesão: você deixa o campo.','fatigue'=>'Substituído por desgaste.','red'=>'Expulso: segundo cartão.'}];
 $allowed=NarrativeContext::allows($scene,$g);ncheck($allowed,'Cena possui testemunha de elegibilidade '.$id);$reachability[]=['id'=>$id,'module'=>$scene['module'],'eligible_fixture'=>$allowed];unset($m);
}
// Ausência de evento/estatística não pode ser tomada como confirmação narrativa.
$g=ng('fim_semana');$i=Game::currentIndex($g);$ctx=NarrativeContext::values($g);ncheck(!isset($ctx['exit_reason']),'Sem marcador não inventa motivo de saída');
foreach(['injury'=>'Lesão: você deixa o campo.','fatigue'=>'Substituído por desgaste.','red'=>'Expulso: segundo cartão.'] as $reason=>$marker){$copy=$g;$copy['matches'][$i]['log'][]=['minute'=>60,'text'=>$marker];ncheck(NarrativeContext::values($copy)['exit_reason']===$reason,'Motivo vem de registro exato '.$reason);}
foreach(['assists','shots','completed','tackles'] as $stat){$scene=$new[2385];$scene['gating']=['own_'.$stat.'_min'=>1];$g['matches'][$i]['stats'][$stat]=0;ncheck(!NarrativeContext::allows($scene,$g),'Zero não satisfaz estatística '.$stat);$g['matches'][$i]['stats'][$stat]=1;ncheck(NarrativeContext::allows($scene,$g),'Estatística persistida satisfaz '.$stat);}
foreach(Rules::POSITIONS as $p){
 foreach(['treino','pre_jogo','partida','fim_semana'] as $stage){$g=ng($stage,$p);$pool=Scenes::eligible($g);$counts=[];foreach($pool as $id){$mod=$catalog[$id]['module'];$counts[$mod]=($counts[$mod]??0)+1;}
  $matrix[]=['position'=>$p,'stage'=>$stage,'scenario'=>'70min_empate_titular','eligible'=>count($pool),'modules'=>$counts];
  if($stage==='partida'){ncheck(count($pool)>=60,'60 de partida '.$p);ncheck(($counts['J01']??0)>=20,'20 ofensivas '.$p);ncheck(($counts['J02']??0)>=20,'20 defensivas '.$p);}
 }
 foreach([15,35,70,80] as $minute)foreach([-1,0,1] as $score){$g=ng('partida',$p,$minute,$score);$pool=Scenes::eligible($g);foreach($pool as $id)if($id>=2000)ncheck(NarrativeContext::allows($catalog[$id],$g),'Elegibilidade contextual coerente');
  $matrix[]=['position'=>$p,'stage'=>'partida','scenario'=>$minute.'min_delta'.$score,'eligible'=>count($pool),'modules'=>array_count_values(array_map(fn($id)=>$catalog[$id]['module'],$pool))];
 }
 $g=ng('partida',$p);$i=Game::currentIndex($g);$g['matches'][$i]['entry']=80;ncheck(Scenes::eligible($g)===[],'Banco sem bola '.$p);Scenes::prepare($g);ncheck(empty($g['active_situation']),'Banco sem cena em campo '.$p);
 $g=ng('partida',$p);$g['matches'][Game::currentIndex($g)]['exit']=60;ncheck(Scenes::eligible($g)===[],'Substituído sem cena em campo '.$p);
 foreach(['boa','ruim'] as $form){$g=ng('fim_semana',$p);$g['matches'][Game::currentIndex($g)]['stats']['rating']=$form==='boa'?8:5;$ctx=NarrativeContext::values($g);ncheck(($ctx['recent_form']??null)===$form,'Forma vem de nota realmente salva');$matrix[]=['position'=>$p,'stage'=>'fim_semana','scenario'=>'forma_'.$form,'eligible'=>count(Scenes::eligible($g)),'modules'=>array_count_values(array_map(fn($id)=>$catalog[$id]['module'],Scenes::eligible($g)))];}
 $g=ng('pre_jogo',$p);$g['round']=4;$matrix[]=['position'=>$p,'stage'=>'pre_jogo','scenario'=>'reserva_confirmada_rodada4','eligible'=>count(Scenes::eligible($g)),'modules'=>array_count_values(array_map(fn($id)=>$catalog[$id]['module'],Scenes::eligible($g)))];
}
$g=ng('treino');$s=reset($new);$g['active_situation']=['id'=>$s['id'],'key'=>Scenes::key($g),'snapshot'=>$s];$g['active_situation']['snapshot']['text']='Texto congelado de uma versão editorial anterior.';ncheck(Scenes::current($g)['text']==='Texto congelado de uma versão editorial anterior.','Snapshot prevalece sobre arquivo editado');$copy=$g;Scenes::prepare($g);ncheck($g===$copy,'Reload mantém seed/snapshot');
foreach(['treino','pre_jogo','partida','fim_semana'] as $stage){$g=ng($stage);$fallback=NarrativeSelection::select($g,[],$catalog);ncheck(NarrativeCatalog::validate($fallback['snapshot'])===[],'Fallback neutro válido '.$stage);ncheck($fallback['selection']['fallback']===['pool_vazio_neutro'],'Fallback explicitamente auditado');}
$repetition=[];
foreach(Rules::POSITIONS as $p){$g=ng('partida',$p);$g['matches']=[$g['matches'][Game::currentIndex($g)]];$ids=[];$families=[];$same10=0;$same20=0;$family10=0;$family20=0;$fallbacks=0;
 for($n=0;$n<500;$n++){$g['season']=1+intdiv($n,50);$g['round']=1+$n%50;$g['matches'][0]['round']=$g['round'];unset($g['active_situation']);Scenes::prepare($g);$copy=$g;Scenes::prepare($copy);ncheck($copy===$g,'Seleção determinística');$s=Scenes::current($g);$family=$catalog[$s['id']]['family']??$s['family'];
  $same10+=in_array($s['id'],array_slice($ids,-10),true)?1:0;$same20+=in_array($s['id'],array_slice($ids,-20),true)?1:0;$family10+=in_array($family,array_slice($families,-10),true)?1:0;$family20+=in_array($family,array_slice($families,-20),true)?1:0;$fallbacks+=count($g['active_situation']['selection']['fallback']);Scenes::remember($g,$s);$ids[]=$s['id'];$families[]=$family;
 }
 ncheck($same10===0,'Não repete IDs em dez aparições '.$p);
 $repetition[]=['position'=>$p,'rounds'=>500,'seasons'=>10,'unique_ids'=>count(array_unique($ids)),'id_repeat_10_pct'=>$same10/5,'id_repeat_20_pct'=>$same20/5,'family_repeat_10_pct'=>$family10/5,'family_repeat_20_pct'=>$family20/5,'fallbacks'=>$fallbacks];
}
// Raridade é medida em contexto fixo com pool sintético coerente, não alterando cenas publicadas.
$g=ng('partida');$g['matches']=[$g['matches'][Game::currentIndex($g)]];$a=$new[2015];$a['rarity']='common';$b=$a;$b['id']=9001;$b['rarity']='rare';$rare=0;
for($n=0;$n<5000;$n++){$g['round']=$n+1;$g['matches'][0]['round']=$g['round'];$id=NarrativeSelection::select($g,[$a['id'],$b['id']],[$a['id']=>$a,$b['id']=>$b])['id'];$rare+=$id===9001?1:0;}
ncheck($rare>200 && $rare<700,'Raridade permanece aproximadamente 9%');
$rareWithMemory=0;
for($n=0;$n<5000;$n++){$g['round']=$n+1;$g['matches'][0]['round']=$g['round'];$selected=NarrativeSelection::select($g,[$a['id'],$b['id']],[$a['id']=>$a,$b['id']=>$b]);$rareWithMemory+=$selected['id']===9001?1:0;Scenes::remember($g,$selected['id']===9001?$b:$a);}
ncheck($rareWithMemory===$rare,'Novidade/memória não tornam raridade comum');
$apply=new ReflectionMethod(Game::class,'effects');
foreach($new as $s)foreach($s['options'] as $o){
 $g=ng($s['stage']);$chance=Game::chance($g,$o);
 foreach([['success'=>true,'class'=>'sucesso'],['success'=>true,'class'=>'excepcional'],['success'=>false,'class'=>'falha'],['success'=>false,'class'=>'falha_grave']] as $roll){
  $expected=null;foreach(['normal','rapido','expresso'] as $mode){$copy=$g;$copy['play_mode']=$mode;ncheck(Game::chance($copy,$o)===$chance,'Chance independente de modo');$effects=$apply->invokeArgs(null,[$o['effects'],$roll,&$copy,$o]);$state=[$copy['skills'],$copy['states'],$effects];if($expected!==null)ncheck($state===$expected,'Mesmo efeito/XP para mesmo roll nos três modos');$expected=$state;}
 }
}
$field=new ReflectionMethod(Game::class,'fieldDecision');
foreach(['chute','passe','desarme'] as $action){
 $option=null;$sid=0;foreach($new as $s)if($s['stage']==='partida')foreach($s['options'] as $o)if($o['action']===$action){$option=$o;$sid=$s['id'];break 2;}
 ncheck($option!==null,'Ação integrada no lote '.$action);$g=ng('partida','meia',15);$i=Game::currentIndex($g);$roll=['success'=>true,'class'=>'sucesso','chance'=>10,'roll'=>1];$g['feedback']=$roll+['text'=>$option['success_text'],'effects'=>[]];
 if($action==='passe')$option['clear_chance']=true; // Isola conversão garantida já suportada pelo motor, sem mudar dados.
 $field->invokeArgs(null,[&$g,$option,$roll,$sid,0]);$m=$g['matches'][$i];$own=$m['home']===$g['club']?$m['hg']:$m['ag'];
 ncheck($m['stats']['goals']+$m['stats']['assists']<=$own,'Evento novo reconciliado ao placar '.$action);
 if($action==='chute')ncheck($m['stats']['goals']===1 && $g['feedback']['highlight']==='goal','Chute novo bem-sucedido produz gol');
 if($action==='passe')ncheck($m['stats']['assists']===1 && $g['feedback']['highlight']==='assist','Passe claro novo produz assistência com gol');
 if($action==='desarme')ncheck($m['stats']['tackles']===1,'Desarme novo registrado na estatística');
}
$g=ng('fim_semana');$matches=$g['matches'];Scenes::prepare($g);ncheck($g['matches']===$matches,'Preparação não recalcula partidas encerradas');
$out=dirname(__DIR__).'/Docs/narrativa-v1';file_put_contents($out.'/eligibilidade.json',json_encode($matrix,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));file_put_contents($out.'/repeticao-500.json',json_encode(['cohorts'=>$repetition,'rare_in_5000'=>$rare,'rare_with_memory_in_5000'=>$rareWithMemory],JSON_PRETTY_PRINT));file_put_contents($out.'/test-contract.json',json_encode(['checks'=>$checks,'new'=>count($new),'catalog'=>count($catalog),'baseline_preserved'=>count($baseline),'mode'=>'PHP puro, sem banco/Hub/rede'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
file_put_contents($out.'/elegibilidade-por-id.json',json_encode($reachability,JSON_PRETTY_PRINT));
echo "OK: $checks verificações; 214 snapshots antigos, ".count($new)." novas, seis posições, 500 rodadas por posição.\n";
