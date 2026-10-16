<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/DataSnapshotManifest.php';
require_once __DIR__ . '/../../../public/api/admin/DataSnapshotStatusService.php';

use FreeTV\Admin\DataSnapshot\DataSnapshotManifest;
use FreeTV\Admin\DataSnapshot\DataSnapshotSourceException;
use FreeTV\Admin\DataSnapshot\DataSnapshotStatusService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PermanentSnapshotQuery
{
    public static array $boundaries = [];
    private array $conditions = [];
    public function __construct(private array $rows) {}
    public function where(string $column, string $operator, string $value): self { $this->conditions[] = [$column, $operator, $value]; self::$boundaries[] = $value; return $this; }
    public function count(): int { return count(array_filter($this->rows, function (array $row): bool { foreach ($this->conditions as [$column, $operator, $value]) { if ($operator === '>' && !($row[$column] > $value)) return false; if ($operator === '<=' && !($row[$column] <= $value)) return false; } return true; })); }
}

final class DataSnapshotStatusServiceTest extends TestCase
{
    public function testValidManifestIsCanonicalizedAndOptionalSampleCountIsOmitted(): void
    {
        $expected = $this->manifest();
        $parsed = DataSnapshotManifest::fromJson(json_encode($expected, JSON_THROW_ON_ERROR));
        unset($expected['counts']['sample_shows']);
        self::assertSame($expected, $parsed);
    }

    #[DataProvider('invalidManifestProvider')]
    public function testInvalidManifestsAreRejected(string $case): void
    {
        $manifest = $this->manifest();
        $json = match ($case) {
            'json' => '{bad',
            'version' => json_encode(array_replace($manifest, ['format_version' => 2]), JSON_THROW_ON_ERROR),
            'version type' => json_encode(array_replace($manifest, ['format_version' => '1']), JSON_THROW_ON_ERROR),
            'generated' => json_encode(array_replace($manifest, ['generated_at' => 'bad']), JSON_THROW_ON_ERROR),
            'snapshot missing' => json_encode(array_diff_key($manifest, ['reconciled_snapshot' => true]), JSON_THROW_ON_ERROR),
            'snapshot type' => json_encode(array_replace($manifest, ['reconciled_snapshot' => 'bad']), JSON_THROW_ON_ERROR),
            'name' => json_encode(array_replace_recursive($manifest, ['reconciled_snapshot' => ['name' => '']]), JSON_THROW_ON_ERROR),
            'captured' => json_encode(array_replace_recursive($manifest, ['reconciled_snapshot' => ['captured_at' => 'bad']]), JSON_THROW_ON_ERROR),
            'counts type' => json_encode(array_replace($manifest, ['counts' => 'bad']), JSON_THROW_ON_ERROR),
            'counts missing' => json_encode(array_replace($manifest, ['counts' => ['playlists' => 1]]), JSON_THROW_ON_ERROR),
            'negative' => json_encode(array_replace_recursive($manifest, ['counts' => ['shows' => -1]]), JSON_THROW_ON_ERROR),
        };
        $this->expectException(InvalidArgumentException::class);
        DataSnapshotManifest::fromJson($json);
    }
    public static function invalidManifestProvider(): array { return array_map(static fn(string $v) => [$v], ['json','version','version type','generated','snapshot missing','snapshot type','name','captured','counts type','counts missing','negative']); }

    public function testStatusUsesOneUtcDatabaseBoundaryAndClassifiesNewAndUpdatedOnly(): void
    {
        PermanentSnapshotQuery::$boundaries = [];
        $rows = ['playlists' => [['created_at'=>'2026-08-01 00:00:00','updated_at'=>'2026-08-01 00:00:00'],['created_at'=>'2026-08-29 12:04:46','updated_at'=>'2026-08-29 12:05:00'],['created_at'=>'2026-08-01 00:00:00','updated_at'=>'2026-08-29 12:04:46'],['created_at'=>'2026-08-30 00:00:00','updated_at'=>'2026-08-30 00:00:00']], 'playlist_shows' => [['created_at'=>'2026-08-29 12:04:45','updated_at'=>'2026-08-29 12:04:46'],['created_at'=>'2026-08-29 12:04:46','updated_at'=>'2026-08-29 12:04:47'],['created_at'=>'2026-08-01 00:00:00','updated_at'=>'2026-08-29 12:04:45']]];
        $service = new DataSnapshotStatusService(fn(): string => json_encode($this->manifest(), JSON_THROW_ON_ERROR), static fn(string $table) => new PermanentSnapshotQuery($rows[$table]));
        $status = $service->status();
        self::assertSame(['playlists'=>4,'shows'=>3], $status['production']['counts']);
        self::assertSame(['new'=>2,'updated'=>1], $status['changes_since_snapshot']['playlists']);
        self::assertSame(['new'=>1,'updated'=>1], $status['changes_since_snapshot']['shows']);
        self::assertArrayNotHasKey('deleted', $status['changes_since_snapshot']['shows']);
        self::assertSame(['2026-08-29 12:04:45'], array_values(array_unique(PermanentSnapshotQuery::$boundaries)));
    }

    public function testNonStringFetcherResponseIsSourceFailure(): void
    {
        $this->expectException(DataSnapshotSourceException::class);
        (new DataSnapshotStatusService(static fn() => null, static fn() => null))->status();
    }

    #[DataProvider('endpointProvider')]
    public function testStatusEndpointRejectsUnsafeRequests(string $method, ?array $user, array $expected): void
    {
        [$body] = $this->endpoint($method, $user);
        self::assertSame($expected, json_decode($body, true, 512, JSON_THROW_ON_ERROR));
    }
    public static function endpointProvider(): array { return ['unauthenticated'=>['GET',null,['success'=>false,'message'=>'Unauthorized']], 'viewer'=>['GET',['id'=>1,'username'=>'v','role'=>'viewer'],['success'=>false,'message'=>'Forbidden']], 'admin POST'=>['POST',['id'=>2,'username'=>'a','role'=>'admin'],['success'=>false,'message'=>'Method not allowed']]]; }

    private function manifest(): array { return ['format_version'=>1,'generated_at'=>'2026-08-31T01:05:06.169Z','reconciled_snapshot'=>['name'=>'freetv-content-snapshot-20260829T120445Z','captured_at'=>'2026-08-29T12:04:45.000Z'],'counts'=>['playlists'=>4,'shows'=>308,'sample_shows'=>50,'thumbnails'=>275]]; }
    private function endpoint(string $method, ?array $user): array
    {
        $endpoint = realpath(__DIR__ . '/../../../public/api/admin/data-snapshot-status.php'); $session = sys_get_temp_dir() . '/freetv-status-session-' . bin2hex(random_bytes(6)); mkdir($session,0700);
        $code = '$_SERVER["REQUEST_METHOD"]=' . var_export($method,true) . ';'; if ($user !== null) { $code .= 'ini_set("session.save_path",'.var_export($session,true).');session_id("test");session_start();$_SESSION["admin"]='.var_export($user,true).';'; } $code .= 'require '.var_export($endpoint,true).';';
        $process=proc_open([PHP_BINARY,'-d','display_errors=0','-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes); self::assertIsResource($process); $out=stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]);fclose($pipes[2]);proc_close($process); @unlink($session.'/sess_test');rmdir($session); return [$out];
    }
}
