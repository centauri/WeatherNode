<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Add "Wunderground upload (push)" to the live data sources, right after
 * Weather Underground, so the setup wizard and the settings page offer it.
 */
return new class extends Migration
{
    private const OPTION = 'wuPush:Wunderground upload (push)';

    public function up(): void
    {
        $options = (string) DB::table('settings')->where('key', 'livedata.format')->value('options');
        if ($options === '' || str_contains($options, 'wuPush:')) {
            return;
        }

        $options = str_contains($options, 'wu:Weather Underground,')
            ? str_replace('wu:Weather Underground,', 'wu:Weather Underground,' . self::OPTION . ',', $options)
            : $options . ',' . self::OPTION;

        DB::table('settings')->where('key', 'livedata.format')->update(['options' => $options, 'updated_at' => now()]);
        Cache::forget('setting.livedata.format');
    }

    public function down(): void
    {
        $options = (string) DB::table('settings')->where('key', 'livedata.format')->value('options');
        $options = str_replace([self::OPTION . ',', ',' . self::OPTION], '', $options);

        DB::table('settings')->where('key', 'livedata.format')->update(['options' => $options, 'updated_at' => now()]);
        Cache::forget('setting.livedata.format');
    }
};
