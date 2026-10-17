<?php
declare(strict_types=1);

/** Isolated regulatory integration. National destinations remain blocked, not faked. */
final class AlagoasTrial {
    public static function create(int $id,string $position='meia',string $mode='normal'):array {
        if(PHP_SAPI!=='cli'||config('RUMO10_WORLD_FIXTURES','0')!=='1')throw new RuntimeException('Somente teste CLI local.');
        $reference=json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-al-2026.json'),true,512,JSON_THROW_ON_ERROR);
        $identities=[];foreach($reference['identities'] as $r)foreach($r['labels'] as $label)$identities[$label]=$r['club'];
        $known=Competition::clubs();$missing=[];
        foreach($reference['identities'] as $r)if(!isset($known[$r['club']])){
            $memberships=[];foreach($reference['competitions'] as $cid=>$entry)foreach($entry['participants'] as $participant)if($identities[$participant['label']]===$r['club']&&!isset($entry['excluded'][$r['club']]))$memberships[]=$cid;
            $missing[$r['club']]=['name'=>$r['club'],'country'=>'BR','uf'=>'AL','competitions'=>$memberships,'game_only'=>true,'rating_origin'=>'existing_game_fallback_not_official_rating','source'=>$reference['sources'][$r['source']]['url']];
        }
        $defs=[];
        foreach(['al-a1'=>'a','al-entrada'=>'b','al-copa'=>'cup'] as $key=>$kind){
            $r=$reference['competitions'][$key];$groups=[];
            foreach($r['participants'] as $entry){$club=$identities[$entry['label']];if(isset($r['excluded'][$club]))continue;$groups[$entry['group']??'1'][]=$club;}
            $groups=array_values($groups);$teams=array_merge(...$groups);
            $defs[$key]=['id'=>$key,'name'=>match($kind){'a'=>'Alagoano Série A','b'=>'Alagoano Série B Sub-23',default=>'Copa Alagoas'},'edition'=>2026,'country'=>'BR','uf'=>'AL','family'=>'state','tier'=>$kind==='b'?6:5,'format'=>'alagoas','test_fixture'=>true,'regulatory_integration_test'=>true,'scope'=>'state','pyramid'=>'al','verified_sources'=>array_map(fn($s)=>$reference['sources'][$s]['url'],$r['source_ids']),'teams'=>$teams,'groups'=>$groups,'reference_clubs'=>$missing,'rules'=>['adapter_version'=>AlagoasCompetition::VERSION,'kind'=>$kind,'return'=>false,'qualify'=>4,'legs'=>$kind==='cup'?1:2,'final_legs'=>$kind==='cup'?1:2,'access'=>$kind==='b'?1:0,'relegation'=>$kind==='a'?1:0,'note'=>'Teste regulatório isolado; datas e temporadas futuras simuladas, vagas nacionais bloqueadas.']];
            if($kind==='b')$defs[$key]['progression_upper']='al-a1';
        }
        $g=Game::legacyCreate('Atleta de teste','Maceió','AL',$position);$g['id']=$id;$g['club']='Zumbi-AL';$g['play_mode']=$mode;$g['handicap']='Equilibrado';$g['season_skill_baseline']=array_sum(array_column($g['skills'],'value'))/9;
        $g['alagoas_trial']=['schema'=>1,'official_path_released'=>false,'future_policy'=>'simulated_faf2026_reuse_v1','national_dependencies'=>'blocked'];
        return WorldSeason::attachFixture($g,$defs,array_map(fn($c)=>$c['teams'],$defs));
    }
    public static function prepareNext(array &$g):void {
        foreach(['al-a1','al-entrada','al-copa'] as $id)if(empty($g['competitions'][$id]['done']))throw new RuntimeException('Percurso estadual incompleto.');
        $a=$g['competitions']['al-a1'];$b=$g['competitions']['al-entrada'];$cup=$g['competitions']['al-copa'];
        // Explicit future simulation: previous A plus B semifinalists, never the real future entry list.
        $teams=array_merge($g['memberships']['al-a1'],array_slice($b['initial_ranking'],0,4));
        if(count(array_unique($teams))!==12)throw new RuntimeException('Classificação simulada da copa duplicada.');
        usort($teams,fn($x,$y)=>strcmp(hash('sha256',$g['id'].'|'.$g['season'].'|'.$x),hash('sha256',$g['id'].'|'.$g['season'].'|'.$y)));
        $g['next_memberships']['al-copa']=$teams;
        $qualified=array_slice($a['final_ranking'],0,3);
        foreach($cup['final_ranking'] as $club)if(!in_array($club,$qualified,true)&&in_array($club,array_merge($g['memberships']['al-a1'],$g['memberships']['al-entrada']),true)){$qualified[]=$club;break;}
        $key='al|'.$g['season'];
        $g['national_qualification_pending'][$key]=['source_edition'=>$g['season'],'basis'=>'FAF 2026 reference; future simulation only','copa_do_brasil_candidates'=>$qualified,'serie_d_candidates'=>$a['final_ranking'],'state'=>'blocked_external_eligibility_and_target_runtime','granted_entries'=>[]];
    }
}
