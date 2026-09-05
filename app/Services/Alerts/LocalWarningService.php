<?php

namespace App\Services\Alerts;

use App\Models\Setting;
use App\Services\River\RiverProviderRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LocalWarningService
{
    private const SEVERITY_COLORS = [
        2 => '#FBEA55',
        3 => '#F19E39',
        4 => '#BB2739',
    ];

    private const STATUS_COLORS = [
        0 => '#4ade80', // green — normal
        2 => '#FBEA55', // yellow — caution
        3 => '#F19E39', // orange — warning
        4 => '#BB2739', // red — danger
    ];

    private const CACHE_KEY_PREFIX = 'local_warnings';
    private const CACHE_TTL = 900; // 15 minutes

    public function getWarnings(): array
    {
        $locale = app()->getLocale();
        $cacheKey = self::CACHE_KEY_PREFIX . '_' . $locale;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $warnings = [];

            $checks = [
                fn () => $this->checkLightning(),
                fn () => $this->checkRivers(),
                fn () => $this->checkAirQuality(),
                fn () => $this->checkUv(),
                fn () => $this->checkPollen(),
                fn () => $this->checkWaves(),
                fn () => $this->checkFireWeather(),
                fn () => $this->checkFrost(),
            ];

            foreach ($checks as $check) {
                try {
                    $result = $check();
                    if ($result === null) continue;
                    // checkRivers() may return an array of warnings
                    if (isset($result[0]) && is_array($result[0])) {
                        $warnings = array_merge($warnings, $result);
                    } else {
                        $warnings[] = $result;
                    }
                } catch (\Throwable $e) {
                    Log::debug('LocalWarningService: source check failed', ['error' => $e->getMessage()]);
                }
            }

            return $warnings;
        });
    }

    private function make(int $severity, string $type, string $typeLabel, string $title, string $description, ?string $link = null, ?string $sourceLabel = null): array
    {
        return [
            'title'              => $title,
            'description'        => $description,
            'severity'           => $severity,
            'severity_color'     => self::SEVERITY_COLORS[$severity] ?? self::SEVERITY_COLORS[2],
            'warning_type'       => $type,
            'warning_type_label' => $typeLabel,
            'source'             => 'internal',
            'source_label'       => $sourceLabel ?? $typeLabel,
            'link'               => $link,
            'region'             => Setting::getValue('alerts.region_name', ''),
        ];
    }

    private function makeStatus(string $type, string $label, int $severity, string $statusLabel, string $value, ?string $link = null): array
    {
        return [
            'type'         => $type,
            'label'        => $label,
            'value'        => $value,
            'severity'     => $severity,
            'status_label' => $statusLabel,
            'color'        => self::STATUS_COLORS[$severity] ?? self::STATUS_COLORS[0],
            'link'         => $link,
        ];
    }

    // ── Status sections (always visible, severity 0 = normal/green) ──────────

    public function getStatusSections(): array
    {
        $sections = [];

        $methods = [
            fn () => $this->statusUv(),
            fn () => $this->statusAirQuality(),
            fn () => $this->statusPollen(),
            fn () => $this->statusWaves(),
            fn () => $this->statusLightning(),
            fn () => $this->statusFireWeather(),
            fn () => $this->statusRivers(),
        ];

        foreach ($methods as $method) {
            try {
                $result = $method();
                if ($result === null) continue;
                // statusRivers() may return an array of sections
                if (isset($result[0]) && is_array($result[0])) {
                    $sections = array_merge($sections, $result);
                } else {
                    $sections[] = $result;
                }
            } catch (\Throwable $e) {
                Log::debug('LocalWarningService::getStatusSections source failed', ['error' => $e->getMessage()]);
            }
        }

        return $sections;
    }

    private function statusUv(): ?array
    {
        $lat    = Setting::latitude();
        $lon    = Setting::longitude();
        $source = Setting::getValue('forecast.default_source', 'fct_yrno_block.php');


        $forecastData = Cache::get(\App\Support\ForecastCacheKeys::forSource($source, $lat, $lon))
                     ?? Cache::get("forecast_{$lat}_{$lon}");

        if (!is_array($forecastData)) return null;

        $forecast = $forecastData['forecast'] ?? null;
        if (!is_array($forecast)) return null;

        $uvMax = 0.0;
        $count = 0;
        foreach ($forecast as $entry) {
            if ($count >= 24) break;
            $uv = (float) ($entry['uv_index'] ?? $entry['uv'] ?? 0);
            if ($uv > $uvMax) $uvMax = $uv;
            $count++;
        }

        $sev = match (true) {
            $uvMax >= 11 => 4,
            $uvMax >= 8  => 3,
            $uvMax >= 6  => 2,
            default      => 0,
        };

        return $this->makeStatus('uv', 'UV', $sev, $this->uvCategory($uvMax), 'UVI ' . (int) $uvMax, route('forecast'));
    }

    private function statusAirQuality(): ?array
    {
        $lat         = Setting::latitude();
        $lon         = Setting::longitude();
        $stationMode = Setting::getValue('waqi.station_mode', 'auto');
        $stationId   = Setting::getValue('waqi.station_id', '');

        $baseKey = ($stationMode === 'manual' && !empty($stationId))
            ? "waqi_station_{$stationId}"
            : "waqi_{$lat}_{$lon}";

        $data = Cache::get($baseKey . '_us')
             ?? Cache::get($baseKey . '_eea')
             ?? Cache::get($baseKey);

        if (!is_array($data)) return null;

        $aqi = (float) ($data['aqi'] ?? 0);
        $sev = match (true) {
            $aqi >= 200 => 4,
            $aqi >= 150 => 3,
            $aqi >= 100 => 2,
            default     => 0,
        };

        $category = $data['category']['level'] ?? '';
        if ($category === '') {
            $category = match ($sev) {
                4       => __('Unhealthy'),
                3       => __('Unhealthy for sensitive'),
                2       => __('Moderate'),
                default => __('Good'),
            };
        }

        return $this->makeStatus('air-quality', __('Air quality'), $sev, $category, 'AQI ' . (int) $aqi, route('airquality'));
    }

    private function statusPollen(): ?array
    {
        $data = Cache::get('pollen_forecast');
        if (!is_array($data)) return null;

        $today = $data['today'] ?? null;
        if (!is_array($today)) return null;

        $riskIndex = (int) ($today['overall_risk_index'] ?? 0);
        $riskLabel = $today['overall_risk'] ?? '';

        $sev = match (true) {
            $riskIndex >= 4 => 3,
            $riskIndex >= 2 => 2,
            default         => 0,
        };

        $statusLabel = $riskLabel ?: match ($riskIndex) {
            0       => __('None'),
            1       => __('Low'),
            2       => __('Moderate'),
            3       => __('High'),
            default => __('Very high'),
        };

        return $this->makeStatus('pollen', __('Pollen'), $sev, $statusLabel, __('index') . ' ' . $riskIndex, route('pollen'));
    }

    private function statusWaves(): ?array
    {
        $lat  = round((float) Setting::latitude(), 2);
        $lon  = round((float) Setting::longitude(), 2);
        $data = Cache::get("waves_{$lat}_{$lon}");

        if (!is_array($data)) {
            return ['type' => 'waves', 'label' => __('Waves'), 'value' => '—',
                    'severity' => 0, 'status_label' => __('No data'), 'color' => '#6b7280',
                    'link' => route('water.waves')];
        }

        $height = (float) ($data['current_wave_height_m'] ?? 0);

        $sev = match (true) {
            $height >= 6.0  => 4,
            $height >= 4.0  => 3,
            $height >= 2.5  => 2,
            default         => 0,
        };

        $statusLabel = match (true) {
            $height >= 6.0  => __('Extreme'),
            $height >= 4.0  => __('Very rough'),
            $height >= 2.5  => __('Rough'),
            $height >= 1.25 => __('Moderate'),
            default         => __('Calm'),
        };

        return $this->makeStatus('waves', __('Waves'), $sev, $statusLabel, number_format($height, 1) . ' m', route('water.waves'));
    }

    private function statusLightning(): ?array
    {
        $enabled = filter_var(Setting::getValue('lightning.enabled', false), FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) return null;

        $data = Cache::get('boltek_lightning');

        if (!is_array($data)) {
            return ['type' => 'lightning', 'label' => __('Lightning'), 'value' => '—',
                    'severity' => 0, 'status_label' => __('No data'), 'color' => '#6b7280',
                    'link' => route('home')];
        }

        $summary  = $data['summary'] ?? [];
        $activity = $summary['activity_level'] ?? 'none';
        $close    = (int) ($summary['close_strikes'] ?? 0);
        $total    = (int) ($summary['total_strikes'] ?? 0);

        $sev = match (true) {
            $close >= 10                                     => 4,
            $close >= 5                                      => 3,
            $activity === 'moderate' && $total >= 10         => 2,
            $activity !== 'none' && $activity !== '' && $total > 0 => 2,
            default                                          => 0,
        };

        $statusLabel = match ($sev) {
            4       => __('Severe'),
            3       => __('Active'),
            2       => __('Moderate'),
            default => __('Clear'),
        };

        $value = $total > 0
            ? $total . ' ' . __('strikes')
            : __('No activity');

        return $this->makeStatus('lightning', __('Lightning'), $sev, $statusLabel, $value, route('home'));
    }

    private function statusFireWeather(): ?array
    {
        $data = Cache::get('fire_weather_current');
        if (!is_array($data)) {
            try {
                $calc = new \App\Services\FireWeatherCalculator();
                $data = $calc->currentIndices();
                Cache::put('fire_weather_current', $data, 3600);
            } catch (\Throwable $e) {
                return null;
            }
        }
        if (!is_array($data)) return null;

        $level    = $data['danger_level'] ?? 'low';
        $angstrom = isset($data['angstrom']) ? number_format((float) $data['angstrom'], 1) : null;

        $sev = match ($level) {
            'extreme'  => 4,
            'high'     => 3,
            'moderate' => 2,
            default    => 0,
        };

        $value = $angstrom !== null ? 'Ångström ' . $angstrom : ucfirst($level);

        return $this->makeStatus('fire', __('Fire risk'), $sev, ucfirst(__($level)), $value, route('fire-weather'));
    }

    private function statusRivers(): ?array
    {
        $sections = [];

        foreach (RiverProviderRegistry::active() as $providerId => $providerInfo) {
            $enabled = filter_var(
                RiverProviderRegistry::getSetting($providerId, 'enabled', false),
                FILTER_VALIDATE_BOOLEAN
            );
            if (!$enabled) continue;

            $data = Cache::get(RiverProviderRegistry::cacheKey($providerId));
            if (!is_array($data)) continue;

            $worstSev     = 0;
            $stationCount = 0;
            $watchCount   = 0;
            $warningCount = 0;
            $subRows      = [];

            foreach ($data as $stationCode => $station) {
                if (!is_array($station)) continue;
                $stationCount++;

                $status = $station['status'] ?? 'normal';
                $sev    = match ($status) {
                    'warning' => 3,
                    'watch'   => 2,
                    default   => 0,
                };

                if ($sev > 0) {
                    $worstSev = max($worstSev, $sev);
                    $sev === 3 ? $warningCount++ : $watchCount++;

                    $name       = $station['name'] ?? $stationCode;
                    $river      = $station['river'] ?? '';
                    $level      = $station['level_cm'] ?? null;
                    $trend      = $station['trend'] ?? 'steady';
                    $trendArrow = match ($trend) { 'rising' => ' ↑', 'falling' => ' ↓', default => '' };
                    $value      = $level !== null ? round((float) $level) . ' cm' . $trendArrow : '—';
                    $label      = $river ? $name . ' · ' . $river : $name;
                    $statusLabel = $sev === 3 ? __('Warning') : __('Watch');

                    $row        = $this->makeStatus('flood', $label, $sev, $statusLabel, $value, route('water.rivers'));
                    $row['sub'] = true;
                    $subRows[]  = $row;
                }
            }

            if ($stationCount === 0) continue;

            $summaryStatus = match ($worstSev) {
                3       => __('Warning'),
                2       => __('Watch'),
                default => __('Normal'),
            };

            $summaryValue = $stationCount . ' ' . ($stationCount === 1 ? __('station') : __('stations'));
            if ($warningCount > 0) $summaryValue .= ' · ' . $warningCount . ' ⚠';
            elseif ($watchCount > 0) $summaryValue .= ' · ' . $watchCount . ' ↑';

            // Provider aggregate row (always shown)
            $sections[] = $this->makeStatus('flood', $providerInfo['name'] ?? __('Rivers'), $worstSev, $summaryStatus, $summaryValue, route('water.rivers'));

            // Elevated station sub-rows (only when watch/warning)
            foreach ($subRows as $sub) {
                $sections[] = $sub;
            }
        }

        return empty($sections) ? null : $sections;
    }

    // ── Lightning ────────────────────────────────────────────────────────────

    private function checkLightning(): ?array
    {
        $data = Cache::get('boltek_lightning');
        if (!is_array($data)) return null;

        $summary  = $data['summary'] ?? [];
        $activity = $summary['activity_level'] ?? 'none';
        $close    = (int) ($summary['close_strikes'] ?? 0);
        $total    = (int) ($summary['total_strikes'] ?? 0);

        if ($close >= 10) {
            $sev = 4;
        } elseif ($close >= 5) {
            $sev = 3;
        } elseif ($activity === 'moderate' && $total >= 10) {
            $sev = 2;
        } else {
            return null;
        }

        $label = __('Lightning');
        return $this->make(
            $sev, 'lightning', $label,
            $label . ' — ' . $close . ' ' . __('close strikes'),
            __('Lightning activity detected nearby. Stay indoors and away from open areas.')
        );
    }

    // ── Rivers ───────────────────────────────────────────────────────────────

    private function checkRivers(): ?array
    {
        $warnings = [];

        foreach (array_keys(RiverProviderRegistry::active()) as $providerId) {
            $data = Cache::get(RiverProviderRegistry::cacheKey($providerId));
            if (!is_array($data)) continue;

            foreach ($data as $stationCode => $station) {
                if (!is_array($station)) continue;

                $status = $station['status'] ?? 'normal';
                if ($status === 'normal') continue;

                $sev   = $status === 'warning' ? 3 : 2;
                $name  = $station['name'] ?? $stationCode;
                $river = $station['river'] ?? '';
                $level = $station['level_cm'] ?? null;
                $label = __('Flood risk');

                $title = $label . ' — ' . $name . ($river ? ' (' . $river . ')' : '');
                $desc  = $status === 'warning'
                    ? __('Rapid water level rise detected.')
                    : __('Rising water level detected.');
                if ($level !== null) {
                    $desc .= ' ' . __('Current level') . ': ' . round((float) $level) . ' cm';
                }

                $warnings[] = $this->make($sev, 'flood', $label, $title, $desc);
            }
        }

        return empty($warnings) ? null : $warnings;
    }

    // ── Air quality ──────────────────────────────────────────────────────────

    private function checkAirQuality(): ?array
    {
        $lat         = Setting::latitude();
        $lon         = Setting::longitude();
        $stationMode = Setting::getValue('waqi.station_mode', 'auto');
        $stationId   = Setting::getValue('waqi.station_id', '');

        $baseKey  = ($stationMode === 'manual' && !empty($stationId))
            ? "waqi_station_{$stationId}"
            : "waqi_{$lat}_{$lon}";

        $data = Cache::get($baseKey . '_us')
             ?? Cache::get($baseKey . '_eea')
             ?? Cache::get($baseKey);

        if (!is_array($data)) return null;

        $aqi = (float) ($data['aqi'] ?? 0);
        if ($aqi < 100) return null;

        $sev = match (true) {
            $aqi >= 200 => 4,
            $aqi >= 150 => 3,
            default     => 2,
        };

        $category = $data['category']['level'] ?? '';
        $label    = __('Air quality');

        return $this->make(
            $sev, 'air-quality', $label,
            $label . ' — AQI ' . (int) $aqi . ($category ? ' (' . $category . ')' : ''),
            __('Air quality is at an unhealthy level. Sensitive groups should limit outdoor exposure.')
        );
    }

    // ── UV index ─────────────────────────────────────────────────────────────

    private function checkUv(): ?array
    {
        $lat    = Setting::latitude();
        $lon    = Setting::longitude();
        $source = Setting::getValue('forecast.default_source', 'fct_yrno_block.php');


        $forecastData = Cache::get(\App\Support\ForecastCacheKeys::forSource($source, $lat, $lon))
                     ?? Cache::get("forecast_{$lat}_{$lon}");

        if (!is_array($forecastData)) return null;

        $forecast = $forecastData['forecast'] ?? null;
        if (!is_array($forecast)) return null;

        $uvMax = 0.0;
        $count = 0;
        foreach ($forecast as $entry) {
            if ($count >= 24) break;
            $uv = (float) ($entry['uv_index'] ?? $entry['uv'] ?? 0);
            if ($uv > $uvMax) $uvMax = $uv;
            $count++;
        }

        if ($uvMax < 6) return null;

        $sev = match (true) {
            $uvMax >= 11 => 4,
            $uvMax >= 8  => 3,
            default      => 2,
        };

        $label    = 'UV';
        $category = $this->uvCategory($uvMax);

        return $this->make(
            $sev, 'uv', $label,
            $label . ' ' . __('index') . ': ' . (int) $uvMax . ' — ' . $category,
            __('High UV radiation expected. Apply sunscreen and limit direct sun exposure.')
        );
    }

    private function uvCategory(float $uv): string
    {
        return match (true) {
            $uv >= 11 => __('Extreme'),
            $uv >= 8  => __('Very high'),
            $uv >= 6  => __('High'),
            $uv >= 3  => __('Moderate'),
            default   => __('Low'),
        };
    }

    // ── Pollen ───────────────────────────────────────────────────────────────

    private function checkPollen(): ?array
    {
        $data = Cache::get('pollen_forecast');
        if (!is_array($data)) return null;

        $today     = $data['today'] ?? null;
        if (!is_array($today)) return null;

        $riskIndex = (int) ($today['overall_risk_index'] ?? 0);
        if ($riskIndex < 2) return null;

        $sev       = $riskIndex >= 4 ? 3 : 2;
        $riskLabel = $today['overall_risk'] ?? '';
        $label     = __('Pollen');

        return $this->make(
            $sev, 'pollen', $label,
            $label . ($riskLabel ? ' — ' . $riskLabel : ''),
            __('Elevated pollen levels. Allergy sufferers should take precautions and consider medication.')
        );
    }

    // ── Waves ────────────────────────────────────────────────────────────────

    private function checkWaves(): ?array
    {
        $lat  = round((float) Setting::latitude(), 2);
        $lon  = round((float) Setting::longitude(), 2);
        $data = Cache::get("waves_{$lat}_{$lon}");
        if (!is_array($data)) return null;

        $height = (float) ($data['current_wave_height_m'] ?? 0);
        if ($height < 2.5) return null;

        $sev   = match (true) {
            $height >= 6.0 => 4,
            $height >= 4.0 => 3,
            default        => 2,
        };

        $label = __('Waves');

        return $this->make(
            $sev, 'waves', $label,
            $label . ' — ' . number_format($height, 1) . ' m',
            __('Significant wave height. Exercise caution at sea and near the coast.')
        );
    }

    // ── Frost / slippery road ────────────────────────────────────────────────

    private function checkFrost(): ?array
    {
        $lat    = Setting::latitude();
        $lon    = Setting::longitude();
        $source = Setting::getValue('forecast.default_source', 'fct_yrno_block.php');


        $forecastData = Cache::get(\App\Support\ForecastCacheKeys::forSource($source, $lat, $lon))
                     ?? Cache::get("forecast_{$lat}_{$lon}");

        if (!is_array($forecastData)) return null;

        $forecast = $forecastData['forecast'] ?? null;
        if (!is_array($forecast)) return null;

        $minTemp = null;
        $count   = 0;
        foreach ($forecast as $entry) {
            if ($count >= 24) break;
            $temp = isset($entry['temperature']) ? (float) $entry['temperature'] : null;
            if ($temp !== null && ($minTemp === null || $temp < $minTemp)) {
                $minTemp = $temp;
            }
            $count++;
        }

        if ($minTemp === null || $minTemp > 2) return null;

        $sev   = $minTemp <= -2 ? 3 : 2;
        $label = __('Frost risk');
        $desc  = $sev === 3
            ? __('Sub-zero temperatures expected. Roads and paths may be icy.')
            : __('Near-zero temperatures expected. Roads may be slippery.');

        return $this->make($sev, 'frost', $label, $label, $desc);
    }

    // ── Fire weather ─────────────────────────────────────────────────────────

    private function checkFireWeather(): ?array
    {
        $data = Cache::get('fire_weather_current');

        if (!is_array($data)) {
            $calc = new \App\Services\FireWeatherCalculator();
            $data = $calc->currentIndices();
            Cache::put('fire_weather_current', $data, 3600);
        }

        $level = $data['danger_level'] ?? 'low';
        if (!in_array($level, ['high', 'extreme'], true)) return null;

        $sev      = $level === 'extreme' ? 4 : 3;
        $label    = __('Fire risk');
        $dryDays  = (int) ($data['consecutive_dry'] ?? 0);

        $desc = __('Fire weather risk is elevated. Avoid open fires and follow local guidelines.');
        if ($dryDays >= 7) {
            $desc .= ' ' . $dryDays . ' ' . __('consecutive dry days') . '.';
        }

        return $this->make(
            $sev, 'fire', $label,
            $label . ' — ' . ucfirst(__($level)),
            $desc
        );
    }
}
