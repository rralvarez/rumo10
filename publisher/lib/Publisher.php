<?php
declare(strict_types=1);
namespace Rumo\Publisher;
require_once __DIR__.'/Core.php';
require_once __DIR__.'/PrivateBackup.php';require_once __DIR__.'/UpdatePolicy.php';

/**
 * Hooks are operator-owned, hash-pinned local CLI programs, NEVER package code.
 * All receipts are rechecked by this installer. No SQL import/restore exists here.
 */
class Publisher {
    private array $c;
    private string $private;
    private mixed $mutex=null;
    public function __construct(array $config) {
        $this->c=$config; $this->private=$config['private'];
        need(in_array($config['project'],['rumo10','rumohub'],true),'Unknown project');
        foreach (['private','current','public','control'] as $key) {
            need(isset($config[$key]) && !is_link($config[$key]),'Physical paths required');
        }
        privateDir($this->private);
        foreach (['current','public','control'] as $key) {
            need(is_dir($config[$key]),'Missing operational root');
            $a=str_replace(chr(92),'/',realpath($this->private)).'/';
            $b=str_replace(chr(92),'/',realpath($config[$key])).'/';
            need(!str_starts_with($a,$b) && !str_starts_with($b,$a),'Private/operational roots overlap');
        }
        need(realpath($config['current'])!==realpath($config['public']),'Separate physical public root required');
    }
    public function __destruct() { if (is_resource($this->mutex)) { flock($this->mutex,LOCK_UN); fclose($this->mutex); } }
    public function acquire(): void { $this->lock(); }
    private function lock(): void {
        if (is_resource($this->mutex)) return;
        need(!is_link($this->c['account_mutex']),'Account mutex cannot be a symlink');
        if(is_file($this->c['account_mutex']))need(lstat($this->c['account_mutex'])['nlink']===1,'Hardlinked mutex refused');
        $this->mutex=fopen($this->c['account_mutex'],'c+b');
        need($this->mutex!==false && flock($this->mutex,LOCK_EX|LOCK_NB),'Concurrent publisher refused');
    }
    protected function promote(string $a,string $b): bool { return rename($a,$b); }
    protected function remove(string $path): bool { return unlink($path); }
    protected function transfer($input,$output): int|false { return stream_copy_to_stream($input,$output); }
    private function journal(): string { return $this->private.'/journal.json'; }
    private function marker(): string { return $this->c['control'].'/deployment.lock'; }
    private function own(array $j): void {
        need(is_file($this->marker()) && file_get_contents($this->marker())===$j['lock_bytes'],'Deployment lock lost/replaced');
    }
    private function save(array $j): void { record($this->journal(),$j); }
    protected function checkpoint(string $point): void { /* Overridden only in local tests. */ }
    protected function hook(string $name,array $request): array {
        $h=$this->c['hooks'][$name]??null;
        need(is_array($h) && digest($h['script'])===$h['sha256'],'Missing/changed trusted hook: '.$name);
        $cmd=array_merge([$this->c['php']],$this->c['php_args']??[],[$h['script'],$name],$h['args']??[]);
        $nonce=bin2hex(random_bytes(10));
        $input=$this->private.'/hook-'.$nonce.'.request.json'; $output=$this->private.'/hook-'.$nonce.'.receipt.json';
        record($input,$request); $outFile=fopen($output,'xb');need($outFile!==false,'Cannot create hook receipt');chmod($output,0600);fclose($outFile);
        // File descriptors, not pipes: Windows PHP cannot make anonymous pipes reliably nonblocking.
        $p=proc_open($cmd,[0=>['file',$input,'rb'],1=>['file',$output,'wb'],2=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','ab']],$pipes);
        need(is_resource($p),'Hook launch failed');
        $deadline=microtime(true)+($h['timeout_seconds']??120); $code=null;
        while(true) {
            clearstatcache(true,$output);
            if(filesize($output)>1024*1024) {proc_terminate($p);proc_close($p);throw new \RuntimeException('Hook output bound exceeded');}
            $status=proc_get_status($p);
            if(!$status['running']) { $code=$status['exitcode']; break; }
            if(microtime(true)>$deadline) { proc_terminate($p); proc_close($p); throw new \RuntimeException('Hook timeout: '.$name); }
            usleep(10000);
        }
        $closed=proc_close($p); $exit=$code>=0?$code:$closed;
        $out=file_get_contents($output);
        need($exit===0,'Hook failed: '.$name); $r=json($out);
        need(($r['ok']??false)===true,'Hook rejected: '.$name);
        return $r;
    }
    public function verify(string $package,array $run,array $artifact,array $jobs,array $envelope): array {
        Evidence::validate($this->c,$run,$artifact,$jobs,$envelope);
        need(digest($package)===$envelope['package_sha256'],'Package digest mismatch');
        $installed=is_file($this->private.'/installed.json')?json(file_get_contents($this->private.'/installed.json')):null;
        if ($installed!==null) {
            need($run['run_number'] >= $installed['run_number'],'Downgrade/late run refused');
            if ($run['run_number']===$installed['run_number']) need($installed['artifact_id']===$artifact['id'] &&
                $installed['package_sha256']===$envelope['package_sha256'],'Replaced same-order candidate refused');
        }
        foreach (array_unique(array_merge($this->c['data_contracts'],$installed['data_contracts']??[])) as $contract) {
            need(in_array($contract,$envelope['data_contracts']??[],true),'Missing data/writer compatibility: '.$contract);
        }
        $inventory=Package::inspect($package,$this->c['project']);
        if ($this->c['project']==='rumo10') {
            need(isset($inventory['files']['app/PlayerDevelopment.php']),'v3 writer missing');
        }
        // Persist only validation fields, never actor/author/commit-message or arbitrary API metadata.
        $run=array_intersect_key($run,array_flip(['id','workflow_id','head_branch','event','status','conclusion','head_sha','run_number','run_attempt','repository']));
        $run['repository']=['full_name'=>$run['repository']['full_name']];
        $artifact=array_intersect_key($artifact,array_flip(['id','name','expired','digest','workflow_run']));
        $artifact['workflow_run']=array_intersect_key($artifact['workflow_run'],array_flip(['id','head_sha']));
        $jobs=array_values(array_filter($jobs,fn($j)=>in_array($j['name'],$this->c['required_jobs'],true)));
        $jobs=array_map(static function($j){
            $j=array_intersect_key($j,array_flip(['name','status','conclusion','head_sha','run_id','_requested_attempt','steps']));
            $j['steps']=array_map(fn($step)=>['conclusion'=>$step['conclusion']??null],$j['steps']??[]);
            return $j;
        },$jobs);
        $envelope=array_intersect_key($envelope,array_flip(['schema','project','repository','commit','workflow_id','run_id','run_number','run_attempt','package_sha256','data_contracts']));
        return ['run_number'=>$run['run_number'],'commit'=>$run['head_sha'],'artifact_id'=>$artifact['id'],
            'package_sha256'=>$envelope['package_sha256'],'artifact_digest'=>$artifact['digest'],
            'data_contracts'=>$envelope['data_contracts'],'inventory'=>$inventory,
            'run'=>$run,'artifact'=>$artifact,'jobs'=>$jobs,'envelope'=>$envelope];
    }
    public function stage(string $package,array $run,array $artifact,array $jobs,array $envelope): array {
        $this->lock();
        $v=$this->verify($package,$run,$artifact,$jobs,$envelope);
        $dir=$this->private.'/stage-'.$artifact['id'].'-'.substr($v['package_sha256'],0,16);
        if (is_dir($dir)) {
            need(is_file($dir.'/ready.json'),'Incomplete stage retained; use a new artifact or reconcile privately');
            $old=json(file_get_contents($dir.'/ready.json'));
            need($old['package_sha256']===$v['package_sha256'],'Stage identity mismatch');
            Package::inspect($dir.'/release.tar.gz',$this->c['project']);
            $this->verifyPayload($dir,$old); return $old;
        }
        privateDir($dir); privateDir($dir.'/payload');
        need(copy($package,$dir.'/release.tar.gz'),'Stage copy failed');
        need(digest($dir.'/release.tar.gz')===$v['package_sha256'],'Stage copy corrupted');
        Package::inspect($dir.'/release.tar.gz',$this->c['project'],$dir.'/payload');
        if ($this->c['project']==='rumo10') need(str_contains(file_get_contents($dir.'/payload/app/PlayerDevelopment.php'),
            'development-v3-isolated'),'Unsupported v3 writer implementation');
        $v['stage']=$dir;
        $this->verifyPayload($dir,$v); record($dir.'/ready.json',$v); return $v;
    }
    private function verifyPayload(string $dir,array $v): void {
        foreach ($v['inventory']['files'] as $path=>$hash) if (!protectedPath($path))
            need(digest(pathAt($dir.'/payload',$path))===$hash,'Staged payload changed: '.$path);
    }
    private function controls(): array {
        $result=[];
        foreach (['index.php','.htaccess'] as $required)
            need(isset($this->c['pinned_controls'][$this->c['public'].'/'.$required]),'Missing mandatory public control pin');
        foreach ($this->c['pinned_controls'] as $path=>$expected) {
            need(digest($path)===$expected,'Public/private control drift: '.$path); $result[$path]=$expected;
        }
        return $result;
    }
    private function scanUnexpected(array $manifest): void {
        $allow=$this->c['unmanaged_allowlist']??[];
        foreach(['current','public'] as $kind) {
            $root=$this->c[$kind];
            $pins=array_fill_keys(array_map(fn($p)=>str_replace(chr(92),'/',$p),array_keys($this->c['pinned_controls'])),true);
            $filter=new \RecursiveCallbackFilterIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS),
                fn($entry)=>!protectedPath(str_replace(chr(92),'/',substr($entry->getPathname(),strlen($root)+1))));
            $iterator=new \RecursiveIteratorIterator($filter);
            foreach($iterator as $entry) {
                $rel=str_replace(chr(92),'/',substr($entry->getPathname(),strlen($root)+1));
                if(protectedPath($rel))continue;
                need(!$entry->isLink(),'Unapproved operational symlink');
                if($entry->isDir())continue;
                if($kind==='current' && ($rel==='release.json' || isset($manifest['files'][$rel])))continue;
                if($kind==='public' && isset($manifest['files']['public/'.$rel]) && !in_array($rel,['index.php','.htaccess'],true))continue;
                if(isset($pins[str_replace(chr(92),'/',$entry->getPathname())]))continue;
                $key=$kind.'/'.$rel;
                need(isset($allow[$key]) && digest($entry->getPathname())===$allow[$key],
                    'Unknown existing managed-path file preserved; reconciliation required: '.$key);
            }
        }
    }
    private function baseline(): array {
        $m=json(file_get_contents($this->c['current'].'/release.json'));
        if(is_file($this->private.'/installed.json')) {
            $installed=json(file_get_contents($this->private.'/installed.json'));
            need(digest($this->c['current'].'/release.json')===($installed['manifest_sha256']??''),'Installed checkpoint/manifest drift; manual reconciliation required');
        }
        need(($m['project']??null)===$this->c['project'] && is_array($m['files']??null),'Invalid installed manifest');
        foreach ($m['files'] as $p=>$hash) if (!protectedPath($p))
            need(digest(pathAt($this->c['current'],$p))===$hash,'Installed file drift: '.$p);
        $this->scanUnexpected($m);
        return $m;
    }
    private function operations(array $old,array $v): array {
        $ops=[]; $new=$v['inventory']['files'];
        $paths=array_unique(array_merge(array_keys($old['files']),array_keys($new))); sort($paths);
        foreach ($paths as $p) {
            if (protectedPath($p)) continue;
            $dest=pathAt($this->c['current'],$p);
            $before=sameFileState($dest); $after=$new[$p]??null;
            // A preexisting unknown file is never adopted just because its hash matches.
            need($before===null || isset($old['files'][$p]),'Unknown existing target: '.$p);
            if (($before['hash']??null)!==$after) $ops[]=['dest'=>$dest,'relative'=>$p,'before'=>$before,'after'=>$after,'state'=>'planned','public'=>false];
            if (str_starts_with($p,'public/') && !in_array($p,['public/index.php','public/.htaccess'],true)) {
                $q=substr($p,7); $pub=pathAt($this->c['public'],$q); $prior=sameFileState($pub);
                // Public mirror ownership must be explicitly inventoried and match baseline.
                $checkpoint=is_file($this->private.'/installed.json')?json(file_get_contents($this->private.'/installed.json')):null;
                $ownership=$checkpoint['public_inventory']??$this->c['public_inventory'];
                $known=$ownership[$q]??null;
                need($prior===null || ($known!==null && $known===$prior['hash']),'Unknown public mirror: '.$q);
                if (($prior['hash']??null)!==$after) $ops[]=['dest'=>$pub,'relative'=>$p,'before'=>$prior,'after'=>$after,'state'=>'planned','public'=>true];
            }
        }
        // Manifest is promoted LAST, after all content hashes and smoke.
        return $ops;
    }
    private function gate(array $v): array {
        need(($this->c['apply_enabled']??false)===true,'apply disabled by default');
        need(($this->c['homologation']['backup']??false) && ($this->c['homologation']['maintenance']??false) &&
            ($this->c['homologation']['recovery']??false) && ($this->c['homologation']['legacy_publishers_fenced']??false),
            'Unhomologated backup / drain / recovery / legacy-writer fencing');
        $request=['project'=>$this->c['project'],'candidate'=>$v,'private'=>$this->private];
        if(isset($this->c['update_policy'])){
            $request['update_policy']=UpdatePolicy::assess($this->baseline(),$v['inventory'],$this->c['update_policy'],$v['jobs']);
            need($request['update_policy']['class']!=='C','Class C requires separately proven exclusive window');
        }
        $g=$this->hook('preflight',$request);
        need(($g['legacy_publishers_fenced']??false) && ($g['source_unchanged']??false),'Source/concurrency fence not confirmed');
        need(!file_exists($this->marker()),'Foreign deployment lock exists');
        need(!file_exists($this->c['control'].'/deployment-journal.json') ||
            in_array(json(file_get_contents($this->c['control'].'/deployment-journal.json'))['state']??'', ['validated','recovered'],true),
            'Unresolved legacy FTPS journal');
        return $request;
    }
    public function apply(string $stage): array {
        $this->lock();
        need(!is_file($this->journal()) || in_array(json(file_get_contents($this->journal()))['state'],['validated','recovered'],true),
            'Pending journal: reconcile with apply --recover, never overwrite');
        need(str_starts_with(str_replace(chr(92),'/',realpath($stage)?:''),str_replace(chr(92),'/',realpath($this->private)).'/stage-'),'Stage outside private root');
        $v=json(file_get_contents($stage.'/ready.json'));
        $this->verify($stage.'/release.tar.gz',$v['run'],$v['artifact'],$v['jobs'],$v['envelope']);
        $this->verifyPayload($stage,$v);
        foreach ($this->c['data_contracts'] as $contract)
            need(in_array($contract,$this->c['baseline_data_contracts']??[],true),'Baseline writer incompatible: stage approved compatible recovery before apply');
        $installedPath=$this->private.'/installed.json';
        if(($this->c['apply_enabled']??false) && is_file($installedPath) && !file_exists($this->marker())) {
            $installed=json(file_get_contents($installedPath));
            if($installed['artifact_id']===$v['artifact_id'] && $installed['package_sha256']===$v['package_sha256']) {
                $this->baseline();$this->controls();$this->verifyRuntime($v['inventory']['files']);
                return ['state'=>'validated','commit'=>$v['commit'],'already_installed'=>true];
            }
        }
        $request=$this->gate($v); $controls=$this->controls(); $old=$this->baseline(); $ops=$this->operations($old,$v);
        // Never silently change Apache rules: public bridge and htaccess are pinned and preserved.
        need(digest($stage.'/payload/public/.htaccess')===$old['files']['public/.htaccess'],'Apache rule change needs separate approval');
        $id=bin2hex(random_bytes(16)); $backup=$this->private.'/backup-'.$id; privateDir($backup);
        $lockBytes=json_encode(['publisher'=>'rumo-pull-v1','project'=>$this->c['project'],'attempt'=>$id],JSON_THROW_ON_ERROR);
        $j=['state'=>'locking','attempt'=>$id,'lock_bytes'=>$lockBytes,'stage'=>$stage,'candidate'=>$v,
            'old_manifest'=>$old,'controls'=>$controls,'operations'=>$ops,'backup'=>$backup,'created_at'=>time(),'events'=>[]];
        $this->save($j);
        $handle=fopen($this->marker(),'xb'); need($handle!==false,'Cannot exclusively create deployment lock');
        need(fwrite($handle,$lockBytes)===strlen($lockBytes) && fflush($handle) && fsync($handle),'Lock write failed');
        fclose($handle); $this->own($j);
        try {
            $j['state']='maintenance'; $this->save($j);
            $m=$this->hook('maintenance', $request+['attempt'=>$id,'action'=>'verify-and-drain']);
            if(isset($request['update_policy']))UpdatePolicy::maintenance($m,$request['update_policy']);
            else need(($m['http_status']??0)===503 && ($m['writes_blocked']??false) &&
                ($m['inflight_writers']??-1)===0,'Maintenance/drain not demonstrated');
            $this->own($j);
            $j['private_backup']=PrivateBackup::capture($this->c['backup_roots']??[],$backup.'/private-files');
            $this->save($j);
            // Include code, managed public files and manifest; DB hook has to snapshot AND rehearse a restore privately.
            foreach ($j['operations'] as $i=>$op) if ($op['before']!==null) {
                need(sameFileState($op['dest'])===$op['before'],'Target changed before backup');
                need(copy($op['dest'],$backup.'/file-'.$i),'Code backup failed');
                need(digest($backup.'/file-'.$i)===$op['before']['hash'],'Backup code hash failed');
            }
            atomic($backup.'/release.json',file_get_contents($this->c['current'].'/release.json'));
            $b=$this->hook('backup',$request+['backup'=>$backup,'attempt'=>$id]);
            need(($b['consistent']??false) && ($b['restore_tested']??false) && ($b['attempt']??null)===$id &&
                isset($b['files']) && count($b['files'])>0,'Backup/restore proof missing');
            foreach ($b['files'] as $p=>$h) need(str_starts_with(str_replace(chr(92),'/',$p),str_replace(chr(92),'/',$backup).'/') &&
                digest($p)===$h,'Backup receipt digest invalid');
            $j['backup_receipt']=$b; $j['state']='installing'; $this->save($j);
            $this->checkpoint('after-backup');
            return $this->continueInstallation($j,$request);
        } catch (\Throwable $e) {
            $j['state']=$j['state']==='validated'?'validated':'blocked'; $j['events'][]=['at'=>time(),'error'=>$e->getMessage()]; $this->save($j);
            throw $e; // Lock retained; no DB restore and no automatic old writer.
        }
    }
    private function continueInstallation(array &$j,array $request):array {
        $v=$j['candidate'];$stage=$j['stage'];$id=$j['attempt'];
        if(!isset($j['migration_receipt']) && !empty($request['update_policy']['migrations'])) {
            $this->own($j);$j['state']='migrating';$this->save($j);
            $receipt=$this->hook('migrate',$request+['attempt'=>$id]);
            need(($receipt['compatible_expansion']??false)===true,'Incremental migration proof missing');
            $j['migration_receipt']=$receipt;$j['state']='installing';$this->save($j);
        }
            foreach ($j['operations'] as $i=>&$op) {
                if($op['state']==='confirmed') {
                    need(sameFileState($op['dest'])===($op['post']??null),'Confirmed destination drift');
                    continue;
                }
                if($op['state']==='intent' && ($now=sameFileState($op['dest']))!==$op['before']) {
                    $proof=$op['temp_proof']??null;
                    need($proof!==null&&($proof['ino']??0)!==0&&$now!==null&&$now['ino']===$proof['ino']&&$now['dev']===$proof['dev']&&$now['hash']===$op['after'],'Ambiguous write retained for reconciliation');
                    $op['post']=$now;$op['state']='confirmed';$this->save($j);continue;
                }
                if(isset($op['temp']) && file_exists($op['temp'])) {
                    $now=sameFileState($op['temp']);$creation=$op['temp_creation']??null;
                    need($creation!==null&&($creation['ino']??0)!==0&&$now['ino']===$creation['ino']&&$now['dev']===$creation['dev'],'Unknown temporary retained');
                    need(unlink($op['temp']),'Cannot remove proven incomplete temporary');
                }
                unset($op['temp'],$op['temp_creation'],$op['temp_proof']);
                $this->own($j); need(sameFileState($op['dest'])===$op['before'],'Target changed before operation');
                if ($op['after']!==null) {
                    $tmp=$op['dest'].'.pull-'.$id; parents($tmp,($op['public']??false)?0755:0750);
                    need(!file_exists($tmp),'Temporary destination collision');
                    $src=pathAt($stage.'/payload',$op['relative']);
                    $op['temp']=$tmp; $op['state']='temp-intent'; $this->save($j);
                    $input=fopen($src,'rb'); $output=fopen($tmp,'xb'); need($input!==false && $output!==false,'Cannot create install temporary');
                    $stat=fstat($output); $op['temp_creation']=['ino'=>$stat['ino'],'dev'=>$stat['dev']];
                    $op['state']='temp-created'; $this->save($j);
                    try { need($this->transfer($input,$output)===filesize($src) && fflush($output) && fsync($output),'Short install write'); }
                    finally { fclose($input); fclose($output); }
                    need(chmod($tmp,$op['before']['mode']??(($op['public']??false)?0644:0640)),'Cannot set target permissions'); need(digest($tmp)===$op['after'],'Install temporary corrupted');
                    $op['temp']=$tmp; $op['temp_proof']=sameFileState($tmp);
                }
                $op['state']='intent'; $this->save($j); $this->checkpoint('before-promotion-'.$i);
                $this->own($j); need(sameFileState($op['dest'])===$op['before'],'Target changed immediately before rename/delete');
                if ($op['after']===null) need($this->remove($op['dest']),'Managed deletion failed');
                else need($this->promote($op['temp'],$op['dest']),'Promotion rename failed');
                $this->checkpoint('after-promotion-'.$i);
                $op['post']=sameFileState($op['dest']);
                need(($op['post']['hash']??null)===$op['after'],'Post-promotion hash mismatch');
                $op['state']='confirmed'; $this->save($j);
            }
            unset($op);
            $this->cleanupTemps($j);
            $this->verifyInstalled($j);
            $this->hook('smoke',$request+['attempt'=>$id]);
            $this->own($j); $j['state']='promoting-manifest'; $this->save($j);
            $managed=array_filter($v['inventory']['files'],fn($p)=>!protectedPath($p),ARRAY_FILTER_USE_KEY);
            record($this->c['current'].'/release.json',['project'=>$this->c['project'],'files'=>$managed]);
            $this->checkpoint('after-manifest');
            need(json(file_get_contents($this->c['current'].'/release.json'))['files']===$managed,'Official manifest mismatch');
            $this->verifyInstalled($j);
            $publicInventory=[];
            foreach($managed as $p=>$hash) if(str_starts_with($p,'public/') && !in_array($p,['public/index.php','public/.htaccess'],true))
                $publicInventory[substr($p,7)]=$hash;
            record($this->private.'/installed.json',array_intersect_key($v,array_flip(['commit','run_number','artifact_id','package_sha256','data_contracts']))+
                ['manifest_sha256'=>digest($this->c['current'].'/release.json'),'public_inventory'=>$publicInventory]);
            $j['state']='validated'; $this->save($j); $this->checkpoint('before-unlock');
            $this->own($j); need(unlink($this->marker()),'Cannot release own maintenance lock');
            return ['state'=>'validated','commit'=>$v['commit']];
    }
    private function cleanupTemps(array $j): void {
        foreach($j['operations'] as $op) {
            if(!isset($op['temp']) || !file_exists($op['temp']))continue;
            $this->own($j); $now=sameFileState($op['temp']); $creation=$op['temp_creation']??null;
            need($creation!==null && ($creation['ino']??0)!==0 &&
                $now['ino']===$creation['ino'] && $now['dev']===$creation['dev'],
                'Unproven temporary preserved; maintenance retained: '.$op['relative']);
            if(isset($op['temp_proof']))need($now===$op['temp_proof'],'Temporary content changed; retained');
            need(unlink($op['temp']),'Owned temporary cleanup failed');
        }
    }
    private function verifyRuntime(array $files): void {
        $this->scanUnexpected(['files'=>$files]);
        foreach($files as $p=>$h)if(!protectedPath($p)){
            need(digest(pathAt($this->c['current'],$p))===$h,'Runtime content divergence: '.$p);
            if(str_starts_with($p,'public/')&&!in_array($p,['public/index.php','public/.htaccess'],true))
                need(digest(pathAt($this->c['public'],substr($p,7)))===$h,'Public mirror content divergence: '.$p);
        }
    }
    protected function verifyUnstartedBaseline(): void {
        $m=$this->baseline();$this->controls();$this->verifyRuntime($m['files']);
    }
    private function verifyInstalled(array $j,bool $requireOwner=true): void {
        if($requireOwner)$this->own($j); $this->controls();
        $this->scanUnexpected(['files'=>$j['candidate']['inventory']['files']]);
        foreach ($j['operations'] as $op) need((sameFileState($op['dest'])['hash']??null)===$op['after'],'Installed content divergence');
        $this->verifyRuntime($j['candidate']['inventory']['files']);
    }
    protected function completedWithoutLock(): array {
        need(!file_exists($this->marker()),'Unexpected deployment lock during completion');
        $j=json(file_get_contents($this->journal()));need($j['state']==='validated','Completed journal required');
        $installed=json(file_get_contents($this->private.'/installed.json'));$v=$j['candidate'];
        foreach(['artifact_id','package_sha256','commit'] as $key)need($installed[$key]===$v[$key],'Installed receipt identity mismatch');
        need(digest($this->c['current'].'/release.json')===$installed['manifest_sha256'],'Completed manifest drift');
        $managed=array_filter($v['inventory']['files'],fn($p)=>!protectedPath($p),ARRAY_FILTER_USE_KEY);
        need(json(file_get_contents($this->c['current'].'/release.json'))['files']===$managed,'Completed manifest inventory drift');
        $this->verifyInstalled($j,false);$this->hook('smoke',['project'=>$this->c['project'],'action'=>'finish-drained-completion']);
        return ['state'=>'validated','finalized'=>true];
    }
    public function recover(): array {
        $this->lock(); need(($this->c['apply_enabled']??false)===true,'apply/recovery disabled');
        $j=json(file_get_contents($this->journal())); $this->own($j);
        if($j['state']==='validated') {
            $this->verifyInstalled($j);
            need(json(file_get_contents($this->c['current'].'/release.json'))['files']===
                array_filter($j['candidate']['inventory']['files'],fn($p)=>!protectedPath($p),ARRAY_FILTER_USE_KEY),
                'Validated manifest drift; lock retained');
            $this->hook('smoke',['project'=>$this->c['project'],'action'=>'finalize','attempt'=>$j['attempt']]);
            $this->own($j);need(unlink($this->marker()),'Cannot finalize own lock');
            return ['state'=>'validated','finalized'=>true];
        }
        need(in_array($j['state'],['locking','blocked','installing','maintenance','migrating','promoting-manifest'],true),'No recoverable pending attempt');
        if(isset($this->c['update_policy']) && isset($j['backup_receipt']) && count(array_filter($j['operations'],fn($o)=>$o['state']!=='planned'))>0) {
            $v=$j['candidate'];$this->verify($j['stage'].'/release.tar.gz',$v['run'],$v['artifact'],$v['jobs'],$v['envelope']);$this->verifyPayload($j['stage'],$v);
            $policy=UpdatePolicy::assess($j['old_manifest'],$v['inventory'],$this->c['update_policy'],$v['jobs']);need($policy['class']!=='C','Forward recovery requires approved compatible policy');
            $request=['project'=>$this->c['project'],'candidate'=>$v,'private'=>$this->private,'update_policy'=>$policy];
            $f=$this->hook('fence',$request);need(($f['legacy_publishers_fenced']??false)===true,'Recovery fence missing');
            $m=$this->hook('maintenance',$request+['action'=>'verify-admission','attempt'=>$j['attempt']]);UpdatePolicy::maintenance($m,$policy);
            foreach($j['backup_receipt']['files'] as $path=>$hash)need(digest($path)===$hash,'Recovery backup drift');
            // New penalty states cannot be opened by the former engine. Finish the verified new release, never downgrade the writer.
            return $this->continueInstallation($j,$request);
        }
        $untouched=count(array_filter($j['operations'],fn($o)=>$o['state']!=='planned' || isset($o['temp'])))===0;
        if($untouched && in_array($j['state'],['locking','maintenance','blocked'],true)) {
            need($this->baseline()['files']===$j['old_manifest']['files'],'Pre-transfer abort baseline drift');
            $this->controls();$this->own($j);
            $this->hook('smoke',['project'=>$this->c['project'],'action'=>'abort-before-transfer','attempt'=>$j['attempt']]);
            $j['state']='recovered';$this->save($j);$this->own($j);need(unlink($this->marker()),'Cannot abort own untouched attempt');
            return ['state'=>'recovered','content_transferred'=>false,'database_restored'=>false];
        }
        // The old code must satisfy CURRENT data-writer requirements. Dump files are NEVER imported.
        foreach ($this->c['data_contracts'] as $contract)
            need(in_array($contract,$this->c['baseline_data_contracts']??[],true),'Old writer incompatible; use approved forward-compatible recovery package');
        $m=$this->hook('maintenance',['action'=>'verify-and-drain','attempt'=>$j['attempt'],'project'=>$this->c['project']]);
        if(isset($this->c['update_policy']))UpdatePolicy::maintenance($m,UpdatePolicy::assess($j['old_manifest'],$j['candidate']['inventory'],$this->c['update_policy'],$j['candidate']['jobs']));
        else need(($m['http_status']??0)===503 && ($m['writes_blocked']??false) && ($m['inflight_writers']??-1)===0,'Recovery maintenance/drain not demonstrated');
        try {
            foreach (array_reverse(array_keys($j['operations'])) as $i) {
                $op=&$j['operations'][$i]; $this->own($j);
                $now=sameFileState($op['dest']);
                if ($op['state']==='recovered' || $now===$op['before']) continue;
                if ($op['state']==='intent') {
                    // Hash/intention alone NEVER prove creation. Inode/device proof across rename required.
                    $t=$op['temp_proof']??null;
                    need($t!==null && ($t['ino']??0)!==0 && $now!==null &&
                        $now['ino']===$t['ino'] && $now['dev']===$t['dev'] &&
                        $now['hash']===$t['hash'],'Ambiguous promotion; preserved for manual reconciliation: '.$op['relative']);
                } else need($op['state']==='confirmed' && $now===($op['post']??null),
                    'Changed/unknown residual preserved: '.$op['relative']);
                if ($op['before']===null) need(unlink($op['dest']),'Recovery deletion refused');
                else {
                    $saved=$j['backup'].'/file-'.$i;
                    need(digest($saved)===$op['before']['hash'],'Backup drift');
                    $tmp=$op['dest'].'.recover-'.$j['attempt']; need(!file_exists($tmp),'Recovery temp collision');
                    need(copy($saved,$tmp) && digest($tmp)===$op['before']['hash'],'Recovery copy failure');
                    need(chmod($tmp,$op['before']['mode']),'Cannot restore target permissions');
                    need(rename($tmp,$op['dest']),'Recovery rename failure');
                }
                $op['state']='recovered'; $this->save($j); unset($op);
            }
            unset($op); // End any reference retained by a skipped recovery entry.
            foreach ($j['operations'] as $op) need((sameFileState($op['dest'])['hash']??null)===($op['before']['hash']??null),'Recovery content mismatch');
            $this->cleanupTemps($j); $this->controls(); $this->own($j);
            atomic($this->c['current'].'/release.json',file_get_contents($j['backup'].'/release.json'));
            need($this->baseline()['files']===$j['old_manifest']['files'],'Recovered manifest mismatch');
            $this->hook('smoke',['project'=>$this->c['project'],'action'=>'recovery','attempt'=>$j['attempt']]);
            $j['state']='recovered'; $this->save($j); $this->own($j); need(unlink($this->marker()),'Cannot release recovered lock');
            return ['state'=>'recovered','database_restored'=>false];
        } catch (\Throwable $e) {
            $j['state']=$j['state']==='validated'?'validated':'blocked'; $j['events'][]=['at'=>time(),'error'=>$e->getMessage()]; $this->save($j); throw $e;
        }
    }
}
