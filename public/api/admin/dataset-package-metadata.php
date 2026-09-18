<?php

declare(strict_types=1);

use FreeTV\Admin\DatasetPackageMetadata;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function datasetPackageMetadataRespond(int $httpStatus, array $payload): void
{
    http_response_code($httpStatus);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    datasetPackageMetadataRespond(405, ['success' => false, 'message' => 'Method not allowed']);
}

try {
    $autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
    if (!is_file($autoloadPath)) {
        throw new RuntimeException('PHP dependencies are unavailable');
    }
    require_once $autoloadPath;
    require_once __DIR__ . '/DatasetPackageMetadata.php';
    $appRoot = dirname(__DIR__, 3);
    datasetPackageMetadataRespond(200, DatasetPackageMetadata::fromRuntime($appRoot)->value());
} catch (Throwable $exception) {
    error_log('Dataset Package Metadata Error: ' . $exception->getMessage());
    datasetPackageMetadataRespond(503, [
        'success' => false,
        'message' => 'Dataset package metadata is unavailable',
    ]);
}
