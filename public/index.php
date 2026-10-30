<?php

declare(strict_types=1);

// Server bawaan PHP (php -S) mengirim SEMUA request ke skrip ini, termasuk
// berkas statis. Di Apache hal itu sudah ditangani .htaccess (RewriteCond -f).
if (PHP_SAPI === 'cli-server') {
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, is_string($path) ? $path : '');

    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Request;
use App\Core\Session;

Session::start();

$request = Request::capture();
$router = require BASE_PATH . '/routes/web.php';

$router->dispatch($request)->send();
