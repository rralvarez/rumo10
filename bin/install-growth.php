<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(PHP_SAPI!=='cli' || !in_array('--local', $argv,true) || config('APP_ENV','local')!=='local')throw new RuntimeException('Use CLI --local somente após backup. Produção: importar SQL revisado.');
$db=Database::connect();if($db->query('SELECT DATABASE()')->fetchColumn()!=='rumohub')throw new RuntimeException('Banco físico local inesperado.');
$files=['C:/inetpub/wwwroot/rumohub/database/005-beneficios.sql',dirname(__DIR__).'/database/005-growth.sql'];
foreach($files as $file)foreach(explode(';',file_get_contents($file)) as $sql){$sql=trim($sql);if(!$sql)continue;if(!preg_match('/^(CREATE TABLE IF NOT EXISTS (rh_|rumo10_)|INSERT IGNORE INTO (rh_|rumo10_))/',$sql))throw new RuntimeException('SQL fora do escopo aditivo.');$db->exec($sql);}
$db->exec("INSERT IGNORE INTO rh_primeiras_carreiras(game_id,usuario_id,carreira_id,chave) SELECT 'rumo10',i.hub_usuario_id,MIN(o.carreira_id),SHA2(CONCAT('legacy-first|',i.hub_usuario_id),256) FROM rumo10_origem_vagas o JOIN rumo10_carreiras c ON c.id=o.carreira_id LEFT JOIN rumo10_carreiras_arquivadas a ON a.carreira_id=c.id JOIN rumo10_identidades i ON i.conta_id=COALESCE(c.conta_id,a.conta_id) GROUP BY i.hub_usuario_id");
echo "OK: migrações aditivas locais; planos globais e catálogos preservados. Flags não foram ativadas.\n";
