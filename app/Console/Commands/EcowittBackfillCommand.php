<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Weather\EcowittBackfill;
use Illuminate\Console\Command;

class EcowittBackfillCommand extends Command
{
    protected $signature = 'ecowitt:backfill {--days= : How many days back to look (1 to 90)}';

    protected $description = 'Fill gaps in the readings from the Ecowitt cloud history';

    public function handle(EcowittBackfill $backfill): int
    {
        if (!filter_var(Setting::getValue('ecowitt.backfill_enabled', '1'), FILTER_VALIDATE_BOOLEAN)) {
            $this->info('Filling gaps from the Ecowitt cloud is switched off.');

            return Command::SUCCESS;
        }

        $days = (int) ($this->option('days') ?: Setting::getValue('ecowitt.backfill_days', 7));
        $report = $backfill->run($days);

        if ($report['skipped']) {
            $this->info($report['skipped']);

            return Command::SUCCESS;
        }

        $this->info("Gaps found: {$report['gaps']}. Readings added: {$report['inserted']}.");
        if ($report['error']) {
            $this->warn("Stopped early: {$report['error']}");
        }

        return Command::SUCCESS;
    }
}
