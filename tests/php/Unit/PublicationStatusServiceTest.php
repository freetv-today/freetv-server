<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/publication/PublicationStatusService.php';

use FreeTV\Admin\Publication\ConfigPublicationSerializer;
use FreeTV\Admin\Publication\PlaylistPublicationSerializer;
use FreeTV\Admin\Publication\PublicationStatusService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicationStatusServiceTest extends TestCase
{
    private string $root;
    private array $basePlaylist;
    private array $baseShows;
    private array $playlistRows;
    private array $showsByPlaylist;
    private array $settings;
    private array $publishedPlaylist;
    private array $publishedConfig;
    private array $publishedIndex;
    private array $readCounts;
    private PublicationStatusService $service;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/freetv-publication-status-phpunit-' . bin2hex(random_bytes(8));
        if (!mkdir($this->root . '/playlists', 0700, true)) {
            self::fail('Could not create publication-status fixture directory');
        }

        $this->basePlaylist = [
            'id' => 1,
            'filename' => 'freetv.json',
            'dbtitle' => 'FreeTV',
            'dbversion' => '1.0',
            'author' => 'Free TV',
            'email' => 'support@example.test',
            'link' => 'https://example.test',
            'is_default' => 1,
            'sort_order' => 0,
        ];
        $this->baseShows = [
            $this->show(1, 0, 'first-show', 'First Show', null),
            $this->show(2, 1, 'second-show', 'Second Show', 'Evening'),
        ];
        $this->playlistRows = [$this->basePlaylist];
        $this->showsByPlaylist = [1 => $this->baseShows];
        $this->settings = ['show_ads' => false];
        $this->readCounts = ['playlists' => 0, 'shows' => 0, 'settings' => 0];

        $this->service = new PublicationStatusService(
            $this->root,
            function (): array {
                $this->readCounts['playlists']++;
                return $this->playlistRows;
            },
            function (int $playlistId): array {
                $this->readCounts['shows']++;
                return $this->showsByPlaylist[$playlistId] ?? [];
            },
            function (): array {
                $this->readCounts['settings']++;
                return $this->settings;
            }
        );

        $this->publishedPlaylist = PlaylistPublicationSerializer::serialize(
            $this->basePlaylist,
            $this->baseShows,
            '2026-08-01T09:00:00.000Z'
        );
        $this->publishedConfig = ConfigPublicationSerializer::serialize(
            ['show_ads' => false],
            '2026-08-01T10:00:00.000Z'
        );
        $this->publishedIndex = [
            'default' => 'freetv.json',
            'playlists' => [[
                'filename' => 'freetv.json',
                'dbtitle' => 'FreeTV',
                'lastupdated' => '2026-08-01T09:00:00.000Z',
                'author' => 'Free TV',
            ]],
        ];

        $this->writeJson('playlists/freetv.json', $this->publishedPlaylist);
        $this->writeJson('playlists/index.json', $this->publishedIndex);
        $this->writeJson('config.json', $this->publishedConfig);
    }

    protected function tearDown(): void
    {
        if (!isset($this->root) || !is_dir($this->root)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    public function testMatchingArtifactsIgnoreTimestampDifferencesAndHaveEmptyDeltas(): void
    {
        self::assertArrayNotHasKey('filename', $this->publishedPlaylist);

        $status = $this->service->status();
        self::assertSame([
            'filename' => 'freetv.json',
            'dbtitle' => 'FreeTV',
            'changed' => false,
            'error' => null,
            'delta' => [
                'shows_added' => 0,
                'shows_removed' => 0,
                'shows_edited' => 0,
                'order_changed' => false,
                'metadata_changed' => false,
                'metadata_fields' => [],
            ],
        ], $status['playlists'][0]);
        self::assertSame([
            'changed' => false,
            'error' => null,
            'delta' => ['fields' => []],
        ], $status['config']);
        self::assertSame([
            'changed' => false,
            'database' => 'freetv.json',
            'published' => 'freetv.json',
            'error' => null,
        ], $status['default_playlist']);
    }

    public function testChangedShowContentReportsEditsAdditionsRemovalsAndOrdering(): void
    {
        $this->showsByPlaylist[1][0]['title'] = 'Edited First Show';
        $edited = $this->service->status()['playlists'][0];
        self::assertTrue($edited['changed']);
        self::assertNull($edited['error']);
        self::assertSame(1, $edited['delta']['shows_edited']);

        $this->showsByPlaylist[1] = $this->baseShows;
        $this->showsByPlaylist[1][] = $this->show(3, 2, 'third-show', 'Third Show', null);
        $added = $this->service->status()['playlists'][0];
        self::assertTrue($added['changed']);
        self::assertSame(1, $added['delta']['shows_added']);
        self::assertSame(0, $added['delta']['shows_edited']);

        $this->showsByPlaylist[1] = [$this->baseShows[0]];
        $removed = $this->service->status()['playlists'][0];
        self::assertTrue($removed['changed']);
        self::assertSame(1, $removed['delta']['shows_removed']);
        self::assertSame(0, $removed['delta']['shows_edited']);

        $this->showsByPlaylist[1] = $this->baseShows;
        $this->showsByPlaylist[1][0]['sort_order'] = 1;
        $this->showsByPlaylist[1][1]['sort_order'] = 0;
        $reordered = $this->service->status()['playlists'][0];
        self::assertTrue($reordered['changed']);
        self::assertTrue($reordered['delta']['order_changed']);
        self::assertSame(0, $reordered['delta']['shows_edited']);
    }

    #[DataProvider('playlistMetadataProvider')]
    public function testPlaylistMetadataChangesAreDetailed(string $field): void
    {
        $this->playlistRows[0][$field] = 'Changed ' . $field;

        $status = $this->service->status()['playlists'][0];
        self::assertTrue($status['changed']);
        self::assertNull($status['error']);
        self::assertTrue($status['delta']['metadata_changed']);
        self::assertSame([$field], $status['delta']['metadata_fields']);
    }

    public static function playlistMetadataProvider(): array
    {
        return array_combine(
            ['dbtitle', 'dbversion', 'author', 'email', 'link'],
            array_map(static fn(string $field): array => [$field], ['dbtitle', 'dbversion', 'author', 'email', 'link'])
        );
    }

    public function testPublishableConfigChangeIsDetailed(): void
    {
        $this->settings['show_ads'] = true;

        $status = $this->service->status()['config'];
        self::assertTrue($status['changed']);
        self::assertNull($status['error']);
        self::assertSame(['fields' => ['show_ads']], $status['delta']);
    }

    #[DataProvider('missingArtifactProvider')]
    public function testMissingArtifactsAreChangedWithoutFabricatedDeltas(string $relativePath, string $section): void
    {
        unlink($this->root . '/' . $relativePath);

        $status = $this->service->status();
        $artifactStatus = $section === 'playlist' ? $status['playlists'][0] : $status['config'];
        self::assertTrue($artifactStatus['changed']);
        self::assertNull($artifactStatus['error']);
        self::assertArrayNotHasKey('delta', $artifactStatus);
    }

    public static function missingArtifactProvider(): array
    {
        return [
            'playlist' => ['playlists/freetv.json', 'playlist'],
            'config' => ['config.json', 'config'],
        ];
    }

    #[DataProvider('invalidPlaylistProvider')]
    public function testInvalidPlaylistArtifactsReturnErrorsWithoutDeltas(string $case): void
    {
        $artifact = $this->publishedPlaylist;
        switch ($case) {
            case 'invalid-json':
                $this->writeRaw('playlists/freetv.json', '{invalid');
                break;
            case 'top-level-list':
                $this->writeJson('playlists/freetv.json', []);
                break;
            case 'missing-dbtitle':
                unset($artifact['dbtitle']);
                $this->writeJson('playlists/freetv.json', $artifact);
                break;
            case 'unexpected-filename':
                $artifact['filename'] = 'freetv.json';
                $this->writeJson('playlists/freetv.json', $artifact);
                break;
            case 'missing-shows':
                unset($artifact['shows']);
                $this->writeJson('playlists/freetv.json', $artifact);
                break;
            case 'shows-not-array':
                $artifact['shows'] = 'not-an-array';
                $this->writeJson('playlists/freetv.json', $artifact);
                break;
            case 'show-not-object':
                $artifact['shows'][0] = 'not-an-object';
                $this->writeJson('playlists/freetv.json', $artifact);
                break;
            case 'missing-show-field':
                unset($artifact['shows'][0]['title']);
                $this->writeJson('playlists/freetv.json', $artifact);
                break;
            case 'invalid-show-field':
                $artifact['shows'][0]['title'] = false;
                $this->writeJson('playlists/freetv.json', $artifact);
                break;
            case 'unexpected-show-field':
                $artifact['shows'][0]['sort_order'] = 1;
                $this->writeJson('playlists/freetv.json', $artifact);
                break;
            case 'duplicate-identifier':
                $artifact['shows'][] = $artifact['shows'][0];
                $this->writeJson('playlists/freetv.json', $artifact);
                break;
            case 'invalid-timestamp':
                $artifact['lastupdated'] = '2026-08-01 09:00:00';
                $this->writeJson('playlists/freetv.json', $artifact);
                break;
            case 'malformed-group':
                $artifact['shows'][0]['group'] = '  Evening  ';
                $this->writeJson('playlists/freetv.json', $artifact);
                break;
            default:
                self::fail('Unknown invalid playlist case');
        }

        $this->assertArtifactError($this->service->status()['playlists'][0]);
    }

    public static function invalidPlaylistProvider(): array
    {
        return array_combine(
            $cases = [
                'invalid-json',
                'top-level-list',
                'missing-dbtitle',
                'unexpected-filename',
                'missing-shows',
                'shows-not-array',
                'show-not-object',
                'missing-show-field',
                'invalid-show-field',
                'unexpected-show-field',
                'duplicate-identifier',
                'invalid-timestamp',
                'malformed-group',
            ],
            array_map(static fn(string $case): array => [$case], $cases)
        );
    }

    public function testOmittedOptionalGroupIsAValidSemanticChange(): void
    {
        $artifact = $this->publishedPlaylist;
        unset($artifact['shows'][1]['group']);
        $this->writeJson('playlists/freetv.json', $artifact);

        $status = $this->service->status()['playlists'][0];
        self::assertTrue($status['changed']);
        self::assertNull($status['error']);
        self::assertSame(1, $status['delta']['shows_edited']);
    }

    #[DataProvider('invalidConfigProvider')]
    public function testInvalidConfigArtifactsReturnErrorsWithoutDeltas(string $case): void
    {
        $artifact = $this->publishedConfig;
        switch ($case) {
            case 'invalid-json':
                $this->writeRaw('config.json', '{invalid');
                break;
            case 'top-level-list':
                $this->writeJson('config.json', []);
                break;
            case 'missing-show-ads':
                unset($artifact['show_ads']);
                $this->writeJson('config.json', $artifact);
                break;
            case 'invalid-show-ads':
                $artifact['show_ads'] = 'false';
                $this->writeJson('config.json', $artifact);
                break;
            case 'unexpected-field':
                $artifact['extra'] = true;
                $this->writeJson('config.json', $artifact);
                break;
            case 'invalid-timestamp':
                $artifact['lastupdated'] = 'not-a-timestamp';
                $this->writeJson('config.json', $artifact);
                break;
            default:
                self::fail('Unknown invalid config case');
        }

        $this->assertArtifactError($this->service->status()['config']);
    }

    public static function invalidConfigProvider(): array
    {
        return array_combine(
            $cases = [
                'invalid-json',
                'top-level-list',
                'missing-show-ads',
                'invalid-show-ads',
                'unexpected-field',
                'invalid-timestamp',
            ],
            array_map(static fn(string $case): array => [$case], $cases)
        );
    }

    public function testValidChangedDefaultReportsDatabaseAndPublishedFilenames(): void
    {
        $index = $this->publishedIndex;
        $index['default'] = 'other.json';
        $index['playlists'][] = [
            'filename' => 'other.json',
            'dbtitle' => 'Other',
            'lastupdated' => '2026-08-01T11:00:00.000Z',
        ];
        $this->writeJson('playlists/index.json', $index);

        self::assertSame([
            'changed' => true,
            'database' => 'freetv.json',
            'published' => 'other.json',
            'error' => null,
        ], $this->service->status()['default_playlist']);
    }

    #[DataProvider('invalidIndexProvider')]
    public function testInvalidOrMissingIndexIsAnExplicitError(string $case): void
    {
        $index = $this->publishedIndex;
        switch ($case) {
            case 'missing':
                unlink($this->root . '/playlists/index.json');
                break;
            case 'invalid-json':
                $this->writeRaw('playlists/index.json', '{invalid');
                break;
            case 'top-level-list':
                $this->writeJson('playlists/index.json', []);
                break;
            case 'missing-playlists':
                unset($index['playlists']);
                $this->writeJson('playlists/index.json', $index);
                break;
            case 'default-absent':
                $index['default'] = 'missing.json';
                $this->writeJson('playlists/index.json', $index);
                break;
            case 'entry-missing-filename':
                unset($index['playlists'][0]['filename']);
                $this->writeJson('playlists/index.json', $index);
                break;
            case 'entry-missing-dbtitle':
                unset($index['playlists'][0]['dbtitle']);
                $this->writeJson('playlists/index.json', $index);
                break;
            case 'invalid-timestamp':
                $index['playlists'][0]['lastupdated'] = '2026-08-01 09:00:00';
                $this->writeJson('playlists/index.json', $index);
                break;
            case 'invalid-author':
                $index['playlists'][0]['author'] = false;
                $this->writeJson('playlists/index.json', $index);
                break;
            case 'unexpected-entry-field':
                $index['playlists'][0]['extra'] = true;
                $this->writeJson('playlists/index.json', $index);
                break;
            case 'duplicate-filename':
                $index['playlists'][] = $index['playlists'][0];
                $this->writeJson('playlists/index.json', $index);
                break;
            default:
                self::fail('Unknown invalid index case');
        }

        $status = $this->service->status()['default_playlist'];
        self::assertNull($status['changed']);
        self::assertIsString($status['error']);
        self::assertNotSame('', $status['error']);
    }

    public static function invalidIndexProvider(): array
    {
        return array_combine(
            $cases = [
                'missing',
                'invalid-json',
                'top-level-list',
                'missing-playlists',
                'default-absent',
                'entry-missing-filename',
                'entry-missing-dbtitle',
                'invalid-timestamp',
                'invalid-author',
                'unexpected-entry-field',
                'duplicate-filename',
            ],
            array_map(static fn(string $case): array => [$case], $cases)
        );
    }

    public function testIndexAuthorIsOptional(): void
    {
        $index = $this->publishedIndex;
        unset($index['playlists'][0]['author']);
        $this->writeJson('playlists/index.json', $index);

        $status = $this->service->status()['default_playlist'];
        self::assertFalse($status['changed']);
        self::assertNull($status['error']);
    }

    #[DataProvider('invalidDatabaseDefaultProvider')]
    public function testDatabaseMustContainExactlyOneDefault(string $case): void
    {
        if ($case === 'none') {
            $this->playlistRows[0]['is_default'] = 0;
        } else {
            $this->playlistRows[] = [
                'id' => 2,
                'filename' => 'other.json',
                'dbtitle' => 'Other',
                'dbversion' => '1.0',
                'author' => null,
                'email' => null,
                'link' => null,
                'is_default' => 1,
                'sort_order' => 1,
            ];
            $this->showsByPlaylist[2] = [];
        }

        $status = $this->service->status()['default_playlist'];
        self::assertNull($status['changed']);
        self::assertNull($status['database']);
        self::assertNull($status['published']);
        self::assertIsString($status['error']);
        self::assertNotSame('', $status['error']);
    }

    public static function invalidDatabaseDefaultProvider(): array
    {
        return ['no defaults' => ['none'], 'multiple defaults' => ['multiple']];
    }

    public function testStatusChecksAreDeterministicReadOnlyAndUseInjectedLoaders(): void
    {
        $hashesBefore = $this->fileHashes();
        $first = $this->service->status();
        $second = $this->service->status();

        self::assertSame($first, $second);
        self::assertSame($hashesBefore, $this->fileHashes());
        self::assertGreaterThan(0, $this->readCounts['playlists']);
        self::assertGreaterThan(0, $this->readCounts['shows']);
        self::assertGreaterThan(0, $this->readCounts['settings']);
    }

    private function assertArtifactError(array $status): void
    {
        self::assertNull($status['changed']);
        self::assertIsString($status['error']);
        self::assertNotSame('', $status['error']);
        self::assertArrayNotHasKey('delta', $status);
    }

    private function show(int $id, int $sortOrder, string $identifier, string $title, ?string $group): array
    {
        return [
            'id' => $id,
            'sort_order' => $sortOrder,
            'category' => $id === 1 ? 'comedy' : 'drama',
            'status' => 'active',
            'identifier' => $identifier,
            'title' => $title,
            'description' => $id === 1 ? 'First description' : 'Second description',
            'start_year' => $id === 1 ? '1960' : '1970',
            'end_year' => $id === 1 ? '1961' : '1971',
            'imdb' => sprintf('tt%07d', $id),
            'group_name' => $group,
        ];
    }

    private function writeJson(string $relativePath, array $artifact): void
    {
        $this->writeRaw(
            $relativePath,
            json_encode(
                $artifact,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ) . "\n"
        );
    }

    private function writeRaw(string $relativePath, string $contents): void
    {
        $path = $this->root . '/' . $relativePath;
        if (file_put_contents($path, $contents) !== strlen($contents)) {
            throw new RuntimeException('Could not write publication-status fixture');
        }
    }

    private function fileHashes(): array
    {
        $hashes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $relativePath = substr($item->getPathname(), strlen($this->root) + 1);
                $hashes[$relativePath] = hash_file('sha256', $item->getPathname());
            }
        }
        ksort($hashes);
        return $hashes;
    }
}
