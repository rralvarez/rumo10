<?php
declare(strict_types=1);
spl_autoload_register(function(string $name): void {
    if (preg_match('/^[A-Za-z]+$/', $name)) {
        $file = __DIR__ . '/' . $name . '.php';
        if (is_file($file)) require $file;
    }
});
$env = dirname(__DIR__) . '/.env';
if (is_file($env)) foreach (file($env, FILE_IGNORE_NEW_LINES) as $line) {
    if (preg_match('/^([A-Z_][A-Z0-9_]*)=(.*)$/', trim($line), $m) && getenv($m[1]) === false) {
        putenv($m[1] . '=' . trim($m[2], "\"'"));
    }
}
date_default_timezone_set('UTC');
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function config(string $key, string $default = ''): string { return getenv($key) !== false ? (string)getenv($key) : $default; }
