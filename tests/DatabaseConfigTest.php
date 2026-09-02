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
putenv('DB_PASS');

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