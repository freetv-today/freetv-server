<?php

require_once __DIR__ . '/Authorization.php';
\FreeTV\Admin\requireRole('viewer');

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/Database.php';

use FreeTV\Admin\Database;

$filename = isset($_GET['file']) ? basename($_GET['file']) : null;
if (!$filename) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing file parameter']);
    exit;
}

if ($filename === 'index.json') {
    try {
        Database::init();

        $playlistRows = Database::table('playlists')
            ->select(['filename', 'dbtitle', 'lastupdated', 'author', 'email', 'link', 'is_default'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $default = null;
        $playlists = [];
        foreach ($playlistRows as $playlist) {
            if ($default === null && !empty($playlist->is_default)) {
                $default = $playlist->filename;
            }
            $playlists[] = [
                'filename' => $playlist->filename,
                'dbtitle' => $playlist->dbtitle,
                'lastupdated' => $playlist->lastupdated,
                'author' => $playlist->author,
                'email' => $playlist->email,
                'link' => $playlist->link,
            ];
        }
        if ($default === null && isset($playlists[0]['filename'])) {
            $default = $playlists[0]['filename'];
        }
        echo json_encode([
            'default' => $default,
            'playlists' => $playlists,
        ]);
        exit;
    } catch (Throwable $e) {
        error_log('Playlist Proxy Index Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to load playlists from database']);
        exit;
    }
}

if (pathinfo($filename, PATHINFO_EXTENSION) !== 'json') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type']);
    exit;
}

try {
    Database::init();

    $playlist = Database::table('playlists')
        ->where('filename', $filename)
        ->first([
            'id',
            'filename',
            'dbtitle',
            'dbversion',
            'author',
            'email',
            'link',
            'lastupdated',
            'is_default',
        ]);

    if (!$playlist) {
        http_response_code(404);
        echo json_encode(['error' => 'Playlist not found']);
        exit;
    }

    $showRows = Database::table('playlist_shows')
        ->where('playlist_id', $playlist->id)
        ->select([
            'category',
            'status',
            'identifier',
            'title',
            'description',
            'start_year',
            'end_year',
            'imdb',
            'group_name',
        ])
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get();

    $shows = [];
    foreach ($showRows as $show) {
        $showData = [
            'category' => $show->category,
            'status' => $show->status,
            'identifier' => $show->identifier,
            'title' => $show->title,
            'desc' => $show->description,
            'start' => $show->start_year,
            'end' => $show->end_year,
            'imdb' => $show->imdb,
        ];

        $group = is_string($show->group_name) ? trim($show->group_name) : '';
        if ($group !== '') {
            $showData['group'] = $group;
        }

        $shows[] = $showData;
    }

    echo json_encode([
        'filename' => $playlist->filename,
        'dbtitle' => $playlist->dbtitle,
        'dbversion' => $playlist->dbversion,
        'author' => $playlist->author,
        'email' => $playlist->email,
        'link' => $playlist->link,
        'lastupdated' => $playlist->lastupdated,
        'is_default' => (bool) $playlist->is_default,
        'shows' => $shows,
    ]);
} catch (Throwable $e) {
    error_log('Playlist Proxy Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load playlist from database']);
}
