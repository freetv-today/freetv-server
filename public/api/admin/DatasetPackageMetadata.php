<?php

declare(strict_types=1);

namespace FreeTV\Admin;

require_once __DIR__ . '/RuntimeEnvironment.php';

final class DatasetPackageMetadata
{
    private const VARIABLES = [
        'sample' => [
            'url' => 'FREETV_SAMPLE_DATA_URL',
            'sha256' => 'FREETV_SAMPLE_DATA_SHA256',
        ],
        'official' => [
            'url' => 'FREETV_OFFICIAL_DATA_URL',
            'sha256' => 'FREETV_OFFICIAL_DATA_SHA256',
        ],
    ];

    private $valueReader;

    public function __construct(callable $valueReader)
    {
        $this->valueReader = $valueReader;
    }

    public static function fromRuntime(string $appRoot): self
    {
        return new self(
            static fn(string $name): array => RuntimeEnvironment::configuredValue($name, $appRoot)
        );
    }

    /**
     * @return array{
     *   format_version: int,
     *   sample: array{url: string, sha256: string},
     *   official: array{url: string, sha256: string}
     * }
     */
    public function value(): array
    {
        $metadata = ['format_version' => 1];
        foreach (self::VARIABLES as $dataset => $variables) {
            $url = $this->requiredValue($variables['url']);
            $sha256 = $this->requiredValue($variables['sha256']);
            $this->requireHttpsUrl($url, $variables['url']);
            if (preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1) {
                throw new \InvalidArgumentException("{$variables['sha256']} must be a lowercase SHA-256 digest");
            }
            $metadata[$dataset] = ['url' => $url, 'sha256' => $sha256];
        }
        return $metadata;
    }

    private function requiredValue(string $name): string
    {
        $configured = ($this->valueReader)($name);
        if (!is_array($configured)
            || ($configured['configured'] ?? false) !== true
            || !is_string($configured['value'] ?? null)
            || $configured['value'] === '') {
            throw new \InvalidArgumentException("{$name} must be configured");
        }
        return $configured['value'];
    }

    private function requireHttpsUrl(string $url, string $name): void
    {
        $parts = parse_url($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || !is_string($parts['host'] ?? null)
            || $parts['host'] === ''
            || isset($parts['user'])
            || isset($parts['pass'])) {
            throw new \InvalidArgumentException("{$name} must be a valid HTTPS URL without credentials");
        }
    }
}
