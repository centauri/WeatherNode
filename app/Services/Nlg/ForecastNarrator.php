<?php

namespace App\Services\Nlg;

use App\Contracts\Nlg\Narrator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;

class ForecastNarrator implements Narrator
{
    /** Celsius unless the reader asked for imperial. Set per narrate() call. */
    private string $scale = 'c';

    /**
     * Which temperature scale a unit system reads in.
     *
     * Only imperial uses Fahrenheit: metric, UK and Scandinavia are all
     * Celsius. Narratives are therefore cached per scale rather than per unit
     * system, which is two variants instead of four.
     */
    public static function temperatureScale(?string $units): string
    {
        return $units === 'imperial' ? 'f' : 'c';
    }

    /**
     * Generate a forecast narrative from structured payload.
     * Delegates to daily() or periods() based on payload structure.
     */
    public function narrate(array $payload, array $options = []): string
    {
        // Ensure locale is set correctly - use locale from options or current app locale
        if (isset($options['locale'])) {
            app()->setLocale($options['locale']);
        }

        // The temperature scale is decided here, where the number still is a
        // number. Once this returns it is prose, and an optional AI pass may
        // rewrite it, so nothing downstream can be sure how a temperature ends
        // up written.
        $this->scale = self::temperatureScale($options['units'] ?? null);
        
        if (isset($payload['periods']) && is_array($payload['periods']) && count($payload['periods']) > 0) {
            return $this->periods($payload);
        }
        return $this->daily($payload);
    }

    /**
     * Generate a forecast for a daily summary record.
     */
    public function daily(array $d): string
    {
        $parts = [];

        // Sky condition - make it more conversational
        $cloud = Arr::get($d, 'cloud_pct');
        if (is_numeric($cloud)) {
            $sky = $this->label('forecast.sky', (float)$cloud);
            // Use more natural phrasing - check if it's the first (clearest) sky condition
            $locale = app()->getLocale();
            $transPath = resource_path("lang/{$locale}/forecast.php");
            if (file_exists($transPath)) {
                $transFile = require $transPath;
                $skyRules = $transFile['sky'] ?? null;
            }
            if (!isset($skyRules) || !is_array($skyRules) || empty($skyRules)) {
                $skyRules = config('forecast.sky', []);
            }
            $isClearSkies = !empty($skyRules) && $cloud <= ($skyRules[0]['max'] ?? 10);
            if ($isClearSkies) {
                $parts[] = __('nlg.templates.expect_sky_clear');
            } else {
                $parts[] = __('nlg.templates.expect_sky', ['sky' => $sky]);
            }
        }

        // Precipitation sentence: uses numeric probability logic
        $precipSentence = $this->precipSentence($d);
        if ($precipSentence) {
            $parts[] = $precipSentence;
        }

        // Wind sentence
        $wind = Arr::get($d, 'wind_ms');
        if (is_numeric($wind)) {
            $windLabel = $this->label('forecast.wind', (float)$wind);
            $windDir = $this->windDirText(Arr::get($d, 'wind_dir_deg'));
            if ($windDir) {
                $parts[] = __('nlg.templates.wind_with_direction', [
                    'wind' => $windLabel,
                    'direction' => $windDir
                ]);
            } else {
                $parts[] = __('nlg.templates.wind', ['wind' => $windLabel]);
            }
        }

        // Temperature range (null-filtering before casting)
        $min = Arr::get($d, 'min_temp_c');
        $max = Arr::get($d, 'max_temp_c');
        
        if (is_numeric($min) && is_numeric($max)) {
            $parts[] = __('nlg.templates.temps_range', [
                'min' => $this->fmtTemp($min),
                'max' => $this->fmtTemp($max),
            ]);
        } elseif (is_numeric($max)) {
            $parts[] = __('nlg.templates.temps_high', ['max' => $this->fmtTemp($max)]);
        }

        return $this->clean(implode(' ', $parts));
    }

