<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';$r=new Repository(Database::connect());
$rows=$r->query('SELECT COUNT(*) FROM rumo10_elenco_jogadores')->fetchColumn();$expected=ClubSquad::catalog()['summary']['real_players']+ClubSquad::catalog()['summary']['fictional_players'];
if((int)$r->query('SELECT COUNT(*) FROM rumo10_catalogo_clubes')->fetchColumn()<count(Competition::clubs()))throw new RuntimeException('Clubes sem identidade estável.');
if((int)$rows!==$expected)throw new RuntimeException('Quantidade de vínculos diverge do catálogo.');
$m=$r->query('SELECT p.nome,p.overall_referencia,e.clube FROM rumo10_jogadores_catalogo p JOIN rumo10_elenco_jogadores e ON e.jogador_id=p.id WHERE p.id=?',['espn:45843'])->fetchAll();
if(count($m)!==1 || $m[0]['clube']!=='Inter Miami CF-US' || (int)$m[0]['overall_referencia']!==89)throw new RuntimeException('Identidade compartilhada de Messi incorreta.');
$ambiguous=$r->query('SELECT COUNT(*) FROM (SELECT jogador_id FROM rumo10_elenco_jogadores GROUP BY jogador_id HAVING COUNT(*)>1) x')->fetchColumn();if($ambiguous)throw new RuntimeException('Jogador associado simultaneamente a vários clubes do catálogo atual.');
echo 'OK: catálogo global normalizado; '.(int)$rows." vínculos, identidade de Messi e ausência de associação ambígua.\n";
