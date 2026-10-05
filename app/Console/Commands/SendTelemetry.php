<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Telemetry\TelemetryService;
use App\Services\Telemetry\TelemetryAggregatorService;
use Illuminate\Console\Command;

class SendTelemetry extends Command
{
    protected $signature = 'telemetry:send';
    protected $description = 'Send station telemetry data to the community aggregator';

    public function handle(TelemetryService $telemetryService, TelemetryAggregatorService $aggregatorService)
    {
        if (!Setting::getValue('telemetry.enabled', false)) {
            $this->info('Telemetry is disabled, skipping.');
            return 0;
        }

        $result = $telemetryService->publish($aggregatorService);
        if ($result['success']) {
            $this->info('Telemetry sent successfully.');
            return 0;
        }

        $this->error($result['message']);
        return 1;
    }
}
