<?php
// LOCAL SYNTHETIC FIXTURE ONLY, never packaged or installed in production.
$c=json_decode(file_get_contents(__DIR__.'/sandbox-hook-state.json'),true);
if(is_file($c['control'].'/deployment.lock')){http_response_code(503);echo 'maintenance';exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 $db=new SQLite3($c['db']);$db->exec("INSERT OR REPLACE INTO careers VALUES(2,'synthetic write probe')");
}
echo 'available';
