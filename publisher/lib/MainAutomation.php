<?php
declare(strict_types=1);
namespace Rumo\Publisher;
require_once __DIR__.'/RequestDrain.php';

final class MainAutomation {
    public const BACKUP_CONTEXT = 'rumo10/manual-backup';
    public const UI001 = ['commit'=>'fb03b614c337845671cf6cbf1eb36abe01308bfd','run_id'=>43929021873,'artifact_id'=>14257772333];

    public static function configuration(array $c): void {
        $a=$c['main_automation']??null;
        if(!array_key_exists('main_automation',$c))return;
        need(is_array($a) && ($a['schema']??null)==='rumo-main-automation-v1' &&
            $c['project']==='rumo10' && $c['repository']==='rralvarez/rumo10' &&
            $c['branch']==='main' && $c['release_channel']==='main' &&
            ($c['delegate_main']??false)===false && is_int($a['owner_id']??null) && $a['owner_id']>0 &&
            is_string($a['status_token_file']??null) && is_file($a['status_token_file']) &&
            $a['status_token_file']!==($c['token_file']??null) &&
            realpath($a['status_token_file'])!==realpath($c['token_file']??'') &&
            is_array($c['required_jobs']??null) &&
            array_diff(['syntax','regression','compatibility','publisher-safety','rumo10-ci-gate','artifact'],$c['required_jobs'])===[] &&
            ($c['request_gate']['coverage_approved']??false)===true,
            'Invalid private main automation configuration');
        self::smokeUrls($a);
    }

    public static function smokeUrls(array $a): array {
        $urls=$a['public_smoke_urls']??[];
        need(is_array($urls) && isset($urls['rumo10'],$urls['rumohub']) && count($urls)===2,
            'Both public HTTPS smoke endpoints required');
        $hosts=[];
        foreach($urls as $url){
            $u=is_string($url)?parse_url($url):false;
            need(($u['scheme']??null)==='https' && !empty($u['host']) && !isset($u['user']) && !isset($u['pass']) &&
                (!isset($u['port']) || $u['port']===443),'Invalid public HTTPS smoke endpoint');
            $hosts[]=strtolower($u['host']);
        }
        need(count(array_unique($hosts))===2,'The public smoke endpoints must cover separate hosts');
        return $urls;
    }

    public static function merge(array $a,string $repo,string $sha,int $number,array $pr,array $commit): void {
        $parents=$commit['parents']??[];
        need(($pr['number']??null)===$number && ($pr['state']??null)==='closed' &&
            ($pr['merged']??false)===true && !empty($pr['merged_at']) &&
            ($pr['merged_by']['id']??null)===($a['owner_id']??null) &&
            ($pr['base']['repo']['full_name']??null)===$repo && ($pr['base']['ref']??null)==='main' &&
            ($pr['merge_commit_sha']??null)===$sha && ($commit['sha']??null)===$sha &&
            is_array($parents) && array_is_list($parents) && count($parents)===2 &&
            preg_match('/^[a-f0-9]{40}$/D',$parents[0]['sha']??'')===1 &&
            preg_match('/^[a-f0-9]{40}$/D',$parents[1]['sha']??'')===1 &&
            $parents[0]['sha']!==$parents[1]['sha'] && $parents[1]['sha']===($pr['head']['sha']??null),
            'Exact main merge by the approved owner not confirmed');
    }

    public static function backupStatement(int $pr, string $head, bool $exception): string {
        return $exception
            ? 'RUMO10 BACKUP CONFIRMADO UI-001 POS-MERGE PR #8 '.self::UI001['commit'].' RUN 43929021873 ARTEFATO 14257772333'
            : "RUMO10 BACKUP CONFIRMADO E MERGE AUTORIZADO PR #{$pr} HEAD {$head}";
    }

    public static function backupRule(array $ruleset,int $id,int $app): void {
        $ref=$ruleset['conditions']['ref_name']??[];
        need(($ruleset['id']??null)===$id && ($ruleset['target']??null)==='branch' &&
            ($ruleset['enforcement']??null)==='active' && ($ruleset['bypass_actors']??null)===[] &&
            ($ref['include']??null)===['refs/heads/main'] && ($ref['exclude']??null)===[],
            'Required backup ruleset must be active on main without bypass');
        need(in_array('pull_request',array_column($ruleset['rules']??[],'type'),true),
            'Required backup ruleset must also require a pull request');
        foreach($ruleset['rules']??[] as $rule){
            if(($rule['type']??null)!=='required_status_checks' ||
                ($rule['parameters']['strict_required_status_checks_policy']??false)!==true)continue;
            foreach($rule['parameters']['required_status_checks']??[] as $check)
                if(($check['context']??null)===self::BACKUP_CONTEXT && ($check['integration_id']??null)===$app)return;
        }
        throw new \RuntimeException('Required backup ruleset lacks the pinned App status');
    }

