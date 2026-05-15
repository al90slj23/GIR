<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/config.php';
date_default_timezone_set($config['app']['timezone']);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/markdown.php';
require_once __DIR__ . '/tencent_tmt.php';
require_once __DIR__ . '/repositories.php';
