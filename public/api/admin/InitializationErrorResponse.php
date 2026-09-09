<?php

declare(strict_types=1);

namespace FreeTV\Admin;

require_once __DIR__ . '/DatasetPackageExceptions.php';

final class InitializationErrorResponse
{
    /** @return array{http_status: int, payload: array{success: false, message: string, error_code?: string}} */
    public static function forException(\Throwable $exception): array
    {
        if ($exception instanceof DatasetPackageAvailabilityException) {
            return self::datasetFailure(
                503,
                'Current dataset is temporarily unavailable.',
                'dataset_unavailable'
            );
        }
        if ($exception instanceof DatasetPackageMetadataIntegrityException) {
            return self::datasetFailure(
                502,
                'Current dataset information could not be safely verified.',
                'dataset_metadata_invalid'
            );
        }
        if ($exception instanceof DatasetPackageIntegrityException) {
            return self::datasetFailure(
                500,
                'Dataset verification failed.',
                'dataset_integrity_failed'
            );
        }

        return [
            'http_status' => 500,
            'payload' => ['success' => false, 'message' => 'FreeTV initialization failed'],
        ];
    }

    /** @return array{http_status: int, payload: array{success: false, message: string, error_code: string}} */
    private static function datasetFailure(int $httpStatus, string $message, string $errorCode): array
    {
        return [
            'http_status' => $httpStatus,
            'payload' => [
                'success' => false,
                'message' => $message,
                'error_code' => $errorCode,
            ],
        ];
    }
}
