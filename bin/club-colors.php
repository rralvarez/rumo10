<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
// Ferramenta local administrativa. Sem endpoint HTTP nem credenciais em argumentos.
if(count($argv)<6) { fwrite(STDERR,"Uso: php bin/club-colors.php slug nome1 '#HEX001' nome2 '#HEX002' [nome3 '#HEX003']\n"); exit(1); }
[$slug,$n1,$h1,$n2,$h2]=array_slice($argv,1,5); $n3=$argv[6]??null; $h3=$argv[7]??null;
if(!preg_match('/^[a-z0-9-]{2,80}$/',$slug) || ($n3!==null && $h3===null)) throw new InvalidArgumentException('Identificação/terceira cor inválida.');
foreach([$h1,$h2,$h3] as $h) if($h!==null && !preg_match('/^#[A-Fa-f0-9]{6}$/',$h)) throw new InvalidArgumentException('HEX inválido.');
foreach([$n1,$n2,$n3] as $n) if($n!==null && (mb_strlen($n)<2 || mb_strlen($n)>30)) throw new InvalidArgumentException('Nome da cor inválido.');
$r=new Repository(Database::connect());
if(!$r->query('SELECT id FROM rumo10_clubes WHERE slug=?',[$slug])->fetchColumn()) throw new InvalidArgumentException('Clube não encontrado.');
$r->query('UPDATE rumo10_clubes SET cor_primaria_nome=?,cor_primaria_hex=?,cor_secundaria_nome=?,cor_secundaria_hex=?,cor_terciaria_nome=?,cor_terciaria_hex=? WHERE slug=?',[$n1,strtoupper($h1),$n2,strtoupper($h2),$n3,$h3?strtoupper($h3):null,$slug]);
echo "Cores aproximadas atualizadas. Seed preserva edição local.\n";
