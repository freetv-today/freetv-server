<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/Settings.php';
require_once __DIR__ . '/../../../public/api/admin/publication/PublicationTimestamp.php';
require_once __DIR__ . '/../../../public/api/admin/publication/ConfigPublicationSerializer.php';

use FreeTV\Admin\Publication\ConfigPublicationSerializer;
use FreeTV\Admin\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigPublicationSerializerTest extends TestCase
{
    #[DataProvider('timestampProvider')]
    public function testSerializesExactPublicConfigWithCanonicalUtcTimestamp(
        DateTimeInterface|string $timestamp,
        string $expected
    ): void {
        self::assertSame(
            ['lastupdated' => $expected, 'show_ads' => true],
            ConfigPublicationSerializer::serialize([
                'show_ads' => true,
                'offline' => true,
                'appdata' => true,
                'collector' => '/api/beacon.php',
                'modules' => true,
                'debugmode' => true,
                'showads' => true,
            ], $timestamp)
        );
    }

    public static function timestampProvider(): array
    {
        return [
            'offset DateTimeImmutable' => [
                new DateTimeImmutable('2026-08-12 15:30:00.123456-04:00'),
                '2026-08-12T19:30:00.123Z',
            ],
            'offset string' => ['2026-08-12T21:30:00.987+02:00', '2026-08-12T19:30:00.987Z'],
            'canonical UTC string' => ['2026-08-12T19:30:00.000Z', '2026-08-12T19:30:00.000Z'],
        ];
    }

    #[DataProvider('booleanProvider')]
    public function testPreservesBooleanSettingsAndJsonTypes(bool $value): void
    {
        $artifact = ConfigPublicationSerializer::serialize(
            ['show_ads' => $value],
            '2026-08-12T19:30:00.000Z'
        );

        self::assertSame([
            'lastupdated' => '2026-08-12T19:30:00.000Z',
            'show_ads' => $value,
        ], $artifact);
        self::assertSame(
            '{"lastupdated":"2026-08-12T19:30:00.000Z","show_ads":'
                . ($value ? 'true' : 'false') . '}',
            json_encode($artifact, JSON_THROW_ON_ERROR)
        );
    }

    public static function booleanProvider(): array
    {
        return ['true' => [true], 'false' => [false]];
    }

    public function testUsesSettingsRowsAndRegisteredDefault(): void
    {
        self::assertSame(
            ['lastupdated' => '2026-08-12T19:30:00.000Z', 'show_ads' => true],
            ConfigPublicationSerializer::serialize(
                Settings::fromRows([['setting_key' => 'show_ads', 'setting_value' => 'true']]),
                '2026-08-12T19:30:00.000Z'
            )
        );
        self::assertSame(
            ['lastupdated' => '2026-08-12T19:30:00.000Z', 'show_ads' => false],
            ConfigPublicationSerializer::serialize(
                Settings::fromRows([['setting_key' => 'show_ads', 'setting_value' => 'false']]),
                '2026-08-12T19:30:00.000Z'
            )
        );
        self::assertSame(
            ['lastupdated' => '2026-08-12T19:30:00.000Z', 'show_ads' => false],
            ConfigPublicationSerializer::serialize(
                Settings::fromRows([]),
                '2026-08-12T19:30:00.000Z'
            )
        );
    }

    #[DataProvider('invalidTimestampProvider')]
    public function testRejectsInvalidTimestamps(string $timestamp): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid publication timestamp');
        ConfigPublicationSerializer::serialize(['show_ads' => false], $timestamp);
    }

    public static function invalidTimestampProvider(): array
    {
        return ['empty' => [''], 'whitespace' => ['   '], 'malformed' => ['not-a-timestamp']];
    }

    #[DataProvider('invalidSettingProvider')]
    public function testRejectsInvalidPublishableSettingValues(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Setting show_ads must be boolean');
        ConfigPublicationSerializer::serialize(
            ['show_ads' => $value],
            '2026-08-12T19:30:00.000Z'
        );
    }

    public static function invalidSettingProvider(): array
    {
        return [
            'string true' => ['true'],
            'integer one' => [1],
            'null' => [null],
            'array' => [[]],
        ];
    }
}
