<?php

namespace FreeTV\Admin\Publication;

require_once __DIR__ . '/../Settings.php';
require_once __DIR__ . '/PublicationTimestamp.php';

use DateTimeInterface;
use FreeTV\Admin\Settings;

class ConfigPublicationSerializer
{
    public static function serialize(
        array $settings,
        DateTimeInterface|string $publicationTimestamp
    ): array {
        return array_merge(
            ['lastupdated' => PublicationTimestamp::format($publicationTimestamp)],
            Settings::publishable($settings)
        );
    }
}
