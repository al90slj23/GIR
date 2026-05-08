<?php
declare(strict_types=1);

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function app_setting(string $key, string $default = ''): string
{
    static $settings = null;

    if ($settings === null) {
        $settings = [];
        $rows = db_all('SELECT setting_key, setting_value FROM app_settings');
        foreach ($rows as $row) {
            $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
        }
    }

    return array_key_exists($key, $settings) && $settings[$key] !== '' ? $settings[$key] : $default;
}

function request_json(): array
{
    $raw = '';
    if (isset($_POST['payload_b64'])) {
        $decoded = base64_decode((string) $_POST['payload_b64'], true);
        $raw = is_string($decoded) ? $decoded : '';
    }
    if ($raw === '' && isset($_POST['payload'])) {
        $raw = (string) $_POST['payload'];
    }
    if ($raw === '') {
        $raw = file_get_contents('php://input');
    }
    if ($raw === false || trim($raw) === '') {
        json_response(['ok' => false, 'error' => 'empty_body'], 400);
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_response(['ok' => false, 'error' => 'invalid_json'], 400);
    }

    return $data;
}

function truncate_text($value, int $max): string
{
    $text = trim((string) $value);
    $text = strip_mysql_utf8_unsupported($text);
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') : $text;
    }
    return strlen($text) > $max ? substr($text, 0, $max) : $text;
}

function strip_mysql_utf8_unsupported(string $text): string
{
    $cleaned = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text);
    return is_string($cleaned) ? $cleaned : $text;
}

function badge_class(string $recommendation): string
{
    switch ($recommendation) {
        case '可复刻':
            return 'badge green';
        case '研究':
            return 'badge blue';
        case '收藏':
            return 'badge amber';
        default:
            return 'badge muted';
    }
}

function display_maturity_score(array $row): int
{
    $score = (int) ($row['maturity_score'] ?? 0);
    if ($score > 0) {
        return min(10, $score);
    }

    $stars = (int) ($row['stars'] ?? 0);
    $forks = (int) ($row['forks'] ?? 0);
    if ($stars >= 100000) {
        $score = 10;
    } elseif ($stars >= 50000) {
        $score = 9;
    } elseif ($stars >= 10000) {
        $score = 8;
    } elseif ($stars >= 5000) {
        $score = 7;
    } elseif ($stars >= 1000) {
        $score = 6;
    } elseif ($stars >= 500) {
        $score = 5;
    } elseif ($stars >= 100) {
        $score = 4;
    } elseif ($stars >= 20) {
        $score = 3;
    } elseif ($stars > 0) {
        $score = 2;
    } else {
        $score = 1;
    }

    if ($forks >= 1000 && $score < 10) {
        $score++;
    }
    return min(10, $score);
}
