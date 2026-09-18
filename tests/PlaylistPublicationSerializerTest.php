<?php

require_once __DIR__ . '/../public/api/admin/publication/PlaylistPublicationSerializer.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationTimestamp.php';

use FreeTV\Admin\Publication\PlaylistPublicationSerializer;

function assertPublicationSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

$playlist = [
    'id' => 7,
    'dbtitle' => 'Fixture Playlist',
    'filename' => 'fixture.json',
    'dbversion' => '2.1',
    'author' => 'FreeTV',
    'email' => 'admin@example.test',
    'link' => 'https://example.test',
    'is_default' => 1,
];

$shows = [
    [
        'id' => 30,
        'sort_order' => 2,
        'category' => 'Comedy',
        'status' => 'active',
        'identifier' => 'show-third',
        'title' => 'Third Show',
        'description' => 'Third description',
        'start_year' => '1970',
        'end_year' => '1971',
        'imdb' => 'tt0000003',
        'group_name' => null,
    ],
    [
        'id' => 20,
        'sort_order' => 1,
        'category' => 'Drama',
        'status' => 'disabled',
        'identifier' => 'show-second',
        'title' => 'Second Show',
        'description' => 'Second description',
        'start_year' => '1960',
        'end_year' => null,
        'imdb' => 'tt0000002',
        'group_name' => '   ',
    ],
    [
        'id' => 10,
        'sort_order' => 1,
        'category' => 'Adventure',
        'status' => 'active',
        'identifier' => 'show-first',
        'title' => 'First Show',
        'description' => 'First description',
        'start_year' => '1950',
        'end_year' => '1955',
        'imdb' => 'tt0000001',
        'group_name' => '  Saturday Morning  ',
    ],
    [
        'id' => 40,
        'sort_order' => 3,
        'category' => 'Mystery',
        'status' => 'active',
        'identifier' => 'show-fourth',
        'title' => 'Fourth Show',
        'description' => null,
        'start_year' => null,
        'end_year' => null,
        'imdb' => null,
        'group_name' => '',
    ],
];

$timestamp = new DateTimeImmutable('2026-07-01 16:39:06.000000-04:00');
$artifact = PlaylistPublicationSerializer::serialize($playlist, $shows, $timestamp);

assertPublicationSame(
    ['lastupdated', 'dbtitle', 'dbversion', 'author', 'email', 'link', 'shows'],
    array_keys($artifact),
    'Playlist artifact top-level shape is not the Viewer contract'
);
assertPublicationSame(false, array_key_exists('is_default', $artifact), 'is_default must not be published');
assertPublicationSame(false, array_key_exists('filename', $artifact), 'filename must not be published');
assertPublicationSame(
    [
        'dbtitle' => 'Fixture Playlist',
        'dbversion' => '2.1',
        'author' => 'FreeTV',
        'email' => 'admin@example.test',
        'link' => 'https://example.test',
    ],
    array_intersect_key($artifact, array_flip([
        'dbtitle',
        'dbversion',
        'author',
        'email',
        'link',
    ])),
    'Playlist metadata was not serialized to the Viewer contract'
);
assertPublicationSame(
    '2026-07-01T20:39:06.000Z',
    $artifact['lastupdated'],
    'Supplied publication timestamp was not normalized to canonical UTC format'
);
assertPublicationSame(
    ['show-first', 'show-second', 'show-third', 'show-fourth'],
    array_column($artifact['shows'], 'identifier'),
    'Shows were not ordered by sort_order, then id'
);
assertPublicationSame(
    [
        'category' => 'Adventure',
        'status' => 'active',
        'identifier' => 'show-first',
        'title' => 'First Show',
        'desc' => 'First description',
        'start' => '1950',
        'end' => '1955',
        'imdb' => 'tt0000001',
        'group' => 'Saturday Morning',
    ],
    $artifact['shows'][0],
    'Show fields were not translated to the Viewer contract'
);
assertPublicationSame(
    false,
    array_key_exists('group', $artifact['shows'][1]),
    'Whitespace group_name must be omitted'
);
assertPublicationSame(
    false,
    array_key_exists('group', $artifact['shows'][2]),
    'Null group_name must be omitted'
);
assertPublicationSame(
    false,
    array_key_exists('group', $artifact['shows'][3]),
    'Empty group_name must be omitted'
);
assertPublicationSame(
    $artifact,
    PlaylistPublicationSerializer::serialize($playlist, $shows, $timestamp),
    'Repeated serialization with identical input must be deterministic'
);

echo "PlaylistPublicationSerializer tests passed\n";
