<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$db=Database::connect();$checks=0;
function unifiedCheck(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
unifiedCheck($db->query('SELECT DATABASE()')->fetchColumn()==='rumohub','Conexão do jogo usa banco rumohub.');
$tables=$db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='rumohub'")->fetchAll(PDO::FETCH_COLUMN);
unifiedCheck(count(array_filter($tables,fn($t)=>str_starts_with($t,'rumo10_')))===27,'27 tabelas operacionais/legadas do jogo no banco unificado.');
unifiedCheck(count(array_filter($tables,fn($t)=>str_starts_with($t,'rh_')))===15,'15 tabelas compartilhadas/admin do Hub preservadas.');
unifiedCheck((int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='ecohub' AND LEFT(TABLE_NAME,7)='rumo10_'")->fetchColumn()===0,'Nenhuma segunda cópia do jogo em ecohub.');
$keys=$db->query("SELECT CONSTRAINT_NAME,TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='rumohub' AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION")->fetchAll();$groups=[];
foreach($keys as $key){unifiedCheck($key['REFERENCED_TABLE_SCHEMA']==='rumohub','Referência aponta para banco unificado.');$groups[$key['CONSTRAINT_NAME']][]=$key;}
foreach($groups as $columns){
 $first=$columns[0];$join=[];$present=[];
 foreach($columns as $c){foreach(['TABLE_NAME','COLUMN_NAME','REFERENCED_TABLE_NAME','REFERENCED_COLUMN_NAME'] as $name)if(!preg_match('/^[a-zA-Z0-9_]+$/',$c[$name]))throw new RuntimeException('Identificador não suportado.');$join[]='c.`'.$c['COLUMN_NAME'].'`=p.`'.$c['REFERENCED_COLUMN_NAME'].'`';$present[]='c.`'.$c['COLUMN_NAME'].'` IS NOT NULL';}
 $sql='SELECT 1 FROM `'.$first['TABLE_NAME'].'` c LEFT JOIN `'.$first['REFERENCED_TABLE_NAME'].'` p ON '.implode(' AND ',$join).' WHERE '.implode(' AND ',$present).' AND p.`'.$first['REFERENCED_COLUMN_NAME'].'` IS NULL LIMIT 1';
 unifiedCheck($db->query($sql)->fetchColumn()===false,'Sem registros órfãos em '.$first['TABLE_NAME']);
}
echo "OK: $checks verificações do banco único rumohub, tabelas e integridade referencial.\n";
