<?php

declare(strict_types=1);

use FreeTV\Tests\Regression\TestDatabaseSupport as Support;

require_once __DIR__ . '/TestDatabaseSupport.php';

function pass(string $message): void
{
    fwrite(STDOUT, "PASS  {$message}\n");
}

function info(string $message): void
{
    fwrite(STDOUT, "INFO  {$message}\n");
}

function fail(string $message): never
{
    fwrite(STDOUT, "FAIL  {$message}\n\nPreflight failed.\n");
    exit(1);
}

fwrite(STDOUT, "FreeTV Admin — Database Preflight\n\n");

if ($argc !== 1) {
    fail('This command does not accept arguments');
}

try {
    $settings = Support::configuration();
    pass('Database configuration found');

    $source = Support::connection($settings, $settings['database'], 'test_preflight_source');
    $source->getPdo();
    Support::assertSelectedDatabase($source, $settings['database']);
    pass('MariaDB connection established');
    pass('Source database accessible: ' . $settings['database']);

    $required = Support::verifyRequiredTables($source, $settings['database']);
    pass('Required source tables present: ' . implode(', ', $required));

    if (!Support::stateExists()) {
        info('No test database is installed');
    } else {
        $state = Support::loadState();
        Support::assertStateMatchesConfiguration($state, $settings);
        info('Tracked test database: ' . $state['test_database']);
        info('Lifecycle status: ' . $state['status']);
        if (!Support::databaseExists($source, $state['test_database'])) {
            fail('Tracked test database does not exist or is not visible');
        }

        $test = Support::connection($settings, $state['test_database'], 'test_preflight_target');
        $test->getPdo();
        Support::assertSelectedDatabase($test, $state['test_database']);
        pass('Tracked test database is accessible');
        if ($state['status'] === Support::STATE_READY) {
            Support::verifyRequiredTables($test, $state['test_database']);
            pass('Tracked test database schema is ready');
        }
    }

    fwrite(STDOUT, "\nPreflight successful.\n");
} catch (Throwable $exception) {
    fail($exception->getMessage());
}
