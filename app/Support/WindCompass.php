<?php

namespace App\Support;

class WindCompass
{
    public const POINTS = [
        'N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE',
        'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW',
    ];

    public static function fromDegrees(?float $degrees): string
    {
        if ($degrees === null) {
            return 'N/A';
        }

        $index = (int) round(((float) $degrees) / 22.5) % 16;
        if ($index < 0) {
            $index += 16;
        }

        return self::POINTS[$index];
    }
}
