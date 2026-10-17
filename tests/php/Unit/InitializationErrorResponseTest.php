<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/InitializationErrorResponse.php';

use FreeTV\Admin\DatasetPackageAvailabilityException;
use FreeTV\Admin\DatasetPackageIntegrityException;
use FreeTV\Admin\DatasetPackageMetadataAvailabilityException;
use FreeTV\Admin\DatasetPackageMetadataIntegrityException;
use FreeTV\Admin\InitializationErrorResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InitializationErrorResponseTest extends TestCase
{
    #[DataProvider('datasetFailureProvider')]
    public function testMapsDatasetFailuresToExactSafePublicResponses(
        Throwable $exception,
        array $expected
    ): void {
        self::assertSame($expected, InitializationErrorResponse::forException($exception));
        $encoded = json_encode($expected, JSON_THROW_ON_ERROR);
        foreach (['/synthetic/private/package.zip', 'SELECT * FROM users', 'fixture_user:fixture_password', str_repeat('d', 64)] as $secret) {
            self::assertStringNotContainsString($secret, $encoded);
        }
    }

    public static function datasetFailureProvider(): array
    {
        $details = '/synthetic/private/package.zip SELECT * FROM users fixture_user:fixture_password '
            . str_repeat('d', 64);
        return [
            'package availability' => [new DatasetPackageAvailabilityException($details), [
                'http_status' => 503,
                'payload' => ['success' => false, 'message' => 'Current dataset is temporarily unavailable.', 'error_code' => 'dataset_unavailable'],
            ]],
            'metadata availability' => [new DatasetPackageMetadataAvailabilityException($details), [
                'http_status' => 503,
                'payload' => ['success' => false, 'message' => 'Current dataset is temporarily unavailable.', 'error_code' => 'dataset_unavailable'],
            ]],
            'metadata integrity' => [new DatasetPackageMetadataIntegrityException($details), [
                'http_status' => 502,
                'payload' => ['success' => false, 'message' => 'Current dataset information could not be safely verified.', 'error_code' => 'dataset_metadata_invalid'],
            ]],
            'package integrity' => [new DatasetPackageIntegrityException($details), [
                'http_status' => 500,
                'payload' => ['success' => false, 'message' => 'Dataset verification failed.', 'error_code' => 'dataset_integrity_failed'],
            ]],
        ];
    }

    #[DataProvider('unexpectedProvider')]
    public function testUnexpectedThrowablesUseGenericResponse(Throwable $exception): void
    {
        self::assertSame([
            'http_status' => 500,
            'payload' => ['success' => false, 'message' => 'FreeTV initialization failed'],
        ], InitializationErrorResponse::forException($exception));
    }

    public static function unexpectedProvider(): array
    {
        return [
            'runtime exception' => [new RuntimeException('/synthetic/private failure')],
            'logic exception' => [new LogicException('SELECT secret FROM users')],
            'error' => [new Error('fixture credential leaked')],
        ];
    }

    public function testMissingLocalBaselineIsNotRemoteAvailabilityFailure(): void
    {
        $response = InitializationErrorResponse::forException(
            new RuntimeException('Bundled Baseline Sample package is missing: /synthetic/private/path')
        );
        self::assertSame(500, $response['http_status']);
        self::assertArrayNotHasKey('error_code', $response['payload']);
    }

    public function testInvalidBaselineRemainsIntegrityFailureNotAvailabilityFailure(): void
    {
        $response = InitializationErrorResponse::forException(
            new DatasetPackageIntegrityException('Bundled Baseline validation failed internally')
        );
        self::assertSame(500, $response['http_status']);
        self::assertSame('dataset_integrity_failed', $response['payload']['error_code']);
        self::assertNotSame('dataset_unavailable', $response['payload']['error_code']);
    }
}
