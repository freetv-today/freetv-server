<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../public/api/admin/Database.php';

use FreeTV\Admin\Database;

foreach (['VITE_DB_HOST', 'DB_HOST', 'VITE_DB_NAME', 'DB_NAME', 'VITE_DB_USER', 'DB_USER', 'VITE_DB_PASS', 'DB_PASS'] as $name) {
    putenv($name);
}

putenv('DB_HOST=127.0.0.1');
putenv('DB_NAME=freetv');
putenv('DB_USER=freetv');
putenv('DB_PASS=');

if (!Database::hasExplicitConfig()) {
    throw new RuntimeException('Empty DB_PASS must be accepted when required database settings are present');
}

putenv('DB_HOST');
if (Database::hasExplicitConfig()) {
    throw new RuntimeException('Missing DB_HOST must be rejected');
}

putenv('DB_HOST=127.0.0.1');
putenv('DB_NAME');
if (Database::hasExplicitConfig()) {
    throw new RuntimeException('Missing DB_NAME must be rejected');
}

putenv('DB_NAME=freetv');
putenv('DB_USER');
if (Database::hasExplicitConfig()) {
    throw new RuntimeException('Missing DB_USER must be rejected');
}

fwrite(STDOUT, "DatabaseConfigTest passed\n");