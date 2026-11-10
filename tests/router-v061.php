<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli-server')exit;
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($path,'/assets/')&&is_file(dirname(__DIR__).'/public'.$path))return false;
require dirname(__DIR__).'/public/index.php';
