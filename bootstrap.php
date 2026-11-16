<?php
declare(strict_types=1);

define('APP_ROOT', __DIR__);

spl_autoload_register(static function (string $class): void {
    $file = APP_ROOT . '/src/' . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});

function app_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $file = APP_ROOT . '/config.php';
        if (!is_file($file)) {
            throw new RuntimeException('Missing config.php — copy config.sample.php to config.php and fill it in.');
        }
        $cfg = require $file;
        date_default_timezone_set('UTC'); // all internal maths in UTC
    }
    return $cfg;
}

function app_tz(): DateTimeZone
{
    static $tz = null;
    return $tz ??= new DateTimeZone(app_config()['timezone'] ?? 'Europe/Copenhagen');
}

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
