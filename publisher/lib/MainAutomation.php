<?php
declare(strict_types=1);
namespace Rumo\Publisher;
require_once __DIR__.'/RequestDrain.php';

final class MainAutomation {
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
            need($assessment['migrations']===[], 'Class A cannot contain migrations');
            foreach($assessment['changed'] as $path){
                need(preg_match('#^(?:app/.+\\.php|public/assets/.+\\.(?:css|js|svg|png|webp|woff2))$#iD',$path)===1 &&
                    !in_array(strtolower($path),['app/bootstrap.php','app/database.php','app/canonicalidentity.php','app/f01drainer.php','app/f01telemetry.php'],true),
                    'Path outside ordinary automatic release policy');
            }
            return;
        }
        // Class B (with migrations or protected/expanded files):
        foreach($assessment['changed'] as $path){
            need(preg_match('#^(?:database/.+\\.(?:sql|php)|app/.+\\.php|public/assets/.+\\.(?:css|js|svg|png|webp|woff2))$#iD',$path)===1,
                'Path outside approved Class B release policy: '.$path);
        }
    }

    public static function proof(array $b,array $v,int $now): void {
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

