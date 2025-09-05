<?php

// prune-report-ip-log.php
// Prunes old timestamps from report-ip-log.json for rate limiting
// Run this via cron every 5 minutes

// forbid web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

$logFile = __DIR__ . '/report-ip-log.json';
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

file_put_contents($logFile, json_encode($ipLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
