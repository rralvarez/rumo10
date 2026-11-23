<?php
declare(strict_types=1);
namespace Rumo\Publisher;
require_once __DIR__.'/Core.php';

/**
 * Provides unambiguous runtime identification and integrity verification for the
 * single active Publisher library. Rejects obsolete receiver-bootstrap-* paths
 * when strict mode is enabled and verifies every library file against version.json.
 */
final class RuntimeIdentity {
    public const VERSION = '9.0.0-consolidated';
    public const SCHEMA = 'rumo-publisher-runtime-v1';

    /**
     * Inspects the active library directory, optionally verifying version.json checksums
     * if present (mandatory in consolidated packages) and returning an auditable identity record.
     */
    public static function inspect(string $libDir, bool $rejectLegacyBootstrap = false): array {
        $real = realpath($libDir);
        need($real !== false && is_dir($real) && !is_link($libDir), 'Active publisher library directory invalid');
        $normalized = str_replace('\\', '/', $real);
        if ($rejectLegacyBootstrap) {
            need(!str_contains($normalized, '/receiver-bootstrap-'),
                'Obsolete receiver-bootstrap library path refused: '.$normalized);
        }

        $versionFile = $real . DIRECTORY_SEPARATOR . 'version.json';
        $manifestSha = null;
        $version = self::VERSION;
        $verifiedFiles = 0;

        if (is_file($versionFile)) {
            need(!is_link($versionFile), 'Runtime version manifest cannot be a symlink');
            $raw = file_get_contents($versionFile);
            need($raw !== false, 'Cannot read runtime version manifest');
            $manifestSha = hash('sha256', $raw);
            $meta = json($raw);
            need(($meta['schema'] ?? null) === self::SCHEMA, 'Unsupported runtime version manifest schema');
            need(is_string($meta['version'] ?? null) && $meta['version'] !== '', 'Invalid runtime version');
            $version = $meta['version'];
            $files = $meta['files'] ?? null;
            need(is_array($files) && count($files) >= 10, 'Incomplete runtime manifest file inventory');
            foreach ($files as $name => $expectedHash) {
                need(is_string($name) && preg_match('/^[A-Za-z0-9_.-]+\.php$/D', $name) === 1,
                    'Invalid runtime manifest entry: '.(string)$name);
                need(is_string($expectedHash) && preg_match('/^[a-f0-9]{64}$/D', $expectedHash) === 1,
                    'Invalid runtime manifest hash for: '.$name);
                $full = $real . DIRECTORY_SEPARATOR . $name;
                need(is_file($full) && !is_link($full) && hash_equals($expectedHash, digest($full)),
                    'Publisher library integrity mismatch: '.$name);
                $verifiedFiles++;
            }
        }

        return [
            'schema' => self::SCHEMA,
            'version' => $version,
            'lib_path' => $normalized,
            'manifest_sha256' => $manifestSha,
            'verified_files' => $verifiedFiles,
            'legacy_bootstrap_path' => str_contains($normalized, '/receiver-bootstrap-'),
        ];
    }

    /** Builds a deterministic version.json payload for all *.php files in a library directory. */
    public static function buildManifest(string $libDir, string $version = self::VERSION, ?string $sourceCommit = null): array {
        $real = realpath($libDir);
        need($real !== false && is_dir($real), 'Library directory required to build manifest');
        $files = [];
        foreach (glob($real . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
            if (!is_file($file) || is_link($file)) continue;
            $files[basename($file)] = digest($file);
        }
        ksort($files, SORT_STRING);
        need(count($files) >= 10, 'Incomplete library directory for manifest generation');
        return [
            'schema' => self::SCHEMA,
            'version' => $version,
            'source_commit' => $sourceCommit,
            'files' => $files,
        ];
    }
}
