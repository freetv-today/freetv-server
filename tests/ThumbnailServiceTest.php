<?php

require_once __DIR__ . '/../public/api/admin/ThumbnailService.php';

use FreeTV\Admin\ThumbnailService;

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

assertSameValue(
    [
        'number_of_shows' => 3,
        'number_of_thumbnails' => 1,
        'missing_thumbnails' => 0,
        'shared_thumbnails' => 2,
    ],
    ThumbnailService::calculateSummary(3, 1, 1),
    'Three shows sharing one existing IMDb should produce two shared overlaps'
);

assertSameValue(
    [
        'number_of_shows' => 100,
        'number_of_thumbnails' => 92,
        'missing_thumbnails' => 3,
        'shared_thumbnails' => 5,
    ],
    ThumbnailService::calculateSummary(100, 95, 92),
    'Summary accounting should match the documented example'
);

assertSameValue(true, ThumbnailService::isValidImdb('tt0052520'), 'Valid IMDb ID rejected');
assertSameValue(true, ThumbnailService::isValidImdb('tt0081846'), 'Valid IMDb ID rejected');
assertSameValue(true, ThumbnailService::isValidImdb('tt13473018'), 'Valid IMDb ID rejected');
assertSameValue(false, ThumbnailService::isValidImdb('0052520'), 'IMDb ID without tt accepted');
assertSameValue(false, ThumbnailService::isValidImdb('ttABC123'), 'Non-numeric IMDb ID accepted');
assertSameValue(false, ThumbnailService::isValidImdb('tt0052520.jpg'), 'Filename accepted as IMDb ID');
assertSameValue(
    false,
    ThumbnailService::isValidImdb('https://www.imdb.com/title/tt0052520/'),
    'IMDb URL accepted as IMDb ID'
);
assertSameValue(false, ThumbnailService::isValidImdb('../tt0052520'), 'Path accepted as IMDb ID');

echo "ThumbnailService tests passed\n";
