<?php

namespace App\Support;

final class AviationScene
{
    public const DEFAULT = 'schiphol';

    /** @var list<string> */
    public const IDS = ['village', 'schiphol', 'arctic', 'volcanic', 'spaceport'];

    public static function normalize(mixed $value): string
    {
        return is_string($value) && in_array($value, self::IDS, true)
            ? $value
            : self::DEFAULT;
    }
}
