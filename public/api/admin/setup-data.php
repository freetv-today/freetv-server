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
$logsDir = $publicDir . 'logs/';
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
        // Fallback to creating basic structure if repo doesn't exist yet
        createFallbackSampleData();
    }
}

function createFallbackSampleData()
{
    global $playlistsDir, $thumbsDir, $logsDir, $configFile;

    // Ensure directories exist
    if (!is_dir($playlistsDir)) {
        mkdir($playlistsDir, 0755, true);
    }
    if (!is_dir($thumbsDir)) {
        mkdir($thumbsDir, 0755, true);
    }
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
    }

    // Create minimal sample config.json
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

    // Create minimal playlist index (fallback only)
    $indexData = [
        'default' => 'sample-movies.json',
        'playlists' => [
            [
                'filename' => 'sample-movies.json',
                'dbtitle' => 'Sample Movies',
                'category' => 'movies'
            ]
        ]
    ];
    file_put_contents(
        $playlistsDir . 'index.json',
        json_encode($indexData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    // Create minimal sample playlist (fallback only)
    $sampleMovies = [
        'title' => 'Sample Movies',
        'category' => 'movies',
        'shows' => [
            [
                'title' => 'Sample Movie',
                'category' => 'Action',
                'desc' => 'Basic sample for testing. Please check freetv-sampledata repo for full dataset.',
                'start' => '1990',
                'end' => '1990',
                'imdb' => 'tt0000001',
                'identifier' => 'sample1',
                'status' => 'enabled'
            ]
        ]
    ];
    file_put_contents(
        $playlistsDir . 'sample-movies.json',
        json_encode($sampleMovies, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    // Create minimal logs structure (fallback only)
    $basicErrors = [
        'reports' => [
            [
                'playlist' => 'sample-movies.json',
                'title' => 'Sample Problem Report',
                'category' => 'action',
                'identifier' => 'sample-problem',
                'imdb' => 'tt0000001',
                'date' => date('c'),
                'reportingIps' => ['127.0.0.1'],
                'reportCount' => 1,
                'status' => 'reported'
            ]
        ]
    ];
    file_put_contents($logsDir . 'errors.json', json_encode($basicErrors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Create index.html files
    file_put_contents($thumbsDir . 'index.html', '<!-- Thumbnails directory -->');
    file_put_contents($logsDir . 'index.html', '<!-- Logs directory -->');
}

function setupFreshData()
{
    global $playlistsDir, $thumbsDir, $logsDir, $configFile;

    // Ensure directories exist
    if (!is_dir($playlistsDir)) {
        mkdir($playlistsDir, 0755, true);
    }
    if (!is_dir($thumbsDir)) {
        mkdir($thumbsDir, 0755, true);
    }
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
    }

    // Create basic config.json
    $freshConfig = [
        'lastupdated' => date('c'),
        'offline' => false,
        'appdata' => false,
        'collector' => '/api/beacon.php',
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
    file_put_contents(
        $playlistsDir . 'index.json',
        json_encode($emptyIndex, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    // Create empty logs structure
    $emptyErrors = ['reports' => []];
    file_put_contents($logsDir . 'errors.json', json_encode($emptyErrors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Create index.html files
    file_put_contents($thumbsDir . 'index.html', '<!-- Thumbnails directory -->');
    file_put_contents($logsDir . 'index.html', '<!-- Logs directory -->');
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minute timeout

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
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $sourcePath = $item->getPathname();
        $relativePath = substr($sourcePath, strlen($source) + 1);
        $target = $destination . DIRECTORY_SEPARATOR . $relativePath;
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
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getRealPath());
        } else {
            unlink($item->getRealPath());
        }
    }
    rmdir($dir);
}
