<?php
declare(strict_types=1);
// Migra somente ecohub.rumo10_* para rumohub, no mesmo servidor MySQL 8.
// Sem --apply, apenas valida e apresenta o plano. Credenciais/backup nunca vão ao Git.
if(PHP_SAPI!=='cli')exit;
ini_set('zend.exception_ignore_args','1');
require dirname(__DIR__).'/app/bootstrap.php';
$root=dirname(__DIR__);$source='ecohub';$target='rumohub';$lock=$root.'/storage/database-migration.lock';
$moved=false;$ownedLock=false;$envChanged=false;$backup=null;$db=null;$originalEnv=null;$tables=[];
function migrationTables(PDO $db,string $schema):array{
 $q=$db->prepare('SELECT TABLE_NAME,ENGINE,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME');$q->execute([$schema]);return $q->fetchAll();
}
function migrationSnapshot(PDO $db,string $schema,array $tables):array{
 $out=[];
 foreach($tables as $table){
  $keys=$db->query("SHOW KEYS FROM `$schema`.`$table` WHERE Key_name='PRIMARY'")->fetchAll();usort($keys,fn($a,$b)=>$a['Seq_in_index']<=>$b['Seq_in_index']);
  if(!$keys)throw new RuntimeException('Tabela sem chave primária.');
  $order=implode(',',array_map(fn($k)=>'`'.$k['Column_name'].'`',$keys));$hash=hash_init('sha256');$count=0;
  $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
  $q=$db->query("SELECT * FROM `$schema`.`$table` ORDER BY $order");while($row=$q->fetch(PDO::FETCH_NUM)){hash_update($hash,serialize($row));$count++;}$q->closeCursor();
  $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);
  $out[$table]=['rows'=>$count,'sha256'=>hash_final($hash)];
 }return $out;
}
function migrationRename(PDO $db,string $source,string $target,array $tables):void{
 $pairs=array_map(fn($t)=>"`$source`.`$t` TO `$target`.`$t`",$tables);$db->exec('RENAME TABLE '.implode(',',$pairs));
}
try{
 $db=Database::connect();$version=$db->query('SELECT VERSION()')->fetchColumn();
 if(!preg_match('/^8\./',$version))throw new RuntimeException('Este procedimento foi preparado para MySQL 8.');
 if(config('DB_DATABASE')===$target){echo "Já configurado em rumohub. Nenhuma alteração executada.\n";exit;}
 if(config('DB_DATABASE')!==$source)throw new RuntimeException('A origem configurada não é ecohub.');
 $src=migrationTables($db,$source);$dst=migrationTables($db,$target);if(!$dst)throw new RuntimeException('Destino rumohub não instalado.');
 foreach($src as $t)if(str_starts_with($t['TABLE_NAME'],'rumo10_')){
  if(!preg_match('/^rumo10_[a-z_]+$/',$t['TABLE_NAME']) || $t['ENGINE']!=='InnoDB' || $t['TABLE_TYPE']!=='BASE TABLE')throw new RuntimeException('Objeto não suportado.');$tables[]=$t['TABLE_NAME'];
 }
 if(!$tables)throw new RuntimeException('Não há tabelas do jogo na origem.');
 if(array_intersect($tables,array_column($dst,'TABLE_NAME')))throw new RuntimeException('Há tabelas de destino com nomes iguais.');
 $db->query('SELECT 1 FROM rumohub.rh_catalogo_metadata LIMIT 1')->fetchColumn();
 $fk=$db->query("SELECT CONSTRAINT_SCHEMA,CONSTRAINT_NAME,TABLE_SCHEMA,TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_NAME IS NOT NULL AND (TABLE_SCHEMA='ecohub' OR REFERENCED_TABLE_SCHEMA='ecohub') ORDER BY CONSTRAINT_SCHEMA,CONSTRAINT_NAME,ORDINAL_POSITION")->fetchAll();
 foreach($fk as $f)if(in_array($f['TABLE_NAME'],$tables,true) || in_array($f['REFERENCED_TABLE_NAME'],$tables,true)){
  if($f['TABLE_SCHEMA']!==$source || $f['REFERENCED_TABLE_SCHEMA']!==$source || !in_array($f['TABLE_NAME'],$tables,true) || !in_array($f['REFERENCED_TABLE_NAME'],$tables,true))throw new RuntimeException('Dependência com outro projeto.');
 }
 $triggers=$db->query("SELECT EVENT_OBJECT_TABLE FROM information_schema.TRIGGERS WHERE EVENT_OBJECT_SCHEMA='ecohub'")->fetchAll(PDO::FETCH_COLUMN);
 if(array_intersect($tables,$triggers))throw new RuntimeException('Mover tabelas com triggers exige outro procedimento.');
 $views=$db->query("SELECT TABLE_NAME FROM information_schema.VIEWS WHERE VIEW_DEFINITION LIKE '%ecohub%'")->fetchAll();if($views)throw new RuntimeException('Há views relacionadas à origem.');
 $constraints=$db->query("SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='rumohub'")->fetchAll(PDO::FETCH_COLUMN);
 if(array_intersect(array_column($fk,'CONSTRAINT_NAME'),$constraints))throw new RuntimeException('Conflito de nomes de vínculos no destino.');
 echo 'Plano: '.count($tables).' tabelas rumo10_* de ecohub para rumohub; '.count($dst).' tabelas existentes no destino preservadas.'.PHP_EOL;
 if(!in_array('--apply',$argv,true)){echo "Somente leitura. Use --apply para backup e migração.\n";exit;}
 $dump='C:/Program Files/MySQL/MySQL Server 8.0/bin/mysqldump.exe';foreach($argv as $arg)if(str_starts_with($arg,'--mysqldump='))$dump=substr($arg,12);
 if(!is_file($dump))throw new RuntimeException('Informe o executável oficial mysqldump instalado.');
 $originalEnv=file_get_contents($root.'/.env');if(!preg_match('/^DB_DATABASE=ecohub[ \t]*\r?$/m',$originalEnv))throw new RuntimeException('Configuração privada divergente.');
 $flag=fopen($lock,'x');if(!$flag)throw new RuntimeException('Já há manutenção em andamento.');fwrite($flag,'Migração local em andamento.');fclose($flag);$ownedLock=true;
 $backup=$root.'/storage/backups/migration-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));if(!mkdir($backup,0700,true))throw new RuntimeException('Não foi possível criar backup privado.');
 if(file_put_contents($backup.'/.env.before',$originalEnv)!==strlen($originalEnv))throw new RuntimeException('Backup de configuração falhou.');
 echo "Manutenção temporária ativa. Preparando backup privado.\n";
 $before=migrationSnapshot($db,$source,$tables);
 $encode=fn($v)=>'"'.str_replace(['\\','"',"\n","\r"],['\\\\','\\"','\\n','\\r'],$v).'"';
 $client=$backup.'/client.cnf';$settings="[client]\nhost=".$encode(config('DB_HOST'))."\nport=".config('DB_PORT')."\nuser=".$encode(config('DB_USERNAME'))."\npassword=".$encode(config('DB_PASSWORD'))."\n";
 if(file_put_contents($client,$settings)===false)throw new RuntimeException('Configuração privada de backup falhou.');
 try{
  $args=[$dump,'--defaults-extra-file='.$client,'--single-transaction','--skip-lock-tables','--no-tablespaces','--set-gtid-purged=OFF','--column-statistics=0','--hex-blob','--complete-insert','--skip-add-drop-table',$source,...$tables];
  $process=proc_open($args,[0=>['pipe','r'],1=>['file',$backup.'/rumo10.sql','w'],2=>['file',$backup.'/dump-errors.log','w']],$pipes,null,null,['bypass_shell'=>true]);if(!is_resource($process))throw new RuntimeException('Backup não iniciado.');fclose($pipes[0]);$code=proc_close($process);if($code!==0)throw new RuntimeException('Backup não concluído.');
 }finally{if(is_file($client))unlink($client);}
 if(filesize($backup.'/rumo10.sql')<100)throw new RuntimeException('Backup vazio.');
 if(migrationSnapshot($db,$source,$tables)!==$before)throw new RuntimeException('Dados mudaram durante o backup; nenhuma tabela será movida.');
 $manifest=json_encode(['source'=>$source,'target'=>$target,'tables'=>$before,'dump_sha256'=>hash_file('sha256',$backup.'/rumo10.sql')],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
 if(file_put_contents($backup.'/manifest.json',$manifest)!==strlen($manifest))throw new RuntimeException('Manifesto do backup não concluído.');
 $db->exec('SET SESSION lock_wait_timeout=30');migrationRename($db,$source,$target,$tables);$moved=true;
 if(migrationSnapshot($db,$target,$tables)!==$before)throw new RuntimeException('Validação de conteúdo divergente.');
 $newFk=$db->query("SELECT TABLE_NAME,REFERENCED_TABLE_SCHEMA FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='rumohub' AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll();
 foreach($newFk as $f)if(in_array($f['TABLE_NAME'],$tables,true) && $f['REFERENCED_TABLE_SCHEMA']!==$target)throw new RuntimeException('Vínculo não atualizado.');
 if(array_intersect($tables,array_column(migrationTables($db,$source),'TABLE_NAME')))throw new RuntimeException('Origem ainda contém tabelas movidas.');
 $updated=preg_replace('/^DB_DATABASE=ecohub[ \t]*\r?$/m','DB_DATABASE=rumohub',$originalEnv,1);$envChanged=true;if(file_put_contents($root.'/.env',$updated)!==strlen($updated))throw new RuntimeException('Não foi possível atualizar conexão.');
 file_put_contents($backup.'/completed.json',json_encode(['tables'=>count($tables),'rows'=>array_sum(array_column($before,'rows')),'completed_at'=>gmdate('c')],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
 unlink($lock);$ownedLock=false;
 echo 'OK: '.count($tables).' tabelas movidas; conteúdo SHA-256 e vínculos conferidos. .env aponta para rumohub.'.PHP_EOL;
 echo 'Backup privado: '.$backup.PHP_EOL;
}catch(Throwable $e){
 $restored=true;
 if($moved){try{migrationRename($db,$target,$source,$tables);if($envChanged && file_put_contents($root.'/.env',$originalEnv)!==strlen($originalEnv))throw new RuntimeException('Configuração não restaurada.');}catch(Throwable $rollback){$restored=false;}}
 if($ownedLock && $restored && is_file($lock))unlink($lock);
 fwrite(STDERR,'Migração não concluída. Código: '.$e->getCode().'. '.($restored?'Origem preservada/restaurada.':'Manutenção mantida: retorno automático não concluído.').' Consulte o backup privado; nenhum detalhe sensível é exibido.'.PHP_EOL);exit(1);
}
