<?php

namespace App\Services\Wave;

use App\Models\Setting;

class WaveServiceFactory
{
    public const SOURCES = [
        'open_meteo' => OpenMeteoWaveService::class,
        'kaur' => KaurMarineService::class,
    ];

    public const DEFAULT_SOURCE = 'open_meteo';

    public static function source(): string
    {
        $source = (string) Setting::getValue('waves.source', self::DEFAULT_SOURCE);

        return isset(self::SOURCES[$source]) ? $source : self::DEFAULT_SOURCE;
    }

    public static function make(): OpenMeteoWaveService|KaurMarineService
    {
        return app(self::SOURCES[self::source()]);
    }
}
