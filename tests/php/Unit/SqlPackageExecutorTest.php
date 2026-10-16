<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/DatasetPackage.php';
require_once __DIR__ . '/../../../public/api/admin/SqlPackageExecutor.php';

use FreeTV\Admin\DatasetPackage;
use FreeTV\Admin\SqlPackageExecutor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SqlExecutorRecordingConnection
{
    public array $calls = [];

    public function __construct(private ?int $failAt = null)
    {
    }

    public function unprepared(string $statement): bool
    {
        $this->calls[] = $statement;
        return $this->failAt === null || count($this->calls) !== $this->failAt;
    }
}

final class SqlPackageExecutorTest extends TestCase
{
    private string $root;
    private SqlPackageExecutor $executor;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/freetv-sql-executor-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->root, 0700, true));
        $this->executor = new SqlPackageExecutor();
    }

    protected function tearDown(): void
    {
        DatasetPackage::removeTree($this->root);
    }

    #[DataProvider('statementProvider')]
    public function testParsesExactStatements(string $sql, array $expected): void
    {
        self::assertSame($expected, $this->executor->statements($sql));
    }

    public static function statementProvider(): array
    {
        return [
            'quoted semicolons' => [
                <<<'SQL'
INSERT INTO `example` VALUES ('semi;colon'), ("quoted;value");
SELECT `identifier;with;semicolons` FROM `example`;
SQL,
                [
                    'INSERT INTO `example` VALUES (\'semi;colon\'), ("quoted;value")',
                    'SELECT `identifier;with;semicolons` FROM `example`',
                ],
            ],
            'escaped and doubled quotes' => [
                <<<'SQL'
INSERT INTO t VALUES ('escaped\'quote;still string');
INSERT INTO t VALUES ('it''s;safe'), ("a""b;c"), (`a``b;c`);
SQL,
                [
                    "INSERT INTO t VALUES ('escaped\\'quote;still string')",
                    'INSERT INTO t VALUES (\'it\'\'s;safe\'), ("a""b;c"), (`a``b;c`)',
                ],
            ],
            'all comment forms' => [
                <<<'SQL'
-- leading ; comment
SELECT 1;
# hash ; comment
SELECT 2;
/* block ; comment */
SELECT 3;
SQL,
                ['SELECT 1', 'SELECT 2', 'SELECT 3'],
            ],
            'whitespace and no final delimiter' => [
                "  SELECT 1  ;\n\n  SELECT 2  ",
                ['SELECT 1', 'SELECT 2'],
            ],
            'empty comments' => [" -- comment only\n/* block */ # hash\n", []],
        ];
    }

    #[DataProvider('unterminatedProvider')]
    public function testRejectsUnterminatedQuotesAndBlockComments(string $sql): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Canonical FreeTV schema package contains unterminated SQL');
        $this->executor->statements($sql);
    }

    public static function unterminatedProvider(): array
    {
        return [
            'single quote' => ["SELECT 'unterminated"],
            'double quote' => ['SELECT "unterminated'],
            'identifier quote' => ['SELECT `unterminated'],
            'block comment' => ['SELECT 1; /* unterminated'],
        ];
    }

    public function testCanonicalTablesOnlySchemaContainsSevenStatements(): void
    {
        $path = dirname(__DIR__, 3) . '/sql/freetv_mariadb_schema-tables-only.sql';
        $sql = file_get_contents($path);

        self::assertNotFalse($sql);
        self::assertCount(7, $this->executor->statements($sql));
    }

    public function testExecuteStatementsPassesEveryStatementThroughUnchangedAndInOrder(): void
    {
        $statements = [
            'CREATE TABLE `a` (`value` VARCHAR(20))',
            "INSERT INTO `a` VALUES ('semi;colon')",
            'ALTER TABLE `a` ADD `other` INT',
        ];
        $connection = new SqlExecutorRecordingConnection();

        $this->executor->executeStatements($connection, $statements);

        self::assertSame($statements, $connection->calls);
    }

    public function testExecuteStatementsStopsAfterFirstFailure(): void
    {
        $connection = new SqlExecutorRecordingConnection(2);
        $statements = ['first statement', 'failing statement', 'must not execute'];

        try {
            $this->executor->executeStatements($connection, $statements);
            self::fail('Expected failed SQL statement');
        } catch (RuntimeException $exception) {
            self::assertSame('A canonical FreeTV schema statement failed', $exception->getMessage());
        }

        self::assertSame(['first statement', 'failing statement'], $connection->calls);
    }

    public function testExecuteFileReadsSyntheticSqlAndExecutesParsedStatements(): void
    {
        $path = $this->root . '/fixture.sql';
        file_put_contents($path, "SELECT 'one;value';\n-- comment\nSELECT 2");
        $connection = new SqlExecutorRecordingConnection();

        $this->executor->executeFile($connection, $path);

        self::assertSame(["SELECT 'one;value'", 'SELECT 2'], $connection->calls);
    }

    public function testExecuteFileRejectsMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Canonical FreeTV schema package is missing or unreadable');
        $this->executor->executeFile(
            new SqlExecutorRecordingConnection(),
            $this->root . '/missing.sql'
        );
    }
}
