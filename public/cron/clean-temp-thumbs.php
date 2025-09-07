<?php

// clean-temp-thumbs.php
// Deletes .jpg files in /public/temp/ older than 7 days
// Run via cron (recommended: weekly)

// Forbid web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
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
