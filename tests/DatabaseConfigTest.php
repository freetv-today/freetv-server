<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../public/api/admin/Database.php';

use FreeTV\Admin\Database;
use FreeTV\Admin\RuntimeEnvironment;
use FreeTV\Admin\ServerPaths;

RuntimeEnvironment::load((new ServerPaths())->appRoot());

foreach (['VITE_DB_HOST', 'DB_HOST', 'VITE_DB_NAME', 'DB_NAME', 'VITE_DB_USER', 'DB_USER', 'VITE_DB_PASS', 'DB_PASS', 'DB_PORT'] as $name) {
    putenv($name);
}

putenv('DB_HOST=127.0.0.1');
putenv('DB_NAME=freetv');
putenv('DB_USER=freetv');
putenv('DB_PASS');

if (Database::createConfiguredConnection()->getConfig('port') !== 3306) {
    throw new RuntimeException('Absent DB_PORT must default to 3306');
}

foreach ([1, 3307, 65535] as $validPort) {
    putenv('DB_PORT=' . $validPort);
    if (Database::createConfiguredConnection()->getConfig('port') !== $validPort) {
        throw new RuntimeException('A valid custom DB_PORT must be used');
    }
}

putenv('DB_PORT=');
foreach (['', '   '] as $emptyPort) {
    putenv('DB_PORT=' . $emptyPort);
    if (Database::createConfiguredConnection()->getConfig('port') !== 3306) {
        throw new RuntimeException('Empty DB_PORT must default to 3306');
    }
}

foreach (['not-a-number', '0', '-1', '65536'] as $invalidPort) {
    putenv('DB_PORT=' . $invalidPort);
    try {
        Database::createConfiguredConnection();
        throw new RuntimeException('Invalid DB_PORT was accepted: ' . $invalidPort);
    } catch (UnexpectedValueException $exception) {
        if ($exception->getMessage() !== 'DB_PORT must be an integer between 1 and 65535') {
            throw new RuntimeException('Invalid DB_PORT produced an unclear configuration error');
        }
    }
}
putenv('DB_PORT');

if (!Database::hasExplicitConfig()) {
    throw new RuntimeException('Absent DB_PASS must be accepted when required database settings are present');
}

putenv('DB_PASS=');
if (!Database::hasExplicitConfig()) {
    throw new RuntimeException('Empty DB_PASS must be accepted when required database settings are present');
}

putenv('DB_PASS=secret');
if (!Database::hasExplicitConfig()) {
    throw new RuntimeException('Non-empty DB_PASS must be accepted when required database settings are present');
}

putenv('DB_HOST');
if (Database::hasExplicitConfig()) {
    throw new RuntimeException('Missing DB_HOST must be rejected');
}
putenv('DB_HOST=');
if (Database::hasExplicitConfig()) {
    throw new RuntimeException('Empty DB_HOST must be rejected');
}
putenv('DB_HOST=127.0.0.1');

putenv('DB_HOST=127.0.0.1');
putenv('DB_NAME');
if (Database::hasExplicitConfig()) {
    throw new RuntimeException('Missing DB_NAME must be rejected');
}
putenv('DB_NAME=');
if (Database::hasExplicitConfig()) {
    throw new RuntimeException('Empty DB_NAME must be rejected');
}
putenv('DB_NAME=freetv');

putenv('DB_USER');
if (Database::hasExplicitConfig()) {
    throw new RuntimeException('Missing DB_USER must be rejected');
}
putenv('DB_USER=');
if (Database::hasExplicitConfig()) {
    throw new RuntimeException('Empty DB_USER must be rejected');
}

putenv('DB_USER=freetv');
putenv('VITE_DB_HOST=127.0.0.1');
putenv('VITE_DB_NAME=freetv');
putenv('VITE_DB_USER=freetv');
putenv('VITE_DB_PASS');
putenv('DB_HOST');
putenv('DB_NAME');
putenv('DB_USER');
if (!Database::hasExplicitConfig()) {
    throw new RuntimeException('VITE_DB_* aliases must remain supported without VITE_DB_PASS');
}

fwrite(STDOUT, "DatabaseConfigTest passed\n");
