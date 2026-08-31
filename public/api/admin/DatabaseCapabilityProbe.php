<?php

declare(strict_types=1);

namespace FreeTV\Admin;

final class DatabasePermissionsInsufficientException extends \RuntimeException
{
}

final class DatabaseCapabilityProbe
{
    public const MODE_CREATE_DATABASE = 'create_database';
    public const MODE_EXISTING_DATABASE = 'existing_database';

    private const NAME_PATTERN = '/^freetv_readiness_[a-f0-9]{12}$/D';
    private const VERIFIED_VALUE = 'freetv-readiness-write-read-ok';
    private const CREATE_DATABASE_PERMISSION_ERROR_CODES = [1044, 1142, 1227];

    private $configuredConnection;
    private $isolatedConnectionFactory;
    private $nameGenerator;
    private $logger;

    public function __construct(
        $configuredConnection,
        ?callable $isolatedConnectionFactory = null,
        ?callable $nameGenerator = null,
        ?callable $logger = null
    ) {
        $this->configuredConnection = $configuredConnection;
        $this->isolatedConnectionFactory = $isolatedConnectionFactory
            ?? static fn(string $database) => Database::createReadinessConnection($database);
        $this->nameGenerator = $nameGenerator
            ?? static fn(): string => 'freetv_readiness_' . bin2hex(random_bytes(6));
        $this->logger = $logger ?? static fn(string $message): bool => error_log($message);
    }

    public function detect(): string
    {
        $databaseName = $this->generateSafeName();

        try {
            $this->executeStatement(
                $this->configuredConnection,
                'CREATE DATABASE ' . $this->quoteIdentifier($databaseName)
            );
        } catch (\Throwable $exception) {
            if (!$this->isCreateDatabasePermissionDenied($exception)) {
                throw $exception;
            }

            return $this->probeConfiguredDatabase();
        }

        return $this->probeCreatedDatabase($databaseName);
    }

    private function probeCreatedDatabase(string $databaseName): string
    {
        $failure = null;
        $cleanupFailure = null;

        try {
            $connection = ($this->isolatedConnectionFactory)($databaseName);
            $connection->getPdo();
            $this->runWriteReadProbe($connection, $databaseName);
        } catch (\Throwable $exception) {
            $failure = $exception;
        }

        try {
            $this->dropDatabase($databaseName);
        } catch (\Throwable $exception) {
            $cleanupFailure = $exception;
        }

        if ($failure !== null) {
            $this->logCleanupFailure($cleanupFailure, 'temporary database');
            if ($this->isDatabasePermissionDenied($failure)) {
                throw new DatabasePermissionsInsufficientException(
                    'MariaDB account cannot create and use required database objects',
                    0,
                    $failure
                );
            }
            throw $failure;
        }
        if ($cleanupFailure !== null) {
            if ($this->isDatabasePermissionDenied($cleanupFailure)) {
                throw new DatabasePermissionsInsufficientException(
                    'MariaDB account cannot safely remove temporary readiness databases',
                    0,
                    $cleanupFailure
                );
            }
            throw $cleanupFailure;
        }

        return self::MODE_CREATE_DATABASE;
    }

    private function probeConfiguredDatabase(): string
    {
        $tableName = $this->generateSafeName();
        $tableCreated = false;
        $failure = null;
        $cleanupFailure = null;

        try {
            $this->executeStatement(
                $this->configuredConnection,
                'CREATE TABLE ' . $this->quoteIdentifier($tableName)
                    . ' (`probe_value` VARCHAR(64) NOT NULL) ENGINE=InnoDB'
            );
            $tableCreated = true;
            $this->writeAndVerify($this->configuredConnection, $tableName);
        } catch (\Throwable $exception) {
            $failure = $exception;
        }

        if ($tableCreated) {
            try {
                $this->dropTable($tableName);
            } catch (\Throwable $exception) {
                $cleanupFailure = $exception;
            }
        }

        if ($failure !== null || $cleanupFailure !== null) {
            $this->logCleanupFailure($cleanupFailure, 'temporary table');
            throw new DatabasePermissionsInsufficientException(
                'MariaDB account cannot create, write, read, and remove required table data',
                0,
                $failure ?? $cleanupFailure
            );
        }

        return self::MODE_EXISTING_DATABASE;
    }

