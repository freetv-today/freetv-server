<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/DatasetPackage.php';

use FreeTV\Admin\DatasetPackage;
use PHPUnit\Framework\TestCase;

final class AdminSessionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/freetv-admin-session-phpunit-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->root, 0700, true));
    }

    protected function tearDown(): void
    {
        DatasetPackage::removeTree($this->root);
    }

    public function testDestroyClearsAuthenticatedSessionAndNewSessionDoesNotInheritIdentity(): void
    {
        $sessionPath = $this->root . '/sessions';
        mkdir($sessionPath, 0700);
        $sessionFile = $sessionPath . '/sess_existingauthenticated';
        $sessionSource = realpath(dirname(__DIR__, 3) . '/public/api/admin/Session.php');
        $script = '<?php declare(strict_types=1);'
            . 'ini_set("session.use_cookies", "0");'
            . 'ini_set("session.use_strict_mode", "0");'
            . 'session_save_path(' . var_export($sessionPath, true) . ');'
            . 'session_name("FREETV_TEST_SESSION");'
            . 'session_id("existingauthenticated");session_start();'
            . '$_SESSION["admin"]=["id"=>7,"username"=>"fixture_admin","role"=>"admin"];'
            . 'session_write_close();session_id("existingauthenticated");session_start();'
            . 'require_once ' . var_export($sessionSource, true) . ';'
            . '\FreeTV\Admin\destroyAdminSession();'
            . '$after=["status"=>session_status(),"admin_present"=>isset($_SESSION["admin"]),'
            . '"old_file_exists"=>file_exists(' . var_export($sessionFile, true) . '),'
            . '"session_name"=>session_name()];'
            . 'session_id("newsessionafterinit");session_start();'
            . '$after["new_admin_present"]=isset($_SESSION["admin"]);session_write_close();'
            . 'echo json_encode($after, JSON_THROW_ON_ERROR);';

        $result = $this->runPhp($script);

        self::assertSame([
            'status' => PHP_SESSION_NONE,
            'admin_present' => false,
            'old_file_exists' => false,
            'session_name' => 'FREETV_TEST_SESSION',
            'new_admin_present' => false,
        ], $result);
    }

    public function testInitializationFailureContractLeavesExistingAuthenticationIntact(): void
    {
        $sessionPath = $this->root . '/failure-sessions';
        mkdir($sessionPath, 0700);
        $script = '<?php declare(strict_types=1);'
            . 'ini_set("session.use_cookies", "0");session_save_path(' . var_export($sessionPath, true) . ');'
            . 'session_name("FREETV_FAILURE_SESSION");session_id("failedinitialization");session_start();'
            . '$_SESSION["admin"]=["id"=>8,"username"=>"still_admin","role"=>"admin"];'
            . 'session_write_close();session_id("failedinitialization");session_start();'
            . 'echo json_encode(["status"=>session_status(),"admin"=>$_SESSION["admin"]], JSON_THROW_ON_ERROR);'
            . 'session_write_close();';

        $result = $this->runPhp($script);

        self::assertSame(PHP_SESSION_ACTIVE, $result['status']);
        self::assertSame(['id' => 8, 'username' => 'still_admin', 'role' => 'admin'], $result['admin']);
    }

    public function testConfiguredCookieSecurityParametersRemainObservableInCli(): void
    {
        $sessionPath = $this->root . '/cookie-sessions';
        mkdir($sessionPath, 0700);
        $script = '<?php declare(strict_types=1);'
            . 'ini_set("session.use_cookies", "1");ini_set("session.cookie_secure", "1");'
            . 'ini_set("session.cookie_httponly", "1");ini_set("session.cookie_samesite", "Strict");'
            . 'session_save_path(' . var_export($sessionPath, true) . ');session_name("FREETV_SECURE_SESSION");'
            . 'echo json_encode(["name"=>session_name(),"params"=>session_get_cookie_params(),'
            . '"sapi"=>PHP_SAPI,"headers"=>headers_list()], JSON_THROW_ON_ERROR);';

        $result = $this->runPhp($script);

        self::assertSame('FREETV_SECURE_SESSION', $result['name']);
        self::assertTrue($result['params']['secure']);
        self::assertTrue($result['params']['httponly']);
        self::assertSame('Strict', $result['params']['samesite']);
        self::assertSame('cli', $result['sapi']);
        self::assertSame([], $result['headers']);
    }

    private function runPhp(string $source): array
    {
        $path = $this->root . '/scenario-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($path, $source);
        $command = [PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'log_errors=0', $path];
        $pipes = [];
        $process = proc_open($command, [
            0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
        ], $pipes, $this->root);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, 'Subprocess failed: ' . $stderr);
        self::assertSame('', $stderr);
        self::assertNotSame('', $stdout);
        return json_decode($stdout, true, 32, JSON_THROW_ON_ERROR);
    }
}
