<?php
// Preview local com dados fictícios, sem conta real nem conexão com MySQL.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(preg_match('#^(/assets/(brand/)?[\w.-]+\.(css|js|svg|png)|/favicon\.(ico|svg))$#',$path) && is_file(dirname(__DIR__).'/public'.$path)) return false;
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit;}
define('RUMO10_PREVIEW',true);
require __DIR__.'/presentation.php';
echo renderSample($path==='/fim-semana',$path==='/chance-clara');
