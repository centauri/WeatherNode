<?php

namespace App\Console\Commands;

use App\Contracts\Nlg\Narrator;
use App\Contracts\Nlg\Rephraser;
use App\Models\Setting;
use App\Services\Forecast\ForecastServiceFactory;
use App\Services\Nlg\ForecastNlgCacheService;
use App\Services\Nlg\RephraseBudget;
use Illuminate\Console\Command;

class RephraseNlg extends Command
{
    protected $signature = 'weather:rephrase-nlg
                            {--days= : Maximum number of forecast days to rephrase per locale. Use "all" to process every available forecast day. Defaults to the admin setting.}
                            {--locales= : Comma-separated locales to rephrase. Accepts full locales or language shorthands like nl,en. Defaults to the configured AI languages.}
                            {--force : Re-run AI rephrasing even when the cached draft, facts, and tone are unchanged.}';

    protected $description = 'Rephrase cached NLG forecast text with the configured LLM in a separate pass';

    public function handle(
        Narrator $narrator,
        ForecastNlgCacheService $cacheService,
        RephraseBudget $budget,
    ): int {
        if (!Setting::getValue('nlg.llm_enabled', false)) {
            $this->info('LLM rephrasing is disabled; skipping.');

            return self::SUCCESS;
        }

        try {
            $rephraser = app(Rephraser::class);
        } catch (\Throwable $e) {
            $this->warn('LLM rephraser not available: ' . $e->getMessage());

            return self::SUCCESS;
        }

        if (!$rephraser instanceof Rephraser) {
            $this->warn('LLM rephraser is not configured; skipping.');

            return self::SUCCESS;
        }

        $configuredAiDays = Setting::getValue('nlg.ai_days', ForecastNlgCacheService::DEFAULT_AI_DAYS);
        $daysOption = $this->option('days');
        $daysLimit = $cacheService->resolveAiDaysLimit(
            $daysOption !== null && $daysOption !== '' ? $daysOption : $configuredAiDays,
            ForecastNlgCacheService::DEFAULT_AI_DAYS,
        );
        $configuredAiLocales = Setting::getValue('nlg.ai_locales', null);
        if (!is_array($configuredAiLocales)) {
            $configuredAiLocales = [Setting::defaultLanguage()];
        }
        $locales = $cacheService->resolveLocales(
            is_string($this->option('locales')) ? $this->option('locales') : null,
            $configuredAiLocales,
        );

        if ($locales === []) {
            $this->warn('No valid locales selected for LLM rephrasing.');

            return self::SUCCESS;
        }

        $forecastService = ForecastServiceFactory::make();
        $daily = $forecastService->getDailyForecast(14);
        $forecastData = $forecastService->fetchForecast();

        if (!is_array($forecastData) || !isset($forecastData['forecast']) || !is_array($forecastData['forecast'])) {
            $this->error('No forecast data available');

            return self::FAILURE;
        }

        $entries = $cacheService->buildEntries($daily, $forecastData['forecast']);
        if ($daysLimit !== null) {
            $entries = $cacheService->limitEntries($entries, $daysLimit);
        }

        if ($entries === []) {
            $this->warn('No forecast entries available for LLM rephrasing.');

            return self::SUCCESS;
        }

        $tone = (string) Setting::getValue('nlg.default_tone', 'brief');
        $force = (bool) $this->option('force');
        $providerId = (string) Setting::getValue('nlg.provider', 'openai');
        $updated = 0;
        $skipped = 0;
        $fallback = 0;
        $budgetExhausted = false;

        $scope = $daysLimit === null
            ? 'all available forecast days'
            : sprintf('%d day(s)', count($entries));

        $this->info(sprintf(
            'Rephrasing cached NLG for %s in %d locale(s)%s...',
            $scope,
            count($locales),
            $force ? ' with force' : '',
        ));

        // Each temperature scale is rephrased in its own units, rather than one
        // being rewritten from the other, because by this point the text is
        // prose and there is no reliable way to convert a number back out of
        // it. That means a second scale costs a second call, so by default only
        // the site's own scale is polished and the other keeps its
        // deterministic sentence. Admin > Settings > NLG turns both on.
        //
        // Calls only happen when the forecast itself changed, and the provider
        // budget still gates them: when it is spent, whatever is left keeps its
        // deterministic draft rather than failing.
        $scales = ForecastNlgCacheService::scalesToRephrase();

        foreach ($locales as $locale) {
            foreach ($scales as $units) {
                $this->line("Rephrasing NLG for locale: {$locale} ({$units})");

                $result = $cacheService->rephraseForLocale(
                    $entries,
                    $locale,
                    $narrator,
                    $rephraser,
                    $tone,
                    $force,
                    $budget,
                    $providerId,
                    $units,
                );

                $updated += $result['updated'];
                $skipped += $result['skipped'];
                $fallback += $result['fallback'];

                // The budget is shared per provider, so once a window is exhausted no later
                // locale or scale will succeed this run. Stop both loops and keep
                // deterministic text for everything still outstanding.
                if ($result['budgetExhausted'] ?? false) {
                    $budgetExhausted = true;

                    break 2;
                }
            }
        }

        if ($budgetExhausted) {
            $reason = $budget->lastSkipReason() === 'day' ? 'daily' : 'hourly';
            $this->warn("Stopped early — {$providerId} {$reason} request budget reached; remaining entries kept their deterministic text.");
        }

        $this->info("LLM rephrase pass complete. Updated {$updated}, skipped {$skipped} unchanged, kept {$fallback} deterministic drafts.");

        return self::SUCCESS;
    }
}
