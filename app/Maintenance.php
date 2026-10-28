<?php
declare(strict_types=1);

final class Maintenance {
    private static ?array $state = null;

    public static function check(): void {
        $state = self::state();
        if (empty($state['manutencao_ativa'])) {
            return;
        }

        // Permitir bypass para rotas administrativas autorizadas
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        
        // Verifica se a rota é do painel administrativo e se o usuário tem token/sessão admin
        if (str_starts_with($path, '/admin') && self::isAdminAuthorized()) {
            return;
        }

        self::render($state);
    }

    public static function state(): array {
        if (self::$state !== null) {
            return self::$state;
        }

        // 1. Tentar ler override de emergência em arquivo se existir
        $overrideFile = dirname(__DIR__) . '/storage/maintenance_override.json';
        if (is_file($overrideFile)) {
            $data = json_decode((string)file_get_contents($overrideFile), true);
            if (is_array($data)) {
                self::$state = $data;
                return self::$state;
            }
        }

        // 2. Tentar ler do MySQL
        try {
            if (class_exists('Database')) {
                $db = Database::connect();
                $tbl = $db->query("SHOW TABLES LIKE 'rumo10_configuracoes_sistema'")->fetchColumn();
                if ($tbl) {
                    $row = $db->query("SELECT manutencao_ativa, manutencao_titulo, manutencao_mensagem, manutencao_previsao FROM rumo10_configuracoes_sistema WHERE id=1 LIMIT 1")->fetch();
                    if ($row) {
                        self::$state = [
                            'manutencao_ativa' => (int)$row['manutencao_ativa'] === 1,
                            'manutencao_titulo' => $row['manutencao_titulo'],
                            'manutencao_mensagem' => $row['manutencao_mensagem'],
                            'manutencao_previsao' => $row['manutencao_previsao'] ?: 'Em breve voltaremos aos gramados.',
                        ];
                        return self::$state;
                    }
                }
            }
        } catch (Throwable $e) {
            error_log("rumo10: Erro ao verificar manutenção no banco: " . $e->getMessage());
        }

        // 3. Padrão: desligado
        self::$state = [
            'manutencao_ativa' => false,
            'manutencao_titulo' => 'Estamos preparando a próxima temporada!',
            'manutencao_mensagem' => 'Nosso time está trabalhando nos bastidores para melhorar sua experiência. Em breve você poderá voltar aos gramados e continuar sua carreira.',
            'manutencao_previsao' => 'Em breve voltaremos aos gramados.',
        ];

        return self::$state;
    }

    public static function setMaintenance(bool $active, string $titulo, string $mensagem, ?string $previsao, string $user = 'admin'): void {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("UPDATE rumo10_configuracoes_sistema SET manutencao_ativa=?, manutencao_titulo=?, manutencao_mensagem=?, manutencao_previsao=?, manutencao_atualizada_por=?, manutencao_atualizada_em=CURRENT_TIMESTAMP WHERE id=1");
            $stmt->execute([(int)$active, $titulo, $mensagem, $previsao, $user]);

            // Auditoria
            $stmtAudit = $db->prepare("INSERT INTO rumo10_formula_alteracoes (versao_nova, usuario_responsavel, acao, diff_json, justificativa, ip_origem) VALUES (1, ?, 'alternar_manutencao', ?, ?, ?)");
            $stmtAudit->execute([
                $user,
                json_encode(['ativa' => $active, 'titulo' => $titulo, 'previsao' => $previsao], JSON_THROW_ON_ERROR),
                $active ? 'Ativação de manutenção programada' : 'Desativação de manutenção',
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);

            // Sincroniza override estático para contingência
            $overrideFile = dirname(__DIR__) . '/storage/maintenance_override.json';
            @file_put_contents($overrideFile, json_encode([
                'manutencao_ativa' => $active,
                'manutencao_titulo' => $titulo,
                'manutencao_mensagem' => $mensagem,
                'manutencao_previsao' => $previsao,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            self::$state = null;
        } catch (Throwable $e) {
            // Se o banco falhar, grava ao menos o arquivo de emergência
            $overrideFile = dirname(__DIR__) . '/storage/maintenance_override.json';
            @file_put_contents($overrideFile, json_encode([
                'manutencao_ativa' => $active,
                'manutencao_titulo' => $titulo,
                'manutencao_mensagem' => $mensagem,
                'manutencao_previsao' => $previsao,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            self::$state = null;
        }
    }

    private static function isAdminAuthorized(): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!empty($_SESSION['is_admin'])) {
            return true;
        }
        return false;
    }

    public static function render(array $state): void {
        http_response_code(503);
        header('Retry-After: 300');

        $isJson = (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (isset($_SERVER['REQUEST_URI']) && str_starts_with($_SERVER['REQUEST_URI'], '/api/'));

        if ($isJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'maintenance',
                'title' => $state['manutencao_titulo'],
                'message' => $state['manutencao_mensagem'],
                'eta' => $state['manutencao_previsao'],
                'retry_after' => 300
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        $titulo = htmlspecialchars($state['manutencao_titulo'], ENT_QUOTES, 'UTF-8');
        $mensagem = htmlspecialchars($state['manutencao_mensagem'], ENT_QUOTES, 'UTF-8');
        $previsao = htmlspecialchars($state['manutencao_previsao'] ?? '', ENT_QUOTES, 'UTF-8');

        echo <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$titulo} · Rumo à Camisa 10</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/maintenance.css">
</head>
<body>
    <main class="maintenance-card" role="main" aria-labelledby="maintenance-title">
        <div class="badge-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M12 6v6l4 2"></path>
            </svg>
        </div>
        <span class="pitch-tag">Manutenção dos Gramados</span>
        <h1 id="maintenance-title">{$titulo}</h1>
        <p class="message">{$mensagem}</p>
        <div class="eta-box">
            <span aria-hidden="true">⏳</span>
            <span>{$previsao}</span>
        </div>
        <a class="btn-refresh" href="/">Verificar novamente</a>
        <footer>Rumo à Camisa 10 · Temporada 2026</footer>
    </main>
</body>
</html>
HTML;
        exit;
    }
}
