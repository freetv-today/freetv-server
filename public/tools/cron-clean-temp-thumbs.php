<?php

// cron-clean-temp-thumbs.php
// Deletes .jpg files in /public/temp/ older than 7 days
// Run via cron (recommended: weekly)

// Secret key for web access
$SECRET_KEY = '12345678'; // Change this to your own secret key
if (php_sapi_name() !== 'cli') {
    if (!isset($_GET['key']) || $_GET['key'] !== $SECRET_KEY) {
        http_response_code(403);
        exit('Forbidden: Invalid or missing key');
    }
}

$dir = __DIR__ . '/../temp';
$days = 7;
$now = time();
$deleted = 0;
if (!is_dir($dir)) {
    exit("Temp directory not found: $dir\n");
}

$files = glob($dir . '/*.jpg');
foreach ($files as $file) {
    if (is_file($file)) {
        $fileAge = $now - filemtime($file);
        if ($fileAge > ($days * 86400)) {
            if (@unlink($file)) {
                $deleted++;
            }
        }
    }
}

echo "Deleted $deleted .jpg files older than $days days from $dir\n";
