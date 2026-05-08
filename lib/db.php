<?php
declare(strict_types=1);

function db(): mysqli
{
    static $mysqli = null;
    global $config;

    if ($mysqli instanceof mysqli) {
        return $mysqli;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $db = $config['db'];
    $mysqli = @new mysqli($db['host'], $db['user'], $db['password'], $db['database'], $db['port']);
    if ($mysqli->connect_errno) {
        http_response_code(500);
        echo 'Database connection failed.';
        exit;
    }
    $mysqli->set_charset($db['charset']);
    return $mysqli;
}

function db_fetch_all(mysqli_result $result): array
{
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();
    return $rows;
}

function db_one(string $sql, array $params = []): ?array
{
    $rows = db_all($sql, $params);
    return $rows ? $rows[0] : null;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    if (!$stmt) {
        return [];
    }
    bind_params($stmt, $params);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }
    $result = $stmt->get_result();
    if (!$result) {
        $stmt->close();
        return [];
    }
    $rows = db_fetch_all($result);
    $stmt->close();
    return $rows;
}

function db_exec(string $sql, array $params = []): bool
{
    $stmt = db()->prepare($sql);
    if (!$stmt) {
        return false;
    }
    bind_params($stmt, $params);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function db_insert_id(): int
{
    return (int) db()->insert_id;
}

function bind_params(mysqli_stmt $stmt, array $params): void
{
    if (!$params) {
        return;
    }
    $types = '';
    $values = [];
    foreach ($params as $param) {
        if (is_int($param)) {
            $types .= 'i';
        } elseif (is_float($param)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
        $values[] = $param;
    }
    $refs = [];
    $refs[] = $types;
    foreach ($values as $key => $value) {
        $refs[] = &$values[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}
