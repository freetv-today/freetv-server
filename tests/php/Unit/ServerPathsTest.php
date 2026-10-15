<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/ServerPaths.php';
require_once __DIR__ . '/../../../public/api/admin/Database.php';
require_once __DIR__ . '/../../../public/api/admin/publication/PublicationUndoService.php';

use FreeTV\Admin\Publication\PublicationUndoService;
use FreeTV\Admin\ServerPaths;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServerPathsTest extends TestCase
{
    private string $root;
    private string|false $previousEnvironment;
    private bool $hadEnvironmentArray;
    private mixed $previousEnvironmentArray;

    protected function setUp(): void
    {
        $this->previousEnvironment = getenv('FREETV_PUBLIC_PATH');
        $this->hadEnvironmentArray = array_key_exists('FREETV_PUBLIC_PATH', $_ENV);
        $this->previousEnvironmentArray = $_ENV['FREETV_PUBLIC_PATH'] ?? null;
        $this->clearEnvironment();
        $this->root = sys_get_temp_dir() . '/freetv-server-paths-' . bin2hex(random_bytes(8));
        if (!mkdir($this->root, 0700)) { self::fail('Could not create path fixture'); }
    }

    protected function tearDown(): void
    {
        if ($this->previousEnvironment === false) {
            putenv('FREETV_PUBLIC_PATH');
        } else {
            putenv('FREETV_PUBLIC_PATH=' . $this->previousEnvironment);
        }
        if ($this->hadEnvironmentArray) {
            $_ENV['FREETV_PUBLIC_PATH'] = $this->previousEnvironmentArray;
        } else {
            unset($_ENV['FREETV_PUBLIC_PATH']);
        }
        $this->removeTree($this->root);
    }

    public function testDefaultLayoutStaysInsideApplicationRoot(): void
    {
        $paths = new ServerPaths($this->root);
        self::assertSame($this->root, $paths->appRoot());
        self::assertSame($this->root . '/public', $paths->publicRoot());
        self::assertSame($this->root . '/temp', $paths->tempRoot());
        self::assertStringStartsWith($paths->appRoot() . '/', $paths->publicRoot());
        self::assertStringStartsWith($paths->appRoot() . '/', $paths->tempRoot());
    }

    #[DataProvider('safePathProvider')]
    public function testAllowedRelativePublicPathsAreNormalizedWithinApplication(string $input, string $expected): void
    {
        $paths = new ServerPaths($this->root, $input);
        self::assertSame($this->root . '/' . $expected, $paths->publicRoot());
        self::assertStringStartsWith($this->root . '/', $paths->publicRoot());
    }

    public static function safePathProvider(): array
    {
        return [
            'public' => ['public', 'public'], 'public html' => ['public_html', 'public_html'],
            'www' => ['www', 'www'], 'html' => ['html', 'html'], 'htdocs' => ['htdocs', 'htdocs'],
            'nested' => ['web/public', 'web/public'], 'trailing slash' => ['web/public/', 'web/public'],
        ];
    }

    #[DataProvider('unsafePathProvider')]
    public function testUnsafePathsAreRejectedWithoutFilesystemCreation(string $input): void
    {
        $before = $this->treeEntries();
        $this->expectException(InvalidArgumentException::class);
        try {
            new ServerPaths($this->root, $input);
        } finally {
            self::assertSame($before, $this->treeEntries());
        }
    }

    public static function unsafePathProvider(): array
    {
        return [
            'empty' => [''], 'whitespace' => ['   '], 'parent' => ['..'],
            'parent prefix' => ['../public'], 'nested traversal' => ['web/../public'],
            'unix absolute' => ['/var/www'], 'windows absolute' => ['C:\\public'],
            'doubled separator' => ['web//public'], 'NUL' => ["public\0evil"],
            'dot segment' => ['web/./public'], 'backslash traversal' => ['web\\..\\public'],
            'punctuation' => ['web/public!'], 'leading slash' => ['\\public'],
        ];
    }

    public function testEnvironmentSelectsValidRelativePublicPath(): void
    {
        $this->setEnvironment('web/public/');
        self::assertSame($this->root . '/web/public', (new ServerPaths($this->root))->publicRoot());
    }

    public function testInvalidEnvironmentPathIsRejected(): void
    {
        $this->setEnvironment('../outside');
        $this->expectException(InvalidArgumentException::class);
        new ServerPaths($this->root);
    }

    public function testExplicitConstructorPathOverridesEnvironment(): void
    {
        $this->setEnvironment('../outside');
        self::assertSame(
            $this->root . '/explicit/public',
            (new ServerPaths($this->root, 'explicit/public'))->publicRoot()
        );
    }

    public function testUndoStateUsesPrivateTempRootWithAlternatePublicPath(): void
    {
        $this->setEnvironment('public_html');
        $paths = new ServerPaths($this->root);
        $undo = new PublicationUndoService(null, null, static function (): void {}, $paths);
        $undo->withLock(static function (): void {});

        self::assertFileExists($this->root . '/temp/publication-undo/.lock');
        self::assertDirectoryDoesNotExist($this->root . '/public_html/publication-undo');
        self::assertFileDoesNotExist($this->root . '/public_html/.lock');
        self::assertStringStartsWith($this->root . '/', $paths->publicRoot());
        self::assertStringStartsWith($this->root . '/', $paths->tempRoot());
    }

    private function setEnvironment(string $value): void
    {
        putenv('FREETV_PUBLIC_PATH=' . $value);
        $_ENV['FREETV_PUBLIC_PATH'] = $value;
    }
    private function clearEnvironment(): void
    {
        putenv('FREETV_PUBLIC_PATH');
        unset($_ENV['FREETV_PUBLIC_PATH']);
    }
    private function treeEntries(): array
    {
        $entries = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) { $entries[] = $item->getPathname(); }
        sort($entries);
        return $entries;
    }
    private function removeTree(string $root): void
    {
        if (!is_dir($root)) { return; }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
        rmdir($root);
    }
}
