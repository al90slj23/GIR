<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

json_response(array_merge(['ok' => true], public_progress_summary()));
