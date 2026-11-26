<?php
declare(strict_types=1);
namespace Rumo\Publisher;
require_once __DIR__.'/GitHub.php';

final class FenceCheckFailed extends \RuntimeException {
    public function __construct(
        public readonly string $reason,
        public readonly bool $retryable,
        array $evidence = [],
    ) {
        $parts = [$reason];
        foreach ($evidence as $key => $value) {
            // The hook receipt may reach private automation logs. Never echo API bodies,
            // arbitrary exception text, URLs, tokens or unvalidated header values.
            if (preg_match('/^[A-Za-z0-9_.\/:-]{1,180}$/D', (string)$value)===1)
                $parts[]=$key.'='.$value;
        }
        parent::__construct(implode(' ', $parts));
    }
}

final class FenceReader extends GitHub {
    private string $repo;
    public function __construct(array $c, private readonly ?float $deadline = null) {
        parent::__construct($c);
        $this->repo=$c['repository'];
    }
    protected function requestDeadline(): ?float { return $this->deadline; }
    public function get(string $path): array {
        $prefix='/repos/'.$this->repo.'/';
        need(str_starts_with($path,$prefix),'Fence path outside configured repository');
        $operation=explode('?',substr($path,strlen($prefix)),2)[0];
        $evidence=['repo'=>$this->repo,'operation'=>$operation];
        try {
            $r=$this->request('https://api.github.com'.$path,true,4*1024*1024);
        } catch (\RuntimeException $e) {
            $retry=$e->getMessage()==='HTTPS GET deadline exceeded' ||
                preg_match('/\(curl (6|7|18|28|52|55|56)\)$/D',$e->getMessage())===1;
            throw new FenceCheckFailed($retry?'api_unavailable':'api_transport_rejected',$retry,$evidence);
        }
        $status=$r['status'];
        if ($status!==200) {
            $headers=$r['headers'];
            $rateLimited=$status===403 &&
                (($headers['x-ratelimit-remaining']??null)==='0' || isset($headers['retry-after']));
            $retry=in_array($status,[408,429,500,502,503,504],true)||$rateLimited;
            throw new FenceCheckFailed($retry?'api_unavailable':'api_rejected',$retry,$evidence+['http'=>$status]);
        }
        try {return json($r['body']);}
        catch (\RuntimeException|\JsonException) {throw new FenceCheckFailed('invalid_api_payload',false,$evidence);}
    }
}

final class LegacyFence {
    public const HOOK_BUDGET_SECONDS=65;
    private const RETRY_DELAY_SECONDS=[1,3];

    // Fail closed on every observation. Restart from the first repo after transient failure.
    public static function verifyWithRetry(array $profile, callable $get, float $deadline): array {
        foreach ([...self::RETRY_DELAY_SECONDS,0] as $delay) {
            try {return self::verify($profile,$get);}
            catch (FenceCheckFailed $failure) {
                if (!$failure->retryable || $delay===0 || microtime(true)+$delay>=$deadline)
                    throw $failure;
                sleep($delay);
            }
        }
        throw new \LogicException('Fence retry schedule is empty');
    }

    private static function check(bool $ok, string $reason, array $evidence = [], bool $retryable = false): void {
        if (!$ok) throw new FenceCheckFailed($reason,$retryable,$evidence);
    }

