<?php

declare(strict_types=1);

namespace FreeTV\Tests\Regression;

use FreeTV\Admin\Database;
use FreeTV\Admin\DatabaseIdentifier;
use FreeTV\Admin\SqlPackageExecutor;
use Illuminate\Database\Capsule\Manager as Capsule;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/public/api/admin/Database.php';
require_once dirname(__DIR__, 3) . '/public/api/admin/DatabaseIdentifier.php';
require_once dirname(__DIR__, 3) . '/public/api/admin/SqlPackageExecutor.php';

final class TestDatabaseSupport
{
    public const STATE_INSTALLING = 'installing';
    public const STATE_READY = 'ready';
    public const TEST_DATABASE_PATTERN = '/\Afreetv_test_\d{8}_\d{6}_[a-f0-9]{4}\z/D';

    public static function appRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public static function statePath(): string
    {
        return self::appRoot() . '/temp/testing/database-state.json';
    }

    /** @return array{host: string, port: int, database: string, user: string, pass: string} */
    public static function configuration(): array
    {
        if (!Database::hasExplicitConfig()) {
            throw new RuntimeException(
                'Database configuration is incomplete; DB_HOST, DB_NAME, and DB_USER are required'
            );
        }

        $settings = Database::configuredConnectionSettings();
        DatabaseIdentifier::quote($settings['database']);
        if (preg_match(self::TEST_DATABASE_PATTERN, $settings['database']) === 1) {
            throw new RuntimeException('Configured source database matches the generated test-database pattern');
        }

        return $settings;
    }

    public static function connection(array $settings, ?string $database, string $name)
    {
        if ($database !== null) {
            DatabaseIdentifier::quote($database);
        }

        $connection = [
            'driver' => 'mysql',
            'host' => $settings['host'],
            'port' => $settings['port'],
            'database' => $database ?? '',
            'username' => $settings['user'],
            'password' => $settings['pass'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];
        $capsule = new Capsule();
        $capsule->addConnection($connection, $name);
        return $capsule->getConnection($name);
    }

    public static function assertSelectedDatabase($connection, string $expected): void
    {
        $selected = $connection->selectOne('SELECT DATABASE() AS database_name');
        $database = is_object($selected) ? ($selected->database_name ?? null) : null;
        if (!is_string($database) || $database !== $expected) {
            throw new RuntimeException('The active MariaDB database does not match the expected database');
        }
    }

    /** @return list<string> */
    public static function canonicalTableNames(): array
    {
        $schemaPath = self::appRoot() . '/sql/freetv_mariadb_schema-tables-only.sql';
        if (!is_file($schemaPath) || !is_readable($schemaPath)) {
            throw new RuntimeException('Canonical FreeTV schema package is missing or unreadable');
        }

        $sql = file_get_contents($schemaPath);
        if ($sql === false) {
            throw new RuntimeException('Canonical FreeTV schema package could not be read');
        }

        $tables = [];
        foreach ((new SqlPackageExecutor())->statements($sql) as $statement) {
            if (preg_match(
                '/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?\s*\(/i',
                $statement,
                $matches
            ) === 1) {
                $tables[] = $matches[1];
            }
        }

        if ($tables === []) {
            throw new RuntimeException('Canonical FreeTV schema package does not define any tables');
        }

        return array_values(array_unique($tables));
    }

    /** @return list<string> */
    public static function baseTableNames($connection, string $database): array
    {
        $rows = $connection->select(
            'SELECT TABLE_NAME AS table_name FROM information_schema.TABLES'
                . ' WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
            [$database, 'BASE TABLE']
        );

        return array_map(
            static fn(object $row): string => (string) $row->table_name,
            $rows
        );
    }

    /** @return list<string> */
    public static function verifyRequiredTables($connection, string $database): array
    {
        $required = self::canonicalTableNames();
        $missing = array_values(array_diff($required, self::baseTableNames($connection, $database)));
        if ($missing !== []) {
            throw new RuntimeException('Required tables missing: ' . implode(', ', $missing));
        }
        return $required;
    }

    public static function assertCloneableObjects($connection, string $database): void
    {
        $unsupported = [];
        $queries = [
            'views' => [
                'SELECT TABLE_NAME AS object_name FROM information_schema.TABLES'
                    . ' WHERE TABLE_SCHEMA = ? AND TABLE_TYPE <> ?',
                [$database, 'BASE TABLE'],
            ],
            'triggers' => [
                'SELECT TRIGGER_NAME AS object_name FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ?',
                [$database],
            ],
            'routines' => [
                'SELECT ROUTINE_NAME AS object_name FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ?',
                [$database],
            ],
            'events' => [
                'SELECT EVENT_NAME AS object_name FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ?',
                [$database],
            ],
        ];

        foreach ($queries as $kind => [$sql, $bindings]) {
            if ($connection->select($sql, $bindings) !== []) {
                $unsupported[] = $kind;
            }
        }
        if ($unsupported !== []) {
            throw new RuntimeException(
                'Safe cloning is not implemented for source database objects: ' . implode(', ', $unsupported)
            );
        }
    }

    public static function databaseExists($connection, string $database): bool
    {
        DatabaseIdentifier::quote($database);
        return $connection->selectOne(
            'SELECT SCHEMA_NAME AS schema_name FROM information_schema.SCHEMATA'
                . ' WHERE SCHEMA_NAME = ? LIMIT 1',
            [$database]
        ) !== null;
    }

    public static function generateTestDatabaseName($connection): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $name = 'freetv_test_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(2));
            if (!self::databaseExists($connection, $name)) {
                return $name;
            }
        }

