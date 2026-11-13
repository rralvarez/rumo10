<?php
declare(strict_types=1);

final class AdminController {
    public static function handle(string $action): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if ($action === 'login') {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                self::processLogin();
                return;
            }
            self::renderLogin();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'login') {
            $csrf = $_POST['csrf'] ?? null;
            if (!is_string($csrf) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) { http_response_code(403); exit('Invalid CSRF token'); }
        }
        if (in_array($action, ['logout','maintenance_toggle','balance_save','balance_activate','balance_rollback'], true) && $_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
        if ($action === 'logout') {
            unset($_SESSION['is_admin'], $_SESSION['admin_user']);
            header('Location: /admin/login?msg=' . urlencode('Sessão administrativa encerrada com segurança.'));
            exit;
        }

        self::ensureAuth();
        $db = Database::connect();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            match($action) {
                'maintenance_toggle' => self::saveMaintenance($db),
                'balance_save' => self::saveBalance($db),
                'balance_activate' => self::activateBalance($db),
                'balance_rollback' => self::rollbackBalance($db),
                default => self::renderDashboard($db, 'Ação desconhecida.')
            };
            return;
        }

        match($action) {
            'simulate' => self::renderSimulation($db),
            default => self::renderDashboard($db)
        };
    }

    private static function ensureAuth(): void {
        if (!empty($_SESSION['is_admin'])) {
            return;
        }

        // Verifica se a conta conectada tem autorização por e-mail configurado
        $userEmail = $_SESSION['hub_user']['email'] ?? null;
        $adminEmails = array_filter(array_map('trim', explode(',', (string)config('ADMIN_EMAILS', ''))));
        if ($userEmail && in_array($userEmail, $adminEmails, true)) {
            $_SESSION['is_admin'] = true;
            $_SESSION['admin_user'] = $userEmail;
            return;
        }

        header('Location: /admin/login');
        exit;
    }

    private static function processLogin(): void {
        $csrf = (string)($_POST['csrf'] ?? '');
        if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
            self::renderLogin('Token CSRF inválido. Recarregue a página.');
            return;
        }

        $password = (string)($_POST['admin_password'] ?? '');
        $expected = (string)config('ADMIN_PASSWORD', config('ADMIN_SECRET_KEY', ''));

        if ($expected === '' || !hash_equals($expected, $password)) {
            // Pequeno atraso para mitigar força bruta
            usleep(200000);
            self::renderLogin('Credencial administrativa incorreta.');
            return;
        }

        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_user'] = 'admin';
        header('Location: /admin?msg=' . urlencode('Autenticação administrativa estabelecida com sucesso.'));
        exit;
    }

    private static function renderLogin(string $error = ''): void {
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
        $msg = (string)($_GET['msg'] ?? '');
        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login Administrativo · Rumo à Camisa 10</title>
    <link rel="stylesheet" href="/assets/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/admin.css">
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <div class="brand">Rumo à Camisa 10</div>
            <h1 class="h5 text-light mb-1">Acesso Administrativo</h1>
            <p class="text-secondary small mb-0">Governança, Balanceamento e Manutenção</p>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-info py-2 small bg-dark text-info border-secondary"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small bg-dark text-danger border-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/admin/login">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
            <div class="mb-3">
                <label for="pwd" class="form-label text-secondary small">Senha Administrativa</label>
                <input type="password" id="pwd" name="admin_password" class="form-control" required autofocus placeholder="Digite a chave autorizada">
            </div>
            <button type="submit" class="btn btn-admin">Entrar no Painel</button>
        </form>
        <div class="text-center mt-3">
            <a href="/" class="text-secondary small text-decoration-none">← Retornar ao jogo público</a>
        </div>
    </div>
</body>
</html>
        <?php
        exit;
    }

    private static function saveMaintenance(PDO $db): void {
        $active = !empty($_POST['manutencao_ativa']);
        $titulo = trim((string)($_POST['manutencao_titulo'] ?? 'Estamos preparando a próxima temporada!'));
        $mensagem = trim((string)($_POST['manutencao_mensagem'] ?? ''));
        $previsao = trim((string)($_POST['manutencao_previsao'] ?? ''));

        Maintenance::setMaintenance($active, $titulo, $mensagem, $previsao, $_SESSION['admin_user'] ?? 'admin');
        header('Location: /admin?msg=Manutenção atualizada com sucesso');
        exit;
    }

    private static function parameterInput(mixed $value, ?string $type = null): string {
        return ($type === 'json' || is_array($value)) ? json_encode($value, JSON_THROW_ON_ERROR) : (string)$value;
    }

    private static function saveBalance(PDO $db): void {
        try {
            $db->beginTransaction();
            $formulaId = trim((string)($_POST['formula_id'] ?? ''));
            $params = $_POST['param'] ?? null;
            if ($formulaId === '' || !is_array($params)) throw new InvalidArgumentException('Parâmetros inválidos.');
            $newVersion = self::saveFormulaVersion(
                $db, $formulaId, $params, (int)($_POST['balance_id'] ?? 0), (int)($_POST['version_id'] ?? 0)
            );
            $db->commit();
            BalanceService::resetCache();
            header('Location: /admin?msg=Fórmula ' . urlencode($formulaId) . ' atualizada para v' . $newVersion);
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            header('Location: /admin?error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    private static function saveFormulaVersion(PDO $db, string $formulaId, array $params, int $balanceId, int $versionId): int {
        $active = $db->query("SELECT id FROM rumo10_balanceamentos WHERE estado='ativo' ORDER BY ativado_em DESC, id DESC LIMIT 1 FOR UPDATE");
        if ($balanceId < 1 || (int)$active->fetchColumn() !== $balanceId) {
            throw new InvalidArgumentException('O conjunto ativo mudou. Recarregue o painel.');
        }
        $link = $db->prepare('SELECT v.id, v.versao FROM rumo10_balanceamento_formulas bf JOIN rumo10_formula_versoes v ON v.id=bf.versao_id WHERE bf.balanceamento_id=? AND bf.formula_id=? FOR UPDATE');
        $link->execute([$balanceId, $formulaId]);
        $current = $link->fetch();
        if (!$current || (int)$current['id'] !== $versionId) {
            throw new InvalidArgumentException('A versão vinculada mudou. Recarregue o painel.');
        }
        $schema = $db->prepare('SELECT * FROM rumo10_formula_parametros WHERE versao_id=?');
        $schema->execute([$versionId]);
        $spec = array_column($schema->fetchAll(), null, 'chave');
        if (!$spec || array_diff_key($spec, $params) || array_diff_key($params, $spec)) {
            throw new InvalidArgumentException('Conjunto de parâmetros inválido.');
        }

        $columns = ['valor_float','valor_int','valor_texto','valor_json'];
        $rows = [];
        foreach ($spec as $key => $def) {
            $input = $params[$key];
            if (!is_string($input)) throw new InvalidArgumentException('Parâmetro inválido.');
            $type = $def['tipo'];
            $column = match ($type) {
                'float' => 'valor_float',
                'int' => 'valor_int',
                'json' => 'valor_json',
                default => 'valor_texto',
            };
            $stored = match ($type) {
                'float' => (float)$def[$column],
                'int' => (int)$def[$column],
                'json' => json_decode((string)$def[$column], true, 512, JSON_THROW_ON_ERROR),
                default => $def[$column],
            };
            if ($input !== self::parameterInput($stored, $type)) {
                if ($type === 'float' || $type === 'int') {
                    if (!is_numeric($input) || !is_finite((float)$input)) throw new InvalidArgumentException('Número inválido.');
                    $number = (float)$input;
                    if (($def['limite_min'] !== null && $number < (float)$def['limite_min']) ||
                        ($def['limite_max'] !== null && $number > (float)$def['limite_max'])) {
                        throw new InvalidArgumentException('Parâmetro fora dos limites homologados.');
                    }
                    if ($type === 'int' && floor($number) !== $number) throw new InvalidArgumentException('Inteiro obrigatório.');
                    $input = $type === 'int' ? (int)$number : $number;
                } elseif ($type === 'json') {
                    $input = json_encode(json_decode($input, true, 512, JSON_THROW_ON_ERROR), JSON_THROW_ON_ERROR);
                }
                $def[$column] = $input;
            }
            $rows[] = [$key, $type, ...array_map(fn($field) => $def[$field], $columns),
                $def['limite_min'], $def['limite_max'], $def['descricao']];
        }

        $latest = $db->prepare('SELECT MAX(versao) FROM rumo10_formula_versoes WHERE formula_id=?');
        $latest->execute([$formulaId]);
        $newVersion = (int)$latest->fetchColumn() + 1;
        $checksum = hash('sha256', $formulaId . '|v' . $newVersion . '|' . microtime());
        $insert = $db->prepare("INSERT INTO rumo10_formula_versoes (formula_id, versao, estado, checksum, autor, motivo) VALUES (?, ?, 'homologado', ?, 'admin', 'Ajuste de balanceamento via painel administrativo')");
        $insert->execute([$formulaId, $newVersion, $checksum]);
        $newId = (int)$db->lastInsertId();
        $insert = $db->prepare('INSERT INTO rumo10_formula_parametros (versao_id,chave,tipo,valor_float,valor_int,valor_texto,valor_json,limite_min,limite_max,descricao) VALUES (?,?,?,?,?,?,?,?,?,?)');
        foreach ($rows as $row) $insert->execute([$newId, ...$row]);
        $update = $db->prepare('UPDATE rumo10_balanceamento_formulas SET versao_id=? WHERE balanceamento_id=? AND formula_id=? AND versao_id=?');
        $update->execute([$newId, $balanceId, $formulaId, $versionId]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Não foi possível atualizar o vínculo.');
        $audit = $db->prepare("INSERT INTO rumo10_formula_alteracoes (formula_id, versao_anterior, versao_nova, usuario_responsavel, acao, diff_json, justificativa) VALUES (?, ?, ?, 'admin', 'editar_rascunho', ?, 'Alteração de parâmetros via painel')");
        $audit->execute([$formulaId, $current['versao'], $newVersion, json_encode($params, JSON_THROW_ON_ERROR)]);
        return $newVersion;
    }

    private static function activateBalance(PDO $db): void {
        $balId = (int)($_POST['balance_id'] ?? 0);
        if (!$balId) {
            header('Location: /admin?error=ID de balanceamento inválido');
            exit;
        }

        $db->beginTransaction();
        try {
            $exists=$db->prepare('SELECT id FROM rumo10_balanceamentos WHERE id=? FOR UPDATE');$exists->execute([$balId]);if(!$exists->fetchColumn())throw new InvalidArgumentException('Balanceamento inexistente.');
            $db->query("UPDATE rumo10_balanceamentos SET estado='arquivado' WHERE estado='ativo'");
            $stmt = $db->prepare("UPDATE rumo10_balanceamentos SET estado='ativo', ativado_em=CURRENT_TIMESTAMP WHERE id=?");
            $stmt->execute([$balId]);
            $db->commit();
            BalanceService::resetCache();
            header('Location: /admin?msg=Conjunto de balanceamento ativado');
            exit;
        } catch (Throwable $e) {
            $db->rollBack();
            header('Location: /admin?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    private static function rollbackBalance(PDO $db): void {
        $db->beginTransaction();
        try {
            // Busca o balanceamento arquivado mais recente
            $stmt = $db->query("SELECT id FROM rumo10_balanceamentos WHERE estado='arquivado' ORDER BY ativado_em DESC, id DESC LIMIT 1");
            $prevId = $stmt->fetchColumn();

            if (!$prevId) {
                throw new RuntimeException("Nenhum balanceamento anterior arquivado encontrado para rollback.");
            }

            $db->query("UPDATE rumo10_balanceamentos SET estado='arquivado' WHERE estado='ativo'");
            $stmt = $db->prepare("UPDATE rumo10_balanceamentos SET estado='ativo', ativado_em=CURRENT_TIMESTAMP WHERE id=?");
            $stmt->execute([(int)$prevId]);

            $stmt = $db->prepare("INSERT INTO rumo10_formula_alteracoes (balanceamento_id, versao_nova, usuario_responsavel, acao, diff_json, justificativa) VALUES (?, 1, 'admin', 'rollback', '{}', 'Rollback atômico para versão anterior')");
            $stmt->execute([(int)$prevId]);

            $db->commit();
            BalanceService::resetCache();
            header('Location: /admin?msg=Rollback executado com sucesso');
            exit;
        } catch (Throwable $e) {
            $db->rollBack();
            header('Location: /admin?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    private static function renderDashboard(PDO $db, string $msg = ''): void {
        $mState = Maintenance::state();
        $balance = BalanceService::activeBalance();
        $formulas = array_column($db->query("SELECT * FROM rumo10_formulas ORDER BY categoria, nome")->fetchAll(), null, 'id');
        $effective = BalanceService::effectiveFormulas();
        $hasFallback = false;
        foreach ($effective as $id => $details) {
            $formulas[$id] ??= ['id'=>$id, 'nome'=>$id, 'categoria'=>'PHP', 'descricao'=>'', 'expressao_padrao'=>''];
            $hasFallback = $hasFallback || in_array('fallback PHP', array_column($details['parameters'], 'source'), true);
        }
        $history = $db->query("SELECT * FROM rumo10_formula_alteracoes ORDER BY criado_em DESC LIMIT 10")->fetchAll();

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel de Governança e Balanceamento · Rumo10</title>
    <link rel="stylesheet" href="/assets/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/admin.css">
</head>
<body class="container">
    <header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
        <div>
            <h1 class="h3 mb-0">Rumo à Camisa 10 · Governança</h1>
            <small class="text-secondary">Centralização de Fórmulas, Balanceamento e Manutenção</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge-status <?= $mState['manutencao_ativa'] ? 'badge-maint' : 'badge-active' ?>">
                <?= $mState['manutencao_ativa'] ? '● SISTEMA EM MANUTENÇÃO' : '● SISTEMA OPERACIONAL' ?>
            </span>
            <span class="badge bg-dark border border-secondary text-secondary">
                <?= htmlspecialchars((string)($_SESSION['admin_user'] ?? 'admin')) ?>
            </span>
            <a href="/admin/logout" class="btn btn-sm btn-outline-danger">Sair</a>
        </div>
    </header>

    <?php if (!empty($_GET['msg'])): ?>
        <div class="alert alert-success bg-dark text-success border-success"><?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-danger bg-dark text-danger border-danger"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <!-- 1. Controle de Manutenção Global -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>1. Sistema Global de Manutenção</span>
            <small class="text-secondary">Intercepta 100% dos acessos públicos com HTTP 503 e tela responsiva</small>
        </div>
        <div class="card-body">
            <form action="/admin/manutencao" method="POST">
<input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <div class="row g-3">
                    <div class="col-md-12">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" name="manutencao_ativa" value="1" id="mSwitch" <?= $mState['manutencao_ativa'] ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold" for="mSwitch">Ativar Modo Manutenção Global</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary">Título da Página</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary" name="manutencao_titulo" value="<?= htmlspecialchars($mState['manutencao_titulo']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary">Previsão Estimada</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary" name="manutencao_previsao" value="<?= htmlspecialchars($mState['manutencao_previsao']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label text-secondary">Mensagem aos Jogadores</label>
                        <textarea class="form-control bg-dark text-light border-secondary" rows="2" name="manutencao_mensagem"><?= htmlspecialchars($mState['manutencao_mensagem']) ?></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-success fw-bold">Salvar Configuração de Manutenção</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. Conjunto Ativo e Rollback -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>2. Conjunto de Balanceamento Ativo</span>
            <form action="/admin/balance_rollback" method="POST" onsubmit="return confirm('Confirma o Rollback para a versão anterior homologada?');">
<input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">⏪ Rollback em 1 Clique</button>
            </form>
        </div>
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title text-warning mb-1"><?= htmlspecialchars($balance['nome'] ?? 'Balanceamento Padrão') ?></h5>
                    <p class="card-text text-secondary mb-0">Código <code><?= e($balance['codigo']) ?></code> · versão <?= (int)$balance['versao'] ?> · checksum <code><?= e($balance['checksum']) ?></code></p>
                    <?php if (BalanceService::isFallbackActive()): ?>
                        <p class="text-warning">Fallback PHP integral.</p>
                    <?php elseif ($hasFallback): ?>
                        <p class="text-warning">Fallback parcial. Alguns parâmetros vêm do PHP.</p>
                    <?php endif; ?>
                </div>
                <div>
                    <a href="/admin/simulacao" class="btn btn-gold">🔬 Abrir Simulador de Impacto (Sandbox)</a>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Editor de Parâmetros e Fórmulas -->
    <div class="card">
        <div class="card-header">3. Catálogo de Fórmulas e Ajuste de Coeficientes</div>
        <div class="card-body">
            <div class="accordion" id="formulaAccordion">
                <?php foreach ($formulas as $f):
                    $details = $effective[$f['id']] ?? ['version'=>null, 'version_id'=>null, 'parameters'=>[]];
                    $editable = in_array('MySQL', array_column($details['parameters'], 'source'), true);
                ?>
                    <details class="accordion-item bg-dark border-secondary mb-2">
                        <summary class="accordion-header p-3">
                            <strong class="text-emerald"><?= e($f['nome']) ?></strong>
                            <span class="badge bg-secondary ms-2"><?= e($f['categoria']) ?></span>
                            <small class="text-secondary ms-2"><code><?= e($f['id']) ?></code> · <?= $details['version'] === null ? 'Sem versão vinculada' : 'Versão vinculada '.$details['version'] ?></small>
                        </summary>
                        <div class="accordion-body border-top border-secondary">
                            <?php if ($f['descricao'] !== ''): ?><p class="text-secondary small mb-2"><?= e($f['descricao']) ?></p><?php endif; ?>
                            <?php if ($f['expressao_padrao'] !== ''): ?><div class="bg-black p-2 rounded mb-3 font-monospace small text-info"><?= e($f['expressao_padrao']) ?></div><?php endif; ?>
                            <?php if ($editable): ?>
                            <form action="/admin/balance_save" method="POST">
                                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf'] ?? '') ?>">
                                <input type="hidden" name="formula_id" value="<?= e($f['id']) ?>">
                                <input type="hidden" name="balance_id" value="<?= (int)$balance['id'] ?>">
                                <input type="hidden" name="version_id" value="<?= $details['version_id'] ?>">
                            <?php endif; ?>
                                <div class="row g-2 mb-3">
                                    <?php foreach ($details['parameters'] as $key => $parameter):
                                        $label = $key.' · '.$parameter['source'];
                                    ?>
                                        <div class="col-md-4 col-sm-6">
                                            <?php if ($parameter['source'] === 'MySQL'): ?>
                                                <label class="form-label small text-secondary mb-0"><?= e($label) ?>
                                                    <input type="text" class="form-control form-control-sm bg-black text-light border-secondary" name="param[<?= e($key) ?>]" value="<?= e(self::parameterInput($parameter['stored'], $parameter['type'])) ?>" required>
                                                </label>
                                                <?php if ($parameter['stored'] !== $parameter['value']): ?>
                                                    <small>Valor efetivo <?= e(self::parameterInput($parameter['value'], $parameter['type'])) ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="small text-secondary"><?= e($label) ?></span>
                                                <div><?= e(self::parameterInput($parameter['value'])) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php if ($editable): ?>
                                <button type="submit" class="btn btn-sm btn-primary">Salvar nova versão da fórmula</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- 4. Histórico de Alterações -->
    <div class="card">
        <div class="card-header">4. Trilha de Auditoria Recente</div>
        <div class="card-body p-0">
            <table class="table table-dark table-hover mb-0">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Ação</th>
                        <th>Alvo</th>
                        <th>Responsável</th>
                        <th>Justificativa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$history): ?>
                        <tr><td colspan="5" class="text-center text-secondary py-3">Nenhum evento registrado ainda.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td><small><?= htmlspecialchars($h['criado_em']) ?></small></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($h['acao']) ?></span></td>
                            <td><code><?= htmlspecialchars($h['formula_id'] ?: 'balanceamento') ?></code></td>
                            <td><?= htmlspecialchars($h['usuario_responsavel']) ?></td>
                            <td><small class="text-secondary"><?= htmlspecialchars($h['justificativa']) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>
        <?php
    }

    private static function renderSimulation(PDO $db): void {
        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Simulador de Impacto (Sandbox) · Rumo10</title>
    <link rel="stylesheet" href="/assets/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/admin.css">
</head>
<body class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>🔬 Simulador de Impacto de Balanceamento (Sandbox)</h2>
        <a href="/admin" class="btn btn-outline-light">← Voltar ao Painel</a>
    </div>
    <div class="card bg-dark border-secondary p-4 mb-4">
        <h4>Impacto Teórico do Overall no Rendimento</h4>
        <p class="text-secondary">Comparação do rendimento de finalizações e expectativa de contribuição por faixa de qualidade técnica.</p>
        <table class="table table-dark table-bordered">
            <thead>
                <tr>
                    <th>Overall</th>
                    <th>Fatia Ofensiva (CA)</th>
                    <th>Chute Normal (Conv. Esperada)</th>
                    <th>Chance Clara (Conv. Esperada)</th>
                    <th>Bônus Coletivo na Equipe</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ([50, 65, 80, 95] as $ovr): 
                    $q = (float)BalanceService::param('match.balance.quotas', 'share_ca', 0.60);
                    $normBase = (float)BalanceService::param('match.shots.base', 'shot_normal', 0.18);
                    $clearBase = (float)BalanceService::param('match.shots.base', 'shot_clear', 0.38);
                    $maxBoost = (float)BalanceService::param('match.balance.quotas', 'team_boost_max', 0.12);
                    $boost = max(-$maxBoost, min($maxBoost, ($ovr - 55) / 250));
                ?>
                    <tr>
                        <td><strong class="text-warning"><?= $ovr ?></strong></td>
                        <td><?= number_format($q * 100, 1) ?>%</td>
                        <td><?= number_format(($normBase + ($ovr - 50) * 0.0035) * 100, 1) ?>%</td>
                        <td><?= number_format(($clearBase + ($ovr - 50) * 0.004) * 100, 1) ?>%</td>
                        <td class="<?= $boost >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= ($boost >= 0 ? '+' : '') . number_format($boost * 100, 2) ?>%
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
        <?php
    }
}
