<?php
declare(strict_types=1);
if(!in_array('--local-readonly',$argv,true))exit("Use --local-readonly. No writes or schema changes.\n");
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');require dirname(__DIR__).'/app/bootstrap.php';
if(config('APP_ENV')==='production'||config('DB_DATABASE')!=='rumohub'||!in_array(config('DB_HOST'),['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Somente banco local.');
$root=dirname(__DIR__);$db=Database::connect();$db->beginTransaction();$checks=0;$queries=0;$evidence=[];
function check(bool $v,string $why):void{global $checks;$checks++;if(!$v)throw new RuntimeException($why);}
try{
    $clubs=Competition::clubs();
    foreach(['br-al-2026.json','br-d-admission-2026-v2.json'] as $file){
        $value=json_decode(file_get_contents($root.'/database/competition-references/'.$file),true,512,JSON_THROW_ON_ERROR);
        $query=$db->prepare("SELECT JSON_EXTRACT(?, '$') AS document");$query->execute([json_encode($value,JSON_THROW_ON_ERROR)]);$queries++;
        $loaded=json_decode($query->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
        if($file==='br-al-2026.json'){
            foreach(array_keys($value['competitions']) as $id){
                $before=CompetitionParticipation::inspect($value,$id,2026,$clubs);$after=CompetitionParticipation::inspect($loaded,$id,2026,$clubs);
                check($before==$after,'admissão resiste à normalização JSON MySQL');check(!$after['playable'],'não ativa percurso por roundtrip');
            }
        }else{
            check(SerieDAdmission::evaluate($loaded,[],$clubs,[])['blocked'],'política bloqueada após roundtrip');
            check(array_sum($loaded['quotas'])===96,'quotas preservadas');
        }
        $evidence[]=['file'=>$file,'bytes'=>strlen(json_encode($loaded))];
    }
}finally{if($db->inTransaction())$db->rollBack();}
file_put_contents($root.'/Docs/mundializacao-v07/percurso-oficial/admission-mysql.json',json_encode(['checks'=>$checks,'select_queries'=>$queries,'dml'=>0,'ddl'=>0,'rh_access'=>false,'rollback'=>true,'documents'=>$evidence],JSON_PRETTY_PRINT));
echo "OK: $checks verificações MySQL somente leitura; nenhuma carreira alterada.\n";