    /**
     * Generate a forecast from multiple time periods (morning/afternoon/evening).
     */
    public function periods(array $payload): string
    {
        $periods = Arr::get($payload, 'periods', []);
        if (!is_array($periods) || count($periods) === 0) {
            // fall back if no periods
            return $this->daily($payload);
        }

        // Normalize period names
        $periods = array_values($periods);
        
        // Check if this is today (for "this morning" vs "in the morning")
        $date = Arr::get($payload, 'date');
        $isToday = $date && $date === date('Y-m-d');
        $timePrefix = $this->timePrefix((bool) $isToday);

        $parts = [];

        // Describe sky conditions with changes over time
        $skyDescription = $this->skyPatternSentence($periods, $timePrefix);
        if ($skyDescription) {
            $parts[] = $skyDescription;
        }

        // Determine precipitation pattern with specific timing
        $precipPattern = $this->precipPatternSentence($periods, $timePrefix);
        if ($precipPattern) {
            $parts[] = $precipPattern;
        }

        // Wind: if stable, one sentence; if changes significantly, mention shift with timing
        $windSentence = $this->windPatternSentence($periods, $timePrefix);
        if ($windSentence) {
            $parts[] = $windSentence;
        }

        // Temperature: overall range with mention of changes if significant
        $tempSentence = $this->temperaturePatternSentence($periods, $timePrefix);
        if ($tempSentence) {
            $parts[] = $tempSentence;
        }

        return $this->clean(implode(' ', $parts));
    }

    // -----------------------------
    // Phrase builders
    // -----------------------------

    /**
     * Build precipitation sentence using numeric probability logic and noise reduction.
     */
    private function precipSentence(array $data): ?string
    {
        // Extract values with proper null handling
        $prob = Arr::get($data, 'precip_prob_pct');
        $amt  = Arr::get($data, 'precip_mm', 0);
        $type = Arr::get($data, 'precip_type', 'none');

        $prob = is_numeric($prob) ? (float)$prob : null;
        $amt  = is_numeric($amt) ? (float)$amt : 0.0;
        $type = is_string($type) ? strtolower(trim($type)) : 'none';

        $minAmt  = (float) \App\Models\Setting::getValue('nlg.min_amount', 0.1);
        $minProb = (float) \App\Models\Setting::getValue('nlg.min_prob', 60);

        // Noise reduction: skip if type is none/dry AND amount is negligible AND probability is low
        if (($type === 'none' || $type === '') && $amt < $minAmt && (($prob ?? 0) < $minProb)) {
            return null;
        }

        $typeLabel = $this->precipTypeLabel($type);

        // If amount is meaningful, mention it
        if ($amt >= $minAmt) {
            $intensity = $this->label('forecast.precip_mm', $amt);
            if ($intensity === 'dry') {
                return null;
            }
            $probLabel = $prob !== null ? $this->label('forecast.precip_prob', $prob) : null;
            if ($probLabel && $probLabel !== 'very unlikely' && $probLabel !== 'a small chance') {
                return ucfirst("{$intensity} {$typeLabel} is {$probLabel}.");
            }
            return ucfirst("Expect {$intensity} {$typeLabel}.");
        }

        // No amount, only probability (only if high enough - numeric comparison)
        if ($prob !== null && $prob >= $minProb) {
            $probLabel = $this->label('forecast.precip_prob', $prob);
            if ($probLabel === 'likely' || $probLabel === 'very likely' || $probLabel === 'almost certain') {
                return ucfirst("There's a good chance of {$typeLabel}.");
            }
            return ucfirst("There's a chance of {$typeLabel}.");
        }

        return null;
    }

