<?php
declare(strict_types=1);

define('PULSE_ROOT', dirname(__DIR__));
$config = require PULSE_ROOT . '/config/defaults.php';
if (is_file(PULSE_ROOT . '/config/local.php')) {
    $config = array_replace($config, require PULSE_ROOT . '/config/local.php');
}
$GLOBALS['pulse_config'] = $config;
date_default_timezone_set($config['timezone']);
require __DIR__ . '/core.php';
require __DIR__ . '/i18n.php';
require __DIR__ . '/updates.php';
require __DIR__ . '/backup.php';

function requirements(): array {
    $items = ['PHP ≥ 8.3' => version_compare(PHP_VERSION, '8.3', '>=')];
    foreach (['pdo_sqlite', 'sqlite3', 'gd', 'fileinfo', 'zip', 'curl', 'mbstring', 'intl'] as $ext) {
        $items['PHP ' . $ext] = extension_loaded($ext);
    }
    $dir = storage();
    if (!is_dir($dir)) @mkdir($dir, 0770, true);
    $items['Storage writable'] = is_dir($dir) && is_writable($dir);
    return $items;
}
function boot(): void {
    foreach (requirements() as $name => $ok) {
        if (!$ok) throw new RuntimeException('Missing requirement: ' . $name);
    }
    foreach (['media', 'backups', 'logs', 'sessions'] as $dir) {
        if (!is_dir(storage($dir)) && !mkdir(storage($dir), 0770, true)) {
            throw new RuntimeException('Cannot create storage directory.');
        }
    }
    db();
    if (installed()) syncVersion();
}
