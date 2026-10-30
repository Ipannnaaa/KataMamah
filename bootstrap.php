<?php

declare(strict_types=1);

/**
 * Titik awal semua request dan skrip CLI.
 * Tidak memerlukan Composer: autoloader PSR-4 sederhana di bawah sudah cukup.
 */

define('BASE_PATH', __DIR__);

/*
 * Namespace yang dikenali autoloader, dipetakan ke foldernya.
 *
 * PHPMailer ikut di sini karena proyek ini tidak memakai Composer: berkas
 * sumbernya disalin apa adanya ke app/Vendor/PHPMailer, dan namespace-nya
 * PHPMailer\PHPMailer dipetakan rata ke folder itu — strukturnya memang
 * datar, tidak bersarang seperti App\.
 */
spl_autoload_register(static function (string $class): void {
    $peta = [
        'App\\' => 'app',
        'PHPMailer\\PHPMailer\\' => 'app/Vendor/PHPMailer',
    ];

    foreach ($peta as $prefix => $folder) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }

        $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
        $file = BASE_PATH . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $folder) . DIRECTORY_SEPARATOR
            . $relative . '.php';

        if (is_file($file)) {
            require $file;

            return;
        }
    }
});

require BASE_PATH . '/app/Core/helpers.php';

App\Core\Config::boot(BASE_PATH);

date_default_timezone_set(config('app.timezone', 'Asia/Jakarta'));
mb_internal_encoding('UTF-8');

App\Core\ErrorHandler::register();
