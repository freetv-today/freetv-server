<?php

declare(strict_types=1);

use FreeTV\Admin\DatabaseIdentifier;
use FreeTV\Tests\Regression\TestDatabaseSupport as Support;

require_once __DIR__ . '/TestDatabaseSupport.php';

function cleanFail(string $message): never
{
    fwrite(STDOUT, "FAIL  {$message}\n\nCleanup failed; state was preserved.\n");
    exit(1);
}

fwrite(STDOUT, "FreeTV Admin — Test Database Cleanup\n\n");

if ($argc !== 1) {
    cleanFail('This command does not accept arguments');
}

try {
    $state = Support::loadState();
    $settings = Support::configuration();
    Support::assertStateMatchesConfiguration($state, $settings);

    $source = Support::connection($settings, $settings['database'], 'test_clean_source');
    $source->getPdo();
    Support::assertSelectedDatabase($source, $settings['database']);
    if (!Support::databaseExists($source, $state['test_database'])) {
        cleanFail('Tracked test database does not exist or is not visible: ' . $state['test_database']);
    }

    fwrite(STDOUT, "Database to remove: {$state['test_database']}\n");
    fwrite(STDOUT, "Lifecycle status: {$state['status']}\n");
    if (!function_exists('stream_isatty') || !stream_isatty(STDIN)) {
        cleanFail('Interactive confirmation requires a terminal');
    }

    fwrite(STDOUT, "Type the exact database name to confirm cleanup: ");
    $confirmation = fgets(STDIN);
    if ($confirmation === false || trim($confirmation) !== $state['test_database']) {
        fwrite(STDOUT, "\nCleanup cancelled; no changes were made.\n");
        exit(1);
    }

    $server = Support::connection($settings, null, 'test_clean_server');
    $server->getPdo();
    $quotedTestDatabase = DatabaseIdentifier::quote($state['test_database']);
    $server->statement("DROP DATABASE {$quotedTestDatabase}");
    if (Support::databaseExists($source, $state['test_database'])) {
        cleanFail('MariaDB still reports the tracked test database after cleanup');
    }

    Support::removeState();
    fwrite(STDOUT, "PASS  Removed test database: {$state['test_database']}\n");
    fwrite(STDOUT, "PASS  Removed database state file\n\nCleanup successful.\n");
} catch (Throwable $exception) {
    cleanFail($exception->getMessage());
}
