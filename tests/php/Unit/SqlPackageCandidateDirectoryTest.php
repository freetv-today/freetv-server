<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/lib/SqlPackageGenerator.php';
require_once __DIR__ . '/../../../tools/lib/SqlPackageCandidateDirectory.php';
require_once __DIR__ . '/../../../public/api/admin/DatasetPackage.php';

use FreeTV\Admin\DatasetPackage;
use FreeTV\Tools\SqlPackageCandidateDirectory;
use FreeTV\Tools\SqlPackageGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SqlPackageCandidateDirectoryTest extends TestCase
{
    private string $root;
    private string $canonical;
    private string $run;
    private string $candidate;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/freetv-permanent-candidate-' . bin2hex(random_bytes(8));
        $this->canonical = $this->root . '/server/sql';
        $this->run = $this->root . '/staging/data-validation-abcdef123456';
        $this->candidate = $this->run . '/sql';
        mkdir($this->canonical, 0700, true);
        mkdir($this->candidate, 0700, true);
        file_put_contents($this->run . '/' . SqlPackageCandidateDirectory::MARKER_FILENAME, SqlPackageCandidateDirectory::MARKER_CONTENT);
    }

    protected function tearDown(): void { DatasetPackage::removeTree($this->root); }

    public function testMarkedEmptyCandidateIsAcceptedForWrite(): void
    {
        self::assertSame(realpath($this->candidate), SqlPackageCandidateDirectory::resolveForWrite($this->candidate, $this->canonical));
    }

    public function testExactGeneratedSetIsAcceptedForRead(): void
    {
        $this->writeExpected();
        self::assertSame(realpath($this->candidate), SqlPackageCandidateDirectory::resolveForRead($this->candidate, $this->canonical, array_values(SqlPackageGenerator::FILES)));
    }

    #[DataProvider('invalidSimplePathProvider')]
    public function testInvalidAndNonexistentPathsAreRejected(string $path): void
    {
        $this->expectException(RuntimeException::class);
        SqlPackageCandidateDirectory::resolveForWrite($path === '@canonical' ? $this->canonical : $path, $this->canonical);
    }

    public static function invalidSimplePathProvider(): array
    {
        return ['empty' => [''], 'whitespace' => ['  '], 'NUL' => ["bad\0path"], 'nonexistent' => ['/tmp/freetv-no-such-candidate'], 'canonical' => ['@canonical']];
    }

    #[DataProvider('badRunNameProvider')]
    public function testMalformedValidationRunNamesAreRejected(string $name): void
    {
        $candidate = $this->root . '/staging/' . $name . '/sql';
        mkdir($candidate, 0700, true);
        file_put_contents(dirname($candidate) . '/' . SqlPackageCandidateDirectory::MARKER_FILENAME, SqlPackageCandidateDirectory::MARKER_CONTENT);
        $this->expectException(RuntimeException::class);
        SqlPackageCandidateDirectory::resolveForWrite($candidate, $this->canonical);
    }

    public static function badRunNameProvider(): array { return ['short' => ['data-validation-abc'], 'uppercase' => ['data-validation-ABCDEF123456'], 'wrong prefix' => ['validation-abcdef123456']]; }

    #[DataProvider('badMarkerProvider')]
    public function testMissingInvalidAndSymlinkedMarkersAreRejected(string $type): void
    {
        $marker = $this->run . '/' . SqlPackageCandidateDirectory::MARKER_FILENAME;
        unlink($marker);
        if ($type === 'invalid') { file_put_contents($marker, 'wrong'); }
        if ($type === 'symlink') { $target = $this->root . '/marker-target'; file_put_contents($target, SqlPackageCandidateDirectory::MARKER_CONTENT); symlink($target, $marker); }
        $this->expectException(RuntimeException::class);
        SqlPackageCandidateDirectory::resolveForWrite($this->candidate, $this->canonical);
    }

    public static function badMarkerProvider(): array { return ['missing' => ['missing'], 'invalid' => ['invalid'], 'symlink' => ['symlink']]; }

    public function testNonemptyCandidateIsRejectedForGenerationWithoutCanonicalChanges(): void
    {
        file_put_contents($this->candidate . '/existing.sql', 'data');
        $before = scandir($this->canonical);
        try { SqlPackageCandidateDirectory::resolveForWrite($this->candidate, $this->canonical); self::fail('Nonempty candidate accepted'); } catch (RuntimeException) {}
        self::assertSame($before, scandir($this->canonical));
    }

    public function testMissingAndExtraGeneratedFilesAreRejected(): void
    {
        $this->writeExpected();
        unlink($this->candidate . '/' . array_values(SqlPackageGenerator::FILES)[0]);
        try { SqlPackageCandidateDirectory::resolveForRead($this->candidate, $this->canonical, array_values(SqlPackageGenerator::FILES)); self::fail('Missing file accepted'); } catch (RuntimeException) {}
        file_put_contents($this->candidate . '/' . array_values(SqlPackageGenerator::FILES)[0], '-- fixture');
        file_put_contents($this->candidate . '/extra.sql', '-- extra');
        $this->expectException(RuntimeException::class);
        SqlPackageCandidateDirectory::resolveForRead($this->candidate, $this->canonical, array_values(SqlPackageGenerator::FILES));
    }

    public function testSymlinkFileAndDirectoryEntriesAreRejected(): void
    {
        $this->writeExpected();
        $filename = array_values(SqlPackageGenerator::FILES)[0];
        unlink($this->candidate . '/' . $filename);
        $target = $this->root . '/outside.sql'; file_put_contents($target, '-- outside');
        symlink($target, $this->candidate . '/' . $filename);
        $this->expectException(RuntimeException::class);
        SqlPackageCandidateDirectory::resolveForRead($this->candidate, $this->canonical, array_values(SqlPackageGenerator::FILES));
    }

    public function testNonFileExpectedEntryIsRejected(): void
    {
        $this->writeExpected();
        $filename = array_values(SqlPackageGenerator::FILES)[0];
        unlink($this->candidate . '/' . $filename);
        mkdir($this->candidate . '/' . $filename);
        $this->expectException(RuntimeException::class);
        SqlPackageCandidateDirectory::resolveForRead($this->candidate, $this->canonical, array_values(SqlPackageGenerator::FILES));
    }

    #[DataProvider('symlinkBoundaryProvider')]
    public function testSymlinkCandidateOrParentIsRejected(string $type): void
    {
        if ($type === 'candidate') {
            $target = $this->root . '/real-sql'; mkdir($target); DatasetPackage::removeTree($this->candidate); symlink($target, $this->candidate);
        } else {
            $target = $this->root . '/real-run'; mkdir($target . '/sql', 0700, true); file_put_contents($target . '/' . SqlPackageCandidateDirectory::MARKER_FILENAME, SqlPackageCandidateDirectory::MARKER_CONTENT); DatasetPackage::removeTree($this->run); symlink($target, $this->run);
        }
        $this->expectException(RuntimeException::class);
        SqlPackageCandidateDirectory::resolveForWrite($this->candidate, $this->canonical);
    }

    public static function symlinkBoundaryProvider(): array { return ['candidate' => ['candidate'], 'parent' => ['parent']]; }

    private function writeExpected(): void { foreach (SqlPackageGenerator::FILES as $filename) { file_put_contents($this->candidate . '/' . $filename, '-- fixture'); } }
}
