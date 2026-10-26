<?php
declare(strict_types=1);
namespace Rumo\Publisher;
require_once __DIR__.'/Core.php';
require_once __DIR__.'/Zip.php';

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
    protected function request(string $url,bool $auth,int $limit,?string $out=null): array {
        self::checkUrl($url,$auth);
        $last=null;
        for ($attempt=0;$attempt<2;$attempt++) {
            $headers=[]; $body=''; $count=0; $fh=null;
            if ($out!==null) { need(!is_link($out),'Linked download target'); $fh=fopen($out,'wb'); need($fh!==false,'Download target failed'); chmod($out,0600); }
            $ch=curl_init($url);
            $req=['Accept: application/vnd.github+json','X-GitHub-Api-Version: 2022-11-28'];
            if ($auth) $req[]='Authorization: Bearer '.$this->token;
            curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>$req,CURLOPT_USERAGENT=>'Rumo-private-pull/1',
                CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>180,
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
            if (in_array($err,[CURLE_SSL_CACERT,CURLE_PEER_FAILED_VERIFICATION,CURLE_SSL_CONNECT_ERROR],true)) break;
        }
        throw new \RuntimeException('HTTPS GET failed; partial output not trusted (curl '.$last.')');
    }
    private function api(string $path): array {
        need(str_starts_with($path,'/repos/'.$this->c['repository'].'/'),'API path outside configured repository');
        $r=$this->request('https://api.github.com'.$path,true,4*1024*1024);
        need($r['status']===200,'GitHub API status '.$r['status']); return json($r['body']);
    }
    public function discover(): array {
        $base='/repos/'.$this->c['repository'].'/actions';
        $r=$this->api($base.'/workflows/'.$this->c['workflow_id'].'/runs?branch='.rawurlencode($this->c['branch']).'&event=push&status=success&per_page=100');
        $runs=$r['workflow_runs']??[];
        need(count($runs)>0,'No successful authorized workflow run');
        usort($runs,fn($a,$b)=>$b['run_number']<=>$a['run_number']);
        $run=$runs[0];
        $a=$this->api($base.'/runs/'.$run['id'].'/artifacts?per_page=100');
        need(($a['total_count']??0)<=100,'Artifact discovery pagination bound; choose explicit IDs');
        $name='release-'.$this->c['project'].'-'.$run['head_sha'];
        $found=array_values(array_filter($a['artifacts']??[],fn($v)=>$v['name']===$name && !$v['expired']));
        need(count($found)===1,'No unique artifact for latest successful run; no fallback to old run');
        return [$run['id'],$found[0]['id']];
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
        $this->assertDescendant($checkpoint['commit']??($this->c['baseline_commit']??''),$run['head_sha']);
        $path=$this->c['workflow_path']??'';
        need(preg_match('#^\.github/workflows/[a-zA-Z0-9_.-]+\.ya?ml$#D',$path)===1,'Pinned workflow path required');
        $definition=$this->api('/repos/'.$this->c['repository'].'/contents/'.$path.'?ref='.$run['head_sha']);
        need(($definition['encoding']??null)==='base64','Workflow content encoding invalid');
        $definitionBytes=base64_decode($definition['content']??'',true);
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
