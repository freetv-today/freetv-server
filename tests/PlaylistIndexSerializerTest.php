<?php

require_once __DIR__ . '/../public/api/admin/publication/PublicationException.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationTimestamp.php';
require_once __DIR__ . '/../public/api/admin/publication/PlaylistIndexSerializer.php';

use FreeTV\Admin\Publication\PlaylistIndexSerializer;
use FreeTV\Admin\Publication\PublicationException;

function assertIndexSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

function expectDefaultValidationFailure(array $playlists, int $count): void
{
    try {
        PlaylistIndexSerializer::serialize(
            $playlists,
            'selected.json',
            '2026-08-12T16:30:00.000Z'
        );
    } catch (PublicationException $exception) {
        assertIndexSame(
            "Publication requires exactly one default playlist; found {$count}",
            $exception->getMessage(),
            'Unexpected default validation error'
        );
        return;
    }

    throw new RuntimeException('Invalid default count did not fail publication');
}

$playlists = [
    [
        'id' => 30,
        'sort_order' => 2,
        'filename' => 'third.json',
        'dbtitle' => 'Third',
        'lastupdated' => '2026-08-10 10:30:00',
        'author' => null,
        'email' => null,
        'link' => null,
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

$timestamp = '2026-08-12T16:30:00.000Z';
$index = PlaylistIndexSerializer::serialize($playlists, 'selected.json', $timestamp);

assertIndexSame('default.json', $index['default'], 'The database default was not published');
assertIndexSame(
    ['default.json', 'selected.json', 'third.json'],
    array_column($index['playlists'], 'filename'),
    'Index entries were not ordered by sort_order, then id'
);
assertIndexSame(
    $timestamp,
    $index['playlists'][1]['lastupdated'],
    'Selected playlist did not receive the publication timestamp'
);
assertIndexSame(
    '2026-08-09T08:00:00.000Z',
    $index['playlists'][0]['lastupdated'],
    'Unchanged playlist timestamp was not retained'
);
assertIndexSame(
    false,
    array_key_exists('is_default', $index['playlists'][0]),
    'Index entry must not expose is_default'
);
assertIndexSame(
    false,
    array_key_exists('author', $index['playlists'][2]),
    'Null optional metadata must be omitted'
);
assertIndexSame(
    false,
    array_key_exists('email', $index['playlists'][0]),
    'Index entries must not invent additional public metadata fields'
);
assertIndexSame(
    $index,
    PlaylistIndexSerializer::serialize($playlists, 'selected.json', $timestamp),
    'Repeated index serialization with identical input must be deterministic'
);

$noDefaults = array_map(static function (array $playlist): array {
    $playlist['is_default'] = 0;
    return $playlist;
}, $playlists);
expectDefaultValidationFailure($noDefaults, 0);

$multipleDefaults = $playlists;
$multipleDefaults[1]['is_default'] = 1;
expectDefaultValidationFailure($multipleDefaults, 2);

echo "PlaylistIndexSerializer tests passed\n";
