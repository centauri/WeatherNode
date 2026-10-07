<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Weather\Normalization\WeatherReadingWriter;
use App\Services\Weather\WuPushParser;
use App\Support\WuPush;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Receives uploads in the Weather Underground format.
 *
 * Answers the way Wunderground does: "success" when it worked. WeeWX stops
 * retrying when a reply starts with ERROR, so a wrong key does not keep
 * hammering the site.
 */
class WuPushController extends Controller
{
    public function receive(Request $request, WuPushParser $parser, WeatherReadingWriter $writer)
    {
        if (!WuPush::active()) {
            return $this->reply('ERROR: Wunderground upload is not the live data source in WeatherNode', 403);
        }

        $key = WuPush::stationKey();
        if ($key === '') {
            return $this->reply('ERROR: no station key in WeatherNode yet, save the Live Data Source page first', 503);
        }

        $password = trim((string) $request->input('PASSWORD', ''));
        if ($password === '' || !hash_equals($key, $password)) {
            Log::warning('Wunderground upload: wrong station key', ['ip' => $request->ip()]);

            return $this->reply('ERROR: wrong station key', 401);
        }

        WuPush::markReceived();

        $data = $parser->parse($request->all());
        if (!isset($data['temperature'])) {
            return $this->reply('invalid data: no tempf', 400);
        }

        // Rapidfire sends every few seconds: answer them all, store one a minute.
        if (!Cache::add(WuPush::SAVE_LOCK, true, WuPush::MIN_SECONDS_BETWEEN_SAVES)) {
            return $this->reply('success');
        }

        try {
            $writer->store($data);
        } catch (\Exception $e) {
            Cache::forget(WuPush::SAVE_LOCK);
            Log::error('Wunderground upload: could not store the reading', ['error' => $e->getMessage()]);

            return $this->reply('ERROR: could not store the reading', 500);
        }

        return $this->reply('success');
    }

    private function reply(string $text, int $status = 200)
    {
        return response($text . "\n", $status)->header('Content-Type', 'text/plain');
    }
}
