<?php
declare(strict_types=1);
namespace Rumo\Publisher;
require_once __DIR__.'/Core.php';
require_once __DIR__.'/Zip.php';
require_once __DIR__.'/MainAutomation.php';

class GitHub {
    private array $c;
    private string $token;
    public function __construct(array $c) {
        $this->c=$c;
        $p=$c['token_file'];
        need(is_file($p) && !is_link($p) && lstat($p)['nlink']===1,'Private token file required');
        if (PHP_OS_FAMILY!=='Windows') need((fileperms($p)&0077)===0,'Token permissions must be 0600');
        $this->token=trim(file_get_contents($p));
        need($this->token!=='' && !preg_match('/[\r\n]/',$this->token),'Invalid token format');
    }
    public static function checkUrl(string $url,bool $auth): void {
        $u=parse_url($url); $host=strtolower($u['host']??'');
        need(($u['scheme']??'')==='https' && !isset($u['user']) && !isset($u['pass']) &&
            (!isset($u['port']) || $u['port']===443),'HTTPS only');
        need($host==='api.github.com' || preg_match('/^[a-z0-9-]+\.blob\.core\.windows\.net$/D',$host)===1 ||
            preg_match('/^[a-z0-9-]+\.actions\.githubusercontent\.com$/D',$host)===1,'Untrusted redirect host');
        need(!$auth || $host==='api.github.com','Credentials must never follow storage redirect');
    }
    protected function requestDeadline(): ?float { return null; }

