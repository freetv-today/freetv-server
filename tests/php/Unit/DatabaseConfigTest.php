<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/Database.php';

use FreeTV\Admin\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DatabaseConfigTest extends TestCase
{
    private const NAMES = [
        'VITE_DB_HOST', 'DB_HOST', 'VITE_DB_NAME', 'DB_NAME', 'VITE_DB_USER', 'DB_USER',
        'VITE_DB_PASS', 'DB_PASS', 'DB_PORT',
    ];
    private array $environment = [];
    private array $environmentArray = [];

    protected function setUp(): void
    {
        foreach (self::NAMES as $name) {
            $this->environment[$name] = getenv($name);
            $this->environmentArray[$name] = [array_key_exists($name, $_ENV), $_ENV[$name] ?? null];
            $this->set($name, null);
        }
        $this->set('VITE_DB_HOST', 'db.test.invalid');
        $this->set('VITE_DB_NAME', 'freetv_test');
        $this->set('VITE_DB_USER', 'synthetic_user');
        $this->set('VITE_DB_PASS', 'synthetic_password');
        $this->set('DB_HOST', 'db.test.invalid');
        $this->set('DB_NAME', 'freetv_test');
        $this->set('DB_USER', 'synthetic_user');
        $this->set('DB_PASS', 'synthetic_password');
        Database::configuredConnectionSettings(); // Loads .env once while safe explicit values take precedence.
        foreach (['VITE_DB_HOST', 'VITE_DB_NAME', 'VITE_DB_USER', 'VITE_DB_PASS'] as $name) {
            $this->set($name, null);
        }
    }

    protected function tearDown(): void
    {
        foreach (self::NAMES as $name) {
            $previous = $this->environment[$name];
            $previous === false ? putenv($name) : putenv($name . '=' . $previous);
            [$present, $value] = $this->environmentArray[$name];
            if ($present) { $_ENV[$name] = $value; } else { unset($_ENV[$name]); }
        }
    }

    #[DataProvider('defaultPortProvider')]
    public function testAbsentOrBlankPortDefaultsTo3306(?string $value): void
    {
        $this->set('DB_PORT', $value);
        self::assertSame(3306, Database::configuredConnectionSettings()['port']);
    }

    public static function defaultPortProvider(): array
    {
        return ['absent' => [null], 'empty' => [''], 'whitespace' => ['   ']];
    }

    #[DataProvider('validPortProvider')]
    public function testValidPortIsUsed(string $value, int $expected): void
    {
        $this->set('DB_PORT', $value);
        self::assertSame($expected, Database::configuredConnectionSettings()['port']);
    }

    public static function validPortProvider(): array
    {
        return [
            'minimum' => ['1', 1],
            'custom' => ['3307', 3307],
            'maximum' => ['65535', 65535],
            'numeric whitespace is trimmed' => [' 3307 ', 3307],
        ];
    }

    #[DataProvider('invalidPortProvider')]
    public function testInvalidPortHasStableError(string $value): void
    {
        $this->set('DB_PORT', $value);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('DB_PORT must be an integer between 1 and 65535');
        Database::configuredConnectionSettings();
    }

    public static function invalidPortProvider(): array
    {
        return [
            'zero' => ['0'], 'negative' => ['-1'], 'too high' => ['65536'],
            'nonnumeric' => ['not-a-number'], 'decimal' => ['3306.5'],
        ];
    }

    #[DataProvider('missingRequiredProvider')]
    public function testMissingOrEmptyRequiredSettingIsNotExplicit(string $primary, string $alias, ?string $value): void
    {
        $this->set($primary, $value);
        $this->set($alias, null);
        self::assertFalse(Database::hasExplicitConfig());
    }

    public static function missingRequiredProvider(): array
    {
        return [
            'missing host' => ['DB_HOST', 'VITE_DB_HOST', null],
            'empty host' => ['DB_HOST', 'VITE_DB_HOST', ''],
            'missing name' => ['DB_NAME', 'VITE_DB_NAME', null],
            'empty name' => ['DB_NAME', 'VITE_DB_NAME', ''],
            'missing user' => ['DB_USER', 'VITE_DB_USER', null],
            'empty user' => ['DB_USER', 'VITE_DB_USER', ''],
        ];
    }

    #[DataProvider('passwordProvider')]
    public function testPasswordIsOptionalAndPreserved(?string $password, string $expected): void
    {
        $this->set('DB_PASS', $password);
        self::assertTrue(Database::hasExplicitConfig());
        self::assertSame($expected, Database::configuredConnectionSettings()['pass']);
        self::assertSame($expected, Database::createConfiguredConnection()->getConfig('password'));
    }

    public static function passwordProvider(): array
    {
        return ['missing' => [null, ''], 'empty' => ['', ''], 'nonempty' => ['test-secret', 'test-secret']];
    }

    public function testConfiguredConnectionUsesExpectedSafeDefaultsAndSelection(): void
    {
        $connection = Database::createConfiguredConnection();
        self::assertSame('mysql', $connection->getConfig('driver'));
        self::assertSame('db.test.invalid', $connection->getConfig('host'));
        self::assertSame(3306, $connection->getConfig('port'));
        self::assertSame('freetv_test', $connection->getConfig('database'));
        self::assertSame('synthetic_user', $connection->getConfig('username'));
        self::assertSame('utf8mb4', $connection->getConfig('charset'));
        self::assertSame('utf8mb4_unicode_ci', $connection->getConfig('collation'));
    }

    public function testLegacyAliasesTakePrecedenceAndAllowMissingPassword(): void
    {
        $this->set('VITE_DB_HOST', 'legacy.test.invalid');
        $this->set('VITE_DB_NAME', 'legacy_database');
        $this->set('VITE_DB_USER', 'legacy_user');
        $this->set('VITE_DB_PASS', null);
        self::assertTrue(Database::hasExplicitConfig());
        $settings = Database::configuredConnectionSettings();
        self::assertSame('legacy.test.invalid', $settings['host']);
        self::assertSame('legacy_database', $settings['database']);
        self::assertSame('legacy_user', $settings['user']);
        self::assertSame('synthetic_password', $settings['pass']);
    }

    public function testExplicitEmptyLegacyPasswordOverridesDbPassword(): void
    {
        $this->set('VITE_DB_PASS', '');
        self::assertSame('', Database::configuredConnectionSettings()['pass']);
    }

    public function testReadinessConnectionUsesOnlyStrictSafeGeneratedName(): void
    {
        $name = 'freetv_readiness_abcdef012345';
        self::assertSame($name, Database::createReadinessConnection($name)->getConfig('database'));
        foreach (['users', 'freetv_readiness_short', '../freetv_readiness_abcdef012345'] as $unsafe) {
            try {
                Database::createReadinessConnection($unsafe);
                self::fail('Unsafe readiness database name accepted');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Unsafe readiness database name', $exception->getMessage());
            }
        }
    }

    private function set(string $name, ?string $value): void
    {
        if ($value === null) {
            putenv($name);
            unset($_ENV[$name]);
            return;
        }
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}
