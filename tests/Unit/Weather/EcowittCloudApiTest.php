<?php

declare(strict_types=1);

namespace Tests\Unit\Weather;

use App\Services\Weather\EcowittCloudApi;
use App\Services\Weather\EcowittCloudApiException;
use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Responses are captured from the real API (device details replaced with a
 * test station). Endpoints: https://doc.ecowitt.net/web/#/apiv3en?page_id=17
 */
class EcowittCloudApiTest extends TestCase
{
    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/Ecowitt/{$name}")), true);
    }

    private function api(): EcowittCloudApi
    {
        return new EcowittCloudApi('app-key', 'api-key', 'https://api.ecowitt.net/api/v3/');
    }

    public function test_it_lists_the_stations_on_the_account(): void
    {
        Http::fake(['api.ecowitt.net/api/v3/device/list*' => Http::response($this->fixture('device-list.json'))]);

        $stations = $this->api()->devices();

        $this->assertSame([[
            'name' => 'Test station',
            'mac' => 'AA:BB:CC:DD:EE:FF',
            'model' => 'GW1000A_V1.7.8',
            'timezone' => 'Europe/Berlin',
            'latitude' => 51.4779,
            'longitude' => -0.0015,
        ]], $stations);
        Http::assertSent(fn (Request $r) => $r['application_key'] === 'app-key' && $r['api_key'] === 'api-key');
    }

    public function test_cameras_are_left_out_of_the_station_list(): void
    {
        $list = $this->fixture('device-list.json');
        $list['data']['list'][] = ['name' => 'Cam', 'mac' => '11:22:33:44:55:66', 'type' => 2];
        Http::fake(['*' => Http::response($list)]);

        $this->assertCount(1, $this->api()->devices());
    }

    public function test_it_reports_whether_a_station_is_online(): void
    {
        Http::fake(['api.ecowitt.net/api/v3/device/info*' => Http::response($this->fixture('device-info.json'))]);

        $device = $this->api()->device('AA:BB:CC:DD:EE:FF');

        $this->assertTrue($device['online']);
        $this->assertSame('Europe/Berlin', $device['timezone']);
        $this->assertTrue(Carbon::createFromTimestamp(1791211731)->equalTo($device['last_update']));
        Http::assertSent(fn (Request $r) => $r['mac'] === 'AA:BB:CC:DD:EE:FF');
    }

    public function test_an_api_error_carries_its_code_and_message(): void
    {
        Http::fake(['*' => Http::response(['code' => 40005, 'msg' => 'One of MAC and IMEI must exist', 'data' => null])]);

        try {
            $this->api()->realTime('');
            $this->fail('Expected an exception');
        } catch (EcowittCloudApiException $e) {
            $this->assertSame(40005, $e->apiCode);
            $this->assertSame('One of MAC and IMEI must exist', $e->getMessage());
        }
    }

    public function test_real_time_asks_for_metric_units(): void
    {
        Http::fake(['*' => Http::response(['code' => 0, 'msg' => 'success', 'data' => ['outdoor' => []]])]);

        $this->assertSame(['outdoor' => []], $this->api()->realTime('AA:BB:CC:DD:EE:FF'));
        Http::assertSent(fn (Request $r) => $r['temp_unitid'] === 1 && $r['rainfall_unitid'] === 12 && $r['call_back'] === 'all');
    }

    /**
     * History dates are read in the station's own time zone: asking for
     * 00:00 to 01:00 in Europe/Berlin returned 22:00 to 23:00 UTC.
     */
    public function test_history_dates_are_sent_in_the_station_time_zone(): void
    {
        Http::fake(['*' => Http::response($this->fixture('history-5min.json'))]);

        $data = $this->api()->history(
            'AA:BB:CC:DD:EE:FF',
            Carbon::parse('2026-10-04 22:00:00', 'UTC'),
            Carbon::parse('2026-10-04 23:00:00', 'UTC'),
            'Europe/Berlin'
        );

        $this->assertCount(13, $data['outdoor']['temperature']['list']);
        Http::assertSent(fn (Request $r) => $r['start_date'] === '2026-10-05 00:00:00'
            && $r['end_date'] === '2026-10-05 01:00:00'
            && $r['cycle_type'] === '5min');
    }

    public function test_a_failed_request_is_an_exception(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);

        $this->expectException(EcowittCloudApiException::class);
        $this->api()->devices();
    }
}
