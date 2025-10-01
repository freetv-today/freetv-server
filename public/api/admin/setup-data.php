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

// Validate setup type strictly
$allowedTypes = ['official', 'sample', 'fresh'];
if (!in_array($setupType, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid setup type']);
    exit;
}

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
    global $publicDir, $logsDir;
    $zipUrl = 'https://github.com/freetv-today/freetv-data/archive/refs/heads/main.zip';
    $result = downloadAndExtractGitHubZip($zipUrl, $publicDir, 'freetv-data-main');
    if (!$result['success']) {
        throw new Exception($result['message']);
    }

    // Ensure logs/activity directory exists (even if empty in the repo)
    $activityDir = $logsDir . 'activity/';
    if (!is_dir($activityDir)) {
        mkdir($activityDir, 0755, true);
        fixWindowsPermissions($activityDir);
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
        fixWindowsPermissions($playlistsDir);
    }
    if (!is_dir($thumbsDir)) {
        mkdir($thumbsDir, 0755, true);
        fixWindowsPermissions($thumbsDir);
    }
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
        fixWindowsPermissions($logsDir);
    }

    // Ensure logs/activity directory exists (for analytics)
    $activityDir = $logsDir . 'activity/';
    if (!is_dir($activityDir)) {
        mkdir($activityDir, 0755, true);
        fixWindowsPermissions($activityDir);
    }

    // Generate current timestamp in the required format (2025-09-30T16:18:29.670Z)
    $currentTimestamp = gmdate('Y-m-d\TH:i:s') . '.' . sprintf('%03d', round(microtime(true) * 1000) % 1000) . 'Z';

    // Create minimal sample config.json
    $sampleConfig = [
        'lastupdated' => $currentTimestamp,
        'offline' => false,
        'appdata' => false,
        'collector' => '/api/beacon.php',
        'showads' => false,
        'modules' => true,
        'debugmode' => true
    ];
    file_put_contents($configFile, json_encode($sampleConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Create playlist index that points to default.json (fallback only)
    $indexData = [
        'default' => 'default.json',
        'playlists' => [
            [
                'filename' => 'default.json',
                'dbtitle' => 'Default Playlist',
                'lastupdated' => $currentTimestamp,
                'author' => 'Free TV'
            ]
        ]
    ];
    file_put_contents(
        $playlistsDir . 'index.json',
        json_encode($indexData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    // Create the actual default.json playlist file (fallback only)
    $defaultPlaylist = [
        'lastupdated' => $currentTimestamp,
        'dbtitle' => 'Default Playlist',
        'filename' => 'default.json',
        'dbversion' => '1.0',
        'author' => 'Free TV',
        'email' => 'support@freetv.today',
        'link' => 'https://freetv.today',
        'shows' => []
    ];
    file_put_contents(
        $playlistsDir . 'default.json',
        json_encode($defaultPlaylist, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    // Create empty logs structure (fallback only)
    $basicErrors = ['reports' => []];
    file_put_contents($logsDir . 'errors.json', json_encode($basicErrors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Create index.html files
    file_put_contents($thumbsDir . 'index.html', '<!-- Thumbnails directory -->');
    file_put_contents($logsDir . 'index.html', '<!-- Logs directory -->');
    file_put_contents($activityDir . 'index.html', '<!-- Activity logs directory -->');
}

function setupFreshData()
{
    global $playlistsDir, $thumbsDir, $logsDir, $configFile;

    // Ensure directories exist
    if (!is_dir($playlistsDir)) {
        mkdir($playlistsDir, 0755, true);
        fixWindowsPermissions($playlistsDir);
    }
    if (!is_dir($thumbsDir)) {
        mkdir($thumbsDir, 0755, true);
        fixWindowsPermissions($thumbsDir);
    }
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
        fixWindowsPermissions($logsDir);
    }

    // Ensure logs/activity directory exists (for analytics)
    $activityDir = $logsDir . 'activity/';
    if (!is_dir($activityDir)) {
        mkdir($activityDir, 0755, true);
        fixWindowsPermissions($activityDir);
    }

    // Generate current timestamp in the required format (2025-09-30T16:18:29.670Z)
    $currentTimestamp = gmdate('Y-m-d\TH:i:s') . '.' . sprintf('%03d', round(microtime(true) * 1000) % 1000) . 'Z';

    // Create basic config.json
    $freshConfig = [
        'lastupdated' => $currentTimestamp,
        'offline' => false,
        'appdata' => false,
        'collector' => '/api/beacon.php',
        'showads' => false,
        'modules' => true,
        'debugmode' => true
    ];
    file_put_contents($configFile, json_encode($freshConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Create playlist index that points to default.json
    $playlistIndex = [
        'default' => 'default.json',
        'playlists' => [
            [
                'filename' => 'default.json',
                'dbtitle' => 'Default Playlist',
                'lastupdated' => $currentTimestamp,
                'author' => 'Free TV'
            ]
        ]
    ];
    file_put_contents(
        $playlistsDir . 'index.json',
        json_encode($playlistIndex, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    // Create the actual default.json playlist file
    $defaultPlaylist = [
        'lastupdated' => $currentTimestamp,
        'dbtitle' => 'Default Playlist',
        'filename' => 'default.json',
        'dbversion' => '1.0',
        'author' => 'Free TV',
        'email' => 'support@freetv.today',
        'link' => 'https://freetv.today',
        'shows' => []
    ];
    file_put_contents(
        $playlistsDir . 'default.json',
        json_encode($defaultPlaylist, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    // Create empty logs structure
    $emptyErrors = ['reports' => []];
    file_put_contents($logsDir . 'errors.json', json_encode($emptyErrors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Create index.html files
    file_put_contents($thumbsDir . 'index.html', '<!-- Thumbnails directory -->');
    file_put_contents($logsDir . 'index.html', '<!-- Logs directory -->');
    file_put_contents($activityDir . 'index.html', '<!-- Activity logs directory -->');
}

/**
 * Download and extract GitHub repository ZIP file with security enhancements
 * @param string $zipUrl - GitHub ZIP download URL
 * @param string $extractToPath - Local path to extract to
 * @param string $rootFolderName - Name of root folder in ZIP (repo-name-branch)
 * @return array - Success/failure result
 */
function downloadAndExtractGitHubZip($zipUrl, $extractToPath, $rootFolderName)
{
    $tempZipPath = sys_get_temp_dir() . '/' . uniqid('freetv_', true) . '.zip';

    try {
        // Validate URL is from GitHub
        if (!preg_match('/^https:\/\/github\.com\/[a-zA-Z0-9\-_]+\/[a-zA-Z0-9\-_]+\/archive\//', $zipUrl)) {
            throw new Exception('Invalid repository URL - must be from GitHub');
        }

        // Download ZIP file with enhanced security
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $zipUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'FreeTV-Admin-Setup/1.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Reduced timeout
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3); // Limit redirects
        curl_setopt($ch, CURLOPT_MAXFILESIZE, 50 * 1024 * 1024); // 50MB limit

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

        // Check file size to prevent memory exhaustion
        if (strlen($zipContent) > 50 * 1024 * 1024) { // 50MB limit
            throw new Exception('Repository too large (>50MB)');
        }

        // Save ZIP temporarily
        if (file_put_contents($tempZipPath, $zipContent) === false) {
            throw new Exception("Failed to save ZIP file temporarily");
        }

        // Verify it's actually a ZIP file
        $zip = new ZipArchive();
        $zipResult = $zip->open($tempZipPath);
        if ($zipResult !== true) {
            unlink($tempZipPath);
            throw new Exception("Downloaded file is not a valid ZIP archive. Error code: $zipResult");
        }

        // Security: Check for directory traversal in ZIP entries
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (strpos($filename, '../') !== false || strpos($filename, '..\\') !== false) {
                $zip->close();
                unlink($tempZipPath);
                throw new Exception('ZIP contains unsafe file paths');
            }
        }

        // Create temporary extraction directory
        $tempExtractPath = sys_get_temp_dir() . '/' . uniqid('freetv_extract_', true);
        if (!mkdir($tempExtractPath, 0755, true)) {
            $zip->close();
            unlink($tempZipPath);
            throw new Exception("Failed to create temporary extraction directory");
        }

        // Extract to temp directory
        $extractResult = $zip->extractTo($tempExtractPath);
        $zip->close();

        if (!$extractResult) {
            removeDirectory($tempExtractPath);
            unlink($tempZipPath);
            throw new Exception("Failed to extract ZIP file");
        }

        // Verify expected root folder exists
        $extractedRootPath = $tempExtractPath . '/' . $rootFolderName;
        if (!is_dir($extractedRootPath)) {
            removeDirectory($tempExtractPath);
            unlink($tempZipPath);
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
        fixWindowsPermissions($destination);
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $sourcePath = $item->getPathname();
        $relativePath = substr($sourcePath, strlen($source) + 1);
        $target = $destination . DIRECTORY_SEPARATOR . $relativePath;

        // Skip Git metadata files during copying
        $filename = basename($sourcePath);
        if (in_array($filename, ['.gitkeep', '.gitignore', '.gitattributes', '.git'])) {
            continue;
        }

        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0755, true);
                fixWindowsPermissions($target);
            }
        } else {
            copy($item, $target);
            fixWindowsPermissions($target);
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

/**
 * Fix Windows permissions to allow normal user deletion
 * Uses pure PHP instead of exec() for security
 */
function fixWindowsPermissions($path)
{
    // Only attempt to fix permissions on Windows
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        try {
            if (is_dir($path)) {
                // Set directory permissions to be more permissive
                @chmod($path, 0777);

                // Recursively fix permissions for all contents
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $item) {
                    if ($item->isDir()) {
                        @chmod($item->getPathname(), 0777);
                    } else {
                        @chmod($item->getPathname(), 0666);
                    }
                }
            } else {
                // Single file
                @chmod($path, 0666);
            }
        } catch (Exception $e) {
            // Silently fail - permissions are nice-to-have, not critical
        }
    }
}
