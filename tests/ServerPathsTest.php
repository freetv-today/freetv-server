<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/ServerPaths.php';
require_once __DIR__ . '/../public/api/admin/Database.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationException.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationTimestamp.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationUndoService.php';

use FreeTV\Admin\ServerPaths;
use FreeTV\Admin\Publication\PublicationUndoService;

function serverPathsAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true));
    }
}

function serverPathsFailure(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException($message);
}

$previousEnvironment = getenv('FREETV_PUBLIC_PATH');
$hadEnvironmentArray = array_key_exists('FREETV_PUBLIC_PATH', $_ENV);
$previousEnvironmentArray = $_ENV['FREETV_PUBLIC_PATH'] ?? null;
$appRoot = sys_get_temp_dir() . '/freetv-server-paths-' . bin2hex(random_bytes(6));
mkdir($appRoot);

try {
    putenv('FREETV_PUBLIC_PATH');
    unset($_ENV['FREETV_PUBLIC_PATH']);
    $default = new ServerPaths($appRoot);
    serverPathsAssertSame($appRoot, $default->appRoot(), 'Application root is incorrect');
    serverPathsAssertSame($appRoot . '/public', $default->publicRoot(), 'Unset path did not default to public');
    serverPathsAssertSame($appRoot . '/temp', $default->tempRoot(), 'Private temp root is incorrect');
    $sourceUndo = new PublicationUndoService(null, null, static function (): void {}, $default);
    $sourceUndo->withLock(static function (): void {});
    serverPathsAssertSame(true, is_file($appRoot . '/temp/publication-undo/.lock'),
        'Source-layout Undo root did not use app/temp/publication-undo');
    unlink($appRoot . '/temp/publication-undo/.lock');
    rmdir($appRoot . '/temp/publication-undo');
    rmdir($appRoot . '/temp');

    foreach (['public_html', 'www', 'html', 'htdocs'] as $relative) {
        putenv('FREETV_PUBLIC_PATH=' . $relative);
        serverPathsAssertSame(
            $appRoot . '/' . $relative,
            (new ServerPaths($appRoot))->publicRoot(),
            "{$relative} was not resolved under the application root"
        );
    }

    putenv('FREETV_PUBLIC_PATH=web/public/');
    serverPathsAssertSame(
        $appRoot . '/web/public',
        (new ServerPaths($appRoot))->publicRoot(),
        'Safe nested/trailing-slash path was not normalized'
    );

    foreach (['', ' ', '..', '../public', 'web/../public', '/var/www', 'C:\\public', 'web//public', "public\0evil"] as $unsafe) {
        serverPathsFailure(
            fn() => new ServerPaths($appRoot, $unsafe),
            "Unsafe public path was accepted: " . var_export($unsafe, true)
        );
    }

    $nested = new ServerPaths($appRoot, 'web/public');
    serverPathsAssertSame(true, str_starts_with($nested->publicRoot(), $nested->appRoot() . '/'),
        'Public root escaped the application boundary');
    serverPathsAssertSame(true, str_starts_with($nested->tempRoot(), $nested->appRoot() . '/'),
        'Temp root escaped the application boundary');
} finally {
    if ($previousEnvironment === false) {
        putenv('FREETV_PUBLIC_PATH');
    } else {
        putenv('FREETV_PUBLIC_PATH=' . $previousEnvironment);
    }
    if ($hadEnvironmentArray) {
        $_ENV['FREETV_PUBLIC_PATH'] = $previousEnvironmentArray;
    } else {
        unset($_ENV['FREETV_PUBLIC_PATH']);
    }
    rmdir($appRoot);
}

fwrite(STDOUT, "ServerPaths tests passed\n");
