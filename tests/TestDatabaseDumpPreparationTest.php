<?php

declare(strict_types=1);

use FreeTV\Tests\Regression\TestDatabaseSupport as Support;

require_once __DIR__ . '/regression/support/TestDatabaseSupport.php';

$dumpPath = tempnam(sys_get_temp_dir(), 'freetv-dump-preparation-test-');
if ($dumpPath === false) {
    throw new RuntimeException('Could not create test dump');
}

try {
    $dump = <<<'SQL'
/* ordinary dump comment */
/*!999999 USE `source_database` */;
CREATE TABLE `expected_table` (`id` INT NOT NULL, `value` VARCHAR(100));
/*!999999 DROP DATABASE `source_database` */;
INSERT INTO `expected_table` VALUES (1,'executable-looking text: /*!999999 USE source_database */');
SQL;
    file_put_contents($dumpPath, $dump, LOCK_EX);

    $testDatabase = 'freetv_test_20261015_001800_abcd';
    Support::prepareDumpForImport($dumpPath, ['expected_table'], $testDatabase);
    $prepared = file_get_contents($dumpPath);
    if ($prepared === false) {
        throw new RuntimeException('Could not read prepared test dump');
    }

    if (preg_match('/^\s*USE\s+`source_database`/im', $prepared) === 1
        || preg_match('/^\s*DROP\s+DATABASE\b/im', $prepared) === 1) {
        throw new RuntimeException('Executable-comment SQL survived dump preparation');
    }
    if (!str_contains($prepared, "USE `{$testDatabase}`;")) {
        throw new RuntimeException('Prepared dump does not select the generated test database');
    }
    if (!str_contains($prepared, 'CREATE TABLE `expected_table`')
        || !str_contains($prepared, 'INSERT INTO `expected_table`')) {
        throw new RuntimeException('Expected table statements were not preserved');
    }
    if (!str_contains($prepared, 'executable-looking text: /*!999999 USE source_database */')) {
        throw new RuntimeException('SQL comment text inside a quoted value was not preserved');
    }
    if (!str_contains($prepared, 'SET SESSION FOREIGN_KEY_CHECKS = 0;')
        || !str_contains($prepared, 'SET SESSION FOREIGN_KEY_CHECKS = 1;')) {
        throw new RuntimeException('Controlled import safeguards were not added');
    }

    file_put_contents($dumpPath, "USE `source_database`;\nCREATE TABLE `expected_table` (`id` INT);\n");
    try {
        Support::prepareDumpForImport($dumpPath, ['expected_table'], $testDatabase);
        throw new RuntimeException('Ordinary database selection was not rejected');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'unsupported statement')) {
            throw $exception;
        }
    }

    fwrite(STDOUT, "Test database dump preparation test passed.\n");
} finally {
    if (is_file($dumpPath)) {
        unlink($dumpPath);
    }
}
