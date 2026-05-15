<?php
declare(strict_types=1);

/**
 * File-system cache for README markdown content.
 *
 * Keeps DB rows lean (only metadata) and stores actual markdown on disk
 * under Data/readme_cache. Each cache entry is keyed by:
 *   <project_id>/<langslug>_<isTranslated>.md
 *
 * - TTL: configurable via APP setting, default 7 days. Older files are
 *   refreshed by the caller transparently.
 * - Size cap: configurable, default 200 MB. When the cache exceeds the
 *   cap, oldest atime files are evicted until 80% of the cap remains.
 * - Concurrency: write is atomic via tmp file + rename.
 */

function readme_cache_root(): string
{
    static $root = null;
    if ($root !== null) {
        return $root;
    }
    // The shared host's PHP-FCGI process can only write under the web root,
    // so we keep the cache at <web_root>/Data/readme_cache and lock down
    // public access to /Data/ via web.config (deployed alongside).
    $candidates = [];
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') : '';
    if ($docRoot !== '') {
        $candidates[] = $docRoot . DIRECTORY_SEPARATOR . 'Data' . DIRECTORY_SEPARATOR . 'readme_cache';
    }
    $candidates[] = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'Data' . DIRECTORY_SEPARATOR . 'readme_cache';
    $candidates[] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gir_readme_cache';

    foreach ($candidates as $dir) {
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0700, true)) {
                continue;
            }
        }
        if (is_writable($dir)) {
            $root = rtrim($dir, '/\\');
            return $root;
        }
    }
    $root = '';
    return $root;
}

function readme_cache_path(int $projectId, string $languageCode, bool $isTranslated): string
{
    $root = readme_cache_root();
    if ($root === '') return '';
    $slug = preg_replace('/[^a-z0-9]/i', '_', strtolower($languageCode)) ?: 'unknown';
    $flag = $isTranslated ? '1' : '0';
    return $root . DIRECTORY_SEPARATOR . $projectId . DIRECTORY_SEPARATOR . $slug . '_' . $flag . '.md';
}

function readme_cache_read(int $projectId, string $languageCode, bool $isTranslated): ?string
{
    $path = readme_cache_path($projectId, $languageCode, $isTranslated);
    if ($path === '' || !is_file($path) || !is_readable($path)) {
        return null;
    }
    $contents = @file_get_contents($path);
    if ($contents === false) return null;
    @touch($path); // bump atime for LRU
    return $contents;
}

function readme_cache_age_seconds(int $projectId, string $languageCode, bool $isTranslated): ?int
{
    $path = readme_cache_path($projectId, $languageCode, $isTranslated);
    if ($path === '' || !is_file($path)) return null;
    $mtime = @filemtime($path);
    if ($mtime === false) return null;
    return max(0, time() - $mtime);
}

function readme_cache_write(int $projectId, string $languageCode, bool $isTranslated, string $content): bool
{
    $path = readme_cache_path($projectId, $languageCode, $isTranslated);
    if ($path === '') return false;
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $content) === false) {
        return false;
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

function readme_cache_delete(int $projectId, string $languageCode, bool $isTranslated): bool
{
    $path = readme_cache_path($projectId, $languageCode, $isTranslated);
    if ($path === '' || !is_file($path)) return true;
    return @unlink($path);
}

function readme_cache_ttl_seconds(): int
{
    $days = (int) app_setting('readme_cache_ttl_days', '7');
    if ($days <= 0) $days = 7;
    return $days * 86400;
}

function readme_cache_max_bytes(): int
{
    $mb = (int) app_setting('readme_cache_max_mb', '200');
    if ($mb <= 0) $mb = 200;
    return $mb * 1024 * 1024;
}

/**
 * Probabilistically run an LRU eviction sweep when the cache exceeds
 * the configured cap. Called from cache_write so we amortize the cost.
 */
function readme_cache_maybe_evict(): void
{
    if (mt_rand(1, 50) !== 1) return; // ~2% sample to keep writes fast

    $root = readme_cache_root();
    if ($root === '') return;
    $cap = readme_cache_max_bytes();
    $files = [];
    $totalBytes = 0;
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iter as $f) {
        if (!$f->isFile()) continue;
        $size = (int) $f->getSize();
        $atime = (int) $f->getATime();
        if ($atime <= 0) $atime = (int) $f->getMTime();
        $files[] = ['path' => $f->getPathname(), 'size' => $size, 'atime' => $atime];
        $totalBytes += $size;
    }
    if ($totalBytes <= $cap) return;
    usort($files, static function ($a, $b) {
        return $a['atime'] <=> $b['atime'];
    });
    $target = (int) ($cap * 0.8);
    foreach ($files as $entry) {
        if ($totalBytes <= $target) break;
        if (@unlink($entry['path'])) {
            $totalBytes -= $entry['size'];
        }
    }
}
