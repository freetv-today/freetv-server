<?php

require_once __DIR__ . '/../public/api/admin/publication/PublicationSemanticDelta.php';

use FreeTV\Admin\Publication\PublicationSemanticDelta;

function assertDeltaSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

function publishedDeltaObject(array $artifact): object
{
    return json_decode(json_encode($artifact, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
}

function playlistDelta(array $authoritative, array $published): array
{
    return PublicationSemanticDelta::playlist($authoritative, publishedDeltaObject($published));
}

function deltaShow(string $identifier, string $title): array
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

$firstShow = deltaShow('first-show', 'First Show');
$secondShow = deltaShow('second-show', 'Second Show');
$basePlaylist = [
    'lastupdated' => '2000-01-01T00:00:00.000Z',
    'dbtitle' => 'FreeTV',
    'dbversion' => '1.0',
    'author' => 'Free TV',
    'email' => 'support@example.test',
    'link' => 'https://example.test',
    'shows' => [$firstShow, $secondShow],
];

$identical = playlistDelta($basePlaylist, $basePlaylist);
assertDeltaSame(null, $identical['error'], 'Identical playlist produced a delta error');
assertDeltaSame(0, $identical['delta']['shows_added'], 'Identical playlist reported added shows');
assertDeltaSame(0, $identical['delta']['shows_removed'], 'Identical playlist reported removed shows');
assertDeltaSame(0, $identical['delta']['shows_edited'], 'Identical playlist reported edited shows');
assertDeltaSame(false, $identical['delta']['order_changed'], 'Identical playlist reported reordered shows');
assertDeltaSame(false, $identical['delta']['metadata_changed'], 'Identical playlist reported metadata changes');

$withAddedShow = $basePlaylist;
$withAddedShow['shows'][] = deltaShow('third-show', 'Third Show');
$added = playlistDelta($withAddedShow, $basePlaylist);
assertDeltaSame(1, $added['delta']['shows_added'], 'New identifier was not reported as added');
assertDeltaSame(0, $added['delta']['shows_removed'], 'New identifier was also reported as removed');

$publishedWithRemovedShow = $basePlaylist;
$publishedWithRemovedShow['shows'][] = deltaShow('old-show', 'Old Show');
$removed = playlistDelta($basePlaylist, $publishedWithRemovedShow);
assertDeltaSame(1, $removed['delta']['shows_removed'], 'Published-only identifier was not reported as removed');
assertDeltaSame(0, $removed['delta']['shows_added'], 'Published-only identifier was also reported as added');

foreach (['title', 'desc', 'status'] as $field) {
    $editedPlaylist = $basePlaylist;
    $editedPlaylist['shows'][0][$field] = 'Changed ' . $field;
    $edited = playlistDelta($editedPlaylist, $basePlaylist);
    assertDeltaSame(1, $edited['delta']['shows_edited'], "Changed {$field} was not reported as edited");
    assertDeltaSame(0, $edited['delta']['shows_added'], "Changed {$field} was reported as added");
    assertDeltaSame(0, $edited['delta']['shows_removed'], "Changed {$field} was reported as removed");
}

$groupAdded = $basePlaylist;
$groupAdded['shows'][0]['group'] = 'Evening';
assertDeltaSame(1, playlistDelta($groupAdded, $basePlaylist)['delta']['shows_edited'],
    'Added group was not reported as an edit');
$groupRemoved = $basePlaylist;
$groupRemoved['shows'][0]['group'] = 'Evening';
assertDeltaSame(1, playlistDelta($basePlaylist, $groupRemoved)['delta']['shows_edited'],
    'Removed group was not reported as an edit');
$changedGroupPublished = $basePlaylist;
$changedGroupPublished['shows'][0]['group'] = 'Evening';
$changedGroupAuthoritative = $changedGroupPublished;
$changedGroupAuthoritative['shows'][0]['group'] = 'Late Night';
assertDeltaSame(1, playlistDelta($changedGroupAuthoritative, $changedGroupPublished)['delta']['shows_edited'],
    'Changed group was not reported as an edit');

$reordered = $basePlaylist;
$reordered['shows'] = [$secondShow, $firstShow];
$reorderOnly = playlistDelta($reordered, $basePlaylist);
assertDeltaSame(true, $reorderOnly['delta']['order_changed'], 'Reorder was not detected');
assertDeltaSame(0, $reorderOnly['delta']['shows_edited'], 'Reorder-only change was reported as an edit');

$editedAndReordered = $reordered;
$editedAndReordered['shows'][0]['title'] = 'Edited Second Show';
$combined = playlistDelta($editedAndReordered, $basePlaylist);
assertDeltaSame(true, $combined['delta']['order_changed'], 'Combined edit and reorder missed the reorder');
assertDeltaSame(1, $combined['delta']['shows_edited'], 'Combined edit and reorder missed the edit');

$duplicatePublished = $basePlaylist;
$duplicatePublished['shows'][] = $firstShow;
$duplicateResult = playlistDelta($basePlaylist, $duplicatePublished);
assertDeltaSame(null, $duplicateResult['delta'], 'Duplicate published identifier produced a delta');
assertDeltaSame(true, is_string($duplicateResult['error']) && $duplicateResult['error'] !== '',
    'Duplicate published identifier did not produce an explicit error');
$duplicateAuthoritative = $basePlaylist;
$duplicateAuthoritative['shows'][] = $firstShow;
assertDeltaSame(null, playlistDelta($duplicateAuthoritative, $basePlaylist)['delta'],
    'Duplicate authoritative identifier produced a delta');

foreach (['dbtitle', 'dbversion', 'author', 'email', 'link'] as $field) {
    $metadataChanged = $basePlaylist;
    $metadataChanged[$field] = 'Changed ' . $field;
    $metadataDelta = playlistDelta($metadataChanged, $basePlaylist)['delta'];
    assertDeltaSame(true, $metadataDelta['metadata_changed'], "Changed {$field} missed metadata flag");
    assertDeltaSame([$field], $metadataDelta['metadata_fields'], "Changed {$field} was not listed");
}

$timestampChanged = $basePlaylist;
$timestampChanged['lastupdated'] = '2026-08-15T12:00:00.000Z';
$timestampDelta = playlistDelta($timestampChanged, $basePlaylist)['delta'];
assertDeltaSame(false, $timestampDelta['metadata_changed'], 'lastupdated produced a metadata delta');
assertDeltaSame([], $timestampDelta['metadata_fields'], 'lastupdated was listed as changed metadata');

$config = ['lastupdated' => '2000-01-01T00:00:00.000Z', 'show_ads' => false];
$publishedConfig = publishedDeltaObject([
    'lastupdated' => '2026-08-15T12:00:00.000Z',
    'show_ads' => false,
]);
assertDeltaSame([], PublicationSemanticDelta::config($config, $publishedConfig)['fields'],
    'Config lastupdated produced a field delta');
$config['show_ads'] = true;
assertDeltaSame(['show_ads'], PublicationSemanticDelta::config($config, $publishedConfig)['fields'],
    'Changed publishable config field was not listed');

echo "PublicationSemanticDelta tests passed\n";
