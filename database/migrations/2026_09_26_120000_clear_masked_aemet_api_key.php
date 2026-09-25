<?php

declare(strict_types=1);

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * The AEMET settings page used to prefill its API key field with ********, and
 * saving the page a second time stored that mask as the key (#97). AEMET then
 * failed without saying why, and the page still showed the key as configured.
 *
 * Clear a key that decrypts to nothing but asterisks, so the page shows it as
 * missing and the owner knows to enter it again. The real key is gone either
 * way; this only stops the install pretending it has one.
 *
 * A key that cannot be decrypted is left alone: that is an APP_KEY problem,
 * not this one.
 */
return new class extends Migration
{
    public function up(): void
    {
        $stored = DB::table('settings')->where('key', 'aemet.api_key')->value('value');

        if (!is_string($stored) || $stored === '') {
            return;
        }

        try {
            $key = Crypt::decryptString($stored);
        } catch (DecryptException) {
            return;
        }

        if (preg_match('/^\*+$/', $key)) {
            DB::table('settings')
                ->where('key', 'aemet.api_key')
                ->update(['value' => '', 'updated_at' => now()]);

            Cache::forget('setting.aemet.api_key');
        }
    }

    public function down(): void
    {
        // Putting the mask back would be the bug, not the fix.
    }
};
