<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';

require_token($config['app']['trigger_token']);

$schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
if ($schema === false) {
    json_response(['ok' => false, 'error' => 'schema_not_found'], 500);
}

$statements = array_filter(array_map('trim', explode(';', $schema)));
$errors = [];

foreach ($statements as $statement) {
    if ($statement === '') {
        continue;
    }
    if (!db()->query($statement)) {
        $errors[] = db()->error;
    }
}

json_response([
    'ok' => !$errors,
    'statements' => count($statements),
    'errors' => $errors,
], $errors ? 500 : 200);
