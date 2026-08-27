<?php

namespace FreeTV\Admin\Publication;

require_once __DIR__ . '/../ServerPaths.php';

require_once __DIR__ . '/../Settings.php';
require_once __DIR__ . '/PublicationException.php';
require_once __DIR__ . '/PublicationTimestamp.php';
require_once __DIR__ . '/ConfigPublicationSerializer.php';
require_once __DIR__ . '/PublicationUndoService.php';

use DateTimeImmutable;
use DateTimeZone;
use FreeTV\Admin\Settings;
use FreeTV\Admin\ServerPaths;
use JsonException;
use Throwable;

class ConfigPublicationService
{
    private string $publicationRoot;
    private $settingsLoader;
    private $clock;
    private PublicationUndoService $undoService;

    public function __construct(
        ?string $publicationRoot = null,
        ?callable $settingsLoader = null,
        ?callable $clock = null,
        ?PublicationUndoService $undoService = null
    ) {
        $this->publicationRoot = rtrim(
            $publicationRoot ?? (new ServerPaths())->publicRoot(),
            DIRECTORY_SEPARATOR
        );
        $this->settingsLoader = $settingsLoader ?? static fn() => Settings::readPublishable();
        $this->clock = $clock ?? static fn() => new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->undoService = $undoService ?? new PublicationUndoService($this->publicationRoot);
    }

    public function publish(): array
    {
        return $this->undoService->withLock(fn() => $this->publishLocked());
    }

    private function publishLocked(): array
    {
        $publicationTimestamp = PublicationTimestamp::forOperation(($this->clock)());
        $artifact = ConfigPublicationSerializer::serialize(
            ($this->settingsLoader)(),
            $publicationTimestamp
        );
        $json = $this->encodeArtifact($artifact);

        $preparedUndo = $this->undoService->prepare('config', 'config.json', ['config.json']);
        try {
            $this->safeWrite($this->publicationRoot . DIRECTORY_SEPARATOR . 'config.json', $json);
            $this->undoService->promote($preparedUndo);
        } catch (Throwable $exception) {
            try {
                $this->undoService->rollbackPrepared($preparedUndo);
            } catch (Throwable $rollbackException) {
                throw new PublicationException(
                    'Config publication failed and the previous live artifact could not be restored'
                );
            }
            throw $exception instanceof PublicationException
                ? $exception
                : new PublicationException('Config publication failed: ' . $exception->getMessage());
        }

        return ['lastupdated' => $publicationTimestamp];
    }

    private function encodeArtifact(array $artifact): string
    {
        try {
            return json_encode(
                $artifact,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ) . "\n";
        } catch (JsonException $exception) {
            throw new PublicationException(
                'Could not encode config artifact: ' . $exception->getMessage()
            );
        }
    }

    private function safeWrite(string $destination, string $contents): void
    {
        if (!is_dir(dirname($destination))) {
            throw new PublicationException('Config publication root does not exist');
        }

        $temporaryPath = tempnam(dirname($destination), '.publish-');
        if ($temporaryPath === false) {
            throw new PublicationException('Could not create temporary config publication file');
        }

        try {
            $bytesWritten = file_put_contents($temporaryPath, $contents, LOCK_EX);
            if ($bytesWritten !== strlen($contents)) {
                throw new PublicationException('Could not write complete config artifact');
            }
            if (!chmod($temporaryPath, 0644)) {
                throw new PublicationException('Could not set config artifact permissions');
            }
            if (!rename($temporaryPath, $destination)) {
                throw new PublicationException('Could not replace config artifact');
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
