<?php

declare(strict_types=1);

use FreeTV\Admin\DatabaseIdentifier;
use FreeTV\Tests\Regression\TestDatabaseSupport as Support;

require_once __DIR__ . '/TestDatabaseSupport.php';

function installPass(string $message): void
{
    fwrite(STDOUT, "PASS  {$message}\n");
}

function installFail(string $message): never
{
    fwrite(STDOUT, "FAIL  {$message}\n\nInstallation failed.\n");
    exit(1);
}

fwrite(STDOUT, "FreeTV Admin — Test Database Installation\n\n");

if ($argc !== 1) {
    installFail('This command does not accept arguments');
}

$stateRecorded = false;
$testDatabase = null;
$dumpPath = null;
$failure = null;

try {
    $settings = Support::configuration();
    installPass('Database configuration found');

    $source = Support::connection($settings, $settings['database'], 'test_install_source');
    $source->getPdo();
    Support::assertSelectedDatabase($source, $settings['database']);
    $requiredTables = Support::verifyRequiredTables($source, $settings['database']);
    Support::assertCloneableObjects($source, $settings['database']);
    $sourceTables = Support::baseTableNames($source, $settings['database']);
    installPass('Source database verified: ' . $settings['database']);

    if (Support::stateExists()) {
        $existing = Support::loadState();
        installFail(
            'A test database is already tracked: ' . $existing['test_database']
                . ' (' . $existing['status'] . ')'
        );
    }

    $testDatabase = Support::generateTestDatabaseName($source);
    $state = Support::newState($settings, $testDatabase);
    Support::writeNewState($state);
    $stateRecorded = true;
    installPass('Installation state recorded: ' . $testDatabase);

    $server = Support::connection($settings, null, 'test_install_server');
    $server->getPdo();
    $quotedTestDatabase = DatabaseIdentifier::quote($testDatabase);
    $server->statement(
        "CREATE DATABASE {$quotedTestDatabase} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    installPass('Test database created: ' . $testDatabase);

    $dumpPath = tempnam(sys_get_temp_dir(), 'freetv-database-dump-');
    if ($dumpPath === false) {
        throw new RuntimeException('Could not create a temporary database dump path');
    }

    Support::withClientDefaults($settings, function (string $defaultsPath) use (
        $settings,
        $testDatabase,
        $dumpPath,
        $sourceTables
    ): void {
        Support::runProcess([
            'mariadb-dump',
            '--defaults-extra-file=' . $defaultsPath,
            '--default-character-set=utf8mb4',
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--skip-add-locks',
            '--skip-comments',
            '--skip-dump-date',
            '--quote-names',
            '--skip-triggers',
            '--skip-routines',
            '--skip-events',
            '--result-file=' . $dumpPath,
            '--',
            $settings['database'],
        ]);
        Support::validateDump($dumpPath, $settings['database'], $sourceTables);

        $input = fopen($dumpPath, 'rb');
        if ($input === false) {
            throw new RuntimeException('Could not open the validated database dump');
        }
        try {
            Support::runProcess([
                'mariadb',
                '--defaults-extra-file=' . $defaultsPath,
                '--default-character-set=utf8mb4',
                '--database=' . $testDatabase,
            ], $input);
        } finally {
            fclose($input);
        }
    });
    installPass('Source schema and data imported');

    $clone = Support::connection($settings, $testDatabase, 'test_install_clone');
    $clone->getPdo();
    Support::assertSelectedDatabase($clone, $testDatabase);
    Support::verifyRequiredTables($clone, $testDatabase);

    $cloneTables = Support::baseTableNames($clone, $testDatabase);
    if ($sourceTables !== $cloneTables) {
        throw new RuntimeException('Source and clone table sets do not match');
    }
    if (Support::rowCounts($source, $requiredTables) !== Support::rowCounts($clone, $requiredTables)) {
        throw new RuntimeException('Source and clone row counts do not match');
    }
    Support::verifyCloneWriteAccess($clone);
    installPass('Clone structure, row counts, and reversible write access verified');

    $state['status'] = Support::STATE_READY;
    Support::updateState($state);
    installPass('Test database is ready: ' . $testDatabase);
    fwrite(STDOUT, "\nInstallation successful.\n");
} catch (Throwable $exception) {
    if ($stateRecorded && $testDatabase !== null) {
        fwrite(STDOUT, "INFO  Installation state preserved as installing: {$testDatabase}\n");
        fwrite(STDOUT, "INFO  Verify the target, then run npm run test:db:clean when appropriate.\n");
    }
    if (preg_match(
        '/(?:access denied|permission|privilege|command denied|SQLSTATE\[42000\].*(?:1044|1142|1227))/i',
        $exception->getMessage()
    ) === 1) {
        fwrite(
            STDOUT,
            "INFO  Check the configured account's database privileges in the MariaDB documentation.\n"
        );
    }
    $failure = $exception->getMessage();
} finally {
    if (is_string($dumpPath) && is_file($dumpPath)) {
        unlink($dumpPath);
    }
}

if ($failure !== null) {
    installFail($failure);
}
