<?php
declare(strict_types=1);
final class Database {
    public static function connect(): PDO {
        $db = config('DB_DATABASE', 'rumohub');
        $host = config('DB_HOST', '127.0.0.1');
        $port = config('DB_PORT', '3306');
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $db) || !preg_match('/^[a-zA-Z0-9.:-]+$/', $host) || !ctype_digit($port)) throw new RuntimeException('Configuração de banco inválida.');
        if (config('DB_USERNAME') === '') throw new RuntimeException('Configure o .env local antes de instalar.');
        return new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", config('DB_USERNAME'), config('DB_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    }
}