    private function precipPatternSentence(array $periods, string $timePrefix = 'in the'): ?string
    {
        // Determine which periods have meaningful precip (using numeric thresholds)
        $wetPeriods = [];
        $minAmt = (float) \App\Models\Setting::getValue('nlg.min_amount', 0.1);
        $minProb = (float) \App\Models\Setting::getValue('nlg.min_prob', 60);

        foreach ($periods as $p) {
            $name = (string)Arr::get($p, 'name', 'period');
            $prob = Arr::get($p, 'precip_prob_pct');
            $mm   = Arr::get($p, 'precip_mm', 0);
            $type = (string)Arr::get($p, 'precip_type', 'none');

            $prob = is_numeric($prob) ? (float)$prob : 0;
            $mm = is_numeric($mm) ? (float)$mm : 0.0;
            $type = strtolower(trim($type));

            // Use numeric comparison: meaningful if amount OR (high prob AND type is not none)
            $isWet = ($mm >= $minAmt) || ($prob >= $minProb && $type !== 'none' && $type !== '');
            if ($isWet) {
                $intensity = $mm >= $minAmt ? $this->label('forecast.precip_mm', $mm) : 'some';
                $wetPeriods[] = [
                    'name' => $name,
                    'intensity' => $intensity,
                    'mm' => $mm,
                ];
            }
        }

        if (count($wetPeriods) === 0) {
            return __('nlg.templates.staying_dry');
        }

        // Build detailed precipitation description
        if (count($wetPeriods) === count($periods)) {
            // All periods have rain
            $intensity = $this->getDominantIntensity($wetPeriods);
            return __('nlg.templates.precip_all_day_detailed', [
                'intensity' => $intensity,
                'time' => $timePrefix,
            ]);
        }

        // Specific period descriptions
        $first = $wetPeriods[0];
        $last = end($wetPeriods);
        
        if (count($wetPeriods) === 1) {
            // Only one period
            $periodName = $this->translatePeriod($first['name']);
            return __('nlg.templates.precip_single_period', [
                'intensity' => $first['intensity'],
                'period' => $periodName,
                'time' => $timePrefix,
            ]);
        }

        // Multiple periods
        if ($first['name'] === 'morning' && $last['name'] !== 'evening') {
            return __('nlg.templates.precip_morning_easing_detailed', [
                'intensity' => $first['intensity'],
                'time' => $timePrefix,
            ]);
        }

        if ($first['name'] === 'morning' && $last['name'] === 'morning') {
            return __('nlg.templates.precip_morning_detailed', [
                'intensity' => $first['intensity'],
                'time' => $timePrefix,
            ]);
        }

        if ($first['name'] === 'afternoon' && $last['name'] === 'afternoon') {
            return __('nlg.templates.precip_afternoon_detailed', [
                'intensity' => $first['intensity'],
                'time' => $timePrefix,
            ]);
        }

        if ($first['name'] === 'evening' && $last['name'] === 'evening') {
            return __('nlg.templates.precip_evening_detailed', [
                'intensity' => $first['intensity'],
                'time' => $timePrefix,
            ]);
        }

        // Multiple periods with transitions
        $periodNames = array_map(fn($p) => $this->translatePeriod($p['name']), $wetPeriods);
        $periodsText = implode(' and ', array_map(fn($n) => "{$timePrefix} {$n}", $periodNames));
        return __('nlg.templates.precip_multiple_periods', [
            'periods' => $periodsText,
            'intensity' => $first['intensity'],
        ]);
    }
    
    private function translatePeriod(string $period): string
    {
        return match($period) {
            'morning' => __('nlg.periods.morning'),
            'afternoon' => __('nlg.periods.afternoon'),
            'evening' => __('nlg.periods.evening'),
            default => $period,
        };
    }

    /**
     * Locale-aware "this"/"in the" prefix glued in front of a period name (e.g. "this afternoon",
     * "deze middag"). Falls back to English when a locale has not defined the keys, so an
     * untranslated locale keeps its current behaviour instead of emitting a raw translation key.
     */
    private function timePrefix(bool $isToday): string
    {
        $key = $isToday ? 'nlg.time_prefix.today' : 'nlg.time_prefix.other';
        $value = __($key);

        // A present-but-empty value is intentional (some languages' period words already read as
        // "in the morning", so they take no prefix). Only fall back when the key is genuinely
        // missing — i.e. the translator returns the dotted key unchanged.
        if (!is_string($value) || $value === $key) {
            return $isToday ? 'this' : 'in the';
        }

        return $value;
    }
    
