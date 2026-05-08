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

if (!function_exists('curl_init')) {
    echo "cURL disabled\n";
    exit;
}

$tests = [
    ['GitHub home HTTP no-follow', 'http://github.com/', false, true],
    ['GitHub home HTTP follow', 'http://github.com/', true, true],
    ['GitHub home HTTPS strict', 'https://github.com/', true, true],
    ['GitHub home HTTPS no-verify', 'https://github.com/', true, false],
    ['GitHub API HTTP no-follow', 'http://api.github.com/', false, true],
    ['GitHub API HTTP follow', 'http://api.github.com/', true, true],
    ['GitHub API HTTPS strict', 'https://api.github.com/', true, true],
    ['GitHub API HTTPS no-verify', 'https://api.github.com/', true, false],
    ['Raw HTTP no-follow', 'http://raw.githubusercontent.com/github/gitignore/main/PHP.gitignore', false, true],
    ['Raw HTTP follow', 'http://raw.githubusercontent.com/github/gitignore/main/PHP.gitignore', true, true],
    ['Raw HTTPS strict', 'https://raw.githubusercontent.com/github/gitignore/main/PHP.gitignore', true, true],
    ['Raw HTTPS no-verify', 'https://raw.githubusercontent.com/github/gitignore/main/PHP.gitignore', true, false],
    ['Codeload HTTP no-follow', 'http://codeload.github.com/github/gitignore/zip/refs/heads/main', false, true],
    ['Codeload HTTP follow', 'http://codeload.github.com/github/gitignore/zip/refs/heads/main', true, true],
    ['Codeload HTTPS strict', 'https://codeload.github.com/github/gitignore/zip/refs/heads/main', true, true],
    ['Codeload HTTPS no-verify', 'https://codeload.github.com/github/gitignore/zip/refs/heads/main', true, false],
];

function runProbe(string $url, bool $follow, bool $verify): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'Mozilla/5.0 PHP GitHub Scheme Probe',
        CURLOPT_HTTPHEADER => ['Accept: */*'],
        CURLOPT_SSL_VERIFYPEER => $verify,
        CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
        CURLOPT_RANGE => '0-1023',
    ]);
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'http' => (int) ($info['http_code'] ?? 0),
        'effective' => (string) ($info['url'] ?? ''),
        'redirects' => (int) ($info['redirect_count'] ?? 0),
        'errno' => $errno,
        'error' => $error,
        'time' => number_format((float) ($info['total_time'] ?? 0), 3),
        'bytes' => is_string($body) ? strlen($body) : 0,
    ];
}

echo 'time: ' . date('Y-m-d H:i:s') . "\n";
echo 'php: ' . PHP_VERSION . "\n\n";

foreach ($tests as [$name, $url, $follow, $verify]) {
    $r = runProbe($url, $follow, $verify);
    echo "[$name]\n";
    echo "url: {$url}\n";
    echo "http: {$r['http']}\n";
    echo "effective: {$r['effective']}\n";
    echo "redirects: {$r['redirects']}\n";
    echo "curl_error: " . ($r['errno'] ? "{$r['errno']} / {$r['error']}" : 'none') . "\n";
    echo "time: {$r['time']}s\n";
    echo "bytes: {$r['bytes']}\n\n";
}
