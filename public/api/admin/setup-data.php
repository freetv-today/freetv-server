<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$setupType = $input['type'] ?? '';
$publicDir = __DIR__ . '/../../';
$playlistsDir = $publicDir . 'playlists/';
$thumbsDir = $publicDir . 'thumbs/';
$configFile = $publicDir . 'config.json';
try {
    switch ($setupType) {
        case 'official':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              setupOfficialData();

            break;
        case 'sample':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              setupSampleData();

            break;
        case 'fresh':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              setupFreshData();

            break;
        default:
            throw new Exception('Invalid setup type');
    }

    echo json_encode(['success' => true, 'message' => 'Data setup completed successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function setupOfficialData()
{

    global $publicDir;
    $zipUrl = 'https://github.com/freetv-today/freetv-data/archive/refs/heads/main.zip';
    $result = downloadAndExtractGitHubZip($zipUrl, $publicDir, 'freetv-data-main');
    if (!$result['success']) {
        throw new Exception($result['message']);
    }
}

function setupSampleData()
{

    global $publicDir;
// Use GitHub's automatic ZIP download for sample data repo
    $zipUrl = 'https://github.com/freetv-today/freetv-sampledata/archive/refs/heads/main.zip';
    $result = downloadAndExtractGitHubZip($zipUrl, $publicDir, 'freetv-sampledata-main');
    if (!$result['success']) {
    // Fallback to creating inline sample data if repo doesn't exist yet
        createInlineSampleData();
    }
}

function createInlineSampleData() {
    global $playlistsDir, $thumbsDir, $configFile;
// Ensure directories exist
if (!is_dir($playlistsDir)) {
    mkdir($playlistsDir, 0755, true);
}
if (!is_dir($thumbsDir)) {
    mkdir($thumbsDir, 0755, true);
}

function createInlineSampleData()
{

    global $playlistsDir, $thumbsDir, $configFile;
// Ensure directories exist
    if (!is_dir($playlistsDir)) {
        mkdir($playlistsDir, 0755, true);
    }
    if (!is_dir($thumbsDir)) {
        mkdir($thumbsDir, 0755, true);
    }

    // Create sample config.json
    $sampleConfig = [
        'lastupdated' => date('c'),
        'offline' => false,
        'appdata' => false,
        'collector' => 'https://freetv.today/api/beacon.php',
        'showads' => false,
        'modules' => true,
        'debugmode' => true
    ];
    file_put_contents($configFile, json_encode($sampleConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
// Create sample playlist index
    $indexData = [
        'default' => 'sample-movies.json',
        'playlists' => [
            [
                'filename' => 'sample-movies.json',
                'dbtitle' => 'Sample Movies',
                'category' => 'movies'
            ],
            [
                'filename' => 'sample-classics.json',
                'dbtitle' => 'Sample Classics',
                'category' => 'classics'
            ]
        ]
    ];
    file_put_contents($playlistsDir . 'index.json', json_encode($indexData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
// Create sample movie playlist
    $sampleMovies = [
        'title' => 'Sample Movies',
        'category' => 'movies',
        'shows' => [
            [
                'title' => 'Sample Movie 1',
                'category' => 'Action',
                'desc' => 'This is a sample movie for testing the admin dashboard.',
                'start' => '1990',
                'end' => '1990',
                'imdb' => 'tt0000001',
                'identifier' => 'sample1',
                'status' => 'enabled'
            ],
            [
                'title' => 'Sample Movie 2',
                'category' => 'Drama',
                'desc' => 'Another sample movie for testing purposes.',
                'start' => '1995',
                'end' => '1995',
                'imdb' => 'tt0000002',
                'identifier' => 'sample2',
                'status' => 'enabled'
            ],
            [
                'title' => 'Sample Movie 3',
                'category' => 'Comedy',
                'desc' => 'A third sample movie for testing.',
                'start' => '2000',
                'end' => '2000',
                'imdb' => 'tt0000003',
                'identifier' => 'sample3',
                'status' => 'disabled'
            ]
        ]
    ];
    file_put_contents($playlistsDir . 'sample-movies.json', json_encode($sampleMovies, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
// Create sample classics playlist
    $sampleClassics = [
        'title' => 'Sample Classics',
        'category' => 'classics',
        'shows' => [
            [
                'title' => 'Classic Film 1',
                'category' => 'Western',
                'desc' => 'A classic western film for testing.',
                'start' => '1950',
                'end' => '1950',
                'imdb' => 'tt0000004',
                'identifier' => 'classic1',
                'status' => 'enabled'
            ],
            [
                'title' => 'Classic Film 2',
                'category' => 'Romance',
                'desc' => 'A classic romance for testing.',
                'start' => '1955',
                'end' => '1955',
                'imdb' => 'tt0000005',
                'identifier' => 'classic2',
                'status' => 'enabled'
            ]
        ]
    ];
    file_put_contents($playlistsDir . 'sample-classics.json', json_encode($sampleClassics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
// Create thumbs index.html
    file_put_contents($thumbsDir . 'index.html', '<!-- Thumbnails directory -->');
}

function setupFreshData()
{

    global $playlistsDir, $thumbsDir, $configFile;
// Ensure directories exist
    if (!is_dir($playlistsDir)) {
        mkdir($playlistsDir, 0755, true);
    }
    if (!is_dir($thumbsDir)) {
        mkdir($thumbsDir, 0755, true);
    }

    // Create basic config.json
    $freshConfig = [
        'lastupdated' => date('c'),
        'offline' => false,
        'appdata' => false,
        'collector' => '',
        'showads' => false,
        'modules' => true,
        'debugmode' => true
    ];
    file_put_contents($configFile, json_encode($freshConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
// Create empty playlist index
    $emptyIndex = [
        'default' => '',
        'playlists' => []
    ];
    file_put_contents($playlistsDir . 'index.json', json_encode($emptyIndex, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
// Create thumbs index.html
    file_put_contents($thumbsDir . 'index.html', '<!-- Thumbnails directory -->');
}

/**
 * Download a single file from GitHub API
 */
function downloadFileFromGitHub($apiUrl, $localPath)
{

    $response = makeGitHubApiRequest($apiUrl);
    if ($response['type'] !== 'file') {
        throw new Exception('Expected file but got: ' . $response['type']);
    }

    // GitHub returns base64 encoded content for files
    $content = base64_decode($response['content']);
// Ensure directory exists
    $dir = dirname($localPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    if (file_put_contents($localPath, $content) === false) {
        throw new Exception('Failed to write file: ' . $localPath);
    }
}

/**
 * Download a directory and all its contents from GitHub API
 */
function downloadDirectoryFromGitHub($apiUrl, $localDir)
{

    $response = makeGitHubApiRequest($apiUrl);
    if (!is_array($response)) {
        throw new Exception('Expected directory listing but got single item');
    }

    foreach ($response as $item) {
        $localPath = $localDir . basename($item['name']);
        if ($item['type'] === 'file') {
        // Skip index.html files - we'll create our own
            if ($item['name'] === 'index.html') {
                continue;
            }

            // Download the file
            downloadFileFromGitHub($item['url'], $localPath);
        } elseif ($item['type'] === 'dir') {
        // Recursively download subdirectory
            if (!is_dir($localPath)) {
                mkdir($localPath, 0755, true);
            }
            downloadDirectoryFromGitHub($item['url'], $localPath . '/');
        }
    }

    // Create index.html for the directory
    file_put_contents($localDir . 'index.html', '<!-- Directory index -->');
}

/**
 * Make HTTP request to GitHub API with proper headers
 */
function makeGitHubApiRequest($url)
{

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'FreeTv-Admin-Dashboard/1.0');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/vnd.github.v3+json',
        'User-Agent: FreeTv-Admin-Dashboard/1.0'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($curlError) {
        throw new Exception('cURL error: ' . $curlError);
    }

    if ($httpCode !== 200) {
        throw new Exception('GitHub API error: HTTP ' . $httpCode . ' - ' . $response);
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON response from GitHub API');
    }

    return $data;
}

/**
 * Download and extract GitHub repository ZIP file
 * @param string $zipUrl - GitHub ZIP download URL
 * @param string $extractToPath - Local path to extract to
 * @param string $rootFolderName - Name of root folder in ZIP (repo-name-branch)
 * @return array - Success/failure result
 */
function downloadAndExtractGitHubZip($zipUrl, $extractToPath, $rootFolderName)
{

    $tempZipPath = sys_get_temp_dir() . '/' . uniqid('freetv_', true) . '.zip';
    try {
    // Download ZIP file
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $zipUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'FreeTV-Admin-Setup/1.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    // 5 minute timeout

        $zipContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($curlError) {
            throw new Exception("cURL error: $curlError");
        }

        if ($httpCode !== 200) {
            throw new Exception("Failed to download ZIP. HTTP: $httpCode");
        }

        if (!$zipContent) {
            throw new Exception("Empty ZIP file received");
        }

        // Save ZIP temporarily
        if (file_put_contents($tempZipPath, $zipContent) === false) {
            throw new Exception("Failed to save ZIP file temporarily");
        }

        // Extract ZIP
        $zip = new ZipArchive();
        $zipResult = $zip->open($tempZipPath);
        if ($zipResult !== true) {
            throw new Exception("Failed to open ZIP file. Error code: $zipResult");
        }

        // Create temporary extraction directory
        $tempExtractPath = sys_get_temp_dir() . '/' . uniqid('extract_', true);
        if (!mkdir($tempExtractPath, 0755, true)) {
            throw new Exception("Failed to create temporary extraction directory");
        }

        // Extract to temp directory
        $extractResult = $zip->extractTo($tempExtractPath);
        $zip->close();
        if (!$extractResult) {
            throw new Exception("Failed to extract ZIP file");
        }

        // Move files from extracted root folder to target location
        $extractedRootPath = $tempExtractPath . '/' . $rootFolderName;
        if (!is_dir($extractedRootPath)) {
            throw new Exception("Expected root folder '$rootFolderName' not found in ZIP");
        }

        // Ensure target directory exists
        if (!is_dir($extractToPath)) {
            mkdir($extractToPath, 0755, true);
        }

        // Copy files from extracted folder to target location
        copyDirectory($extractedRootPath, $extractToPath);
    // Cleanup
        unlink($tempZipPath);
        removeDirectory($tempExtractPath);
        return ['success' => true, 'message' => 'Repository downloaded and extracted successfully'];
    } catch (Exception $e) {
    // Cleanup on error
        if (file_exists($tempZipPath)) {
            unlink($tempZipPath);
        }
        if (isset($tempExtractPath) && is_dir($tempExtractPath)) {
            removeDirectory($tempExtractPath);
        }

        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Recursively copy directory contents
 */
function copyDirectory($source, $destination)
{

    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $item) {
        $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0755, true);
            }
        } else {
            copy($item, $target);
        }
    }
}

/**
 * Recursively remove directory and contents
 */
function removeDirectory($dir)
{

    if (!is_dir($dir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getRealPath());
        } else {
            unlink($item->getRealPath());
        }
    }

    rmdir($dir);
}
