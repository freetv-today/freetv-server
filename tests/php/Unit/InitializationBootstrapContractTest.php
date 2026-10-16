<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InitializationBootstrapContractTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/public/api/admin/initialize.php');
        self::assertNotFalse($source);
        $this->source = $source;
    }

    public function testUsesCanonicalTablesOnlySchemaAndNeverCreateDatabasePackage(): void
    {
        self::assertSame(2, substr_count(
            $this->source,
            "'/sql/freetv_mariadb_schema-tables-only.sql'"
        ));
        self::assertStringNotContainsString('freetv_mariadb_schema-create-db.sql', $this->source);
    }

    public function testAcquiresAdvisoryLockBeforeSchemaBootstrapAndReleasesItInFinally(): void
    {
        $getLock = $this->position("selectOne('SELECT GET_LOCK(?, 10) AS acquired'");
        $schema = $this->position('new SchemaBootstrapper(');
        $finally = $this->position('} finally {', $schema);
        $release = $this->position("selectOne('SELECT RELEASE_LOCK(?) AS released'", $finally);
        self::assertLessThan($schema, $getLock);
        self::assertLessThan($finally, $schema);
        self::assertGreaterThan($finally, $release);
        self::assertStringContainsString('if ($lockAcquired && $bootstrapConnection !== null)', $this->source);
    }

    public function testValidatesFourExplicitModesAndDelegatesWithoutFallback(): void
    {
        self::assertStringContainsString(
            "!in_array(\$mode, ['fresh', 'baseline', 'sample', 'official'], true)",
            $this->source
        );
        self::assertStringContainsString(
            "initializeRespond(400, ['success' => false, 'message' => 'Invalid initialization mode'])",
            $this->source
        );
        foreach (['fresh', 'baseline', 'sample', 'official'] as $mode) {
            self::assertSame(1, substr_count(
                $this->source,
                "'{$mode}' => \$bootstrapper->{$mode}(\$username, \$password)"
            ));
        }
        $matchStart = $this->position('$result = match ($mode) {');
        $matchEnd = $this->position('};', $matchStart);
        self::assertStringNotContainsString('default =>', substr($this->source, $matchStart, $matchEnd - $matchStart));
    }

    public function testFailureAndAlreadyInitializedPathsPrecedeSoleSessionDestruction(): void
    {
        $mapping = $this->position('InitializationErrorResponse::forException($e)');
        $already = $this->position('if ($result === Bootstrapper::ALREADY_INITIALIZED)');
        $destroy = $this->position('destroyAdminSession()');
        self::assertLessThan($already, $mapping);
        self::assertLessThan($destroy, $already);
        self::assertSame(1, substr_count($this->source, 'destroyAdminSession()'));
    }

    public function testReturnsExactAlreadyInitializedAndSuccessfulResponses(): void
    {
        self::assertStringContainsString(
            "initializeRespond(409, ['success' => false, 'message' => 'FreeTV has already been initialized'])",
            $this->source
        );
        $destroy = $this->position('destroyAdminSession()');
        $success = $this->position(
            "initializeRespond(201, ['success' => true, 'message' => 'FreeTV library initialized'])",
            $destroy
        );
        self::assertGreaterThan($destroy, $success);
    }

    public function testCatchPathReturnsMappedStatusAndPayload(): void
    {
        $mapping = $this->position('$failure = \FreeTV\Admin\InitializationErrorResponse::forException($e);');
        $response = $this->position(
            "initializeRespond(\$failure['http_status'], \$failure['payload']);",
            $mapping
        );
        self::assertGreaterThan($mapping, $response);
        self::assertStringContainsString('catch (\Throwable $e)', $this->source);
    }

    public function testRequestValidationTerminatesBeforeAnyDatabaseBootstrap(): void
    {
        $method = $this->position("\$_SERVER['REQUEST_METHOD'] !== 'POST'");
        $json = $this->position('$input = initializeReadJsonObject();');
        $database = $this->position('Database::createBootstrapConnection()');
        self::assertLessThan($json, $method);
        self::assertLessThan($database, $json);
    }

    private function position(string $needle, int $offset = 0): int
    {
        $position = strpos($this->source, $needle, $offset);
        self::assertNotFalse($position, 'Missing initialization contract fragment: ' . $needle);
        return $position;
    }
}
