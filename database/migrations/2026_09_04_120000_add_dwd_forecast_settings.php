<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DWD arrived as a forecast service with no way to reach it.
 *
 * The forecast picker is built from the options string on
 * forecast.default_source, so a source missing from that string cannot be
 * chosen however well it works. Existing installs never reseed, so the option
 * has to be appended here rather than only in the seeder.
 */
return new class extends Migration
{
    private const OPTION = 'fct_dwd_block.php:DWD';

    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => 'dwd.station_id',
            'value' => '',
            'type' => 'string',
            'group' => 'dwd',
            'description' => 'DWD MOSMIX station id, e.g. 10382 for Berlin-Tegel. Leave empty to use the nearest station',
            'options' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('settings')->where('key', 'forecast.default_source')->first();

        if ($row === null) {
            return;
        }

        $options = (string) ($row->options ?? '');

        // Appending twice would put DWD in the dropdown twice.
        if ($options === '' || str_contains($options, 'fct_dwd_block.php')) {
            return;
        }

        DB::table('settings')
            ->where('key', 'forecast.default_source')
            ->update([
                'options' => $options.','.self::OPTION,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'dwd.station_id')->delete();

        $row = DB::table('settings')->where('key', 'forecast.default_source')->first();

        if ($row === null) {
            return;
        }

        $options = (string) ($row->options ?? '');

        DB::table('settings')
            ->where('key', 'forecast.default_source')
            ->update([
                'options' => trim(str_replace([','.self::OPTION, self::OPTION], '', $options), ','),
                'updated_at' => now(),
            ]);
    }
};
