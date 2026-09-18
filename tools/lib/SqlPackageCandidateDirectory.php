<?php

declare(strict_types=1);

namespace FreeTV\Tools;

use RuntimeException;

final class SqlPackageCandidateDirectory
{
    public const MARKER_FILENAME = '.freetv-sql-candidate';
    public const MARKER_CONTENT = "freetv-tooling data:validate\n";

    public static function resolveForWrite(string $directory, string $canonicalDirectory): string
    {
        $resolved = self::resolve($directory, $canonicalDirectory);
        $entries = array_values(array_diff(scandir($resolved) ?: [], ['.', '..']));
        if ($entries !== []) {
            throw new RuntimeException('Temporary SQL candidate directory must be empty before generation');
        }
        return $resolved;
    }

    /** @param list<string> $expectedFiles */
    public static function resolveForRead(string $directory, string $canonicalDirectory, array $expectedFiles): string
    {
        $resolved = self::resolve($directory, $canonicalDirectory);
        $actual = array_values(array_diff(scandir($resolved) ?: [], ['.', '..']));
        sort($actual);
        $expected = $expectedFiles;
        sort($expected);
        if ($actual !== $expected) {
            throw new RuntimeException('Temporary SQL candidate directory must contain exactly the six generated packages');
        }
        foreach ($actual as $filename) {
            $path = $resolved . DIRECTORY_SEPARATOR . $filename;
            if (is_link($path) || !is_file($path)) {
                throw new RuntimeException("Temporary SQL candidate contains an unsafe entry: {$filename}");
            }
        }
        return $resolved;
    }

    private static function resolve(string $directory, string $canonicalDirectory): string
    {
        if (trim($directory) === '' || str_contains($directory, "\0")) {
            throw new RuntimeException('Temporary SQL candidate directory must be explicitly supplied');
        }
        $resolved = realpath($directory);
        $canonical = realpath($canonicalDirectory);
        if ($resolved === false || !is_dir($resolved) || basename($resolved) !== 'sql') {
            throw new RuntimeException('Temporary SQL candidate must be an existing directory named sql');
        }
        if ($canonical === false || $resolved === $canonical) {
            throw new RuntimeException('Temporary SQL candidate must not be the canonical Server sql directory');
        }
        if (is_link($directory) || is_link(dirname($directory))) {
            throw new RuntimeException('Temporary SQL candidate directory and its parent must not be symbolic links');
        }
        $parent = dirname($resolved);
        if (!preg_match('/^data-validation-[a-f0-9]{12}$/', basename($parent))) {
            throw new RuntimeException('Temporary SQL candidate parent has an unsafe validation-run name');
        }
        $marker = $parent . DIRECTORY_SEPARATOR . self::MARKER_FILENAME;
        if (!is_file($marker) || is_link($marker) || file_get_contents($marker) !== self::MARKER_CONTENT) {
            throw new RuntimeException('Temporary SQL candidate is missing its Tooling validation marker');
        }
        return $resolved;
    }
}