    /** Read-only observation, not a remote lock. The operator fences manual channels. */
    public static function verify(array $profile, callable $get): array {
        self::check(($profile['manual_channels_fenced']??false)===true,'manual_channels_unfenced');
        $repos=$profile['repositories']??[];
        $names=array_keys($repos);
        sort($names);
        self::check($names===['rralvarez/rumo10','rralvarez/rumohub'],'repository_inventory_invalid');
        $proof=[];
        foreach ($repos as $repo=>$cfg) {
            $base='/repos/'.$repo;
            $scope=['repo'=>$repo];
            $list=[];$paths=[];$count=null;
            for ($page=1;$page<=10;$page++) {
                $v=$get($repo,$base.'/actions/workflows?per_page=100&page='.$page);
                self::check(is_int($v['total_count']??null)&&$v['total_count']>=0,'workflow_inventory_invalid',$scope);
                $count??=$v['total_count'];
                self::check($v['total_count']===$count,'inventory_changed',$scope,true);
                $chunk=$v['workflows']??null;
                self::check(is_array($chunk),'workflow_inventory_invalid',$scope);
                foreach ($chunk as $w) {
                    self::check(is_int($w['id']??null)&&$w['id']>0 &&
                        is_string($w['path']??null)&&is_string($w['state']??null),'workflow_inventory_invalid',$scope);
                    self::check(!isset($list[$w['id']])&&!isset($paths[$w['path']]),'inventory_changed',$scope,true);
                    $list[$w['id']]=$w;
                    $paths[$w['path']]=true;
                }
                if (count($list)===$count) break;
                self::check(count($list)<$count&&count($chunk)===100,'workflow_inventory_incomplete',$scope);
            }
            self::check(count($list)===$count,'workflow_inventory_incomplete',$scope);
            $legacy=[];
            foreach ($list as $w) {
                $path=$w['path'];
                $workflow=$scope+['workflow'=>$path,'state'=>$w['state']];
                if (in_array($path,['.github/workflows/production.yml','.github/workflows/production-ftps.yml'],true)) {
                    self::check($w['state']==='disabled_manually','legacy_workflow_not_disabled',$workflow);
                    $legacy[]=$path;
                } elseif ($w['state']==='active') {
                    $pin=$cfg['approved_active'][$path]??null;
                    self::check(is_array($pin)&&preg_match('/^[a-f0-9]{40}$/D',$pin['commit']??'')===1&&
                        preg_match('/^[a-f0-9]{64}$/D',$pin['sha256']??'')===1,'workflow_unapproved',$workflow);
                    $branch=$pin['branch']??$cfg['branch'];
                    $ref=$get($repo,$base.'/git/ref/heads/'.rawurlencode($branch));
                    $actual=$ref['object']['sha']??'';
                    $expected=($pin['track_head']??false)?$actual:$pin['commit'];
                    self::check(preg_match('/^[a-f0-9]{40}$/D',$expected)===1&&$actual===$expected,
                        'control_branch_moved',$workflow+['branch'=>$branch,'expected'=>$expected,'observed'=>$actual]);
                    $file=$get($repo,$base.'/contents/'.$path.'?ref='.$expected);
                    self::check(($file['encoding']??'')==='base64'&&is_string($file['content']??null),
                        'workflow_content_unavailable',$workflow+['commit'=>$expected]);
                    $bytes=base64_decode(preg_replace('/\s+/','',$file['content']),true);
                    $observed=$bytes===false?'invalid_base64':hash('sha256',$bytes);
                    self::check($observed===$pin['sha256'],'workflow_hash_changed',
                        $workflow+['commit'=>$expected,'expected'=>$pin['sha256'],'observed'=>$observed]);
                } else {
                    self::check(in_array($w['state'],['disabled_manually','disabled_inactivity','deleted'],true),
                        'workflow_state_unknown',$workflow);
                }
            }
            self::check(count($legacy)===2,'legacy_workflow_inventory_missing',$scope);
            foreach (['in_progress','queued','requested','waiting','pending'] as $status) {
                $v=$get($repo,$base.'/actions/runs?status='.$status.'&per_page=100');
                $total=$v['total_count']??null;
                $runs=$v['workflow_runs']??null;
                self::check(is_int($total)&&$total>=0&&is_array($runs),'run_inventory_invalid',$scope+['state'=>$status]);
                $detail=$scope+['state'=>$status,'count'=>$total];
                if (is_int($runs[0]['id']??null)) $detail['run']=$runs[0]['id'];
                self::check($total===0&&$runs===[],'workflow_busy',$detail,true);
            }
            $proof[$repo]=['legacy_disabled'=>true,'pending_runs'=>0,'workflow_count'=>count($list)];
        }
        return ['ok'=>true,'legacy_publishers_fenced'=>true,'observation_only'=>true,
            'operator_manual_fence_required'=>true,'repositories'=>$proof];
    }
}
