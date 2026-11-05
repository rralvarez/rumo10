<?php
declare(strict_types=1);

final class CanonicalIdentity {
    public static function uuidV4(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function isValidUuid(string $uuid): bool {
        return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
    }

    public static function currentEngineCommit(): string {
        $rootDir = dirname(__DIR__);
        return self::resolveCommitForDirectory($rootDir);
    }

    /**
     * Resolve o commit da engine com distinção estrita entre ambiente Git e pacote de release fechado.
     * 
     * Regras OS-F01-P1-006:
     * 1. Ambiente Git (.git presente):
     *    - Se a worktree contiver qualquer modificação local ou arquivos relevantes alterados, retorna 'desconhecido'.
     *    - Manifesto residual ou variáveis de ambiente NUNCA sobrepõem o estado modificado.
     *    - Se a worktree estiver 100% limpa, retorna o commit do HEAD verificado via Git.
     * 2. Pacote de release sem Git (.git ausente):
     *    - Aceita manifesto (build-manifest.json) SOMENTE se houver vínculo verificável de integridade com os arquivos executados.
     *    - O manifesto deve conter 'commit' (40 hex) e um mapa de hashes 'checksums' ou 'package_hash'.
     *    - Se algum arquivo verificado divergir do hash esperado ou o manifesto estiver corrompido/ausente, retorna 'desconhecido'.
     */
    public static function resolveCommitForDirectory(string $rootDir): string {
        $gitPath = $rootDir . '/.git';
        $isGitEnvironment = is_dir($gitPath) || is_file($gitPath);

        if ($isGitEnvironment) {
            // AMBIENTE WORKTREE GIT
            try {
                $statusCmd = 'git -C ' . escapeshellarg($rootDir) . ' status --porcelain 2>nul';
                $status = @shell_exec($statusCmd);
                if ($status === null || trim($status) !== '') {
                    // Dirty worktree ou alterações locais presentes: NUNCA atribuir commit
                    return 'desconhecido';
                }

                $headCmd = 'git -C ' . escapeshellarg($rootDir) . ' rev-parse HEAD 2>nul';
                $head = @shell_exec($headCmd);
                if (is_string($head) && preg_match('/^[0-9a-f]{40}$/i', trim($head))) {
                    return strtolower(trim($head));
                }
            } catch (Throwable) {}

            return 'desconhecido';
        }

        // AMBIENTE PACOTE DE RELEASE SEM .GIT
        $manifestPath = $rootDir . '/build-manifest.json';
        if (!is_file($manifestPath)) {
            return 'desconhecido';
        }

        try {
            $raw = @file_get_contents($manifestPath);
            if ($raw === false || $raw === '') {
                return 'desconhecido';
            }
            $manifest = json_decode($raw, true);
            if (!is_array($manifest) || empty($manifest['commit']) || !preg_match('/^[0-9a-f]{40}$/i', (string)$manifest['commit'])) {
                return 'desconhecido';
            }

            // Validação do vínculo verificável de integridade entre manifesto e os arquivos executados (OS-F01-P1-007 §2)
            $checksums = $manifest['checksums'] ?? null;
            if (!is_array($checksums) || empty($checksums)) {
                return 'desconhecido';
            }

            // Inventário obrigatório de arquivos executáveis essenciais que DEVEM estar presentes e no manifesto (OS-F01-P1-008 §2)
            $mandatoryFiles = [
                'public/index.php',
                'app/bootstrap.php',
                'app/CanonicalIdentity.php',
                'app/F01Telemetry.php',
                'app/F01Drainer.php',
                'app/Repository.php',
                'app/Database.php',
                'app/Game.php',
                'app/view.php',
                'public/assets/game.js',
                'public/assets/audio-engine.js',
                'public/assets/v061.js',
                'public/assets/f01-telemetry.js',
            ];

            foreach ($mandatoryFiles as $mandatory) {
                if (!isset($checksums[$mandatory]) || !is_file($rootDir . '/' . $mandatory)) {
                    // Arquivo executável obrigatório ausente do manifesto ou do disco
                    return 'desconhecido';
                }
            }

            // Descobrir TODOS os arquivos executáveis/relevantes existentes no artefato em disco
            $scannedFiles = self::scanExecutableFiles($rootDir);

            // Verificar se algum executável em disco foi omitido do manifesto
            foreach ($scannedFiles as $diskFile) {
                if (!isset($checksums[$diskFile])) {
                    // Executável existente em disco omitido do manifesto
                    return 'desconhecido';
                }
            }

            // Verificar integridade SHA-256 de cada arquivo listado no manifesto
            foreach ($checksums as $relPath => $expectedHash) {
                if (str_contains($relPath, '..') || !preg_match('/^[0-9a-f]{64}$/i', (string)$expectedHash)) {
                    return 'desconhecido';
                }
                $targetFile = $rootDir . '/' . ltrim(str_replace('\\', '/', $relPath), '/');
                if (!is_file($targetFile)) {
                    return 'desconhecido';
                }
                $actualHash = hash_file('sha256', $targetFile);
                if (!hash_equals(strtolower((string)$expectedHash), strtolower($actualHash))) {
                    // Divergência de integridade: pacote adulterado/modificado
                    return 'desconhecido';
                }
            }

            // Vínculo de integridade e cobertura integral comprovados
            return strtolower((string)$manifest['commit']);
        } catch (Throwable) {
            return 'desconhecido';
        }
    }

    /**
     * Escaneia arquivos executáveis e essenciais do pacote de release para verificação de cobertura (OS-F01-P1-007 / OS-F01-P1-008 §2).
     * Inclui código PHP, SQL de migração e todos os scripts JavaScript distribuídos e executados pela aplicação.
     * @return string[] Caminhos relativos normalizados com barra frontal
     */
    public static function scanExecutableFiles(string $rootDir): array {
        $files = [];
        $scanDirs = ['app', 'public', 'database'];
        foreach ($scanDirs as $dir) {
            $fullDir = $rootDir . '/' . $dir;
            if (!is_dir($fullDir)) continue;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fullDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $info) {
                if ($info->isFile()) {
                    $ext = strtolower($info->getExtension());
                    $relPath = str_replace('\\', '/', substr($info->getPathname(), strlen($rootDir) + 1));
                    // Incluir arquivos PHP, SQL de migração e TODOS os arquivos JavaScript (.js)
                    if ($ext === 'php' || $ext === 'sql' || $ext === 'js') {
                        $files[] = $relPath;
                    }
                }
            }
        }
        return $files;
    }

    public static function utcMicroTimestamp(): string {
        $now = microtime(true);
        $micro = sprintf("%06d", ($now - floor($now)) * 1000000);
        return gmdate('Y-m-d H:i:s.' . $micro, (int)$now);
    }
}
