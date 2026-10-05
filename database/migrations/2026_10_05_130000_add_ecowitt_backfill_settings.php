<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Filling gaps in the readings from the Ecowitt cloud history. On by
 * default: it does nothing until cloud keys and a MAC address are set.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            [
                'key' => 'ecowitt.backfill_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'ecowitt',
                'description' => 'Fill gaps in the readings from the Ecowitt cloud history',
                'options' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'ecowitt.backfill_days',
                'value' => '7',
                'type' => 'integer',
                'group' => 'ecowitt',
                'description' => 'How many days back to look for gaps (1 to 90)',
                'options' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['ecowitt.backfill_enabled', 'ecowitt.backfill_days', 'ecowitt.backfill_last_run'])->delete();
    }
};
