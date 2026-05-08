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

function request_json(): array
{
    $raw = file_get_contents('php://input');
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
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') : $text;
    }
    return strlen($text) > $max ? substr($text, 0, $max) : $text;
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
