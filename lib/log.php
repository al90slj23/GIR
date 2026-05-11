<?php
declare(strict_types=1);

function app_log_path(): string
{
    static $resolved = null;
    if ($resolved !== null) {
        return $resolved;
    }

    $candidates = [
        dirname(dirname(__DIR__)) . '/Data/logs',
        dirname(__DIR__) . '/Data/logs',
        sys_get_temp_dir() . '/gir_logs',
    ];
    foreach ($candidates as $dir) {
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0700, true)) {
                continue;
            }
        }
        if (is_writable($dir)) {
            $resolved = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'app.log';
            return $resolved;
        }
    }
    $resolved = '';
    return $resolved;
}

function app_log(string $channel, string $message, array $context = []): void
{
    $path = app_log_path();
    if ($path === '') {
        return;
    }
    $line = sprintf(
        "[%s] [%s] %s%s\n",
        date('Y-m-d H:i:s'),
        preg_replace('/[^a-z0-9_\-]/i', '', $channel) ?: 'app',
        $message,
        $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
    );
    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}
