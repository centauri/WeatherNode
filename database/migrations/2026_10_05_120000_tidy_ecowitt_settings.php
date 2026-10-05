<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tidy the Ecowitt settings for the rebuilt settings page.
 *
 * Ten rows were never read by anything: an "enabled" switch and switches and
 * counts for sensors the dashboard detects from the data anyway. They only
 * made the page longer.
 *
 * ecowitt.data_source used "local_file" for two different set-ups: a station
 * that pushes to WeatherNode (the default, with no file) and one that really
 * writes a file. It is now "push" or "local_file", decided by whether the file
 * is there, which is what the reader did at run time already. A station on
 * the Ecowitt cloud live data source is marked "cloud_api" to match.
 */
return new class extends Migration
{
    private const DEAD = [
        'ecowitt.enabled', 'ecowitt.lightning_sensor', 'ecowitt.uv_sensor', 'ecowitt.solar_sensor',
        'ecowitt.air_quality_sensor', 'ecowitt.soil_sensors', 'ecowitt.extra_temp_sensors',
        'ecowitt.pm25_sensors', 'ecowitt.co2_sensor', 'ecowitt.leak_sensors',
    ];

    public function up(): void
    {
        DB::table('settings')->whereIn('key', self::DEAD)->delete();

        $value = fn (string $key) => DB::table('settings')->where('key', $key)->value('value');
        $format = (string) $value('livedata.format');
        $source = (string) $value('ecowitt.data_source');

        if ($format === 'ecowittAPI') {
            $source = 'cloud_api';
        } elseif ($source === 'local_api') {
            $source = 'push';
        } elseif ($source !== 'cloud_api') {
            $file = trim((string) $value('ecowitt.local_file'));
            $path = str_starts_with($file, DIRECTORY_SEPARATOR) ? $file : base_path($file);
            $source = $file !== '' && !str_contains($file, '..') && is_file($path) ? 'local_file' : 'push';
        }

        DB::table('settings')->updateOrInsert(['key' => 'ecowitt.data_source'], [
            'value' => $source,
            'type' => 'select',
            'group' => 'ecowitt',
            'description' => 'How the Ecowitt station sends data',
            'options' => 'push:Push from the console,local_file:Local file,cloud_api:Ecowitt cloud',
            'updated_at' => now(),
        ]);

        foreach ([...self::DEAD, 'ecowitt.data_source'] as $key) {
            Cache::forget("setting.{$key}");
        }
    }

    public function down(): void
    {
        // The removed rows held nothing that was read, so there is nothing to restore.
    }
};
