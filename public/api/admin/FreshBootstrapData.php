<?php

declare(strict_types=1);

namespace FreeTV\Admin;

use JsonException;

final class FreshBootstrapData
{
    public function __construct(private string $path)
    {
    }

    public function load(): array
    {
        if (!is_file($this->path) || !is_readable($this->path)) {
            throw new \RuntimeException('Bundled Fresh bootstrap data is missing or unreadable');
        }

        $contents = file_get_contents($this->path);
        if ($contents === false) {
            throw new \RuntimeException('Bundled Fresh bootstrap data could not be read');
        }
        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Bundled Fresh bootstrap data is invalid JSON', 0, $exception);
        }

        $playlistFields = [
            'filename', 'dbtitle', 'dbversion', 'author', 'email', 'link', 'is_default', 'sort_order',
        ];
        if (!is_array($data)
            || array_keys($data) !== ['settings', 'playlist', 'shows']
            || !is_array($data['settings'])
            || $data['settings'] !== ['show_ads' => false]
            || !is_array($data['playlist'])
            || array_keys($data['playlist']) !== $playlistFields
            || $data['playlist']['filename'] !== 'playlist-one.json'
            || $data['playlist']['dbtitle'] !== 'Playlist One'
            || $data['playlist']['dbversion'] !== null
            || $data['playlist']['author'] !== null
            || $data['playlist']['email'] !== null
            || $data['playlist']['link'] !== null
            || $data['playlist']['is_default'] !== 1
            || $data['playlist']['sort_order'] !== 0
            || $data['shows'] !== []) {
            throw new \RuntimeException('Bundled Fresh bootstrap data does not match the Fresh contract');
        }

        return $data;
    }
}
