<?php
declare(strict_types=1);
use Rumo\Publisher\{GitHub,MainAutomation,Publisher,RequestDrain,UpdatePolicy};
use function Rumo\Publisher\{record,need};
require_once dirname(__DIR__).'/lib/Publisher.php';
require_once dirname(__DIR__).'/lib/GitHub.php';

$passed=0;
function check(bool $ok,string $test):void {
    global $passed; need($ok,$test); ++$passed;
}
function rejects(callable $fn,string $message):bool {
    try{$fn();}catch(RuntimeException $e){return str_contains($e->getMessage(),$message);}return false;
}
$repo='rralvarez/rumo10';
$sha=str_repeat('a',40);$parent=str_repeat('b',40);$head=str_repeat('c',40);
$pr=['number'=>4,'state'=>'closed','merged'=>true,'merged_at'=>'2026-11-13T17:35:20Z',
     'merged_by'=>['id'=>35331184],'base'=>['repo'=>['full_name'=>$repo],'ref'=>'main'],
     'merge_commit_sha'=>$sha,'head'=>['sha'=>$head]];
$commit=['sha'=>$sha,'parents'=>[['sha'=>$parent],['sha'=>$head]]];
$cfg=['owner_id'=>35331184];
MainAutomation::merge($cfg,$repo,$sha,4,$pr,$commit);check(true,'Authorized owner merge');
check(rejects(fn()=>MainAutomation::merge($cfg,$repo,$sha,4,array_replace($pr,['merged_by'=>['id'=>3]]),$commit),'approved owner'),'Foreign merger');
check(rejects(fn()=>MainAutomation::merge($cfg,$repo,$sha,4,$pr,['sha'=>$sha,'parents'=>[['sha'=>$parent]]]),'approved owner'),'Direct push / squash');
check(rejects(fn()=>MainAutomation::merge($cfg,$repo,$sha,4,$pr,['sha'=>$sha,'parents'=>[['sha'=>$parent],['sha'=>$sha]]]),'approved owner'),'Mismatched PR parent');
$base=['files'=>['app/view.php'=>'old','app/Repository.php'=>'old','database/009.sql'=>'old']];
$policy=['schema'=>'rumo-update-policy-v1','protected_contract_paths'=>['app/Repository.php'],'approved_incremental_migrations'=>['database/009.sql'=>'new']];
$jobs=[['name'=>'compatibility','status'=>'completed','conclusion'=>'success']];
$v=['commit'=>$sha,'run'=>['id'=>12],'artifact_id'=>123,'package_sha256'=>str_repeat('e',64),
    'envelope'=>['release_channel'=>'main','functional_commit'=>$sha]];
$assess=static function(array $new,?string $from=null,?string $to=null,array $extra=[])use($base,$policy,$jobs):array{
    $a=UpdatePolicy::assess($base,['files'=>$new],$policy+$extra,$jobs,$from,$to);
    $a['requires_full_drain']=true;return $a;
};
foreach(['app/view.php','public/assets/visual.css'] as $path){
    MainAutomation::change($cfg,$v,$assess(array_replace($base['files'],[$path=>'new'])));
    check(true,'Two distinct ordinary changes, no SHA permit');
}
// Class B with database migration is allowed without sensitive permit
MainAutomation::change($cfg,$v,$assess(array_replace($base['files'],['database/009.sql'=>'new'])));
check(true,'Class B release with migration allowed without manual SHA permit');

check(rejects(fn()=>MainAutomation::change($cfg,$v,$assess(array_replace($base['files'],['publisher/Core.php'=>'new']))),'Infrastructure change'),'Infrastructure blocked');
check(rejects(fn()=>MainAutomation::change($cfg,$v,$assess(array_replace($base['files'],['public/.htaccess'=>'new']))),'Infrastructure change'),'htaccess blocked');
check(rejects(fn()=>MainAutomation::change($cfg,$v,$assess(array_diff_key($base['files'],['app/view.php'=>true]))),'Class C'),'Deletion blocked');

