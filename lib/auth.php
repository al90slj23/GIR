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

function admin_cookie_value(string $token): string
{
    return hash_hmac('sha256', 'gir_admin', $token);
}

function is_admin_authenticated(): bool
{
    global $config;
    $token = (string) ($config['app']['admin_token'] ?? '');
    if ($token === '' || $token === 'change_me') {
        return false;
    }
    $cookie = isset($_COOKIE['gir_admin_auth']) ? (string) $_COOKIE['gir_admin_auth'] : '';
    return $cookie !== '' && hash_equals(admin_cookie_value($token), $cookie);
}

function require_admin(): void
{
    global $config;
    $token = (string) ($config['app']['admin_token'] ?? '');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_token'])) {
        $provided = (string) $_POST['admin_token'];
        if ($token !== '' && $token !== 'change_me' && hash_equals($token, $provided)) {
            setcookie('gir_admin_auth', admin_cookie_value($token), time() + 7200, '/admin/', '', false, true);
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
        render_admin_login(true);
    }

    if (!is_admin_authenticated()) {
        render_admin_login(false);
    }
}

function render_admin_login(bool $failed): void
{
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    $message = $failed ? '<p class="error">Token 不正确。</p>' : '';
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>后台登录</title><style>body{margin:0;background:#f5f6f8;color:#111827;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif}.box{max-width:360px;margin:80px auto;background:#fff;border:1px solid #d9dee7;border-radius:8px;padding:22px}h1{font-size:22px;margin:0 0 8px}.muted{color:#667085;font-size:14px}.error{color:#b42318;font-size:14px}input,button{width:100%;box-sizing:border-box;font:inherit;padding:10px;margin-top:12px;border-radius:6px}input{border:1px solid #cbd5e1}button{border:1px solid #1d4ed8;background:#2563eb;color:#fff;cursor:pointer}</style></head><body><div class="box"><h1>后台登录</h1><p class="muted">请输入后台 token。</p>' . $message . '<form method="post"><input name="admin_token" type="password" autocomplete="current-password" autofocus><button type="submit">进入后台</button></form></div></body></html>';
    exit;
}
