<?php
declare(strict_types=1);

function bearer_token(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (stripos($header, 'Bearer ') === 0) {
        return trim(substr($header, 7));
    }
    return isset($_GET['token']) ? (string) $_GET['token'] : '';
}

function require_token(string $expected): void
{
    $provided = bearer_token();
    if ($expected === '' || $expected === 'change_me' || !hash_equals($expected, $provided)) {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }
}
