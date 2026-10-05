<?php

declare(strict_types=1);

namespace Tests\Unit\Weather;

use App\Services\Weather\EcowittPushParser;
use Tests\TestCase;

/**
 * Real push payloads from ecowitt2mqtt's test fixtures (MIT licence,
 * https://github.com/bachya/ecowitt2mqtt/tree/dev/tests/fixtures). Running
 * them through the parser showed fields it dropped. The local file holds the
 * same fields, so it had the same gaps.
 */
class PushFieldsFromEcowitt2mqttTest extends TestCase
{
    private function payload(string $name): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/Ecowitt/ecowitt2mqtt/{$name}.json")), true);
    }

    /** A GW2000A spells leaf wetness leafwetness_ch1. */
    public function test_leaf_wetness_spelled_with_ch(): void
    {
        $data = (new EcowittPushParser())->parse($this->payload('payload_gw2000a_1'));

        $this->assertSame(14, $data['leaf_wetness_1']);
    }

    /** WN34 temperature probes, eight of them on this GW1100B, in °F. */
    public function test_wn34_temperature_probes(): void
    {
        $data = (new EcowittPushParser())->parse($this->payload('payload_gw1100b'));

        $this->assertSame(29.3, $data['extra_sensors']['probes'][1]);
        $this->assertSame(29.0, $data['extra_sensors']['probes'][8]);
    }

    /** The WS90 says whether its piezo is wet, as Wet/Dry or as 1/0. */
    public function test_ws90_wet_or_dry(): void
    {
        $parser = new EcowittPushParser();

        $this->assertTrue($parser->parse($this->payload('payload_gw2000a_wet'))['extra_sensors']['raining']);
        $this->assertFalse($parser->parse($this->payload('payload_gw2000a_3'))['extra_sensors']['raining']);
    }
}
