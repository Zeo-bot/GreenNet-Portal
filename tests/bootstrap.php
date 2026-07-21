<?php

declare(strict_types=1);

define('GRENNET_TEST_ROOT', dirname(__DIR__));
define('GRENNET_LIVE_DATABASE', GRENNET_TEST_ROOT . '/src/database/database.sqlite');
define('GRENNET_LIVE_BACKUPS', GRENNET_TEST_ROOT . '/src/storage/backups');

require GRENNET_TEST_ROOT . '/vendor/autoload.php';

if ((string) getenv('APP_ENV') !== 'testing') {
    throw new RuntimeException('Tests require APP_ENV=testing.');
}

foreach (['MIKROTIK_HOST', 'MIKROTIK_USERNAME', 'MIKROTIK_PASSWORD'] as $key) {
    if ((string) getenv($key) !== '') {
        throw new RuntimeException($key . ' must be empty during tests.');
    }
}
