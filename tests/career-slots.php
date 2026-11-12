<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';

if (config('DB_HOST') !== '127.0.0.1' || config('DB_DATABASE') !== 'rumo10_homolog' || CareerBenefits::enabled()) {
    throw new RuntimeException('Este teste requer rumo10_homolog local e benefícios centrais desligados.');
}
$r = new Repository(Database::connect());
$accounts = [];
$checks = 0;

function slotCheck(bool $ok, string $message): void {
    global $checks;
    $checks++;
    if (!$ok) throw new RuntimeException($message);
}
function slotRejected(callable $action): bool {
    try {
        $action();
        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
}

try {
    foreach (['free', 'local-paid'] as $type) {
        $r->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)', [
            "slots-$type-".bin2hex(random_bytes(12)).'@example.invalid',
            password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT),
        ]);
        $accounts[] = (int)$r->db->lastInsertId();
    }
    [$free, $paid] = $accounts;
    $r->query("INSERT INTO rumo10_conta_planos(conta_id,plano) VALUES(?,'pago')", [$paid]);
    slotCheck(CareerSlots::quota($r, $free) === ['plan'=>'gratuito','limit'=>2,'used'=>0], 'Duas vagas-base para conta vazia');
    slotCheck(CareerSlots::quota($r, $paid) === ['plan'=>'gratuito','limit'=>2,'used'=>0], 'Plano local pago não comprova benefício');
    $game = fn(string $name) => Game::legacyCreate($name, 'São Paulo', 'SP', 'meia');
    $r->create($free, $game('Primeira'));
    slotCheck(CareerSlots::quota($r, $free)['used'] === 1, 'Uma carreira deixa uma vaga livre');
    $r->create($free, $game('Segunda'), true);
    slotCheck(CareerSlots::quota($r, $free)['used'] === 2 && count($r->archives($free)) === 1, 'Carreira arquivada retomável ocupa vaga');
    slotCheck(slotRejected(fn()=>$r->create($free, $game('Excedente'), true)), 'Terceira rejeitada');

    // Reconstitui uma terceira carreira arquivada de antes da regra, apenas nesta conta sintética.
    $r->query("INSERT INTO rumo10_carreiras(conta_id,nome,cidade,uf,posicao,idade,etapa,clube)
        VALUES(NULL,'Legada de teste','São Paulo','SP','meia',16,'handicap','União do Bairro')");
    $legacy = (int)$r->db->lastInsertId();
    $r->query('INSERT INTO rumo10_carreiras_arquivadas(carreira_id,conta_id) VALUES(?,?)', [$legacy, $free]);
    $r->query("INSERT INTO rumo10_origem_vagas(carreira_id,tipo) VALUES(?,'LEGADO')", [$legacy]);
    $r->query('INSERT INTO rumo10_universos(carreira_id,estado_json) VALUES(?,?)', [
        $legacy, json_encode(['id'=>$legacy] + $game('Legada de teste'), JSON_THROW_ON_ERROR),
    ]);
    slotCheck(CareerSlots::quota($r, $free)['used'] === 3, 'Três carreiras legadas são mantidas');
    slotCheck(slotRejected(fn()=>$r->create($free, $game('Quarta'), true)), 'Quarta rejeitada');
    $r->restore($free, $legacy);
    slotCheck((int)$r->load($free)['id'] === $legacy && CareerSlots::quota($r, $free)['used'] === 3, 'Legada pode ser retomada acima da cota');
    slotCheck(slotRejected(fn()=>CareerSlots::delete($r, $paid, $legacy, 'delete')), 'Não exclui carreira alheia');
    slotCheck(slotRejected(fn()=>CareerSlots::delete($r, $free, $legacy, '')), 'Exige confirmação para excluir');
    echo "OK: $checks verificações de vagas e preservação.\n";
} finally {
    foreach ($accounts as $account) {
        foreach (CareerSlots::listing($r, $account) as $career) {
            CareerSlots::delete($r, $account, (int)$career['id'], 'delete');
        }
        $r->query('DELETE FROM rumo10_contas WHERE id=?', [$account]);
    }
}
