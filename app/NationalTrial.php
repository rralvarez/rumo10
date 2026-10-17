<?php
declare(strict_types=1);
/** First national integration: one edition only, not a released complete career. */
final class NationalTrial {
    public static function registerSquads(array &$g):void {
        foreach(['br-a','br-b','br-c','br-d'] as $competition)foreach($g['memberships'][$competition]??[] as $club){$reference=ClubSquad::players($club);$players=$reference;usort($players,fn($a,$b)=>[($a['position']??'')!=='goleiro',-(float)($a['overall']??0),$a['id']]<=>[($b['position']??'')!=='goleiro',-(float)($b['overall']??0),$b['id']]);$players=array_slice($players,0,50);$count=count($players);$keepers=count(array_filter($players,fn($p)=>($p['position']??'')==='goleiro'));$added=[];
            while($count<25||$keepers<2){$position=$keepers<2?'goleiro':'meia';$added[]=['id'=>'fictional:national:'.substr(hash('sha256',$club.'|'.$g['season'].'|'.$count),0,20),'origin'=>'fictional','position'=>$position,'simulated_birth_year'=>2025+$g['season']-18];$count++;if($position==='goleiro')$keepers++;}
            $g['national_squad_registration'][$club]=['reference_count'=>count($reference),'registered_count'=>$count,'registered_reference_ids'=>array_column($players,'id'),'not_registered_reference_count'=>count($reference)-count($players),'goalkeepers'=>$keepers,'added_simulated_players'=>$added,'reference_sha256'=>hash('sha256',json_encode($reference,JSON_THROW_ON_ERROR)),'official_bid_verified'=>false,'selection_basis'=>'simulated_registration; reference roster not modified'];
        }
    }
    public static function create(int $id,string $mode='normal'):array {
        $b=Competition::catalog()['br-b'];$g=AlagoasTrial::create($id,'meia',$mode);$defs=array_map(fn($p)=>$p['definition'],$g['world_reference']);
        $b['test_fixture']=true;$b['verified_sources']=['https://www.cbf.com.br/futebol-brasileiro/noticias/undefined/ceara-x-guarani/cbf-divulga-documentos-tecnicos-do-brasileirao-da-serie-b-de-2026','https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/Tabela_Detalhada_Brasileiro_Serie_B_2026_09_03_4ba230593f.pdf'];$b['external_progression_pending']=true;
        $b['verified_sources'][]=$b['source'];$defs['br-b']=$b;$g['club']='CRB-AL';$g['national_trial']=['edition'=>2026,'continuation'=>'unavailable_until_national_destinations_validated','mandatory_tournaments_not_complete'=>true];
        return WorldSeason::attachFixture($g,$defs,array_map(fn($c)=>$c['teams'],$defs),['max_matches'=>512,'budget'=>8]);
    }
    /** A/B/C results can be homologated; global renewal remains blocked by D/cups. */
    public static function createABC(int $id,string $mode='normal'):array {
        $catalog=Competition::catalog();$ref=json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-abc-2026.json'),true,512,JSON_THROW_ON_ERROR);
        $g=self::create($id,$mode);$defs=array_map(fn($p)=>$p['definition'],$g['world_reference']);
        foreach($ref['competitions'] as $key=>$entry){$c=$catalog[$key];if($c['teams']!==$entry['participants'])throw new RuntimeException('Cadastro nacional diverge da inscrição-base verificada.');
            $c['test_fixture']=true;$c['national_reference']=$ref['id'];$c['verified_sources']=[$entry['source']];$c['rules']['registration']=$entry['registration'];
            if($key==='br-c'){$c['format']='national_c';$c['rules']['home_priority']=$entry['home_priority'];}
            $defs[$key]=$c;
        }
        $g['national_trial']['schema']='abc-closure-v1';
        return WorldSeason::attachFixture($g,$defs,array_map(fn($c)=>$c['teams'],$defs),['max_matches'=>1152,'budget'=>8]);
    }
    public static function createABCD(int $id,string $mode='normal'):array {
        $d=Competition::catalog()['br-d'];$g=self::createABC($id,$mode);$defs=array_map(fn($p)=>$p['definition'],$g['world_reference']);
        $r=json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-d-sports-2026.json'),true,512,JSON_THROW_ON_ERROR);
        $d['groups']=$r['groups'];$d['test_fixture']=true;$d['format']='national_d';$d['d_reference']=$r['id'];$d['verified_sources']=[$r['rec_source'],$r['groups_source']];$defs['br-d']=$d;
        $g['national_trial']['schema']='abcd-closure-v1';
        return WorldSeason::attachFixture($g,$defs,array_map(fn($c)=>$c['teams'],$defs),['max_matches'=>1673,'budget'=>8,'sized_contract'=>'abcd-2026-v1']);
    }
    public static function createABCDWithNordeste(int $id,string $mode='normal'):array {
        if(isset(Competition::catalog()['br-nordeste']))throw new RuntimeException('Identidade de copa já presente: revisar adaptador, não duplicar.');
        $g=self::createABCD($id,$mode);$defs=array_map(fn($p)=>$p['definition'],$g['world_reference']);$r=json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-nordeste-2026.json'),true,512,JSON_THROW_ON_ERROR);
        $defs['br-nordeste']=['id'=>'br-nordeste','name'=>'Copa do Nordeste','edition'=>2026,'country'=>'BR','scope'=>'regional','family'=>'br','tier'=>3,'internal_tier_basis'=>'simulation_compatibility_not_official_division','format'=>'nordeste','teams'=>array_merge(...$r['groups']),'groups'=>$r['groups'],'test_fixture'=>true,'verified_sources'=>[$r['rec'],$r['groups_source']]];
        return WorldSeason::attachFixture($g,$defs,array_map(fn($c)=>$c['teams'],$defs),['max_matches'=>1733,'budget'=>8,'sized_contract'=>'abcd-ne-2026-v1']);
    }
    public static function createWithCopaBrasil(int $id,string $mode='normal'):array {
        $g=self::createABCDWithNordeste($id,$mode);$defs=array_map(fn($p)=>$p['definition'],$g['world_reference']);
        $r=json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-copa-sports-2026.json'),true,512,JSON_THROW_ON_ERROR);
        if(isset($defs['br-copa']))throw new RuntimeException('Copa do Brasil já inscrita.');
        $defs['br-copa']=['id'=>'br-copa','name'=>'Copa do Brasil','edition'=>2026,'country'=>'BR','scope'=>'national','family'=>'br','tier'=>1,'internal_tier_basis'=>'simulation_compatibility_not_official_division','format'=>'copa_brasil','teams'=>array_column(CopaBrasilReference::reference()['participants'],'club'),'test_fixture'=>true,'verified_sources'=>[$r['rec'],$r['draw_source'],$r['rnc_source']],'sports_contract'=>$r,'reference_clubs'=>$r['reference_clubs']];
        return WorldSeason::attachFixture($g,$defs,array_map(fn($c)=>$c['teams'],$defs),['max_matches'=>1888,'budget'=>8,'sized_contract'=>'abcd-ne-cdb-2026-v1']);
    }
    public static function finish(array &$g):void {
        $t=$g['competitions']['br-b'];if(!$t['done'])throw new RuntimeException('Nacional incompleto.');
        foreach(['promoted'=>'br-a','relegated'=>'br-c'] as $field=>$target)foreach($t[$field] as $club)ExternalQualification::record($g,['source'=>'br-b','source_edition'=>$g['season'],'club'=>$club,'target'=>$target,'target_edition'=>$g['season']+1,'evidence'=>hash('sha256',json_encode([$t['initial_ranking'],$t[$field]]))]);
        if(isset($g['competition_results']['br-b'])){$r=&$g['competition_results']['br-b'];$r['promotion_merit']=in_array($g['club'],$t['promoted'],true);$r['access_pending']=$r['promotion_merit'];$r['relegation_pending']=in_array($g['club'],$t['relegated'],true);$r['promoted']=false;$r['relegated']=false;unset($r);}
    }
}