    private function runWriteReadProbe($connection, string $tableName): void
    {
        $this->executeStatement(
            $connection,
            'CREATE TABLE ' . $this->quoteIdentifier($tableName)
                . ' (`probe_value` VARCHAR(64) NOT NULL) ENGINE=InnoDB'
        );
        $this->writeAndVerify($connection, $tableName);
    }

    private function writeAndVerify($connection, string $tableName): void
    {
        $this->executeStatement(
            $connection,
            'INSERT INTO ' . $this->quoteIdentifier($tableName) . ' (`probe_value`) VALUES (?)',
            [self::VERIFIED_VALUE]
        );
        $row = $connection->selectOne(
            'SELECT `probe_value` FROM ' . $this->quoteIdentifier($tableName) . ' LIMIT 1'
        );
        $value = is_object($row) && property_exists($row, 'probe_value')
            ? $row->probe_value
            : (is_array($row) && array_key_exists('probe_value', $row) ? $row['probe_value'] : null);
        if (!is_string($value) || !hash_equals(self::VERIFIED_VALUE, $value)) {
            throw new \RuntimeException('MariaDB readiness probe did not read back the exact inserted value');
        }
    }

    private function executeStatement($connection, string $sql, array $bindings = []): void
    {
        if ($connection->statement($sql, $bindings) !== true) {
            throw new \RuntimeException('MariaDB readiness statement did not complete successfully');
        }
    }

    private function dropDatabase(string $databaseName): void
    {
        $this->assertSafeName($databaseName);
        $this->executeStatement(
            $this->configuredConnection,
            'DROP DATABASE ' . $this->quoteIdentifier($databaseName)
        );
    }

    private function dropTable(string $tableName): void
    {
        $this->assertSafeName($tableName);
        $this->executeStatement(
            $this->configuredConnection,
            'DROP TABLE ' . $this->quoteIdentifier($tableName)
        );
    }

    private function generateSafeName(): string
    {
        $name = ($this->nameGenerator)();
        if (!is_string($name)) {
            throw new \RuntimeException('MariaDB readiness name generator returned an invalid value');
        }
        $this->assertSafeName($name);
        return $name;
    }

    private function assertSafeName(string $name): void
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new \RuntimeException('Refusing to use an unsafe MariaDB readiness object name');
        }
    }

    private function quoteIdentifier(string $name): string
    {
        $this->assertSafeName($name);
        return '`' . $name . '`';
    }

    private function isCreateDatabasePermissionDenied(\Throwable $exception): bool
    {
        return in_array($this->driverErrorCode($exception), self::CREATE_DATABASE_PERMISSION_ERROR_CODES, true);
    }

    private function isDatabasePermissionDenied(\Throwable $exception): bool
    {
        return in_array($this->driverErrorCode($exception), [1044, 1142, 1227], true);
    }

    private function driverErrorCode(\Throwable $exception): ?int
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof \PDOException && is_array($current->errorInfo ?? null)
                && isset($current->errorInfo[1]) && is_numeric($current->errorInfo[1])) {
                return (int) $current->errorInfo[1];
            }
            if (is_numeric($current->getCode()) && (int) $current->getCode() >= 1000) {
                return (int) $current->getCode();
            }
        }
        return null;
    }

    private function logCleanupFailure(?\Throwable $exception, string $object): void
    {
        if ($exception !== null) {
            ($this->logger)("Readiness {$object} cleanup error: " . $exception->getMessage());
        }
    }
}