    protected function request(string $url,bool $auth,int $limit,?string $out=null): array {
        self::checkUrl($url,$auth);
        $last=null;
        for ($attempt=0;$attempt<2;$attempt++) {
            $deadline=$this->requestDeadline();
            $remaining=$deadline===null?180000:(int)floor(($deadline-microtime(true))*1000);
            if($remaining<1)throw new \RuntimeException('HTTPS GET deadline exceeded');
            $headers=[]; $body=''; $count=0; $fh=null;
            if ($out!==null) { need(!is_link($out),'Linked download target'); $fh=fopen($out,'wb'); need($fh!==false,'Download target failed'); chmod($out,0600); }
            $ch=curl_init($url);
            $req=['Accept: application/vnd.github+json','X-GitHub-Api-Version: 2022-11-28'];
            if ($auth) $req[]='Authorization: Bearer '.$this->token;
            curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>$req,CURLOPT_USERAGENT=>'Rumo-private-pull/1',
                CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT_MS=>min(15000,$remaining),
                CURLOPT_TIMEOUT_MS=>min(180000,$remaining),
                CURLOPT_LOW_SPEED_LIMIT=>512,CURLOPT_LOW_SPEED_TIME=>30,
                CURLOPT_HEADERFUNCTION=>static function($ch,$h)use(&$headers){ $i=strpos($h,':'); if($i!==false)$headers[strtolower(substr($h,0,$i))]=trim(substr($h,$i+1)); return strlen($h); },
                CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$body,&$count,$limit,$fh){
                    $count+=strlen($chunk); if($count>$limit)return 0;
                    if($fh!==null)return fwrite($fh,$chunk);
                    $body.=$chunk; return strlen($chunk);
                }]);
            $ok=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $err=curl_errno($ch); curl_close($ch);
            if ($fh!==null) { fflush($fh); fsync($fh); fclose($fh); }
            if ($ok!==false && $err===0) return ['status'=>$code,'headers'=>$headers,'body'=>$body,'bytes'=>$count];
            $last=$err;
            // Only safe GET retries. Never retain partial bytes and never downgrade TLS.
            // libcurl uses 60 for peer/certificate verification; older builds also
            // used 51. Some PHP builds do not export the peer verification name.
            if (in_array($err,[35,51,60],true)) break;
        }
        throw new \RuntimeException('HTTPS GET failed; partial output not trusted (curl '.$last.')');
    }
    private function api(string $path): array {
        need(str_starts_with($path,'/repos/'.$this->c['repository'].'/'),'API path outside configured repository');
        $r=$this->request('https://api.github.com'.$path,true,4*1024*1024);
        need($r['status']===200,'GitHub API status '.$r['status']); return json($r['body']);
    }
    public function discover(): array {
        need(($this->c['release_channel']??null)!=='recovery-v8','V8 recovery requires explicit selection');
        $base='/repos/'.$this->c['repository'].'/actions';
        $r=$this->api($base.'/workflows/'.$this->c['workflow_id'].'/runs?branch='.rawurlencode($this->c['branch']).'&event=push&status=success&per_page=100');
        $runs=$r['workflow_runs']??[];
        need(count($runs)>0,'No successful authorized workflow run');
        usort($runs,fn($a,$b)=>$b['run_number']<=>$a['run_number']);
        $run=$runs[0];
        $this->assertMainTip($run['head_sha']);
        $a=$this->api($base.'/runs/'.$run['id'].'/artifacts?per_page=100');
        need(($a['total_count']??0)<=100,'Artifact discovery pagination bound; choose explicit IDs');
        $name='release-'.$this->c['project'].'-'.$run['head_sha'];
        $found=array_values(array_filter($a['artifacts']??[],fn($v)=>$v['name']===$name && !$v['expired']));
        need(count($found)===1,'No unique artifact for latest successful run; no fallback to old run');
        return [$run['id'],$found[0]['id']];
    }
    public function automatic(Publisher $publisher, ?array $expected = null): array {
        $repo='/repos/'.$this->c['repository'];
        $head=$this->api($repo.'/git/ref/heads/main')['object']['sha']??'';
        need(preg_match('/^[a-f0-9]{40}$/D',$head)===1,'Invalid main tip');
        if($expected!==null)need($head===$expected['commit'],'Explicit release is no longer the main tip');
        $runs=$this->api($repo.'/actions/workflows/'.$this->c['workflow_id'].'/runs?branch=main&event=push&head_sha='.$head.'&per_page=100');
        $items=$runs['workflow_runs']??[];
        need(is_array($items) && count($items)<=1,'No unique canonical run for main tip');
        if(!$items){
            $this->report(['id'=>null,'head_sha'=>$head],'pending','Aguardando execucao do workflow oficial');
            return ['state'=>'waiting','commit'=>$head];
        }
        $run=$items[0];
        need(($run['head_sha']??null)===$head && ($run['head_branch']??null)==='main' &&
            ($run['repository']['full_name']??null)===$this->c['repository'] &&
            ($run['workflow_id']??null)===$this->c['workflow_id'] && ($run['event']??null)==='push' &&
            is_int($run['id']??null) && $run['id']>0,'Unexpected canonical run identity');
        if($expected!==null)need($run['id']===$expected['run_id'],'Unexpected run for explicit release');
        $checkpointPath=$this->c['private'].'/installed.json';
        $checkpoint=is_file($checkpointPath)?json(file_get_contents($checkpointPath)):[];
        $status='pending';$reason='Aguardando CI oficial';$result=['state'=>'waiting','commit'=>$head];
        try {
            if(($checkpoint['commit']??null)===$head && is_int($checkpoint['artifact_id']??null) &&
            ($expected===null || $checkpoint['artifact_id']===$expected['artifact_id']) &&
            MainAutomation::outcome($this->c,$run,['id'=>$checkpoint['artifact_id']])){
                $reason='Verificacao HTTP publica reprovada';
                $publisher->verifyPublicAvailability();
                $this->report($run,'success','Recibo UOL validado; artefato '.$checkpoint['artifact_id']);
                return ['state'=>'validated','commit'=>$head,'already_installed'=>true];
            }
            if(($run['status']??null)==='completed'){
                need(($run['conclusion']??null)==='success','Official CI failed');
                // PR #9 installs receiver infrastructure only. The prior UI-001 pilot
                // must already be validated before that PR merges. Never apply or
                // label its infrastructure-only artifact as an application release.
                if($expected===null && isset($this->c['main_automation']) &&
                    ($this->c['main_automation']['owner_id']??null)===35331184 &&
                    ($checkpoint['commit']??null)===MainAutomation::UI001['commit']){
                    $merge=$this->api($repo.'/commits/'.$head);
                    $parents=$merge['parents']??[];
                    if(count($parents)===2 &&
                        ($parents[0]['sha']??null)===MainAutomation::UI001['commit']){
                        $pr=$this->api($repo.'/pulls/9');
                        if(($pr['merge_commit_sha']??null)===$head){
                            $reviewHead=$pr['head']['sha']??'';
                            need(is_string($reviewHead) &&
                                preg_match('/^[a-f0-9]{40}$/D',$reviewHead)===1,
                                'PR #9 reviewed head not proven');
                            $review=$this->api($repo.'/commits/'.$reviewHead);
                            $comparison=$this->api($repo.'/compare/'.MainAutomation::UI001['commit'].'...'.$head);
                            MainAutomation::infrastructureOnlyPr9($this->c['main_automation'],
                                $pr,$merge,$review,$comparison);
                            need(MainAutomation::outcome($this->c,
                                ['id'=>MainAutomation::UI001['run_id'],
                                 'head_sha'=>MainAutomation::UI001['commit']],
                                ['id'=>MainAutomation::UI001['artifact_id']]),
                                'UI-001 production receipt must remain validated before PR #9');
                            $publisher->verifyPublicAvailability();
                            $reason='Infrastructure PR #9 retained without application deployment';
                            $this->report($run,'pending',$reason);
                            return ['state'=>'infrastructure-held','main_commit'=>$head,
                                'installed_commit'=>MainAutomation::UI001['commit'],
                                'application_deployed'=>false];
                        }
                    }
                }
                $reason='Artefato oficial indisponivel';
                $a=$this->api($repo.'/actions/runs/'.$run['id'].'/artifacts?per_page=100');
                need(($a['total_count']??101)<=100,'Artifact discovery pagination bound');
                $found=array_values(array_filter($a['artifacts']??[],fn($v)=>
                    ($v['name']??null)==='release-'.$this->c['project'].'-'.$head && ($v['expired']??true)===false));
                need(count($found)===1 && is_int($found[0]['id']??null),'No unique unexpired canonical artifact');
                $artifact=$found[0];
                if($expected!==null)need($artifact['id']===$expected['artifact_id'],'Unexpected artifact for explicit release');
                $reason='Politica ou integridade do receptor bloqueou a publicacao';
                if(MainAutomation::outcome($this->c,$run,$artifact)) {
                    $result=['state'=>'validated','commit'=>$head,'already_installed'=>true];
                } else {
                    $input=$this->fetch($run['id'],$artifact['id'],$this->c['private'].'/downloads');
                    $staged=$publisher->stage(...$input);
                    $this->assertMainTip($staged['commit']);
                    $reason='Publicacao bloqueada pelo receptor';
                    $result=$publisher->apply($staged['stage']);
                    need(MainAutomation::outcome($this->c,$run,$artifact),'Validated production receipt not confirmed');
                }
                $reason='Verificacao HTTP publica reprovada';
                $publisher->verifyPublicAvailability();
                $status='success';$reason='Recibo UOL validado; artefato '.$artifact['id'];
            }
        } catch(PublicationFenceDeferred $e) {
            try{$this->report($run,'pending','Fence aguardando: '.$e->getMessage());}
            catch(\Throwable){fwrite(STDERR,"GitHub status unavailable; receiver state retained\n");}
            return ['state'=>'waiting','commit'=>$head,'reason'=>'publication_fence','condition'=>$e->getMessage()];
        } catch(\Throwable $e) {
            $details=[
                'Authenticated owner backup statement'=>'Confirmacao autenticada de backup ausente',
                'Required backup approval status'=>'Status da App de backup ausente ou invalido',
                'Exact main merge by the approved owner not confirmed'=>'Merge nao autorizado pelo proprietario',
                'Sensitive release requires an exact private permit and authenticated transition'=>'Transicao sensivel sem autorizacao especifica',
                'Structural change or Class C'=>'Mudanca estrutural ou Classe C bloqueada',
                'Workflow definition changed'=>'Hash do workflow divergiu da politica privada',
                'Hook failed: fence'=>'Fence de publicacao reprovado',
                'Hook failed: backup'=>'Backup e restauracao isolada nao comprovados',
                'Hook timeout: backup'=>'Tempo limite da prova de restauracao',
                'Fresh isolated restore proof'=>'Prova de restauracao ausente ou vencida',
                'Hook failed: maintenance'=>'Manutencao ou drenagem nao comprovada',
                'Hook failed: migrate'=>'Validacao somente leitura da migration 009 falhou',
                'Hook failed: smoke'=>'Smoke produtivo reprovado; manutencao preservada',
                'Pending drain session'=>'Sessao pendente; recuperacao necessaria',
                'Pending journal'=>'Journal pendente; recuperacao necessaria',
            ];
            foreach($details as $match=>$message)if(str_contains($e->getMessage(),$match)){$reason=$message;break;}
            if(preg_match('/^Hook failed: fence \(FENCE_BLOCKED ([a-z_]+)\b/',$e->getMessage(),$fence)===1)
                $reason='Fence bloqueado: '.$fence[1];
            try{$this->report($run,'failure',($run['conclusion']??null)!=='success'?'CI oficial reprovado':$reason);}
            catch(\Throwable){fwrite(STDERR,"GitHub status unavailable; receiver state retained\n");}
            $this->safeAutoRecoverPreTransfer($publisher);
            throw $e;
        }
        $this->report($run,$status,$reason);
        return $result;
    }
    protected function safeAutoRecoverPreTransfer(Publisher $publisher): void {
        try {
            $private = $this->c['private'] ?? '';
            $journalPath = $private . '/journal.json';
            $sessionPath = $private . '/drain-session.json';
            if (!is_file($sessionPath)) return;
            $s = json(file_get_contents($sessionPath));
            if (($s['state'] ?? '') !== 'blocked') return;

            $isUntouched = true;
            if (is_file($journalPath)) {
                $j = json(file_get_contents($journalPath));
                $allPlanned = count(array_filter($j['operations'] ?? [], fn($o) => ($o['state'] ?? '') !== 'planned' || isset($o['temp']))) === 0;
                $noMigrations = !($j['migration_started']??false) && !isset($j['migration_receipt']);
                $isUntouched = $allPlanned && $noMigrations;
            }
            if ($isUntouched) {
                $publisher->recover();
            }
        } catch (\Throwable $recoverErr) {
            fwrite(STDERR, "Pre-transfer auto-recovery skipped: " . $recoverErr->getMessage() . "\n");
        }
    }
    protected function report(array $run,string $state,string $reason): void {
        $a=$this->c['main_automation'];
        $file=$a['status_token_file'];
        need(is_file($file) && !is_link($file) && lstat($file)['nlink']===1 &&
            (PHP_OS_FAMILY==='Windows'||(fileperms($file)&0077)===0),'Private status token required');
        $token=trim(file_get_contents($file));
        need($token!=='' && !preg_match('/[\r\n]/',$token),'Invalid status token');
        $cache=$this->c['private'].'/main-automation-status.json';
        $payload=['state'=>$state,'context'=>'rumo10/uol-deploy','description'=>$reason,
            'target_url'=>'https://github.com/'.$this->c['repository'].
                (isset($run['id'])?'/actions/runs/'.$run['id']:'/actions')];
        $receipt=['commit'=>$run['head_sha'],'payload'=>$payload];
        if(is_file($cache) && json(file_get_contents($cache))===$receipt)return;
        $url='https://api.github.com/repos/'.$this->c['repository'].'/statuses/'.$run['head_sha'];
        self::checkUrl($url,true);
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/vnd.github+json',
                'Content-Type: application/json','X-GitHub-Api-Version: 2022-11-28'],
            CURLOPT_USERAGENT=>'Rumo-private-pull/1',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,
            CURLOPT_TIMEOUT=>10,CURLOPT_WRITEFUNCTION=>static fn($ch,$b)=>strlen($b)]);
        $ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        need($ok!==false && $status===201,'GitHub deployment status rejected (HTTP '.$status.')');
        record($cache,$receipt);
    }

    public function assertMainTip(string $head): void {
        if(($this->c['release_channel']??null)!=='main')return;
        $ref=$this->api('/repos/'.$this->c['repository'].'/git/ref/heads/main');
        need(($ref['object']['sha']??null)===$head,'Latest main commit has no eligible candidate');
    }
    public function assertApprovedMerge(array $identity,int $number): BackupAuthorization {
        $cfg=$this->c['main_automation'];
        $name=$this->c['repository'];
        $repo='/repos/'.$name;
        $head=$identity['commit'];
        need($number>0 && preg_match('/^[a-f0-9]{40}$/D',$head)===1,'Invalid merge identity');
        $pr=$this->api($repo.'/pulls/'.$number);
        $commit=$this->api($repo.'/commits/'.$head);
        MainAutomation::merge($cfg,$name,$head,$number,$pr,$commit);
        if($identity===MainAutomation::UI001 && $number===8 && ($cfg['ui001_bootstrap_request']??false)===true){
            $statement=MainAutomation::backupStatement($number,$pr['head']['sha'],true);
            for($page=1;$page<=5;$page++){
                $comments=$this->api($repo."/issues/{$number}/comments?per_page=100&direction=desc&page={$page}");
                foreach($comments as $comment){
                    if(($comment['body']??null)===$statement && ($comment['user']['id']??null)===$cfg['owner_id'])
                        return BackupAuthorization::fromGitHub($cfg,$name,$identity,$pr,$comment,null);
                }
                if(count($comments)<100)break;
            }
            throw new \RuntimeException('Authenticated owner backup statement missing for UI-001 exception');
        }
        $ruleId=$cfg['backup_ruleset_id']??null;
        $appId=$cfg['backup_app_id']??null;
        need(is_int($ruleId) && $ruleId>0 && is_int($appId) && $appId>0,'Required backup ruleset and App pins missing');
        $rule=$this->api($repo.'/rulesets/'.$ruleId);
        MainAutomation::backupRule($rule,$ruleId,$appId);
        $approval=$this->api($repo.'/commits/'.$pr['head']['sha'].'/status?per_page=100');
        need(($approval['sha']??null)===$pr['head']['sha'],'Backup status does not belong to the approved head');
        $statuses=array_values(array_filter($approval['statuses']??[],static fn($status)=>
            ($status['context']??null)===MainAutomation::BACKUP_CONTEXT));
        need(count($statuses)===1,'Required backup approval status missing or ambiguous');
        $status=$statuses[0];
        $target="https://github.com/{$name}/pull/{$number}#issuecomment-";
        need(str_starts_with($status['target_url']??'',$target),'Required backup approval status points to another PR');
        $commentId=substr($status['target_url'],strlen($target));
        need(preg_match('/^[1-9][0-9]*$/D',$commentId)===1,'Required backup approval status lacks a comment ID');
        $comment=$this->api($repo.'/issues/comments/'.$commentId);
        return BackupAuthorization::fromGitHub($cfg,$name,$identity,$pr,$comment,$status);
    }
    /**
     * Traces the linear first-parent main history from $fromSha (installed) to $toSha (candidate)
     * via authenticated GitHub API and proves that EVERY step that modified any path in
     * $delegatedPaths was an exact main PR merge performed by the configured private owner_id.
     * Prevents an earlier non-owner change from being laundered by a later owner merge.
     */
    public function assertOwnerMergedDelegatedPaths(string $fromSha, string $toSha, array $delegatedPaths): array {
        $cfg = $this->c['main_automation'] ?? null;
        need(is_array($cfg) && is_int($cfg['owner_id'] ?? null) && $cfg['owner_id'] > 0,
            'Owner merge authorization requires private main automation configuration');
        need(preg_match('/^[a-f0-9]{40}$/D', $fromSha) === 1 && preg_match('/^[a-f0-9]{40}$/D', $toSha) === 1 && $fromSha !== $toSha,
            'Valid installed and candidate commits required for delegated Class B proof');
        $targets = array_values(array_unique(array_filter($delegatedPaths, static fn($p) => is_string($p) && $p !== '')));
        need($targets !== [], 'At least one delegated path required for owner merge proof');

        $this->assertDescendant($fromSha, $toSha);
        $repoName = $this->c['repository'];
        $repo = '/repos/'.$repoName;
        $proofs = [];
        $cursor = $toSha;
        $steps = 0;

        while ($cursor !== $fromSha) {
            $steps++;
            need($steps <= 50, 'Delegated Class B history depth limit exceeded');
            $commit = $this->api($repo.'/commits/'.$cursor);
            need(($commit['sha']??null) === $cursor, 'Commit identity mismatch while tracing main history');
            $parents = $commit['parents'] ?? [];
            need(is_array($parents) && array_is_list($parents) && count($parents) >= 1 &&
                preg_match('/^[a-f0-9]{40}$/D', $parents[0]['sha'] ?? '') === 1,
                'Cannot trace linear main history from installed commit to candidate');
            $prevSha = $parents[0]['sha'];

            $stepCompare = $this->api($repo.'/compare/'.$prevSha.'...'.$cursor);
            $stepFiles = $stepCompare['files'] ?? null;
            need(is_array($stepFiles) && count($stepFiles) < 300,
                'Step diff unavailable or truncated while verifying delegated Class B paths');

            $touchedDelegated = [];
            foreach ($stepFiles as $file) {
                $fname = $file['filename'] ?? null;
                $prevName = $file['previous_filename'] ?? null;
                if (is_string($fname) && in_array($fname, $targets, true)) {
                    $touchedDelegated[$fname] = true;
                }
                if (is_string($prevName) && in_array($prevName, $targets, true)) {
                    $touchedDelegated[$prevName] = true;
                }
            }

            if ($touchedDelegated !== []) {
                $prNumber = $this->mergedMainPr($cursor);
                $pr = $this->api($repo.'/pulls/'.$prNumber);
                MainAutomation::merge($cfg, $repoName, $cursor, $prNumber, $pr, $commit);
                foreach (array_keys($touchedDelegated) as $path) {
                    $proofs[$path][] = [
                        'commit' => $cursor,
                        'pr' => $prNumber,
                        'head' => $pr['head']['sha'],
                        'owner_id' => $cfg['owner_id'],
                        'merged_at' => $pr['merged_at'],
                    ];
                }
            }
            $cursor = $prevSha;
        }

        foreach ($targets as $path) {
            need(!empty($proofs[$path]),
                'No authenticated owner merge found introducing delegated Class B path: '.$path);
        }
        return $proofs;
    }

    private function mergedMainPr(string $head): int {
        $repo=$this->c['repository'];
        $pulls=$this->api('/repos/'.$repo.'/commits/'.$head.'/pulls?per_page=100');
        $matches=array_values(array_filter($pulls,static fn($pr)=>
            ($pr['base']['repo']['full_name']??null)===$repo && ($pr['base']['ref']??null)==='main' &&
            ($pr['merge_commit_sha']??null)===$head && !empty($pr['merged_at']) &&
            ($pr['state']??null)==='closed' && is_int($pr['number']??null) && $pr['number']>0));
        need(count($matches)===1,'Exact merged main PR not found');
        return $matches[0]['number'];
    }
    public function assertDescendant(string $base,string $head): void {
        need(preg_match('/^[a-f0-9]{40}$/D',$base)===1 && preg_match('/^[a-f0-9]{40}$/D',$head)===1,'Trusted baseline commit required');
        if($base===$head)return;
        $r=$this->api('/repos/'.$this->c['repository'].'/compare/'.$base.'...'.$head);
        need(($r['status']??null)==='ahead' && ($r['merge_base_commit']['sha']??null)===$base,
            'Candidate is not descendant of installed code; downgrade/divergence refused');
    }
    public function evidence(int $runId,int $artifactId): array {
        need($runId>0 && $artifactId>0,'Explicit run/artifact IDs required; latest refused');
        $base='/repos/'.$this->c['repository'].'/actions';
        $run=$this->api($base.'/runs/'.$runId);
        $artifact=$this->api($base.'/artifacts/'.$artifactId);
        $jobs=[]; $page=1;
        do {
            $r=$this->api($base.'/runs/'.$runId.'/attempts/'.($run['run_attempt']??0).'/jobs?per_page=100&page='.$page);
            $items=$r['jobs']??[];
            foreach($items as &$job)$job['_requested_attempt']=$run['run_attempt']; unset($job);
            $jobs=array_merge($jobs,$items); $page++;
            need($page<=51,'Job pagination bound exceeded');
        } while(count($jobs)<($r['total_count']??0));
        return [$run,$artifact,$jobs];
    }
    public function fetch(int $runId,int $artifactId,string $dir): array {
        privateDir($dir);
        [$run,$artifact,$jobs]=$this->evidence($runId,$artifactId);
        $checkpoint=is_file($this->c['private'].'/installed.json')?json(file_get_contents($this->c['private'].'/installed.json')):null;
        $channel=$this->c['release_channel']??null;
        if($channel==='recovery-v8'){
            need(($this->c['recovery_run_id']??null)===$runId && ($this->c['recovery_artifact_id']??null)===$artifactId,
                'V8 recovery requires private run and artifact pins');
        }else{
            $previous=$checkpoint['last_main_commit']??$checkpoint['commit']??$this->c['baseline_commit']??'';
            $this->assertDescendant($previous,$run['head_sha']);
            $this->assertMainTip($run['head_sha']);
        }
        $path=$this->c['workflow_path']??'';
        need(preg_match('#^\.github/workflows/[a-zA-Z0-9_.-]+\.ya?ml$#D',$path)===1,'Pinned workflow path required');
        $definition=$this->api('/repos/'.$this->c['repository'].'/contents/'.$path.'?ref='.$run['head_sha']);
        need(($definition['encoding']??null)==='base64','Workflow content encoding invalid');
        $definitionBytes=base64_decode(preg_replace('/\s+/','',$definition['content']??''),true);
        need($definitionBytes!==false && hash('sha256',$definitionBytes)===($this->c['workflow_sha256']??''),
            'Workflow definition changed; operator approval required');
        need(($artifact['expired']??true)===false && preg_match('/^sha256:([a-f0-9]{64})$/D',$artifact['digest']??'', $match)===1,'Immutable artifact digest required');
        $zip=$dir.'/artifact-'.$artifactId.'.zip.part';
        $base='/repos/'.$this->c['repository'].'/actions/artifacts/'.$artifactId.'/zip';
        // Refresh signed URL after failure; never reuse an expired link or forward the PAT.
        $ok=false;
        for($i=0;$i<2;$i++) {
            $r=$this->request('https://api.github.com'.$base,true,4096);
            need($r['status']===302 && isset($r['headers']['location']),'Expected authenticated download redirect');
            $r=$this->request($r['headers']['location'],false,$this->c['max_artifact_bytes'],$zip);
            if($r['status']===200) { $ok=true; break; }
            need(in_array($r['status'],[401,403,410],true),'Storage download rejected');
        }
        need($ok && digest($zip)===$match[1],'Artifact ZIP digest mismatch');
        [$after,$a2,$j2]=$this->evidence($runId,$artifactId);
        need($after===$run && $a2===$artifact && $j2===$jobs,'GitHub source changed during download');
        if($channel==='main'){
            $this->assertMainTip($run['head_sha']);
            $run['merged_pr']=$this->mergedMainPr($run['head_sha']);
            if(isset($this->c['main_automation'])){
                // The operator handles manual backup BEFORE merge. Publication checks
                // the signed workflow artifact and actual owner merge, not a backup
                // comment, GitHub App or database dump receipt.
                $pr=$this->api('/repos/'.$this->c['repository'].'/pulls/'.$run['merged_pr']);
                $merge=$this->api('/repos/'.$this->c['repository'].'/commits/'.$run['head_sha']);
                MainAutomation::merge($this->c['main_automation'],$this->c['repository'],
                    $run['head_sha'],$run['merged_pr'],$pr,$merge);
            }
        }
        // PharData reads ZIP; do not extract archive-controlled paths.
        $zipFinal=$dir.'/artifact-'.$artifactId.'.zip';
        need(rename($zip,$zipFinal),'Cannot commit downloaded archive');
        inspectArtifactZip($zipFinal);
        $z=new \PharData($zipFinal); $members=[];
        foreach(new \RecursiveIteratorIterator($z) as $item) {
            $name=str_replace('phar://'.str_replace('\\','/',$zipFinal).'/', '',str_replace('\\','/',$item->getPathname()));
            need(in_array($name,['candidate.json','release.tar.gz'],true) && !$item->isLink(),'Unexpected artifact member');
            need(!isset($members[$name]),'Duplicate artifact member'); $members[$name]=$item;
        }
        need(count($members)===2,'Artifact must contain exactly envelope and release TAR');
        need($members['candidate.json']->getSize()<=65536,'Envelope too large');
        $envelope=json($members['candidate.json']->getContent());
        Evidence::validate($this->c,$run,$artifact,$jobs,$envelope);
        $package=$dir.'/package-'.$artifactId.'.tar.gz';
        $in=$members['release.tar.gz']->openFile('r'); $out=fopen($package,'wb'); need($out!==false,'Package output failed');
        $bytes=0;
        try {
            while(!$in->eof()) {
                $chunk=$in->fread(65536); $bytes+=strlen($chunk);
                need($bytes<=512*1024*1024 && fwrite($out,$chunk)===strlen($chunk),'Package extraction failed/limit');
            }
            need(fflush($out) && fsync($out),'Downloaded package sync failed');
        } finally { fclose($out); }
        need(digest($package)===$envelope['package_sha256'],'Inner package hash mismatch');
        return [$package,$run,$artifact,$jobs,$envelope];
    }
}