    private function skyPatternSentence(array $periods, string $timePrefix = 'in the'): ?string
    {
        $cloudValues = [];
        $periodClouds = [];
        
        foreach ($periods as $p) {
            $name = (string)Arr::get($p, 'name', 'period');
            $cloud = Arr::get($p, 'cloud_pct');
            if (is_numeric($cloud)) {
                $cloudVal = (float)$cloud;
                $cloudValues[] = $cloudVal;
                $periodClouds[$name] = $this->label('forecast.sky', $cloudVal);
            }
        }

        if (count($cloudValues) === 0) {
            return null;
        }

        $avgCloud = $this->avg($cloudValues);
        $avgSky = $this->label('forecast.sky', $avgCloud);

        // Check for significant changes
        $uniqueSkies = array_unique(array_values($periodClouds));
        if (count($uniqueSkies) === 1) {
            // Consistent sky condition - check cloud percentage for clear skies
            if ($avgCloud <= 10) {
                return __('nlg.templates.expect_sky_clear');
            }
            return __('nlg.templates.expect_sky', ['sky' => $avgSky]);
        }

        // Sky conditions change - describe the pattern
        $firstPeriod = $periods[0];
        $lastPeriod = end($periods);
        $firstCloud = Arr::get($firstPeriod, 'cloud_pct');
        $lastCloud = Arr::get($lastPeriod, 'cloud_pct');
        
        if (is_numeric($firstCloud) && is_numeric($lastCloud)) {
            $firstSky = $this->label('forecast.sky', (float)$firstCloud);
            $lastSky = $this->label('forecast.sky', (float)$lastCloud);
            
            if ($firstCloud > $lastCloud + 20) {
                // Clearing
                return __('nlg.templates.sky_clearing', [
                    'start' => $firstSky,
                    'time' => $timePrefix,
                    'period' => $this->translatePeriod($firstPeriod['name']),
                ]);
            } elseif ($lastCloud > $firstCloud + 20) {
                // Clouding over
                return __('nlg.templates.sky_clouding', [
                    'start' => $firstSky,
                    'time' => $timePrefix,
                    'period' => $this->translatePeriod($lastPeriod['name']),
                ]);
            }
        }

        // Default: average condition - check cloud percentage for clear skies
        if ($avgCloud <= 10) {
            return __('nlg.templates.expect_sky_clear');
        }
        return __('nlg.templates.expect_sky', ['sky' => $avgSky]);
    }
    
    private function temperaturePatternSentence(array $periods, string $timePrefix = 'in the'): ?string
    {
        $temps = [];
        $periodTemps = [];
        
        foreach ($periods as $p) {
            $name = (string)Arr::get($p, 'name', 'period');
            $temp = Arr::get($p, 'temp_c');
            if (is_numeric($temp)) {
                $tempVal = (float)$temp;
                $temps[] = $tempVal;
                $periodTemps[$name] = $tempVal;
            }
        }

        if (count($temps) === 0) {
            return null;
        }

        $min = min($temps);
        $max = max($temps);
        $threshold = (float)config('forecast.thresholds.temp_change_c', 5.0);

        // If temperature changes significantly, mention it
        if (($max - $min) >= $threshold && count($periodTemps) > 1) {
            $warmestPeriod = array_search($max, $periodTemps);
            $coolestPeriod = array_search($min, $periodTemps);
            
            return __('nlg.templates.temps_changing', [
                'min' => $this->fmtTemp($min),
                'max' => $this->fmtTemp($max),
                'warmest' => $this->translatePeriod($warmestPeriod),
                'coolest' => $this->translatePeriod($coolestPeriod),
                'time' => $timePrefix,
            ]);
        }

        // Stable temperature
        return __('nlg.templates.temps_range', [
            'min' => $this->fmtTemp($min),
            'max' => $this->fmtTemp($max),
        ]);
    }
    
    private function getDominantIntensity(array $wetPeriods): string
    {
        $intensities = array_map(fn($p) => $p['intensity'], $wetPeriods);
        $counts = array_count_values($intensities);
        arsort($counts);
        return array_key_first($counts) ?? 'some';
    }

    private function windPatternSentence(array $periods, string $timePrefix = 'in the'): ?string
    {
        $threshold = (float)config('forecast.thresholds.wind_change_ms', 3.0);

        // Null-filtering before casting
        $winds = [];
        $periodWinds = [];
        $periodDirs = [];
        
        foreach ($periods as $p) {
            $name = (string)Arr::get($p, 'name', 'period');
            $wind = Arr::get($p, 'wind_ms');
            $dir = Arr::get($p, 'wind_dir_deg');
            
            if (is_numeric($wind)) {
                $windVal = (float)$wind;
                $winds[] = $windVal;
                $periodWinds[$name] = $windVal;
            }
            if (is_numeric($dir)) {
                $periodDirs[$name] = $this->windDirText($dir);
            }
        }

        if (count($winds) === 0) {
            return null;
        }

        $min = min($winds);
        $max = max($winds);
        $avg = $this->avg($winds);
        $avgLabel = $this->label('forecast.wind', $avg);

        // Get dominant direction
        $dirs = array_filter($periodDirs);
        $dir = !empty($dirs) ? $this->getDominantDirection($dirs) : null;

        // If wind changes significantly, describe the pattern
        if (($max - $min) >= $threshold && count($periodWinds) > 1) {
            $strongestPeriod = array_search($max, $periodWinds);
            $lightestPeriod = array_search($min, $periodWinds);
            
            $minLabel = $this->label('forecast.wind', $min);
            $maxLabel = $this->label('forecast.wind', $max);
            
            if ($dir) {
            return __('nlg.templates.wind_changing_with_direction', [
                'min' => $minLabel,
                'max' => $maxLabel,
                'strongest' => $this->translatePeriod($strongestPeriod),
                'lightest' => $this->translatePeriod($lightestPeriod),
                'direction' => $dir,
                'time' => $timePrefix,
            ]);
        }
        
        return __('nlg.templates.wind_changing', [
            'min' => $minLabel,
            'max' => $maxLabel,
            'strongest' => $this->translatePeriod($strongestPeriod),
            'lightest' => $this->translatePeriod($lightestPeriod),
            'time' => $timePrefix,
        ]);
        }

        // Stable wind
        if ($dir) {
            return __('nlg.templates.wind_with_direction', [
                'wind' => $avgLabel,
                'direction' => $dir
            ]);
        }
        return __('nlg.templates.wind', ['wind' => $avgLabel]);
    }
    
