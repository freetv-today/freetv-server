<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/publication/PlaylistPublicationSerializer.php';

use FreeTV\Admin\Publication\PlaylistPublicationSerializer;
use PHPUnit\Framework\TestCase;

final class PlaylistPublicationSerializerTest extends TestCase
{
    public function testSerializesViewerFieldsAndOrdersShowsBySortOrderThenId(): void
    {
        $playlist = [
            'dbtitle' => 'Fixture Playlist',
            'dbversion' => '2.1',
            'author' => 'FreeTV',
            'email' => 'admin@example.test',
            'link' => 'https://example.test',
        ];
        $shows = [
            $this->show(30, 2, 'show-third', 'Third description', '1970', '1971'),
            $this->show(20, 1, 'show-second', 'Second description', '1960', null),
            $this->show(10, 1, 'show-first', 'First description', '1950', '1955'),
        ];

        $artifact = PlaylistPublicationSerializer::serialize(
            $playlist,
            $shows,
            '2026-07-01T20:39:06.000Z'
        );

        self::assertSame('Fixture Playlist', $artifact['dbtitle']);
        self::assertSame('2.1', $artifact['dbversion']);
        self::assertSame('FreeTV', $artifact['author']);
        self::assertSame('admin@example.test', $artifact['email']);
        self::assertSame('https://example.test', $artifact['link']);
        self::assertSame(
            ['show-first', 'show-second', 'show-third'],
            array_column($artifact['shows'], 'identifier')
        );
        self::assertSame(
            [
                'category' => 'Adventure',
                'status' => 'active',
                'identifier' => 'show-first',
                'title' => 'First Show',
                'desc' => 'First description',
                'start' => '1950',
                'end' => '1955',
                'imdb' => 'tt0000010',
            ],
            $artifact['shows'][0]
        );
    }

    public function testIncludesTrimmedNonemptyGroupAndOmitsEmptyGroups(): void
    {
        $shows = [
            $this->show(10, 1, 'grouped', group: '  Saturday Morning  '),
            $this->show(20, 2, 'whitespace', group: '   '),
            $this->show(30, 3, 'empty', group: ''),
            $this->show(40, 4, 'null', group: null),
        ];

        $serialized = PlaylistPublicationSerializer::serialize(
            $this->playlist(),
            $shows,
            '2026-07-01T20:39:06.000Z'
        )['shows'];

        self::assertSame('Saturday Morning', $serialized[0]['group']);
        self::assertArrayNotHasKey('group', $serialized[1]);
        self::assertArrayNotHasKey('group', $serialized[2]);
        self::assertArrayNotHasKey('group', $serialized[3]);
    }

    public function testFormatsPublicationTimestampInUtc(): void
    {
        $artifact = PlaylistPublicationSerializer::serialize(
            $this->playlist(),
            [],
            new DateTimeImmutable('2026-07-01 16:39:06.123456-04:00')
        );

        self::assertSame('2026-07-01T20:39:06.123Z', $artifact['lastupdated']);
    }

    private function playlist(): array
    {
        return [
            'dbtitle' => 'Fixture Playlist',
            'dbversion' => '2.1',
            'author' => 'FreeTV',
            'email' => 'admin@example.test',
            'link' => 'https://example.test',
        ];
    }

    private function show(
        int $id,
        int $sortOrder,
        string $identifier,
        ?string $description = 'Description',
        ?string $startYear = '1950',
        ?string $endYear = '1955',
        ?string $group = null
    ): array {
        return [
            'id' => $id,
            'sort_order' => $sortOrder,
            'category' => 'Adventure',
            'status' => 'active',
            'identifier' => $identifier,
            'title' => $identifier === 'show-first' ? 'First Show' : 'Fixture Show',
            'description' => $description,
            'start_year' => $startYear,
            'end_year' => $endYear,
            'imdb' => sprintf('tt%07d', $id),
            'group_name' => $group,
        ];
    }
}
