<?php
declare(strict_types=1);
return [
    'name' => 'PULSE · Dietikon',
    'timezone' => 'Europe/Zurich',
    'storage' => getenv('PULSE_STORAGE') ?: dirname(__DIR__) . '/storage',
    'updater_state' => getenv('PULSE_UPDATER_STATE') ?: '/var/lib/pulse-updater',
    'updater_config' => getenv('PULSE_UPDATER_CONFIG') ?: '/etc/pulse/updater.json',
    'session_seconds' => 14400,
    'max_upload_bytes' => 8 * 1024 * 1024,
];
