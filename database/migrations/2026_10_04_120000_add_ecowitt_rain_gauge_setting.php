<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Which rain gauge an Ecowitt station's rain is read from (#132).
 *
 * A migration as well as a seeder row, because existing installs never
 * re-seed and the Ecowitt settings page only lists rows that exist. Auto
 * leaves every station reading what it read before, except WS90 and WS85
 * stations, which had no rain at all.
 */
return new class extends Migration
{
    private const KEY = 'ecowitt.rain_gauge';

    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => self::KEY,
            'value' => 'auto',
            'type' => 'select',
            'group' => 'ecowitt',
            'description' => 'Which rain gauge to read. Auto uses a WS90 or WS85 piezo gauge unless a tipping bucket has recorded rain this year',
            'options' => 'auto:Auto,tipping:Tipping bucket (WH40 / WS69),piezo:Piezo (WS90 / WS85)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', self::KEY)->delete();
    }
};