    /**
     * One-time no-op transition for the reviewed infrastructure-only PR #9.
     * This does NOT authorize application deployment or bypass backup checks
     * for any following functional PR.
     */
    public static function infrastructureOnlyPr9(array $a,array $pr,array $merge,array $source,array $comparison): void {
        $base=self::UI001['commit'];
        $parents=$merge['parents']??[];
        $files=$comparison['files']??null;
        need(($pr['number']??null)===9 && ($pr['state']??null)==='closed' &&
            ($pr['merged']??false)===true && !empty($pr['merged_at']) &&
            ($pr['merged_by']['id']??null)===($a['owner_id']??null) &&
            ($pr['base']['repo']['full_name']??null)==='rralvarez/rumo10' &&
            ($pr['base']['ref']??null)==='main' &&
            ($pr['merge_commit_sha']??null)===($merge['sha']??null) &&
            is_array($parents) && count($parents)===2 &&
            ($parents[0]['sha']??null)===$base &&
            ($parents[1]['sha']??null)===($pr['head']['sha']??null) &&
            ($source['sha']??null)===($pr['head']['sha']??null) &&
            is_string($merge['commit']['tree']['sha']??null) &&
            ($merge['commit']['tree']['sha']??null)===($source['commit']['tree']['sha']??null) &&
            ($comparison['status']??null)==='ahead' &&
            ($comparison['merge_base_commit']['sha']??null)===$base &&
            is_array($files) && count($files)>0 && count($files)<300,
            'Pinned PR #9 infrastructure transition identity not proven');
        foreach($files as $file){
            $path=$file['filename']??null;
            need(is_string($path) &&
                (str_starts_with($path,'publisher/') ||
                 $path==='.github/workflows/manual-backup-approval.yml'),
                'PR #9 changes application or another unauthorized operational surface');
        }
    }

    public static function change(array $a,array $v,array $assessment): void {
        need(($v['envelope']['release_channel']??null)==='main' &&
            ($v['envelope']['functional_commit']??null)===$v['commit'] &&
            ($assessment['compatible_writers_ci']??false)===true,
            'Automatic release is outside the approved main contract');
        need(in_array($assessment['class'],['A','B'],true),
            'Structural change or Class C: separate authorized procedure required');
        foreach($assessment['changed'] as $path){
            need(!preg_match('#^(?:publisher|bin|deploy)/#iD',$path) &&
                !preg_match('#(?:^|/)(?:\\.htaccess|\\.user\\.ini)$#iD',$path),
                'Infrastructure change requires a separate authorized procedure');
        }
        if($assessment['class']==='A'){
            need($assessment['migrations']===[] && ($assessment['required_migrations']??[])===[], 'Class A cannot contain migrations');
            foreach($assessment['changed'] as $path){
                need(preg_match('#^(?:app/.+\\.php|public/assets/.+\\.(?:css|js|svg|png|webp|woff2))$#iD',$path)===1 &&
                    !in_array(strtolower($path),['app/bootstrap.php','app/database.php','app/canonicalidentity.php','app/f01drainer.php','app/f01telemetry.php'],true),
                    'Path outside ordinary automatic release policy');
            }
            return;
        }
        // Class B: public/index.php is permitted ONLY for an exact, authenticated
        // from/to transition explicitly listing the path in the private policy.
        foreach($assessment['changed'] as $path){
            if($path==='public/index.php'){
                need(($assessment['transition_to']??null)===$v['commit'] &&
                    is_string($assessment['transition_from']??null) &&
                    preg_match('/^[a-f0-9]{40}$/D',$assessment['transition_from'])===1 &&
                    ($assessment['requires_full_drain']??false)===true &&
                    in_array($path,$assessment['authorized_protected_paths']??[],true),
                    'Public entrypoint requires exact authorized, fully drained transition');
                continue;
            }
            need(preg_match('#^(?:database/.+\\.(?:sql|php)|app/.+\\.php|public/assets/.+\\.(?:css|js|svg|png|webp|woff2))$#iD',$path)===1,
                'Path outside approved Class B release policy: '.$path);
        }
    }

