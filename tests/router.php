<?php
// Exclusivo para php -S local. IIS e Apache usam suas próprias regras de rewrite.
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/');
if(preg_match('#^(/assets/(brand/)?[a-zA-Z0-9._-]+\.(css|js|svg|png)|/favicon\.(ico|svg))$#',$path) && is_file(dirname(__DIR__).'/public'.$path)) return false;
require dirname(__DIR__).'/public/index.php';
