<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = BASE_PATH . '/app/';

    $length = strlen($prefix);

    if (strncmp($prefix, $class, $length) !== 0) {
        return;
    }

    $relativeClass = substr($class, $length);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Config;
use App\Core\Database;

$config = require BASE_PATH . '/config/config.php';
$database = require BASE_PATH . '/config/database.php';

Config::load([
    'app' => $config['app'] ?? [],
    'chat' => $config['chat'] ?? [],
    'database' => $database,
]);

date_default_timezone_set(Config::get('app.timezone', 'UTC'));

error_reporting(E_ALL);

$debug = (bool) Config::get('app.debug', false);

ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

$logPath = BASE_PATH . '/storage/logs/php_error.log';

if (!is_dir(dirname($logPath))) {
    mkdir(dirname($logPath), 0775, true);
}

ini_set('error_log', $logPath);

Database::init(Config::get('database'));