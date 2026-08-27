<?php

declare(strict_types=1);

namespace FreeTV\Admin;

final class InitializationPlan
{
    public const ALREADY_INITIALIZED = 'already_initialized';
    public const CREATE_ADMIN_ONLY = 'create_admin_only';
    public const CREATE_ADMIN_AND_STARTER = 'create_admin_and_starter';

    public static function forState(bool $hasUsers, bool $hasPlaylists, bool $hasPlaylistShows): string
    {
        if ($hasUsers) {
            return self::ALREADY_INITIALIZED;
        }
        if ($hasPlaylistShows && !$hasPlaylists) {
            throw new \RuntimeException('Playlist shows exist without a parent playlist');
        }
        return $hasPlaylists || $hasPlaylistShows
            ? self::CREATE_ADMIN_ONLY
            : self::CREATE_ADMIN_AND_STARTER;
    }
}
