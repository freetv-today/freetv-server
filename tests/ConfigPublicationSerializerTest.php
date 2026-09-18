<?php

require_once __DIR__ . '/../public/api/admin/Settings.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationTimestamp.php';
require_once __DIR__ . '/../public/api/admin/publication/ConfigPublicationSerializer.php';

use FreeTV\Admin\Publication\ConfigPublicationSerializer;
use FreeTV\Admin\Settings;

function assertConfigSerializerSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

$timestamp = new DateTimeImmutable('2026-08-12 15:30:00.000000-04:00');
$trueArtifact = ConfigPublicationSerializer::serialize(
    [
        'show_ads' => true,
        'offline' => true,
        'appdata' => true,
        'collector' => '/api/beacon.php',
        'modules' => true,
        'debugmode' => true,
        'showads' => true,
    ],
    $timestamp
);

assertConfigSerializerSame(
    ['lastupdated', 'show_ads'],
    array_keys($trueArtifact),
    'Config artifact shape must contain only publication metadata and publishable settings'
);
assertConfigSerializerSame(
    '2026-08-12T19:30:00.000Z',
    $trueArtifact['lastupdated'],
    'Config publication timestamp was not normalized to canonical UTC format'
);
assertConfigSerializerSame(true, $trueArtifact['show_ads'], 'show_ads true was not preserved as a boolean');
assertConfigSerializerSame(
    '{"lastupdated":"2026-08-12T19:30:00.000Z","show_ads":true}',
    json_encode($trueArtifact, JSON_THROW_ON_ERROR),
    'show_ads true was not JSON-encoded as a boolean'
);

$falseArtifact = ConfigPublicationSerializer::serialize(['show_ads' => false], $timestamp);
assertConfigSerializerSame(false, $falseArtifact['show_ads'], 'show_ads false was not preserved as a boolean');
assertConfigSerializerSame(
    '{"lastupdated":"2026-08-12T19:30:00.000Z","show_ads":false}',
    json_encode($falseArtifact, JSON_THROW_ON_ERROR),
    'show_ads false was not JSON-encoded as a boolean'
);

$settingsFromTrueRow = Settings::fromRows([[
    'setting_key' => 'show_ads',
    'setting_value' => 'true',
]]);
assertConfigSerializerSame(
    true,
    ConfigPublicationSerializer::serialize($settingsFromTrueRow, $timestamp)['show_ads'],
    'Existing Settings deserialization did not produce a boolean true'
);

$settingsFromFalseRow = Settings::fromRows([[
    'setting_key' => 'show_ads',
    'setting_value' => 'false',
]]);
assertConfigSerializerSame(
    false,
    ConfigPublicationSerializer::serialize($settingsFromFalseRow, $timestamp)['show_ads'],
    'Existing Settings deserialization did not produce a boolean false'
);

$settingsWithMissingRow = Settings::fromRows([]);
$defaultArtifact = ConfigPublicationSerializer::serialize($settingsWithMissingRow, $timestamp);
assertConfigSerializerSame(
    false,
    $defaultArtifact['show_ads'],
    'Missing database row did not use the registered default'
);
assertConfigSerializerSame(
    $trueArtifact,
    ConfigPublicationSerializer::serialize(
        [
            'show_ads' => true,
            'offline' => true,
            'appdata' => true,
            'collector' => '/api/beacon.php',
            'modules' => true,
            'debugmode' => true,
            'showads' => true,
        ],
        $timestamp
    ),
    'Repeated config serialization with identical input must be deterministic'
);

echo "ConfigPublicationSerializer tests passed\n";