        throw new RuntimeException('Could not generate an unused test database name');
    }

    /** @return array<string, int> */
    public static function rowCounts($connection, array $tables): array
    {
        $counts = [];
        foreach ($tables as $table) {
            $quoted = DatabaseIdentifier::quote($table);
            $row = $connection->selectOne("SELECT COUNT(*) AS row_count FROM {$quoted}");
            $counts[$table] = (int) ($row->row_count ?? -1);
        }
        return $counts;
    }

    public static function verifyCloneWriteAccess($connection): void
    {
        $table = DatabaseIdentifier::quote('freetv_clone_write_probe');
        try {
            $connection->statement("CREATE TEMPORARY TABLE {$table} (probe_value INT NOT NULL)");
            $connection->insert("INSERT INTO {$table} (probe_value) VALUES (?)", [1]);
            $row = $connection->selectOne("SELECT probe_value FROM {$table} LIMIT 1");
            if ((int) ($row->probe_value ?? 0) !== 1) {
                throw new RuntimeException('Test database write verification returned unexpected data');
            }
        } finally {
            $connection->statement("DROP TEMPORARY TABLE IF EXISTS {$table}");
        }
    }

    /** @return array{source_database: string, test_database: string, host: string, port: int, created_at: string, status: string} */
    public static function newState(array $settings, string $testDatabase): array
    {
        return [
            'source_database' => $settings['database'],
            'test_database' => $testDatabase,
            'host' => $settings['host'],
            'port' => $settings['port'],
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'status' => self::STATE_INSTALLING,
        ];
    }

    public static function stateExists(): bool
    {
        return is_file(self::statePath());
    }

    /** @return array{source_database: string, test_database: string, host: string, port: int, created_at: string, status: string} */
    public static function loadState(): array
    {
        $path = self::statePath();
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Test database state file is missing or unreadable');
        }

        try {
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException('Test database state file could not be read');
            }
            $state = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('Test database state file is malformed', 0, $exception);
        }
        if (!is_array($state)) {
            throw new RuntimeException('Test database state file is malformed');
        }

        foreach (['source_database', 'test_database', 'host', 'created_at', 'status'] as $key) {
            if (!isset($state[$key]) || !is_string($state[$key]) || $state[$key] === '') {
                throw new RuntimeException("Test database state has an invalid {$key}");
            }
        }
        if (!isset($state['port']) || !is_int($state['port'])
            || $state['port'] < 1 || $state['port'] > 65535) {
            throw new RuntimeException('Test database state has an invalid port');
        }
        if (!in_array($state['status'], [self::STATE_INSTALLING, self::STATE_READY], true)) {
            throw new RuntimeException('Test database state has an invalid lifecycle status');
        }
        if (preg_match(self::TEST_DATABASE_PATTERN, $state['test_database']) !== 1) {
            throw new RuntimeException('Tracked test database name is unsafe');
        }
        DatabaseIdentifier::quote($state['source_database']);
        if ($state['source_database'] === $state['test_database']) {
            throw new RuntimeException('Tracked test database must not equal the source database');
        }
        $createdAt = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $state['created_at']);
        if ($createdAt === false || $createdAt->format('Y-m-d\TH:i:s\Z') !== $state['created_at']) {
            throw new RuntimeException('Test database state has an invalid created_at timestamp');
        }

        /** @var array{source_database: string, test_database: string, host: string, port: int, created_at: string, status: string} $state */
        return $state;
    }

    public static function assertStateMatchesConfiguration(array $state, array $settings): void
    {
        if ($state['host'] !== $settings['host'] || $state['port'] !== $settings['port']) {
            throw new RuntimeException('Tracked test database host or port does not match current configuration');
        }
        if ($state['source_database'] !== $settings['database']) {
            throw new RuntimeException('Tracked source database does not match current configuration');
        }
        if ($state['test_database'] === $settings['database']) {
            throw new RuntimeException('Tracked test database equals the configured source database');
        }
    }

    public static function writeNewState(array $state): void
    {
        if (file_exists(self::statePath())) {
            throw new RuntimeException('A test database is already tracked; clean it before installing another');
        }
        self::writeState($state, false);
    }

    public static function updateState(array $state): void
    {
        self::loadState();
        self::writeState($state, true);
    }

    private static function writeState(array $state, bool $replace): void
    {
        $directory = dirname(self::statePath());
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the test database state directory');
        }

        $temporary = tempnam($directory, '.database-state-');
        if ($temporary === false) {
            throw new RuntimeException('Could not create a temporary test database state file');
        }
        try {
            chmod($temporary, 0600);
            $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (file_put_contents($temporary, $json . "\n", LOCK_EX) === false) {
                throw new RuntimeException('Could not write test database state');
            }
            if (!$replace && file_exists(self::statePath())) {
                throw new RuntimeException('A test database state file appeared during installation');
            }
            if (!rename($temporary, self::statePath())) {
                throw new RuntimeException('Could not promote test database state atomically');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public static function removeState(): void
    {
        if (!unlink(self::statePath())) {
            throw new RuntimeException('Test database was removed but its state file could not be deleted');
        }
    }

    public static function withClientDefaults(array $settings, callable $operation): mixed
    {
        $path = tempnam(sys_get_temp_dir(), 'freetv-mariadb-client-');
        if ($path === false) {
            throw new RuntimeException('Could not create a temporary MariaDB client configuration');
        }
        try {
            chmod($path, 0600);
            $contents = "[client]\n"
                . 'host=' . self::optionValue($settings['host']) . "\n"
                . 'port=' . $settings['port'] . "\n"
                . 'user=' . self::optionValue($settings['user']) . "\n"
                . 'password=' . self::optionValue($settings['pass']) . "\n";
            if (file_put_contents($path, $contents, LOCK_EX) === false) {
                throw new RuntimeException('Could not write temporary MariaDB client configuration');
            }
            return $operation($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private static function optionValue(string $value): string
    {
        return '"' . strtr($value, [
            '\\' => '\\\\',
            '"' => '\\"',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
        ]) . '"';
    }

    public static function runProcess(array $command, $stdin = null): void
    {
        $pipes = [];
        $process = proc_open($command, [
            0 => $stdin === null ? ['file', '/dev/null', 'r'] : $stdin,
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, self::appRoot());
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start required MariaDB client utility');
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0) {
            $detail = trim((string) ($stderr !== '' ? $stderr : $stdout));
            throw new RuntimeException(
                'MariaDB client utility failed' . ($detail !== '' ? ': ' . $detail : '')
            );
        }
    }

    public static function validateDump(string $dumpPath, string $sourceDatabase, array $sourceTables): void
    {
        $sql = file_get_contents($dumpPath);
        if ($sql === false || $sql === '') {
            throw new RuntimeException('MariaDB dump is empty or unreadable');
        }
        if (preg_match('/^\s*(?:CREATE|ALTER|DROP)\s+DATABASE\b/im', $sql) === 1
            || preg_match('/^\s*USE\s+/im', $sql) === 1) {
            throw new RuntimeException('MariaDB dump contains a database-selection or database-DDL statement');
        }
        $qualifiedSource = '/`' . preg_quote($sourceDatabase, '/') . '`\s*\.\s*`/i';
        if (preg_match($qualifiedSource, $sql) === 1) {
            throw new RuntimeException('MariaDB dump contains a source-qualified object reference');
        }
        if (preg_match('/`[^`]+`\s*\.\s*`/', $sql) === 1) {
            throw new RuntimeException('MariaDB dump contains a qualified object reference');
        }
        if (preg_match('/\bDEFINER\s*=/i', $sql) === 1) {
            throw new RuntimeException('MariaDB dump contains a DEFINER clause');
        }

        $allowedTables = array_fill_keys($sourceTables, true);
        foreach ((new SqlPackageExecutor())->statements($sql) as $statement) {
            $statement = ltrim($statement);
            if (preg_match('/\ASET\s+/i', $statement) === 1) {
                if (preg_match('/\ASET\s+(?:(?:@@)?GLOBAL\b|PERSIST(?:_ONLY)?\b|PASSWORD\b)/i', $statement) === 1) {
                    throw new RuntimeException('MariaDB dump contains a server-scoped SET statement');
                }
                continue;
            }
            if (preg_match('/\A(?:START\s+TRANSACTION|COMMIT)\z/i', $statement) === 1) {
                continue;
            }

            $table = null;
            foreach ([
                '/\ADROP\s+TABLE\s+IF\s+EXISTS\s+`([^`]+)`\z/is',
                '/\ACREATE\s+TABLE\s+`([^`]+)`\s*\(/is',
                '/\AINSERT\s+INTO\s+`([^`]+)`\s+VALUES\b/is',
            ] as $pattern) {
                if (preg_match($pattern, $statement, $matches) === 1) {
                    $table = $matches[1];
                    break;
                }
            }
            if ($table !== null && isset($allowedTables[$table])) {
                continue;
            }

            preg_match('/\A([A-Za-z]+)(?:\s+([A-Za-z]+))?/', $statement, $keywords);
            $statementType = strtoupper(trim(($keywords[1] ?? 'unknown') . ' ' . ($keywords[2] ?? '')));
            throw new RuntimeException(
                'MariaDB dump contains an unsupported statement type or target: ' . $statementType
            );
        }
    }
}
