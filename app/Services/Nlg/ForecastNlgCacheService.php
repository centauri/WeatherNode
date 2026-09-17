<?php

namespace App\Services\Nlg;

use App\Contracts\Nlg\BatchRephraser;
use App\Models\Setting;
use App\Contracts\Nlg\Narrator;
use App\Contracts\Nlg\Rephraser;
use Illuminate\Support\Facades\Cache;

class ForecastNlgCacheService
{
    public const CACHE_TTL_MINUTES = 45;

    public const HASH_TTL_HOURS = 6;

    public const DEFAULT_AI_DAYS = 3;

    /**
     * One unit system per temperature scale, for the passes that pre-generate
     * every variant. Metric stands in for every Celsius system, so there are
     * two of these and not four.
     */
    public const SCALE_UNITS = ['metric', 'imperial'];

    /**
     * Whether the AI pass polishes both temperature scales or only the one the
     * site is set to.
     *
     * Prose cannot be converted back into numbers, so a second scale means
     * asking the provider a second time. That is a bill rather than a detail,
     * so it is the owner's call, and it is off unless they say otherwise: an
     * install upgrading into this should not find its token use doubled
     * without anyone choosing it. With it off, a reader who switches units
     * still gets the right units, just the plainer sentence.
     */
    public static function rephrasesBothScales(): bool
    {
        return (bool) Setting::getValue('nlg.rephrase_both_units', false);
    }

    /**
     * The unit systems the AI pass should run for.
     *
     * @return list<string>
     */
    public static function scalesToRephrase(): array
    {
        if (self::rephrasesBothScales()) {
            return self::SCALE_UNITS;
        }

        return [(string) Setting::getValue('display.unit_system', 'metric')];
    }

    /**
     * @param  array<int, array<string, mixed>>  $daily
     * @param  array<int, array<string, mixed>>  $hourlyForecast
     * @return array<int, array{date: string, payload: array<string, mixed>}>
     */
    public function buildEntries(array $daily, array $hourlyForecast): array
    {
        $entries = [];

        foreach ($daily as $day) {
            $date = (string) ($day['date'] ?? '');
            if ($date === '') {
                continue;
            }

            $payload = $this->buildPayloadForDay($day, $hourlyForecast);
            if ($payload === null) {
                continue;
            }

            $entries[] = [
                'date' => $date,
                'payload' => $payload,
            ];
        }

        return $entries;
    }

    /**
     * @param  array<int, array{date: string, payload: array<string, mixed>}>  $entries
     */
    public function cacheDraftsForLocale(array $entries, string $locale, Narrator $narrator, ?string $units = null): int
    {
        $count = 0;
        $ttl = now()->addMinutes(self::CACHE_TTL_MINUTES);

        foreach ($entries as $entry) {
            $date = $entry['date'];
            $draftKey = self::draftCacheKey($locale, $date, $units);
            $finalKey = self::finalCacheKey($locale, $date, $units);
            $draft = $narrator->narrate($entry['payload'], ['locale' => $locale, 'units' => $units]);
            $existingDraft = Cache::get($draftKey);
            $existingFinal = Cache::get($finalKey);

            Cache::put($draftKey, $draft, $ttl);

            $draftUnchanged = is_string($existingDraft)
                && trim($existingDraft) !== ''
                && trim($existingDraft) === trim($draft);

            if ($draftUnchanged && is_string($existingFinal) && trim($existingFinal) !== '') {
                Cache::put($finalKey, $existingFinal, $ttl);
            } else {
                Cache::put($finalKey, $draft, $ttl);
            }

            $count++;
        }

        return $count;
    }

