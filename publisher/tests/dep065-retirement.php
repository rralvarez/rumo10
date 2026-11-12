<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/lib/RequestDrain.php';

use Rumo\Publisher\RequestDrain;
use function Rumo\Publisher\{digest,json,need,privateDir,record};

if (in_array($argv[1] ?? '', ['fence', 'smoke'], true)) {
    echo '{"ok":true,"legacy_publishers_fenced":true}';
    exit;
}

$root = str_replace('\\', '/', sys_get_temp_dir()).'/r10-dep065-'.bin2hex(random_bytes(6));
privateDir($root);

function runCase(string $root, string $case, ?string $rejection): void
{
    $dir = $root.'/'.$case;
    foreach (['current', 'public', 'private', 'control'] as $kind) {
        privateDir($dir.'/'.$kind);
    }
    $public = $dir.'/public';
    $private = $dir.'/private';
    $current = $dir.'/current';
    $control = $dir.'/control';
    $gatePath = $dir.'/gate';
    RequestDrain::provision($gatePath);
    $gate = new RequestDrain($gatePath);
    $owner = bin2hex(random_bytes(16));
    $gate->close($owner);

    file_put_contents($current.'/game.php', '<?php // baseline');
    file_put_contents($public.'/index.php', '<?php // bridge');
    file_put_contents($public.'/.htaccess', '# protected bridge');
    record($current.'/release.json', ['project'=>'rumo10', 'files'=>['game.php'=>digest($current.'/game.php')]]);
    record($private.'/installed.json', ['manifest_sha256'=>digest($current.'/release.json')]);
    record($private.'/journal.json', ['state'=>'validated']);
    $journalHash = digest($private.'/journal.json');
    $before = [
        'current/game.php'=>digest($current.'/game.php'),
        'current/release.json'=>digest($current.'/release.json'),
        'public/.htaccess'=>digest($public.'/.htaccess'),
        'public/index.php'=>digest($public.'/index.php'),
        'public/testeCodex/.htaccess'=>$case==='wrong-hash' ? str_repeat('0', 64) :
            'fd5720626d7a72b9a808212700660d5dab8698c5200975d49a0e6942dfae2af7',
    ];
    record($private.'/drain-session.json', [
        'owner'=>$owner, 'state'=>'blocked', 'content_before'=>$before, 'journal_before'=>$journalHash,
    ]);
    if ($case==='other-drift') file_put_contents($current.'/game.php', '<?php // changed');
    if ($case==='folder-present') privateDir($public.'/testeCodex');
    if ($case==='started-install') file_put_contents($control.'/deployment.lock', 'pending');

    $hook = ['script'=>__FILE__, 'sha256'=>digest(__FILE__)];
    record($private.'/config.json', [
        'project'=>'rumo10', 'private'=>$private, 'current'=>$current, 'public'=>$public, 'control'=>$control,
        'apply_enabled'=>true, 'account_mutex'=>$dir.'/account.lock', 'php'=>PHP_BINARY,
        'request_gate'=>['root'=>$gatePath, 'coverage_approved'=>true, 'projects'=>['rumo10','rumohub'],
            'entrypoints'=>[$public.'/index.php'=>$before['public/index.php'],
                $public.'/.htaccess'=>$before['public/.htaccess']]],
        'pinned_controls'=>[$public.'/index.php'=>$before['public/index.php'],
            $public.'/.htaccess'=>$before['public/.htaccess']],
        'hooks'=>['fence'=>$hook, 'smoke'=>$hook],
    ]);
    $args = [PHP_BINARY, dirname(__DIR__).'/bin/controlled-pull.php',
        '--config='.$private.'/config.json', '--mode=apply', '--recover'];
    if ($case!=='without-authorization') $args[] = '--retire-testecodex';
    $process = proc_open($args, [1=>['file',$dir.'/stdout','w'], 2=>['file',$dir.'/stderr','w']], $pipes);
    need(is_resource($process), 'Cannot start receiver');
    $exit = proc_close($process);
    need(digest($private.'/journal.json')===$journalHash, 'Recovery changed the journal');
    $session = json(file_get_contents($private.'/drain-session.json'));
    need($session['content_before']===$before, 'Recovery changed the original inventory');
    if ($rejection!==null) {
        need($exit===1 && str_contains(file_get_contents($dir.'/stderr'), $rejection), 'Unexpected rejection: '.$case);
        need($gate->closed() && $session['state']==='blocked', 'Rejection released the gate');
    } else {
        need($exit===0 && $session['state']==='recovered' && !$gate->closed(),
            'Recovery failed: '.file_get_contents($dir.'/stderr'));
        need($session['authorized_retirement']==='public/testeCodex', 'Missing retirement record');
    }
    echo "PASS $case\n";
}

runCase($root, 'authorized-retirement', null);
runCase($root, 'other-drift', 'Unstarted attempt code drift');
runCase($root, 'without-authorization', 'Unstarted attempt code drift');
runCase($root, 'wrong-hash', 'Authorized testeCodex retirement not demonstrated');
runCase($root, 'folder-present', 'Authorized testeCodex retirement not demonstrated');
runCase($root, 'started-install', 'Retirement requires an unstarted installation');
