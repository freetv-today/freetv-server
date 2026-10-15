<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/publication/PublicationSemanticDelta.php';

use FreeTV\Admin\Publication\PublicationSemanticDelta;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicationSemanticDeltaTest extends TestCase
{
    public function testIdenticalPlaylistHasAnEmptyDelta(): void
    {
        $result = $this->playlistDelta($this->playlist(), $this->playlist());

        self::assertNull($result['error']);
        self::assertSame([
            'shows_added' => 0,
            'shows_removed' => 0,
            'shows_edited' => 0,
            'order_changed' => false,
            'metadata_changed' => false,
            'metadata_fields' => [],
        ], $result['delta']);
    }

    public function testAdditionsAndRemovalsRemainDistinctFromEdits(): void
    {
        $base = $this->playlist();
        $withAddedShow = $base;
        $withAddedShow['shows'][] = $this->show('third-show', 'Third Show');

        self::assertSame([
            'shows_added' => 1,
            'shows_removed' => 0,
            'shows_edited' => 0,
            'order_changed' => false,
            'metadata_changed' => false,
            'metadata_fields' => [],
        ], $this->playlistDelta($withAddedShow, $base)['delta']);

        $publishedWithRemovedShow = $base;
        $publishedWithRemovedShow['shows'][] = $this->show('old-show', 'Old Show');
        self::assertSame([
            'shows_added' => 0,
            'shows_removed' => 1,
            'shows_edited' => 0,
            'order_changed' => false,
            'metadata_changed' => false,
            'metadata_fields' => [],
        ], $this->playlistDelta($base, $publishedWithRemovedShow)['delta']);
    }

    #[DataProvider('editedShowFieldProvider')]
    public function testChangedShowFieldsCountAsEdits(string $field, string $value): void
    {
        $base = $this->playlist();
        $edited = $base;
        $edited['shows'][0][$field] = $value;

        $delta = $this->playlistDelta($edited, $base)['delta'];
        self::assertSame(1, $delta['shows_edited']);
        self::assertSame(0, $delta['shows_added']);
        self::assertSame(0, $delta['shows_removed']);
    }

    public static function editedShowFieldProvider(): array
    {
        return [
            'title' => ['title', 'Edited First Show'],
            'description' => ['desc', 'Edited description'],
            'status' => ['status', 'disabled'],
        ];
    }

    #[DataProvider('groupChangeProvider')]
    public function testOptionalGroupChangesCountAsEdits(array $authoritative, array $published): void
    {
        $result = $this->playlistDelta($authoritative, $published);

        self::assertNull($result['error']);
        self::assertSame(1, $result['delta']['shows_edited']);
    }

    public static function groupChangeProvider(): array
    {
        $base = self::playlistFixture();
        $withEvening = $base;
        $withEvening['shows'][0]['group'] = 'Evening';
        $withLateNight = $withEvening;
        $withLateNight['shows'][0]['group'] = 'Late Night';

        return [
            'added' => [$withEvening, $base],
            'removed' => [$base, $withEvening],
            'modified' => [$withLateNight, $withEvening],
        ];
    }

    public function testOrderingAndEditingAreReportedSeparately(): void
    {
        $base = $this->playlist();
        $reordered = $base;
        $reordered['shows'] = [$base['shows'][1], $base['shows'][0]];

        $reorderDelta = $this->playlistDelta($reordered, $base)['delta'];
        self::assertTrue($reorderDelta['order_changed']);
        self::assertSame(0, $reorderDelta['shows_edited']);

        $reordered['shows'][0]['title'] = 'Edited Second Show';
        $combinedDelta = $this->playlistDelta($reordered, $base)['delta'];
        self::assertTrue($combinedDelta['order_changed']);
        self::assertSame(1, $combinedDelta['shows_edited']);
    }

    #[DataProvider('duplicateIdentifierProvider')]
    public function testDuplicateIdentifiersReturnAnErrorWithoutDelta(string $side): void
    {
        $authoritative = $this->playlist();
        $published = $this->playlist();
        if ($side === 'authoritative') {
            $authoritative['shows'][] = $authoritative['shows'][0];
        } else {
            $published['shows'][] = $published['shows'][0];
        }

        $result = $this->playlistDelta($authoritative, $published);
        self::assertNull($result['delta']);
        self::assertIsString($result['error']);
        self::assertNotSame('', $result['error']);
    }

    public static function duplicateIdentifierProvider(): array
    {
        return [
            'authoritative duplicate' => ['authoritative'],
            'published duplicate' => ['published'],
        ];
    }

    #[DataProvider('metadataFieldProvider')]
    public function testPlaylistMetadataChangesListTheChangedField(string $field): void
    {
        $base = $this->playlist();
        $changed = $base;
        $changed[$field] = 'Changed ' . $field;

        $delta = $this->playlistDelta($changed, $base)['delta'];
        self::assertTrue($delta['metadata_changed']);
        self::assertSame([$field], $delta['metadata_fields']);
    }

    public static function metadataFieldProvider(): array
    {
        return array_combine(
            ['dbtitle', 'dbversion', 'author', 'email', 'link'],
            array_map(static fn(string $field): array => [$field], ['dbtitle', 'dbversion', 'author', 'email', 'link'])
        );
    }

    public function testPlaylistTimestampDifferenceIsIgnored(): void
    {
        $base = $this->playlist();
        $changed = $base;
        $changed['lastupdated'] = '2026-08-15T12:00:00.000Z';

        $delta = $this->playlistDelta($changed, $base)['delta'];
        self::assertFalse($delta['metadata_changed']);
        self::assertSame([], $delta['metadata_fields']);
    }

    public function testConfigDetectsPublishableChangesButIgnoresTimestamp(): void
    {
        $published = $this->object([
            'lastupdated' => '2026-08-15T12:00:00.000Z',
            'show_ads' => false,
        ]);

        self::assertSame(
            ['fields' => []],
            PublicationSemanticDelta::config([
                'lastupdated' => '2000-01-01T00:00:00.000Z',
                'show_ads' => false,
            ], $published)
        );
        self::assertSame(
            ['fields' => ['show_ads']],
            PublicationSemanticDelta::config([
                'lastupdated' => '2000-01-01T00:00:00.000Z',
                'show_ads' => true,
            ], $published)
        );
    }

    private function playlistDelta(array $authoritative, array $published): array
    {
        return PublicationSemanticDelta::playlist($authoritative, $this->object($published));
    }

    private function object(array $artifact): object
    {
        return json_decode(json_encode($artifact, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
    }

    private function playlist(): array
    {
        return self::playlistFixture();
    }

    private static function playlistFixture(): array
    {
        return [
            'lastupdated' => '2000-01-01T00:00:00.000Z',
            'dbtitle' => 'FreeTV',
            'dbversion' => '1.0',
            'author' => 'Free TV',
            'email' => 'support@example.test',
            'link' => 'https://example.test',
            'shows' => [
                self::showFixture('first-show', 'First Show'),
                self::showFixture('second-show', 'Second Show'),
            ],
        ];
    }

    private function show(string $identifier, string $title): array
    {
        return self::showFixture($identifier, $title);
    }

    private static function showFixture(string $identifier, string $title): array
    {
        return [
            'category' => 'Comedy',
            'status' => 'active',
            'identifier' => $identifier,
            'title' => $title,
            'desc' => 'Description for ' . $title,
            'start' => '1960',
            'end' => '1961',
            'imdb' => 'tt0000001',
        ];
    }
}
