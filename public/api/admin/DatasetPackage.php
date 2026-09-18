<?php

declare(strict_types=1);

namespace FreeTV\Admin;

final class DatasetPackage
{
    private bool $cleaned = false;

    public function __construct(
        private string $workspace,
        private string $root,
        private string $dataset,
        private array $files
    ) {
    }

    public function root(): string
    {
        return $this->root;
    }

    public function dataset(): string
    {
        return $this->dataset;
    }

    public function files(): array
    {
        return $this->files;
    }

    public function path(string $relativePath): string
    {
        if (!array_key_exists($relativePath, $this->files)) {
            throw new \InvalidArgumentException('Requested file is not in the validated dataset package');
        }
        return $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    public function cleanup(): void
    {
        if (!$this->cleaned) {
            self::removeTree($this->workspace);
            $this->cleaned = true;
        }
    }

    public function __destruct()
    {
        try {
            $this->cleanup();
        } catch (\Throwable $exception) {
            error_log('Dataset package cleanup error: ' . $exception->getMessage());
        }
    }

    public static function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            if (!unlink($path)) {
                throw new \RuntimeException('Could not remove a dataset package temporary file');
            }
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        $iterator = new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $item) {
            self::removeTree($item->getPathname());
        }
        if (!rmdir($path)) {
            throw new \RuntimeException('Could not remove a dataset package temporary directory');
        }
    }
}
