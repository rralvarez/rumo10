<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/lib/RequestDrain.php';
use Rumo\Publisher\RequestDrain;use function Rumo\Publisher\{need,json,digest,protectedPath,pathAt};
try{
 need(PHP_SAPI==='cli'&&($argv[2]??'')==='--profile','Private site hook only');$mode=$argv[1]??'';$file=$argv[3]??'';
 need(is_file($file)&&!is_link($file),'Private site profile required');if(PHP_OS_FAMILY!=='Windows')need((fileperms($file)&0077)===0,'Profile must be 0600');
 $c=json(file_get_contents($file));$q=json(stream_get_contents(STDIN));need(($q['project']??null)===$c['project'],'Project mismatch');
 foreach(['current','public','control'] as $k)need(is_dir($c[$k])&&!is_link($c[$k]),'Physical root required');
 foreach($c['pinned_controls'] as $p=>$h)need(is_file($p)&&!is_link($p)&&digest($p)===$h,'Control drift');
 if($mode==='maintenance'){
  $compatible=isset($q['update_policy'])&&in_array($q['update_policy']['class'],['A','B'],true)&&($q['update_policy']['compatible_writers_ci']??false);if(!$compatible){$g=new RequestDrain($c['gate_root']);need($g->closed(),'Account gate is open');}$marker=json(file_get_contents($c['control'].'/deployment.lock'));need(($marker['attempt']??null)===($q['attempt']??null)&&isset($q['attempt']),'Deployment attempt mismatch');
  need(is_array($c['maintenance_urls']??null)&&count($c['maintenance_urls'])>=2,'Both maintenance endpoints required');
  foreach($c['maintenance_urls'] as $url){$u=parse_url($url);$local=($c['test_only_loopback']??false)&&in_array($u['host']??'',['127.0.0.1','localhost'],true)&&str_starts_with(str_replace(chr(92),'/',realpath($file)),str_replace(chr(92),'/',realpath(dirname(__DIR__).'/tests')).'/');
   need(($u['scheme']??'')==='https'||($local&&($u['scheme']??'')==='http'),'TLS required');need(!isset($u['user'])&&!isset($u['pass']),'No credentials in URL');
   foreach(['GET','POST'] as $method){$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);$body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_errno($ch);curl_close($ch);need($err===0&&$status===503&&is_string($body)&&hash('sha256',trim($body))===$c['maintenance_body_sha256'],'Maintenance HTTP proof failed');}
  }
  if($compatible){$f=$c['fence_hook'];need(digest($f['script'])===$f['sha256'],'Fence provider drift');$p=proc_open(array_merge([PHP_BINARY,$f['script'],'fence'],$f['args']),[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['file','/dev/null','a']],$pipes);need(is_resource($p),'Fence process missing');$fence=json(stream_get_contents($pipes[1]));fclose($pipes[1]);need(proc_close($p)===0&&($fence['legacy_publishers_fenced']??false),'Fence unavailable');}
  // Zero writers is asserted ONLY by DrainPublisher holding the actual exclusive lease, not by this HTTP probe.
  $out=['ok'=>true,'http_status'=>503,'gate_closed'=>!$compatible,'writes_blocked'=>false,'inflight_writers'=>-1,'new_requests_blocked'=>true,'publisher_exclusive'=>$compatible];
 }elseif(in_array($mode,['preflight','smoke'],true)){
  $m=json(file_get_contents($c['current'].'/release.json'));$files=($mode==='smoke'?($q['candidate']['inventory']['files']??$m['files']):$m['files']);
  need($m['project']===$c['project'],'Manifest project mismatch');
  foreach($files as $p=>$h){if(protectedPath($p))continue;need(digest(pathAt($c['current'],$p))===$h,'Runtime hash mismatch');if(str_starts_with($p,'public/')&&!in_array($p,['public/index.php','public/.htaccess'],true))need(digest(pathAt($c['public'],substr($p,7)))===$h,'Public mirror mismatch');}
  $out=['ok'=>true,'source_unchanged'=>true];
  if($mode==='preflight'){
   // Fence is independently re-evaluated by the audited CLI provider, never a stale boolean.
   $f=$c['fence_hook'];need(digest($f['script'])===$f['sha256'],'Fence provider drift');$cmd=array_merge([PHP_BINARY,$f['script'],'fence'],$f['args']);
  }else{
   $f=$c['qa_probe'];need(digest($f['script'])===$f['sha256'],'QA probe drift');$cmd=array_merge([PHP_BINARY,$f['script']],$f['args']);
  }
  $tmp=tmpfile();$err=tmpfile();$p=proc_open($cmd,[0=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','r'],1=>$tmp,2=>$err],$pipes);need(is_resource($p),'Read-only probe unavailable');$end=microtime(true)+30;$exit=-1;
  do{$s=proc_get_status($p);if(!$s['running']){$exit=$s['exitcode'];break;}if(microtime(true)>$end){proc_terminate($p);break;}usleep(10000);}while(true);proc_close($p);rewind($tmp);$raw=stream_get_contents($tmp,1024*1024);fclose($tmp);fclose($err);need($exit===0,'Read-only proof failed');$receipt=json($raw);need(($receipt['ok']??false)===true,'Proof rejected');
  if($mode==='preflight'){need(($receipt['legacy_publishers_fenced']??false)===true,'Fence not confirmed');$out['legacy_publishers_fenced']=true;}else need(($receipt['read_only']??false)===true,'QA probe must be audited read-only');
 }else throw new RuntimeException('Unknown operational hook');
 echo json_encode($out,JSON_THROW_ON_ERROR);
}catch(Throwable $e){fwrite(STDERR,'Operational site proof failed; maintenance must be retained'.PHP_EOL);exit(1);}
