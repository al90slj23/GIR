<?php
declare(strict_types=1);

const PROBE_TOKEN = '9a63f2904a94abdad1d7375b454d0963';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow', true);

if (!isset($_GET['token']) || !hash_equals(PROBE_TOKEN, (string) $_GET['token'])) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

function probe_env(string $key, string $default = ''): string
{
    static $values = null;

    if ($values === null) {
        $values = [];
        $paths = [
            dirname(__DIR__) . '/Data/.env',
            __DIR__ . '/.env',
        ];

        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                $pos = strpos($line, '=');
                if ($pos === false) {
                    continue;
                }
                $values[trim(substr($line, 0, $pos))] = trim(substr($line, $pos + 1));
            }
            break;
        }
    }

    return array_key_exists($key, $values) ? $values[$key] : $default;
}

$host = probe_env('MYSQL_HOST', '127.0.0.1');
$port = (int) probe_env('MYSQL_PORT', '3306');
$database = probe_env('MYSQL_DATABASE', '');
$user = probe_env('MYSQL_USER', '');
$password = probe_env('MYSQL_PASSWORD', '');

echo "time: " . date('Y-m-d H:i:s') . "\n";
echo "php: " . PHP_VERSION . "\n";
echo "mysqli: " . (extension_loaded('mysqli') ? 'enabled' : 'disabled') . "\n";
echo "pdo_mysql: " . (extension_loaded('pdo_mysql') ? 'enabled' : 'disabled') . "\n\n";
echo "configured_database: " . ($database !== '' ? $database : '-') . "\n\n";

if (!extension_loaded('mysqli')) {
    echo "mysqli extension is not enabled\n";
    exit;
}

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = @new mysqli($host, $user, $password, $database, $port);

if ($mysqli->connect_errno) {
    echo "connect: failed\n";
    echo "errno: " . $mysqli->connect_errno . "\n";
    echo "error: " . $mysqli->connect_error . "\n";
    exit;
}

$mysqli->set_charset('utf8mb4');

echo "connect: ok\n";
echo "server_info: " . $mysqli->server_info . "\n";
echo "client_info: " . mysqli_get_client_info() . "\n";

$result = $mysqli->query('SELECT DATABASE() AS db, NOW() AS now_time, VERSION() AS version');
if ($result) {
    $row = $result->fetch_assoc();
    echo "database: " . ($row['db'] ?? '-') . "\n";
    echo "db_time: " . ($row['now_time'] ?? '-') . "\n";
    echo "db_version: " . ($row['version'] ?? '-') . "\n";
    $result->free();
}

$result = $mysqli->query('SHOW TABLES');
if ($result) {
    echo "tables_count: " . $result->num_rows . "\n";
    $result->free();
}

$mysqli->close();
