<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/Session.php';

use function FreeTV\Admin\destroyAdminSession;

function adminSessionAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true));
    }
}

$sessionPath = sys_get_temp_dir() . '/freetv-admin-session-' . bin2hex(random_bytes(8));
if (!mkdir($sessionPath) && !is_dir($sessionPath)) {
    throw new RuntimeException('Could not create the session test directory');
}

session_save_path($sessionPath);
session_name('FREETV_TEST_SESSION');
session_id('existing-authenticated-session');
session_start();
$_SESSION['admin'] = ['id' => 1, 'username' => 'admin', 'role' => 'admin'];

destroyAdminSession();

adminSessionAssertSame(PHP_SESSION_NONE, session_status(),
    'Successful initialization must destroy the existing PHP session');
adminSessionAssertSame(false, file_exists($sessionPath . '/sess-existing-authenticated-session'),
    'Destroyed session data must not remain on disk');

session_id('new-session-after-initialization');
session_start();
adminSessionAssertSame(false, isset($_SESSION['admin']),
    'Successful initialization must not create a new authenticated session');
session_write_close();

session_id('failed-initialization-session');
session_start();
$_SESSION['admin'] = ['id' => 1, 'username' => 'admin', 'role' => 'admin'];
adminSessionAssertSame('admin', $_SESSION['admin']['role'],
    'Initialization failure must preserve the existing authentication session');
session_write_close();

fwrite(STDOUT, "AdminSessionTest passed\n");