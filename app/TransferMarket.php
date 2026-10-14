<?php
declare(strict_types=1);
/** Critérios de jogo, não regras reais de contratação ou visto. */
final class TransferMarket {
    public const COUNTRIES=['EN'=>'Inglaterra','ES'=>'Espanha','DE'=>'Alemanha','IT'=>'Itália','FR'=>'França','PT'=>'Portugal','NL'=>'Países Baixos','BE'=>'Bélgica','TR'=>'Turquia','SC'=>'Escócia','AR'=>'Argentina','UY'=>'Uruguai','PY'=>'Paraguai','BO'=>'Bolívia','BR'=>'Brasil','US'=>'Estados Unidos','CA'=>'Canadá','MX'=>'México','CR'=>'Costa Rica','HN'=>'Honduras','GT'=>'Guatemala','SV'=>'El Salvador','NO'=>'Noruega','DK'=>'Dinamarca','GR'=>'Grécia','CY'=>'Chipre','RU'=>'Rússia','SA'=>'Arábia Saudita','EC'=>'Equador','CO'=>'Colômbia','CZ'=>'Tchéquia','PL'=>'Polônia','HU'=>'Hungria','HR'=>'Croácia'];
    public static function country(string $club):string {return Competition::clubs()[$club]['country']??'BR';}
    public const EXTRA_COUNTRIES=['NI'=>'Nicarágua','PA'=>'Panamá','HT'=>'Haiti','JM'=>'Jamaica','DO'=>'República Dominicana','SR'=>'Suriname','CL'=>'Chile','PE'=>'Peru','VE'=>'Venezuela','GY'=>'Guiana','ZA'=>'África do Sul','NG'=>'Nigéria','GH'=>'Gana','KE'=>'Quênia','MA'=>'Marrocos','EG'=>'Egito','DZ'=>'Argélia','TN'=>'Tunísia','TZ'=>'Tanzânia','CD'=>'RD Congo','AO'=>'Angola','CI'=>'Costa do Marfim','ZM'=>'Zâmbia','UG'=>'Uganda','RW'=>'Ruanda','CM'=>'Camarões','SN'=>'Senegal','ML'=>'Mali','ET'=>'Etiópia','LY'=>'Líbia','AT'=>'Áustria','AZ'=>'Azerbaijão','CH'=>'Suíça','LI'=>'Liechtenstein','UA'=>'Ucrânia','RS'=>'Sérvia','JP'=>'Japão','IL'=>'Israel','BG'=>'Bulgária','SE'=>'Suécia'];
    public const EUROPE=['EN','ES','DE','IT','FR','PT','NL','BE','TR','SC','NO','DK','GR','CY','CZ','PL','HU','HR','RU','AT','AZ','CH','LI','UA','RS','IL','BG','SE'];
    public static function profile(array $g):array {
        $summary=Game::summary($g);$technical=array_sum(array_column($g['skills'],'value'))/9;
        $baseline=$g['season_skill_baseline']??null;
        if($baseline===null){$baseline=46.111111111;foreach(Rules::HANDICAPS as $h)if($h['nome']===$g['handicap'])foreach($h['efeitos'] as $key=>$delta)if(in_array($key,Rules::SKILLS,true))$baseline+=$delta/9;}
        $growth=max(0,$technical-$baseline);
        $abroad=$summary['games']>=6 && $summary['minutes']>=360 && $summary['average']>=6.5 && $technical>=50 && ($growth>=.75 || $technical>=58);
        return $summary+['technical'=>$technical,'growth'=>$growth,'abroad'=>$abroad];
    }
    public static function pool(array $g):array {
        $p=self::profile($g);$members=$g['next_memberships']??$g['memberships'];$origin=self::country($g['club']);$domestic=[];$foreign=[];
        $currentTier=1;foreach(Competition::catalog() as $id=>$c)if(in_array($g['club'],$members[$id]??[],true))$currentTier=max($currentTier,$c['tier']);
        foreach(Competition::clubs() as $club=>$data) {
            if($club===$g['club'])continue;$competitions=[];$tier=1;
            foreach($data['competitions'] as $id)if(in_array($club,$members[$id]??[],true)){$competitions[]=$id;$tier=max($tier,Competition::catalog()[$id]['tier']);}
            // Acesso/rebaixamento pode mover o clube para outra divisão nesta janela.
            if(!$competitions)foreach($members as $id=>$teams)if(in_array($club,$teams,true)){$competitions[]=$id;$tier=max($tier,Competition::catalog()[$id]['tier']);}
            if(!$competitions)continue;
            $country=self::country($club);$overall=$g['next_club_strengths'][$club]??TeamStrength::initial($club);$outside=$country!==$origin;
            if($outside) {
                if(!$p['abroad'])continue;
                $required=max(50,$overall-14);
                if($p['technical']<$required || ($overall>=85 && $p['average']<7))continue;
                if(in_array($country,self::EUROPE,true) && ($p['average']<6.7 || $p['minutes']<600))continue;
                $weight=max(15,min(70,(int)round(25+($p['average']-6.5)*20+$p['growth']*3)));
            } else {
                if($tier>$currentTier+1 || ($tier>$currentTier && ($p['average']<6 || $p['technical']<45)))continue;
                $weight=$tier>$currentTier?max(5,(int)(($p['average']-5)*20)):30;
            }
            usort($competitions,fn($a,$b)=>Competition::catalog()[$b]['tier']<=>Competition::catalog()[$a]['tier']);
            $offer=['club'=>$club,'tier'=>$tier,'peso'=>$weight,'country'=>$country,'competition'=>$competitions[0],'overall'=>round($overall,1),'international'=>$outside,'reason'=>$outside?'Seu desempenho e evolução chamaram atenção no exterior.':'O clube procura um jogador com o seu perfil.'];
            if($outside)$foreign[]=$offer;else$domestic[]=$offer;
        }
        shuffle($domestic);shuffle($foreign);
        // A lista tem diversidade de mercados; não deixa as centenas de clubes locais diluírem o exterior.
        $chosenForeign=[];$seen=[];
        foreach($foreign as $offer)if(!isset($seen[$offer['country']])){$chosenForeign[]=$offer;$seen[$offer['country']]=true;if(count($chosenForeign)===4)break;}
        foreach($foreign as $offer)if(count($chosenForeign)<min(4,count($foreign)) && !in_array($offer['club'],array_column($chosenForeign,'club'),true))$chosenForeign[]=$offer;
        $pool=array_merge(array_slice($domestic,0,8-count($chosenForeign)),$chosenForeign);
        if(!$pool)throw new LogicException('Nenhum clube elegível para a janela de mercado.');
        return $pool;
    }
    public static function describe(array $offer):void {
        $country=$offer['country']??self::country($offer['club']);
        echo '<p class="offer-destination">';ClubBadge::render($offer['club']);echo ' <strong>'.e((self::COUNTRIES+self::EXTRA_COUNTRIES)[$country]??$country).'</strong>';
        if(isset($offer['competition']))echo ' · '.e(Competition::catalog()[$offer['competition']]['name']);
        echo '</p>';if(!empty($offer['international']))echo '<p>'.e($offer['reason']).'</p>';
    }
}