    private function getDominantDirection(array $directions): ?string
    {
        if (empty($directions)) {
            return null;
        }
        $counts = array_count_values($directions);
        arsort($counts);
        return array_key_first($counts);
    }

    private function precipTypeLabel(string $type): string
    {
        $t = strtolower(trim($type));
        return match ($t) {
            'rain' => 'rain',
            'snow' => 'snow',
            'sleet' => 'sleet',
            'hail' => 'hail',
            'thunderstorm', 'tstorm', 'storm' => 'thunderstorms',
            'none', '' => 'precipitation',
            default => 'precipitation',
        };
    }

    // -----------------------------
    // Utility helpers
    // -----------------------------

    private function label(string $configKey, float $value): string
    {
        // Try to get translated labels first, fallback to config
        $transKey = str_replace('forecast.', '', $configKey);
        $locale = app()->getLocale();
        
        // Try full locale first (e.g., nl-nl, de-de)
        $transPath = resource_path("lang/{$locale}/forecast.php");
        if (file_exists($transPath)) {
            $transFile = require $transPath;
            if (isset($transFile[$transKey]) && is_array($transFile[$transKey])) {
                $rules = $transFile[$transKey];
                foreach ($rules as $rule) {
                    if ($value <= $rule['max']) {
                        return $rule['text'];
                    }
                }
            }
        }
        
        // Fallback to base locale if full locale not found (e.g., nl if nl-nl not found)
        $baseLocale = explode('-', $locale)[0] ?? $locale;
        if ($baseLocale !== $locale) {
            $transPath = resource_path("lang/{$baseLocale}/forecast.php");
            if (file_exists($transPath)) {
                $transFile = require $transPath;
                if (isset($transFile[$transKey]) && is_array($transFile[$transKey])) {
                    $rules = $transFile[$transKey];
                    foreach ($rules as $rule) {
                        if ($value <= $rule['max']) {
                            return $rule['text'];
                        }
                    }
                }
            }
        }
        
        // Final fallback to config if translation not found
        $rules = config($configKey, []);
        foreach ($rules as $rule) {
            if ($value <= $rule['max']) {
                return $rule['text'];
            }
        }
        return '';
    }

    private function fmtTemp($t): ?string
    {
        if (!is_numeric($t)) {
            return null;
        }

        $celsius = (float) $t;

        if ($this->scale === 'f') {
            return round($celsius * 9 / 5 + 32) . '°F';
        }

        return round($celsius) . '°C';
    }

    /**
     * Convert degrees to full compass direction text (north, northeast, east, ...).
     * Returns null if input is missing.
     */
    private function windDirText($deg): ?string
    {
        if (!is_numeric($deg)) {
            return null;
        }
        $deg = fmod((float)$deg, 360.0);
        if ($deg < 0) {
            $deg += 360.0;
        }

        // Use full words for more natural language
        $dirs = [
            __('nlg.directions.north'),
            __('nlg.directions.northeast'),
            __('nlg.directions.east'),
            __('nlg.directions.southeast'),
            __('nlg.directions.south'),
            __('nlg.directions.southwest'),
            __('nlg.directions.west'),
            __('nlg.directions.northwest'),
        ];
        $idx = (int)round($deg / 45.0) % 8;
        return $dirs[$idx];
    }

    private function avg(array $values): float
    {
        if (count($values) === 0) {
            return 0.0;
        }
        return array_sum($values) / count($values);
    }

    private function clean(string $s): string
    {
        // remove accidental double spaces, etc.
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }
}
