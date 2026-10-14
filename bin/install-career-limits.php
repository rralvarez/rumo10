<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
$db=Database::connect();
foreach(explode(';',file_get_contents(dirname(__DIR__).'/database/003.sql')) as $sql){$sql=trim($sql);if(!$sql)continue;if(!str_starts_with($sql,'CREATE TABLE IF NOT EXISTS rumo10_conta_planos '))throw new RuntimeException('Migration fora do escopo.');$db->exec($sql);}
$db->exec("INSERT IGNORE INTO rumo10_migrations(version) VALUES('003')");
echo "OK: limites de carreiras preparados; contas sem plano atribuído usam gratuito. Nenhuma carreira excluída.\n";
