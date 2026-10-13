<?php
declare(strict_types=1);
/** Calendários de jogo com participantes reais; exceções do regulamento estão documentadas. */
final class InternationalCompetition {
    public static function create(array $c,array $teams): array {
        $groups=$c['groups'];
        if($teams!==$c['teams']){
            $assigned=[];foreach($groups as &$group){$group=array_values(array_intersect($group,$teams));$assigned=array_merge($assigned,$group);}unset($group);
            $new=array_values(array_diff($teams,$assigned));shuffle($new);
            foreach($groups as $i=>&$group)while(count($group)<count($c['groups'][$i]) && $new)$group[]=array_shift($new);unset($group);
            while($new){$sizes=array_map('count',$groups);$i=array_search(min($sizes),$sizes,true);$groups[$i][]=array_shift($new);}
        }
        $lots=$teams;shuffle($lots);
        $t=['id'=>$c['id'],'teams'=>$teams,'format'=>'international','phase'=>'liga','groups'=>$groups,'initial_groups'=>$groups,'matches'=>[],'phase_start'=>1,'phase_end'=>0,'done'=>false,'champion'=>null,'promoted'=>[],'relegated'=>[],'ties'=>[],'lots'=>array_flip($lots),'rounds'=>[],'titles'=>[]];
        if($c['id']==='ar-1'){self::argentineHalf($t,$groups,'liga');return $t;}
        Competition::leaguePhase($t,$groups,'liga',!in_array($c['id'],['uy-1','uy-2'],true));
        if($c['id']==='ar-2' && count($groups)===2){
            foreach($t['matches'] as &$m)$m['round']+=(int)($m['round']>=7)+(int)($m['round']>=24);unset($m);
            // Emparelhamentos interzonais do fixture AFA 2026, em ida e volta.
            $pairs=[['All Boys','Nueva Chicago'],['Ferro Carril Oeste','Atlanta'],['Deportivo Madryn','San Martín Tucumán'],['Chaco For Ever','Gimnasia Jujuy'],['Deportivo Morón','Almagro'],['Estudiantes Caseros','Chacarita Juniors'],['Racing Córdoba','San Martín San Juan'],['Los Andes','Temperley'],['Mitre Santiago del Estero','Güemes Santiago del Estero'],['Almirante Brown','Tristán Suárez'],['Ciudad de Bolívar','Agropecuario Argentino'],['Colón Santa Fe','Patronato'],['Central Norte Salta','Gimnasia y Tiro'],['Godoy Cruz','Deportivo Maipú'],['San Telmo','Quilmes'],['San Miguel','Colegiales'],['Defensores de Belgrano','Atlético Rafaela'],['Acassuso','Ferro Carril Midland']];
            if($teams!==$c['teams'])$pairs=array_map(fn($i)=>[$groups[0][$i],$groups[1][$i]],range(0,17));else$pairs=array_map(fn($p)=>[$p[0].'-AR',$p[1].'-AR'],$pairs);
            foreach($pairs as [$a,$b])foreach([[7,$a,$b],[25,$b,$a]] as [$r,$h,$v])$t['matches'][]=Schedule::match($r,$h,$v)+['phase'=>'liga','group'=>'cruzado'];
            $t['phase_end']=36;usort($t['matches'],fn($a,$b)=>$a['round']<=>$b['round']);
        }
        return $t;
    }
    private static function argentineHalf(array &$t,array $groups,string $phase): void {
        if(count($groups)!==2 || count($groups[0])!==15 || count($groups[1])!==15){Competition::leaguePhase($t,[$t['teams']],$phase,false);return;}
        Competition::leaguePhase($t,$groups,$phase,false);
        // A rodada de folga de cada grupo recebe um confronto interzonal; uma rodada extra completa 16 jogos.
        $start=$t['phase_start'];
        for($round=$start;$round<=$t['phase_end'];$round++){
            $busy=[];foreach($t['matches'] as $m)if($m['round']===$round)$busy=array_merge($busy,[$m['home'],$m['away']]);
            $a=array_values(array_diff($groups[0],$busy));$b=array_values(array_diff($groups[1],$busy));
            if($a&&$b)$t['matches'][]=Schedule::match($round,$a[0],$b[0])+['phase'=>$phase,'group'=>'cruzado'];
        }
        $extra=++$t['phase_end'];for($i=0;$i<15;$i++)$t['matches'][]=Schedule::match($extra,$groups[1][($i+1)%15],$groups[0][$i])+['phase'=>$phase,'group'=>'cruzado'];
        usort($t['matches'],fn($a,$b)=>$a['round']<=>$b['round']);
    }
    public static function ranking(array $t,array $teams,?string $phase=null): array {
        // Interzonais contam nos pontos do grupo, sem incluir o adversário na tabela.
        $matches=array_values(array_filter($t['matches'],fn($m)=>!$phase||$m['phase']===$phase));
        return array_column(Schedule::table($matches,$teams),'team');
    }
    private static function groupRanks(array $t,string $phase): array {return array_map(fn($group)=>self::ranking($t,$group,$phase),$t['groups']);}
    private static function annual(array $t): array {
        $copy=$t;$copy['matches']=array_values(array_filter($t['matches'],fn($m)=>in_array($m['phase'],['liga','clausura','intermedio','regular'],true)));
        return self::ranking($copy,$t['teams']);
    }
    private static function end(array &$t,string $champion,array $promoted=[]): void {$t['champion']=$champion;$t['promoted']=$promoted;$t['done']=true;}
    public static function next(array &$t): void {
        $id=$t['id'];$phase=$t['phase'];
        if(!isset($t['initial_ranking']))$t['initial_ranking']=self::ranking($t,$t['teams'],'liga');
        if($id==='bo-1'||$id==='py-2'){self::end($t,$t['initial_ranking'][0],$id==='py-2'?array_slice($t['initial_ranking'],0,2):[]);$t['relegated']=array_slice($t['initial_ranking'],-($id==='bo-1'?1:3));return;}
        if($id==='py-1'){
            if($phase==='liga'){$t['titles']['Apertura']=self::ranking($t,$t['teams'],'liga')[0];Competition::leaguePhase($t,[$t['teams']],'clausura',true);return;}
            $t['titles']['Clausura']=self::ranking($t,$t['teams'],'clausura')[0];self::end($t,$t['titles']['Clausura']);$t['initial_ranking']=self::annual($t);$t['relegated']=array_slice($t['initial_ranking'],-2);return;
        }
        if($id==='ar-1'){
            if(in_array($phase,['liga','clausura'],true)){
                $ranks=self::groupRanks($t,$phase);$qualified=[];
                foreach($ranks as $r)$qualified=array_merge($qualified,array_slice($r,0,8));
                $pairs=count($ranks)===2?array_map(fn($i)=>[$ranks[0][$i],$ranks[1][7-$i]],range(0,7)):Competition::seededPairs($qualified);
                $t['half']=$phase;Competition::knockout($t,$pairs,'oitavas',1);return;
            }
            $w=Competition::resolve($t);
            if(count($w)>1){Competition::knockout($t,Competition::seededPairs($w),count($w)===8?'quartas':(count($w)===4?'semifinal':'final'),1);return;}
            $t['titles'][$t['half']==='liga'?'Apertura':'Clausura']=$w[0];
            if($t['half']==='liga'){self::argentineHalf($t,$t['initial_groups'],'clausura');return;}
            $t['initial_ranking']=self::annual($t);self::end($t,$t['initial_ranking'][0]);$t['relegated']=array_slice($t['initial_ranking'],-2);return;
        }
        if($id==='ar-2'){
            if($phase==='liga'){
                $ranks=self::groupRanks($t,'liga');$t['zone_ranks']=$ranks;$t['relegated']=array_merge(array_slice($ranks[0],-2),array_slice($ranks[1],-2));
                Competition::knockout($t,[[$ranks[0][0],$ranks[1][0]]],'acesso',1);return;
            }
            $w=Competition::resolve($t);
            if($phase==='acesso'){
                $t['champion']=$w[0];$t['promoted']=[$w[0]];$t['runner_up']=array_values(array_diff($t['groups'][0],$w))[0];
                [$a,$b]=$t['zone_ranks'];$pairs=[];
                for($i=1;$i<=3;$i++){$pairs[]=[$a[$i],$b[8-$i]];$pairs[]=[$b[$i],$a[8-$i]];}$pairs[]=[$a[4],$b[4]];
                Competition::knockout($t,$pairs,'reduzido',1,'advantage');return;
            }
            if($phase==='reduzido'){$w=array_merge([$t['runner_up']],self::ranking($t,$w,'liga'));Competition::knockout($t,Competition::seededPairs($w),'quartas',2,'advantage');return;}
            if(count($w)>1){Competition::knockout($t,Competition::seededPairs(self::ranking($t,$w,'liga')),count($w)===4?'semifinal':'final',2,count($w)===4?'advantage':'penalties');return;}
            $t['promoted'][]=$w[0];$t['done']=true;return;
        }
        if($id==='uy-2'){
            if($phase==='liga'){$r=self::groupRanks($t,'liga');Competition::knockout($t,[[$r[0][0],$r[1][0]]],'competencia_final',1);return;}
            if($phase==='competencia_final'){$t['titles']['Competencia']=Competition::resolve($t)[0];Competition::leaguePhase($t,[$t['teams']],'regular',true);return;}
            if($phase==='regular'){$r=self::annual($t);$t['initial_ranking']=$r;$t['champion']=$r[0];$t['promoted']=array_slice($r,0,2);$t['relegated']=array_slice($r,-2);Competition::knockout($t,Competition::seededPairs(array_slice($r,2,4)),'semifinal',2);return;}
            $w=Competition::resolve($t);if(count($w)>1){Competition::knockout($t,[$w],'final',2);return;}$t['promoted'][]=$w[0];$t['done']=true;return;
        }
        if($id==='uy-1'){
            if($phase==='liga'){
                $r=self::ranking($t,$t['teams'],'liga');$t['titles']['Apertura']=$r[0];$groups=[[],[]];foreach($r as $i=>$club)$groups[$i%2][]=$club;Competition::leaguePhase($t,$groups,'intermedio',false);return;
            }
            if($phase==='intermedio'){$r=self::groupRanks($t,'intermedio');Competition::knockout($t,[[$r[0][0],$r[1][0]]],'intermedio_final',1);return;}
            if($phase==='intermedio_final'){$t['titles']['Intermedio']=Competition::resolve($t)[0];Competition::leaguePhase($t,[$t['teams']],'clausura',false);return;}
            if($phase==='clausura'){
                $t['titles']['Clausura']=self::ranking($t,$t['teams'],'clausura')[0];$t['initial_ranking']=self::annual($t);$t['annual_winner']=$t['initial_ranking'][0];$t['relegated']=array_slice($t['initial_ranking'],-3);
                $a=$t['titles']['Apertura'];$b=$t['titles']['Clausura'];
                if($a===$b){if($a===$t['annual_winner']){self::end($t,$a);return;}Competition::knockout($t,[[$t['annual_winner'],$a]],'final',2);return;}
                Competition::knockout($t,[[$a,$b]],'semifinal',1);return;
            }
            $w=Competition::resolve($t)[0];if($phase==='final'||$w===$t['annual_winner']){self::end($t,$w);return;}Competition::knockout($t,[[$t['annual_winner'],$w]],'final',2);return;
        }
        if($id==='bo-2'){
            if($phase==='liga'){
                $r=self::groupRanks($t,'liga');$groups=[];for($i=0;$i<count($r);$i+=2)$groups[]=array_merge(array_slice($r[$i],0,2),array_slice($r[$i+1],0,2));Competition::leaguePhase($t,$groups,'quadrangular',true);return;
            }
            if($phase==='quadrangular'){
                $r=self::groupRanks($t,'quadrangular');$qualified=[];$thirds=[];foreach($r as $group){$qualified=array_merge($qualified,array_slice($group,0,2));$thirds[]=$group[2];}
                $qualified=array_merge($qualified,array_slice(self::ranking($t,$thirds,'quadrangular'),0,6));shuffle($qualified);Competition::leaguePhase($t,array_chunk($qualified,4),'nacional',true);return;
            }
            if($phase==='nacional'){
                $r=self::groupRanks($t,'nacional');$qualified=[];$thirds=[];foreach($r as $group){$qualified=array_merge($qualified,array_slice($group,0,2));$thirds[]=$group[2];}
                $qualified=array_merge($qualified,array_slice(self::ranking($t,$thirds,'nacional'),0,4));Competition::knockout($t,Competition::seededPairs(self::ranking($t,$qualified,'nacional')),'oitavas',2);return;
            }
            $w=Competition::resolve($t);if(count($w)>1){Competition::knockout($t,Competition::seededPairs($w),count($w)===8?'quartas':(count($w)===4?'semifinal':'final'),2);return;}self::end($t,$w[0],$w);return;
        }
        throw new LogicException('Formato internacional desconhecido.');
    }
}
