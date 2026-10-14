<?php
declare(strict_types=1);
// Fotografia para PRIMEIRA instalação no banco vazio escolhido no phpMyAdmin.
// Somente leitura local. Nunca exporta usuários, hashes de senha ou carreiras.
if(PHP_SAPI!=='cli')exit(1);
ini_set('zend.exception_ignore_args','1');
require dirname(__DIR__).'/app/bootstrap.php';
$output=$argv[1]??dirname(__DIR__).'/storage/deploy/rumohub-inicial.sql.gz';
if(!str_ends_with($output,'.sql.gz') || file_exists($output))throw new RuntimeException('Informe um arquivo .sql.gz novo.');
if(!is_dir(dirname($output)) && !mkdir(dirname($output),0700,true))throw new RuntimeException('Diretório indisponível.');
$db=Database::connect();$tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$ddl=[];$dependencies=[];
foreach($tables as $table){
 if(!preg_match('/^(rh|rumo10)_[a-z_]+$/',$table))continue;
 $sql=$db->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
 // Não carregar contadores de contas/carreiros locais para uma instalação nova.
 $sql=preg_replace('/ AUTO_INCREMENT=\d+/','',$sql);
 preg_match_all('/REFERENCES `([^`]+)`/',$sql,$refs);$dependencies[$table]=array_values(array_diff($refs[1],[$table]));$ddl[$table]=$sql;
}
$ordered=[];while(count($ordered)<count($ddl)){
 $before=count($ordered);
 foreach($ddl as $table=>$sql)if(!isset($ordered[$table]) && !array_diff($dependencies[$table],array_keys($ordered)))$ordered[$table]=$sql;
 if(count($ordered)===$before)throw new RuntimeException('Dependência externa ou circular inesperada.');
}
$dataTables=['rh_clubes','rh_competicoes','rh_jogadores','rh_elenco_vinculos','rh_catalogo_metadata','rh_ratings_ea','rh_selecoes','rumo10_clubes','rumo10_situacoes','rumo10_opcoes','rumo10_migrations','rumo10_competicoes','rumo10_clubes_oficiais','rumo10_catalogo_clubes'];
foreach($dataTables as $table)if(!isset($ddl[$table]))throw new RuntimeException('Tabela esperada ausente.');
$stream=gzopen($output,'wb6');if(!$stream)throw new RuntimeException('Não foi possível criar SQL.');
$write=static function(string $s)use($stream):void{if(gzwrite($stream,$s)!==strlen($s))throw new RuntimeException('Falha ao gravar SQL.');};
$counts=[];
try{
 $write("-- RumoHub + Rumo10: primeira instalação. Selecionar BANCO VAZIO no phpMyAdmin.\n-- Não usar como atualização nem importar novamente em produção existente.\n-- Sem CREATE DATABASE, USE, DROP ou dados de usuários/carreiros locais.\nSET NAMES utf8mb4;\nSET SESSION time_zone = '+00:00';\n");
 foreach($ordered as $table=>$sql)$write($sql.";\n");
 $write("START TRANSACTION;\n");
 $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('SET TRANSACTION READ ONLY');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
 foreach($dataTables as $table){
  $query=$db->query('SELECT * FROM `'.$table.'`');$counts[$table]=0;$batch=[];$bytes=0;$prefix='';
  $flush=static function()use(&$batch,&$bytes,&$prefix,$write):void{if($batch){$write($prefix.implode(",\n",$batch).";\n");$batch=[];$bytes=0;}};
  while($row=$query->fetch()){
   // applied_at de migrations é instalação nova, não histórico dos usuários.
   $columns=implode(',',array_map(static fn($k)=>'`'.$k.'`',array_keys($row)));
   $values=implode(',',array_map(static fn($v)=>$v===null?'NULL':"CONVERT(X'".bin2hex((string)$v)."' USING utf8mb4)",array_values($row)));
   $prefix='INSERT INTO `'.$table.'` ('.$columns.') VALUES '."\n";
   $batch[]='('.$values.')';$bytes+=strlen($values);$counts[$table]++;
   if(count($batch)>=250 || $bytes>=524288)$flush();
  }$flush();$query->closeCursor();
 }
 $db->commit();
 $write("INSERT INTO rh_hub_games (slug,nome,descricao,url_publica,logo_caminho,status,ordem) VALUES\n('rumo10','Rumo à Camisa 10','Construa sua carreira como jogador.','https://www.rumoacamisa10.com.br','/assets/rumo10.svg','disponivel',1),\n('rumotecnico','Rumo Técnico','Carreira de treinador — nome provisório.',NULL,NULL,'planejado',2),\n('rumoagente','Rumo Agente','Gestão de atletas, empresas e clubes — nome provisório.',NULL,NULL,'planejado',3);\nCOMMIT;\n");
 gzclose($stream);
 file_put_contents($output.'.json',json_encode(['schema_tables'=>array_keys($ordered),'reference_rows'=>$counts,'games'=>3,'sha256'=>hash_file('sha256',$output)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
 echo count($ordered).' tabelas e '.array_sum($counts).' registros de referência exportados, sem contas/carreiros: '.$output.PHP_EOL;
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw new RuntimeException('SQL incompleto. Não importar.',0,$e);}