    /**
     * @param  array<int, array{date: string, payload: array<string, mixed>}>  $entries
     * @return array{updated: int, skipped: int, fallback: int}
     */
    public function rephraseForLocale(
        array $entries,
        string $locale,
        Narrator $narrator,
        Rephraser $rephraser,
        string $tone = 'brief',
        bool $force = false,
        ?RephraseBudget $budget = null,
        string $providerId = '',
        ?string $units = null,
    ): array {
        // Rephrasers that support batching send a whole locale's days in one request, which keeps
        // the request rate far under strict free-tier per-minute quotas (e.g. Cerebras 5 RPM).
        if ($rephraser instanceof BatchRephraser) {
            return $this->rephraseBatchForLocale($entries, $locale, $narrator, $rephraser, $tone, $force, $budget, $providerId, $units);
        }

        $updated = 0;
        $skipped = 0;
        $fallback = 0;
        $budgetExhausted = false;
        $ttl = now()->addMinutes(self::CACHE_TTL_MINUTES);
        $hashTtl = now()->addHours(self::HASH_TTL_HOURS);

        foreach ($entries as $entry) {
            $date = $entry['date'];
            $draftKey = self::draftCacheKey($locale, $date, $units);
            $finalKey = self::finalCacheKey($locale, $date, $units);
            $hashKey = self::hashCacheKey($locale, $date, $units);

            $draft = Cache::get($draftKey);
            if (! is_string($draft) || trim($draft) === '') {
                $draft = $narrator->narrate($entry['payload'], ['locale' => $locale, 'units' => $units]);
                Cache::put($draftKey, $draft, $ttl);
            }

            $final = Cache::get($finalKey);

            $sourceHash = sha1(json_encode([
                'tone' => $tone,
                'draft' => $draft,
                'facts' => $entry['payload'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');

            $hasAiFinal = is_string($final)
                && trim($final) !== ''
                && trim($final) !== trim($draft);

            if (! $force && Cache::get($hashKey) === $sourceHash && $hasAiFinal) {
                $skipped++;

                continue;
            }

            // Budget gate: if the provider's hour/day quota is spent, stop calling the API
            // and keep whatever final text already exists (the deterministic draft for fresh
            // entries). The shared budget is per provider, so nothing later will succeed.
            if ($budget !== null && ! $budget->tryReserve($providerId)) {
                $budgetExhausted = true;
                break;
            }

            $rewritten = trim($rephraser->rewrite($draft, $entry['payload'], $tone, $locale));

            if ($rewritten === '') {
                $rewritten = $draft;
            }

            Cache::put($finalKey, $rewritten, $ttl);

            if ($rewritten !== $draft) {
                Cache::put($hashKey, $sourceHash, $hashTtl);
                $updated++;

                continue;
            }

            Cache::forget($hashKey);
            $fallback++;
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'fallback' => $fallback,
            'budgetExhausted' => $budgetExhausted,
        ];
    }

    /**
     * Rephrase a whole locale in a single provider request.
     *
     * The skip/force/hash semantics match the per-entry path; the difference is that every
     * entry still needing work is collected and sent together, so a locale costs one request
     * (plus retries) instead of one per day. The budget is reserved per request via a callback
     * passed into the rephraser, so retries are paced and counted rather than bypassing the quota.
     *
     * @param  array<int, array{date: string, payload: array<string, mixed>}>  $entries
     * @return array{updated: int, skipped: int, fallback: int, budgetExhausted: bool}
     */
    private function rephraseBatchForLocale(
        array $entries,
        string $locale,
        Narrator $narrator,
        BatchRephraser $rephraser,
        string $tone,
        bool $force,
        ?RephraseBudget $budget,
        string $providerId,
        ?string $units = null,
    ): array {
        $updated = 0;
        $skipped = 0;
        $fallback = 0;
        $budgetExhausted = false;
        $ttl = now()->addMinutes(self::CACHE_TTL_MINUTES);
        $hashTtl = now()->addHours(self::HASH_TTL_HOURS);

        /** @var array<string, array{draft: string, facts: array<string, mixed>}> $pending */
        $pending = [];
        /** @var array<string, array{finalKey: string, hashKey: string, sourceHash: string}> $meta */
        $meta = [];

        foreach ($entries as $entry) {
            $date = $entry['date'];
            $draftKey = self::draftCacheKey($locale, $date, $units);
            $finalKey = self::finalCacheKey($locale, $date, $units);
            $hashKey = self::hashCacheKey($locale, $date, $units);

            $draft = Cache::get($draftKey);
            if (! is_string($draft) || trim($draft) === '') {
                $draft = $narrator->narrate($entry['payload'], ['locale' => $locale, 'units' => $units]);
                Cache::put($draftKey, $draft, $ttl);
            }

            $final = Cache::get($finalKey);

            $sourceHash = sha1(json_encode([
                'tone' => $tone,
                'draft' => $draft,
                'facts' => $entry['payload'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');

            $hasAiFinal = is_string($final)
                && trim($final) !== ''
                && trim($final) !== trim($draft);

            if (! $force && Cache::get($hashKey) === $sourceHash && $hasAiFinal) {
                $skipped++;

                continue;
            }

            $pending[$date] = ['draft' => $draft, 'facts' => $entry['payload']];
            $meta[$date] = ['finalKey' => $finalKey, 'hashKey' => $hashKey, 'sourceHash' => $sourceHash];
        }

        if ($pending === []) {
            return ['updated' => 0, 'skipped' => $skipped, 'fallback' => 0, 'budgetExhausted' => false];
        }

        $reserveSlot = $budget !== null
            ? fn (): bool => $budget->tryReserve($providerId)
            : null;

        $results = $rephraser->rewriteBatch($pending, $tone, $reserveSlot, $locale);

        // A denied reserve (hour/day quota) makes the rephraser keep the drafts; surface that so the
        // command stops early instead of trying the next locale on the same exhausted provider budget.
        if ($budget !== null && $budget->lastSkipReason() !== null) {
            $budgetExhausted = true;
        }

        foreach ($pending as $date => $item) {
            $draft = $item['draft'];
            $rewritten = trim((string) ($results[$date] ?? $draft));
            if ($rewritten === '') {
                $rewritten = $draft;
            }

            Cache::put($meta[$date]['finalKey'], $rewritten, $ttl);

            if ($rewritten !== $draft) {
                Cache::put($meta[$date]['hashKey'], $meta[$date]['sourceHash'], $hashTtl);
                $updated++;

                continue;
            }

            Cache::forget($meta[$date]['hashKey']);
            $fallback++;
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'fallback' => $fallback,
            'budgetExhausted' => $budgetExhausted,
        ];
    }

    /**
     * @param  array<int, array{date: string, payload: array<string, mixed>}>  $entries
     * @return array<int, array{date: string, payload: array<string, mixed>}>
     */
    public function limitEntries(array $entries, int $maxDays): array
    {
        if ($maxDays <= 0) {
            return [];
        }

        return array_slice($entries, 0, $maxDays);
    }

    public function resolveAiDaysLimit(mixed $value, int $default = self::DEFAULT_AI_DAYS): ?int
    {
        if ($value === null || $value === '') {
            return max(1, $default);
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === 'all') {
                return null;
            }

            if (! is_numeric($normalized)) {
                return max(1, $default);
            }

            return max(1, (int) $normalized);
        }

        if (is_int($value) || is_float($value)) {
            return max(1, (int) $value);
        }

        return max(1, $default);
    }

    /**
     * @return array<int, string>
     */
    public function resolveLocales(?string $csvLocales = null, ?array $fallbackLocales = null): array
    {
        $locales = [];
        $available = array_keys(config('localization.locales', []));

        if (is_string($csvLocales) && trim($csvLocales) !== '') {
            $requestedLocales = preg_split('/\s*,\s*/', trim($csvLocales)) ?: [];
            $locales = $this->expandRequestedLocales($requestedLocales, $fallbackLocales, $available);
        } elseif (is_array($fallbackLocales)) {
            $locales = $fallbackLocales;
        } else {
            $locales = $available;
        }

        return array_values(array_filter(array_unique($locales), static function ($locale) use ($available): bool {
            return is_string($locale) && in_array($locale, $available, true);
        }));
    }

    /**
     * @param  array<int, mixed>  $requestedLocales
     * @param  array<int, mixed>|null  $fallbackLocales
     * @param  array<int, string>  $availableLocales
     * @return array<int, string>
     */
    private function expandRequestedLocales(array $requestedLocales, ?array $fallbackLocales, array $availableLocales): array
    {
        $expanded = [];
        $preferredLocales = array_values(array_filter($fallbackLocales ?? [], static fn ($locale): bool => is_string($locale)));
        $preferredMap = $preferredLocales !== [] ? array_flip($preferredLocales) : [];

        foreach ($requestedLocales as $requestedLocale) {
            if (! is_string($requestedLocale) || trim($requestedLocale) === '') {
                continue;
            }

            $normalized = strtolower(str_replace('_', '-', trim($requestedLocale)));

            if (in_array($normalized, $availableLocales, true)) {
                $expanded[] = $normalized;

                continue;
            }

            if (! preg_match('/^[a-z]{2}$/', $normalized)) {
                continue;
            }

            $matches = array_values(array_filter($availableLocales, static function (string $locale) use ($normalized): bool {
                return $locale === $normalized || str_starts_with($locale, $normalized.'-');
            }));

            if ($matches === []) {
                continue;
            }

            $preferredMatches = array_values(array_filter($matches, static function (string $locale) use ($preferredMap): bool {
                return isset($preferredMap[$locale]);
            }));

            array_push($expanded, ...($preferredMatches !== [] ? $preferredMatches : $matches));
        }

        return $expanded;
    }

    /**
     * Keys carry the temperature scale, because the text has the temperature
     * written into it and readers pick their own units. Without it the first
     * reader of the day decided what everybody after them saw.
     *
     * The scale, not the unit system: metric, UK and Scandinavia all read
     * Celsius, so they share one text and there are two variants, not four.
     */
    public static function finalCacheKey(string $locale, string $date, ?string $units): string
    {
        return "nlg_{$locale}_" . ForecastNarrator::temperatureScale($units) . "_{$date}";
    }

    public static function draftCacheKey(string $locale, string $date, ?string $units): string
    {
        return "nlg_draft_{$locale}_" . ForecastNarrator::temperatureScale($units) . "_{$date}";
    }

    public static function hashCacheKey(string $locale, string $date, ?string $units): string
    {
        return "nlg_hash_{$locale}_" . ForecastNarrator::temperatureScale($units) . "_{$date}";
    }

    /**
     * @param  array<string, mixed>  $day
     * @param  array<int, array<string, mixed>>  $hourlyForecast
     * @return array<string, mixed>|null
     */
    private function buildPayloadForDay(array $day, array $hourlyForecast): ?array
    {
        $date = (string) ($day['date'] ?? '');
        if ($date === '') {
            return null;
        }

        $dayStart = $date.'T00:00:00Z';
        $dayEnd = $date.'T23:59:59Z';

        $dayHours = array_values(array_filter($hourlyForecast, static function ($entry) use ($dayStart, $dayEnd): bool {
            $time = $entry['time'] ?? null;

            return is_string($time) && $time >= $dayStart && $time <= $dayEnd;
        }));

        if ($dayHours === []) {
            return [
                'date' => $date,
                'min_temp_c' => $day['temp_low'] ?? null,
                'max_temp_c' => $day['temp_high'] ?? null,
                'precip_prob_pct' => ($day['precipitation'] ?? 0) > 0 ? 70 : 10,
                'precip_mm' => $day['precipitation'] ?? 0,
                'precip_type' => ($day['precipitation'] ?? 0) > 0 ? 'rain' : 'none',
            ];
        }

        $periods = [];

        foreach ($dayHours as $hour) {
            try {
                $hourTime = new \DateTime((string) $hour['time']);
                $hourOfDay = (int) $hourTime->format('H');
            } catch (\Exception) {
                continue;
            }

            $periodName = match (true) {
                $hourOfDay >= 6 && $hourOfDay < 12 => 'morning',
                $hourOfDay >= 12 && $hourOfDay < 18 => 'afternoon',
                $hourOfDay >= 18 => 'evening',
                default => null,
            };

            if ($periodName === null) {
                continue;
            }

            if (! isset($periods[$periodName])) {
                $periods[$periodName] = [
                    'name' => $periodName,
                    'temp_c' => [],
                    'wind_ms' => [],
                    'wind_dir_deg' => [],
                    'precip_mm' => [],
                    'cloud_pct' => [],
                ];
            }

            if (isset($hour['temperature']) && $hour['temperature'] !== null) {
                $periods[$periodName]['temp_c'][] = (float) $hour['temperature'];
            }

            if (isset($hour['wind_speed']) && $hour['wind_speed'] !== null) {
                $periods[$periodName]['wind_ms'][] = (float) $hour['wind_speed'] / 3.6;
            }

            if (isset($hour['wind_direction']) && $hour['wind_direction'] !== null) {
                $periods[$periodName]['wind_dir_deg'][] = (float) $hour['wind_direction'];
            }

            if (isset($hour['cloud_cover']) && $hour['cloud_cover'] !== null) {
                $periods[$periodName]['cloud_pct'][] = (float) $hour['cloud_cover'];
            }

            $precip = $hour['precipitation_1h'] ?? $hour['precipitation_6h'] ?? null;
            if ($precip !== null && (float) $precip > 0) {
                $periods[$periodName]['precip_mm'][] = (float) $precip;
            }
        }

        $preparedPeriods = [];

        foreach ($periods as $period) {
            $preparedPeriods[] = [
                'name' => $period['name'],
                'temp_c' => $this->average($period['temp_c']),
                'wind_ms' => $this->average($period['wind_ms']),
                'wind_dir_deg' => $period['wind_dir_deg'] !== [] ? $this->circularMeanDeg($period['wind_dir_deg']) : null,
                'cloud_pct' => $this->average($period['cloud_pct']),
                'precip_mm' => $period['precip_mm'] !== [] ? array_sum($period['precip_mm']) : 0,
                'precip_type' => $period['precip_mm'] !== [] ? 'rain' : 'none',
                'precip_prob_pct' => $period['precip_mm'] !== [] ? 70 : 10,
            ];
        }

        if ($preparedPeriods === []) {
            return [
                'date' => $date,
                'min_temp_c' => $day['temp_low'] ?? null,
                'max_temp_c' => $day['temp_high'] ?? null,
                'precip_prob_pct' => ($day['precipitation'] ?? 0) > 0 ? 70 : 10,
                'precip_mm' => $day['precipitation'] ?? 0,
                'precip_type' => ($day['precipitation'] ?? 0) > 0 ? 'rain' : 'none',
            ];
        }

        return [
            'date' => $date,
            'periods' => $preparedPeriods,
        ];
    }

    /**
     * @param  array<int, float>  $values
     */
    private function average(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        return array_sum($values) / count($values);
    }

    /**
     * @param  array<int, float>  $degrees
     */
    private function circularMeanDeg(array $degrees): float
    {
        $sinSum = 0;
        $cosSum = 0;

        foreach ($degrees as $degree) {
            $rad = deg2rad($degree);
            $sinSum += sin($rad);
            $cosSum += cos($rad);
        }

        $avg = rad2deg(atan2($sinSum / count($degrees), $cosSum / count($degrees)));

        return $avg < 0 ? $avg + 360 : round($avg);
    }
}
