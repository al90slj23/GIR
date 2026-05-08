<?php
declare(strict_types=1);

function env_value(string $key, string $default = ''): string
{
    static $values = null;

    if ($values === null) {
        $values = [];
        $paths = [
            dirname(dirname(__DIR__)) . '/Data/.env',
            dirname(__DIR__) . '/.env',
        ];
        $path = '';
        foreach ($paths as $candidate) {
            if (is_file($candidate)) {
                $path = $candidate;
                break;
            }
        }
        if ($path !== '') {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                $pos = strpos($line, '=');
                if ($pos === false) {
                    continue;
                }
                $name = trim(substr($line, 0, $pos));
                $value = trim(substr($line, $pos + 1));
                $values[$name] = $value;
            }
        }
    }

    return array_key_exists($key, $values) ? $values[$key] : $default;
}

return [
    'app' => [
        'name' => 'GIR · 灵猎雷达',
        'base_url' => env_value('APP_BASE_URL', 'http://gir.likeheng.com'),
        'ingest_token' => env_value('APP_INGEST_TOKEN', 'change_me'),
        'trigger_token' => env_value('APP_TRIGGER_TOKEN', 'change_me'),
        'timezone' => 'Asia/Shanghai',
    ],
    'db' => [
        'host' => env_value('MYSQL_HOST', '127.0.0.1'),
        'port' => (int) env_value('MYSQL_PORT', '3306'),
        'database' => env_value('MYSQL_DATABASE', ''),
        'user' => env_value('MYSQL_USER', ''),
        'password' => env_value('MYSQL_PASSWORD', ''),
        'charset' => 'utf8',
    ],
    'github' => [
        'owner' => env_value('GITHUB_OWNER', ''),
        'repo' => env_value('GITHUB_REPO', ''),
        'token' => env_value('GITHUB_TOKEN', ''),
        'workflow' => env_value('GITHUB_WORKFLOW', 'discover.yml'),
    ],
];
