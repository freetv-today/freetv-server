<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/InitializationErrorResponse.php';

use FreeTV\Admin\DatasetPackageAvailabilityException;
use FreeTV\Admin\DatasetPackageIntegrityException;
use FreeTV\Admin\DatasetPackageMetadataAvailabilityException;
use FreeTV\Admin\DatasetPackageMetadataIntegrityException;
use FreeTV\Admin\InitializationErrorResponse;

function initializationErrorAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
        );
    }
}

$internalDetails = 'secret filesystem path /private/data.zip SHA deadbeef SQL SELECT * FROM users';
$cases = [
    [new DatasetPackageAvailabilityException($internalDetails), 503, 'dataset_unavailable'],
    [new DatasetPackageMetadataAvailabilityException($internalDetails), 503, 'dataset_unavailable'],
    [new DatasetPackageMetadataIntegrityException($internalDetails), 502, 'dataset_metadata_invalid'],
    [new DatasetPackageIntegrityException($internalDetails), 500, 'dataset_integrity_failed'],
];

foreach ($cases as [$exception, $status, $errorCode]) {
    $exceptionClass = $exception::class;
    $response = InitializationErrorResponse::forException($exception);
    initializationErrorAssertSame($status, $response['http_status'],
        "{$exceptionClass} used the wrong HTTP status");
    initializationErrorAssertSame(false, $response['payload']['success'],
        "{$exceptionClass} produced a successful response");
    initializationErrorAssertSame($errorCode, $response['payload']['error_code'],
        "{$exceptionClass} used the wrong public error code");
    initializationErrorAssertSame(false, str_contains(json_encode($response['payload']), $internalDetails),
        "{$exceptionClass} exposed internal exception details");
}

$unexpected = InitializationErrorResponse::forException(new RuntimeException($internalDetails));
initializationErrorAssertSame(500, $unexpected['http_status'],
    'Unexpected initialization exception used the wrong HTTP status');
initializationErrorAssertSame(
    ['success' => false, 'message' => 'FreeTV initialization failed'],
    $unexpected['payload'],
    'Unexpected initialization exception did not retain the generic public response'
);

$missingBaseline = InitializationErrorResponse::forException(
    new RuntimeException('Bundled Baseline Sample package is missing: /private/path')
);
initializationErrorAssertSame(false, isset($missingBaseline['payload']['error_code']),
    'Missing local Baseline package was classified as remote availability');

$invalidBaseline = InitializationErrorResponse::forException(
    new DatasetPackageIntegrityException('Bundled Baseline package validation failed internally')
);
initializationErrorAssertSame('dataset_integrity_failed', $invalidBaseline['payload']['error_code'],
    'Invalid Baseline package was not treated as an integrity failure');
initializationErrorAssertSame(false, $invalidBaseline['payload']['error_code'] === 'dataset_unavailable',
    'Invalid Baseline package was classified as remote availability');

fwrite(STDOUT, "InitializationErrorResponseTest passed\n");
