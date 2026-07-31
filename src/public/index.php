<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function ($class) {
    $prefix = 'GreenNet\\';
    $baseDir = BASE_PATH . '/app/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use GreenNet\Core\Router;
use GreenNet\Core\Config;

Config::load(BASE_PATH . '/.env');

$displayErrors = Config::isProduction() ? '0' : '1';
ini_set('display_errors', $displayErrors);
ini_set('display_startup_errors', $displayErrors);
error_reporting(E_ALL);

session_set_cookie_params([
    'httponly' => true,
    'secure' => Config::get('SESSION_COOKIE_SECURE', 'false') === 'true',
    'samesite' => (string) Config::get('SESSION_COOKIE_SAMESITE', 'Lax'),
]);
session_start();
if (!isset($_SESSION['admin_csrf_token']) || !is_string($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

$router = new Router();

require BASE_PATH . '/app/Routes/web.php';

$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
