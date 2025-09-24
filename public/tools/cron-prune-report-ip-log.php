<?php

// cron-prune-report-ip-log.php
// Prunes old timestamps from report-ip-log.json for rate limiting
// Run this via cron every 5 minutes

// Secret key for web access
$SECRET_KEY = '12345678'; // Change this to your own secret key
if (php_sapi_name() !== 'cli') {
    if (!isset($_GET['key']) || $_GET['key'] !== $SECRET_KEY) {
        http_response_code(403);
        exit('Forbidden: Invalid or missing key');
    }
}

$logFile = __DIR__ . '/../logs/report-ip-log.json';
$window = 300;
// 5 minutes in seconds
$now = time();
if (!file_exists($logFile)) {
    exit;
}

$ipLog = json_decode(file_get_contents($logFile), true);
if (!is_array($ipLog) || !isset($ipLog['ips'])) {
    exit;
}

$before = count($ipLog['ips']);
foreach ($ipLog['ips'] as $ip => $timestamps) {
    // Keep only timestamps within the last 5 minutes
    $ipLog['ips'][$ip] = array_values(array_filter($timestamps, function ($ts) use ($now, $window) {
        return (strtotime($ts) > ($now - $window));
    }));
    // Remove IP if no recent timestamps remain
    if (empty($ipLog['ips'][$ip])) {
        unset($ipLog['ips'][$ip]);
    }
}
$after = count($ipLog['ips']);
if ($after > 0) {
    file_put_contents($logFile, json_encode($ipLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
echo "There were $before IP addresses before, and now there are $after IPs remaining.\n";
