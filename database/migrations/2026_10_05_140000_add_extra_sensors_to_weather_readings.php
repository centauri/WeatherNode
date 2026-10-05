<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Newer Ecowitt sensors kept together as JSON rather than as dozens of
 * mostly empty columns: black globe, soil EC (16 channels), water level,
 * wetness status and water quality. See App\Support\ExtraSensors.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('weather_readings', 'extra_sensors')) {
            Schema::table('weather_readings', function (Blueprint $table) {
                $table->json('extra_sensors')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('weather_readings', 'extra_sensors')) {
            Schema::table('weather_readings', function (Blueprint $table) {
                $table->dropColumn('extra_sensors');
            });
        }
    }
};
