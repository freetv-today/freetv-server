<?php

declare(strict_types=1);

namespace FreeTV\Admin;

final class SchemaBootstrapper
{
    public const ALREADY_INITIALIZED = 'already_initialized';
    public const SCHEMA_PRESENT = 'schema_present';
    public const SCHEMA_INSTALLED = 'schema_installed';

    public const REQUIRED_TABLES = ['users', 'playlists', 'playlist_shows', 'app_settings'];

    private $bootstrapConnection;
    private $configuredConnectionFactory;
    private $createDatabaseCapability;
    private string $databaseName;
    private string $schemaPath;
    private SqlPackageExecutor $executor;

    public function __construct(
        $bootstrapConnection,
        callable $configuredConnectionFactory,
        callable $createDatabaseCapability,
        string $databaseName,
        string $schemaPath,
        ?SqlPackageExecutor $executor = null
    ) {
        $this->bootstrapConnection = $bootstrapConnection;
        $this->configuredConnectionFactory = $configuredConnectionFactory;
        $this->createDatabaseCapability = $createDatabaseCapability;
        $this->databaseName = $databaseName;
        $this->schemaPath = $schemaPath;
        $this->executor = $executor ?? new SqlPackageExecutor();
    }

    /** @return array{0: object, 1: string} */
    public function prepare(): array
    {
        $databaseMissing = false;
        try {
            $connection = ($this->configuredConnectionFactory)();
            $connection->getPdo();
        } catch (\Throwable $exception) {
            if (!MariaDbError::isUnknownDatabase($exception)) {
                throw $exception;
            }
            $databaseMissing = true;
            $connection = null;
        }

        if ($connection !== null && $this->hasInitializedUser($connection)) {
            return [$connection, self::ALREADY_INITIALIZED];
        }
        if ($connection !== null && $this->missingTables($connection) === []) {
            return [$connection, self::SCHEMA_PRESENT];
        }

        // Read and parse the entire canonical package before making any schema changes.
        if (!is_file($this->schemaPath) || !is_readable($this->schemaPath)) {
            throw new \RuntimeException('Canonical FreeTV schema package is missing or unreadable');
        }
        $sql = file_get_contents($this->schemaPath);
        if ($sql === false) {
            throw new \RuntimeException('Canonical FreeTV schema package could not be read');
        }
        $statements = $this->executor->statements($sql);

        if ($databaseMissing) {
            $quotedName = DatabaseIdentifier::quote($this->databaseName);
            if (($this->createDatabaseCapability)() !== DatabaseCapabilityProbe::MODE_CREATE_DATABASE) {
                throw new DatabasePermissionsInsufficientException(
                    'MariaDB account cannot create the configured database'
                );
            }
            if ($this->bootstrapConnection->statement(
                'CREATE DATABASE ' . $quotedName
                    . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            ) !== true) {
                throw new \RuntimeException('Configured FreeTV database creation failed');
            }
            $connection = ($this->configuredConnectionFactory)();
            $connection->getPdo();
        }

        $this->executor->executeStatements($connection, $statements);
        $missing = $this->missingTables($connection);
        if ($missing !== []) {
            throw new \RuntimeException('Canonical FreeTV schema installation is incomplete');
        }

        return [$connection, self::SCHEMA_INSTALLED];
    }

    /** @return list<string> */
    private function missingTables($connection): array
    {
        $schema = $connection->getSchemaBuilder();
        return array_values(array_filter(
            self::REQUIRED_TABLES,
            static fn(string $table): bool => !$schema->hasTable($table)
        ));
    }

    private function hasInitializedUser($connection): bool
    {
        if (!$connection->getSchemaBuilder()->hasTable('users')) {
            return false;
        }

        return $connection->table('users')->exists();
    }
}
