<?php

namespace App\Support;

use App\Models\Setting;

/**
 * How an Ecowitt station gets its data to WeatherNode.
 *
 * Two settings take part: livedata.format, which the scheduler reads to pick
 * the live data source, and ecowitt.data_source, which says how the Ecowitt
 * reader gets its data. They used to be set on different pages and could
 * disagree. They are read together here and always written together.
 */
final class EcowittSource
{
    /** The console sends to /api/ecowitt/receive. */
    public const PUSH = 'push';

    /** WeatherNode reads the Ecowitt cloud API. */
    public const CLOUD = 'cloud';

    /** Something else writes the readings to a file WeatherNode reads. */
    public const FILE = 'file';

    public const ALL = [self::PUSH, self::CLOUD, self::FILE];

    /** The source in use, or null when the live data comes from something other than Ecowitt. */
    public static function current(): ?string
    {
        return match ((string) Setting::getValue('livedata.format', 'ecoLcl')) {
            'ecowittAPI' => self::CLOUD,
            'ecoLcl' => self::fromDataSource((string) Setting::getValue('ecowitt.data_source', 'push')),
            default => null,
        };
    }

    /** How the Ecowitt reader gets its data, whatever the live data source is. */
    public static function reader(): string
    {
        return self::current() ?? self::fromDataSource((string) Setting::getValue('ecowitt.data_source', 'push'));
    }

    public static function apply(string $source): void
    {
        [$format, $dataSource] = match ($source) {
            self::CLOUD => ['ecowittAPI', 'cloud_api'],
            self::FILE => ['ecoLcl', 'local_file'],
            default => ['ecoLcl', 'push'],
        };

        Setting::setValue('livedata.format', $format, 'select', 'livedata');
        Setting::setValue('ecowitt.data_source', $dataSource, 'select', 'ecowitt');
    }

    private static function fromDataSource(string $dataSource): string
    {
        return match ($dataSource) {
            'cloud_api' => self::CLOUD,
            'local_file', 'local' => self::FILE,
            default => self::PUSH,
        };
    }
}
