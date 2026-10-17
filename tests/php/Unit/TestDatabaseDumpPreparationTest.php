<?php

declare(strict_types=1);

require_once __DIR__ . '/../../regression/support/TestDatabaseSupport.php';

use FreeTV\Tests\Regression\TestDatabaseSupport;
use PHPUnit\Framework\TestCase;

final class TestDatabaseDumpPreparationTest extends TestCase
{
    private const TEST_DATABASE = 'freetv_test_20261015_001800_abcd';

    /** @var list<string> */
    private array $dumpPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->dumpPaths as $dumpPath) {
            if (is_file($dumpPath)) {
                unlink($dumpPath);
            }
        }

        $this->dumpPaths = [];
    }

    public function testPreparesSafeDumpWithControlledSessionSafeguards(): void
    {
        $dump = <<<'SQL'
/* ordinary dump comment */
/*!999999 USE `source_database` */;
CREATE TABLE `expected_table` (`id` INT NOT NULL, `value` VARCHAR(100));
/*!999999 DROP DATABASE `source_database` */;
INSERT INTO `expected_table` VALUES (1,'executable-looking text: /*!999999 USE source_database */');
SQL;
        $dumpPath = $this->createDump($dump);

        TestDatabaseSupport::prepareDumpForImport(
            $dumpPath,
            ['expected_table'],
            self::TEST_DATABASE
        );

        $prepared = $this->readDump($dumpPath);

        self::assertStringContainsString('USE `' . self::TEST_DATABASE . '`;', $prepared);
        self::assertDoesNotMatchRegularExpression('/^\s*USE\s+`source_database`/im', $prepared);
        self::assertDoesNotMatchRegularExpression('/^\s*DROP\s+DATABASE\b/im', $prepared);
        self::assertStringNotContainsString('/*!999999 USE `source_database` */', $prepared);
        self::assertStringNotContainsString('/*!999999 DROP DATABASE `source_database` */', $prepared);
        self::assertStringContainsString('CREATE TABLE `expected_table`', $prepared);
        self::assertStringContainsString('INSERT INTO `expected_table`', $prepared);
        self::assertStringContainsString(
            'executable-looking text: /*!999999 USE source_database */',
            $prepared
        );
        self::assertStringContainsString("SET SESSION SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';", $prepared);
        self::assertStringContainsString("SET SESSION TIME_ZONE = '+00:00';", $prepared);
        self::assertStringContainsString('SET SESSION FOREIGN_KEY_CHECKS = 0;', $prepared);
        self::assertStringContainsString('SET SESSION FOREIGN_KEY_CHECKS = 1;', $prepared);
    }

    public function testRejectsOrdinarySourceDatabaseSelectionWithoutRewritingDump(): void
    {
        $dump = "USE `source_database`;\nCREATE TABLE `expected_table` (`id` INT);\n";

        $this->assertRejectedWithoutRewrite($dump, ['expected_table'], self::TEST_DATABASE);
    }

    public function testRejectsUnsafeTargetDatabaseNamesWithoutRewritingDump(): void
    {
        $dump = 'CREATE TABLE `expected_table` (`id` INT);';
        $unsafeNames = [
            'production_database',
            '',
            'freetv_test_20261015_bad_abcd',
        ];

        foreach ($unsafeNames as $unsafeName) {
            $this->assertRejectedWithoutRewrite(
                $dump,
                ['expected_table'],
                $unsafeName,
                'Prepared dump target is not a generated test database'
            );
        }
    }

    public function testRejectsUnsupportedStatementsAndTargetsWithoutRewritingDump(): void
    {
        $unsupportedDumps = [
            'DROP DATABASE `source_database`;',
            'DROP TABLE `expected_table`;',
            'CREATE TABLE `unexpected_table` (`id` INT);',
            'INSERT INTO `unexpected_table` VALUES (1);',
        ];

        foreach ($unsupportedDumps as $dump) {
            $this->assertRejectedWithoutRewrite(
                $dump,
                ['expected_table'],
                self::TEST_DATABASE,
                'unsupported statement type or target'
            );
        }
    }

    public function testRejectsEmptyDumpWithoutRewritingDump(): void
    {
        $this->assertRejectedWithoutRewrite(
            '',
            ['expected_table'],
            self::TEST_DATABASE,
            'MariaDB dump is empty or unreadable'
        );
    }

    public function testRejectsDumpWithNoImportableStatementsWithoutRewritingDump(): void
    {
        $this->assertRejectedWithoutRewrite(
            "-- ordinary comment only\n/* another ordinary comment */\n",
            ['expected_table'],
            self::TEST_DATABASE,
            'MariaDB dump does not contain importable statements'
        );
    }

    private function assertRejectedWithoutRewrite(
        string $dump,
        array $sourceTables,
        string $testDatabase,
        string $expectedMessage = 'unsupported statement'
    ): void {
        $dumpPath = $this->createDump($dump);

        try {
            TestDatabaseSupport::prepareDumpForImport($dumpPath, $sourceTables, $testDatabase);
            self::fail('Unsafe dump preparation unexpectedly succeeded');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString($expectedMessage, $exception->getMessage());
        }

        self::assertSame($dump, $this->readDump($dumpPath));
    }

    private function createDump(string $contents): string
    {
        $dumpPath = tempnam(sys_get_temp_dir(), 'freetv-dump-preparation-phpunit-');
        if ($dumpPath === false) {
            self::fail('Could not create temporary SQL dump');
        }
        $this->dumpPaths[] = $dumpPath;

        if (file_put_contents($dumpPath, $contents, LOCK_EX) !== strlen($contents)) {
            self::fail('Could not write temporary SQL dump');
        }

        return $dumpPath;
    }

    private function readDump(string $dumpPath): string
    {
        $contents = file_get_contents($dumpPath);
        if ($contents === false) {
            self::fail('Could not read temporary SQL dump');
        }

        return $contents;
    }
}
