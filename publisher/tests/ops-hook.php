<?php
// SYNTHETIC HOOK ONLY; operational GitHub fencing must use authenticated live checks.
if(($argv[1]??'')==='fence'){echo json_encode(['ok'=>true,'legacy_publishers_fenced'=>true]);exit;}
require __DIR__.'/hook.php';
