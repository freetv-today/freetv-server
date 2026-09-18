<?php

declare(strict_types=1);

require_once __DIR__ . '/../tools/lib/SqlPackageGenerator.php';
require_once __DIR__ . '/../tools/lib/SqlPackageCandidateDirectory.php';

use FreeTV\Tools\SqlPackageCandidateDirectory;
use FreeTV\Tools\SqlPackageGenerator;

function candidateAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function candidateRejects(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (RuntimeException) {
        return;
    }
    throw new RuntimeException($message);
}

$root = sys_get_temp_dir() . '/freetv-sql-candidate-test-' . bin2hex(random_bytes(5));
$canonical = $root . '/server/sql';
$validationRoot = $root . '/staging/data-validation-abcdef123456';
$candidate = $validationRoot . '/sql';
$unmarkedRoot = $root . '/staging/data-validation-123456abcdef';
$unmarked = $unmarkedRoot . '/sql';
mkdir($canonical, 0755, true);
mkdir($candidate, 0755, true);
mkdir($unmarked, 0755, true);
file_put_contents(
    $validationRoot . '/' . SqlPackageCandidateDirectory::MARKER_FILENAME,
    SqlPackageCandidateDirectory::MARKER_CONTENT
);

try {
    candidateAssert(
        SqlPackageCandidateDirectory::resolveForWrite($candidate, $canonical) === realpath($candidate),
        'Marked candidate was not accepted for generation'
    );
    candidateRejects(
        fn(): string => SqlPackageCandidateDirectory::resolveForWrite($canonical, $canonical),
        'Canonical SQL directory was accepted as a candidate'
    );
    candidateRejects(
        fn(): string => SqlPackageCandidateDirectory::resolveForWrite($unmarked, $canonical),
        'Unmarked SQL directory was accepted as a candidate'
    );

    foreach (SqlPackageGenerator::FILES as $filename) {
        file_put_contents($candidate . '/' . $filename, '-- fixture');
    }
    candidateAssert(
        SqlPackageCandidateDirectory::resolveForRead(
            $candidate,
            $canonical,
            array_values(SqlPackageGenerator::FILES)
        ) === realpath($candidate),
        'Complete candidate was not accepted for validation'
    );
    file_put_contents($candidate . '/unexpected.sql', '-- unexpected');
    candidateRejects(
        fn(): string => SqlPackageCandidateDirectory::resolveForRead(
            $candidate,
            $canonical,
            array_values(SqlPackageGenerator::FILES)
        ),
        'Candidate with an unexpected file was accepted'
    );
} finally {
    $remove = static function (string $directory) use (&$remove): void {
        if (!is_dir($directory)) {
            return;
        }
        foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $entry) {
            $path = $directory . '/' . $entry;
            is_dir($path) ? $remove($path) : unlink($path);
        }
        rmdir($directory);
    };
    $remove($root);
}

fwrite(STDOUT, "SqlPackageCandidateDirectoryTest passed\n");
