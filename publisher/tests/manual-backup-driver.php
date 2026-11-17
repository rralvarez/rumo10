<?php
declare(strict_types=1);
// Test adapter. GitHub requests use fixture responses; receiver, archive verification and hooks run normally.
require dirname(__DIR__).'/lib/DrainPublisher.php';
require dirname(__DIR__).'/lib/GitHub.php';

use Rumo\Publisher\{DrainPublisher,GitHub,MainAutomation,RequestDrain};
use function Rumo\Publisher\{digest,json,need};

if(in_array($argv[1]??'',['fence','preflight','maintenance','backup','migrate','smoke'],true)){
    $mode=$argv[1];
    $c=json(file_get_contents($argv[2]));
    $q=json(stream_get_contents(STDIN));
    file_put_contents($c['trace'],$mode.':'.($q['action']??'default')."\n",FILE_APPEND);
    $gate=new RequestDrain($c['request_gate']['root']);
    $r=['ok'=>true];
    switch($mode){
        case 'fence':$r['legacy_publishers_fenced']=true;break;
        case 'preflight':$r+=['legacy_publishers_fenced'=>true,'source_unchanged'=>true];break;
        case 'maintenance':
            $gateState=$gate->closed() && is_file($c['control'].'/deployment.lock');
            $r+=['http_status'=>$gateState?503:200,'writes_blocked'=>$gateState,'inflight_writers'=>0];
            break;
        case 'backup':
            if(($q['action']??'')==='preflight'){
                need(!$gate->closed(),'Database preflight ran behind a closed gate');
                need(!is_file($c['fail_backup']),'Injected database preflight failure');
                $r+=['preflight'=>true,'full_data_tested'=>true];
            }else{
                need($gate->closed(),'Database backup requires a closed gate');
                $db=new SQLite3($c['db'],SQLITE3_OPEN_READONLY);
                $file=$q['backup'].'/database.sqlite';
                $copy=new SQLite3($file);
                need($db->backup($copy) && $copy->querySingle('SELECT count(*) FROM saves')===1,'Fixture database backup failed');
                $copy->close();$db->close();
                $r+=['created_at'=>time(),'consistent'=>true,'attempt'=>$q['attempt'],'files'=>[$file=>digest($file)],
                    'dump_sha256'=>digest($file),'artifact_id'=>$q['candidate']['artifact_id'],'package_sha256'=>$q['candidate']['package_sha256']];
            }
            break;
        case 'migrate':$r+=['compatible_expansion'=>true,'versions'=>[]];break;
        case 'smoke':break;
        default:throw new RuntimeException('Unknown fixture hook');
    }
    echo json_encode($r,JSON_THROW_ON_ERROR);
    exit;
}
if(($argv[1]??'')==='init'){
    RequestDrain::provision($argv[2]);
    $db=new SQLite3($argv[3]);$db->exec('CREATE TABLE saves(id INT PRIMARY KEY); INSERT INTO saves VALUES (1)');
    exit;
}
final class FixtureGitHub extends GitHub {
    public function __construct(array $c,private array $fixture){parent::__construct($c);}
    protected function request(string $url,bool $auth,int $limit,?string $out=null): array {
        self::checkUrl($url,$auth);
        $path=parse_url($url,PHP_URL_PATH);
        $f=$this->fixture;
        if(!$auth && $out!==null){
            need(copy($f['zip'],$out),'Fixture ZIP copy failed');
            return ['status'=>200];
        }
        if(str_ends_with($path,'/zip'))return ['status'=>302,'headers'=>['location'=>'https://fixture.blob.core.windows.net/candidate']];
        $d=match(true){
            str_ends_with($path,'/git/ref/heads/main')=>['object'=>['sha'=>$f['run']['head_sha']]],
            str_ends_with($path,'/compare/'.$f['parent'].'...'.$f['run']['head_sha'])=>['status'=>'ahead','merge_base_commit'=>['sha'=>$f['parent']]],
            str_ends_with($path,'/workflows/7/runs')=>['workflow_runs'=>[$f['run']]],
            str_ends_with($path,'/contents/.github/workflows/production-consolidated.yml')=>['encoding'=>'base64','content'=>base64_encode('pinned workflow fixture')],
            str_ends_with($path,'/jobs')=>['jobs'=>$f['jobs'],'total_count'=>count($f['jobs'])],
            str_ends_with($path,'/runs/'.$f['run']['id'].'/artifacts')=>['artifacts'=>[$f['artifact']],'total_count'=>1],
            str_ends_with($path,'/runs/'.$f['run']['id'])=>$f['run'],
            str_ends_with($path,'/artifacts/'.$f['artifact']['id'])=>$f['artifact'],
            str_ends_with($path,'/commits/'.$f['run']['head_sha'].'/pulls')=>[$f['pr']],
            str_ends_with($path,'/pulls/'.$f['pr']['number'])=>$f['pr'],
            str_ends_with($path,'/commits/'.$f['run']['head_sha'])=>$f['commit'],
            str_ends_with($path,'/commits/'.$f['pr']['head']['sha'].'/status')=>['sha'=>$f['pr']['head']['sha'],'statuses'=>$f['statuses']],
            str_ends_with($path,'/rulesets/8')=>['id'=>8,'target'=>'branch','enforcement'=>'active','bypass_actors'=>[],
                'conditions'=>['ref_name'=>['include'=>['refs/heads/main'],'exclude'=>[]]],
                'rules'=>[['type'=>'pull_request'],['type'=>'required_status_checks','parameters'=>['strict_required_status_checks_policy'=>true,
                    'required_status_checks'=>[['context'=>'rumo10/manual-backup','integration_id'=>11]]]]]],
            str_ends_with($path,'/issues/comments/21')=>$f['comment'],
            str_ends_with($path,'/issues/8/comments')=>[$f['comment']],
            default=>throw new RuntimeException('Unmapped fixture API request '.$path)
        };
        return ['status'=>200,'body'=>json_encode($d,JSON_THROW_ON_ERROR)];
    }
    protected function report(array $run,string $state,string $reason):void {}
}
try{
    $c=json(file_get_contents($argv[2]));
    $fixture=json(file_get_contents($argv[3]));
    $source=new FixtureGitHub($c,$fixture);
    $p=new class($c,$source) extends DrainPublisher {
        public function __construct(private array $config,private GitHub $fixture){parent::__construct($config);}
        protected function source(): GitHub {return $this->fixture;}
        public function verifyPublicAvailability(): void {MainAutomation::stable($this->config);}
        protected function checkpoint(string $point):void {
            if($point==='after-promotion-0' && is_file($this->config['fault']))exit(77);
        }
    };
    $p->acquire();
    $out=match($argv[1]){
        'authorize'=>$source->assertApprovedMerge(['commit'=>$fixture['run']['head_sha'],'run_id'=>$fixture['run']['id'],
            'artifact_id'=>$fixture['artifact']['id']],$fixture['pr']['number'])->evidence,
        'lock'=>sleep(3),
        'recover'=>$p->recover(),
        'pilot'=>$source->automatic($p,MainAutomation::UI001),
        'automatic'=>$source->automatic($p),
    };
    echo json_encode($out,JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
