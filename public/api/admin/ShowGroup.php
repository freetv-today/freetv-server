<?php

namespace FreeTV\Admin;

class ShowGroup
{
    public static function fromShow(array $show): ?string
    {
        return array_key_exists('group', $show)
            ? self::normalize($show['group'])
            : null;
    }

    public static function normalize($group): ?string
    {
        if (!is_string($group)) {
            throw new \InvalidArgumentException('Group must be a string');
        }

        $group = trim($group);
        return $group === '' ? null : $group;
    }
}
