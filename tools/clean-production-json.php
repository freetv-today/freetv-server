#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/ProductionJsonCleaner.php';

use FreeTV\Tools\ProductionJsonCleaner;

function cleanUsage(): never
{
    fwrite(STDERR, "Usage: php tools/clean-production-json.php DATA_DIR [--write] [--output=/absolute/child/path]\n");
    exit(2);
}

$directory = null;
$write = false;
$output = null;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--write') {
        $write = true;
    } elseif (str_starts_with($argument, '--output=')) {
        $output = substr($argument, strlen('--output='));
    } elseif (!str_starts_with($argument, '--') && $directory === null) {
        $directory = $argument;
    } else {
        cleanUsage();
    }
}
if ($directory === null || ($output !== null && !$write)) {
    cleanUsage();
}

try {
    $cleaner = new ProductionJsonCleaner();
    $validated = $cleaner->validate($directory);
    $totalOriginal = $totalRemoved = $totalResulting = 0;
    foreach ($validated['report'] as $filename => $row) {
        printf("%s: %d -> %d (remove %d)\n", $filename, $row['original'], $row['resulting'], $row['removed']);
        printf("  identifiers: %s\n", $row['identifiers'] === [] ? '(none)' : implode(', ', $row['identifiers']));
        $totalOriginal += $row['original'];
        $totalRemoved += $row['removed'];
        $totalResulting += $row['resulting'];
    }
    printf("Total: %d -> %d (remove %d)\n", $totalOriginal, $totalResulting, $totalRemoved);

    if (!$write) {
        fwrite(STDOUT, "Dry run only; no files were written.\n");
        exit(0);
    }
    $written = $cleaner->write($directory, $output);
    fwrite(STDOUT, "Cleaned files written atomically to {$written}\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "ERROR: {$exception->getMessage()}\n");
    exit(1);
}
