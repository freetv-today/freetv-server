<?php

require_once __DIR__ . '/../public/api/admin/Settings.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationException.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationTimestamp.php';
require_once __DIR__ . '/../public/api/admin/publication/ConfigPublicationSerializer.php';
require_once __DIR__ . '/../public/api/admin/publication/ConfigPublicationService.php';

use FreeTV\Admin\Publication\ConfigPublicationService;
use FreeTV\Admin\Publication\PublicationException;

function assertConfigServiceSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

$testRoot = sys_get_temp_dir() . '/freetv-config-publication-test-' . bin2hex(random_bytes(8));
if (!mkdir($testRoot, 0700, true)) {
    throw new RuntimeException('Could not create config publication test directory');
}

try {
    $service = new ConfigPublicationService(
        $testRoot,
        static fn() => ['show_ads' => true, 'showads' => false],
        static fn() => new DateTimeImmutable('2026-08-12T19:30:00.876Z')
    );
    $result = $service->publish();
    $configPath = $testRoot . '/config.json';
    $artifact = json_decode(file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);

    assertConfigServiceSame(true, is_file($configPath), 'Config artifact was not created');
    assertConfigServiceSame(
        ['lastupdated' => '2026-08-12T19:30:00.000Z', 'show_ads' => true],
        $artifact,
        'Published config artifact does not match the Viewer contract'
    );
    assertConfigServiceSame(
        ['lastupdated' => '2026-08-12T19:30:00.000Z'],
        $result,
        'Config publication result did not contain its publication timestamp'
    );
    assertConfigServiceSame(
        0644,
        fileperms($configPath) & 0777,
        'Published config permissions do not allow static serving'
    );

    $invalidRoot = $testRoot . '/not-a-directory';
    file_put_contents($invalidRoot, 'fixture');
    try {
        (new ConfigPublicationService(
            $invalidRoot,
            static fn() => ['show_ads' => false],
            static fn() => new DateTimeImmutable('2026-08-12T20:00:00Z')
        ))->publish();
        throw new RuntimeException('Invalid publication root did not fail');
    } catch (PublicationException $exception) {
        assertConfigServiceSame(
            'Config publication root does not exist',
            $exception->getMessage(),
            'Config write failure was not clear'
        );
    }
} finally {
    foreach (glob($testRoot . '/*') ?: [] as $path) {
        unlink($path);
    }
    rmdir($testRoot);
}

echo "ConfigPublicationService tests passed\n";
