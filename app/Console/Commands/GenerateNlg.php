<?php

namespace App\Console\Commands;

use App\Contracts\Nlg\Narrator;
use App\Services\Forecast\ForecastServiceFactory;
use App\Services\Nlg\ForecastNlgCacheService;
use Illuminate\Console\Command;

class GenerateNlg extends Command
{
    protected $signature = 'weather:generate-nlg';
    protected $description = 'Pre-generate deterministic NLG forecast text for all languages and cache it';

    public function handle(Narrator $narrator, ForecastNlgCacheService $cacheService): int
    {
        $this->info('Generating deterministic NLG forecast text for all languages...');

        $locales = $cacheService->resolveLocales();
        $forecastService = ForecastServiceFactory::make();

        $daily = $forecastService->getDailyForecast(14);
        $forecastData = $forecastService->fetchForecast();

        if (!is_array($forecastData) || !isset($forecastData['forecast']) || !is_array($forecastData['forecast'])) {
            $this->error('No forecast data available');

            return self::FAILURE;
        }

        $entries = $cacheService->buildEntries($daily, $forecastData['forecast']);
        if ($entries === []) {
            $this->error('No forecast entries available for NLG generation');

            return self::FAILURE;
        }

        $generatedCount = 0;

        // Both temperature scales, because readers choose their own units and
        // the text has the temperature written into it. This pass is pure
        // computation, so the second scale costs nothing but cache entries.
        foreach ($locales as $locale) {
            foreach (ForecastNlgCacheService::SCALE_UNITS as $units) {
                $this->line("Generating NLG for locale: {$locale} ({$units})");
                $generatedCount += $cacheService->cacheDraftsForLocale($entries, $locale, $narrator, $units);
            }
        }

        $this->info("Successfully generated {$generatedCount} deterministic NLG texts for " . count($locales) . " languages");

        return self::SUCCESS;
    }
}