$proof=['created_at'=>500,'artifact_id'=>123,'package_sha256'=>$v['package_sha256'],
    'consistent'=>true,'source_uuid'=>'s',
    'dump_sha256'=>str_repeat('d',64),'files'=>['dump'=>str_repeat('d',64)]];
MainAutomation::proof($proof,$v,2299);check(true,'Proof fresh on single server');
check(rejects(fn()=>MainAutomation::proof($proof,$v,2300),'Fresh consistent'),'Proof expires at 30 minutes');
check(rejects(fn()=>MainAutomation::proof(array_replace($proof,['files'=>[]]),$v,2299),'Valid backup dump'),'Missing dump file');
check(rejects(fn()=>MainAutomation::proof($proof,array_replace($v,['artifact_id'=>124]),2299),'Fresh consistent'),'Wrong backup artifact');
$root=sys_get_temp_dir().'/r10-auto-'.bin2hex(random_bytes(8));mkdir($root,0700);RequestDrain::provision($root.'/gate');
try {
    $config=['private'=>$root,'control'=>$root,'request_gate'=>['root'=>$root.'/gate']];
    $run=['id'=>12,'head_sha'=>$sha];$artifact=['id'=>123];
    record($root.'/installed.json',['commit'=>$sha,'artifact_id'=>123,'run_id'=>12,'package_sha256'=>$v['package_sha256']]);
    record($root.'/journal.json',['state'=>'blocked','stage'=>'x','candidate'=>$v]);
    record($root.'/drain-session.json',['state'=>'validated','stage'=>'x']);
    check(!MainAutomation::outcome($config,$run,$artifact),'Receipt alone not success');
    record($root.'/journal.json',['state'=>'validated','stage'=>'x','candidate'=>$v]);
    $gate=new RequestDrain($root.'/gate');$gate->close(str_repeat('a',32));
    check(!MainAutomation::outcome($config,$run,$artifact),'Closed gate not success');
    $gate->drain(1);$gate->release();
    check(MainAutomation::outcome($config,$run,$artifact),'Validated real receipt and open gate');
    check(!MainAutomation::outcome($config,$run,['id'=>124]),'Different artifact not success');
    $cfg2=$config+['token_file'=>$root.'/fake-token','repository'=>$repo,'workflow_id'=>7,'project'=>'rumo10',
        'main_automation'=>['owner_id'=>35331184]];
    file_put_contents($cfg2['token_file'],'synthetic');chmod($cfg2['token_file'],0600);
    $source=new class($cfg2,$sha) extends GitHub {
        public array $reported=[];
        private string $sha;
        public function __construct(array $cfg,string $sha){parent::__construct($cfg);$this->sha=$sha;}
        protected function request(string $url,bool $auth,int $limit,?string $out=null):array{
            $payload=str_contains($url,'/git/ref/')?['object'=>['sha'=>$this->sha]]:
                ['workflow_runs'=>[['id'=>12,'head_sha'=>$this->sha,'head_branch'=>'main','event'=>'push',
                  'repository'=>['full_name'=>'rralvarez/rumo10'],'workflow_id'=>7,'status'=>'completed','conclusion'=>'success']]];
            need($out===null && !str_contains($url,'/artifacts'),'Successful receipt must not download or run again');
            return ['status'=>200,'body'=>json_encode($payload,JSON_THROW_ON_ERROR)];
        }
        protected function report(array $run,string $state,string $reason):void{$this->reported[]=[$state,$reason];}
    };
    $unused=(new ReflectionClass(Publisher::class))->newInstanceWithoutConstructor();
    $out=$source->automatic($unused);
    check(($out['already_installed']??false)===true && $source->reported===[['success','Recibo UOL validado; artefato 123']],
        'Rerun reports validated production receipt without apply or download');
    unlink($cfg2['token_file']);
}finally{
    foreach(['installed.json','journal.json','drain-session.json','gate/writers.lock','gate/identity.json'] as $f)unlink($root.'/'.$f);
    rmdir($root.'/gate');rmdir($root);
}
echo "$passed checks passed\n";

