<?php
declare(strict_types=1);

$pdo = new PDO("mysql:host=127.0.0.1;port=33306;dbname=r10_f01_test;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$baseDir = __DIR__;

// 1. Migrações base
$migrationFiles = [
    $baseDir . '/database/001.sql',
    $baseDir . '/database/002.sql',
    $baseDir . '/database/003.sql',
    $baseDir . '/database/004.sql',
    $baseDir . '/database/005-growth.sql',
    $baseDir . '/database/006-balance-and-maintenance.sql',
];

foreach ($migrationFiles as $file) {
    echo "Aplicando: " . basename($file) . "... ";
    $sql = file_get_contents($file);
    $pdo->exec($sql);
    echo "OK\n";
}

// 2. Executar seed_balance.php
echo "Executando seed_balance_data... ";
require_once $baseDir . '/database/seed_balance.php';
seed_balance_data($pdo);
echo "OK\n";

// 3. Migrações posteriores
$remainingFiles = [
    $baseDir . '/database/007-recalibracao-ofensiva.sql',
    $baseDir . '/database/008-penalties-balance.sql',
    $baseDir . '/database/migrations/006-achievements.sql',
    $baseDir . '/database/migrations/007-reconciliation-feedback.sql',
    $baseDir . '/database/009-f01-canonical-telemetry.sql',
];

foreach ($remainingFiles as $file) {
    echo "Aplicando: " . basename($file) . "... ";
    $sql = file_get_contents($file);
    $pdo->exec($sql);
    echo "OK\n";
}

// 4. Criar tabela auxiliar de contas caso não exista
$pdo->exec("
    CREATE TABLE IF NOT EXISTS rumo10_contas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        senha_hash VARCHAR(255) NOT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
");

echo "Todas as migrações e tabelas aplicadas com sucesso!\n";
