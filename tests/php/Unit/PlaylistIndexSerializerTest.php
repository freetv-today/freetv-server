<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/publication/PublicationException.php';
require_once __DIR__ . '/../../../public/api/admin/publication/PublicationTimestamp.php';
require_once __DIR__ . '/../../../public/api/admin/publication/PlaylistIndexSerializer.php';

use FreeTV\Admin\Publication\PlaylistIndexSerializer;
use FreeTV\Admin\Publication\PublicationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlaylistIndexSerializerTest extends TestCase
{
    public function testSerializeProducesExactOrderedPublicIndexAndPreservesPublishedTimestamps(): void
    {
        $artifact = PlaylistIndexSerializer::serialize(
            $this->playlists(),
            'selected.json',
            '2026-08-12T16:30:00.000Z',
            [
                'default.json' => '2026-08-09T08:00:00.000Z',
                'third.json' => '2026-08-10T10:30:00.000Z',
            ]
        );

        self::assertSame([
            'default' => 'default.json',
            'playlists' => [
                [
                    'filename' => 'default.json',
                    'dbtitle' => 'Default',
                    'lastupdated' => '2026-08-09T08:00:00.000Z',
                    'author' => 'Free TV',
                ],
                [
                    'filename' => 'selected.json',
                    'dbtitle' => 'Selected',
                    'lastupdated' => '2026-08-12T16:30:00.000Z',
                    'author' => 'Free TV',
                ],
                [
                    'filename' => 'third.json',
                    'dbtitle' => 'Third',
                    'lastupdated' => '2026-08-10T10:30:00.000Z',
                ],
            ],
        ], $artifact);
    }

    public function testDatabaseLastupdatedDoesNotReplaceExistingPublishedTimestamp(): void
    {
        $playlists = $this->playlists();
        $playlists[0]['lastupdated'] = '2099-01-01 00:00:00';

        $artifact = PlaylistIndexSerializer::serialize(
            $playlists,
            'selected.json',
            '2026-08-12T16:30:00.000Z',
            [
                'default.json' => '2020-01-02T03:04:05.000Z',
                'third.json' => '2021-02-03T04:05:06.000Z',
            ]
        );

        self::assertSame('2020-01-02T03:04:05.000Z', $artifact['playlists'][0]['lastupdated']);
        self::assertSame('2021-02-03T04:05:06.000Z', $artifact['playlists'][2]['lastupdated']);
    }

    public function testSerializeChangedHandlesMultipleSelectionsAndObjectRows(): void
    {
        $rows = array_map(static fn(array $row): object => (object) $row, $this->playlists());

        $artifact = PlaylistIndexSerializer::serializeChanged(
            $rows,
            ['selected.json', 'third.json'],
            new DateTimeImmutable('2026-08-12 12:30:00.456789-04:00'),
            ['default.json' => '2026-08-09T08:00:00.000Z']
        );

        self::assertSame('default.json', $artifact['default']);
        self::assertSame(
            ['default.json', 'selected.json', 'third.json'],
            array_column($artifact['playlists'], 'filename')
        );
        self::assertSame(
            [
                '2026-08-09T08:00:00.000Z',
                '2026-08-12T16:30:00.456Z',
                '2026-08-12T16:30:00.456Z',
            ],
            array_column($artifact['playlists'], 'lastupdated')
        );
    }

    #[DataProvider('defaultCountProvider')]
    public function testRequiresExactlyOneDatabaseDefault(int $count): void
    {
        $playlists = $this->playlists();
        foreach ($playlists as &$playlist) {
            $playlist['is_default'] = 0;
        }
        unset($playlist);
        for ($index = 0; $index < $count; $index++) {
            $playlists[$index]['is_default'] = 1;
        }

        $exception = $this->captureFailure(fn() => PlaylistIndexSerializer::serialize(
            $playlists,
            'selected.json',
            '2026-08-12T16:30:00.000Z',
            []
        ));

        self::assertSame(409, $exception->getHttpStatus());
        self::assertSame(
            "Publication requires exactly one default playlist; found {$count}",
            $exception->getMessage()
        );
    }

    public static function defaultCountProvider(): array
    {
        return ['none' => [0], 'multiple' => [2]];
    }

    #[DataProvider('invalidPublishedTimestampProvider')]
    public function testRejectsMissingOrInvalidUnchangedTimestamps(string $case, mixed $timestamp): void
    {
        $published = ['third.json' => '2026-08-10T10:30:00.000Z'];
        if ($case !== 'missing') {
            $published['default.json'] = $timestamp;
        }

        $exception = $this->captureFailure(fn() => PlaylistIndexSerializer::serialize(
            $this->playlists(),
            'selected.json',
            '2026-08-12T16:30:00.000Z',
            $published
        ));

        self::assertSame(409, $exception->getHttpStatus());
        self::assertSame(
            $case === 'missing'
                ? 'Existing published index is missing playlist default.json'
                : 'Existing published index has an invalid lastupdated for playlist default.json',
            $exception->getMessage()
        );
    }

    public static function invalidPublishedTimestampProvider(): array
    {
        return [
            'missing' => ['missing', null],
            'null' => ['null', null],
            'array' => ['array', []],
            'malformed' => ['malformed', 'not-a-date'],
            'noncanonical offset' => ['offset', '2026-08-09T04:00:00-04:00'],
            'noncanonical precision' => ['precision', '2026-08-09T08:00:00Z'],
        ];
    }

    #[DataProvider('invalidPublicationTimestampProvider')]
    public function testRejectsInvalidNewPublicationTimestamp(string $timestamp): void
    {
        $this->expectException(InvalidArgumentException::class);
        PlaylistIndexSerializer::serialize(
            $this->playlists(),
            'selected.json',
            $timestamp,
            [
                'default.json' => '2026-08-09T08:00:00.000Z',
                'third.json' => '2026-08-10T10:30:00.000Z',
            ]
        );
    }

    public static function invalidPublicationTimestampProvider(): array
    {
        return ['empty' => [''], 'malformed' => ['definitely-not-a-date']];
    }

    private function playlists(): array
    {
        return [
            [
                'id' => 30,
                'sort_order' => 2,
                'filename' => 'third.json',
                'dbtitle' => 'Third',
                'lastupdated' => '2026-08-10 10:30:00',
                'author' => null,
                'email' => 'not-public@example.test',
                'link' => 'https://not-public.example.test',
                'is_default' => 0,
            ],
            [
                'id' => 20,
                'sort_order' => 1,
                'filename' => 'selected.json',
                'dbtitle' => 'Selected',
                'lastupdated' => '2026-08-11 09:15:00',
                'author' => 'Free TV',
                'is_default' => 0,
            ],
            [
                'id' => 10,
                'sort_order' => 1,
                'filename' => 'default.json',
                'dbtitle' => 'Default',
                'lastupdated' => '2026-08-09 08:00:00',
                'author' => 'Free TV',
                'is_default' => 1,
            ],
        ];
    }

    private function captureFailure(callable $operation): PublicationException
    {
        try {
            $operation();
            self::fail('Expected PublicationException');
        } catch (PublicationException $exception) {
            return $exception;
        }
    }
}
