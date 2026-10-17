<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/DatasetPackageMetadata.php';

use FreeTV\Admin\DatasetPackageMetadata;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DatasetPackageMetadataTest extends TestCase
{
    private const SAMPLE_URL = 'https://downloads.example.test/current/sample.zip';
    private const OFFICIAL_URL = 'https://downloads.example.test/current/official.zip';
    private const SAMPLE_SHA = '1a0bcdef22222222222222222222222222222222222222222222222222222222';
    private const OFFICIAL_SHA = '3333333333333333333333333333333333333333333333333333333333333333';

    public function testExactMetadataDocumentIsGenerated(): void
    {
        self::assertSame($this->expected(), $this->metadata($this->values())->value());
        self::assertSame(1, $this->metadata($this->values())->value()['format_version']);
    }

    #[DataProvider('invalidValueProvider')]
    public function testInvalidMetadataValuesAreRejected(string $name, ?string $value, bool $remove): void
    {
        $values = $this->values();
        if ($remove) { unset($values[$name]); } else { $values[$name] = $value; }
        $this->expectException(InvalidArgumentException::class);
        $this->metadata($values)->value();
    }

    public static function invalidValueProvider(): array
    {
        return [
            'missing sample URL' => ['FREETV_SAMPLE_DATA_URL', null, true],
            'missing sample digest' => ['FREETV_SAMPLE_DATA_SHA256', null, true],
            'missing official URL' => ['FREETV_OFFICIAL_DATA_URL', null, true],
            'missing official digest' => ['FREETV_OFFICIAL_DATA_SHA256', null, true],
            'empty' => ['FREETV_SAMPLE_DATA_URL', '', false],
            'HTTP URL' => ['FREETV_SAMPLE_DATA_URL', 'http://example.test/sample.zip', false],
            'malformed URL' => ['FREETV_SAMPLE_DATA_URL', 'not a URL', false],
            'credential URL' => ['FREETV_OFFICIAL_DATA_URL', 'https://user:secret@example.test/official.zip', false],
            'short digest' => ['FREETV_SAMPLE_DATA_SHA256', 'aaaaaaaa', false],
            'long digest' => ['FREETV_SAMPLE_DATA_SHA256', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', false],
            'uppercase digest' => ['FREETV_SAMPLE_DATA_SHA256', 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA', false],
            'nonhex digest' => ['FREETV_OFFICIAL_DATA_SHA256', 'zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz', false],
        ];
    }

    public function testUnauthenticatedGetReturnsExactDocument(): void
    {
        [$exit, $status, $body] = $this->endpoint('GET', $this->values());
        self::assertSame(0, $exit);
        self::assertSame(200, $status);
        self::assertSame($this->expected(), json_decode($body, true, 512, JSON_THROW_ON_ERROR));
    }

    public function testInvalidRuntimeMetadataReturnsSanitized503(): void
    {
        $secret = 'http://do-not-expose.invalid/private.zip';
        [$exit, $status, $body] = $this->endpoint('GET', array_replace($this->values(), ['FREETV_SAMPLE_DATA_URL' => $secret]));
        self::assertSame(0, $exit);
        self::assertSame(503, $status);
        self::assertSame(['success' => false, 'message' => 'Dataset package metadata is unavailable'], json_decode($body, true, 512, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($secret, $body);
    }

    public function testPostReturnsStable405(): void
    {
        [$exit, $status, $body] = $this->endpoint('POST', $this->values());
        self::assertSame(0, $exit);
        self::assertSame(405, $status);
        self::assertSame(['success' => false, 'message' => 'Method not allowed'], json_decode($body, true, 512, JSON_THROW_ON_ERROR));
    }

    private function values(): array
    {
        return ['FREETV_SAMPLE_DATA_URL' => self::SAMPLE_URL, 'FREETV_SAMPLE_DATA_SHA256' => self::SAMPLE_SHA, 'FREETV_OFFICIAL_DATA_URL' => self::OFFICIAL_URL, 'FREETV_OFFICIAL_DATA_SHA256' => self::OFFICIAL_SHA];
    }
    private function expected(): array
    {
        return ['format_version' => 1, 'sample' => ['url' => self::SAMPLE_URL, 'sha256' => self::SAMPLE_SHA], 'official' => ['url' => self::OFFICIAL_URL, 'sha256' => self::OFFICIAL_SHA]];
    }
    private function metadata(array $values): DatasetPackageMetadata
    {
        return new DatasetPackageMetadata(static fn(string $name): array => array_key_exists($name, $values) ? ['configured' => true, 'value' => $values[$name]] : ['configured' => false, 'value' => '']);
    }
    private function endpoint(string $method, array $values): array
    {
        $endpoint = realpath(__DIR__ . '/../../../public/api/admin/dataset-package-metadata.php');
        $code = '$_SERVER["REQUEST_METHOD"]=' . var_export($method, true) . ';putenv("DB_HOST=metadata-test.invalid");register_shutdown_function(static function():void{fwrite(STDERR,"HTTP_STATUS=".http_response_code());});';
        foreach ($this->values() as $name => $_) {
            $value = $values[$name] ?? '';
            $code .= 'putenv(' . var_export($name . '=' . $value, true) . ');$_ENV[' . var_export($name, true) . ']=' . var_export($value, true) . ';';
        }
        $code .= 'require ' . var_export($endpoint, true) . ';';
        $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $exit = proc_close($process);
        preg_match('/HTTP_STATUS=(\d+)/', $stderr, $match);
        return [$exit, (int) ($match[1] ?? 0), $stdout, $stderr];
    }
}
