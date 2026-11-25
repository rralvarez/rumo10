<?php
declare(strict_types=1);
namespace Rumo\Publisher;
require_once __DIR__.'/Core.php';

/**
 * Strict runtime identification and integrity verification for the single active
 * Publisher library. Requires version.json by default, enforces an exact 1:1 file
 * set (rejects missing OR extra unexpected *.php files), and supports single-file
 * pointer switching (runtime-active.json) without dual-directory rename races.
 */
final class RuntimeIdentity {
    public const VERSION = '9.0.1-consolidated';
    public const SCHEMA = 'rumo-publisher-runtime-v1';
    public const POINTER_SCHEMA = 'rumo-active-runtime-v1';

    /**
     * Inspects and strictly verifies a library directory against its version.json.
     * - Fails closed if version.json is missing (when $requireManifest is true).
     * - Fails closed if any declared file is missing/altered OR any undeclared *.php exists.
     * - Fails closed if $expectedManifestSha is provided and does not match version.json.
     */
    public static function inspect(
        string $libDir,
        bool $rejectLegacyBootstrap = true,
        bool $requireManifest = true,
        ?string $expectedManifestSha = null
    ): array {
        $real = realpath($libDir);
        need($real !== false && is_dir($real) && !is_link($libDir), 'Active publisher library directory invalid');
        $normalized = str_replace('\\', '/', $real);
        if ($rejectLegacyBootstrap) {
            need(!str_contains($normalized, '/receiver-bootstrap-'),
                'Obsolete receiver-bootstrap library path refused: '.$normalized);
        }

        $versionFile = $real . DIRECTORY_SEPARATOR . 'version.json';
        if (!is_file($versionFile)) {
            need(!$requireManifest, 'Mandatory runtime manifest missing: version.json');
            return [
                'schema' => self::SCHEMA,
                'version' => self::VERSION,
                'lib_path' => $normalized,
                'manifest_sha256' => null,
                'verified_files' => 0,
                'legacy_bootstrap_path' => str_contains($normalized, '/receiver-bootstrap-'),
            ];
        }

        need(!is_link($versionFile), 'Runtime version manifest cannot be a symlink');
        $raw = file_get_contents($versionFile);
        need($raw !== false, 'Cannot read runtime version manifest');
        $manifestSha = hash('sha256', $raw);
        if ($expectedManifestSha !== null) {
            need(preg_match('/^[a-f0-9]{64}$/D', $expectedManifestSha) === 1 &&
                hash_equals($expectedManifestSha, $manifestSha),
                'Runtime version manifest SHA-256 mismatch');
        }

        $meta = json($raw);
        need(($meta['schema'] ?? null) === self::SCHEMA, 'Unsupported runtime version manifest schema');
        need(is_string($meta['version'] ?? null) && $meta['version'] !== '', 'Invalid runtime version');
        $version = $meta['version'];
        $files = $meta['files'] ?? null;
        need(is_array($files) && count($files) >= 10, 'Incomplete runtime manifest file inventory');

        // Enumerate actual directory contents to reject undeclared extra PHP files or symlinks.
        $actualPhp = [];
        foreach (scandir($real) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'version.json') continue;
            $full = $real . DIRECTORY_SEPARATOR . $entry;
            need(!is_link($full) && is_file($full), 'Unexpected non-regular entry in publisher library: '.$entry);
            need(str_ends_with($entry, '.php'), 'Unexpected non-PHP file in publisher library: '.$entry);
            $actualPhp[$entry] = true;
        }

        $verifiedFiles = 0;
        foreach ($files as $name => $expectedHash) {
            need(is_string($name) && preg_match('/^[A-Za-z0-9_.-]+\.php$/D', $name) === 1,
                'Invalid runtime manifest entry: '.(string)$name);
            need(is_string($expectedHash) && preg_match('/^[a-f0-9]{64}$/D', $expectedHash) === 1,
                'Invalid runtime manifest hash for: '.$name);
            $full = $real . DIRECTORY_SEPARATOR . $name;
            need(isset($actualPhp[$name]) && is_file($full) && !is_link($full) && hash_equals($expectedHash, digest($full)),
                'Publisher library integrity mismatch: '.$name);
            unset($actualPhp[$name]);
            $verifiedFiles++;
        }
        need($actualPhp === [], 'Undeclared extra PHP files in publisher library: '.implode(', ', array_keys($actualPhp)));

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