    public static function proof(array $b,array $v,int $now, string $class = 'B', ?BackupAuthorization $authorization = null): void {
        if ($class === 'A') {
            need($authorization !== null && $authorization->matches($v),
                'Authenticated manual backup confirmation required for this Class A release');
            return;
        }
        need($class === 'B', 'Unknown database backup policy');
        $at=$b['created_at']??$b['restored_at']??null;
        need(is_int($at) && $at<=$now && $now-$at<1800 &&
            ($b['package_sha256']??null)===$v['package_sha256'] &&
            ($b['artifact_id']??null)===$v['artifact_id'] &&
            ($b['consistent']??false)===true,
            'Fresh consistent backup for this exact artifact required');
        $hash=$b['dump_sha256']??$b['restored_dump_sha256']??null;
        need(is_string($hash) && preg_match('/^[a-f0-9]{64}$/D',$hash)===1 &&
            in_array($hash,$b['files']??[],true),
            'Valid backup dump digest required');
    }

    public static function stable(array $c): void {
        $lock=$c['control'].'/deployment.lock';
        $closed=$c['request_gate']['root'].'/closed.json';
        clearstatcache();
        need(!file_exists($lock) && !is_link($lock) && !is_link($closed) &&
            !(new RequestDrain($c['request_gate']['root']))->closed(),'Publication gate or deployment lock retained');
    }

    public static function outcome(array $c,array $run,array $artifact): bool {
        $records=[];
        foreach([$c['private'].'/installed.json',$c['private'].'/journal.json',$c['private'].'/drain-session.json'] as $path){
            if(!is_file($path) || is_link($path))return false;
            $records[]=json(file_get_contents($path));
        }
        [$installed,$journal,$drain]=$records;
        $v=$journal['candidate']??[];
        return ($journal['state']??null)==='validated' && ($drain['state']??null)==='validated' &&
            isset($drain['stage'],$journal['stage']) && $drain['stage']===$journal['stage'] &&
            ($installed['commit']??null)===$run['head_sha'] && ($installed['artifact_id']??null)===$artifact['id'] &&
            ($installed['run_id']??null)===$run['id'] && ($v['run']['id']??null)===$run['id'] &&
            ($v['artifact_id']??null)===$artifact['id'] && ($v['commit']??null)===$run['head_sha'] &&
            isset($installed['package_sha256'],$v['package_sha256']) && $installed['package_sha256']===$v['package_sha256'] &&
            !file_exists($c['control'].'/deployment.lock') && !is_link($c['control'].'/deployment.lock') &&
            !is_link($c['request_gate']['root'].'/closed.json') &&
            !(new RequestDrain($c['request_gate']['root']))->closed();
    }
}



/** Evidence fetched from GitHub for one exact artifact. Stages and journals never construct it. */
final class BackupAuthorization {
    private function __construct(private array $identity, public readonly array $evidence) {}

    public static function fromGitHub(array $a, string $repo, array $identity, array $pr, array $comment, ?array $status): self {
        $number=$pr['number'];
        $head=$pr['head']['sha'];
        $exception=$identity===MainAutomation::UI001 && $number===8 && ($a['ui001_bootstrap_request']??false)===true;
        $created=strtotime($comment['created_at']??'');
        $merged=strtotime($pr['merged_at']??'');
        need(is_int($comment['id']??null) && $comment['id']>0 &&
            ($comment['issue_url']??null)==="https://api.github.com/repos/{$repo}/issues/{$number}" &&
            ($comment['user']['id']??null)===$a['owner_id'] && ($comment['user']['type']??null)==='User' &&
            ($comment['performed_via_github_app']??null)===null &&
            ($comment['created_at']??null)===($comment['updated_at']??null) &&
            ($comment['body']??null)===MainAutomation::backupStatement($number,$head,$exception) &&
            $created!==false && $merged!==false && $created<=time(),
            'Authenticated owner backup statement missing or altered');
        if ($exception) {
            need($created>$merged, 'UI-001 exception requires an explicit post-merge confirmation');
        } else {
            $statusAt=strtotime($status['created_at']??'');
            need(is_int($a['backup_app_bot_id']??null) && $a['backup_app_bot_id']>0 &&
                ($status['creator']['id']??null)===$a['backup_app_bot_id'] && ($status['creator']['type']??null)==='Bot' &&
                ($status['context']??null)===MainAutomation::BACKUP_CONTEXT && ($status['state']??null)==='success' &&
                ($status['target_url']??null)==="https://github.com/{$repo}/pull/{$number}#issuecomment-{$comment['id']}" &&
                $statusAt!==false && $created<=$statusAt && $statusAt<$merged,
                'Required backup approval status missing, invalid or created after merge');
        }
        return new self($identity, ['pr'=>$number,'head'=>$head,'owner_id'=>$a['owner_id'],
            'comment_id'=>$comment['id'],'confirmed_at'=>$comment['created_at'],'status_id'=>$status['id']??null,
            'exception'=>$exception]+$identity);
    }

    public function matches(array $v): bool {
        return $this->identity===['commit'=>$v['commit']??null,'run_id'=>$v['run']['id']??null,'artifact_id'=>$v['artifact_id']??null];
    }
}
