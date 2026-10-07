<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Netatmo as a live data source: its settings rows, and "Netatmo" in the
 * live data sources right after Ambient Weather.
 */
return new class extends Migration
{
    private const OPTION = 'netatmo:Netatmo';

    private const ROWS = [
        ['netatmo.client_id', 'string', 'Client ID of your Netatmo app'],
        ['netatmo.client_secret', 'encrypted', 'Client secret of your Netatmo app'],
        ['netatmo.refresh_token', 'encrypted', 'Netatmo refresh token (changes on every refresh)'],
        ['netatmo.access_token', 'encrypted', 'Netatmo access token'],
        ['netatmo.access_expires_at', 'string', 'When the Netatmo access token expires (Unix time)'],
        ['netatmo.device_id', 'string', 'Netatmo station to read (empty: the first one)'],
        ['netatmo.last_error', 'string', 'Last Netatmo error'],
    ];

    public function up(): void
    {
        foreach (self::ROWS as [$key, $type, $description]) {
            DB::table('settings')->insertOrIgnore([
                'key' => $key,
                'value' => '',
                'type' => $type,
                'group' => 'netatmo',
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Cache::forget("setting.{$key}");
        }

        $options = (string) DB::table('settings')->where('key', 'livedata.format')->value('options');
        if ($options === '' || str_contains($options, self::OPTION)) {
            return;
        }

        $options = str_contains($options, 'AWapi:Ambient Weather API,')
            ? str_replace('AWapi:Ambient Weather API,', 'AWapi:Ambient Weather API,' . self::OPTION . ',', $options)
            : $options . ',' . self::OPTION;

        DB::table('settings')->where('key', 'livedata.format')->update(['options' => $options, 'updated_at' => now()]);
        Cache::forget('setting.livedata.format');
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column(self::ROWS, 0))->delete();

        $options = (string) DB::table('settings')->where('key', 'livedata.format')->value('options');
        $options = str_replace([self::OPTION . ',', ',' . self::OPTION], '', $options);
        DB::table('settings')->where('key', 'livedata.format')->update(['options' => $options, 'updated_at' => now()]);
        Cache::forget('setting.livedata.format');
    }
};
