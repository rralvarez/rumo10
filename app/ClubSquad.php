<?php
declare(strict_types=1);
final class ClubSquad {
    public static function catalog():array {
        if(RumoHub::enabled())return RumoHub::catalog()['player_catalog'];
        static $index;return $index??=json_decode(file_get_contents(dirname(__DIR__).'/database/player-catalog.json'),true,512,JSON_THROW_ON_ERROR);
    }
    public static function players(string $club):array {
        if(RumoHub::enabled() && empty(Competition::clubs()[$club]['game_only']))return RumoHub::players($club);
        // Somente o elenco solicitado; não carregar dezenas de milhares de atletas no universo da carreira.
        static $cache=[];if(isset($cache[$club]))return $cache[$club];
        $entry=self::catalog()['clubs'][$club]??null;
        if($entry) {
            if(!preg_match('/^[a-f0-9]{20}\.json$/',$entry['file']))throw new LogicException('Arquivo de elenco inválido.');
            $data=json_decode(file_get_contents(dirname(__DIR__).'/database/rosters/'.$entry['file']),true,512,JSON_THROW_ON_ERROR);
            if($data['club']!==$club)throw new LogicException('Elenco associado ao clube errado.');
            $players=$data['players'];
        } else {
            $players=[];$positions=['goleiro','goleiro','zagueiro','zagueiro','zagueiro','zagueiro','lateral','lateral','lateral','lateral','volante','volante','volante','meia','meia','meia','meia','ponta','ponta','ponta','ponta','centroavante','centroavante','centroavante'];
            foreach($positions as $i=>$position){$hash=hash('sha256',$club.'|'.$i);$players[]=['id'=>'fictional:'.substr($hash,0,20),'name'=>['Lucas','Rafael','Bruno','Diego','Pedro','Caio'][$i%6].' '.['Silva','Costa','Santos','Rocha'][$i%4],'position'=>$position,'origin'=>'fictional','overall'=>40+hexdec(substr($hash,0,2))%16,'overall_origin'=>'estimated_rumo10_v1','age_at_import'=>18+$i%16,'source'=>null,'rating_source'=>null];}
        }
        if(count($cache)>=4)array_shift($cache);$cache[$club]=$players;return $players;
    }
    public static function render(array $g):void {
        echo '<section class="panel squad-reference"><p class="tag">SEU CLUBE</p><h1>Companheiros de elenco</h1><p>';ClubBadge::render($g['club']);echo ' <strong>'.e(ClubBadge::name($g['club'])).'</strong></p>';
        $players=self::players($g['club']);$entry=self::catalog()['clubs'][$g['club']]??null;
        $checked=self::catalog()['summary']['checked']??'2026-10-14';$checked=implode('/',array_reverse(explode('-',$checked)));
        echo '<p class="subtle">Elenco de referência consultado em '.e($checked).'. '.e($entry['real']??0).' jogadores reais · '.e($entry['fictional']??count($players)).' fictícios.</p><p class="subtle">Notas publicadas usam EA SPORTS FC 27; estimativas usam o modelo do Rumo à Camisa 10. A nota e a origem de cada atleta estão identificadas.</p>';
        $order=['goleiro'=>0,'zagueiro'=>1,'lateral'=>2,'volante'=>3,'meia'=>4,'ponta'=>5,'centroavante'=>6];usort($players,fn($a,$b)=>($order[$a['position']]<=>$order[$b['position']])?:($b['overall']<=>$a['overall'])?:strcmp($a['name'],$b['name']));
        echo '<div class="table-responsive"><table class="table squad-table"><thead><tr><th>Jogador</th><th>Pos.</th><th>Overall</th></tr></thead><tbody>';
        foreach($players as $p) {
            $positionLabel=['goleiro'=>'GOL','zagueiro'=>'ZAG','lateral'=>'LAT','volante'=>'VOL','meia'=>'MEI','ponta'=>'PON','centroavante'=>'CA'][$p['position']];
            if($p['overall_origin']!=='published_ea_fc27' && isset($p['source_position']))$positionLabel=['D'=>'DEF','M'=>'MEI','F'=>'ATA'][$p['source_position']]??$positionLabel;
            $ratingLabel=match($p['overall_origin']){'published_ea_fc27'=>'Publicado · FC 27','manual_rumohub'=>'Ajuste manual',default=>'Estimado'};
            echo '<tr><td><strong>'.e($p['name']).'</strong><small>'.($p['origin']==='real'?'Real':'Fictício').(!empty($p['reference_only'])?' · referência licenciada EA':'').'</small></td><td>'.e($positionLabel).'</td><td><b>'.e($p['overall']).'</b><small>'.e($ratingLabel).'</small></td></tr>';
        }
        echo '</tbody></table></div><p class="subtle">Referência inicial dos companheiros. Estatísticas de partidas ainda não estão disponíveis nesta página.</p><a class="btn btn-primary" href="/jogador">Voltar ao meu jogador</a></section>';
    }
}
