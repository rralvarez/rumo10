<?php
declare(strict_types=1);
namespace Rumo\Publisher;

function need(bool $condition, string $message): void {
    if (!$condition) throw new \RuntimeException($message);
}
function json(string $text): array {
    $value = json_decode($text, true, 64, JSON_THROW_ON_ERROR);
    need(is_array($value), 'JSON object required');
    return $value;
}
function digest(string $file): string {
    need(is_file($file) && !is_link($file), 'Regular file required: '.$file);
    return hash_file('sha256', $file);
}
function atomic(string $file, string $bytes): void {
    $tmp = $file.'.new-'.bin2hex(random_bytes(8));
    $stream = fopen($tmp, 'xb');
    need($stream !== false, 'Cannot create private temporary file');
    try {
        chmod($tmp, 0600);
        $offset = 0;
        while ($offset < strlen($bytes)) {
            $written = fwrite($stream, substr($bytes, $offset));
            need($written !== false && $written > 0, 'Short write / disk full');
            $offset += $written;
        }
        need(fflush($stream) && fsync($stream), 'Cannot sync file');
    } finally { fclose($stream); }
    need(rename($tmp, $file), 'Atomic file replacement failed');
}
function record(string $file, array $value): void {
    atomic($file, json_encode($value, JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
}
function relative(string $path): void {
    need($path !== '' && !str_contains($path, '\\') && !str_contains($path, "\0") &&
        !str_contains($path, ':') && !str_starts_with($path, '/') &&
        !preg_match('#(^|/)\.\.?(/|$)|//|[\x00-\x1f]#', $path), 'Unsafe archive path: '.$path);
    foreach(explode('/', $path) as $component) {
        need(!preg_match('/[ .]$/D', $component) && !preg_match('/[<>"|?*]/', $component) &&
            !preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\\..*)?$/iD', $component),
            'Nonportable/ambiguous path component');
    }
}
function protectedPath(string $path): bool {
    relative($path);
    $parts = explode('/', strtolower($path));
    foreach ($parts as $part) {
        if (in_array($part, ['.env','clients.php','storage','media','sessions','saves','uploads',
            '.git','.ssh','.well-known','cgi-bin','.user.ini','php.ini','error_log','credentials','secrets','maintenance.lock','deployment.lock',
            'deployment-journal.json','pull-journal.json'], true) ||
            str_starts_with($part, '.env.') || preg_match('/\.(pem|key|token|dump|bak)$/', $part)) return true;
    }
    return false;
}
function pathAt(string $root, string $relative): string {
    relative($relative);
    $path = rtrim($root, '/\\');
    need(is_dir($path) && !is_link($path), 'Physical root required');
    foreach (explode('/', $relative) as $part) {
        $path .= DIRECTORY_SEPARATOR.$part;
        need(!is_link($path), 'Symlink in destination path: '.$relative);
    }
    return $path;
}
function parents(string $file,int $mode=0700): void {
    $missing=[];$dir=dirname($file);
    while(!is_dir($dir)) { need(!is_link($dir),'Linked parent refused');$missing[]=$dir;$next=dirname($dir);need($next!==$dir,'Missing filesystem root');$dir=$next; }
    if($missing)need(mkdir(dirname($file),$mode,true),'Cannot create parent directory');
    foreach($missing as $created)need(chmod($created,$mode),'Cannot set new directory mode');
}
function privateDir(string $path): void {
    need(!is_link($path), 'Private directory must not be a link');
    if (!is_dir($path)) need(mkdir($path, 0700, true), 'Cannot create private directory');
    if (PHP_OS_FAMILY !== 'Windows') need((fileperms($path) & 0077) === 0, 'Private directory must be 0700');
}
function sameFileState(string $file): ?array {
    if (!file_exists($file)) return null;
    $stat = lstat($file);
    need(!is_link($file) && is_file($file) && $stat['nlink'] === 1, 'Linked/special target refused');
    return ['hash'=>digest($file), 'bytes'=>$stat['size'], 'ino'=>$stat['ino'], 'dev'=>$stat['dev'], 'mode'=>$stat['mode']&0777];
}

/** Strict sequential TAR reader: no extractTo, no links/PAX/devices or duplicate names. */
final class Package {
    public static function inspect(string $file, string $project, ?string $out = null): array {
        $gz = gzopen($file, 'rb'); need($gz !== false, 'Cannot open package');
        $seen = []; $hashes = []; $manifest = null; $bytes = 0; $ended = false;
        try {
            while (!gzeof($gz)) {
                $h = self::read($gz,512);
                if ($h === str_repeat("\0",512)) {
                    need(self::read($gz,512) === str_repeat("\0",512), 'Missing TAR end');
                    while (!gzeof($gz)) need(trim(gzread($gz,8192), "\0") === '', 'Data after TAR end');
                    $ended=true; break;
                }
                $name = rtrim(substr($h,0,100),"\0");
                $prefix = rtrim(substr($h,345,155),"\0");
                if ($prefix !== '') $name = $prefix.'/'.$name;
                $type = $h[156];
                $name = rtrim($name,'/');
                relative($name);
                need(!isset($seen[strtolower($name)]), 'Duplicate/case-colliding TAR entry: '.$name); $seen[strtolower($name)]=true;
                need(count($seen) <= 15000, 'Too many files');
                $sum = array_sum(unpack('C*',substr_replace($h,str_repeat(' ',8),148,8)));
                need(trim(substr($h,148,8), " \0") !== '' && octdec(trim(substr($h,148,8), " \0")) === $sum, 'Invalid TAR checksum');
                $sz = rtrim(substr($h,124,12), " \0");
                need(preg_match('/^[0-7]+$/D', $sz) === 1, 'Invalid TAR size');
                $size = octdec($sz); $bytes += $size;
                need($size <= 64*1024*1024 && $bytes <= 512*1024*1024, 'Package expansion limit');
                need(in_array($type, ["\0",'0','5'], true), 'TAR links/extensions/special entries refused');
                if ($type === '5') { need($size===0, 'Directory payload refused'); continue; }
                need($name==='release.json' || preg_match('#^(app|bin|database|public|deploy|storage)/#',$name)===1, 'Unmanaged package root');
                $ctx = hash_init('sha256'); $data = ''; $sink = null;
                if ($out !== null && $name !== 'release.json' && !protectedPath($name)) {
                    $dest = pathAt($out,$name); parents($dest); $sink=fopen($dest,'xb'); need($sink!==false,'Extract create failed'); chmod($dest,0600);
                }
                try {
                    $left=$size;
                    while ($left>0) {
                        $chunk=self::read($gz,min($left,65536)); $left-=strlen($chunk);
                        hash_update($ctx,$chunk);
                        if ($name==='release.json') {
                            need($size<=4*1024*1024, 'Manifest too big'); $data.=$chunk;
                        }
                        if ($sink!==null) need(fwrite($sink,$chunk)===strlen($chunk),'Extraction short write');
                    }
                    if ($sink!==null) need(fflush($sink) && fsync($sink),'Extract sync failed');
                } finally { if ($sink!==null) fclose($sink); }
                if ($name==='release.json') $manifest=json($data); else $hashes[$name]=hash_final($ctx);
                if ($size%512) self::read($gz,512-($size%512));
            }
        } finally { gzclose($gz); }
        need($ended && is_array($manifest) && ($manifest['project']??null)===$project, 'Missing/wrong package manifest');
        $expected=$manifest['files']??null; need(is_array($expected),'Missing file inventory');
        ksort($expected); ksort($hashes);
        need($expected===$hashes,'Manifest inventory/hash mismatch');
        foreach ($expected as $path=>$hash) { relative($path); need(preg_match('/^[a-f0-9]{64}$/D',$hash)===1,'Invalid file digest'); }
        foreach (['app/bootstrap.php','public/index.php','public/.htaccess'] as $required) need(isset($expected[$required]),'Missing required runtime file');
        return ['project'=>$project,'files'=>$expected,'bytes'=>$bytes,'archive_sha256'=>digest($file)];
    }
    private static function read($gz,int $bytes): string {
        $data=''; while(strlen($data)<$bytes) {
            $part=gzread($gz,$bytes-strlen($data));
            need($part!==false && $part!=='','Truncated compressed TAR'); $data.=$part;
        } return $data;
    }
}

final class Evidence {
    public static function validate(array $c,array $m, array $a,array $jobs,array $e): void {
        need(($m['repository']['full_name']??null)===$c['repository'],'Wrong repository');
        need(($m['workflow_id']??null)===$c['workflow_id'],'Wrong workflow');
        need(($m['head_branch']??null)===$c['branch'] && ($m['event']??null)==='push','Unauthorized branch/event');
        need(($m['status']??null)==='completed' && ($m['conclusion']??null)==='success','Unsuccessful run');
        need(preg_match('/^[a-f0-9]{40}$/D',$m['head_sha']??'')===1,'Invalid commit SHA');
        need(($a['workflow_run']['id']??null)===$m['id'] &&
             ($a['workflow_run']['head_sha']??null)===$m['head_sha'],'Artifact/run binding failed');
        need(($a['name']??null)==='release-'.$c['project'].'-'.$m['head_sha'] &&
            ($a['expired']??true)===false,'Wrong/expired artifact');
        need(preg_match('/^sha256:[a-f0-9]{64}$/D',$a['digest']??'')===1,'Missing artifact digest');
        need(($e['project']??null)===$c['project'] && ($e['repository']??null)===$c['repository'] &&
            ($e['commit']??null)===$m['head_sha'] && ($e['run_id']??null)===$m['id'] &&
            ($e['run_attempt']??null)===$m['run_attempt'] &&
            ($e['workflow_id']??null)===$m['workflow_id'],'Envelope provenance mismatch');
        need(($e['run_number']??null)===$m['run_number'],'Envelope order mismatch');
        need(($e['schema']??null)==='rumo-pull-v1','Unsupported envelope');
        foreach ($c['required_jobs'] as $required) {
            $found=array_values(array_filter($jobs,fn($j)=>($j['name']??null)===$required));
            need(count($found)===1 && ($found[0]['conclusion']??null)==='success' &&
                ($found[0]['status']??null)==='completed' &&
                ($found[0]['head_sha']??null)===$m['head_sha'] &&
                ($found[0]['run_id']??null)===$m['id'] &&
                ($found[0]['_requested_attempt']??null)===$m['run_attempt'],'Mandatory job not approved: '.$required);
            foreach ($found[0]['steps']??[] as $step) {
                need(!in_array($step['conclusion']??'', ['failure','cancelled','timed_out','skipped'],true),'Required job contains unsuccessful/skipped step');
            }
        }
        need(is_int($m['run_number']??null) && $m['run_number']>0,'Missing release order');
        need(preg_match('/^[a-f0-9]{64}$/D',$e['package_sha256']??'')===1,'Invalid package digest');
    }
}
