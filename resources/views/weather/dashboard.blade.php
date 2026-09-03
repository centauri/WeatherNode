<!DOCTYPE html>
<html lang="{{ $jsLocale ?? app()->getLocale() }}" class="dark has-weather-bg">
@php
    $activeLocale = $activeLocale ?? app()->getLocale();
    $activeUnits = $activeUnits ?? 'metric';
    $jsLocale = $jsLocale ?? $activeLocale;
    $dashboardHybridSsrEnabled = (bool) ($dashboardHybridSsrEnabled ?? false);
    $ssrDashboard = ($dashboardHybridSsrEnabled && isset($ssrDashboard) && is_array($ssrDashboard)) ? $ssrDashboard : null;
    $ssrCurrent = is_array($ssrDashboard['current'] ?? null) ? $ssrDashboard['current'] : null;
    $ssrEnabledWidgets = is_array($ssrDashboard['enabled_widgets'] ?? null) ? $ssrDashboard['enabled_widgets'] : [];
    $ssrForecast = is_array($ssrDashboard['forecast'] ?? null) ? $ssrDashboard['forecast'] : [];
    $ssrHourlyForecast = is_array($ssrDashboard['hourlyForecast'] ?? null) ? $ssrDashboard['hourlyForecast'] : [];
    $ssrAlerts = is_array($ssrDashboard['alerts'] ?? null) ? $ssrDashboard['alerts'] : [];
    $ssrEvents = is_array($ssrDashboard['astronomical_events'] ?? null) ? $ssrDashboard['astronomical_events'] : [];
    $ssrEarthquakes = is_array($ssrDashboard['earthquakes'] ?? null) ? $ssrDashboard['earthquakes'] : [];
    $ssrStation = is_array($ssrDashboard['station'] ?? null) ? $ssrDashboard['station'] : [];
    $ssrAirQuality = is_array($ssrDashboard['air_quality'] ?? null) ? $ssrDashboard['air_quality'] : [];
    $ssrPollen = is_array($ssrDashboard['pollen'] ?? null) ? $ssrDashboard['pollen'] : [];
    $ssrTide = is_array($ssrDashboard['tide'] ?? null) ? $ssrDashboard['tide'] : [];
    $ssrLuftdaten = is_array($ssrDashboard['luftdaten'] ?? null) ? $ssrDashboard['luftdaten'] : [];
    $ssrAurora = is_array($ssrDashboard['aurora'] ?? null) ? $ssrDashboard['aurora'] : [];
    $ssrSun = is_array($ssrDashboard['sun'] ?? null) ? $ssrDashboard['sun'] : [];
    $ssrMoon = is_array($ssrDashboard['moon'] ?? null) ? $ssrDashboard['moon'] : [];
    $ssrExtraSensors = is_array($ssrDashboard['extra_sensors'] ?? null) ? $ssrDashboard['extra_sensors'] : [];
    $ssrBatteryStatus = is_array($ssrDashboard['battery_status'] ?? null) ? $ssrDashboard['battery_status'] : [];
    $ssrLightning = is_array($ssrDashboard['lightning'] ?? null) ? $ssrDashboard['lightning'] : [];
    $ssrMetar = is_array($ssrDashboard['metar'] ?? null) ? $ssrDashboard['metar'] : [];
    $ssrWaterWaves = is_array($ssrDashboard['water_waves'] ?? null) ? $ssrDashboard['water_waves'] : [];
    $ssrDateLabel = \Carbon\Carbon::now($stationTimezone ?? \App\Models\Setting::timezone())
        ->locale($activeLocale)
        ->translatedFormat('D j M Y');
    $ssrTemperatureText = (isset($ssrCurrent['temperature']) && is_numeric($ssrCurrent['temperature']))
        ? (string) round((float) $ssrCurrent['temperature'], 1)
        : '--';
    $ssrHumidityText = (isset($ssrCurrent['humidity']) && is_numeric($ssrCurrent['humidity']))
        ? (string) round((float) $ssrCurrent['humidity']) . '%'
        : '--%';
    $ssrPressureText = (isset($ssrCurrent['pressure']) && is_numeric($ssrCurrent['pressure']))
        ? (string) round((float) $ssrCurrent['pressure'], 1)
        : '--';
    $ssrWindSpeedText = (isset($ssrCurrent['wind_speed']) && is_numeric($ssrCurrent['wind_speed']))
        ? (string) round((float) $ssrCurrent['wind_speed'], 1)
        : '--';
    $ssrWindGustText = (isset($ssrCurrent['wind_gust']) && is_numeric($ssrCurrent['wind_gust']))
        ? (string) round((float) $ssrCurrent['wind_gust'], 1)
        : '--';
    $ssrAlertSummary = __('No active alerts');
    if (!empty($ssrAlerts) && is_array($ssrAlerts[0] ?? null)) {
        $firstAlert = $ssrAlerts[0];
        $ssrAlertSummary = (string) ($firstAlert['title'] ?? $firstAlert['warning_type_label'] ?? __('Active weather alert'));
    }
    $ssrEarthquakeCountLabel = count($ssrEarthquakes) > 0 ? (string) count($ssrEarthquakes) : '✓';
    $ssrNextRainLabel = '--';
    if (!empty($ssrHourlyForecast) && is_array($ssrHourlyForecast[0] ?? null)) {
        $nextHourly = $ssrHourlyForecast[0];
        $nextRain = (float) ($nextHourly['precipitation_1h'] ?? 0);
        if ($nextRain > 0) {
            $ssrNextRainLabel = __('Now');
        } else {
            $ssrNextRainLabel = __('Dry 24h');
        }
    }
    $ssrTempAdvisoryLabel = '--';
    if (isset($ssrCurrent['temperature']) && is_numeric($ssrCurrent['temperature'])) {
        $tempValue = (float) $ssrCurrent['temperature'];
        if ($tempValue >= 30) {
            $ssrTempAdvisoryLabel = __('Extreme heat');
        } elseif ($tempValue <= 0) {
            $ssrTempAdvisoryLabel = __('Frost risk');
        } elseif ($tempValue < 10) {
            $ssrTempAdvisoryLabel = __('Chilly');
        } elseif ($tempValue < 20) {
            $ssrTempAdvisoryLabel = __('Mild');
        } elseif ($tempValue < 27) {
            $ssrTempAdvisoryLabel = __('Warm');
        } else {
            $ssrTempAdvisoryLabel = __('Tropical');
        }
    }
    $ssrBestOutdoorLabel = '--';
    if (!empty($ssrHourlyForecast) && is_array($ssrHourlyForecast[0] ?? null) && isset($ssrHourlyForecast[0]['time'])) {
        try {
            $ssrBestOutdoorLabel = \Carbon\Carbon::parse($ssrHourlyForecast[0]['time'])->timezone($stationTimezone ?? \App\Models\Setting::timezone())->format('H:i');
        } catch (\Throwable $e) {
            $ssrBestOutdoorLabel = __('Now');
        }
    }
    $ssrTideCurrentLevelLabel = isset($ssrTide['current_level_cm']) && is_numeric($ssrTide['current_level_cm'])
        ? (string) round((float) $ssrTide['current_level_cm'])
        : '--';
    $ssrTempVsYesterdayLabel = '--';
    if ($ssrDashboard) {
        $yesterdayHigh = \App\Models\DailySummary::whereDate('date', now()->subDay()->toDateString())->value('temp_high');
        $todayHigh = $ssrDashboard['today']['temp_high'] ?? null;
        if ($todayHigh !== null && $yesterdayHigh !== null && is_numeric($todayHigh) && is_numeric($yesterdayHigh)) {
            $diff = round((float) $todayHigh - (float) $yesterdayHigh, 1);
            if (abs($diff) < 1) {
                $ssrTempVsYesterdayLabel = __('Similar');
            } else {
                $ssrTempVsYesterdayLabel = ($diff > 0 ? '+' : '') . $diff . '°C';
            }
        }
    }
    $toTideTimeLabel = function ($entry) use ($stationTimezone) {
        if (!is_array($entry) || !isset($entry['timestamp_unix'])) {
            return '--';
        }
        $raw = (float) $entry['timestamp_unix'];
        if ($raw > 20000000000) {
            $raw = $raw / 1000;
        }
        try {
            return \Carbon\Carbon::createFromTimestamp((int) $raw)->timezone($stationTimezone ?? \App\Models\Setting::timezone())->format('H:i');
        } catch (\Throwable $e) {
            return '--';
        }
    };
    $ssrTideNextHighTimeLabel = $toTideTimeLabel($ssrTide['next_high'] ?? null);
    $ssrTideNextLowTimeLabel = $toTideTimeLabel($ssrTide['next_low'] ?? null);
    $ssrWidgetEnabled = fn (string $id): bool => in_array($id, $ssrEnabledWidgets, true);
    $ssrWidgetFlags = [
        'current' => $ssrWidgetEnabled('current'),
        'forecast' => $ssrWidgetEnabled('forecast'),
        'hourly' => $ssrWidgetEnabled('hourly'),
        'wind' => $ssrWidgetEnabled('wind'),
        'pressure' => $ssrWidgetEnabled('pressure'),
        'rain' => $ssrWidgetEnabled('rain'),
        'sun_moon' => $ssrWidgetEnabled('sun') || $ssrWidgetEnabled('moon') || $ssrWidgetEnabled('sun_moon'),
        'uv_solar' => $ssrWidgetEnabled('uv') || $ssrWidgetEnabled('solar') || $ssrWidgetEnabled('uv_solar'),
        'airquality' => $ssrWidgetEnabled('airquality'),
        'pollen' => $ssrWidgetEnabled('pollen'),
        'tide' => $ssrWidgetEnabled('tide'),
        'metar' => $ssrWidgetEnabled('metar'),
        'earthquakes' => $ssrWidgetEnabled('earthquakes'),
        'alerts' => $ssrWidgetEnabled('alerts'),
        'lightning' => $ssrWidgetEnabled('lightning'),
        'indoor' => $ssrWidgetEnabled('indoor'),
        'extra_temps' => $ssrWidgetEnabled('extra_temps'),
        'soil' => $ssrWidgetEnabled('soil'),
        'pm25' => $ssrWidgetEnabled('pm25'),
        'co2' => $ssrWidgetEnabled('co2'),
        'battery' => $ssrWidgetEnabled('battery'),
        'radar' => $ssrWidgetEnabled('radar'),
        'webcam' => $ssrWidgetEnabled('webcam'),
        'aurora' => $ssrWidgetEnabled('aurora'),
        'astro_events' => $ssrWidgetEnabled('astro_events'),
        'ads' => $ssrWidgetEnabled('ads'),
    ];
    $ssrHybridCards = [];
    if ($ssrDashboard) {
        if ($ssrWidgetFlags['current']) {
            $ssrHybridCards[] = [
                'id' => 'current',
                'title' => __('Current'),
                'lines' => [
                    __('Temperature') . ': ' . $ssrTemperatureText . '°C',
                    __('Humidity') . ': ' . $ssrHumidityText,
                    __('Pressure') . ': ' . $ssrPressureText . ' hPa',
                ],
            ];
        }
        if ($ssrWidgetFlags['forecast']) {
            $lines = [];
            foreach (array_slice($ssrForecast, 0, 3) as $day) {
                if (!is_array($day)) {
                    continue;
                }
                $date = (string) ($day['date'] ?? '--');
                $high = isset($day['temp_high']) && is_numeric($day['temp_high']) ? round((float) $day['temp_high']) . '°' : '--';
                $low = isset($day['temp_low']) && is_numeric($day['temp_low']) ? round((float) $day['temp_low']) . '°' : '--';
                $lines[] = $date . ': ' . $low . ' / ' . $high;
            }
            $ssrHybridCards[] = ['id' => 'forecast', 'title' => __('Forecast'), 'lines' => $lines ?: [__('No forecast data')]];
        }
        if ($ssrWidgetFlags['hourly']) {
            $lines = [];
            foreach (array_slice($ssrHourlyForecast, 0, 4) as $hour) {
                if (!is_array($hour)) {
                    continue;
                }
                $time = '--:--';
                if (!empty($hour['time'])) {
                    try {
                        $time = \Carbon\Carbon::parse($hour['time'])->timezone($stationTimezone ?? \App\Models\Setting::timezone())->format('H:i');
                    } catch (\Throwable $e) {
                        $time = '--:--';
                    }
                }
                $temp = isset($hour['temperature']) && is_numeric($hour['temperature']) ? round((float) $hour['temperature']) . '°' : '--';
                $rain = isset($hour['precipitation_1h']) && is_numeric($hour['precipitation_1h']) ? round((float) $hour['precipitation_1h'], 1) . ' mm' : '0 mm';
                $lines[] = $time . ': ' . $temp . ' · ' . $rain;
            }
            $ssrHybridCards[] = ['id' => 'hourly', 'title' => __('Hourly'), 'lines' => $lines ?: [__('No hourly data')]];
        }
        if ($ssrWidgetFlags['wind']) {
            $ssrHybridCards[] = [
                'id' => 'wind',
                'title' => __('Wind'),
                'lines' => [
                    __('Speed') . ': ' . $ssrWindSpeedText . ' km/h',
                    __('Gust') . ': ' . $ssrWindGustText . ' km/h',
                    __('Direction') . ': ' . __((string) ($ssrCurrent['wind_direction_compass'] ?? 'N')) . ' ' . ((string) ($ssrCurrent['wind_direction'] ?? '--')) . '°',
                ],
            ];
        }
        if ($ssrWidgetFlags['pressure']) {
            $ssrHybridCards[] = [
                'id' => 'pressure',
                'title' => __('Pressure'),
                'lines' => [
                    __('Current') . ': ' . $ssrPressureText . ' hPa',
                    __('Trend') . ': ' . ((string) ($ssrCurrent['pressure_trend'] ?? '--')),
                ],
            ];
        }
        if ($ssrWidgetFlags['rain']) {
            $ssrHybridCards[] = [
                'id' => 'rain',
                'title' => __('Precipitation'),
                'lines' => [
                    __('Rate') . ': ' . ((string) ($ssrCurrent['rain_rate'] ?? '--')) . ' mm/h',
                    __('Today') . ': ' . ((string) ($ssrCurrent['rain_daily'] ?? '--')) . ' mm',
                    __('Month') . ': ' . ((string) ($ssrCurrent['rain_monthly'] ?? '--')) . ' mm',
                    __('Year') . ': ' . ((string) ($ssrCurrent['rain_yearly'] ?? '--')) . ' mm',
                ],
            ];
        }
        if ($ssrWidgetFlags['sun_moon']) {
            $ssrHybridCards[] = [
                'id' => 'sun_moon',
                'title' => __('Sun & Moon'),
                'lines' => [
                    __('Sunrise') . ': ' . ((string) ($ssrSun['sunrise'] ?? '--:--')),
                    __('Sunset') . ': ' . ((string) ($ssrSun['sunset'] ?? '--:--')),
                    __('Moon') . ': ' . ((string) ($ssrMoon['phase_name'] ?? '--')),
                ],
            ];
        }
        if ($ssrWidgetFlags['uv_solar']) {
            $ssrHybridCards[] = [
                'id' => 'uv_solar',
                'title' => __('UV & Solar'),
                'lines' => [
                    __('UV Index') . ': ' . ((string) ($ssrCurrent['uv_index'] ?? '--')),
                    __('Radiation') . ': ' . ((string) ($ssrCurrent['solar_radiation'] ?? '--')) . ' W/m²',
                ],
            ];
        }
        if ($ssrWidgetFlags['airquality']) {
            $ssrHybridCards[] = [
                'id' => 'airquality',
                'title' => __('Air Quality'),
                'lines' => [
                    'AQI: ' . ((string) ($ssrAirQuality['aqi'] ?? '--')),
                    'PM2.5: ' . ((string) ($ssrAirQuality['pollutants']['pm25'] ?? '--')) . ' µg/m³',
                    'PM10: ' . ((string) ($ssrAirQuality['pollutants']['pm10'] ?? '--')) . ' µg/m³',
                ],
            ];
        }
        if ($ssrWidgetFlags['pollen']) {
            $ssrHybridCards[] = [
                'id' => 'pollen',
                'title' => __('Pollen'),
                'lines' => [
                    __('Overall') . ': ' . ((string) ($ssrPollen['today']['overall_risk_index'] ?? '--')),
                ],
            ];
        }
        if ($ssrWidgetFlags['tide']) {
            $tideLines = [];
            if ($ssrTide) {
                $tideLines[] = __('Current') . ': ' . $ssrTideCurrentLevelLabel . ' cm';
                $tideLines[] = __('High Tide') . ': ' . $ssrTideNextHighTimeLabel;
                $tideLines[] = __('Low Tide') . ': ' . $ssrTideNextLowTimeLabel;
            }
            if ($ssrWaterWaves) {
                $tideLines[] = __('Wave height') . ': ' . ((string) ($ssrWaterWaves['wave_height_m'] ?? '--')) . ' m';
                $tideLines[] = __('Water temperature') . ': ' . ((string) ($ssrWaterWaves['sst_c'] ?? '--')) . '°C';
            }
            $ssrHybridCards[] = ['id' => 'tide', 'title' => __('Tide'), 'lines' => $tideLines ?: [__('No tide data')]];
        }
        if ($ssrWidgetFlags['metar']) {
            $metarFirst = is_array($ssrMetar[0] ?? null) ? $ssrMetar[0] : (is_array($ssrMetar) ? $ssrMetar : []);
            $ssrHybridCards[] = [
                'id' => 'metar',
                'title' => __('METAR'),
                'lines' => [
                    __('Station') . ': ' . ((string) ($metarFirst['icao'] ?? \App\Models\Setting::getValue('metar.primary_icao', 'EHAM'))),
                    __('Flight category') . ': ' . ((string) ($metarFirst['flight_category'] ?? '--')),
                ],
            ];
        }
        if ($ssrWidgetFlags['earthquakes']) {
            $lines = [];
            foreach (array_slice($ssrEarthquakes, 0, 3) as $eq) {
                if (!is_array($eq)) {
                    continue;
                }
                $mag = isset($eq['magnitude']) && is_numeric($eq['magnitude']) ? number_format((float) $eq['magnitude'], 1) : '--';
                $place = \Illuminate\Support\Str::limit((string) ($eq['place'] ?? $eq['location'] ?? __('Unknown')), 28);
                $lines[] = 'M' . $mag . ' · ' . $place;
            }
            $ssrHybridCards[] = ['id' => 'earthquakes', 'title' => __('Earthquakes'), 'lines' => $lines ?: [__('No recent earthquakes')]];
        }
        if ($ssrWidgetFlags['alerts']) {
            $lines = [];
            foreach (array_slice($ssrAlerts, 0, 2) as $alert) {
                if (!is_array($alert)) {
                    continue;
                }
                $lines[] = \Illuminate\Support\Str::limit((string) ($alert['title'] ?? __('Weather alert')), 50);
            }
            $ssrHybridCards[] = ['id' => 'alerts', 'title' => __('Alerts'), 'lines' => $lines ?: [__('No active alerts')]];
        }
        if ($ssrWidgetFlags['lightning']) {
            $ssrHybridCards[] = [
                'id' => 'lightning',
                'title' => __('Lightning'),
                'lines' => [
                    __('Distance') . ': ' . ((string) ($ssrLightning['distance'] ?? '--')) . ' km',
                    __('Today') . ': ' . ((string) ($ssrLightning['count_daily'] ?? 0)),
                ],
            ];
        }
        if ($ssrWidgetFlags['indoor']) {
            $ssrHybridCards[] = [
                'id' => 'indoor',
                'title' => __('Indoor'),
                'lines' => [
                    __('Temperature') . ': ' . ((string) ($ssrCurrent['temperature_indoor'] ?? '--')) . '°C',
                    __('Humidity') . ': ' . ((string) ($ssrCurrent['humidity_indoor'] ?? '--')) . '%',
                ],
            ];
        }
        if ($ssrWidgetFlags['extra_temps']) {
            $lines = [];
            foreach (array_slice((array) ($ssrExtraSensors['temps'] ?? []), 0, 4, true) as $key => $temp) {
                $lines[] = (string) $key . ': ' . (is_numeric($temp) ? round((float) $temp, 1) . '°C' : '--');
            }
            $ssrHybridCards[] = ['id' => 'extra_temps', 'title' => __('Extra Sensors'), 'lines' => $lines ?: [__('No extra temperature sensors')]];
        }
        if ($ssrWidgetFlags['soil']) {
            $lines = [];
            foreach (array_slice((array) ($ssrExtraSensors['soil'] ?? []), 0, 4, true) as $key => $soil) {
                $moisture = is_array($soil) && isset($soil['moisture']) ? $soil['moisture'] : null;
                $temp = is_array($soil) && isset($soil['temperature']) ? $soil['temperature'] : null;
                $lines[] = (string) $key . ': ' . ($moisture !== null ? $moisture . '%' : '--') . ' · ' . ($temp !== null ? $temp . '°C' : '--');
            }
            $ssrHybridCards[] = ['id' => 'soil', 'title' => __('Soil sensors'), 'lines' => $lines ?: [__('No soil sensor data')]];
        }
        if ($ssrWidgetFlags['pm25']) {
            $lines = [];
            foreach (['ch1', 'ch2', 'ch3', 'ch4'] as $key) {
                $pm = ($ssrExtraSensors['pm25'] ?? [])[$key] ?? null;
                $value = is_array($pm) ? ($pm['current'] ?? null) : $pm;
                if ($value === null) {
                    continue;
                }
                $lines[] = (string) $key . ': ' . $value . ' µg/m³';
            }
            $ssrHybridCards[] = ['id' => 'pm25', 'title' => __('PM2.5 Air Quality'), 'lines' => $lines ?: [__('No PM2.5 data')]];
        }
        if ($ssrWidgetFlags['co2']) {
            $ssrHybridCards[] = ['id' => 'co2', 'title' => __('CO2 Monitor'), 'lines' => ['CO2: ' . ((string) ($ssrExtraSensors['co2'] ?? '--')) . ' ppm']];
        }
        if ($ssrWidgetFlags['battery']) {
            $lines = [];
            foreach (array_slice($ssrBatteryStatus, 0, 5, true) as $key => $val) {
                $lines[] = (string) $key . ': ' . ((string) $val);
            }
            $ssrHybridCards[] = ['id' => 'battery', 'title' => __('Battery Status'), 'lines' => $lines ?: [__('No battery data')]];
        }
        if ($ssrWidgetFlags['radar']) {
            $ssrHybridCards[] = ['id' => 'radar', 'title' => __('Precipitation radar'), 'lines' => [__('Station') . ': ' . ((string) ($ssrStation['location'] ?? \App\Models\Setting::stationLocation()))]];
        }
        if ($ssrWidgetFlags['webcam']) {
            $webcamUrl = (string) \App\Models\Setting::getValue('webcam.url', '');
            $ssrHybridCards[] = ['id' => 'webcam', 'title' => __('Webcam'), 'lines' => [__('Source') . ': ' . $webcamUrl]];
        }
        if ($ssrWidgetFlags['aurora']) {
            $ssrHybridCards[] = [
                'id' => 'aurora',
                'title' => __('Aurora'),
                'lines' => [
                    'Kp: ' . ((string) ($ssrAurora['kp'] ?? '--')),
                    __('Status') . ': ' . ((string) ($ssrAurora['level'] ?? '--')),
                ],
            ];
        }
        if ($ssrWidgetFlags['astro_events']) {
            $lines = [];
            foreach (array_slice($ssrEvents, 0, 3) as $event) {
                if (!is_array($event)) {
                    continue;
                }
                $lines[] = ((string) ($event['emoji'] ?? '✨')) . ' ' . ((string) ($event['event'] ?? __('Event')));
            }
            $ssrHybridCards[] = ['id' => 'astro_events', 'title' => __('Sky Events'), 'lines' => $lines ?: [__('No upcoming events')]];
        }
        if ($ssrWidgetFlags['ads']) {
            $ssrHybridCards[] = ['id' => 'ads', 'title' => __('Advertisement'), 'lines' => [__('Advertisement widget enabled')]];
        }
    }
    $ssrHybridCardsById = [];
    foreach ($ssrHybridCards as $ssrHybridCard) {
        $ssrCardId = (string) ($ssrHybridCard['id'] ?? '');
        if ($ssrCardId === '') {
            continue;
        }
        $ssrHybridCardsById[$ssrCardId] = $ssrHybridCard;
    }
    $ssrFallbackDefaults = [
        'sortable-left-column' => ['current', 'wind', 'pressure', 'rain'],
        'sortable-middle-column' => ['forecast', 'hourly'],
        'sortable-right-column' => ['sun_moon', 'uv_solar', 'airquality', 'pollen', 'tide', 'lightning', 'indoor', 'extra_temps', 'soil', 'pm25', 'co2', 'battery', 'aurora', 'astro_events'],
        'sortable-media-row' => ['webcam', 'radar'],
        'sortable-widgets' => ['metar', 'earthquakes', 'alerts', 'ads'],
    ];
    $ssrFallbackGroups = [
        'sortable-left-column' => [],
        'sortable-middle-column' => [],
        'sortable-right-column' => [],
        'sortable-media-row' => [],
        'sortable-widgets' => [],
    ];
    $ssrPlacedFallbackIds = [];
    $ssrLayoutWidgetOrder = is_array($ssrDashboard['widget_order'] ?? null) ? $ssrDashboard['widget_order'] : [];
    foreach (array_keys($ssrFallbackGroups) as $ssrContainerId) {
        $preferredOrder = $ssrFallbackDefaults[$ssrContainerId] ?? [];
        if (isset($ssrLayoutWidgetOrder[$ssrContainerId]) && is_array($ssrLayoutWidgetOrder[$ssrContainerId])) {
            $preferredOrder = $ssrLayoutWidgetOrder[$ssrContainerId];
        }
        foreach ($preferredOrder as $widgetId) {
            $widgetId = (string) $widgetId;
            if ($widgetId === '' || isset($ssrPlacedFallbackIds[$widgetId]) || !isset($ssrHybridCardsById[$widgetId])) {
                continue;
            }
            $ssrFallbackGroups[$ssrContainerId][] = $ssrHybridCardsById[$widgetId];
            $ssrPlacedFallbackIds[$widgetId] = true;
        }
    }
    foreach ($ssrHybridCardsById as $widgetId => $ssrCard) {
        if (isset($ssrPlacedFallbackIds[$widgetId])) {
            continue;
        }
        $ssrFallbackGroups['sortable-widgets'][] = $ssrCard;
        $ssrPlacedFallbackIds[$widgetId] = true;
    }
    $localeOptions = $localeOptions ?? config('localization.locales', []);
    $unitOptions = $unitOptions ?? config('localization.units', []);
    $unitShort = match ($activeUnits) {
        'imperial' => 'F',
        'uk' => 'UK',
        'scandinavia' => 'm/s',
        default => 'C',
    };
    $menuFeatures = \App\Support\MenuFeatureMap::all();
    $astronomyFeatureEnabled = $menuFeatures['astronomy'] ?? true;
    $airPollenFeatureEnabled = $menuFeatures['air_pollen'] ?? true;
    $skyWaterFeatureEnabled = $menuFeatures['sky_water'] ?? true;
    $earthquakesFeatureEnabled = $menuFeatures['earthquakes'] ?? true;
    $alertsFeatureEnabled = $menuFeatures['alerts'] ?? true;
@endphp
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $toSeoString = function ($value, string $locale, string $fallback = ''): string {
            if (is_array($value)) {
                $value = $value[$locale]
                    ?? $value[str_replace('-', '_', $locale)]
                    ?? $value[explode('-', $locale)[0] ?? $locale]
                    ?? (count($value) ? reset($value) : $fallback);
            }

            if ($value instanceof \Stringable) {
                return (string) $value;
            }

            if (is_null($value)) {
                return $fallback;
            }

            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            if (is_scalar($value)) {
                return (string) $value;
            }

            return $fallback;
        };

        $seoSiteTitleRaw = \App\Models\Setting::getValue('seo.site_title', \App\Models\Setting::stationName());
        $seoSiteDescriptionRaw = \App\Models\Setting::getValue('seo.site_description', __('Live weather data from a local station.'));
        $seoSiteKeywordsRaw = \App\Models\Setting::getValue('seo.site_keywords', '');
        $seoOgImageRaw = \App\Models\Setting::getValue('seo.og_image', '');

        $seoSiteTitle = $toSeoString($seoSiteTitleRaw, $activeLocale ?? app()->getLocale(), \App\Models\Setting::stationName());
        $seoSiteDescription = $toSeoString($seoSiteDescriptionRaw, $activeLocale ?? app()->getLocale(), '');
        $seoSiteKeywords = $toSeoString($seoSiteKeywordsRaw, $activeLocale ?? app()->getLocale(), '');
        $seoOgImage = '';
        if (is_string($seoOgImageRaw) && trim($seoOgImageRaw) !== '') {
            $seoOgImageRaw = trim($seoOgImageRaw);
            $seoOgImage = str_starts_with($seoOgImageRaw, 'http://') || str_starts_with($seoOgImageRaw, 'https://')
                ? $seoOgImageRaw
                : url($seoOgImageRaw);
        }
        // Dynamic OG image for the home/dashboard page.
        $dynamicOgImage = '';
        if (\App\Models\Setting::getValue('og.enabled', false)) {
            $dynamicOgImage = route('og.home');
        }
        $resolvedOgImage = $dynamicOgImage ?: $seoOgImage;
        $seoTitle = $seoSiteTitle;
        $seoDescription = $seoSiteDescription;
        // Locale-aware canonical: matches the hreflang alternates exactly and collapses
        // the default-locale duplicate (prefixed /nl-nl/x and unprefixed /x → one canonical).
        $seoCanonical = localeCanonicalUrl($activeLocale ?? app()->getLocale());
        $seoTwitterCard = $resolvedOgImage ? 'summary_large_image' : 'summary';
        $dashboardJsonLd = null;
        if ($dashboardHybridSsrEnabled && $ssrDashboard) {
            $stationName = (string) ($ssrStation['name'] ?? \App\Models\Setting::stationName());
            $stationLocation = (string) ($ssrStation['location'] ?? \App\Models\Setting::stationLocation());
            $lastUpdateIso = (string) ($ssrDashboard['last_update'] ?? now()->toIso8601String());
            $todaySummaryBits = [];
            if (isset($ssrCurrent['temperature']) && is_numeric($ssrCurrent['temperature'])) {
                $todaySummaryBits[] = __('Temperature') . ': ' . round((float) $ssrCurrent['temperature'], 1) . '°C';
            }
            if (isset($ssrCurrent['wind_speed']) && is_numeric($ssrCurrent['wind_speed'])) {
                $todaySummaryBits[] = __('Wind') . ': ' . round((float) $ssrCurrent['wind_speed'], 1) . ' km/h';
            }
            if (isset($ssrCurrent['rain_daily']) && is_numeric($ssrCurrent['rain_daily'])) {
                $todaySummaryBits[] = __('Rain') . ': ' . round((float) $ssrCurrent['rain_daily'], 1) . ' mm';
            }
            if (isset($ssrAirQuality['aqi']) && is_numeric($ssrAirQuality['aqi'])) {
                $todaySummaryBits[] = __('AQI') . ': ' . (int) $ssrAirQuality['aqi'];
            }
            $forecastText = [];
            foreach (array_slice($ssrForecast, 0, 3) as $day) {
                if (!is_array($day)) {
                    continue;
                }
                $date = (string) ($day['date'] ?? '');
                $high = isset($day['temp_high']) && is_numeric($day['temp_high']) ? round((float) $day['temp_high'], 1) : null;
                $low = isset($day['temp_low']) && is_numeric($day['temp_low']) ? round((float) $day['temp_low'], 1) : null;
                if ($date !== '' && $high !== null && $low !== null) {
                    $forecastText[] = "{$date}: {$low}–{$high}°C";
                }
            }
            $dashboardJsonLd = [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'WebPage',
                        '@id' => $seoCanonical . '#webpage',
                        'url' => $seoCanonical,
                        'name' => $seoTitle,
                        'description' => $seoDescription,
                        'inLanguage' => str_replace('_', '-', $activeLocale),
                        'dateModified' => $lastUpdateIso,
                    ],
                    [
                        '@type' => 'Place',
                        '@id' => $seoCanonical . '#station',
                        'name' => $stationName,
                        'address' => $stationLocation,
                        'description' => __('Weather station location'),
                    ],
                    [
                        '@type' => 'Dataset',
                        '@id' => $seoCanonical . '#dashboard-dataset',
                        'name' => __('Live weather dashboard data'),
                        'description' => implode(' · ', array_filter(array_merge($todaySummaryBits, $forecastText))),
                        'isAccessibleForFree' => true,
                        'dateModified' => $lastUpdateIso,
                        'spatialCoverage' => [
                            '@id' => $seoCanonical . '#station',
                        ],
                        'url' => $seoCanonical,
                    ],
                ],
            ];
        }
    @endphp

    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    @if(is_string($seoSiteKeywords) && trim($seoSiteKeywords) !== '')
        <meta name="keywords" content="{{ trim($seoSiteKeywords) }}">
    @endif
    <link rel="canonical" href="{{ $seoCanonical }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $seoSiteTitle }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $seoCanonical }}">
    <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
    @if($resolvedOgImage)
        <meta property="og:image" content="{{ $resolvedOgImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:type" content="image/png">
    @endif

    <meta name="twitter:card" content="{{ $seoTwitterCard }}">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    @if($resolvedOgImage)
        <meta name="twitter:image" content="{{ $resolvedOgImage }}">
    @endif
    @if($dashboardJsonLd)
        <script type="application/ld+json">{!! json_encode($dashboardJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- Theme Color for Safari/Chrome - matches header glass effect -->
    <meta name="theme-color" content="#1a2332">
    <meta name="color-scheme" content="dark light">
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="WeatherNode">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png">
    <link rel="icon" type="image/x-icon" href="/images/favicon.ico">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- Compiled CSS & JS via Vite -->
    @vite(['resources/css/app.css', 'resources/js/pages/dashboard.js', 'resources/js/app.js'])
    
    <script>
        // Browser detection (for debugging/logging only - effects are now uniform)
	        window.Meteo = {
	            activeLocale: @json($activeLocale),
	            activeUnits: @json($activeUnits),
	            jsLocale: @json($jsLocale),
	            stationTimezone: @json($stationTimezone ?? \App\Models\Setting::timezone()),
	            rainRateUnit: @json(\App\Models\Setting::getValue('display.rainrate_unit', '/h')),
	            temperatureDecimals: @json((int) \App\Models\Setting::getValue('display.temperature_decimals', 1)),
	            windDecimals: @json((int) \App\Models\Setting::getValue('display.wind_decimals', 1)),
	            rainDecimals: @json((int) \App\Models\Setting::getValue('display.rain_decimals', 1)),
	            pressureDecimals: @json((int) \App\Models\Setting::getValue('display.pressure_decimals', 1)),
		            apiKey: @json($publicApiKey ?? null),
	            iconsAnimatedBaseUrl: @json($weatherIconsBaseUrl ?? '/icons/weather'),
	            iconsStaticBaseUrl: '/icons/weather-static',
	            iconsBaseUrl: @json($weatherIconsBaseUrl ?? '/icons/weather'),
	            // Browser detection for logging
	            isSafari: /^((?!chrome|android).)*safari/i.test(navigator.userAgent),
	            isIOS: /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream,
	            yesterdayHigh: @json(\App\Models\DailySummary::whereDate('date', now()->subDay()->toDateString())->value('temp_high')),
	        };
        
        // Log browser detection (no longer affects behavior - effects are uniform)
        if (window.Meteo.isSafari || window.Meteo.isIOS) {
            console.log('🍎 Safari/iOS detected');
        }
        
        window.Meteo.apiHeaders = function (extraHeaders) {
            const headers = Object.assign({ 'Accept': 'application/json' }, extraHeaders || {});
            if (window.Meteo.apiKey) {
                headers['X-API-Key'] = window.Meteo.apiKey;
            }
            return headers;
        };
        window.Meteo.appendApiKey = function (url) {
            if (!window.Meteo.apiKey) return url;
            const isAbsolute = url.startsWith('http://') || url.startsWith('https://');
            if (isAbsolute && !url.startsWith(window.location.origin)) {
                return url;
            }
            // Tile URLs with Leaflet template variables {z}/{x}/{y} must not be parsed by URL()
            // or the braces get encoded and Leaflet can't replace them → 400 Bad Request
            if (url.includes('{z}') || url.includes('{x}') || url.includes('{y}')) {
                const separator = url.includes('?') ? '&' : '?';
                return url + separator + 'api_key=' + encodeURIComponent(window.Meteo.apiKey);
            }
            const normalized = new URL(url, window.location.origin);
            if (!normalized.searchParams.has('api_key')) {
                normalized.searchParams.set('api_key', window.Meteo.apiKey);
            }
            return normalized.origin === window.location.origin
                ? normalized.pathname + normalized.search
                : normalized.toString();
        };
        
        // Store weatherDashboard globally so Alpine can find it.
        window.weatherDashboard = window.weatherDashboard || null;
    </script>

    @php
        $dashboardDefaultWidgets = ['current', 'forecast', 'wind', 'rain', 'sun', 'moon', 'airquality', 'metar', 'radar', 'webcam', 'lightning', 'indoor'];
        $dashboardEnabledWidgetsValue = \App\Models\Setting::getValue('widgets.enabled', []);
        if (is_string($dashboardEnabledWidgetsValue)) {
            $dashboardDecodedWidgets = json_decode($dashboardEnabledWidgetsValue, true);
            $dashboardEnabledWidgets = is_array($dashboardDecodedWidgets) ? $dashboardDecodedWidgets : $dashboardDefaultWidgets;
        } elseif (is_array($dashboardEnabledWidgetsValue)) {
            $dashboardEnabledWidgets = !empty($dashboardEnabledWidgetsValue) ? $dashboardEnabledWidgetsValue : $dashboardDefaultWidgets;
        } else {
            $dashboardEnabledWidgets = $dashboardDefaultWidgets;
        }

        $dashboardAdCode = \App\Models\Setting::getValue('widgets.ad_code', '');
        $dashboardAdCompany = \App\Models\Setting::getValue('widgets.ad_company', '');
        $dashboardAdsConsentService = app(\App\Services\Ads\AdsConsentService::class);
        $dashboardAdsConsentMode = $dashboardAdsConsentService->normalizeConsentMode(
            (string) \App\Models\Setting::getValue('widgets.ads_consent_mode', \App\Services\Ads\AdsConsentService::MODE_AUTO)
        );
        $dashboardAdsConsentCountryCode = $dashboardAdsConsentService->resolveCountryCode(request());
        $dashboardAdsConsentRequired = $dashboardAdsConsentService->requiresConsentForCountryWithMode($dashboardAdsConsentCountryCode, $dashboardAdsConsentMode);

        $dashboardWidgetProvider = \App\Models\Setting::getValue('radar.widget_provider', '');
        $dashboardRadarProvider = $dashboardWidgetProvider ?: \App\Models\Setting::getValue('radar.provider', 'rainviewer');
        $dashboardRainviewerMode = $dashboardRadarProvider === 'rainviewer'
            ? ($dashboardWidgetProvider ? \App\Models\Setting::getValue('radar.widget_rainviewer_mode', 'api') : \App\Models\Setting::getValue('radar.rainviewer_mode', 'api'))
            : 'api';
        $dashboardUseProxy = (bool) \App\Models\Setting::getValue('radar.use_proxy', false);

        $dashboardI18nKeys = [
            'Accept',
            'Active',
            'Advertisement',
            'Annular Solar Eclipse',
            'Hybrid Solar Eclipse',
            'Autumn Equinox',
            'Avalanches',
            'Blowing',
            'Blue Moon',
            'Broken clouds',
            'CAVOK',
            'CO2 Sensor',
            'Calm',
            'Chance',
            'Clear',
            'Coastal event',
            'Cold',
            'Comet',
            'Configure API',
            'Cookies for ads',
            'Cool',
            'Delta Aquariids peak',
            'Done',
            'Draconids peak',
            'Drizzle',
            'Dust',
            'Dust/sand whirls',
            'Duststorm',
            'Earth',
            'Earth at Aphelion',
            'Earth at Perihelion',
            'Eclipse',
            'Edit',
            'Error',
            'Error saving',
            'Eta Aquariids peak',
            'Extra Sensor 1',
            'Extra Sensor 2',
            'Extra Sensor 3',
            'Extra Sensor 4',
            'Extreme',
            'Extremely Poor',
            'Fair',
            'Few clouds',
            'First Quarter',
            'Flight category',
            'Flooding',
            'Fog',
            'Foggy',
            'Forest fire',
            'Grass',
            'Freezing',
            'Full',
            'Full Moon',
            'Funnel cloud',
            'Geminids peak',
            'Good',
            'Hail',
            'Hazardous',
            'Haze',
            'Hazy',
            'Heavy',
            'High',
            'High temperature',
            'Ice crystals',
            'Ice pellets',
            'In the vicinity',
            'Jupiter at opposition',
            'Jupiter-Mercury conjunction',
            'Jupiter-Saturn great conjunction',
            'Just now',
            'Last Quarter',
            'Layout saved!',
            'Leak Sensor',
            'Leonids peak',
            'Light',
            'Lightning Sensor (WH57)',
            'Loading...',
            'Low',
            'Low drifting',
            'Low temperature',
            'Lyrids peak',
            'Mars at opposition',
            'Mars-Saturn conjunction',
            'Mercury at greatest elongation',
            'Meteor',
            'Mist',
            'Misty',
            'Moderate',
            'Moon',
            'Neptune at opposition',
            'New Moon',
            'No activity',
            'None',
            'Not updated yet',
            'Orionids peak',
            'Outdoor Sensor (WH65)',
            'Overcast',
            'PM2.5 Sensor',
            'Partial',
            'Partial Lunar Eclipse',
            'Partial Solar Eclipse',
            'Patches',
            'Penumbral Lunar Eclipse',
            'Perseids peak',
            'Planet',
            'Pleasant',
            'Poor',
            'Quadrantids peak',
            'Rain',
            'Rain-flood',
            'Rainy',
            'Refresh',
            'Refreshing...',
            'Reject',
            'Sand',
            'Sandstorm',
            'Saturn at opposition',
            'Saturn-Neptune conjunction',
            'Scattered clouds',
            'Season',
            'Sensor',
            'Settings',
            'Shallow',
            'Showers',
            'Small hail',
            'Smoke',
            'Snow',
            'Snow grains',
            'Snow ice',
            'Snowy',
            'Soil',
            'Soil Sensor',
            'Special',
            'Spray',
            'Spring Equinox',
            'Squalls',
            'Storm',
            'Strong',
            'Summer Solstice',
            'Supermoon',
            'Taurids peak',
            'Temperature/Humidity Sensor',
            'Thunderstorm',
            'Today',
            'Tomorrow',
            'Tree',
            'Total Lunar Eclipse',
            'Total Solar Eclipse',
            'Unhealthy',
            'Unhealthy for Sensitive Groups',
            'Unknown',
            'Unknown precipitation',
            'Unlikely',
            'Update',
            'Updated',
            'Uranus at opposition',
            'Ursids peak',
            'Venus at greatest brilliancy',
            'Venus at greatest elongation',
            'Venus-Jupiter conjunction',
            'Venus-Mars conjunction',
            'Venus-Saturn conjunction',
            'Vertical visibility',
            'Very High',
            'Very Poor',
            'Very Unhealthy',
            'Very high',
            'Very low',
            'Volcanic ash',
            'Weed',
            'Waning Crescent',
            'Waning Gibbous',
            'Warm',
            'Waxing Crescent',
            'Waxing Gibbous',
            'We use cookies to show ads and measure ad performance. You can accept or reject.',
            'Weather',
            'Wind',
            'Winter Solstice',
            'Zodiacal Light (evening)',
            'Zodiacal Light (morning)',
            'Seven-planet parade',
            'Six-planet alignment (morning)',
            'Six-planet alignment (evening)',
            'Seven-planet parade hint',
            'Six-planet alignment morning hint',
            'Six-planet alignment evening hint',
            'Venus-Neptune conjunction',
            'Transit of Mercury',
            'Transit',
            'active',
            'and',
            'hours ago',
            'in region',
            'minutes ago',
            'seconds ago',
            'strikes',
            'with',
            // Next Rain + Advisory cards
            'Advisory',
            'Chilly',
            'Cold',
            'Dry 24h',
            'Extreme heat',
            'Frost',
            'Frost risk',
            'Hard frost',
            'Mild',
            'Next Rain',
            'Now',
            'Pleasant',
            'Tropical',
            'Warm',
            // Alert banner
            'active warning',
            'active warnings',
            'View all',
            // vs. Yesterday + Best time cards
            'vs. Yesterday',
            'Similar',
            'Best time',
            // Weather alert toasts
            'Heavy rain',
            'Storm-force wind',
            'Extreme cold',
            'Roads may be slippery',
                    'View alerts',
                    // Wind compass points (English keys; locale files may remap e.g. NNE→NNO)
                    'N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE',
                    'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW',
                ];

        $dashboardI18n = [];
        foreach ($dashboardI18nKeys as $dashboardI18nKey) {
            $dashboardI18n[$dashboardI18nKey] = __($dashboardI18nKey);
        }

        $dashboardConfig = [
            'canUseDebugOverrides' => auth()->check() && auth()->user() && auth()->user()->is_admin,
            'enabledWidgets' => $dashboardEnabledWidgets,
            'enabledStatTiles' => \App\Support\StatTileRegistry::enabledIds(),
            'stationName' => \App\Models\Setting::stationName(),
            'stationLocation' => \App\Models\Setting::stationLocation(),
            'tempChartShowNowLine' => (bool) \App\Models\Setting::getValue('widgets.temp_chart_now_line', true),
            'tempChartShowObserved' => (bool) \App\Models\Setting::getValue('widgets.temp_chart_observed', false),
            'defaultMetarIcao' => \App\Models\Setting::getValue('metar.primary_icao', 'EHAM'),
            'hasAdCode' => !empty($dashboardAdCode),
            'adCodeHtml' => $dashboardAdCode,
            'adCompany' => $dashboardAdCompany ? ucfirst(str_replace('_', ' ', $dashboardAdCompany)) : '',
            'adsConsentMode' => $dashboardAdsConsentMode,
            'adsConsentRequired' => (bool) $dashboardAdsConsentRequired,
            'adsConsentCountryCode' => $dashboardAdsConsentCountryCode,
            'webcamRefreshInterval' => (int) \App\Models\Setting::getValue('webcam.refresh_interval', 60),
            'radarRainviewerApiEnabled' => $dashboardRadarProvider === 'rainviewer' && $dashboardRainviewerMode === 'api',
            'stationLat' => (float) \App\Models\Setting::latitude(),
            'stationLon' => (float) \App\Models\Setting::longitude(),
            'rainviewerZoom' => (int) \App\Models\Setting::getValue('radar.rainviewer_zoom', 7),
            'rainviewerFrameDelay' => (int) \App\Models\Setting::getValue('radar.frame_delay', 1000),
            'radarUseProxy' => $dashboardUseProxy,
            'radarWidgetFutureFramesEnabled' => (bool) \App\Models\Setting::getValue('radar.widget_future_frames_enabled', false),
            'i18n' => $dashboardI18n,
        ];
    @endphp
    <script>
        window.__METEO_DASHBOARD_CONFIG__ = @json($dashboardConfig);
        window.__METEO_DASHBOARD_HYBRID__ = @json($dashboardHybridSsrEnabled);
        @if($ssrDashboard)
        window.__METEO_DASHBOARD_INITIAL__ = @json($ssrDashboard);
        @endif
    </script>
    
    {{-- Alpine is bundled via Vite (resources/js/app.js). Loading it again via CDN causes double init and extra CPU/GPU work. --}}

    {{-- Custom head code from admin (ads, analytics, tracking, etc.) --}}
    @php
        $customHeadCode = \App\Models\Setting::getValue('integrations.head_code', '');
    @endphp
    @if(is_string($customHeadCode) && trim($customHeadCode) !== '')
        {!! $customHeadCode !!}
    @endif
</head>
<body class="has-weather-bg text-white min-h-screen font-sans {{ ($siteTheme ?? 'fx') === 'flat' ? 'theme-flat effects-disabled' : '' }}"
      data-side-rails="enabled"
      :class="(@json($siteTheme ?? 'fx') !== 'flat') ? { 'effects-disabled': !backgroundEffectsEnabled } : {}"
      x-data="weatherDashboard()"
      x-init="init()">
    <!-- Site wrapper: clips weather effects overflow without affecting AdSense side rail ads -->
    <div id="site-wrapper">

    <!-- Fixed background layer (never blocks body scroll) -->
    <div class="weather-bg"
         :class="(@json($siteTheme ?? 'fx') !== 'flat') ? (backgroundEffectsEnabled ? 'weather-bg--animated' : 'weather-bg--static') : 'weather-bg--static'"
         google-side-rail-overlap="true"
         aria-hidden="true"></div>

	    @if(($siteTheme ?? 'fx') !== 'flat')
	    <!-- Weather Effects Containers (controlled by backgroundEffectsEnabled toggle) -->
	    <div x-show="backgroundEffectsEnabled && effectsEnabled">
	        <div class="rain-container" x-ref="rainContainer" google-side-rail-overlap="true"></div>
	        <div class="snow-container" x-ref="snowContainer" google-side-rail-overlap="true"></div>
	        <div class="wind-container" x-ref="windContainer" google-side-rail-overlap="true"></div>
	    </div>

    <!-- Lightning flash overlay -->
    <div class="lightning-flash" x-ref="lightningFlash" x-show="backgroundEffectsEnabled && effectsEnabled && effects.lightning.enabled" google-side-rail-overlap="true"></div>

    <!-- Fog (mist) overlay: shown when Mist Effect is enabled and isFoggy (test mode = fog, or real humidity >= 98%). See showFog(). -->
    <div class="fog-container" x-show="showFog()" x-transition.opacity.duration.2000ms google-side-rail-overlap="true">
        <div class="fog-layer layer-1"></div>
        <div class="fog-layer layer-2"></div>
        <div class="fog-layer layer-3"></div>
    </div>
    
    <!-- Ambient clouds -->
    <template x-if="backgroundEffectsEnabled && effectsEnabled && effects.clouds.enabled">
        <div>
            <div class="cloud" style="width: 400px; height: 200px; top: 10%; animation-duration: 60s;"></div>
            <div class="cloud" style="width: 300px; height: 150px; top: 30%; animation-duration: 80s; animation-delay: -20s;"></div>
            <div class="cloud" style="width: 500px; height: 250px; top: 60%; animation-duration: 100s; animation-delay: -40s;"></div>
        </div>
    </template>

    <!-- Sun rays (shown when sunny) -->
    <div class="sun-rays"
         x-show="backgroundEffectsEnabled && effectsEnabled && effects.sun && effects.sun.enabled && isSunny"
         google-side-rail-overlap="true"
         x-transition.opacity.duration.1000ms></div>
    @endif
    
    <!-- Top Bar: Compact Header -->
    <header id="site-header" class="glass border-b border-white/10 sticky top-0 z-50 floating-header" google-side-rail-overlap="false">
        <div class="max-w-7xl mx-auto px-4 py-2">
            <!-- Mobile: Two rows -->
            <div class="flex flex-col gap-2 sm:hidden">
                <!-- Row 1: Logo and controls -->
                <div class="flex flex-wrap items-center justify-between gap-y-2">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-lg flex items-center justify-center shadow-lg shadow-blue-500/30">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold" x-text="station.name">{{ \App\Models\Setting::stationName() }}</h1>
                            <p class="text-xs text-gray-400">{{ \App\Models\Setting::stationLocation() }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0 ml-auto">
                        @if(($siteTheme ?? 'fx') !== 'flat')
                        <!-- FX button: visible to all visitors (toggles rain/snow/fog etc.; preference saved in localStorage) -->
                        <button @click="toggleBackgroundEffects()" :class="backgroundEffectsEnabled ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-gray-600 hover:bg-gray-500'" class="px-3 py-1 text-xs rounded transition-colors flex items-center gap-1" :title="backgroundEffectsEnabled ? '{{ __('Disable background effects') }}' : '{{ __('Enable background effects') }}'">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path></svg>
                            <span x-text="backgroundEffectsEnabled ? 'FX' : 'FX'" class="relative"><span x-show="!backgroundEffectsEnabled" class="absolute inset-0 flex items-center justify-center"><span class="w-full h-0.5 bg-current rotate-45 absolute"></span></span></span>
                        </button>
                        @endif
                        @auth
                            @if(auth()->user()->is_admin)
                                <button @click="toggleEditMode()" :class="editMode ? 'bg-amber-500 hover:bg-amber-400' : 'bg-violet-600 hover:bg-violet-500'" class="px-3 py-1 text-xs rounded transition-colors flex items-center gap-1">
                                    <svg x-show="!editMode" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    <svg x-show="editMode" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span x-text="(editMode ? doneLabel : editLabel) || (editMode ? $el.dataset.doneFallback : $el.dataset.editFallback)" data-edit-fallback="{{ __('Edit') }}" data-done-fallback="{{ __('Done') }}">{{ __('Edit') }}</span>
                                </button>
                                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1 text-xs bg-blue-600 hover:bg-blue-500 rounded transition-colors">{{ __('Admin') }}</a>
                            @endif
                        @endauth
                        <div class="relative" x-data="{ openLang: false }">
                            <button type="button" class="px-2 py-1 text-xs bg-white/10 rounded hover:bg-white/20 transition-colors" @click="openLang = !openLang">
                                {{ $localeOptions[$activeLocale]['short'] ?? strtoupper($activeLocale) }}
                            </button>
                            <div x-cloak x-show="openLang" @click.outside="openLang = false" class="absolute right-0 mt-2 w-40 bg-weather-card border border-white/10 rounded-lg shadow-lg overflow-hidden text-xs z-50">
                                @foreach($localeOptions as $code => $meta)
                                    <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" class="block px-3 py-2 hover:bg-white/10 {{ $activeLocale === $code ? 'text-blue-300' : 'text-gray-200' }}">{{ $meta['label'] }}</a>
                                @endforeach
                            </div>
                        </div>
                        <div class="relative" x-data="{ openUnits: false }">
                            <button type="button" class="px-2 py-1 text-xs bg-white/10 rounded hover:bg-white/20 transition-colors" @click="openUnits = !openUnits">
                                {{ $unitShort }}
                            </button>
                            <div x-cloak x-show="openUnits" @click.outside="openUnits = false" class="absolute right-0 mt-2 w-40 bg-weather-card border border-white/10 rounded-lg shadow-lg overflow-hidden text-xs z-50">
                                @foreach($unitOptions as $code => $meta)
                                    <a href="{{ request()->fullUrlWithQuery(['units' => $code]) }}" class="block px-3 py-2 hover:bg-white/10 {{ $activeUnits === $code ? 'text-blue-300' : 'text-gray-200' }}">{{ $meta['label'] }}</a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Row 2: Time/date (mobile) -->
                <div class="flex items-center justify-center gap-2 text-sm border-t border-white/5 pt-2">
                    <span class="live-indicator inline-block w-2 h-2 bg-green-500 rounded-full shadow-lg shadow-green-500/50"></span>
                    <span class="text-gray-300 font-display" x-text="currentTime">--:--:--</span>
                    <span class="text-gray-500">|</span>
                    <span class="text-gray-300" x-text="currentDate">{{ $ssrDateLabel }}</span>
                    <span class="text-gray-500 text-xs ml-1" x-show="currentTimeZoneLabel" x-text="'( ' + currentTimeZoneLabel + ' )'"></span>
                </div>
            </div>
            <!-- Desktop: Single row -->
            <div class="hidden sm:flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-lg flex items-center justify-center shadow-lg shadow-blue-500/30">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold" x-text="station.name">{{ \App\Models\Setting::stationName() }}</h1>
                        <p class="text-xs text-gray-400">{{ \App\Models\Setting::stationLocation() }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <span class="live-indicator inline-block w-2 h-2 bg-green-500 rounded-full shadow-lg shadow-green-500/50"></span>
                    <span class="text-gray-300 font-display" x-text="currentTime">--:--:--</span>
                    <span class="text-gray-500">|</span>
                    <span class="text-gray-300" x-text="currentDate">{{ $ssrDateLabel }}</span>
                    <span class="text-gray-500 text-xs ml-1" x-show="currentTimeZoneLabel" x-text="'( ' + currentTimeZoneLabel + ' )'"></span>
                </div>
                <div class="flex items-center gap-2">
                    @if(($siteTheme ?? 'fx') !== 'flat')
                    <!-- FX button: visible to all visitors (toggles rain/snow/fog etc.; preference saved in localStorage) -->
                    <button @click="toggleBackgroundEffects()" :class="backgroundEffectsEnabled ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-gray-600 hover:bg-gray-500'" class="px-3 py-1 text-xs rounded transition-colors flex items-center gap-1" :title="backgroundEffectsEnabled ? '{{ __('Disable background effects') }}' : '{{ __('Enable background effects') }}'">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path></svg>
                        <span x-text="backgroundEffectsEnabled ? 'FX' : 'FX'" class="relative"><span x-show="!backgroundEffectsEnabled" class="absolute inset-0 flex items-center justify-center"><span class="w-full h-0.5 bg-current rotate-45 absolute"></span></span></span>
                    </button>
                    @endif
                    @auth
                        @if(auth()->user()->is_admin)
                            <button @click="toggleEditMode()" :class="editMode ? 'bg-amber-500 hover:bg-amber-400' : 'bg-violet-600 hover:bg-violet-500'" class="px-3 py-1 text-xs rounded transition-colors flex items-center gap-1">
                                <svg x-show="!editMode" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                <svg x-show="editMode" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                <span x-text="(editMode ? doneLabel : editLabel) || (editMode ? $el.dataset.doneFallback : $el.dataset.editFallback)" data-edit-fallback="{{ __('Edit') }}" data-done-fallback="{{ __('Done') }}">{{ __('Edit') }}</span>
                            </button>
                            <a href="{{ route('admin.dashboard') }}" class="px-3 py-1 text-xs bg-blue-600 hover:bg-blue-500 rounded transition-colors">{{ __('Admin') }}</a>
                        @endif
                    @endauth
                    <div class="relative" x-data="{ openLang: false }">
                        <button type="button" class="px-2 py-1 text-xs bg-white/10 rounded hover:bg-white/20 transition-colors" @click="openLang = !openLang">
                            {{ $localeOptions[$activeLocale]['short'] ?? strtoupper($activeLocale) }}
                        </button>
                        <div x-cloak x-show="openLang" @click.outside="openLang = false" class="absolute right-0 mt-2 w-40 bg-weather-card border border-white/10 rounded-lg shadow-lg overflow-hidden text-xs">
                            @foreach($localeOptions as $code => $meta)
                                <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" class="block px-3 py-2 hover:bg-white/10 {{ $activeLocale === $code ? 'text-blue-300' : 'text-gray-200' }}">{{ $meta['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                    <div class="relative" x-data="{ openUnits: false }">
                        <button type="button" class="px-2 py-1 text-xs bg-white/10 rounded hover:bg-white/20 transition-colors" @click="openUnits = !openUnits">
                            {{ $unitShort }}
                        </button>
                        <div x-cloak x-show="openUnits" @click.outside="openUnits = false" class="absolute right-0 mt-2 w-40 bg-weather-card border border-white/10 rounded-lg shadow-lg overflow-hidden text-xs">
                            @foreach($unitOptions as $code => $meta)
                                <a href="{{ request()->fullUrlWithQuery(['units' => $code]) }}" class="block px-3 py-2 hover:bg-white/10 {{ $activeUnits === $code ? 'text-blue-300' : 'text-gray-200' }}">{{ $meta['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    @include('weather.partials.navigation')

    <main id="main-content" class="max-w-7xl mx-auto px-4 py-4 relative z-10 side-rail-safe">
        @if($ssrDashboard)
            <noscript>
                <div class="mb-4 px-3 py-2 rounded-lg border border-white/10 bg-white/5 text-sm text-gray-200">
                    {{ __('Current weather') }}:
                    {{ __('Temperature') }} {{ $ssrTemperatureText }}°C,
                    {{ __('Humidity') }} {{ $ssrHumidityText }},
                    {{ __('Wind') }} {{ $ssrWindSpeedText }} km/h,
                    {{ __('Pressure') }} {{ $ssrPressureText }} hPa.
                </div>
            </noscript>
        @endif

        <!-- Status Bar: Last Updated, Alert summary & Refresh -->
        {{-- On mobile wraps to 2 rows: row-1 = status+button, row-2 = alert summary --}}
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 mb-4 px-3 py-2 rounded-lg border transition-colors"
             :class="alerts.length === 0 ? 'bg-white/5 border-white/10' : ''"
             :style="alerts.length > 0 ? 'background:' + (alerts[0]?.severity_color||'#FBEA55') + '14; border-color:' + (alerts[0]?.severity_color||'#FBEA55') + '40' : ''">

            <!-- Left: refresh / last-updated / live — row 1 left on mobile, row 1 left on desktop -->
            <div class="flex items-center gap-2 flex-shrink-0 order-1">
                <!-- Refresh Indicator -->
                <div x-show="isRefreshing" class="flex items-center gap-1.5 text-blue-400">
                    <svg class="w-3 h-3 refresh-indicator" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span class="text-xs font-medium">{{ __('Refreshing...') }}</span>
                </div>

                <!-- Last Updated -->
                <div x-show="!isRefreshing && lastUpdateTime" class="flex items-center gap-1.5 text-gray-400">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span
                        class="text-xs"
                        x-text="(updatedLabel || $el.dataset.fallback) + ' ' + (lastUpdateText || notUpdatedYetLabel)"
                        data-fallback="{{ __('Updated') }}"
                    >{{ __('Updated') }}</span>
                </div>

                <!-- Live indicator dot -->
                <div x-show="!isRefreshing && !dataIsStale" class="flex items-center gap-1.5">
                    <div class="w-2 h-2 bg-green-500 rounded-full live-indicator"></div>
                    <span class="text-xs text-green-400">{{ __('Live') }}</span>
                </div>
            </div>

            <!-- Centre: alert summary — row 2 (full-width) on mobile, inline flex-1 on desktop -->
            <div class="flex items-center gap-1.5 min-w-0 w-full order-3 md:order-2 md:flex-1 md:w-auto md:border-l md:border-white/10 md:pl-3">
                <template x-if="alerts.length > 0">
                    <span class="w-2 h-2 rounded-full flex-shrink-0 animate-pulse"
                          :style="'background:' + (alerts[0]?.severity_color||'#FBEA55')"></span>
                </template>
                <template x-if="alerts.length === 0">
                    <span class="w-2 h-2 rounded-full flex-shrink-0 bg-emerald-500"></span>
                </template>
                <span class="text-xs truncate"
                      :class="alerts.length > 0 ? 'font-medium' : 'text-gray-500'"
                      :style="alerts.length > 0 ? 'color:' + (alerts[0]?.severity_color||'#FBEA55') : ''"
                      x-text="alerts.length > 0 ? alertBannerText() : '{{ __('No active alerts') }}'">{{ $ssrAlertSummary }}</span>
                @if($menuFeatures['alerts'] ?? true)
                    <a href="{{ route('alerts') }}"
                       class="text-xs text-gray-500 hover:text-white flex-shrink-0 whitespace-nowrap ml-1">
                        {{ __('Alerts') }} →
                    </a>
                @endif
            </div>

            <!-- Right: refresh button — row 1 right (ml-auto) on mobile, row 1 rightmost on desktop -->
            <button @click="fetchData()"
                    x-bind:disabled="isRefreshing"
                    class="ml-auto flex-shrink-0 order-2 md:order-3 px-2.5 py-0.5 text-xs bg-blue-600 hover:bg-blue-500 disabled:bg-gray-600 disabled:cursor-not-allowed rounded transition-colors flex items-center gap-1.5">
                <svg class="w-3 h-3" :class="{ 'refresh-indicator': isRefreshing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <span
                    x-text="(isRefreshing ? refreshingLabel : refreshLabel) || $el.dataset.fallback"
                    data-fallback="{{ __('Refresh') }}"
                >{{ __('Refresh') }}</span>
            </button>
        </div>

        <!-- Stale Data Warning Banner -->
        <div x-show="dataIsStale"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform -translate-y-4"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             class="stale-data-banner mb-4 p-4 bg-yellow-900/50 border border-yellow-500/50 rounded-lg">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div class="flex-1">
                    <div class="font-semibold text-yellow-200">{{ __('Stale data') }}</div>
                    <div class="text-sm text-yellow-300">{{ __('The last update was more than 10 minutes ago. Check your internet connection.') }}</div>
                </div>
                <button @click="fetchData()" class="px-3 py-1 bg-yellow-600 hover:bg-yellow-500 rounded text-sm font-medium">
                    {{ __('Refresh now') }}
                </button>
            </div>
        </div>

        {{-- Quick Stats Bar: registry-driven, toggled in admin, reordered in edit mode --}}
        @include('weather.partials.quick-stats')

        <!-- Main Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-4">
            
	            <!-- LEFT COLUMN - Sortable -->
		            <div id="sortable-left-column" class="col-span-1 md:col-span-1 lg:col-span-4 space-y-4">
                        @if($ssrDashboard && count($ssrFallbackGroups['sortable-left-column'] ?? []) > 0)
                            @foreach($ssrFallbackGroups['sortable-left-column'] as $ssrCard)
                                <article x-show="ssrFallbackVisible"
                                         class="ssr-fallback-block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
                                         data-widget="{{ $ssrCard['id'] ?? 'widget' }}">
                                    <div class="flex items-center justify-between mb-3">
                                        <h2 class="font-semibold">{{ $ssrCard['title'] ?? __('Weather') }}</h2>
                                        <span class="text-[10px] text-gray-500 uppercase tracking-wide">SSR</span>
                                    </div>
                                    <div class="space-y-1.5 text-sm text-gray-300">
                                        @foreach(($ssrCard['lines'] ?? []) as $ssrLine)
                                            <p class="leading-snug">{{ $ssrLine }}</p>
                                        @endforeach
                                    </div>
                                </article>
                            @endforeach
                        @endif
		                
		                <!-- Temperature Card - Hero -->
		                @php $tempVisualization = \App\Models\Setting::getValue('widgets.temp_visualization', 'gradient'); @endphp
		                <template x-if="isWidgetEnabled('current')">
		                <div class="sortable-widget bg-gradient-to-br from-weather-card to-weather-card/50 card-3d rounded-2xl p-4 md:p-6 glow border border-white/10 relative overflow-hidden"
		                     data-widget="current"
		                     @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    
                    <!-- Weather Icon & Description - Slightly left to avoid thermometer -->
                    <div class="absolute top-4 md:top-6 pointer-events-none" style="right: calc(1rem + 1cm); z-index: 10;">
		                        <img :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/' + getWeatherIcon() + '.svg'"
		                             class="w-12 h-12 md:w-16 md:h-16 opacity-60"
		                             alt="Weather condition">
                        <div class="text-xs md:text-sm text-gray-300 mt-2" x-text="getWeatherDescription()" style="opacity: 0.8;"></div>
                    </div>

	                    @if($tempVisualization === 'thermometer')
	                    <!-- Thermometer Visualization - Background layer, positioned independently -->
	                    <div class="fx-visual" style="position: absolute; top: 0; right: 0; bottom: 0; left: 0; z-index: 0; pointer-events: none; overflow: hidden;"
	                         x-data="{
	                             _temp: current?.temperature ?? 20,
	                             _isFrozen: false,
	                             init() {
                                 this.updateFrozen();
                                 this.$watch('current?.temperature', (val) => {
                                     this._temp = Number(val) || 20;
                                     this.updateFrozen();
                                 });
                             },
                             updateFrozen() {
                                 const temp = this.temp;
                                 const isFah = temp > 50;
                                 this._isFrozen = isFah ? temp < 32 : temp < 0;
                             },
                             get temp() {
                                 const t = current?.temperature ?? this._temp;
                                 return Number(t) || 20;
                             },
                             get isFahrenheit() {
                                 return this.temp > 50;
                             },
                             get isFrozen() {
                                 return this._isFrozen;
                             },
                             get tempLevel() {
                                 const temp = this.temp;
                                 if (this.isFahrenheit) {
                                     const min = 0; // 32°F = 0°C equivalent
                                     const max = 100.4;
                                     if (temp <= min) return Math.max(0, ((temp - min) / (max - min)) * 100);
                                     if (temp >= max) return 100;
                                     return ((temp - min) / (max - min)) * 100;
                                 } else {
                                     const min = -15; // Extended range to show very cold temps
                                     const max = 38;
                                     if (temp <= min) return 0;
                                     if (temp >= max) return 100;
                                     return ((temp - min) / (max - min)) * 100;
                                 }
                             },
                             get isCold() {
                                 return this.isFahrenheit ? this.temp < 59 : this.temp < 15;
                             },
                             get isWarm() {
                                 return this.isFahrenheit ? this.temp >= 71.6 : this.temp >= 22;
                             },
                             get fillColor() {
                                 if (this._isFrozen) {
                                     return 'rgba(191,219,254,0.9)'; // Light ice blue-white - more opaque when frozen
                                 }
                                 return this.isCold ? 'rgba(59,130,246,0.75)' : this.isWarm ? 'rgba(234,88,12,0.75)' : 'rgba(147,197,253,0.7)';
                             },
                             get fillColorDark() {
                                 if (this._isFrozen) {
                                     return 'rgba(147,197,253,1)'; // Brighter ice blue when frozen
                                 }
                                 return this.isCold ? 'rgba(30,64,175,0.9)' : this.isWarm ? 'rgba(234,88,12,0.9)' : 'rgba(59,130,246,0.85)';
                             }
                         }"
                         x-init="init()"
                         >
                        <svg class="opacity-40 pointer-events-none" viewBox="0 0 200 100" preserveAspectRatio="none" 
                             style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; margin: 0; padding: 0;">
                            <defs>
                                <linearGradient id="thermoMercuryGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                    <stop offset="0%" :stop-color="fillColor"/>
                                    <stop offset="100%" :stop-color="fillColorDark"/>
                                </linearGradient>
                                <pattern id="icePattern" x="0" y="0" width="6" height="6" patternUnits="userSpaceOnUse">
                                    <circle cx="1" cy="1" r="0.4" fill="rgba(255,255,255,0.7)"/>
                                    <circle cx="4" cy="4" r="0.4" fill="rgba(255,255,255,0.6)"/>
                                    <circle cx="1" cy="4" r="0.3" fill="rgba(255,255,255,0.5)"/>
                                    <circle cx="4" cy="1" r="0.3" fill="rgba(255,255,255,0.5)"/>
                                </pattern>
                                <!-- Mask to hide stem inside the ball (matches circular ball shape exactly) -->
                                <mask id="thermoStemMask">
                                    <!-- White = visible, Black = hidden -->
                                    <rect x="0" y="0" width="100" height="100" fill="white"/>
                                    <!-- Hide the circular ball area -->
                                    <circle cx="32" cy="42" r="4.5" fill="black"/>
                                </mask>
                                <linearGradient id="thermoGlassFill" x1="23.73" x2="39.18" y1="19.16" y2="45.93" gradientUnits="userSpaceOnUse">
                                    <stop offset="0" stop-color="#515a69" stop-opacity=".05"/>
                                    <stop offset=".45" stop-color="#6b7280" stop-opacity=".05"/>
                                    <stop offset="1" stop-color="#384354" stop-opacity=".1"/>
                                </linearGradient>
                                <linearGradient id="thermoGlassStroke" x1="23.48" x2="39.43" y1="18.73" y2="46.36" gradientUnits="userSpaceOnUse">
                                    <stop offset="0" stop-color="#d4d7dd"/>
                                    <stop offset=".45" stop-color="#d4d7dd"/>
                                    <stop offset="1" stop-color="#bec1c6"/>
                                </linearGradient>
                            </defs>
                            <g transform="translate(100.4 -28.4) scale(2.7)" style="opacity: 0.95;">
                                <!-- Mercury stem (rendered first, so it's behind the ball, masked to hide inside ball) -->
                                <g mask="url(#thermoStemMask)">
                                    <rect
                                        x="30.5"
                                        width="3"
                                        :y="42 - (27 * (tempLevel / 100))"
                                        :height="Math.max(0, 27 * (tempLevel / 100))"
                                        rx="1.5"
                                        :fill="fillColor"
                                        :style="_isFrozen ? 'transition: y 900ms ease-in-out, height 900ms ease-in-out, fill 900ms ease-in-out; filter: brightness(1.15) saturate(1.2);' : 'transition: y 900ms ease-in-out, height 900ms ease-in-out, fill 900ms ease-in-out'"
                                        x-show="tempLevel > 0"
                                    />
                                    <!-- Ice pattern overlay on stem when frozen -->
                                    <rect
                                        x="30.5"
                                        width="3"
                                        :y="42 - (27 * (tempLevel / 100))"
                                        :height="Math.max(0, 27 * (tempLevel / 100))"
                                        rx="1.5"
                                        fill="url(#icePattern)"
                                        :style="'opacity: ' + (_isFrozen ? 0.75 : 0) + '; transition: opacity 900ms ease-in-out, y 900ms ease-in-out, height 900ms ease-in-out; pointer-events: none;'"
                                        x-show="tempLevel > 0"
                                    />
                                </g>
                                <!-- Base mercury bulb (rendered last, so it's on top) -->
                                <circle cx="32" cy="42" r="4.5" :fill="fillColor" style="transition: fill 900ms ease-in-out">
                                    <title x-show="_isFrozen">Frozen</title>
                                </circle>
                                <!-- Ice pattern on bulb when frozen -->
                                <circle
                                    cx="32"
                                    cy="42"
                                    r="4.5"
                                    fill="url(#icePattern)"
                                    :style="'opacity: ' + (_isFrozen ? 0.75 : 0) + '; transition: opacity 900ms ease-in-out; pointer-events: none;'"
                                />
                                <path
                                    fill="url(#thermoGlassFill)"
                                    stroke="url(#thermoGlassStroke)"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M39 41.9a7 7 0 11-14 0 7.12 7.12 0 013-5.83v-17a4 4 0 118 0v17a7.12 7.12 0 013 5.83zM32.5 25H36m-3.5-4H36m-3.5 8H36"
                                />
                            </g>
                        </svg>
                    </div>
	                    @endif

	                    @if($tempVisualization !== 'none' && $tempVisualization !== 'thermometer')
	                    <!-- Artistic Temperature Visualization Background -->
	                    <div class="absolute inset-0 pointer-events-none fx-visual" style="z-index: 0;" x-data="{
	                        get temperature() {
	                            return $root.current?.temperature || 20;
	                        },
	                        get isFahrenheit() {
                            // Check if temperature is likely in Fahrenheit (values typically > 50)
                            return this.temperature > 50;
                        },
                        get tempLevel() {
                            const temp = this.temperature;
                            if (this.isFahrenheit) {
                                // Fahrenheit scale: 17.6°F (-8°C) = 0%, 100.4°F (38°C) = 100%
                                if (temp <= 17.6) return 0;
                                if (temp >= 100.4) return 100;
                                return ((temp - 17.6) / 82.8) * 100;
                            } else {
                                // Celsius scale: -8°C = 0% (frozen), 38°C = 100% (overcooking)
                                if (temp <= -8) return 0;
                                if (temp >= 38) return 100;
                                return ((temp + 8) / 46) * 100;
                            }
                        },
                        get isCold() {
                            return this.isFahrenheit ? this.temperature < 59 : this.temperature < 15;
                        },
                        get isWarm() {
                            return this.isFahrenheit ? this.temperature >= 71.6 : this.temperature >= 22;
                        }
                    }">
                        @if($tempVisualization === 'gradient')
                        <!-- Sky Gradient Visualization - Following actual weather and day/night -->
                        <svg class="w-full h-full opacity-50" viewBox="0 0 200 100" preserveAspectRatio="xMidYMid slice" x-data="{
                            get rainRate() {
                                return $root.current?.rain_rate || 0;
                            },
                            get isNight() {
                                // Use actual sunrise/sunset (station TZ) and current time in station TZ
                                const sunrise = $root.sun?.sunrise;
                                const sunset = $root.sun?.sunset;
                                const tz = window.Meteo?.stationTimezone || 'UTC';
                                const now = new Date();
                                const timeStr = now.toLocaleTimeString('en-GB', { timeZone: tz, hour: '2-digit', minute: '2-digit', hour12: false });
                                const [h, m] = timeStr.split(':').map(Number);
                                const currentMinutes = (h || 0) * 60 + (m || 0);
                                if (!sunrise || !sunset) {
                                    return currentMinutes < 6 * 60 || currentMinutes >= 20 * 60;
                                }
                                const [sunriseHour, sunriseMin] = sunrise.split(':').map(Number);
                                const sunriseMinutes = sunriseHour * 60 + sunriseMin;
                                const [sunsetHour, sunsetMin] = sunset.split(':').map(Number);
                                const sunsetMinutes = sunsetHour * 60 + sunsetMin;
                                return currentMinutes < sunriseMinutes || currentMinutes >= sunsetMinutes;
                            },
                            get skyCondition() {
                                // Determine sky condition based on precipitation
                                const rain = this.rainRate;
                                if (rain > 5) return 'heavy-rain';      // Heavy rain: dark gray sky
                                if (rain > 1) return 'rain';            // Light rain: gray sky
                                if (rain > 0.1) return 'drizzle';       // Drizzle: light gray sky
                                return 'clear';                          // No rain: clear blue sky
                            },
                            get skyGradientTop() {
                                // Top of sky gradient based on weather and time of day
                                if (this.isNight) {
                                    switch(this.skyCondition) {
                                        case 'heavy-rain': return 'rgba(30,41,59,0.5)';     // Very dark night
                                        case 'rain': return 'rgba(51,65,85,0.5)';           // Dark night
                                        case 'drizzle': return 'rgba(71,85,105,0.45)';      // Gray night
                                        default: return 'rgba(30,58,138,0.4)';              // Clear night blue
                                    }
                                } else {
                                    switch(this.skyCondition) {
                                        case 'heavy-rain': return 'rgba(71,85,105,0.5)';    // Dark slate day
                                        case 'rain': return 'rgba(100,116,139,0.45)';       // Medium gray day
                                        case 'drizzle': return 'rgba(148,163,184,0.4)';     // Light gray day
                                        default: return 'rgba(96,165,250,0.35)';            // Clear blue day
                                    }
                                }
                            },
                            get skyGradientBottom() {
                                // Bottom of sky gradient based on weather and time of day
                                if (this.isNight) {
                                    switch(this.skyCondition) {
                                        case 'heavy-rain': return 'rgba(15,23,42,0.6)';     // Very dark night
                                        case 'rain': return 'rgba(30,41,59,0.55)';          // Dark night
                                        case 'drizzle': return 'rgba(51,65,85,0.5)';        // Gray night
                                        default: return 'rgba(17,24,39,0.45)';              // Night blue
                                    }
                                } else {
                                    switch(this.skyCondition) {
                                        case 'heavy-rain': return 'rgba(51,65,85,0.55)';    // Very dark day
                                        case 'rain': return 'rgba(71,85,105,0.5)';          // Dark gray day
                                        case 'drizzle': return 'rgba(100,116,139,0.45)';    // Medium gray day
                                        default: return 'rgba(59,130,246,0.4)';             // Blue day
                                    }
                                }
                            }
                        }">
                            <defs>
                                <linearGradient id="tempSkyGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                    <stop offset="0%" :style="'stop-color:' + skyGradientTop"/>
                                    <stop offset="100%" :style="'stop-color:' + skyGradientBottom"/>
                                </linearGradient>
                                <!-- Star pattern for night sky -->
                                <radialGradient id="starGlow" cx="50%" cy="50%" r="50%">
                                    <stop offset="0%" style="stop-color:rgba(255,255,255,0.8)"/>
                                    <stop offset="100%" style="stop-color:rgba(255,255,255,0)"/>
                                </radialGradient>
                            </defs>
                            <rect x="0" y="0" width="200" height="100" fill="url(#tempSkyGrad)" class="transition-all duration-1000"/>
                            <!-- Sun for daytime - positioned slightly right of center -->
                            <g :style="'opacity: ' + (!isNight && skyCondition === 'clear' ? 0.35 : 0)" class="transition-opacity duration-1000">
                                <circle cx="120" cy="20" r="12" fill="rgba(251,191,36,0.4)"/>
                                <circle cx="120" cy="20" r="6" fill="rgba(251,191,36,0.7)"/>
                            </g>
                            <!-- Moon and stars for nighttime - positioned slightly right of center -->
                            <g :style="'opacity: ' + (isNight ? 0.4 : 0)" class="transition-opacity duration-1000">
                                <!-- Moon -->
                                <circle cx="120" cy="20" r="10" fill="rgba(226,232,240,0.6)"/>
                                <circle cx="116" cy="18" r="9" fill="rgba(30,41,59,0.3)"/>
                                <!-- Stars scattered across the sky, avoiding moon area -->
                                <circle cx="30" cy="15" r="1.5" fill="url(#starGlow)" class="twinkle-star"/>
                                <circle cx="60" cy="35" r="1" fill="url(#starGlow)" class="twinkle-star" style="animation-delay: 0.5s"/>
                                <circle cx="160" cy="18" r="1.5" fill="url(#starGlow)" class="twinkle-star" style="animation-delay: 1s"/>
                                <circle cx="180" cy="30" r="1" fill="url(#starGlow)" class="twinkle-star" style="animation-delay: 1.5s"/>
                                <circle cx="40" cy="45" r="1.2" fill="url(#starGlow)" class="twinkle-star" style="animation-delay: 0.8s"/>
                                <circle cx="150" cy="40" r="1" fill="url(#starGlow)" class="twinkle-star" style="animation-delay: 1.2s"/>
                                <circle cx="90" cy="50" r="1.3" fill="url(#starGlow)" class="twinkle-star" style="animation-delay: 0.3s"/>
                                <circle cx="170" cy="55" r="1.1" fill="url(#starGlow)" class="twinkle-star" style="animation-delay: 1.7s"/>
                            </g>
                            <!-- Subtle rain indication for rainy weather -->
                            <g :style="'opacity: ' + (rainRate > 0.1 ? Math.min(0.4, rainRate / 10) : 0)" class="transition-opacity duration-1000">
                                <line x1="40" y1="20" x2="38" y2="35" stroke="rgba(147,197,253,0.6)" stroke-width="1.5" stroke-linecap="round"/>
                                <line x1="70" y1="25" x2="68" y2="40" stroke="rgba(147,197,253,0.5)" stroke-width="1.5" stroke-linecap="round"/>
                                <line x1="100" y1="30" x2="98" y2="45" stroke="rgba(147,197,253,0.6)" stroke-width="1.5" stroke-linecap="round"/>
                                <line x1="130" y1="22" x2="128" y2="37" stroke="rgba(147,197,253,0.5)" stroke-width="1.5" stroke-linecap="round"/>
                            </g>
                        </svg>
                        <style>
                            .twinkle-star {
                                animation: starTwinkle 3s ease-in-out infinite;
                            }
                            @keyframes starTwinkle {
                                0%, 100% { opacity: 1; }
                                50% { opacity: 0.3; }
                            }
                        </style>
                        @endif
                    </div>
                    @endif

                    <!-- Widget Content (on top of visualization) -->
                    <div class="relative" style="position: relative; z-index: 10; width: 100%; contain: layout;">
                    <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </div>
                    <!-- Offline Badge (Centered) -->
                    <div x-cloak x-show="healthStatus.sensor?.is_stale === true" class="absolute inset-0 flex items-center justify-center z-30 pointer-events-none">
                        <div class="flex flex-col items-center gap-2 text-white bg-red-600/90 backdrop-blur-sm px-6 py-4 rounded-xl shadow-2xl animate-pulse">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <span class="text-lg font-bold">{{ __('Offline') }}</span>
                            <span class="text-xs" x-show="healthStatus.sensor?.age_minutes" x-text="healthStatus.sensor?.age_minutes ? '(' + Math.round(healthStatus.sensor.age_minutes) + ' ' + translations.minutesAgo + ')' : ''"></span>
                        </div>
                    </div>
                    <!-- Update Timestamp (Top Center) -->
                    <div x-cloak x-show="healthStatus.sensor?.is_stale !== true && getHealthTimestamp('sensor')" class="absolute left-1/2 -translate-x-1/2 flex items-center gap-0.5 text-[10px] text-gray-400 bg-black/30 backdrop-blur-sm px-1.5 py-0.5 rounded z-10" style="top: -1rem;">
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span x-text="getHealthTimestamp('sensor')"></span>
                    </div>
                    <!-- Main Content -->
                    <div class="relative" style="position: relative; z-index: 2; width: 100%;">
                            <div class="text-5xl md:text-6xl font-bold font-display data-value"
                                 data-field="temperature"
                                 :class="{ 'field-updated': changedFields.has('temperature') }">
                                <span x-text="formatTempValue(current?.temperature)">{{ $ssrTemperatureText }}</span>
                                <span class="text-2xl md:text-3xl text-gray-400" x-text="tempUnit()"></span>
                            </div>
                            <div class="flex items-start gap-6 mt-2">
                                <div class="text-gray-400 text-xs space-y-0.5">
                                    <div>{{ __('Feels like') }} <span class="text-white font-medium" x-text="formatTemp(current?.feels_like)">{{ isset($ssrCurrent['feels_like']) && is_numeric($ssrCurrent['feels_like']) ? round((float) $ssrCurrent['feels_like'], 1) . '°' : '--' }}</span></div>
                                    <div>{{ __('Dewpoint') }} <span class="text-white font-medium" x-text="formatTemp(current?.dew_point)">{{ isset($ssrCurrent['dew_point']) && is_numeric($ssrCurrent['dew_point']) ? round((float) $ssrCurrent['dew_point'], 1) . '°' : '--' }}</span></div>
                                    <div>{{ __('Wet Bulb') }} <span class="text-white font-medium" x-text="formatTemp(current?.wet_bulb)">{{ isset($ssrCurrent['wet_bulb']) && is_numeric($ssrCurrent['wet_bulb']) ? round((float) $ssrCurrent['wet_bulb'], 1) . '°' : '--' }}</span></div>
                                    <div class="hidden md:block text-xs text-gray-400 mt-1" x-show="metar && metar[0]?.clouds?.length > 0" x-text="formatMetarClouds(metar[0]?.clouds)"></div>
                                </div>
                            </div>
                            <!-- Mobile: Show weather description and cloud coverage below -->
                            <div class="md:hidden mt-2">
                                <div class="text-xs text-gray-300" x-text="getWeatherDescription()">{{ __('Loading...') }}</div>
                                <div class="text-[10px] text-gray-400 mt-1" x-show="metar && metar[0]?.clouds?.length > 0" x-text="formatMetarClouds(metar[0]?.clouds)"></div>
                            </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 md:gap-4 mt-4 pt-4 border-t border-white/10">
                        <div>
                            <div class="text-[10px] md:text-xs text-gray-400">{{ __('Humidity') }}</div>
                            <div class="text-lg md:text-xl font-bold data-value"
                                 data-field="humidity"
                                 :class="{ 'field-updated': changedFields.has('humidity') }"
                                 x-text="current?.humidity ? Math.round(current.humidity) + '%' : '--%'">{{ $ssrHumidityText }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] md:text-xs text-gray-400">{{ __('UV Index') }}</div>
                            <div class="text-lg md:text-xl font-bold data-value" x-text="current?.uv_index ?? '--'">{{ isset($ssrCurrent['uv_index']) ? $ssrCurrent['uv_index'] : '--' }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] md:text-xs text-gray-400">{{ __('Pressure') }}</div>
                            <div class="text-lg md:text-xl font-bold data-value"
                                 data-field="pressure"
                                 :class="{ 'field-updated': changedFields.has('pressure') }"
                                 x-text="formatPressureValue(current?.pressure)">{{ $ssrPressureText }}</div>
                        </div>
                    </div>
	                    </div>
	                </div>
	                </template>

		                <!-- Wind Card -->
		                @php $windVisualization = \App\Models\Setting::getValue('widgets.wind_visualization', 'streams'); @endphp
	                <template x-if="isWidgetEnabled('wind')">
		                <div class="sortable-widget bg-weather-card rounded-2xl border border-white/10 relative card-flip-container"
		                     data-widget="wind"
		                     x-ref="windCard">

                    <div class="card-flip-inner" :class="{ 'flipped': windCardFlipped }"
                         x-ref="windFlipInner"
                         :style="'min-height:' + (windCardFlipped ? (($refs.windBack?.scrollHeight || 0) + 'px') : (($refs.windFront?.scrollHeight || 0) + 'px'))">

                    <!-- ═══ FRONT FACE ═══ -->
                    <div class="card-flip-front p-5 overflow-hidden rounded-2xl transition-opacity duration-300"
                         x-ref="windFront"
                         :class="windCardFlipped ? 'pointer-events-none opacity-0 invisible' : 'pointer-events-auto opacity-100 visible'"
                         :aria-hidden="windCardFlipped ? 'true' : 'false'">

                    @if($windVisualization !== 'none')
                    <!-- Artistic Wind Visualization Background -->
	                    <div class="absolute inset-0 pointer-events-none z-0 fx-visual">
                        @if($windVisualization === 'streams')
                        <!-- Wind Streams Visualization -->
                        <svg class="w-full h-full opacity-60" viewBox="0 0 200 100" preserveAspectRatio="xMidYMid slice">
                            <defs>
                                <linearGradient id="windWidgetStreamGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" style="stop-color:rgba(147,197,253,0);stop-opacity:0"/>
                                    <stop offset="50%" style="stop-color:rgba(147,197,253,0.7);stop-opacity:1"/>
                                    <stop offset="100%" style="stop-color:rgba(147,197,253,0);stop-opacity:0"/>
                                </linearGradient>
                                <filter id="windWidgetBlur" x="-20%" y="-20%" width="140%" height="140%">
                                    <feGaussianBlur stdDeviation="1"/>
                                </filter>
                            </defs>
                            <!-- Animated flow lines -->
                            <g filter="url(#windWidgetBlur)"
                               :transform="'rotate(' + ((((Number((current && current.wind_direction != null) ? current.wind_direction : 0) || 0) + 90) % 360)) + ' 100 50)'">
                                <!-- Curved streams for a softer, more natural look -->
                                <path d="M-30 22 C 30 10, 80 34, 230 22" stroke="url(#windWidgetStreamGrad)" stroke-width="2" fill="none" stroke-linecap="round"
                                      stroke-dasharray="180 520" class="wind-stream"
                                      :style="'opacity:' + (Math.min(1, Math.max(0.22, (Number((current && current.wind_speed != null) ? current.wind_speed : 0) || 0) / 25)) * 0.55) + '; animation-duration:' + (Math.max(1.4, 7 - (Number((current && current.wind_speed != null) ? current.wind_speed : 0) || 0) / 18)) + 's'"/>
                                <path d="M-30 36 C 40 24, 90 48, 230 36" stroke="url(#windWidgetStreamGrad)" stroke-width="1.5" fill="none" stroke-linecap="round"
                                      stroke-dasharray="140 560" class="wind-stream"
                                      :style="'opacity:' + (Math.min(1, Math.max(0.22, (Number((current && current.wind_speed != null) ? current.wind_speed : 0) || 0) / 25)) * 0.45) + '; animation-delay:0.3s; animation-duration:' + (Math.max(1.6, 7.6 - (Number((current && current.wind_speed != null) ? current.wind_speed : 0) || 0) / 18)) + 's'"/>
                                <path d="M-30 50 C 25 38, 85 62, 230 50" stroke="url(#windWidgetStreamGrad)" stroke-width="2" fill="none" stroke-linecap="round"
                                      stroke-dasharray="210 600" class="wind-stream"
                                      :style="'opacity:' + (Math.min(1, Math.max(0.22, (Number((current && current.wind_speed != null) ? current.wind_speed : 0) || 0) / 25)) * 0.62) + '; animation-delay:0.6s; animation-duration:' + (Math.max(1.2, 6.6 - (Number((current && current.wind_speed != null) ? current.wind_speed : 0) || 0) / 18)) + 's'"/>
                                <path d="M-30 64 C 35 52, 95 76, 230 64" stroke="url(#windWidgetStreamGrad)" stroke-width="1.5" fill="none" stroke-linecap="round"
                                      stroke-dasharray="120 520" class="wind-stream"
                                      :style="'opacity:' + (Math.min(1, Math.max(0.22, (Number((current && current.wind_speed != null) ? current.wind_speed : 0) || 0) / 25)) * 0.4) + '; animation-delay:0.9s; animation-duration:' + (Math.max(1.7, 8.2 - (Number((current && current.wind_speed != null) ? current.wind_speed : 0) || 0) / 18)) + 's'"/>
                                <path d="M-30 78 C 28 66, 88 90, 230 78" stroke="url(#windWidgetStreamGrad)" stroke-width="2" fill="none" stroke-linecap="round"
                                      stroke-dasharray="160 580" class="wind-stream"
                                      :style="'opacity:' + (Math.min(1, Math.max(0.22, (Number((current && current.wind_speed != null) ? current.wind_speed : 0) || 0) / 25)) * 0.5) + '; animation-delay:1.2s; animation-duration:' + (Math.max(1.5, 7.2 - (Number((current && current.wind_speed != null) ? current.wind_speed : 0) || 0) / 18)) + 's'"/>
                            </g>
                        </svg>
                        <style>
                            .wind-stream {
                                animation: windFlow 3s linear infinite;
                            }
                            @keyframes windFlow {
                                0% { stroke-dashoffset: 0; }
                                100% { stroke-dashoffset: -760; }
                            }
                        </style>
                        @elseif($windVisualization === 'particles')
                        <!-- Wind Particles Visualization -->
                        <svg class="w-full h-full opacity-50" viewBox="0 0 200 100" preserveAspectRatio="xMidYMid slice">
                            <defs>
                                <linearGradient id="particleGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" style="stop-color:rgba(255,255,255,0)"/>
                                    <stop offset="50%" style="stop-color:rgba(147,197,253,0.8)"/>
                                    <stop offset="100%" style="stop-color:rgba(255,255,255,0)"/>
                                </linearGradient>
                            </defs>
                            <g :transform="'rotate(' + (((current?.wind_direction ?? 0) + 90) % 360) + ' 100 50)'">
                                <circle cx="20" cy="25" r="1.5" fill="url(#particleGrad)" class="wind-svg-particle" :style="'opacity:' + (Math.min(1, Math.max(0.25, (current?.wind_speed ?? 0) / 15)) * 0.65)"/>
                                <circle cx="40" cy="30" r="1" fill="url(#particleGrad)" class="wind-svg-particle" :style="'opacity:' + (Math.min(1, Math.max(0.25, (current?.wind_speed ?? 0) / 15)) * 0.55) + '; animation-delay:0.2s'"/>
                                <circle cx="60" cy="20" r="1.5" fill="url(#particleGrad)" class="wind-svg-particle" :style="'opacity:' + (Math.min(1, Math.max(0.25, (current?.wind_speed ?? 0) / 15)) * 0.75) + '; animation-delay:0.4s'"/>
                                <circle cx="80" cy="35" r="1" fill="url(#particleGrad)" class="wind-svg-particle" :style="'opacity:' + (Math.min(1, Math.max(0.25, (current?.wind_speed ?? 0) / 15)) * 0.5) + '; animation-delay:0.6s'"/>
                                <circle cx="100" cy="28" r="1.5" fill="url(#particleGrad)" class="wind-svg-particle" :style="'opacity:' + (Math.min(1, Math.max(0.25, (current?.wind_speed ?? 0) / 15)) * 0.65) + '; animation-delay:0.8s'"/>
                                <circle cx="120" cy="40" r="1" fill="url(#particleGrad)" class="wind-svg-particle" :style="'opacity:' + (Math.min(1, Math.max(0.25, (current?.wind_speed ?? 0) / 15)) * 0.55) + '; animation-delay:1s'"/>
                                <circle cx="140" cy="22" r="1.5" fill="url(#particleGrad)" class="wind-svg-particle" :style="'opacity:' + (Math.min(1, Math.max(0.25, (current?.wind_speed ?? 0) / 15)) * 0.75) + '; animation-delay:1.2s'"/>
                                <circle cx="160" cy="38" r="1" fill="url(#particleGrad)" class="wind-svg-particle" :style="'opacity:' + (Math.min(1, Math.max(0.25, (current?.wind_speed ?? 0) / 15)) * 0.5) + '; animation-delay:1.4s'"/>
                                <circle cx="180" cy="30" r="1.5" fill="url(#particleGrad)" class="wind-svg-particle" :style="'opacity:' + (Math.min(1, Math.max(0.25, (current?.wind_speed ?? 0) / 15)) * 0.65) + '; animation-delay:1.6s'"/>
                                <circle cx="30" cy="50" r="1" fill="url(#particleGrad)" class="wind-svg-particle" :style="'opacity:' + (Math.min(1, Math.max(0.25, (current?.wind_speed ?? 0) / 15)) * 0.55) + '; animation-delay:0.3s'"/>
                                <circle cx="50" cy="55" r="1.5" fill="url(#particleGrad)" class="wind-svg-particle" :style="'opacity:' + (Math.min(1, Math.max(0.25, (current?.wind_speed ?? 0) / 15)) * 0.65) + '; animation-delay:0.5s'"/>
                                <circle cx="70" cy="48" r="1" fill="url(#particleGrad)" class="wind-svg-particle" :style="'opacity:' + (Math.min(1, Math.max(0.25, (current?.wind_speed ?? 0) / 15)) * 0.5) + '; animation-delay:0.7s'"/>
                                <circle cx="90" cy="60" r="1.5" fill="url(#particleGrad)" class="wind-svg-particle" :style="'opacity:' + (Math.min(1, Math.max(0.25, (current?.wind_speed ?? 0) / 15)) * 0.75) + '; animation-delay:0.9s'"/>
                                <circle cx="110" cy="52" r="1" fill="url(#particleGrad)" class="wind-svg-particle" :style="'opacity:' + (Math.min(1, Math.max(0.25, (current?.wind_speed ?? 0) / 15)) * 0.55) + '; animation-delay:1.1s'"/>
                                <circle cx="130" cy="65" r="1.5" fill="url(#particleGrad)" class="wind-svg-particle" :style="'opacity:' + (Math.min(1, Math.max(0.25, (current?.wind_speed ?? 0) / 15)) * 0.65) + '; animation-delay:1.3s'"/>
                                <circle cx="150" cy="58" r="1" fill="url(#particleGrad)" class="wind-svg-particle" :style="'opacity:' + (Math.min(1, Math.max(0.25, (current?.wind_speed ?? 0) / 15)) * 0.5) + '; animation-delay:1.5s'"/>
                                <circle cx="170" cy="70" r="1.5" fill="url(#particleGrad)" class="wind-svg-particle" :style="'opacity:' + (Math.min(1, Math.max(0.25, (current?.wind_speed ?? 0) / 15)) * 0.75) + '; animation-delay:1.7s'"/>
                            </g>
                        </svg>
                        <style>
                            .wind-svg-particle {
                                animation: particleMove 4s linear infinite;
                            }
                            @keyframes particleMove {
                                0% { transform: translateX(-20px) translateY(0); opacity: 0; }
                                10% { opacity: 1; }
                                90% { opacity: 1; }
                                100% { transform: translateX(220px) translateY(-10px); opacity: 0; }
                            }
                        </style>
                        @elseif($windVisualization === 'sky')
                        <!-- Wind Sky Visualization -->
                        <svg class="w-full h-full opacity-40" viewBox="0 0 200 100" preserveAspectRatio="xMidYMid slice">
                            <defs>
                                <linearGradient id="windSkyGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                    <stop offset="0%" :style="'stop-color:' + (windIntensity > 0.5 ? 'rgba(59,130,246,0.3)' : 'rgba(100,116,139,0.25)')"/>
                                    <stop offset="100%" :style="'stop-color:' + (windIntensity > 0.5 ? 'rgba(147,197,253,0.15)' : 'rgba(148,163,184,0.2)')"/>
                                </linearGradient>
                                <filter id="cloudBlurWind" x="-20%" y="-20%" width="140%" height="140%">
                                    <feGaussianBlur stdDeviation="2"/>
                                </filter>
                            </defs>
                            <rect x="0" y="0" width="200" height="100" fill="url(#windSkyGrad)" class="transition-all duration-1000"/>
                            <!-- Moving clouds based on wind speed -->
                            <g filter="url(#cloudBlurWind)" :style="'opacity: ' + (0.3 + windIntensity * 0.4)">
                                <ellipse class="wind-cloud" cx="50" cy="30" rx="20" ry="10" fill="rgba(148,163,184,0.6)" :style="'animation-duration: ' + (20 / (windSpeed + 1)) + 's'"/>
                                <ellipse class="wind-cloud" cx="40" cy="28" rx="12" ry="8" fill="rgba(148,163,184,0.7)"/>
                                <ellipse class="wind-cloud" cx="62" cy="29" rx="15" ry="9" fill="rgba(148,163,184,0.55)"/>
                                <ellipse class="wind-cloud" cx="140" cy="45" rx="18" ry="9" fill="rgba(148,163,184,0.5)" :style="'animation-duration: ' + (25 / (windSpeed + 1)) + 's; animation-delay: 2s'"/>
                                <ellipse class="wind-cloud" cx="132" cy="43" rx="10" ry="7" fill="rgba(148,163,184,0.6)"/>
                                <ellipse class="wind-cloud" cx="150" cy="42" rx="12" ry="6" fill="rgba(148,163,184,0.45)"/>
                            </g>
                        </svg>
                        <style>
                            .wind-cloud {
                                animation: cloudDrift linear infinite;
                            }
                            @keyframes cloudDrift {
                                0% { transform: translateX(-30px); }
                                100% { transform: translateX(230px); }
                            }
                        </style>
                        @endif
                    </div>
                    @endif

                    <!-- Widget Content (on top of visualization) -->
                    <div class="relative z-10">
                    <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </div>
                    <!-- Offline Badge (Centered) -->
                    <div x-cloak x-show="healthStatus.sensor?.is_stale === true" class="absolute inset-0 flex items-center justify-center z-30 pointer-events-none">
                        <div class="flex flex-col items-center gap-2 text-white bg-red-600/90 backdrop-blur-sm px-6 py-4 rounded-xl shadow-2xl animate-pulse">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <span class="text-lg font-bold">{{ __('Offline') }}</span>
                        </div>
                    </div>
                    <!-- Update Timestamp (Top Center) -->
                    <div x-cloak x-show="healthStatus.sensor?.is_stale !== true && getHealthTimestamp('sensor')" class="absolute -top-3 left-1/2 -translate-x-1/2 flex items-center gap-0.5 text-[10px] text-gray-400 bg-black/30 backdrop-blur-sm px-1.5 py-0.5 rounded z-10">
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span x-text="getHealthTimestamp('sensor')"></span>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold">{{ __('Wind') }}</h3>
                        <button @click.stop="windCardFlipped = true" class="text-xs text-gray-400 hover:text-white transition-colors cursor-pointer flex items-center gap-1 z-20 relative">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                            <span>{{ __('Wind Rose') }}</span>
                        </button>
                    </div>
                    <div class="flex items-center gap-6">
                        <div class="relative w-28 h-28 flex-shrink-0">
                            <svg viewBox="0 0 100 100" class="w-full h-full">
                                <circle cx="50" cy="50" r="45" fill="none" stroke="currentColor" stroke-width="2" class="text-white/20"/>
                                <text x="50" y="12" text-anchor="middle" fill="currentColor" class="text-[10px] text-gray-400">{{ __('N') }}</text>
                                <text x="88" y="54" text-anchor="middle" fill="currentColor" class="text-[10px] text-gray-400">{{ __('E') }}</text>
                                <text x="50" y="96" text-anchor="middle" fill="currentColor" class="text-[10px] text-gray-400">{{ __('S') }}</text>
                                <text x="12" y="54" text-anchor="middle" fill="currentColor" class="text-[10px] text-gray-400">{{ __('W') }}</text>
                                <polygon points="50,20 45,40 50,35 55,40" fill="#3b82f6"
                                         :style="{ transform: 'rotate(' + (current?.wind_direction ?? 0) + 'deg)', transformOrigin: '50px 50px' }"/>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="text-center">
                                    <div class="text-2xl font-bold font-display data-value" x-text="formatWindValue(current?.wind_speed)">{{ $ssrWindSpeedText }}</div>
                                    <div class="text-xs text-gray-400" x-text="windUnit()"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex-1 space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-400">{{ __('Direction') }}</span>
                                <span class="font-bold" x-text="current ? (translateKey(current.wind_direction_compass) + ' ' + current.wind_direction + '°') : '--'">{{ isset($ssrCurrent['wind_direction']) && is_numeric($ssrCurrent['wind_direction']) ? (__($ssrCurrent['wind_direction_compass'] ?? 'N') . ' ' . (int) $ssrCurrent['wind_direction'] . '°') : '--' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">{{ __('Wind gust') }}</span>
                                <span class="font-bold text-amber-400 data-value" x-text="formatWind(current?.wind_gust)">{{ $ssrWindGustText }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">{{ __('Beaufort') }}</span>
                                <span class="font-bold" x-text="current?.beaufort ? current.beaufort + ' Bft' : '-- Bft'"></span>
                            </div>
                        </div>
                    </div>
	                    </div>
                    </div><!-- End front face -->

                    <!-- ═══ BACK FACE (Wind Rose) ═══ -->
                    <div class="card-flip-back bg-weather-card rounded-2xl p-5 border border-white/10 transition-opacity duration-300"
                         x-ref="windBack"
                         :class="windCardFlipped ? 'pointer-events-auto opacity-100 visible' : 'pointer-events-none opacity-0 invisible'"
                         :aria-hidden="windCardFlipped ? 'false' : 'true'">
                        <!-- Header -->
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold">{{ __('Wind Rose') }} <span class="text-xs text-gray-400 font-normal">24h</span></h3>
                            <button @click.stop="windCardFlipped = false" class="text-xs text-gray-400 hover:text-white transition-colors cursor-pointer flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                                <span>{{ __('Back') }}</span>
                            </button>
                        </div>

                        <!-- Wind Rose SVG (rendered via x-html — template/x-for doesn't work inside SVG) -->
                        <template x-if="windRoseData.total > 0">
                            <div>
                                <div x-html="windRoseData.svgMarkup"></div>

                                <!-- Legend -->
                                <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 mt-2 text-[10px] text-gray-400">
                                    <template x-for="(range, ri) in windRoseData.speedRanges" :key="'legend-'+ri">
                                        <span class="flex items-center gap-1">
                                            <span class="inline-block w-2 h-2 rounded-full" :style="'background:' + range.color"></span>
                                            <span x-text="range.label + ' ' + windUnit()"></span>
                                        </span>
                                    </template>
                                </div>
                                <div class="text-center text-[10px] text-gray-500 mt-1" x-text="windRoseData.total + ' readings'"></div>
                            </div>
                        </template>

                        <!-- No data state -->
                        <template x-if="windRoseData.total === 0">
                            <div class="flex flex-col items-center justify-center py-10 text-gray-500">
                                <svg class="w-10 h-10 mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                                <span class="text-sm">{{ __('No wind data available') }}</span>
                                <span class="text-xs mt-1">{{ __('Data will appear after 24h of readings') }}</span>
                            </div>
                        </template>
                    </div><!-- End back face -->

                    </div><!-- End card-flip-inner -->
	                </div>
	                </template>

	                <!-- Barometer Card -->
	                @php $pressureVisualization = \App\Models\Setting::getValue('widgets.pressure_visualization', 'sky'); @endphp
	                <template x-if="isWidgetEnabled('pressure')">
		                <div class="sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 relative overflow-hidden"
		                     data-widget="pressure"
		                     @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">

                    @if($pressureVisualization !== 'none')
                    <!-- Artistic Pressure Visualization Background (above sun/moon so birds/clouds visible) -->
	                    <div class="absolute inset-0 pointer-events-none z-[1] fx-visual" x-cloak x-show="current?.pressure != null || debugPressureFromUrl != null" x-data="{
                        get debugPressureFromUrl() {
                            // Debug overrides are admin-only (same policy as temp/wind/rain)
                            if (!canUseDebugOverrides) return null;
                            try {
                                const v = new URLSearchParams(window.location.search).get('debug_pressure');
                                if (v === null || v === '') return null;
                                const n = Number(v);
                                return Number.isFinite(n) ? n : null;
                            } catch (e) {
                                return null;
                            }
                        },
                        get pressureLevel() {
                            const isMetric = units !== 'imperial';
                            const min = isMetric ? 980 : 28.9;
                            const max = isMetric ? 1030 : 30.4;
                            const dbg = this.debugPressureFromUrl;
                            // Source data is stored in hPa. Convert to inHg when the active unit system is imperial.
                            const rawHpa = (dbg != null ? dbg : current?.pressure);
                            const val = rawHpa == null
                                ? ((min + max) / 2)
                                : (isMetric ? rawHpa : (rawHpa * 0.02953));
                            return Math.max(0, Math.min(100, ((val - min) / (max - min)) * 100));
                        },
                        get trend() {
                            const key = (current?.pressure_trend_key || '').toLowerCase();
                            if (key === 'rising') return 'rising';
                            if (key === 'falling') return 'falling';
                            const t = (current?.pressure_trend || '').toLowerCase();
                            if (t.includes('stijg') || t.includes('ris')) return 'rising';
                            if (t.includes('dal') || t.includes('fall')) return 'falling';
                            return 'steady';
                        },
                        get isNight() {
                            // Use actual sunrise/sunset (station TZ) and current time in station TZ
                            const sunrise = sun?.sunrise;
                            const sunset = sun?.sunset;
                            const tz = window.Meteo?.stationTimezone || 'UTC';
                            const now = new Date();
                            const timeStr = now.toLocaleTimeString('en-GB', { timeZone: tz, hour: '2-digit', minute: '2-digit', hour12: false });
                            const [h, m] = timeStr.split(':').map(Number);
                            const currentMinutes = (h || 0) * 60 + (m || 0);
                            if (!sunrise || !sunset) {
                                return currentMinutes < 6 * 60 || currentMinutes >= 20 * 60;
                            }
                            const [sunriseHour, sunriseMin] = sunrise.split(':').map(Number);
                            const sunriseMinutes = sunriseHour * 60 + sunriseMin;
                            const [sunsetHour, sunsetMin] = sunset.split(':').map(Number);
                            const sunsetMinutes = sunsetHour * 60 + sunsetMin;
                            return currentMinutes < sunriseMinutes || currentMinutes >= sunsetMinutes;
                        },
                    }">
                        <svg class="w-full h-full" viewBox="0 0 200 100" preserveAspectRatio="xMidYMid slice">
                            <defs>
                                <!-- Sky background: transparent so card colour shows; theme changes via clouds/birds/storm opacity -->
                                <linearGradient id="pressureSkyGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                    <stop offset="0%" style="stop-color:transparent"/>
                                    <stop offset="100%" style="stop-color:transparent"/>
                                </linearGradient>
                                <!-- Sun glow -->
                                <radialGradient id="sunGlowGrad" cx="50%" cy="50%" r="50%">
                                    <stop offset="0%" style="stop-color:rgba(251,191,36,0.8)"/>
                                    <stop offset="60%" style="stop-color:rgba(251,191,36,0.2)"/>
                                    <stop offset="100%" style="stop-color:rgba(251,191,36,0)"/>
                                </radialGradient>
                                <!-- Star glow -->
                                <radialGradient id="pressureStarGlow" cx="50%" cy="50%" r="50%">
                                    <stop offset="0%" style="stop-color:rgba(255,255,255,0.9)"/>
                                    <stop offset="100%" style="stop-color:rgba(255,255,255,0.3)"/>
                                </radialGradient>
                                <!-- Cloud filter for soft edges -->
                                <filter id="cloudBlur" x="-20%" y="-20%" width="140%" height="140%">
                                    <feGaussianBlur stdDeviation="1.5"/>
                                </filter>
                            </defs>

                            <!-- Sky background -->
                            <rect x="0" y="0" width="200" height="100" fill="url(#pressureSkyGrad)" class="transition-all duration-1000"/>

                            <!-- Stars (nighttime, only visible at high pressure / clear skies) -->
                            <g class="transition-all duration-1000" :style="'opacity: ' + (isNight ? Math.max(0, (pressureLevel - 55) / 45) : 0)">
                                <circle cx="30" cy="15" r="1.5" fill="url(#pressureStarGlow)" class="twinkle-star"/>
                                <circle cx="60" cy="35" r="1" fill="url(#pressureStarGlow)" class="twinkle-star" style="animation-delay: 0.5s"/>
                                <circle cx="180" cy="18" r="1.5" fill="url(#pressureStarGlow)" class="twinkle-star" style="animation-delay: 1s"/>
                                <circle cx="190" cy="30" r="1" fill="url(#pressureStarGlow)" class="twinkle-star" style="animation-delay: 1.5s"/>
                                <circle cx="40" cy="45" r="1.2" fill="url(#pressureStarGlow)" class="twinkle-star" style="animation-delay: 0.8s"/>
                                <circle cx="150" cy="40" r="1" fill="url(#pressureStarGlow)" class="twinkle-star" style="animation-delay: 1.2s"/>
                                <circle cx="90" cy="50" r="1.3" fill="url(#pressureStarGlow)" class="twinkle-star" style="animation-delay: 0.3s"/>
                                <circle cx="170" cy="55" r="1.1" fill="url(#pressureStarGlow)" class="twinkle-star" style="animation-delay: 1.7s"/>
                            </g>

                            <!-- Birds (appear at high pressure / fair weather) - brighter stroke so visible over sun/moon -->
                            <g class="transition-all duration-1000" :style="'opacity: ' + Math.max(0, (pressureLevel - 55) / 45)">
                                <path d="M35,30 Q38,27 41,30 M41,30 Q44,27 47,30" fill="none" stroke="rgba(71,85,105,0.8)" stroke-width="2" stroke-linecap="round" class="bird-fly"/>
                                <path d="M55,22 Q57,20 59,22 M59,22 Q61,20 63,22" fill="none" stroke="rgba(71,85,105,0.7)" stroke-width="1.5" stroke-linecap="round" class="bird-fly" style="animation-delay: 0.5s"/>
                                <path d="M25,40 Q27,38 29,40 M29,40 Q31,38 33,40" fill="none" stroke="rgba(71,85,105,0.65)" stroke-width="1.5" stroke-linecap="round" class="bird-fly" style="animation-delay: 1s"/>
                            </g>

                            <!-- Clouds (more visible at low pressure) -->
                            <g filter="url(#cloudBlur)" class="transition-all duration-1000" :style="'opacity: ' + Math.max(0.1, (100 - pressureLevel) / 70)">
                                <!-- Large cloud -->
                                <ellipse cx="50" cy="35" rx="25" ry="12" fill="rgba(148,163,184,0.5)"/>
                                <ellipse cx="40" cy="32" rx="15" ry="10" fill="rgba(148,163,184,0.6)"/>
                                <ellipse cx="62" cy="33" rx="18" ry="9" fill="rgba(148,163,184,0.55)"/>
                                <ellipse cx="48" cy="28" rx="12" ry="8" fill="rgba(203,213,225,0.5)"/>

                                <!-- Medium cloud -->
                                <ellipse cx="140" cy="45" rx="20" ry="10" fill="rgba(148,163,184,0.45)"/>
                                <ellipse cx="132" cy="43" rx="12" ry="8" fill="rgba(148,163,184,0.5)"/>
                                <ellipse cx="150" cy="42" rx="14" ry="7" fill="rgba(148,163,184,0.4)"/>

                                <!-- Small cloud -->
                                <ellipse cx="100" cy="55" rx="15" ry="7" fill="rgba(148,163,184,0.35)"/>
                                <ellipse cx="95" cy="53" rx="10" ry="6" fill="rgba(148,163,184,0.4)"/>
                            </g>

                            <!-- Dark storm clouds (only at very low pressure) -->
                            <g filter="url(#cloudBlur)" class="transition-all duration-1000" :style="'opacity: ' + Math.max(0, (35 - pressureLevel) / 35)">
                                <ellipse cx="60" cy="40" rx="30" ry="15" fill="rgba(71,85,105,0.6)"/>
                                <ellipse cx="45" cy="38" rx="20" ry="12" fill="rgba(51,65,85,0.65)"/>
                                <ellipse cx="78" cy="37" rx="22" ry="11" fill="rgba(71,85,105,0.55)"/>
                                <!-- Rain streaks -->
                                <line x1="45" y1="52" x2="42" y2="65" stroke="rgba(147,197,253,0.3)" stroke-width="1"/>
                                <line x1="55" y1="54" x2="52" y2="68" stroke="rgba(147,197,253,0.25)" stroke-width="1"/>
                                <line x1="65" y1="52" x2="62" y2="66" stroke="rgba(147,197,253,0.3)" stroke-width="1"/>
                                <line x1="75" y1="50" x2="72" y2="62" stroke="rgba(147,197,253,0.2)" stroke-width="1"/>
                            </g>

                            <!-- Pressure arrows showing air movement -->
                            <g class="transition-all duration-500" :style="'opacity: ' + (trend !== 'steady' ? '0.4' : '0')">
                                <!-- Rising arrows (low pressure - air rises) -->
                                <g :style="'opacity: ' + (trend === 'falling' ? '1' : '0')" class="transition-opacity duration-500">
                                    <path d="M180,85 L180,70 M175,75 L180,70 L185,75" fill="none" stroke="rgba(96,165,250,0.5)" stroke-width="2" stroke-linecap="round" class="arrow-up"/>
                                    <path d="M190,80 L190,68 M186,73 L190,68 L194,73" fill="none" stroke="rgba(96,165,250,0.4)" stroke-width="1.5" stroke-linecap="round" class="arrow-up" style="animation-delay: 0.3s"/>
                                </g>
                                <!-- Descending arrows (high pressure - air descends) -->
                                <g :style="'opacity: ' + (trend === 'rising' ? '1' : '0')" class="transition-opacity duration-500">
                                    <path d="M180,60 L180,75 M175,70 L180,75 L185,70" fill="none" stroke="rgba(251,191,36,0.5)" stroke-width="2" stroke-linecap="round" class="arrow-down"/>
                                    <path d="M190,63 L190,75 M186,71 L190,75 L194,71" fill="none" stroke="rgba(251,191,36,0.4)" stroke-width="1.5" stroke-linecap="round" class="arrow-down" style="animation-delay: 0.3s"/>
                                </g>
                            </g>
                        </svg>
                    </div>
	                    <!-- Subtle moon (nighttime) or sun (daytime)
	                         Part of the pressure 'sky' theme: only render when visualization is enabled.
	                         Layering: sun/moon (z-0) < pressure sky theme (z-[1]) < widget content (z-10) -->
	                    <div class="absolute top-10 left-1/2 -translate-x-1/2 z-0 pointer-events-none fx-visual" x-cloak x-data="{
	                        get pressureLevel() {
	                            // Mirror the pressure theme logic so sun/moon can react to stormy vs clear skies.
	                            const isMetric = units !== 'imperial';
	                            const min = isMetric ? 980 : 28.9;
                            const max = isMetric ? 1030 : 30.4;
                            const rawHpa = current?.pressure;
                            const val = rawHpa == null
                                ? ((min + max) / 2)
                                : (isMetric ? rawHpa : (rawHpa * 0.02953));
                            return Math.max(0, Math.min(100, ((val - min) / (max - min)) * 100));
                        },
                        get clearFactor() {
                            // 0 = stormy/low pressure, 1 = clear/high pressure
                            // Start “clearing up” around midrange.
                            return Math.max(0, Math.min(1, (this.pressureLevel - 40) / 60));
                        },
                        get isNight() {
                            // Use the dashboard’s canonical night calculation (handles station timezone correctly).
                            if (typeof isNightTime === 'function') return !!isNightTime();
                            // Fallback: local clock heuristic
                            const now = new Date();
                            const currentMinutes = now.getHours() * 60 + now.getMinutes();
                            return currentMinutes < 6 * 60 || currentMinutes >= 21 * 60;
                        },
                        get illumination() {
                            const raw = moon?.illumination;
                            const n = Number(raw);
                            if (!Number.isFinite(n)) return 55;
                            return Math.max(0, Math.min(100, n));
                        },
                        get isWaning() {
                            const icon = String(moon?.icon || '').toLowerCase();
                            const name = String(moon?.phase_name || '').toLowerCase();
                            return icon.includes('waning') || name.includes('waning');
                        },
                        get shadowOffset() {
                            const r = 26;
                            const offset = (this.illumination / 100) * (2 * r);
                            return (this.isWaning ? 1 : -1) * offset;
                        },
                        get moonOpacity() {
                            return 0.05 + (this.illumination / 100) * 0.06;
                        },
                        get moonBlur() {
                            // Slightly sharper on very clear nights; a touch more diffuse when stormy.
                            return 0.9 - (this.clearFactor * 0.35); // ~0.55..0.9
                        },
                        get sunOpacity() {
                            // Kept for backwards-compat; use per-part opacity so the sun never reads as a “moon”.
                            return 1;
                        },
                        get sunBlur() {
                            // Stormy: hazier glow; Clear: crisper.
                            return 1.25 - (this.clearFactor * 0.85); // ~0.4..1.25
                        },
                        get sunGlowOpacity() {
                            // Always allow a faint warm glow during stormy daytime.
                            return 0.02 + (this.clearFactor * 0.14); // ~0.02..0.16
                        },
                        get sunDiscFactor() {
                            // Hide the sun disc (which can look moon-like) unless skies are reasonably clear.
                            return Math.max(0, Math.min(1, (this.clearFactor - 0.35) / 0.65));
                        },
                        get sunDiscOpacity() {
                            return this.sunDiscFactor * 0.9; // 0..0.9 (multiplied by soft-light)
                        },
                        get sunRaysOpacity() {
                            return this.sunDiscFactor * 0.8;
                        },
                    }">
                        <template x-if="isNight">
                            <div
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24"
                                :style="'filter: blur(' + moonBlur + 'px);'">
                                <svg viewBox="0 0 100 100" class="w-full h-full mix-blend-soft-light" :style="'opacity:' + moonOpacity">
                                    <defs>
                                        <radialGradient id="pressureMoonGlow" cx="50%" cy="45%" r="60%">
                                            <stop offset="0%" stop-color="rgba(226,232,240,0.65)"/>
                                            <stop offset="70%" stop-color="rgba(148,163,184,0.18)"/>
                                            <stop offset="100%" stop-color="rgba(148,163,184,0)"/>
                                        </radialGradient>
                                        <radialGradient id="pressureMoonDisc" cx="40%" cy="35%" r="70%">
                                            <stop offset="0%" stop-color="rgba(248,250,252,0.92)"/>
                                            <stop offset="60%" stop-color="rgba(226,232,240,0.78)"/>
                                            <stop offset="100%" stop-color="rgba(148,163,184,0.55)"/>
                                        </radialGradient>
                                        <mask id="pressureMoonPhaseMask">
                                            <rect x="0" y="0" width="100" height="100" fill="black"/>
                                            <circle cx="50" cy="50" r="26" fill="white"/>
                                            <circle :cx="50 + shadowOffset" cy="50" r="26" fill="black"/>
                                        </mask>
                                    </defs>
                                    <circle cx="50" cy="50" r="38" fill="url(#pressureMoonGlow)"/>
                                    <g>
                                        <circle cx="50" cy="50" r="26" fill="rgba(15,23,42,0.05)"/>
                                        <g mask="url(#pressureMoonPhaseMask)">
                                            <circle cx="50" cy="50" r="26" fill="url(#pressureMoonDisc)"/>
                                        </g>
                                        <circle cx="42" cy="44" r="3.2" fill="rgba(148,163,184,0.18)"/>
                                        <circle cx="58" cy="57" r="2.4" fill="rgba(148,163,184,0.14)"/>
                                        <circle cx="55" cy="40" r="1.6" fill="rgba(148,163,184,0.12)"/>
                                    </g>
                                </svg>
                            </div>
                        </template>

                        <template x-if="!isNight">
                            <div
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="flex items-center justify-center w-28 h-28 sm:w-32 sm:h-32"
                                :style="'filter: blur(' + sunBlur + 'px);'">
                                <svg viewBox="0 0 100 100" class="w-full h-full mix-blend-soft-light">
                                    <defs>
                                        <radialGradient id="pressureSunGlow" cx="50%" cy="50%" r="60%">
                                            <stop offset="0%" stop-color="rgba(253,230,138,0.70)"/>
                                            <stop offset="55%" stop-color="rgba(251,191,36,0.22)"/>
                                            <stop offset="100%" stop-color="rgba(251,191,36,0)"/>
                                        </radialGradient>
                                        <radialGradient id="pressureSunDisc" cx="40%" cy="35%" r="70%">
                                            <stop offset="0%" stop-color="rgba(255,255,255,0.80)"/>
                                            <stop offset="55%" stop-color="rgba(253,230,138,0.75)"/>
                                            <stop offset="100%" stop-color="rgba(251,191,36,0.45)"/>
                                        </radialGradient>
                                    </defs>

                                    <circle cx="50" cy="50" r="46" fill="url(#pressureSunGlow)" :style="'opacity:' + sunGlowOpacity"/>
                                    <circle cx="50" cy="50" r="22" fill="url(#pressureSunDisc)" :style="'opacity:' + sunDiscOpacity"/>
                                    <g :style="'opacity:' + sunRaysOpacity" stroke="rgba(253,230,138,0.35)" stroke-width="2" stroke-linecap="round">
                                        <line x1="50" y1="10" x2="50" y2="18"/>
                                        <line x1="50" y1="82" x2="50" y2="90"/>
                                        <line x1="10" y1="50" x2="18" y2="50"/>
                                        <line x1="82" y1="50" x2="90" y2="50"/>
                                        <line x1="22" y1="22" x2="28" y2="28"/>
                                        <line x1="72" y1="72" x2="78" y2="78"/>
                                        <line x1="72" y1="28" x2="78" y2="22"/>
                                        <line x1="22" y1="78" x2="28" y2="72"/>
                                    </g>
                                </svg>
                            </div>
                        </template>
                    </div>
                    @endif

                    <!-- Widget Content (on top of visualization) -->
                    <div class="relative z-10">
                        <div class="drag-handle absolute -top-3 -right-3 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                            <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                            </svg>
                        </div>
                        <!-- Offline Badge (Centered) -->
                        <div x-cloak x-show="healthStatus.sensor?.is_stale === true" class="absolute inset-0 flex items-center justify-center z-30 pointer-events-none">
                            <div class="flex flex-col items-center gap-2 text-white bg-red-600/90 backdrop-blur-sm px-6 py-4 rounded-xl shadow-2xl animate-pulse">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                <span class="text-lg font-bold">{{ __('Offline') }}</span>
                            </div>
                        </div>
                        <!-- Update Timestamp (Top Center) -->
                        <div x-cloak x-show="healthStatus.sensor?.is_stale !== true && getHealthTimestamp('sensor')" class="absolute -top-3 left-1/2 -translate-x-1/2 flex items-center gap-0.5 text-[10px] text-gray-400 bg-black/30 backdrop-blur-sm px-1.5 py-0.5 rounded z-10">
                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span x-text="getHealthTimestamp('sensor')"></span>
                        </div>

                        <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold">{{ __('Pressure') }}</h3>
                        <a href="{{ route('weather.pressure-map') }}"
                           target="_blank"
                           class="text-xs px-2 py-1 bg-blue-500/20 text-blue-400 rounded hover:bg-blue-500/30 transition-colors flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                            </svg>
                            {{ __('Map') }}
                        </a>
                    </div>
                    
                    <!-- Main Pressure Display with Gauge -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex-1">
                            <div class="text-4xl font-bold font-display data-value mb-1" x-text="formatPressureValue(current?.pressure)"></div>
                            <div class="text-sm text-gray-400" x-text="pressureUnit()"></div>
                        </div>

                        <!-- Compact Circular Gauge -->
                        <div class="relative w-24 h-24 flex-shrink-0">
                            <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" class="w-full h-full transform -rotate-90">
                                <!-- Background arc -->
                                <circle cx="50" cy="50" r="42" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="8"/>

                                <!-- Pressure range arc (low to high) -->
                                <circle cx="50" cy="50" r="42"
                                        fill="none"
                                        stroke="url(#pressureGradient)"
                                        stroke-width="8"
                                        stroke-dasharray="264"
                                        :stroke-dashoffset="264 - (264 * 0.75)"
                                        stroke-linecap="round"
                                        class="transition-all duration-500"/>

                                <!-- Gradient: red = low pressure (970), green = fair, cyan = high pressure (1040) -->
                                <defs>
                                    <linearGradient id="pressureGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%" style="stop-color:#ef4444;stop-opacity:1" />
                                        <stop offset="50%" style="stop-color:#10b981;stop-opacity:1" />
                                        <stop offset="100%" style="stop-color:#06b6d4;stop-opacity:1" />
                                    </linearGradient>
                                </defs>

                                <!-- Tick marks at min, mid, max (arc is 0-270deg, -90 rotated so 0=top, 270=right) -->
                                <line x1="92" y1="50" x2="96" y2="50" stroke="rgba(255,255,255,0.4)" stroke-width="1.5" stroke-linecap="round"/>
                                <line x1="50" y1="92" x2="50" y2="96" stroke="rgba(255,255,255,0.4)" stroke-width="1.5" stroke-linecap="round"/>
                                <line x1="20.3" y1="20.3" x2="17" y2="17" stroke="rgba(255,255,255,0.4)" stroke-width="1.5" stroke-linecap="round"/>

                                <!-- Current position indicator -->
                                <circle cx="50" cy="8" r="4"
                                        class="fill-white drop-shadow-lg"
                                        x-data="{
                                            get rotation() {
                                                const isMetric = pressureUnit().includes('hPa');
                                                const min = isMetric ? 970 : 28.5;
                                                const max = isMetric ? 1040 : 31.0;
                                                const rawHpa = current?.pressure;
                                                const val = rawHpa == null
                                                    ? min
                                                    : (isMetric ? rawHpa : (rawHpa * 0.02953));
                                                const normalized = (val - min) / (max - min);
                                                return Math.min(Math.max(normalized * 270, 0), 270);
                                            }
                                        }"
                                        :transform="`rotate(${rotation} 50 50)`"/>
                            </svg>

                            <!-- Trend indicator in center (uses pressure_trend_key so color/icon work in all languages) -->
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="text-2xl"
                                     x-data="{
                                         getTrendIcon(key, trend) {
                                             const k = (key || '').toLowerCase();
                                             if (k === 'rising') return { icon: '↗', class: 'text-red-400' };
                                             if (k === 'falling') return { icon: '↘', class: 'text-cyan-400' };
                                             if (k === 'stable') return { icon: '→', class: 'text-green-400' };
                                             if (!trend || trend === 'n/a') return { icon: '--', class: 'text-gray-500 text-base' };
                                             const lower = trend.toLowerCase();
                                             if (lower.includes('stijg') || lower.includes('ris')) return { icon: '↗', class: 'text-red-400' };
                                             if (lower.includes('dal') || lower.includes('fall')) return { icon: '↘', class: 'text-cyan-400' };
                                             if (lower.includes('stab') || lower.includes('stead')) return { icon: '→', class: 'text-green-400' };
                                             return { icon: '--', class: 'text-gray-400 text-base' };
                                         }
                                     }"
                                     :class="getTrendIcon(current?.pressure_trend_key, current?.pressure_trend).class"
                                     x-text="getTrendIcon(current?.pressure_trend_key, current?.pressure_trend).icon">
                                </div>
                            </div>

                            <!-- Min / mid / max labels (metric and imperial); arc runs top=min to right=max -->
                            <div class="absolute inset-0 pointer-events-none text-[9px] text-gray-400">
                                <span class="absolute left-1/2 top-0 -translate-x-1/2 -translate-y-0.5" x-text="pressureUnit().includes('hPa') ? '970' : '28.5'"></span>
                                <span class="absolute right-0 top-1/2 translate-x-0.5 -translate-y-1/2" x-text="pressureUnit().includes('hPa') ? '1040' : '31.0'"></span>
                                <span class="absolute top-2 right-2" x-text="pressureUnit().includes('hPa') ? '1005' : '29.8'"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Grid -->
                    <div class="grid grid-cols-3 gap-3 mb-3">
                        <div class="bg-white/5 rounded-lg p-2">
                            <div class="text-xs text-gray-400 mb-1">{{ __('Min') }}</div>
                            <div class="text-sm font-bold text-cyan-400 data-value" x-text="formatPressureValue(today?.pressure_low) || '--'"></div>
                        </div>
                        <div class="bg-white/5 rounded-lg p-2">
                            <div class="text-xs text-gray-400 mb-1">{{ __('Max') }}</div>
                            <div class="text-sm font-bold text-red-400 data-value" x-text="formatPressureValue(today?.pressure_high) || '--'"></div>
                        </div>
                        <div class="bg-white/5 rounded-lg p-2">
                            <div class="text-xs text-gray-400 mb-1">{{ __('Trend') }}</div>
                            <div class="text-sm font-bold"
                                 x-data="{
                                     getTrendClass(key, trend) {
                                         const k = (key || '').toLowerCase();
                                         if (k === 'rising') return 'text-red-400';
                                         if (k === 'falling') return 'text-cyan-400';
                                         if (k === 'stable') return 'text-green-400';
                                         if (!trend || trend === 'n/a') return 'text-gray-500';
                                         const lower = trend.toLowerCase();
                                         if (lower.includes('stijg') || lower.includes('ris')) return 'text-red-400';
                                         if (lower.includes('dal') || lower.includes('fall')) return 'text-cyan-400';
                                         if (lower.includes('stab') || lower.includes('stead')) return 'text-green-400';
                                         return 'text-gray-400';
                                     }
                                 }"
                                 :class="getTrendClass(current?.pressure_trend_key, current?.pressure_trend)"
                                 x-text="current?.pressure_trend || '--'">
                            </div>
                        </div>
                    </div>

                    <!-- 24hr Pressure History Chart (uses dashboard scope so pressureHistory updates trigger chart) -->
                    <div class="bg-white/5 rounded-lg p-3">
                        <div class="text-xs text-gray-400 mb-2">{{ __('Last 24 Hours') }}</div>
                        <div class="relative">
                            <div x-show="hasPressureChartData">
                                <!-- Chart Container -->
                                <div class="relative h-20 mb-1">
                                    <!-- SVG Line Chart Overlay -->
                                    <svg class="absolute inset-0 w-full h-full pointer-events-none" style="z-index: 1;" viewBox="0 0 100 100" preserveAspectRatio="none">
                                        <path
                                            :d="pressureChartLinePath"
                                            fill="none"
                                            stroke="rgba(147, 197, 253, 0.6)"
                                            stroke-width="0.5"
                                            class="transition-all duration-300"
                                            stroke-linecap="round"
                                            stroke-linejoin="round">
                                        </path>
                                        <template x-for="(bar, idx) in pressureChartData.bars" :key="idx">
                                            <circle
                                                :cx="((idx + 0.5) / pressureChartData.bars.length) * 100"
                                                :cy="100 - bar"
                                                r="0.8"
                                                fill="rgba(147, 197, 253, 0.8)"
                                                class="transition-all duration-300">
                                            </circle>
                                        </template>
                                    </svg>

                                    <!-- Bar Chart -->
                                    <div class="h-full flex items-end gap-0.5 relative" style="z-index: 0;">
                                        <template x-for="(height, idx) in pressureChartData.bars" :key="idx">
                                            <div
                                                class="flex-1 rounded-t transition-all duration-300 cursor-pointer group relative"
                                                :class="(pressureChartHoveredIndex === idx ? ' scale-110 opacity-100' : '') + (pressureChartHoveredIndex !== null && pressureChartHoveredIndex !== idx ? ' opacity-70' : '')"
                                                :style="`height: ${height}%; background-color: ${pressureChartGetBarColor(idx, pressureChartData)};`"
                                                @mouseenter="pressureChartHandleMouseEnter($event, idx)"
                                                @mouseleave="pressureChartHandleMouseLeave()">
                                            </div>
                                        </template>
                                    </div>

                                    <!-- Tooltip -->
                                    <div
                                        x-show="pressureChartHoveredIndex !== null && pressureChartData.pressures[pressureChartHoveredIndex]"
                                        x-transition:enter="transition ease-out duration-200"
                                        x-transition:enter-start="opacity-0 scale-95"
                                        x-transition:enter-end="opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-150"
                                        x-transition:leave-start="opacity-100 scale-100"
                                        x-transition:leave-end="opacity-0 scale-95"
                                        class="absolute bg-gray-900/95 backdrop-blur-sm border border-white/20 rounded-lg px-3 py-2 text-xs shadow-xl pointer-events-none z-50 whitespace-nowrap"
                                        :style="`left: ${pressureChartTooltipX}px; top: ${pressureChartTooltipY}px; transform: translateX(-50%) translateY(-100%); margin-top: -4px;`">
                                        <div class="text-white font-semibold" x-text="pressureChartData.pressures[pressureChartHoveredIndex] ? formatPressure(pressureChartData.pressures[pressureChartHoveredIndex]) : '--'"></div>
                                        <div class="text-gray-400 text-[10px] mt-0.5" x-text="pressureChartData.times[pressureChartHoveredIndex] ? pressureChartFormatTime(pressureChartData.times[pressureChartHoveredIndex]) : ''"></div>
                                        <div class="text-gray-500 text-[10px]" x-text="pressureChartData.times[pressureChartHoveredIndex] ? pressureChartFormatRelativeTime(pressureChartData.times[pressureChartHoveredIndex]) : ''"></div>
                                    </div>
                                </div>

                                <!-- Time Labels -->
                                <div class="flex justify-between text-[10px] text-gray-500 mt-1 px-0.5">
                                    <template x-for="label in pressureChartTimeLabels" :key="label.idx">
                                        <span x-text="label.time"></span>
                                    </template>
                                </div>

                                <!-- Summary Stats -->
                                <div class="flex justify-between items-center mt-2 pt-2 border-t border-white/10 text-[10px]">
                                    <div class="flex items-center gap-1">
                                        <span class="text-gray-400">{{ __('Min') }}:</span>
                                        <span class="text-cyan-400 font-semibold" x-text="pressureChartData.min !== null ? formatPressureValue(pressureChartData.min) : '--'"></span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span class="text-gray-400">{{ __('Max') }}:</span>
                                        <span class="text-red-400 font-semibold" x-text="pressureChartData.max !== null ? formatPressureValue(pressureChartData.max) : '--'"></span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span class="text-gray-400">{{ __('Range') }}:</span>
                                        <span class="text-gray-300 font-semibold" x-text="pressureChartData.range !== null ? formatPressureValue(pressureChartData.range) : '--'"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div><!-- End relative z-10 wrapper -->
	                </div>
	                </template>
	            </div>

		            <!-- MIDDLE COLUMN - Sortable -->
		            <div id="sortable-middle-column" class="col-span-1 md:col-span-1 lg:col-span-5 space-y-4">
                        @if($ssrDashboard && count($ssrFallbackGroups['sortable-middle-column'] ?? []) > 0)
                            @foreach($ssrFallbackGroups['sortable-middle-column'] as $ssrCard)
                                <article x-show="ssrFallbackVisible"
                                         class="ssr-fallback-block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
                                         data-widget="{{ $ssrCard['id'] ?? 'widget' }}">
                                    <div class="flex items-center justify-between mb-3">
                                        <h2 class="font-semibold">{{ $ssrCard['title'] ?? __('Weather') }}</h2>
                                        <span class="text-[10px] text-gray-500 uppercase tracking-wide">SSR</span>
                                    </div>
                                    <div class="space-y-1.5 text-sm text-gray-300">
                                        @foreach(($ssrCard['lines'] ?? []) as $ssrLine)
                                            <p class="leading-snug">{{ $ssrLine }}</p>
                                        @endforeach
                                    </div>
                                </article>
                            @endforeach
                        @endif
		                
		                <!-- Forecast -->
		                <template x-if="isWidgetEnabled('forecast')">
		                <div id="forecast" class="sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
		                     data-widget="forecast"
		                     @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </div>
                    <!-- Offline Badge (Centered) -->
                    <div x-cloak x-show="healthStatus.forecast?.is_stale === true" class="absolute inset-0 flex items-center justify-center z-30 pointer-events-none">
                        <div class="flex flex-col items-center gap-2 text-white bg-red-600/90 backdrop-blur-sm px-6 py-4 rounded-xl shadow-2xl animate-pulse">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <span class="text-lg font-bold">{{ __('Offline') }}</span>
                        </div>
                    </div>                    <!-- Update Timestamp (Top Center) -->
                    <div x-cloak x-show="healthStatus.forecast?.is_stale !== true && getHealthTimestamp('forecast')" class="absolute top-2 left-1/2 -translate-x-1/2 flex items-center gap-0.5 text-[10px] text-gray-400 bg-black/20 px-1.5 py-0.5 rounded z-10">
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span x-text="getHealthTimestamp('forecast')"></span>
                    </div>

                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold">{{ __('Forecast') }}</h3>
                        <div class="flex gap-1 text-xs">
                            <button @click="forecastView = 'daily5'" 
                                    :class="forecastView === 'daily5' ? 'bg-blue-600 shadow-lg shadow-blue-600/30' : 'bg-white/10 hover:bg-white/20'" 
                                    class="px-3 py-1 rounded transition-colors">{{ __('5 days') }}</button>
                            <button @click="forecastView = 'hourly'" 
                                    :class="forecastView === 'hourly' ? 'bg-blue-600 shadow-lg shadow-blue-600/30' : 'bg-white/10 hover:bg-white/20'" 
                                    class="px-3 py-1 rounded transition-colors">{{ __('Hourly') }}</button>
                            <button @click="forecastView = 'daily14'" 
                                    :class="forecastView === 'daily14' ? 'bg-blue-600 shadow-lg shadow-blue-600/30' : 'bg-white/10 hover:bg-white/20'" 
                                    class="px-3 py-1 rounded transition-colors">{{ __('14 days') }}</button>
                        </div>
                    </div>
                    
                    <!-- 5 Day View -->
                    <div x-show="forecastView === 'daily5'" class="flex gap-2 overflow-x-auto pb-2 -mx-2 px-2 snap-x">
                        @if($ssrDashboard)
                        <div x-show="forecast.length === 0" class="ssr-fallback-block contents">
                            @forelse(array_slice($ssrForecast, 0, 5) as $day)
                                <div class="text-center p-3 rounded-xl min-w-[72px] flex-shrink-0 snap-start transition-all bg-white/5">
                                    <div class="text-xs text-gray-400">{{ (string) ($day['date'] ?? '--') }}</div>
                                    <div class="font-bold text-sm">
                                        <span class="text-weather-warm">{{ isset($day['temp_high']) && is_numeric($day['temp_high']) ? round((float) $day['temp_high']) . '°' : '--' }}</span>
                                        <span class="text-gray-500">/</span>
                                        <span class="text-weather-cold">{{ isset($day['temp_low']) && is_numeric($day['temp_low']) ? round((float) $day['temp_low']) . '°' : '--' }}</span>
                                    </div>
                                    @if(isset($day['precipitation']) && is_numeric($day['precipitation']) && (float) $day['precipitation'] > 0)
                                        <div class="text-[10px] text-blue-400 mt-1">💧{{ round((float) $day['precipitation'], 1) }}</div>
                                    @endif
                                </div>
                            @empty
                                <div class="text-xs text-gray-500 py-2">{{ __('No forecast data') }}</div>
                            @endforelse
                        </div>
                        @endif
                        <template x-for="(day, idx) in forecast.slice(0, 5)" :key="'d5-'+day.date">
                            <div class="text-center p-3 rounded-xl min-w-[72px] flex-shrink-0 snap-start transition-all hover:bg-white/10"
                                 :class="idx === 0 ? 'bg-white/10' : 'bg-white/5'">
                                <div class="text-xs text-gray-400" x-text="formatDate(day.date)"></div>
		                                <img :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/' + getWeatherIconForSymbol(day.symbol, day.date) + '.svg'"
		                                     class="w-8 h-8 mx-auto my-1" alt="Weather">
                                <div class="font-bold text-sm">
                                    <span class="text-weather-warm" x-text="formatTemp(day.temp_high, 0)"></span>
                                    <span class="text-gray-500">/</span>
                                    <span class="text-weather-cold" x-text="formatTemp(day.temp_low, 0)"></span>
                                </div>
                                <div class="text-[10px] text-blue-400 mt-1" x-show="day.precipitation > 0">
                                    💧<span x-text="formatRain(day.precipitation, 1)"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                    
                    <!-- Hourly View -->
                    <div x-show="forecastView === 'hourly'" class="flex gap-2 overflow-x-auto pb-2 -mx-2 px-2 snap-x">
                        @if($ssrDashboard)
                        <div x-show="hourlyForecast.length === 0" class="ssr-fallback-block contents">
                            @forelse(array_slice($ssrHourlyForecast, 0, 12) as $hour)
                                <div class="text-center p-3 rounded-xl min-w-[64px] flex-shrink-0 snap-start transition-all bg-white/5">
                                    <div class="text-xs text-gray-400">
                                        @if(!empty($hour['time']))
                                            @php
                                                try {
                                                    $hourLabel = \Carbon\Carbon::parse($hour['time'])->timezone($stationTimezone ?? \App\Models\Setting::timezone())->format('H:i');
                                                } catch (\Throwable $e) {
                                                    $hourLabel = '--';
                                                }
                                            @endphp
                                            {{ $hourLabel }}
                                        @else
                                            --
                                        @endif
                                    </div>
                                    <div class="font-bold text-sm">{{ isset($hour['temperature']) && is_numeric($hour['temperature']) ? round((float) $hour['temperature']) . '°' : '--' }}</div>
                                    @if(isset($hour['precipitation_1h']) && is_numeric($hour['precipitation_1h']) && (float) $hour['precipitation_1h'] > 0)
                                        <div class="text-[10px] text-blue-400 mt-1">💧{{ round((float) $hour['precipitation_1h'], 1) }}</div>
                                    @endif
                                </div>
                            @empty
                                <div class="text-xs text-gray-500 py-2">{{ __('No hourly data') }}</div>
                            @endforelse
                        </div>
                        @endif
                        <template x-for="(hour, idx) in hourlyForecast.slice(0, 12)" :key="'hr-'+idx">
                            <div class="text-center p-3 rounded-xl min-w-[64px] flex-shrink-0 snap-start transition-all hover:bg-white/10"
                                 :class="idx === 0 ? 'bg-white/10' : 'bg-white/5'">
                                <div class="text-xs text-gray-400" x-text="formatHour(hour.time)"></div>
		                                <img :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/' + getWeatherIconForSymbol(hour.symbol, null, hour.time) + '.svg'"
		                                     class="w-8 h-8 mx-auto my-1" alt="Weather">
                                <div class="font-bold text-sm" x-text="formatTemp(hour.temperature, 0)"></div>
                                <div class="text-[10px] text-blue-400 mt-1" x-show="hour.precipitation_1h > 0">
                                    💧<span x-text="formatRain(hour.precipitation_1h, 1)"></span>
                                </div>
                            </div>
                        </template>
                        @if($menuFeatures['forecast'] ?? true)
                            <a href="{{ route('forecast') }}" class="text-center p-3 rounded-xl min-w-[64px] flex-shrink-0 snap-start bg-white/5 hover:bg-white/10 transition-all flex flex-col items-center justify-center">
                                <div class="text-gray-400 text-xs">{{ __('View') }}</div>
                                <div class="text-blue-400 text-sm font-medium">{{ __('more') }} →</div>
                            </a>
                        @endif
                    </div>
                    
                    <!-- 14 Day View -->
                    <div x-show="forecastView === 'daily14'" class="flex gap-2 overflow-x-auto pb-2 -mx-2 px-2 snap-x">
                        @if($ssrDashboard)
                        <div x-show="forecast.length === 0" class="ssr-fallback-block contents">
                            @forelse($ssrForecast as $day)
                                <div class="text-center p-3 rounded-xl min-w-[64px] flex-shrink-0 snap-start transition-all bg-white/5">
                                    <div class="text-xs text-gray-400">{{ (string) ($day['date'] ?? '--') }}</div>
                                    <div class="font-bold text-xs">
                                        <span class="text-weather-warm">{{ isset($day['temp_high']) && is_numeric($day['temp_high']) ? round((float) $day['temp_high']) . '°' : '--' }}</span>
                                        <span class="text-gray-500">/</span>
                                        <span class="text-weather-cold">{{ isset($day['temp_low']) && is_numeric($day['temp_low']) ? round((float) $day['temp_low']) . '°' : '--' }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="text-xs text-gray-500 py-2">{{ __('No forecast data') }}</div>
                            @endforelse
                        </div>
                        @endif
                        <template x-for="(day, idx) in forecast" :key="'d14-'+day.date">
                            <div class="text-center p-3 rounded-xl min-w-[64px] flex-shrink-0 snap-start transition-all hover:bg-white/10"
                                 :class="idx === 0 ? 'bg-white/10' : 'bg-white/5'">
                                <div class="text-xs text-gray-400" x-text="formatDate(day.date)"></div>
		                                <img :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/' + getWeatherIconForSymbol(day.symbol, day.date) + '.svg'"
		                                     class="w-6 h-6 mx-auto my-1" alt="Weather">
                                <div class="font-bold text-xs">
                                    <span class="text-weather-warm" x-text="formatTemp(day.temp_high, 0)"></span>
                                    <span class="text-gray-500">/</span>
                                    <span class="text-weather-cold" x-text="formatTemp(day.temp_low, 0)"></span>
                                </div>
                            </div>
                        </template>
                        @if($menuFeatures['forecast'] ?? true)
                            <a href="{{ route('forecast') }}" class="text-center p-3 rounded-xl min-w-[64px] flex-shrink-0 snap-start bg-white/5 hover:bg-white/10 transition-all flex flex-col items-center justify-center">
                                <div class="text-gray-400 text-xs">{{ __('Full') }}</div>
                                <div class="text-blue-400 text-sm font-medium">{{ __('overview') }} →</div>
                            </a>
                        @endif
                    </div>
	                </div>
	                </template>

	                <!-- Temperature Chart -->
	                <template x-if="isWidgetEnabled('hourly')">
		                <div class="sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
		                     data-widget="hourly"
		                     @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)"
		                     x-data="{ tempChartView: '24u' }">
                    <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-semibold">{{ __('Temperature') }}</h3>
                            <div class="text-[10px] text-gray-500">
                                <span x-show="tempChartShowObserved">{{ __('Observed') }} + {{ __('Forecast') }}</span>
                                <span x-show="!tempChartShowObserved">{{ __('Forecast') }}</span>
                            </div>
                        </div>
                        <div class="flex gap-1 text-xs">
                            <button @click="tempChartView = '24u'" 
                                    :class="tempChartView === '24u' ? 'bg-blue-600 shadow-lg shadow-blue-600/30' : 'bg-white/10 hover:bg-white/20'" 
                                    class="px-3 py-1 rounded transition-colors">{{ __('24h') }}</button>
                            <button @click="tempChartView = 'week'" 
                                    :class="tempChartView === 'week' ? 'bg-blue-600 shadow-lg shadow-blue-600/30' : 'bg-white/10 hover:bg-white/20'" 
                                    class="px-3 py-1 rounded transition-colors">{{ __('Week') }}</button>
                        </div>
                    </div>
                    
                    <!-- 24 Hour View -->
                    <div x-show="tempChartView === '24u'" class="space-y-2">
                        <div class="flex">
                            <!-- Y-axis labels -->
                            <div class="flex flex-col justify-between text-[10px] text-gray-500 pr-2 py-1 w-8 text-right">
                                <span class="text-weather-warm" x-text="formatTemp(getHourlyMax(), 0)"></span>
                                <span x-text="formatTemp((getHourlyMax() + getHourlyMin()) / 2, 0)"></span>
                                <span class="text-weather-cold" x-text="formatTemp(getHourlyMin(), 0)"></span>
                            </div>
                            <!-- Chart area -->
                            <div class="flex-1 h-28 bg-gradient-to-b from-weather-warm/5 to-weather-cold/5 rounded-lg relative overflow-hidden">
                                <!-- Horizontal guide lines -->
                                <div class="absolute inset-0 flex flex-col justify-between py-2 pointer-events-none">
                                    <div class="border-t border-white/5"></div>
                                    <div class="border-t border-white/10 border-dashed"></div>
                                    <div class="border-t border-white/5"></div>
                                </div>
                                <svg class="w-full h-full relative z-10 p-2" viewBox="0 0 400 100" preserveAspectRatio="none">
                                    <defs>
                                        <linearGradient id="tempGrad24" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.4"/>
                                            <stop offset="100%" stop-color="#06b6d4" stop-opacity="0.1"/>
                                        </linearGradient>
                                    </defs>
                                    <!-- Current time marker -->
                                    <g x-show="tempChartShowNowLine && getTempChartNowX() !== null" opacity="0.7">
                                        <line :x1="getTempChartNowX() === null ? 0 : getTempChartNowX()"
                                              y1="0"
                                              :x2="getTempChartNowX() === null ? 0 : getTempChartNowX()"
                                              y2="100"
                                              stroke="rgba(255,255,255,0.35)"
                                              stroke-width="1"
                                              stroke-dasharray="3 3"/>
                                    </g>
                                    <path :d="getHourlyTempPath(true)" fill="url(#tempGrad24)"/>
                                    <!-- Observed (station) temperature -->
                                    <path x-show="tempChartShowObserved"
                                          :d="getObservedTempPath()"
                                          fill="none"
                                          stroke="rgba(34,197,94,0.9)"
                                          stroke-width="1.8"
                                          stroke-linecap="round"/>
                                    <path :d="getHourlyTempPath(false)" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round"/>
                                    <circle x-show="tempChartShowNowLine && current?.temperature !== null && current?.temperature !== undefined && getTempChartNowX() !== null"
                                            :cx="getTempChartNowX() === null ? 0 : getTempChartNowX()"
                                            :cy="getTempY(Number(current.temperature), getHourlyMin(), getHourlyMax())"
                                            r="2.2"
                                            fill="rgba(255,255,255,0.85)"
                                            stroke="rgba(0,0,0,0.25)"
                                            stroke-width="0.6"/>
                                </svg>
                            </div>
                        </div>
                        <div class="flex">
                            <div class="w-8"></div>
                            <div x-show="!tempChartShowObserved" class="flex-1 flex justify-between text-[10px] text-gray-500 px-1">
                                <span x-text="formatHour(hourlyForecast[0]?.time)"></span>
                                <span x-text="formatHour(hourlyForecast[6]?.time)"></span>
                                <span x-text="formatHour(hourlyForecast[12]?.time)"></span>
                                <span x-text="formatHour(hourlyForecast[18]?.time)"></span>
                                <span x-text="formatHour(hourlyForecast[23]?.time)"></span>
                            </div>
                            <div x-show="tempChartShowObserved" class="flex-1 flex justify-between text-[10px] text-gray-500 px-1">
                                <template x-for="(label, idx) in getBlendedTempAxisLabels()" :key="'tlabel-'+idx">
                                    <span x-text="label"></span>
                                </template>
                            </div>
                        </div>
                        <div x-show="tempChartShowObserved" class="flex justify-center gap-4 text-[10px] text-gray-400">
                            <span class="flex items-center gap-1"><span class="w-3 h-0.5 rounded" style="background: rgba(34,197,94,0.9)"></span> {{ __('Observed') }}</span>
                            <span class="flex items-center gap-1"><span class="w-3 h-0.5 bg-weather-warm rounded"></span> {{ __('Forecast') }}</span>
                        </div>
                    </div>
                    
                    <!-- Week View -->
                    <div x-show="tempChartView === 'week'" class="space-y-2">
                        <div class="flex">
                            <!-- Y-axis labels -->
                            <div class="flex flex-col justify-between text-[10px] text-gray-500 pr-2 py-1 w-8 text-right">
                                <span class="text-weather-warm" x-text="formatTemp(getWeeklyMax(), 0)"></span>
                                <span x-text="formatTemp((getWeeklyMax() + getWeeklyMin()) / 2, 0)"></span>
                                <span class="text-weather-cold" x-text="formatTemp(getWeeklyMin(), 0)"></span>
                            </div>
                            <!-- Chart area -->
                            <div class="flex-1 h-28 bg-gradient-to-b from-weather-warm/5 to-weather-cold/5 rounded-lg relative overflow-hidden">
                                <!-- Horizontal guide lines -->
                                <div class="absolute inset-0 flex flex-col justify-between py-2 pointer-events-none">
                                    <div class="border-t border-white/5"></div>
                                    <div class="border-t border-white/10 border-dashed"></div>
                                    <div class="border-t border-white/5"></div>
                                </div>
                                <svg class="w-full h-full relative z-10 p-2" viewBox="0 0 400 100" preserveAspectRatio="none">
                                    <defs>
                                        <linearGradient id="tempGradWeek" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.3"/>
                                            <stop offset="50%" stop-color="#06b6d4" stop-opacity="0.1"/>
                                            <stop offset="100%" stop-color="#06b6d4" stop-opacity="0.3"/>
                                        </linearGradient>
                                    </defs>
                                    <!-- High temp line -->
                                    <path :d="getWeeklyHighPath()" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round"/>
                                    <!-- Low temp line -->
                                    <path :d="getWeeklyLowPath()" fill="none" stroke="#06b6d4" stroke-width="2" stroke-linecap="round"/>
                                    <!-- Fill between -->
                                    <path :d="getWeeklyFillPath()" fill="url(#tempGradWeek)" opacity="0.5"/>
                                </svg>
                            </div>
                        </div>
                        <div class="flex">
                            <div class="w-8"></div>
                            <div class="flex-1 flex justify-between text-[10px] text-gray-500 px-1">
                                <template x-for="(day, idx) in forecast.slice(0, 7)" :key="'label-'+idx">
                                    <span x-text="formatShortDay(day.date)"></span>
                                </template>
                            </div>
                        </div>
                        <div class="flex justify-center gap-4 text-[10px] text-gray-400">
                            <span class="flex items-center gap-1"><span class="w-3 h-0.5 bg-weather-warm rounded"></span> {{ __('Max') }}</span>
                            <span class="flex items-center gap-1"><span class="w-3 h-0.5 bg-weather-cold rounded"></span> {{ __('Min') }}</span>
                        </div>
                    </div>
	                </div>
	                </template>

	                <!-- Rain Card -->
	                @php $rainVisualization = \App\Models\Setting::getValue('widgets.rain_visualization', 'ripple'); @endphp
	                <template x-if="isWidgetEnabled('rain')">
		                <div class="sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 relative overflow-hidden"
		                     data-widget="rain"
		                     @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">

                    @if($rainVisualization !== 'none')
                    <!-- Background Artistic Rain Visualization -->
	                    <div class="absolute inset-0 pointer-events-none z-0 fx-visual">
                        @if($rainVisualization === 'ripple')
                        <!-- Ripple Pond Background -->
                        <div class="rain-viz-ripple absolute inset-0 flex items-end justify-center overflow-hidden" x-data="{
                            get dailyRain() {
                                return $root.current?.rain_daily || 0;
                            },
                            get rainLevel() {
                                // Daily rain accumulation: 0-35mm maps to 0-100%
                                // Threshold: 0mm = no puddle, 35mm = full puddle
                                return Math.min(100, Math.max(0, (this.dailyRain / 35) * 100));
                            },
                            get ring1Size() {
                                // Inner ring: fills from 0mm to 8.75mm (0-25% of total)
                                if (this.dailyRain <= 0) return 0;
                                if (this.dailyRain >= 8.75) return 100;
                                return (this.dailyRain / 8.75) * 100;
                            },
                            get ring2Size() {
                                // Second ring: fills from 8.75mm to 17.5mm (25-50% of total)
                                if (this.dailyRain <= 8.75) return 0;
                                if (this.dailyRain >= 17.5) return 100;
                                return ((this.dailyRain - 8.75) / 8.75) * 100;
                            },
                            get ring3Size() {
                                // Third ring: fills from 17.5mm to 26.25mm (50-75% of total)
                                if (this.dailyRain <= 17.5) return 0;
                                if (this.dailyRain >= 26.25) return 100;
                                return ((this.dailyRain - 17.5) / 8.75) * 100;
                            },
                            get ring4Size() {
                                // Outer ring: fills from 26.25mm to 35mm (75-100% of total)
                                if (this.dailyRain <= 26.25) return 0;
                                if (this.dailyRain >= 35) return 100;
                                return ((this.dailyRain - 26.25) / 8.75) * 100;
                            }
                        }">
                            <svg class="w-full h-full opacity-40" viewBox="0 0 200 100" preserveAspectRatio="xMidYMid slice">
                                <defs>
                                    <!-- Water surface glow -->
                                    <radialGradient id="rippleGradBg" cx="50%" cy="80%" r="60%">
                                        <stop offset="0%" style="stop-color:rgba(59,130,246,0.5)"/>
                                        <stop offset="100%" style="stop-color:rgba(59,130,246,0)"/>
                                    </radialGradient>
                                    <!-- Distinctive water ring gradients - more visible blue/cyan color -->
                                    <radialGradient id="rippleRing1" cx="50%" cy="50%" r="50%">
                                        <stop offset="0%" style="stop-color:rgba(59,130,246,0.9);stop-opacity:0.9"/>
                                        <stop offset="50%" style="stop-color:rgba(96,165,250,0.7);stop-opacity:0.7"/>
                                        <stop offset="75%" style="stop-color:rgba(147,197,253,0.4);stop-opacity:0.4"/>
                                        <stop offset="100%" style="stop-color:rgba(147,197,253,0);stop-opacity:0"/>
                                    </radialGradient>
                                    <radialGradient id="rippleRing2" cx="50%" cy="50%" r="50%">
                                        <stop offset="0%" style="stop-color:rgba(59,130,246,0.8);stop-opacity:0.8"/>
                                        <stop offset="50%" style="stop-color:rgba(96,165,250,0.6);stop-opacity:0.6"/>
                                        <stop offset="75%" style="stop-color:rgba(147,197,253,0.3);stop-opacity:0.3"/>
                                        <stop offset="100%" style="stop-color:rgba(147,197,253,0);stop-opacity:0"/>
                                    </radialGradient>
                                    <radialGradient id="rippleRing3" cx="50%" cy="50%" r="50%">
                                        <stop offset="0%" style="stop-color:rgba(59,130,246,0.7);stop-opacity:0.7"/>
                                        <stop offset="50%" style="stop-color:rgba(96,165,250,0.5);stop-opacity:0.5"/>
                                        <stop offset="75%" style="stop-color:rgba(147,197,253,0.25);stop-opacity:0.25"/>
                                        <stop offset="100%" style="stop-color:rgba(147,197,253,0);stop-opacity:0"/>
                                    </radialGradient>
                                    <radialGradient id="rippleRing4" cx="50%" cy="50%" r="50%">
                                        <stop offset="0%" style="stop-color:rgba(59,130,246,0.6);stop-opacity:0.6"/>
                                        <stop offset="50%" style="stop-color:rgba(96,165,250,0.4);stop-opacity:0.4"/>
                                        <stop offset="75%" style="stop-color:rgba(147,197,253,0.2);stop-opacity:0.2"/>
                                        <stop offset="100%" style="stop-color:rgba(147,197,253,0);stop-opacity:0"/>
                                    </radialGradient>
                                    <!-- Blur filter for soft, organic edges -->
                                    <filter id="rippleBlur" x="-50%" y="-50%" width="200%" height="200%">
                                        <feGaussianBlur stdDeviation="2" result="blur"/>
                                        <feMerge>
                                            <feMergeNode in="blur"/>
                                            <feMergeNode in="SourceGraphic"/>
                                        </feMerge>
                                    </filter>
                                </defs>
                                <!-- Water surface glow -->
                                <ellipse cx="100" cy="85" rx="120" ry="30" fill="url(#rippleGradBg)" opacity="0.4"/>
                                <!-- Puddle rings - fill from center outward based on daily rain accumulation -->
                                <!-- Rings expand progressively: inner ring fills first, then outer rings -->
                                <!-- Filled rings with blur for organic look -->
                                <g class="ripple-group" filter="url(#rippleBlur)">
                                    <!-- Ring 1 (innermost): 0-8.75mm daily rain -->
                                    <ellipse class="puddle-ring puddle-ring-1" cx="100" cy="80" 
                                             :rx="5 + (15 * (ring1Size / 100))" 
                                             :ry="2 + (6 * (ring1Size / 100))" 
                                             fill="url(#rippleRing1)" 
                                             :opacity="0.5 + (0.4 * (ring1Size / 100))"
                                             style="transition: all 1.5s ease-out;"/>
                                    <!-- Ring 2: 8.75-17.5mm daily rain -->
                                    <ellipse class="puddle-ring puddle-ring-2" cx="100" cy="80" 
                                             :rx="20 + (20 * (ring2Size / 100))" 
                                             :ry="7 + (7 * (ring2Size / 100))" 
                                             fill="url(#rippleRing2)" 
                                             :opacity="0.4 + (0.35 * (ring2Size / 100))"
                                             style="transition: all 1.5s ease-out;"/>
                                    <!-- Ring 3: 17.5-26.25mm daily rain -->
                                    <ellipse class="puddle-ring puddle-ring-3" cx="100" cy="80" 
                                             :rx="40 + (25 * (ring3Size / 100))" 
                                             :ry="14 + (8 * (ring3Size / 100))" 
                                             fill="url(#rippleRing3)" 
                                             :opacity="0.35 + (0.3 * (ring3Size / 100))"
                                             style="transition: all 1.5s ease-out;"/>
                                    <!-- Ring 4 (outermost): 26.25-35mm daily rain -->
                                    <ellipse class="puddle-ring puddle-ring-4" cx="100" cy="80" 
                                             :rx="65 + (30 * (ring4Size / 100))" 
                                             :ry="22 + (10 * (ring4Size / 100))" 
                                             fill="url(#rippleRing4)" 
                                             :opacity="0.3 + (0.25 * (ring4Size / 100))"
                                             style="transition: all 1.5s ease-out;"/>
                                    <!-- Rain drops splash points - subtle pulsing -->
                                    <circle class="raindrop-splash" cx="100" cy="80" r="3" fill="rgba(191,219,254,0.6)" :opacity="0.3 + (0.3 * (rainLevel / 100))"/>
                                    <circle class="raindrop-splash" cx="60" cy="75" r="2" fill="rgba(191,219,254,0.4)" :opacity="0.2 + (0.2 * (rainLevel / 100))" style="animation-delay: 0.7s;"/>
                                    <circle class="raindrop-splash" cx="145" cy="82" r="2" fill="rgba(191,219,254,0.4)" :opacity="0.2 + (0.2 * (rainLevel / 100))" style="animation-delay: 1.3s;"/>
                                </g>
                                <!-- Subtle ring outlines - always visible as indicators for visitors -->
                                <g class="ripple-outlines">
                                    <!-- Ring 1 outline: 0-8.75mm daily rain - always visible at full size -->
                                    <ellipse class="puddle-ring-outline puddle-ring-outline-1" cx="100" cy="80" 
                                             rx="20" 
                                             ry="8" 
                                             fill="none"
                                             stroke="rgba(191,219,254,0.5)"
                                             stroke-width="1"
                                             opacity="0.35"
                                             style="transition: all 1.5s ease-out;"/>
                                    <!-- Ring 2 outline: 8.75-17.5mm daily rain - always visible at full size -->
                                    <ellipse class="puddle-ring-outline puddle-ring-outline-2" cx="100" cy="80" 
                                             rx="40" 
                                             ry="14" 
                                             fill="none"
                                             stroke="rgba(191,219,254,0.5)"
                                             stroke-width="1"
                                             opacity="0.35"
                                             style="transition: all 1.5s ease-out;"/>
                                    <!-- Ring 3 outline: 17.5-26.25mm daily rain - always visible at full size -->
                                    <ellipse class="puddle-ring-outline puddle-ring-outline-3" cx="100" cy="80" 
                                             rx="65" 
                                             ry="22" 
                                             fill="none"
                                             stroke="rgba(191,219,254,0.5)"
                                             stroke-width="1"
                                             opacity="0.35"
                                             style="transition: all 1.5s ease-out;"/>
                                    <!-- Ring 4 outline: 26.25-35mm daily rain - always visible at full size -->
                                    <ellipse class="puddle-ring-outline puddle-ring-outline-4" cx="100" cy="80" 
                                             rx="95" 
                                             ry="32" 
                                             fill="none"
                                             stroke="rgba(191,219,254,0.5)"
                                             stroke-width="1"
                                             opacity="0.35"
                                             style="transition: all 1.5s ease-out;"/>
                                </g>
                            </svg>
                            <style>
                                /* Subtle pulsing animation for puddle rings - organic water movement */
                                .puddle-ring {
                                    animation: puddlePulse 4s ease-in-out infinite;
                                    transform-origin: 100px 80px;
                                }
                                .puddle-ring-1 {
                                    animation-duration: 3s;
                                }
                                .puddle-ring-2 {
                                    animation-duration: 3.5s;
                                    animation-delay: 0.4s;
                                }
                                .puddle-ring-3 {
                                    animation-duration: 4s;
                                    animation-delay: 0.8s;
                                }
                                .puddle-ring-4 {
                                    animation-duration: 4.5s;
                                    animation-delay: 1.2s;
                                }
                                @keyframes puddlePulse {
                                    0%, 100% {
                                        transform: scale(1);
                                        opacity: 1;
                                    }
                                    50% {
                                        transform: scale(1.02);
                                        opacity: 0.95;
                                    }
                                }
                                /* Raindrop splash subtle animation */
                                .raindrop-splash {
                                    animation: dropSplash 3s ease-out infinite;
                                }
                                @keyframes dropSplash {
                                    0%, 100% {
                                        transform: scale(1);
                                        opacity: 1;
                                    }
                                    50% {
                                        transform: scale(1.1);
                                        opacity: 0.8;
                                    }
                                }
                            </style>
                        </div>
                        @elseif($rainVisualization === 'mountain')
                        <!-- Mountain Lake Background -->
                        <div class="rain-viz-mountain absolute inset-0 overflow-hidden">
                            <svg class="w-full h-full opacity-50" viewBox="0 0 200 100" preserveAspectRatio="xMidYMid slice" x-data="{
                                get dailyRain() {
                                    return current?.rain_daily || 0;
                                },
                                get fillPercent() {
                                    // Simple linear mapping: 0mm→85%, 35mm→50% of viewBox height
                                    // Water rises from bottom (y=85) to halfway (y=50)
                                    const waterY = 85 - (this.dailyRain / 35) * 35;
                                    return 100 - waterY;
                                }
                            }">
                                <defs>
                                    <linearGradient id="mountainGradBg" x1="0%" y1="0%" x2="0%" y2="100%">
                                        <stop offset="0%" style="stop-color:rgba(51,65,85,0.6)"/>
                                        <stop offset="100%" style="stop-color:rgba(30,41,59,0.8)"/>
                                    </linearGradient>
                                    <linearGradient id="waterGradBg" x1="0%" y1="0%" x2="0%" y2="100%">
                                        <stop offset="0%" style="stop-color:rgba(59,130,246,0.4)"/>
                                        <stop offset="100%" style="stop-color:rgba(30,64,175,0.6)"/>
                                    </linearGradient>
                                    <clipPath id="mountainClipBg">
                                        <rect x="0" y="0" width="200" height="100"/>
                                    </clipPath>
                                </defs>
                                <!-- Mountains silhouette -->
                                <path d="M0,100 L0,55 L25,30 L50,50 L75,20 L105,45 L135,15 L165,40 L200,25 L200,100 Z" fill="url(#mountainGradBg)"/>
                                <!-- Snow caps - triangular caps precisely aligned with mountain peak slopes -->
                                <!-- First peak at x=75, y=20 - triangle following left slope (50,50)→(75,20) and right slope (75,20)→(105,45) -->
                                <path d="M68,28 L75,20 L82,26 Z" fill="rgba(255,255,255,0.3)"/>
                                <!-- Second peak at x=135, y=15 - triangle following left slope (105,45)→(135,15) and right slope (135,15)→(165,40) -->
                                <path d="M127,23 L135,15 L143,22 Z" fill="rgba(255,255,255,0.3)"/>
                                <!-- Water level -->
                                <g clip-path="url(#mountainClipBg)">
                                    <rect class="water-level" x="-10" width="220" fill="url(#waterGradBg)" :y="100 - fillPercent" :height="fillPercent" style="transition: height 1.5s ease-out, y 1.5s ease-out;"/>
                                    <line class="water-shimmer" x1="-10" x2="210" stroke="rgba(147,197,253,0.4)" stroke-width="1.5" :y1="100 - fillPercent" :y2="100 - fillPercent" style="transition: y1 1.5s ease-out, y2 1.5s ease-out;"/>
                                </g>
                                <!-- NAP Peilstok - subtiel tussen Month en Year -->
                                <!-- Bij 0mm regen: water op y=85, dus 0-lijn peilstok ook op y=85 -->
                                <!-- Bij 35mm regen: water stijgt 60 punten (van y=85 naar y=25) -->
                                <g transform="translate(138, 18) rotate(3)" opacity="0.5">
                                    <!-- Paal/stok -->
                                    <defs>
                                        <linearGradient id="napPoleGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                            <stop offset="0%" style="stop-color:#4b5563"/>
                                            <stop offset="50%" style="stop-color:#6b7280"/>
                                            <stop offset="100%" style="stop-color:#4b5563"/>
                                        </linearGradient>
                                    </defs>
                                    <!-- Paal: van y=15 tot y=90 -->
                                    <rect x="-1.5" y="15" width="3" height="75" fill="url(#napPoleGrad)" rx="0.4" opacity="0.8"/>
                                    <!-- NAP bovenkant (rond) - kleiner en subtieler -->
                                    <circle cx="0" cy="12" r="4" fill="#1e3a8a" opacity="0.7"/>
                                    <circle cx="0" cy="12" r="2.8" fill="#3b82f6" opacity="0.8"/>
                                    <!-- NAP tekst op bovenkant -->
                                    <text x="0" y="12" text-anchor="middle" dominant-baseline="middle" font-size="2.2" font-weight="bold" fill="white" font-family="Arial, sans-serif" opacity="0.9">NAP</text>
                                    <!-- Meetstreepjes - exact aligned with water levels -->
                                    <!-- 35mm: water at y=50, local = 50-18 = 32 -->
                                    <line x1="-3" y1="32" x2="3" y2="32" stroke="#d1d5db" stroke-width="0.5" opacity="0.6"/> <!-- 35mm -->
                                    <!-- 15mm: water at y=70, local = 70-18 = 52 -->
                                    <line x1="-3.5" y1="52" x2="3.5" y2="52" stroke="#e5e7eb" stroke-width="0.7" opacity="0.7"/> <!-- 15mm -->
                                    <!-- 0mm: water at y=85, local = 85-18 = 67 -->
                                    <line x1="-4" y1="67" x2="4" y2="67" stroke="#fbbf24" stroke-width="0.9" opacity="0.8"/> <!-- 0mm = NAP -->
                                    <!-- mm markeringen (rechts van de paal, klein) -->
                                    <text x="5.5" y="33.5" font-size="2.2" fill="#d1d5db" font-family="Arial, sans-serif" font-weight="500" opacity="0.7">35</text>
                                    <text x="5.5" y="53.5" font-size="2.2" fill="#e5e7eb" font-family="Arial, sans-serif" font-weight="500" opacity="0.7">15</text>
                                    <text x="5.5" y="68.5" font-size="2.4" fill="#fbbf24" font-family="Arial, sans-serif" font-weight="600" opacity="0.85">0</text>
                                    <!-- Onderkant in de grond -->
                                    <rect x="-1.8" y="88" width="3.6" height="3" fill="#374151" rx="0.4" opacity="0.7"/>
                                </g>
                            </svg>
                        </div>
                        @elseif($rainVisualization === 'tree')
                        <!-- Growing Tree Background -->
                        <div class="rain-viz-tree absolute inset-0 overflow-hidden">
                            <svg class="w-full h-full opacity-50" viewBox="0 0 200 100" preserveAspectRatio="xMidYMid slice" x-data="{ growth: Math.min(100, Math.max(15, ((current?.rain_daily || 0) / 35) * 100)) }">
                                <defs>
                                    <linearGradient id="trunkGradBg" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%" style="stop-color:#78350f"/>
                                        <stop offset="50%" style="stop-color:#92400e"/>
                                        <stop offset="100%" style="stop-color:#78350f"/>
                                    </linearGradient>
                                    <linearGradient id="leafGradBg" x1="0%" y1="0%" x2="0%" y2="100%">
                                        <stop offset="0%" style="stop-color:#22c55e"/>
                                        <stop offset="100%" style="stop-color:#15803d"/>
                                    </linearGradient>
                                    <filter id="leafGlowBg" x="-50%" y="-50%" width="200%" height="200%">
                                        <feGaussianBlur stdDeviation="3" result="blur"/>
                                        <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
                                    </filter>
                                </defs>
                                <!-- Ground -->
                                <ellipse cx="100" cy="98" rx="80" ry="10" fill="rgba(120,53,15,0.2)"/>
                                <!-- Tree trunk -->
                                <rect class="tree-trunk" x="95" width="10" fill="url(#trunkGradBg)" rx="3" :y="98 - (growth * 0.6)" :height="growth * 0.6" style="transition: all 1s ease-out;"/>
                                <!-- Tree foliage -->
                                <g class="tree-foliage" filter="url(#leafGlowBg)" :style="'opacity: ' + (growth / 100) + '; transition: opacity 1s ease-out;'">
                                    <ellipse cx="100" :cy="98 - (growth * 0.6) - 8" :rx="20 + (growth * 0.25)" :ry="12 + (growth * 0.1)" fill="url(#leafGradBg)" opacity="0.8"/>
                                    <ellipse cx="100" :cy="98 - (growth * 0.6) - 22" :rx="16 + (growth * 0.2)" :ry="10 + (growth * 0.08)" fill="#16a34a" opacity="0.75"/>
                                    <ellipse cx="100" :cy="98 - (growth * 0.6) - 34" :rx="10 + (growth * 0.12)" :ry="7 + (growth * 0.05)" fill="#22c55e" opacity="0.85"/>
                                </g>
                            </svg>
                        </div>
                        @endif
                    </div>
                    @endif

                    <!-- Widget Content (on top of visualization) -->
                    <div class="relative z-10">
                        <div class="drag-handle absolute -top-3 -right-3 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                            <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                            </svg>
                        </div>
                        <!-- Offline Badge (Centered) -->
                        <div x-cloak x-show="healthStatus.sensor?.is_stale === true" class="absolute inset-0 flex items-center justify-center z-30 pointer-events-none">
                            <div class="flex flex-col items-center gap-2 text-white bg-red-600/90 backdrop-blur-sm px-6 py-4 rounded-xl shadow-2xl animate-pulse">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                <span class="text-lg font-bold">{{ __('Offline') }}</span>
                            </div>
                        </div>
                        <!-- Update Timestamp (Top Center) -->
                        <div x-cloak x-show="healthStatus.sensor?.is_stale !== true && getHealthTimestamp('sensor')" class="absolute -top-3 left-1/2 -translate-x-1/2 flex items-center gap-0.5 text-[10px] text-gray-400 bg-black/30 backdrop-blur-sm px-1.5 py-0.5 rounded z-10">
                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span x-text="getHealthTimestamp('sensor')"></span>
                        </div>

                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold">{{ __('Precipitation') }}</h3>
                            <span class="text-xs text-gray-400">{{ __('Last rain') }}: <span x-text="formatLastRainAt(current?.last_rain_at)"></span></span>
                        </div>
                        <div class="grid grid-cols-4 gap-2 text-center">
                            <div>
                                <div class="text-xl md:text-2xl font-bold font-display text-weather-rain data-value drop-shadow-lg" x-text="formatRainRateValue(current?.rain_rate)"></div>
                                <div class="text-[10px] text-gray-400" x-text="rainUnit() + rainRateSuffix()"></div>
                            </div>
                            <div>
                                <div class="text-xl md:text-2xl font-bold font-display data-value drop-shadow-lg" x-text="formatRainValue(current?.rain_daily)"></div>
                                <div class="text-[10px] text-gray-400">{{ __('Today') }}</div>
                            </div>
                            <div>
                                <div class="text-xl md:text-2xl font-bold font-display data-value drop-shadow-lg" x-text="formatRainValue(current?.rain_monthly)"></div>
                                <div class="text-[10px] text-gray-400">{{ __('Month') }}</div>
                            </div>
                            <div>
                                <div class="text-xl md:text-2xl font-bold font-display data-value drop-shadow-lg" x-text="formatRainValue(current?.rain_yearly, 0)"></div>
                                <div class="text-[10px] text-gray-400">{{ __('Year') }}</div>
                            </div>
                        </div>

                        <!-- Rain probability bars -->
                        <div class="mt-4 pt-4 border-t border-white/10">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-xs text-gray-400">{{ __('Precipitation chance in the coming hours') }}</div>
                                <div x-show="hourlyForecast.length > 0" class="text-xs">
                                    <span class="text-gray-500">{{ __('Next Rain') }}: </span>
                                    <span :class="nextRainInfo() ? 'text-blue-400 font-medium' : 'text-gray-500'"
                                          x-text="nextRainLabel()">{{ $ssrNextRainLabel }}</span>
                                </div>
                            </div>
                            <div class="flex gap-1">
                                @if($ssrDashboard)
                                <div x-show="hourlyForecast.length === 0" class="ssr-fallback-block contents">
                                    @foreach(array_slice($ssrHourlyForecast, 0, 6) as $hour)
                                        @php
                                            $hourRain = isset($hour['precipitation_1h']) && is_numeric($hour['precipitation_1h']) ? (float) $hour['precipitation_1h'] : 0.0;
                                            $hourRainOpacity = max(10, min(90, (int) round($hourRain * 10)));
                                        @endphp
                                        <div class="flex-1 h-8 rounded text-[10px] flex items-end justify-center pb-1 transition-all backdrop-blur-sm bg-blue-500"
                                             style="opacity: {{ max(0.15, min(0.95, $hourRainOpacity / 100)) }};">
                                            {{ round($hourRain, 1) }}
                                        </div>
                                    @endforeach
                                </div>
                                @endif
                                <template x-for="(hour, idx) in hourlyForecast.slice(0, 6)" :key="'rain-'+idx">
                                        <div class="flex-1 h-8 rounded text-[10px] flex items-end justify-center pb-1 transition-all backdrop-blur-sm"
                                             :class="'bg-blue-500/' + Math.max(10, Math.min(90, Math.round((hour.precipitation_1h || 0) * 10)))"
                                         x-text="formatRain(hour.precipitation_1h || 0, 1)"></div>
                                </template>
                            </div>
                        </div>
                    </div>
	                </div>
	                </template>
	            </div>

		            <!-- RIGHT COLUMN - Sortable -->
		            <div id="sortable-right-column" class="col-span-1 md:col-span-2 lg:col-span-3 space-y-4">
                        @if($ssrDashboard && count($ssrFallbackGroups['sortable-right-column'] ?? []) > 0)
                            @foreach($ssrFallbackGroups['sortable-right-column'] as $ssrCard)
                                <article x-show="ssrFallbackVisible"
                                         class="ssr-fallback-block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
                                         data-widget="{{ $ssrCard['id'] ?? 'widget' }}">
                                    <div class="flex items-center justify-between mb-3">
                                        <h2 class="font-semibold">{{ $ssrCard['title'] ?? __('Weather') }}</h2>
                                        <span class="text-[10px] text-gray-500 uppercase tracking-wide">SSR</span>
                                    </div>
                                    <div class="space-y-1.5 text-sm text-gray-300">
                                        @foreach(($ssrCard['lines'] ?? []) as $ssrLine)
                                            <p class="leading-snug">{{ $ssrLine }}</p>
                                        @endforeach
                                    </div>
                                </article>
                            @endforeach
                        @endif
		                
		                <!-- Sun & Moon -->
		                <template x-if="isWidgetEnabled('sun') || isWidgetEnabled('moon')">
                        @if($astronomyFeatureEnabled)
		                    <a href="{{ route('astronomy') }}"
		                       id="astronomy"
		                       class="block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 hover:border-orange-500/30 transition-colors cursor-pointer"
		                       data-widget="sun_moon"
		                       @click="editMode && $event.preventDefault()"
		                       @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                        @else
                            <div id="astronomy"
                                 class="block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 transition-colors cursor-default"
                                 data-widget="sun_moon"
                                 @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                        @endif
                    <!-- Drag Handle -->
                    <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20" @click.prevent.stop>
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </div>
                    <!-- Offline Badge (Centered) -->
                    <div x-cloak x-show="healthStatus.astronomy?.is_stale === true" class="absolute inset-0 flex items-center justify-center z-30 pointer-events-none">
                        <div class="flex flex-col items-center gap-2 text-white bg-red-600/90 backdrop-blur-sm px-6 py-4 rounded-xl shadow-2xl animate-pulse">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <span class="text-lg font-bold">{{ __('Offline') }}</span>
                        </div>
                    </div>                    <!-- Update Timestamp (Top Center) -->
                    <div x-cloak x-show="healthStatus.astronomy?.is_stale !== true && getHealthTimestamp('astronomy')" class="absolute top-2 left-1/2 -translate-x-1/2 flex items-center gap-0.5 text-[10px] text-gray-400 bg-black/20 px-1.5 py-0.5 rounded z-10">
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span x-text="getHealthTimestamp('astronomy')"></span>
                    </div>

                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold">{{ __('Sun & Moon') }}</h3>
                        @if($astronomyFeatureEnabled)
                            <span class="text-xs text-gray-500">{{ __('More') }} →</span>
                        @else
                            <span class="text-xs text-amber-400">{{ __('Page disabled') }}</span>
                        @endif
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
		                                <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/sunrise.svg') }}"
		                                     :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/sunrise.svg'"
		                                     class="w-8 h-8" alt="Sunrise">
                                <div>
                                    <div class="text-xs text-gray-400">{{ __('Sunrise') }}</div>
                                    <div class="font-bold" x-text="sun?.sunrise ?? '--:--'"></div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="text-right">
                                    <div class="text-xs text-gray-400">{{ __('Sunset') }}</div>
                                    <div class="font-bold" x-text="sun?.sunset ?? '--:--'"></div>
                                </div>
		                                <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/sunset.svg') }}"
		                                     :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/sunset.svg'"
		                                     class="w-8 h-8" alt="Sunset">
                            </div>
                        </div>
                        <div class="h-2 bg-white/10 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-orange-400 via-yellow-300 to-orange-400 transition-all duration-1000" 
                                 :style="{ width: (sun?.position_percent ?? getDaylightProgress()) + '%' }"></div>
                        </div>
                        <div class="flex justify-between text-sm">
                            <div>
                                <span class="text-gray-400">{{ __('Daylight') }} </span>
                                <span class="font-bold text-amber-400" x-text="sun?.day_length ?? '--:--'"></span>
                            </div>
                            <div x-show="sun?.day_length_change">
                                <span :class="sun?.day_length_change_seconds > 0 ? 'text-green-400' : 'text-red-400'" x-text="sun?.day_length_change"></span>
                            </div>
                        </div>
                        <div class="pt-4 border-t border-white/10 flex items-center justify-between">
                            <div class="flex items-center gap-3">
		                                <img :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/' + (moon?.icon ?? 'moon-waxing-crescent') + '.svg'"
		                                     class="w-12 h-12" alt="Moon phase">
                                <div>
                                    <div class="font-bold" x-text="translateMoonPhase(moon?.phase_name) || translations.loading"></div>
                                    <div class="text-xs text-gray-400"><span x-text="moon?.illumination ?? '--'"></span>% {{ __('illuminated') }}</div>
                                </div>
                            </div>
                            <div class="text-right text-xs" x-show="aurora?.kp !== undefined">
                                <div class="text-gray-400">{{ __('Kp Index') }}</div>
                                <div class="font-bold" :style="'color: ' + (aurora?.color ?? '#22c55e')" x-text="aurora?.kp ?? '--'">{{ isset($ssrAurora['kp']) ? $ssrAurora['kp'] : '--' }}</div>
                            </div>
                        </div>
                    </div>
                        @if($astronomyFeatureEnabled)
		                    </a>
                        @else
                            </div>
                        @endif
	                </template>

	                <!-- Astronomical Events Widget -->
                    @if(in_array('astro_events', $ssrEnabledWidgets, true) && count($ssrEvents) > 0)
                        @if($astronomyFeatureEnabled)
                            <a href="{{ route('astronomy') }}"
                               x-show="ssrFallbackVisible && isWidgetEnabled('astro_events') && astronomicalEvents.length === 0"
                               class="ssr-fallback-block block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 hover:border-orange-500/30 transition-colors cursor-pointer"
                               data-widget="astro_events"
                               @click="editMode && $event.preventDefault()"
                               @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                        @else
                            <div x-show="ssrFallbackVisible && isWidgetEnabled('astro_events') && astronomicalEvents.length === 0"
                                 class="ssr-fallback-block block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 transition-colors cursor-default"
                                 data-widget="astro_events"
                                 @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                        @endif
                        <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20" @click.prevent.stop>
                            <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                            </svg>
                        </div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold">📅 {{ __('Sky Events') }}</h3>
                            @if($astronomyFeatureEnabled)
                                <span class="text-xs text-gray-500">{{ __('More') }} →</span>
                            @else
                                <span class="text-xs text-amber-400">{{ __('Page disabled') }}</span>
                            @endif
                        </div>
                        <div class="space-y-2 min-w-0 overflow-hidden">
                            @foreach($ssrEvents as $event)
                                @php
                                    $eventType = (string) ($event['type'] ?? '');
                                    $eventTypeClass = match ($eventType) {
                                        'moon' => 'bg-blue-500/20 text-blue-400',
                                        'seasonal' => 'bg-orange-500/20 text-orange-400',
                                        'eclipse' => 'bg-purple-500/20 text-purple-400',
                                        'meteor' => 'bg-yellow-500/20 text-yellow-400',
                                        'planet' => 'bg-cyan-500/20 text-cyan-400',
                                        'earth' => 'bg-green-500/20 text-green-400',
                                        'comet' => 'bg-pink-500/20 text-pink-400',
                                        'special' => 'bg-indigo-500/20 text-indigo-400',
                                        'transit' => 'bg-amber-500/20 text-amber-400',
                                        default => 'bg-white/10 text-gray-300',
                                    };
                                @endphp
                                <div class="flex items-center justify-between gap-2 p-2 bg-white/5 rounded-lg min-w-0">
                                    <div class="flex items-center gap-2 min-w-0 flex-1">
                                        <span class="text-xl flex-shrink-0">{{ (string) ($event['emoji'] ?? '✨') }}</span>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm font-medium truncate">{{ (string) ($event['event'] ?? __('Event')) }}</div>
                                            <div class="text-xs text-gray-400">{{ (string) ($event['formatted_date'] ?? ($event['date'] ?? '--')) }}</div>
                                        </div>
                                    </div>
                                    <div class="text-xs px-2 py-0.5 rounded-full flex-shrink-0 whitespace-nowrap {{ $eventTypeClass }}">
                                        {{ (string) ($event['type'] ?? __('Sky')) }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if($astronomyFeatureEnabled)
                            </a>
                        @else
                            </div>
                        @endif
                    @endif
	                <template x-if="isWidgetEnabled('astro_events') && astronomicalEvents.length > 0">
                        @if($astronomyFeatureEnabled)
		                    <a href="{{ route('astronomy') }}"
		                       class="block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 hover:border-orange-500/30 transition-colors cursor-pointer"
		                       data-widget="astro_events"
		                       @click="editMode && $event.preventDefault()"
		                       @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                        @else
                            <div class="block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 transition-colors cursor-default"
                                 data-widget="astro_events"
                                 @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                        @endif
                    <!-- Drag Handle -->
                    <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20" @click.prevent.stop>
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </div>

                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold">📅 {{ __('Sky Events') }}</h3>
                        @if($astronomyFeatureEnabled)
                            <span class="text-xs text-gray-500">{{ __('More') }} →</span>
                        @else
                            <span class="text-xs text-amber-400">{{ __('Page disabled') }}</span>
                        @endif
                    </div>

                    <div class="space-y-2 min-w-0 overflow-hidden">
                        <template x-for="event in astronomicalEvents" :key="event.date + event.event">
                            <div class="flex items-center justify-between gap-2 p-2 bg-white/5 rounded-lg hover:bg-white/10 transition-colors min-w-0">
                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                    <span class="text-xl flex-shrink-0" x-text="event.emoji"></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-medium truncate" x-text="translateEvent(event.event)"></div>
                                        <div class="text-xs text-gray-400" x-text="event.formatted_date"></div>
                                        <template x-if="event.hint">
                                            <div class="text-xs text-gray-500 mt-0.5" x-text="translateEvent(event.hint)"></div>
                                        </template>
                                    </div>
                                </div>
                                <div class="text-xs px-2 py-0.5 rounded-full flex-shrink-0 whitespace-nowrap"
                                     :class="{
                                         'bg-blue-500/20 text-blue-400': event.type === 'moon',
                                         'bg-orange-500/20 text-orange-400': event.type === 'seasonal',
                                         'bg-purple-500/20 text-purple-400': event.type === 'eclipse',
                                         'bg-yellow-500/20 text-yellow-400': event.type === 'meteor',
                                         'bg-cyan-500/20 text-cyan-400': event.type === 'planet',
                                         'bg-green-500/20 text-green-400': event.type === 'earth',
                                         'bg-pink-500/20 text-pink-400': event.type === 'comet',
                                         'bg-indigo-500/20 text-indigo-400': event.type === 'special',
                                         'bg-amber-500/20 text-amber-400': event.type === 'transit'
                                     }"
                                     x-text="translateEventType(event.type)">
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-3 pt-3 border-t border-white/10 text-center">
                        <span class="text-xs text-gray-400">{{ __('View all upcoming events') }}</span>
                    </div>
                        @if($astronomyFeatureEnabled)
		                    </a>
                        @else
                            </div>
                        @endif
	                </template>

	                <!-- UV & Solar -->
	                <template x-if="isWidgetEnabled('uv') || isWidgetEnabled('solar')">
		                <div class="sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
		                     data-widget="uv_solar"
		                     @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </div>
                    <!-- Offline Badge (Centered) -->
                    <div x-cloak x-show="healthStatus.sensor?.is_stale === true" class="absolute inset-0 flex items-center justify-center z-30 pointer-events-none">
                        <div class="flex flex-col items-center gap-2 text-white bg-red-600/90 backdrop-blur-sm px-6 py-4 rounded-xl shadow-2xl animate-pulse">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <span class="text-lg font-bold">{{ __('Offline') }}</span>
                        </div>
                    </div>                    <!-- Update Timestamp (Top Center) -->
                    <div x-cloak x-show="healthStatus.sensor?.is_stale !== true && getHealthTimestamp('sensor')" class="absolute top-2 left-1/2 -translate-x-1/2 flex items-center gap-0.5 text-[10px] text-gray-400 bg-black/20 px-1.5 py-0.5 rounded z-10">
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span x-text="getHealthTimestamp('sensor')"></span>
                    </div>

                    <h3 class="font-semibold mb-4">{{ __('UV & Solar radiation') }}</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center p-3 bg-green-500/10 rounded-xl">
                            <div class="text-xs text-gray-400">{{ __('UV Index') }}</div>
                            <div class="text-3xl font-bold" :class="getUvColor(current?.uv_index)" x-text="current?.uv_index ?? '0'"></div>
                            <div class="text-xs" :class="getUvColor(current?.uv_index)" x-text="getUvLevel(current?.uv_index)"></div>
                        </div>
                        <div class="text-center p-3 bg-yellow-500/10 rounded-xl">
                            <div class="text-xs text-gray-400">{{ __('Radiation') }}</div>
                            <div class="text-3xl font-bold text-yellow-400 data-value" x-text="current?.solar_radiation ?? '--'">{{ isset($ssrCurrent['solar_radiation']) ? $ssrCurrent['solar_radiation'] : '--' }}</div>
                            <div class="text-xs text-gray-400">W/m²</div>
                        </div>
                    </div>
	                </div>
	                </template>

	                <!-- Air Quality -->
	                <template x-if="isWidgetEnabled('airquality')">
		                <div id="airquality" class="sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
		                     data-widget="airquality"
		                     @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </div>
                    <!-- Offline Badge (Centered) -->
                    <div x-cloak x-show="healthStatus.airquality?.is_stale === true" class="absolute inset-0 flex items-center justify-center z-30 pointer-events-none">
                        <div class="flex flex-col items-center gap-2 text-white bg-red-600/90 backdrop-blur-sm px-6 py-4 rounded-xl shadow-2xl animate-pulse">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <span class="text-lg font-bold">{{ __('Offline') }}</span>
                        </div>
                    </div>                    <!-- Update Timestamp (Top Center) -->
                    <div x-cloak x-show="healthStatus.airquality?.is_stale !== true && getHealthTimestamp('airquality')" class="absolute top-2 left-1/2 -translate-x-1/2 flex items-center gap-0.5 text-[10px] text-gray-400 bg-black/20 px-1.5 py-0.5 rounded z-10">
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span x-text="getHealthTimestamp('airquality')"></span>
                    </div>

                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold">{{ __('Air Quality') }}</h3>
                        <template x-if="airQuality">
                            <span class="text-xs px-2 py-0.5 rounded" 
                                  :style="'background-color: ' + (airQuality.category?.color || '#00e400') + '20; color: ' + (airQuality.category?.color || '#00e400')"
                                  x-text="getAqiEmoji(airQuality.category?.level) + ' ' + getAqiLevelTranslation(airQuality.category?.level)"></span>
                        </template>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center"
                             :style="'background-color: ' + (airQuality?.category?.color || '#00e400') + '20'">
                            <div class="text-center">
                                <div class="text-2xl font-bold" :style="'color: ' + (airQuality?.category?.color || '#00e400')" x-text="airQuality?.aqi ?? '--'">{{ isset($ssrAirQuality['aqi']) ? (int) $ssrAirQuality['aqi'] : '--' }}</div>
                                <div class="text-[10px] text-gray-400">{{ __('AQI') }}</div>
                            </div>
                        </div>
                        <div class="flex-1 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-400">PM2.5</span>
                                <span x-text="(airQuality?.pollutants?.pm25 ?? '--') + ' µg/m³'">{{ isset($ssrAirQuality['pollutants']['pm25']) ? $ssrAirQuality['pollutants']['pm25'] : '--' }} µg/m³</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">PM10</span>
                                <span x-text="(airQuality?.pollutants?.pm10 ?? '--') + ' µg/m³'">{{ isset($ssrAirQuality['pollutants']['pm10']) ? $ssrAirQuality['pollutants']['pm10'] : '--' }} µg/m³</span>
                            </div>
                            <template x-if="luftdatenNoise && luftdatenNoise.formatted && luftdatenNoise.formatted.noise_avg">
                                <div class="pt-2 mt-2 border-t border-white/10 space-y-1">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-400">🔊 {{ __('Noise') }}</span>
                                        <span x-text="(luftdatenNoise?.formatted?.noise_avg?.value != null ? Math.round(luftdatenNoise.formatted.noise_avg.value * 10) / 10 : '--') + ' dB(A)'">-- dB(A)</span>
                                    </div>
                                    <template x-if="luftdatenNoise?.noise_level">
                                        <div class="text-xs" :style="'color: ' + (luftdatenNoise.noise_level?.color || '#9ca3af')" x-text="luftdatenNoise.noise_level?.level ? (typeof t === 'function' ? t(luftdatenNoise.noise_level.level) : luftdatenNoise.noise_level.level) : ''"></div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
	                </div>
	                </template>

	                <!-- Pollen Widget -->
                <template x-if="isWidgetEnabled('pollen')">
                    @if($airPollenFeatureEnabled)
	                    <a href="{{ route('pollen') }}"
		                   id="pollen"
		                   class="relative block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 hover:border-orange-500/30 transition-colors cursor-pointer"
		                   data-widget="pollen"
		                   @click="editMode && $event.preventDefault()"
		                   @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    @else
                        <div id="pollen"
		                     class="relative block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 transition-colors cursor-default"
		                     data-widget="pollen"
		                     @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    @endif
                    <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20" @click.prevent.stop>
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </div>

                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold">🌿 {{ __('Pollen') }}</h3>
                        @if($airPollenFeatureEnabled)
                            <span class="text-xs text-gray-500">{{ __('More') }} →</span>
                        @else
                            <span class="text-xs text-amber-400">{{ __('Page disabled') }}</span>
                        @endif
                    </div>

                    {{-- Overall risk circle + label (always rendered, null-safe fallbacks) --}}
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center flex-shrink-0"
                             :style="'background-color:' + (pollenData?.today?.overall_color || '#4b5563') + '20;border:2px solid ' + (pollenData?.today?.overall_color || '#4b5563') + '40'">
                            <div class="text-center">
                                <div class="text-xl font-bold leading-none"
                                     :style="'color:' + (pollenData?.today?.overall_color || '#9ca3af')"
                                     x-text="pollenData?.today?.overall_risk_index ?? '--'">{{ isset($ssrPollen['today']['overall_risk_index']) ? $ssrPollen['today']['overall_risk_index'] : '--' }}</div>
                                <div class="text-[9px] text-gray-400 uppercase tracking-wide">{{ __('Overall') }}</div>
                            </div>
                        </div>
                        <div>
                            <div class="font-semibold text-sm"
                                 :style="'color:' + (pollenData?.today?.overall_color || '#9ca3af')"
                                 x-text="pollenData ? pollenTranslateRisk(pollenData.today?.overall_risk) : '—'">—</div>
                            <div class="text-xs text-gray-400">{{ __('Overall Pollen Risk') }}</div>
                        </div>
                    </div>

                    {{-- Grass / Tree / Weed grid (always rendered) --}}
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <template x-for="[cat, icon] in [['grass','🌾'],['tree','🌳'],['weed','🌿']]" :key="cat">
                            <div class="rounded-lg p-2 bg-white/5"
                                 :style="pollenData?.today?.[cat]?.color ? 'background-color:' + pollenData.today[cat].color + '15' : ''">
                                <div class="mb-0.5" x-text="icon"></div>
                                <div class="text-gray-400 mb-0.5" x-text="translations.pollenTypes[cat] || cat"></div>
                                <div class="font-semibold"
                                     :style="pollenData?.today?.[cat]?.color ? 'color:' + pollenData.today[cat].color : 'color:#9ca3af'"
                                     x-text="pollenData?.today?.[cat]?.risk ? pollenTranslateRisk(pollenData.today[cat].risk) : '—'">—</div>
                            </div>
                        </template>
                    </div>
                    @if($airPollenFeatureEnabled)
	                    </a>
                    @else
                        </div>
                    @endif
	            </template>

                <!-- Water Widget (Tides + Wave + Sea temp — only shows when at least one has data) -->
                <template x-if="isWidgetEnabled('tide') && (tideData || waterWaves)">
                    @if($skyWaterFeatureEnabled)
                        <a href="{{ route('water') }}"
                       id="tide"
                       class="relative block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 hover:border-cyan-500/30 transition-colors cursor-pointer"
                       data-widget="tide"
                       @click="editMode && $event.preventDefault()"
                       @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    @else
                        <div id="tide"
                             class="relative block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 transition-colors cursor-default"
                             data-widget="tide"
                             @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    @endif
                        <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20" @click.prevent.stop>
                            <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                            </svg>
                        </div>

                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold">🌊 {{ __('Water') }}</h3>
                            @if($skyWaterFeatureEnabled)
                                <span class="text-xs text-gray-500">{{ __('More') }} →</span>
                            @else
                                <span class="text-xs text-amber-400">{{ __('Page disabled') }}</span>
                            @endif
                        </div>

                        {{-- Tides: only when tide data is available --}}
                        <template x-if="tideData">
                            <div>
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="w-14 h-14 rounded-full flex items-center justify-center flex-shrink-0 bg-cyan-900/30 border border-cyan-800/40">
                                        <div class="text-center">
                                            <div class="text-lg font-bold leading-none text-cyan-300"
                                                 x-text="tideData?.current_level_cm != null ? Math.round(tideData.current_level_cm) : '--'">{{ $ssrTideCurrentLevelLabel }}</div>
                                            <div class="text-[9px] text-gray-400">cm</div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-sm text-white"
                                             x-text="tideData?.station ?? '—'">—</div>
                                        <div class="text-xs"
                                             :class="{'text-cyan-400': tideData?.trend === 'rising', 'text-blue-400': tideData?.trend === 'falling', 'text-gray-400': !tideData?.trend || tideData?.trend === 'steady'}"
                                             x-text="tideData?.trend === 'rising' ? '↑ {{ __('Rising') }}' : tideData?.trend === 'falling' ? '↓ {{ __('Falling') }}' : '→ {{ __('Steady') }}'">—</div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs mb-3">
                                    <div class="rounded-lg p-2 bg-cyan-900/20 border border-cyan-800/30">
                                        <div class="text-gray-400 mb-0.5">🔼 {{ __('High Tide') }}</div>
                                        <div class="font-semibold text-cyan-300"
                                             x-text="tideData?.next_high ? new Date(tideData.next_high.timestamp_unix).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) : '--'">{{ $ssrTideNextHighTimeLabel }}</div>
                                        <div class="text-gray-400"
                                             x-text="tideData?.next_high ? Math.round(tideData.next_high.level_cm) + ' cm' : ''"></div>
                                    </div>
                                    <div class="rounded-lg p-2 bg-blue-950/30 border border-blue-900/30">
                                        <div class="text-gray-400 mb-0.5">🔽 {{ __('Low Tide') }}</div>
                                        <div class="font-semibold text-blue-300"
                                             x-text="tideData?.next_low ? new Date(tideData.next_low.timestamp_unix).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) : '--'">{{ $ssrTideNextLowTimeLabel }}</div>
                                        <div class="text-gray-400"
                                             x-text="tideData?.next_low ? Math.round(tideData.next_low.level_cm) + ' cm' : ''"></div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Wave height + Sea temp: only when wave/SST data is available --}}
                        <div x-show="waterWaves && (waterWaves.wave_height_m != null || waterWaves.sst_c != null)" class="text-xs border-t border-white/10 pt-2 flex gap-3 flex-wrap">
                            <template x-if="waterWaves?.wave_height_m != null">
                                <div class="flex items-center gap-1">
                                    <span>〰</span>
                                    <span class="text-gray-400">{{ __('Wave Height') }}:</span>
                                    <span class="font-semibold text-blue-300" x-text="units === 'imperial' ? (waterWaves.wave_height_m * 3.28084).toFixed(1) + ' ft' : waterWaves.wave_height_m.toFixed(2) + ' m'"></span>
                                </div>
                            </template>
                            <template x-if="waterWaves?.sst_c != null">
                                <div class="flex items-center gap-1">
                                    <span>🌡</span>
                                    <span class="text-gray-400">{{ __('Sea Temperature') }}:</span>
                                    <span class="font-semibold text-orange-300" x-text="units === 'imperial' ? ((waterWaves.sst_c * 9/5) + 32).toFixed(1) + '°F' : waterWaves.sst_c.toFixed(1) + '°C'"></span>
                                </div>
                            </template>
                        </div>
                    @if($skyWaterFeatureEnabled)
                        </a>
                    @else
                        </div>
                    @endif
                </template>

	                <!-- Extra Sensors -->
	                <template x-if="isWidgetEnabled('indoor')">
		                <div class="sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
		                     data-widget="indoor"
		                     @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </div>
                    <!-- Offline Badge (Centered) -->
                    <div x-cloak x-show="healthStatus.sensor?.is_stale === true" class="absolute inset-0 flex items-center justify-center z-30 pointer-events-none">
                        <div class="flex flex-col items-center gap-2 text-white bg-red-600/90 backdrop-blur-sm px-6 py-4 rounded-xl shadow-2xl animate-pulse">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <span class="text-lg font-bold">{{ __('Offline') }}</span>
                        </div>
                    </div>                    <!-- Update Timestamp (Top Center) -->
                    <div x-cloak x-show="healthStatus.sensor?.is_stale !== true && getHealthTimestamp('sensor')" class="absolute top-2 left-1/2 -translate-x-1/2 flex items-center gap-0.5 text-[10px] text-gray-400 bg-black/20 px-1.5 py-0.5 rounded z-10">
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span x-text="getHealthTimestamp('sensor')"></span>
                    </div>

                    <h3 class="font-semibold mb-4">{{ __('Extra Sensors') }}</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400 flex items-center gap-1">
		                                <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/thermometer.svg') }}"
		                                     :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/thermometer.svg'"
		                                     class="w-4 h-4" alt="">
                                {{ __('Indoor') }}
                            </span>
                            <span class="font-bold data-value" x-text="formatTemp(current?.temperature_indoor)"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400 flex items-center gap-1">
		                                <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/humidity.svg') }}"
		                                     :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/humidity.svg'"
		                                     class="w-4 h-4" alt="">
                                {{ __('Indoor humidity') }}
                            </span>
                            <span class="font-bold data-value" x-text="current?.humidity_indoor ? current.humidity_indoor + '%' : '--'"></span>
                        </div>
                        <!-- Extra temperature sensors -->
                        <template x-if="extraSensors?.temps">
                            <template x-for="(temp, key) in extraSensors.temps" :key="key">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400 flex items-center gap-1">
		                                        <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/thermometer.svg') }}"
		                                             :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/thermometer.svg'"
		                                             class="w-4 h-4" alt="">
                                        <span x-text="getExtraTempLabel(key)"></span>
                                    </span>
                                    <span class="font-bold data-value" x-text="formatTemp(temp)"></span>
                                </div>
                            </template>
                        </template>
                        <!-- Soil sensors -->
                        <template x-if="extraSensors?.soil">
                            <template x-for="(data, idx) in extraSensors.soil" :key="idx">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400" x-text="'🌱 ' + getSoilLabel(idx)"></span>
                                    <span class="font-bold data-value" x-text="data.moisture ? data.moisture + '%' : '--'"></span>
                                </div>
                            </template>
                        </template>
                    </div>
	                </div>
	                </template>

	                <!-- Lightning -->
	                <template x-if="isWidgetEnabled('lightning')">
		                <a href="{{ route('lightning') }}"
		                   class="block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 hover:border-orange-500/30 transition-colors cursor-pointer"
		                   data-widget="lightning"
		                   @click="editMode && $event.preventDefault()"
		                   @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20" @click.prevent.stop>
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold">⚡ {{ __('Lightning') }}</h3>
                        <span class="text-xs" :class="lightning ? 'text-yellow-400' : 'text-gray-400'"
                              x-text="lightning?.time_ago ? lightning.time_ago : translations.noActivity"></span>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center"
                             :class="lightning?.distance && lightning.distance < 30 ? 'bg-yellow-500/20' : 'bg-gray-500/20'">
                            <div class="text-center">
                                <div class="text-2xl font-bold" 
                                     :class="lightning?.distance && lightning.distance < 30 ? 'text-yellow-400' : 'text-gray-400'"
                                     x-text="formatDistanceValue(lightning?.distance, 0)"></div>
                                <div class="text-[10px] text-gray-400" x-text="distanceUnit()"></div>
                            </div>
                        </div>
                        <div class="flex-1 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-400">{{ __('Today') }}</span>
                                <span class="font-bold" x-text="(lightning?.count_daily ?? 0) + ' ' + translations.strikes"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">{{ __('Last distance') }}</span>
                                <span class="font-bold text-yellow-400" x-text="formatDistance(lightning?.distance, 0)"></span>
                            </div>
                        </div>
                    </div>
                    <div class="text-xs text-center mt-3 text-blue-400 hover:text-blue-300">{{ __('Map') }} →</div>
	                </a>
	                </template>

	                <!-- Battery Status Widget -->
	                <template x-if="isWidgetEnabled('battery') && Object.keys(batteryStatus).length > 0">
		                <div class="sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
		                     data-widget="battery"
		                     @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold">🔋 {{ __('Battery Status') }}</h3>
                        <span class="text-xs text-gray-400">{{ __('Sensor status') }}</span>
                    </div>
                    <div class="space-y-2 text-sm">
                        <template x-for="(value, key) in batteryStatus" :key="key">
                            <div class="flex justify-between items-center py-1 border-b border-white/5 last:border-0">
                                <span class="text-gray-400 flex items-center gap-2">
                                    <span x-text="getBatteryIcon(value)"></span>
                                    <span x-text="getBatteryLabel(key)"></span>
                                </span>
                                <span class="font-medium" 
                                      :class="getBatteryStatus(key, value).class"
                                      x-text="getBatteryStatus(key, value).text"></span>
                            </div>
                        </template>
                    </div>
	                </div>
	                </template>

	                <!-- Aurora / Kp Index Widget -->
	                <template x-if="isWidgetEnabled('aurora') && aurora">
		                <div class="sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
		                     data-widget="aurora"
		                     @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold">✨ {{ __('Aurora') }} / {{ __('Kp Index') }}</h3>
                        <span class="text-xs text-gray-400">{{ __('Geomagnetic activity') }}</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center" :class="getKpBgColor(aurora?.kp)">
                            <div class="text-center">
                                <div class="text-3xl font-bold" :class="getKpColor(aurora?.kp)"
                                     x-text="aurora?.kp?.toFixed(1) ?? '--'"></div>
                                <div class="text-[10px] text-gray-400">Kp</div>
                            </div>
                        </div>
                        <div class="flex-1 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-400">{{ __('Status') }}</span>
                                <span class="font-bold" :class="getKpColor(aurora?.kp)"
                                      x-text="getKpLevel(aurora?.kp)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">{{ __('Aurora chance') }}</span>
                                <span class="font-bold" x-text="getKpChance(aurora?.kp)"></span>
                            </div>
                        </div>
                    </div>
	                </div>
	                </template>

	                <!-- Extra Temperature Sensors Widget -->
	                <template x-if="isWidgetEnabled('extra_temps') && extraSensors?.temps && Object.keys(extraSensors.temps).length > 0">
		                <div class="sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
		                     data-widget="extra_temps"
		                     @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold flex items-center gap-2">
		                            <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/thermometer.svg') }}"
		                                 :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/thermometer.svg'"
		                                 class="w-5 h-5" alt="">
                            {{ __('Extra Sensors') }}
                        </h3>
                        <span class="text-xs text-gray-400">{{ __('Temperature') }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <template x-for="(temp, key) in extraSensors?.temps || {}" :key="key">
                            <div class="bg-white/5 rounded-lg p-3 text-center">
                                <div class="text-xs text-gray-400" x-text="getExtraTempLabel(key)"></div>
                                <div class="text-xl font-bold" x-text="formatTemp(temp)"></div>
                            </div>
                        </template>
                    </div>
	                </div>
	                </template>

	                <!-- Soil Sensors Widget -->
	                <template x-if="isWidgetEnabled('soil') && extraSensors?.soil && Object.keys(extraSensors.soil).length > 0">
		                <div class="sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
		                     data-widget="soil"
		                     @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold">🌱 {{ __('Soil sensors') }}</h3>
                        <span class="text-xs text-gray-400">{{ __('Moisture & Temperature') }}</span>
                    </div>
                    <div class="space-y-3 text-sm">
                        <template x-for="(data, key) in extraSensors?.soil || {}" :key="key">
                            <div class="flex justify-between items-center py-2 border-b border-white/5 last:border-0">
                                <span class="text-gray-400" x-text="getSoilLabel(key)"></span>
                                <div class="flex gap-4">
                                    <span class="font-bold text-blue-400" x-text="data?.moisture !== undefined ? data.moisture + '%' : '--'"></span>
                                    <span class="font-bold" x-text="formatTemp(data?.temperature)"></span>
                                </div>
                            </div>
                        </template>
                    </div>
	                </div>
	                </template>

	                <!-- PM2.5 Widget -->
	                <template x-if="isWidgetEnabled('pm25') && Object.keys(pm25Channels()).length > 0">
		                <div class="sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
		                     data-widget="pm25"
		                     @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold">💨 {{ __('PM2.5 Air Quality') }}</h3>
                        <span class="text-xs text-gray-400">{{ __('Fine dust') }}</span>
                    </div>
                    <div class="space-y-3 text-sm">
                        <template x-for="(value, key) in pm25Channels()" :key="key">
                            <div class="flex justify-between items-center py-2 border-b border-white/5 last:border-0">
                                <span class="text-gray-400" x-text="getPm25Label(key)"></span>
                                <span class="font-bold" x-text="value + ' µg/m³'"></span>
                            </div>
                        </template>
                    </div>
	                </div>
	                </template>

	                <!-- CO2 Widget -->
	                <template x-if="isWidgetEnabled('co2') && extraSensors?.co2">
		                <div class="sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
		                     data-widget="co2"
		                     @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold flex items-center gap-2">
		                            <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/haze.svg') }}"
		                                 :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/haze.svg'"
		                                 class="w-5 h-5" alt="">
                            {{ __('CO2 Monitor') }}
                        </h3>
                        <span class="text-xs text-gray-400">{{ __('Carbon dioxide') }}</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center"
                             :class="extraSensors?.co2 > 1000 ? 'bg-yellow-500/20' : 'bg-green-500/20'">
                            <div class="text-center">
                                <div class="text-2xl font-bold" 
                                     :class="extraSensors?.co2 > 1000 ? 'text-yellow-400' : 'text-green-400'"
                                     x-text="extraSensors?.co2 ?? '--'"></div>
                                <div class="text-[10px] text-gray-400">ppm</div>
                            </div>
                        </div>
                        <div class="flex-1 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-400">{{ __('Quality') }}</span>
                                <span class="font-bold" 
                                      :class="extraSensors?.co2 > 1000 ? 'text-yellow-400' : 'text-green-400'"
                                      x-text="extraSensors?.co2 > 1000 ? translations.moderate : translations.good"></span>
                            </div>
                        </div>
                    </div>
	                </div>
	                </template>
	            </div>
	        </div>

		        <!-- Webcam & Radar Row - Sortable -->
        <div id="sortable-media-row" class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
            @if($ssrDashboard && count($ssrFallbackGroups['sortable-media-row'] ?? []) > 0)
                @foreach($ssrFallbackGroups['sortable-media-row'] as $ssrCard)
                    <article x-show="ssrFallbackVisible"
                             class="ssr-fallback-block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
                             data-widget="{{ $ssrCard['id'] ?? 'widget' }}">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="font-semibold">{{ $ssrCard['title'] ?? __('Weather') }}</h2>
                            <span class="text-[10px] text-gray-500 uppercase tracking-wide">SSR</span>
                        </div>
                        <div class="space-y-1.5 text-sm text-gray-300">
                            @foreach(($ssrCard['lines'] ?? []) as $ssrLine)
                                <p class="leading-snug">{{ $ssrLine }}</p>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            @endif
            <!-- Webcam -->
            <template x-if="isWidgetEnabled('webcam')">
                <div class="sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
                     data-widget="webcam"
                     @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)"
                     x-data="{
                     displayMode: '{{ \App\Models\Setting::getValue('webcam.display_mode', 'image') }}',
                     streamUrl: '{{ addslashes(\App\Models\Setting::getValue('webcam.stream_url', '')) }}',
                     streamType: '{{ \App\Models\Setting::getValue('webcam.stream_type', 'none') }}',
                     imageUrl: '{{ \App\Models\Setting::getValue('webcam.url', '') }}',
                     showStreamModal: false,
                     imageUpdatedAt: null,
                     imageLoadFailed: false,
                     // Determine initial streaming state immediately (do not rely on init()).
                     // Goal:
                     // - Phones/tablets OR small viewports: start paused (data saver, tap-to-play)
                     // - Desktop (wide viewport): autoplay in stream-only mode
                     isMobileByUA: /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent),
                     isMobile: /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
                              (navigator.maxTouchPoints > 0 && window.innerWidth < 1024),
                     isOnMobileData: false,
                     isStreaming: (() => {
                         const displayMode = '{{ \App\Models\Setting::getValue('webcam.display_mode', 'image') }}';
                         const streamUrl = '{{ addslashes(\App\Models\Setting::getValue('webcam.stream_url', '')) }}';
                         const streamType = '{{ \App\Models\Setting::getValue('webcam.stream_type', 'none') }}';
                         const isMobileByUA = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                         const isMobileViewport = window.innerWidth < 768;
                         const shouldStartPaused = isMobileByUA || isMobileViewport;
                         return !shouldStartPaused && displayMode === 'stream' && streamType !== 'none' && !!streamUrl;
                     })(),
                     init() {
                         // Prevent double initialization
                         if (this._initialized) return;
                         this._initialized = true;

                         // Check mobile/connection; never throw so init cannot abort
                         try {
                             this.checkMobileData();
                         } catch (e) {
                             // Even if detection fails, keep existing initial state and continue.
                         }

                         // Ensure consistent paused/autoplay behavior (in case viewport changes between parse and init).
                         const isMobileViewport = window.innerWidth < 768;
                         const shouldStartPaused = this.isMobileByUA || isMobileViewport;
                         if (this.displayMode === 'stream') {
                             this.isStreaming = !shouldStartPaused && this.streamType !== 'none' && !!this.streamUrl;
                         } else {
                             // For image/both modes, never inline-autoplay the stream.
                             this.isStreaming = false;
                         }

                         // If we start paused in stream mode, show the data saver indicator
                         if (shouldStartPaused && this.displayMode === 'stream') {
                             this.isOnMobileData = true;
                         }

                         // Apply streaming state after Alpine has had a chance to process bindings.
                         // (Avoid relying on Alpine $nextTick being available in all contexts.)
                         setTimeout(() => this.applyStreamingState(), 0);
                     },
                     markImageLoaded() {
                         this.imageUpdatedAt = new Date();
                         this.imageLoadFailed = false;
                     },
                     formatImageUpdatedAt() {
                         if (!this.imageUpdatedAt) return '';
                         return this.imageUpdatedAt.toLocaleTimeString(document.documentElement.lang || undefined, {
                             hour: '2-digit',
                             minute: '2-digit'
                         });
                     },
                     checkMobileData() {
                         // Phone/tablet UA only (used for autoplay decision so desktop/touchscreen still autoplays)
                         this.isMobileByUA = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                         // Broader mobile: UA or touch + narrow (for UI / connection)
                         this.isMobile = this.isMobileByUA || (navigator.maxTouchPoints > 0 && window.innerWidth < 1024);

                         // Use Network Information API where available (Chrome/Edge/Samsung - not Safari)
                         const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
                         if (connection) {
                             this.isOnMobileData = connection.type === 'cellular' ||
                                                   connection.effectiveType === 'slow-2g' ||
                                                   connection.effectiveType === '2g' ||
                                                   connection.effectiveType === '3g';

                             if (typeof connection.addEventListener === 'function') {
                                 connection.addEventListener('change', () => {
                                     const wasMobileData = this.isOnMobileData;
                                     this.isOnMobileData = connection.type === 'cellular';
                                     if (!wasMobileData && this.isOnMobileData && this.isStreaming) {
                                         this.isStreaming = false;
                                         this.applyStreamingState();
                                     }
                                 });
                             }
                         }

                         // Note: streaming on load is decided in init() to avoid order/timing issues.
                         // No logging here (keep behavior silent).
                     },
                    getEmbedUrl() {
                        if (!this.streamUrl || this.streamType === 'none') return '';
                        if (this.streamType === 'youtube') {
                            const videoId = this.extractYouTubeId(this.streamUrl);
                            // YouTube requires mute=1 for autoplay to work in most browsers
                            return videoId ? `https://www.youtube.com/embed/${videoId}?autoplay=1&mute=1&loop=1&playlist=${videoId}` : '';
                        }
                         if (this.streamType === 'restreamer') {
                             // Restreamer can be embedded as iframe or HLS stream
                             // If URL contains /b/ it's a browser embed, otherwise use HLS
                             if (this.streamUrl.includes('/b/')) {
                                 return this.streamUrl.replace(/\/$/, '');
                             }
                             // For HLS streams, ensure .m3u8 extension
                             const cleanUrl = this.streamUrl.replace(/\/$/, '');
                             return cleanUrl.endsWith('.m3u8') ? cleanUrl : cleanUrl + '/index.m3u8';
                         }
                         return '';
                     },
                     getRestreamerIframeUrl() {
                         if (this.streamType !== 'restreamer' || !this.streamUrl) return '';
                         // Restreamer browser embed URL format: https://restreamer.example.com/b/stream-name
                         if (this.streamUrl.includes('/b/')) {
                             return this.streamUrl.replace(/\/$/, '');
                         }
                         // If it's a direct stream URL, try to construct browser URL
                         try {
                             const url = new URL(this.streamUrl);
                             return `${url.protocol}//${url.host}/b/${url.pathname.split('/').pop()}`;
                         } catch(e) {
                             return '';
                         }
                     },
                    extractYouTubeId(url) {
                        if (!url) return null;
                        // Handle various YouTube URL formats
                        let videoId = null;
                        
                        // Live stream URL: https://www.youtube.com/live/VIDEO_ID
                        const liveMatch = url.match(/\/live\/([^#&?\/]+)/);
                        if (liveMatch && liveMatch[1]) {
                            videoId = liveMatch[1].substring(0, 11);
                        }
                        
                        // Standard watch URL: https://www.youtube.com/watch?v=VIDEO_ID
                        if (!videoId) {
                            const watchMatch = url.match(/[?&]v=([^#&?]+)/);
                            if (watchMatch && watchMatch[1]) {
                                videoId = watchMatch[1].substring(0, 11);
                            }
                        }
                        
                        // Short URL: https://youtu.be/VIDEO_ID
                        if (!videoId) {
                            const shortMatch = url.match(/youtu\.be\/([^#&?\/]+)/);
                            if (shortMatch && shortMatch[1]) {
                                videoId = shortMatch[1].substring(0, 11);
                            }
                        }
                        
                        // Embed URL: https://www.youtube.com/embed/VIDEO_ID
                        if (!videoId) {
                            const embedMatch = url.match(/\/embed\/([^#&?\/]+)/);
                            if (embedMatch && embedMatch[1]) {
                                videoId = embedMatch[1].substring(0, 11);
                            }
                        }
                        
                        // Direct video ID (if URL is just the ID)
                        if (!videoId && url.length === 11 && /^[a-zA-Z0-9_-]+$/.test(url)) {
                            videoId = url;
                        }
                        
                        return videoId;
                    },
                     openStreamModal() {
                         const embedUrl = this.getEmbedUrl();
                         if (embedUrl || (this.streamType === 'restreamer' && this.streamUrl.includes('/b/'))) {
                             // Start streaming when modal is opened (user explicitly requested)
                             this.isStreaming = true;
                             this.showStreamModal = true;
                         }
                     },
                     closeStreamModal() {
                         this.showStreamModal = false;
                     },
                     toggleStream() {
                         this.isStreaming = !this.isStreaming;
                         this.applyStreamingState();
                     },
                     applyStreamingState() {
                         const container = this.$el.querySelector('.aspect-video');
                         if (!container) return;

                         // Handle video elements (HLS streams)
                         const videos = container.querySelectorAll('video');
                         videos.forEach(video => {
                             if (this.isStreaming) {
                                 // Reload the video source and play
                                 const source = video.querySelector('source');
                                 if (source && !source.src) {
                                     source.src = this.getEmbedUrl();
                                     video.load();
                                 }
                                 video.play().catch(e => console.log('Video play failed:', e));
                             } else {
                                 video.pause();
                                 // Clear source to stop buffering
                                 const source = video.querySelector('source');
                                 if (source) {
                                     source.src = '';
                                     video.load();
                                 }
                             }
                         });

                         // Handle iframes (YouTube/Restreamer browser embed)
                         const iframes = container.querySelectorAll('iframe');
                         iframes.forEach(iframe => {
                             if (this.isStreaming) {
                                 // Restore the iframe src to resume playback
                                 if (iframe.dataset.originalSrc) {
                                     iframe.src = iframe.dataset.originalSrc;
                                 } else if (!iframe.src || iframe.src === 'about:blank') {
                                     // Set initial src based on stream type
                                     if (this.streamType === 'youtube') {
                                         iframe.src = this.getEmbedUrl();
                                     } else if (this.streamType === 'restreamer') {
                                         iframe.src = this.getRestreamerIframeUrl();
                                     }
                                 }
                             } else {
                                 // Store src and clear it to stop playback completely
                                 if (iframe.src && iframe.src !== 'about:blank') {
                                     iframe.dataset.originalSrc = iframe.src;
                                 }
                                 iframe.src = 'about:blank';
                             }
                         });
                     },
                     getActiveStreamUrl() {
                         // Returns the stream URL only when streaming is active
                         return this.isStreaming ? this.getEmbedUrl() : 'about:blank';
                     },
                     getActiveRestreamerUrl() {
                         // Returns the restreamer URL only when streaming is active
                         return this.isStreaming ? this.getRestreamerIframeUrl() : 'about:blank';
                     }
                 }"
                 x-init="init()"
                 @keydown.escape.window="closeStreamModal()">
                <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                    <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                    </svg>
                </div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold">📷 {{ __('Webcam') }}</h3>
                    <div class="flex items-center gap-2">
                        <!-- Stop/Play stream button - only show when streaming is active -->
                        <button x-show="displayMode === 'stream' && streamType !== 'none' && streamUrl"
                                @click="toggleStream()"
                                class="text-xs px-2 py-1 rounded transition-colors flex items-center gap-1"
                                :class="isStreaming ? 'bg-red-500/20 text-red-400 hover:bg-red-500/30' : 'bg-green-500/20 text-green-400 hover:bg-green-500/30'"
                                :title="isStreaming ? '{{ __('Stop stream') }}' : '{{ __('Start stream') }}'">
                            <svg x-show="isStreaming" class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                                <rect x="6" y="4" width="4" height="16" rx="1"/>
                                <rect x="14" y="4" width="4" height="16" rx="1"/>
                            </svg>
                            <svg x-show="!isStreaming" class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                            <span x-text="isStreaming ? '{{ __('Stop') }}' : '{{ __('Play') }}'"></span>
                        </button>
                        <span class="text-xs text-gray-400" x-show="isStreaming">{{ __('Live') }}</span>
                        <span class="text-xs text-yellow-400" x-show="displayMode === 'stream' && !isStreaming">📱 {{ __('Data saver') }}</span>
                        <span x-show="displayMode === 'image' || displayMode === 'both'"
                              class="inline-flex max-w-36 items-center gap-1.5 whitespace-nowrap text-xs text-gray-400"
                              style="display: none;">
                            <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full"
                                  :class="imageLoadFailed ? 'bg-red-400' : (imageUpdatedAt ? 'bg-green-400' : 'bg-amber-400')"></span>
                            <span x-show="!imageUpdatedAt && !imageLoadFailed">{{ __('Updating...') }}</span>
                            <span x-show="imageUpdatedAt && !imageLoadFailed" class="truncate">
                                {{ __('Updated') }} <span x-text="formatImageUpdatedAt()"></span>
                            </span>
                            <span x-show="imageLoadFailed"
                                  class="truncate"
                                  title="{{ __('Webcam not available') }}">{{ __('Webcam not available') }}</span>
                        </span>
                    </div>
                </div>
                <div class="aspect-video bg-black/30 rounded-xl overflow-hidden relative">
                    <!-- Stream display (when mode is 'stream') - Show first so it has priority -->
                    <div x-show="displayMode === 'stream' && streamType === 'youtube' && streamUrl && isStreaming"
                         class="absolute inset-0"
                         style="display: none;">
                        <iframe
                            :src="isStreaming ? getEmbedUrl() : 'about:blank'"
                            class="w-full h-full"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen>
                        </iframe>
                    </div>

                    <!-- Paused state overlay for YouTube - tap to play -->
                    <div x-show="displayMode === 'stream' && streamType === 'youtube' && streamUrl && !isStreaming"
                         @click="toggleStream()"
                         class="absolute inset-0 flex items-center justify-center bg-black/80 cursor-pointer hover:bg-black/70 transition-colors"
                         style="display: none;">
                        <div class="text-center pointer-events-none">
                            <div class="w-16 h-16 mx-auto mb-3 rounded-full bg-white/10 flex items-center justify-center">
                                <svg class="w-8 h-8 text-white ml-1" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                            </div>
                            <p class="text-white text-sm font-medium">{{ __('Video stream paused') }}</p>
                            <p class="text-yellow-400 text-xs mt-1">{{ __('Tap to play - saves mobile data') }}</p>
                        </div>
                    </div>

                    <div x-show="displayMode === 'stream' && streamType === 'restreamer' && streamUrl"
                         class="absolute inset-0"
                         style="display: none;">
                        <template x-if="streamUrl.includes('/b/')">
                            <div class="w-full h-full">
                                <iframe
                                    x-show="isStreaming"
                                    :src="isStreaming ? getRestreamerIframeUrl() : 'about:blank'"
                                    class="w-full h-full"
                                    frameborder="0"
                                    allowfullscreen>
                                </iframe>
                                <!-- Paused state - tap to play -->
                                <div x-show="!isStreaming"
                                     @click="toggleStream()"
                                     class="w-full h-full flex items-center justify-center bg-black/80 cursor-pointer hover:bg-black/70 transition-colors">
                                    <div class="text-center pointer-events-none">
                                        <div class="w-16 h-16 mx-auto mb-3 rounded-full bg-white/10 flex items-center justify-center">
                                            <svg class="w-8 h-8 text-white ml-1" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M8 5v14l11-7z"/>
                                            </svg>
                                        </div>
                                        <p class="text-white text-sm font-medium">{{ __('Video stream paused') }}</p>
                                        <p class="text-yellow-400 text-xs mt-1">{{ __('Tap to play - saves mobile data') }}</p>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template x-if="!streamUrl.includes('/b/')">
                            <div class="w-full h-full">
                                <video
                                    x-show="isStreaming"
                                    x-ref="hlsVideo"
                                    class="w-full h-full object-cover"
                                    :autoplay="isStreaming"
                                    muted
                                    playsinline
                                    controls
                                    x-init="if (isStreaming) { $el.querySelector('source').src = getEmbedUrl(); $el.load(); }"
                                    x-effect="if (isStreaming && $refs.hlsVideo) {
                                        const source = $refs.hlsVideo.querySelector('source');
                                        if (source && !source.src) { source.src = getEmbedUrl(); $refs.hlsVideo.load(); }
                                        $refs.hlsVideo.play().catch(e => {});
                                    } else if ($refs.hlsVideo) {
                                        $refs.hlsVideo.pause();
                                    }">
                                    <source type="application/x-mpegURL">
                                    {{ __('Your browser does not support the video tag.') }}
                                </video>
                                <!-- Paused state - tap to play -->
                                <div x-show="!isStreaming"
                                     @click="toggleStream()"
                                     class="w-full h-full flex items-center justify-center bg-black/80 cursor-pointer hover:bg-black/70 transition-colors">
                                    <div class="text-center pointer-events-none">
                                        <div class="w-16 h-16 mx-auto mb-3 rounded-full bg-white/10 flex items-center justify-center">
                                            <svg class="w-8 h-8 text-white ml-1" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M8 5v14l11-7z"/>
                                            </svg>
                                        </div>
                                        <p class="text-white text-sm font-medium">{{ __('Video stream paused') }}</p>
                                        <p class="text-yellow-400 text-xs mt-1">{{ __('Tap to play - saves mobile data') }}</p>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    
                    <!-- Nothing configured yet. Better than a broken image, and
                         far better than the default this used to carry, which
                         was the author's own webcam. -->
                    <div x-show="(displayMode === 'image' || displayMode === 'both') && !imageUrl"
                         class="absolute inset-0 flex items-center justify-center bg-black/40 text-sm text-gray-300"
                         style="display: none;">
                        📷 {{ __('Webcam not configured') }}
                    </div>

                    <!-- Image display (when mode is 'image' or 'both') -->
                    <div x-show="(displayMode === 'image' || displayMode === 'both') && imageUrl"
                         class="absolute inset-0"
                         style="display: none;">
                        <img id="webcam-image" 
                             src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=="
                             x-bind:data-lazy-src="imageUrl"
                             x-bind:data-display-mode="displayMode"
                             alt="{{ __('Webcam') }}" 
                             class="w-full h-full object-cover"
                             loading="lazy"
                             decoding="async"
                             :class="(displayMode === 'both' && streamType !== 'none' && streamUrl && (getEmbedUrl() || (streamType === 'restreamer' && streamUrl.includes('/b/')))) ? 'cursor-pointer hover:opacity-90 transition-opacity' : ''"
                             @click.stop="(displayMode === 'both' && streamType !== 'none' && streamUrl && (getEmbedUrl() || (streamType === 'restreamer' && streamUrl.includes('/b/')))) ? openStreamModal() : null"
                             x-on:load="markImageLoaded()"
                             x-on:error="imageLoadFailed = true">
                        <div x-show="imageLoadFailed"
                             class="absolute inset-0 flex items-center justify-center bg-black/50 text-sm text-gray-300"
                             style="display: none;">
                            📷 {{ __('Webcam not available') }}
                        </div>
                    </div>
                    
                    <!-- Live indicator -->
                    <div x-show="displayMode === 'stream' && isStreaming"
                         class="absolute bottom-2 left-2 text-xs bg-black/50 px-2 py-1 rounded flex items-center gap-1.5 z-10"
                         style="display: none;">
                        <span class="live-indicator inline-block w-2 h-2 bg-green-500 rounded-full shadow-lg shadow-green-500/50"></span>
                        {{ __('Live') }}
                    </div>
                    
                    <!-- Click to view stream overlay (when mode is 'both') -->
                    <template x-if="displayMode === 'both' && streamType !== 'none' && streamUrl">
                        <div class="absolute inset-0 flex items-center justify-center bg-black/20 hover:bg-black/30 transition-colors cursor-pointer z-20"
                             @click.stop.prevent="openStreamModal()"
                             @mousedown.stop>
                            <div class="bg-black/60 px-4 py-2 rounded-lg flex items-center gap-2 pointer-events-none">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-white text-sm font-medium">{{ __('Click to view live stream') }}</span>
                            </div>
                        </div>
                    </template>
                    
                    <!-- Debug info (remove in production) -->
                    <template x-if="displayMode === 'stream' && !streamUrl">
                        <div class="absolute inset-0 flex items-center justify-center text-yellow-400 text-sm">
                            Stream URL not configured
                        </div>
                    </template>
                </div>
                
                <!-- Stream Modal -->
                <div x-show="showStreamModal"
                     x-cloak
                     style="display: none;"
                     class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 backdrop-blur-sm"
                     google-side-rail-overlap="true"
                     @click.self="closeStreamModal()"
                     @keydown.escape.window="closeStreamModal()"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     x-init="$watch('showStreamModal', value => { if (value) { document.body.style.overflow = 'hidden'; } else { document.body.style.overflow = ''; } })">
                    <div class="relative w-full h-full max-w-7xl max-h-[90vh] m-4"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95">
                        <!-- Close button -->
                        <button @click="closeStreamModal()"
                                class="absolute top-4 right-4 z-10 bg-black/60 hover:bg-black/80 text-white rounded-full p-2 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                        
                        <!-- Stream container -->
                        <div class="w-full h-full bg-black rounded-xl overflow-hidden">
                            <template x-if="streamType === 'youtube'">
                                <iframe 
                                    :src="getEmbedUrl()"
                                    class="w-full h-full"
                                    frameborder="0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                                    allowfullscreen>
                                </iframe>
                            </template>
                            
                            <template x-if="streamType === 'restreamer'">
                                <template x-if="streamUrl.includes('/b/')">
                                    <iframe 
                                        :src="getRestreamerIframeUrl()"
                                        class="w-full h-full"
                                        frameborder="0"
                                        allowfullscreen>
                                    </iframe>
                                </template>
                                <template x-if="!streamUrl.includes('/b/')">
                                    <video 
                                        class="w-full h-full object-contain"
                                        autoplay
                                        muted
                                        playsinline
                                        controls>
                                        <source :src="getEmbedUrl()" type="application/x-mpegURL">
                                        {{ __('Your browser does not support the video tag.') }}
                                    </video>
                                </template>
                            </template>
                        </div>
                    </div>
                </div>
                </div>
            </template>

            <!-- Radar -->
            <template x-if="isWidgetEnabled('radar')">
                <div class="sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
                     data-widget="radar"
                     @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                    <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                    </svg>
                </div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold">🛰️ {{ __('Precipitation radar') }}</h3>
                    @php
                        $radarProvider = \App\Models\Setting::getValue('radar.provider', 'rainviewer');
                        $providerLabels = [
                            'knmi' => 'KNMI',
                            'buienradar' => 'Buienradar',
                            'rainviewer' => 'RainViewer'
                        ];
                        $providerLabel = $providerLabels[$radarProvider] ?? 'KNMI';
                    @endphp
                    <span class="text-xs text-gray-400">{{ $providerLabel }}</span>
                </div>
                <div class="aspect-video bg-black/30 rounded-xl overflow-hidden relative">
                    @php
                        // Check if widget has separate provider setting
                        $widgetProvider = \App\Models\Setting::getValue('radar.widget_provider', '');
                        $radarProvider = $widgetProvider ?: \App\Models\Setting::getValue('radar.provider', 'rainviewer');
                        $radarUrl = \App\Models\Setting::getValue('radar.url', '');
                        $stationLat = \App\Models\Setting::latitude();
                        $stationLon = \App\Models\Setting::longitude();
                        $rainviewerMode = $radarProvider === 'rainviewer' 
                            ? ($widgetProvider ? \App\Models\Setting::getValue('radar.widget_rainviewer_mode', 'api') : \App\Models\Setting::getValue('radar.rainviewer_mode', 'api'))
                            : 'api';
                    @endphp
                    
		    @if($radarProvider === 'rainviewer' && $rainviewerMode === 'api')
                        {{-- RainViewer API with Leaflet map --}}
                        <div id="radar-map-widget" class="relative z-0 w-full h-full"></div>
                        <div x-cloak
                             x-show="_radarMapInitialized"
                             class="absolute top-2 left-2 z-[1200] flex flex-col gap-1">
                            <button type="button"
                                    @click="radarZoomIn()"
                                    :disabled="!radarCanZoomIn()"
                                    class="h-7 w-7 rounded bg-black/55 text-white text-sm leading-none flex items-center justify-center border border-white/20 hover:bg-black/70 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                                    aria-label="{{ __('Zoom in') }}">
                                +
                            </button>
                            <button type="button"
                                    @click="radarZoomOut()"
                                    :disabled="!radarCanZoomOut()"
                                    class="h-7 w-7 rounded bg-black/55 text-white text-sm leading-none flex items-center justify-center border border-white/20 hover:bg-black/70 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                                    aria-label="{{ __('Zoom out') }}">
                                -
                            </button>
                        </div>
                        <div x-cloak
                             x-show="radarFrameTimeLabel"
                             class="pointer-events-none absolute top-2 right-2 text-xs bg-black/60 px-2 py-1 rounded z-[1200]">
                            🕒 <span x-text="radarFrameTimeLabel"></span>
                        </div>
                        <div class="pointer-events-none absolute bottom-2 left-2 text-xs bg-black/50 px-2 py-1 rounded z-[1200]">
                            📍 <span x-text="station.location || '{{ __('Station location') }}'"></span>
                        </div>
                    @elseif($radarProvider === 'rainviewer' && $rainviewerMode === 'iframe')
                        @php
                            // Build RainViewer iframe URL with station coordinates
                            $rainviewerZoom = \App\Models\Setting::getValue('radar.rainviewer_zoom', 7);
                            $rainviewerUrl = 'https://www.rainviewer.com/map.html?loc=' . $stationLat . ',' . $stationLon . ',' . $rainviewerZoom . '&oC=true&oCS=1&c=3&o=83&lm=1&layer=radar&sm=1&sn=1';
                        @endphp
                        <iframe 
                            src="{{ $rainviewerUrl }}"
                            class="w-full h-full"
                            frameborder="0"
                            style="border:0;"
                            loading="lazy"
                            allowfullscreen>
                        </iframe>
                    @else
	                        @php
	                            $isFlatTheme = (($siteTheme ?? 'fx') === 'flat');
	                            $buienradarStaticUrl = 'https://api.buienradar.nl/image/1.0/radarmapnl?w=500&h=512';
	                            $knmiStaticUrl = 'https://cdn.knmi.nl/knmi/map/page/weer/actueel-weer/neerslagradar/WWWRADARPRE_loop.gif';

	                            $resolvedRadarUrl = is_string($radarUrl) ? trim($radarUrl) : '';
	                            $useRainviewerFallback = false;

	                            if ($resolvedRadarUrl === '') {
	                                // Provider-specific default URLs
	                                $resolvedRadarUrl = match($radarProvider) {
	                                    'buienradar' => $buienradarStaticUrl,
	                                    'knmi' => $knmiStaticUrl,
	                                    default => '', // Will trigger RainViewer fallback
	                                };

	                                // Use RainViewer iframe as worldwide fallback if no URL
	                                if ($resolvedRadarUrl === '') {
	                                    $useRainviewerFallback = true;
	                                }
	                            }

	                            // Flat mode is a low-power mode: avoid animated loop GIF radars.
	                            if (!$useRainviewerFallback && $isFlatTheme && is_string($resolvedRadarUrl) && str_contains($resolvedRadarUrl, '_loop.gif')) {
	                                $resolvedRadarUrl = $buienradarStaticUrl;
	                            }
	                        @endphp
	                        @if($useRainviewerFallback)
	                            {{-- RainViewer iframe as worldwide fallback --}}
	                            @php
	                                $rainviewerZoom = \App\Models\Setting::getValue('radar.rainviewer_zoom', 7);
	                                $rainviewerFallbackUrl = 'https://www.rainviewer.com/map.html?loc=' . $stationLat . ',' . $stationLon . ',' . $rainviewerZoom . '&oC=true&oCS=1&c=3&o=83&lm=1&layer=radar&sm=1&sn=1';
	                            @endphp
	                            <iframe
	                                src="{{ $rainviewerFallbackUrl }}"
	                                class="w-full h-full"
	                                frameborder="0"
	                                style="border:0;"
	                                loading="lazy"
	                                allowfullscreen>
	                            </iframe>
	                        @elseif($resolvedRadarUrl)
	                            <img id="radar-image"
	                                 src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=="
	                                 data-lazy-src="{{ $resolvedRadarUrl }}"
	                                 alt="{{ __('Precipitation radar') }}"
	                                 class="w-full h-full object-contain"
	                                 loading="lazy"
	                                 decoding="async"
	                                 onerror="this.parentElement.innerHTML='<div class=\'absolute inset-0 flex items-center justify-center text-gray-500\'>🛰️ {{ __('Radar not available') }}</div>'">
	                            <div class="absolute bottom-2 left-2 text-xs bg-black/50 px-2 py-1 rounded">
	                                📍 {{ __('Netherlands') }}
	                            </div>
	                        @else
	                            <div class="absolute inset-0 flex items-center justify-center text-gray-500">🛰️ {{ __('Radar not available') }}</div>
	                        @endif
	                    @endif
                </div>
            </div>
            </template>
        </div>

		        <!-- Bottom Section - sortable grid based on admin settings -->
		        <div id="sortable-widgets" class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 bottom-widgets-grid">
                    @if($ssrDashboard && count($ssrFallbackGroups['sortable-widgets'] ?? []) > 0)
                        @foreach($ssrFallbackGroups['sortable-widgets'] as $ssrCard)
                            <article x-show="ssrFallbackVisible"
                                     class="ssr-fallback-block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
                                     data-widget="{{ $ssrCard['id'] ?? 'widget' }}">
                                <div class="flex items-center justify-between mb-3">
                                    <h2 class="font-semibold">{{ $ssrCard['title'] ?? __('Weather') }}</h2>
                                    <span class="text-[10px] text-gray-500 uppercase tracking-wide">SSR</span>
                                </div>
                                <div class="space-y-1.5 text-sm text-gray-300">
                                    @foreach(($ssrCard['lines'] ?? []) as $ssrLine)
                                        <p class="leading-snug">{{ $ssrLine }}</p>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    @endif
                <template x-if="isWidgetEnabled('metar')">
                    @if($skyWaterFeatureEnabled)
			                <a href="{{ route('aviation') }}" class="block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 hover:border-orange-500/30 transition-colors cursor-pointer"
		                     data-widget="metar"
		                     @click="editMode && $event.preventDefault()"
		                     @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    @else
                        <div class="block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 transition-colors cursor-default"
                             data-widget="metar"
                             @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    @endif
                <!-- Drag Handle (visible in edit mode) -->
                <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                    <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                    </svg>
                </div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold">✈️ METAR</h3>
                    <div class="text-right">
                        <span class="text-xs text-gray-400" x-text="metar?.[0]?.icao || defaultMetarIcao">{{ \App\Models\Setting::getValue('metar.primary_icao', 'EHAM') }}</span>
                        @unless($skyWaterFeatureEnabled)
                            <span class="block text-[10px] text-amber-400">{{ __('Page disabled') }}</span>
                        @endunless
                    </div>
                </div>
                <template x-if="metar && metar[0]">
                    <div class="text-sm space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-400">{{ __('Temp') }}</span>
                            <span x-text="formatTemp(metar[0]?.temperature)"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">{{ __('Wind') }}</span>
                            <span x-text="(metar[0].wind?.direction ?? '--') + '° / ' + (metar[0].wind?.speed_kmh != null ? formatWind(metar[0].wind.speed_kmh, 0) : '--')"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">{{ __('Pressure') }}</span>
                            <span x-text="formatPressure(metar[0]?.pressure)"></span>
                        </div>
                        <template x-if="metar[0].clouds?.length > 0">
                            <div class="flex justify-between">
                                <span class="text-gray-400">{{ __('Sky') }}</span>
                                <span x-text="formatMetarClouds(metar[0].clouds)"></span>
                            </div>
                        </template>
                        <template x-if="metar[0].conditions?.length > 0">
                            <div class="flex justify-between">
                                <span class="text-gray-400">{{ __('Weather') }}</span>
                                <span x-text="formatMetarConditions(metar[0].conditions)"></span>
                            </div>
                        </template>
                    <div class="text-xs text-gray-500 mt-2" x-text="translations.flightCategory + ': ' + (metar[0].flight_category || '--')"></div>
                    </div>
                </template>
                <template x-if="!metar || !metar[0]">
                    <div class="text-sm text-gray-400">
                        <p>{{ __('No METAR data available') }}</p>
                    </div>
                </template>
                    @if($skyWaterFeatureEnabled)
		                </a>
                    @else
                        </div>
                    @endif
	        </template>

                @if(in_array('earthquakes', $ssrEnabledWidgets, true))
                    @if($earthquakesFeatureEnabled)
                        <a href="{{ route('earthquakes') }}"
                           x-show="ssrFallbackVisible && isWidgetEnabled('earthquakes') && earthquakes.length === 0"
                           class="ssr-fallback-block block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 hover:border-orange-500/30 transition-colors cursor-pointer"
                           data-widget="earthquakes"
                           @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    @else
                        <div x-show="ssrFallbackVisible && isWidgetEnabled('earthquakes') && earthquakes.length === 0"
                             class="ssr-fallback-block block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 transition-colors cursor-default"
                             data-widget="earthquakes"
                             @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    @endif
                    <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20" @click.prevent.stop>
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold">🌍 {{ __('Earthquakes') }}</h3>
                        <span class="text-xs text-gray-400">{{ count($ssrEarthquakes) }} {{ __('in region') }}</span>
                    </div>
                    @if(count($ssrEarthquakes) > 0)
                        <div class="text-sm space-y-2">
                            @foreach(array_slice($ssrEarthquakes, 0, 3) as $eq)
                                @php
                                    $eqMagnitude = isset($eq['magnitude']) && is_numeric($eq['magnitude']) ? number_format((float) $eq['magnitude'], 1) : '--';
                                    $eqPlace = (string) ($eq['place'] ?? $eq['location'] ?? __('Unknown'));
                                    $eqDistance = isset($eq['distance']) && is_numeric($eq['distance'])
                                        ? round((float) $eq['distance']) . ' km'
                                        : (isset($eq['distance_km']) && is_numeric($eq['distance_km']) ? round((float) $eq['distance_km']) . ' km' : '--');
                                @endphp
                                <div class="flex justify-between items-center p-2 bg-white/5 rounded-lg gap-2">
                                    <div class="flex items-center gap-2 min-w-0 flex-1">
                                        <span class="shrink-0 w-10 h-10 rounded-lg flex items-center justify-center font-bold text-sm bg-orange-500/20 text-orange-300">{{ $eqMagnitude }}</span>
                                        <div class="min-w-0">
                                            <div class="text-xs text-gray-400 truncate">{{ \Illuminate\Support\Str::limit($eqPlace, 25) }}</div>
                                            <div class="text-xs text-gray-400">{{ $eqDistance }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-sm text-gray-400 text-center py-2">
                            <p>✓ {{ __('No recent earthquakes') }}</p>
                        </div>
                    @endif
                    @if($earthquakesFeatureEnabled)
                        </a>
                    @else
                        </div>
                    @endif
                @endif
                <template x-if="isWidgetEnabled('earthquakes')">
                    @if($earthquakesFeatureEnabled)
		                <a href="{{ route('earthquakes') }}" class="block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 hover:border-orange-500/30 transition-colors cursor-pointer"
		                     data-widget="earthquakes"
		                     @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    @else
                        <div class="block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 transition-colors cursor-default"
                             data-widget="earthquakes"
                             @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    @endif
                <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20" @click.prevent.stop>
                    <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                    </svg>
                </div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold">🌍 {{ __('Earthquakes') }}</h3>
                    <span class="text-xs text-gray-400" x-text="earthquakes.length + ' ' + translations.inRegion">0 {{ __('in region') }}</span>
                </div>
                <template x-if="earthquakes.length > 0">
                    <div class="text-sm space-y-2">
                        <template x-for="eq in earthquakes.slice(0, 3)" :key="eq.id || eq.time">
                            <div class="flex justify-between items-center p-2 bg-white/5 rounded-lg gap-2">
                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                    <span class="shrink-0 w-10 h-10 rounded-lg flex items-center justify-center font-bold text-sm"
                                          :class="magnitudeColorClass(eq.magnitude)"
                                          x-text="eq.magnitude != null ? eq.magnitude.toFixed(1) : '--'"></span>
                                    <div class="min-w-0">
                                        <div class="text-xs text-gray-400 truncate" x-text="(eq.place || eq.location || '')?.substring(0, 25) || translations.unknown"></div>
                                        <div class="text-xs text-gray-400" x-text="formatDistance(eq.distance ?? eq.distance_km, 0)"></div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="earthquakes.length === 0">
                    <div class="text-sm text-gray-400 text-center py-2">
                        <p>✓ {{ __('No recent earthquakes') }}</p>
                    </div>
                </template>
                @if($earthquakesFeatureEnabled)
                    <div class="text-xs text-center mt-3 text-blue-400 hover:text-blue-300">{{ __('View all') }} →</div>
                @else
                    <div class="text-xs text-center mt-3 text-amber-400">{{ __('Page disabled') }}</div>
                @endif
                @if($earthquakesFeatureEnabled)
		            </a>
                @else
                    </div>
                @endif
	        </template>

                @if(in_array('alerts', $ssrEnabledWidgets, true))
                    @if($alertsFeatureEnabled)
                        <a href="{{ route('alerts') }}"
                           x-show="ssrFallbackVisible && isWidgetEnabled('alerts') && alerts.length === 0"
                           class="ssr-fallback-block block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 hover:border-orange-500/30 transition-colors cursor-pointer"
                           data-widget="alerts"
                           @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    @else
                        <div x-show="ssrFallbackVisible && isWidgetEnabled('alerts') && alerts.length === 0"
                             class="ssr-fallback-block block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 transition-colors cursor-default"
                             data-widget="alerts"
                             @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    @endif
                    <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20" @click.prevent.stop>
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold">⚠️ {{ __('Alerts') }}</h3>
                        @if(count($ssrAlerts) === 0)
                            <span class="text-xs px-2 py-1 bg-green-500/20 text-green-400 rounded">{{ __('None') }}</span>
                        @else
                            <span class="text-xs px-2 py-1 rounded bg-yellow-500/20 text-yellow-300">{{ count($ssrAlerts) }} {{ __('active') }}</span>
                        @endif
                    </div>
                    @if(count($ssrAlerts) > 0)
                        <div class="text-sm space-y-1.5">
                            @foreach(array_slice($ssrAlerts, 0, 3) as $alert)
                                @php $alertColor = (string) ($alert['severity_color'] ?? '#FBEA55'); @endphp
                                <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg border"
                                     style="border-color: {{ $alertColor }}40; background-color: {{ $alertColor }}10">
                                    <span class="shrink-0 w-2 h-2 rounded-full" style="background-color: {{ $alertColor }}"></span>
                                    <span class="text-xs font-medium truncate min-w-0">{{ \Illuminate\Support\Str::limit((string) ($alert['title'] ?? $alert['warning_type_label'] ?? __('Weather alert')), 40) }}</span>
                                </div>
                            @endforeach
                            @if(count($ssrAlerts) > 3)
                                <div class="text-xs text-gray-400 text-center">+{{ count($ssrAlerts) - 3 }}</div>
                            @endif
                        </div>
                    @else
                        <div class="text-sm text-gray-400 text-center py-2">
                            <p>✓ {{ __('No active alerts') }}</p>
                            @php
                                $alertsRegionCode = \App\Models\Setting::getValue('alerts.region_code', 'NL011');
                                $alertsRegionName = \App\Models\Setting::getValue('alerts.region_name', '');
                                $alertsRegionLabel = $alertsRegionName !== '' ? $alertsRegionName : (config('meteoalarm_regions.regions')[$alertsRegionCode] ?? $alertsRegionCode);
                            @endphp
                            <p class="text-xs mt-1">{{ $alertsRegionLabel }}</p>
                        </div>
                    @endif
                    @if($alertsFeatureEnabled)
                        </a>
                    @else
                        </div>
                    @endif
                @endif
                <template x-if="isWidgetEnabled('alerts')">
                    @if($alertsFeatureEnabled)
		                <a href="{{ route('alerts') }}"
		                   class="block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 hover:border-orange-500/30 transition-colors cursor-pointer"
		                   data-widget="alerts"
		                   @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    @else
                        <div class="block sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10 transition-colors cursor-default"
                             data-widget="alerts"
                             @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                    @endif
                <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20" @click.prevent.stop>
                    <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                    </svg>
                </div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold">⚠️ {{ __('Alerts') }}</h3>
                    <template x-if="alerts.length === 0">
                        <span class="text-xs px-2 py-1 bg-green-500/20 text-green-400 rounded">{{ __('None') }}</span>
                    </template>
                    <template x-if="alerts.length > 0">
                        <span class="text-xs px-2 py-1 rounded" 
                              :style="'background-color: ' + (alerts[0]?.severity_color || '#FBEA55') + '20; color: ' + (alerts[0]?.severity_color || '#FBEA55')"
                              x-text="alerts.length + ' ' + translations.active"></span>
                    </template>
                </div>
                <template x-if="alerts.length > 0">
                    <div class="text-sm space-y-1.5">
                        {{-- key on the index: internal warnings have no link, duplicates break x-for --}}
                        <template x-for="(alert, i) in alerts.slice(0, 3)" :key="i">
                            <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg border"
                                 :style="'border-color: ' + (alert.severity_color || '#FBEA55') + '40; background-color: ' + (alert.severity_color || '#FBEA55') + '10'">
                                <span class="shrink-0 w-2 h-2 rounded-full"
                                      :style="'background-color: ' + (alert.severity_color || '#FBEA55')"></span>
                                <span class="text-xs font-medium truncate min-w-0"
                                      x-text="alert.title
                                        || alert.warning_type_label
                                        || translations.warningTypes?.[alert.warning_type]
                                        || alert.warning_type
                                        || translations.weather"></span>
                            </div>
                        </template>
                        <div x-show="alerts.length > 3" class="text-xs text-gray-400 text-center"
                             x-text="'+' + (alerts.length - 3)"></div>
                    </div>
                </template>
                <template x-if="alerts.length === 0">
                    <div class="text-sm text-gray-400 text-center py-2">
                        <p>✓ {{ __('No active alerts') }}</p>
                        @php
                            $alertsRegionCode = \App\Models\Setting::getValue('alerts.region_code', 'NL011');
                            $alertsRegionName = \App\Models\Setting::getValue('alerts.region_name', '');
                            $alertsRegionLabel = $alertsRegionName !== '' ? $alertsRegionName : (config('meteoalarm_regions.regions')[$alertsRegionCode] ?? $alertsRegionCode);
                        @endphp
                        <p class="text-xs mt-1">{{ $alertsRegionLabel }}</p>
                    </div>
                </template>
                @unless($alertsFeatureEnabled)
                    <div class="text-xs text-center mt-3 text-amber-400">{{ __('Page disabled') }}</div>
                @endunless
                @if($alertsFeatureEnabled)
		            </a>
                @else
                    </div>
                @endif
	        </template>

            <!-- Advertisement Widget -->
            <div x-cloak
                 x-show="isWidgetEnabled('ads') && hasAdCode && canRenderAds"
                 class="sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
                 data-widget="ads"
                 @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                    <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                    </svg>
                </div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold">📢 <span x-text="translations.advertisement"></span></h3>
                    <span class="text-xs text-gray-400" x-show="translations.adCompany" x-text="translations.adCompany"></span>
                </div>
                <div id="dashboard-ad-slot"
                     class="ad-container w-full transition-all duration-500"
                     :class="adSlotCollapsed ? 'min-h-0 overflow-hidden' : 'min-h-[100px]'"></div>
                <p class="mt-3 text-xs text-gray-400" x-show="!adMounted || adFillState === 'loading'">{{ __('Advertisement loads when visible') }}</p>
                <p class="mt-3 text-xs text-amber-300" x-show="adMounted && adFillState === 'unfilled'">{{ __('No ad available right now (ad network returned no fill).') }}</p>
                <p class="mt-3 text-xs text-gray-400" x-show="adMounted && adFillState === 'error'">{{ __('Advertisement could not be loaded.') }}</p>
                <p class="mt-1 text-[11px] text-gray-500" x-show="adMounted && adForceTestMode">{{ __('Local ad test mode is active.') }}</p>
            </div>

            <div x-cloak
                 x-show="showAdsConsentPlaceholder"
                 class="sortable-widget bg-weather-card card-3d rounded-2xl p-5 border border-white/10"
                 data-widget="ads"
                 @mouseenter="!editMode && tiltCard($event)" @mouseleave="!editMode && resetCard($event)" @mousemove="!editMode && tiltCard($event)">
                <div class="drag-handle absolute top-2 right-2 p-2 cursor-grab hover:bg-white/10 rounded-lg transition-colors z-20">
                    <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                    </svg>
                </div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold">📢 <span x-text="translations.advertisement"></span></h3>
                    <span class="text-xs text-gray-400" x-show="translations.adCompany" x-text="translations.adCompany"></span>
                </div>
                <div class="space-y-3 text-sm">
                    <p class="text-gray-300" x-show="adsConsentStatus === 'rejected'">{{ __('You rejected ad cookies. Ads stay disabled until you change your choice.') }}</p>
                    <p class="text-gray-300" x-show="adsConsentStatus !== 'rejected'">{{ __('Ads are blocked in your region until cookie consent is accepted.') }}</p>
                    <button type="button"
                            @click="openCookieSettings()"
                            class="inline-flex items-center rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-violet-500 transition-colors">
                        {{ __('Cookie settings') }}
                    </button>
                </div>
            </div>
	        </div>
        </div>
        
        <!-- Edit Mode Indicator -->
        <div x-show="editMode" x-transition class="fixed bottom-20 lg:bottom-4 left-1/2 transform -translate-x-1/2 z-50" google-side-rail-overlap="true">
            <div class="bg-violet-600 text-white px-4 py-2 rounded-full shadow-lg flex items-center gap-2 text-sm">
                <svg class="w-4 h-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                <span>{{ __('Edit mode - drag cards to reorder') }}</span>
            </div>
        </div>
    </main>

    <!-- Footer -->
    @include('weather.partials.footer')

    @include('weather.partials.mobile-nav')

    <div x-cloak
         x-show="showCookieBanner"
         x-transition.opacity.duration.250ms
         class="fixed inset-x-0 bottom-24 lg:bottom-4 px-4"
         google-side-rail-overlap="true"
         style="z-index: 2147483000;">
        <div class="mx-auto max-w-2xl rounded-2xl border border-white/20 bg-slate-900/95 p-4 shadow-2xl backdrop-blur-sm">
            <div class="space-y-3">
                <div>
                    <h2 class="text-sm font-semibold text-white" x-text="cookieBannerCopy.title"></h2>
                    <p class="mt-1 text-xs text-gray-300" x-text="cookieBannerCopy.description"></p>
                    <p class="mt-1 text-[11px] text-gray-400">
                        <a href="{{ route('legal.privacy') }}" class="underline hover:text-gray-200">{{ __('Privacy Policy') }}</a>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button"
                            @click="acceptAdsConsent()"
                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500 transition-colors"
                            x-text="cookieBannerCopy.accept"></button>
                    <button type="button"
                            @click="rejectAdsConsent()"
                            class="rounded-lg bg-gray-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-600 transition-colors"
                            x-text="cookieBannerCopy.reject"></button>
                    <button type="button"
                            @click="openCookieSettings()"
                            class="rounded-lg border border-white/20 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10 transition-colors"
                            x-text="cookieBannerCopy.settings"></button>
                </div>
            </div>
        </div>
    </div>

    <div x-cloak
         x-show="showCookieSettingsModal"
         x-transition.opacity.duration.250ms
         class="fixed inset-0 flex items-center justify-center p-4"
         google-side-rail-overlap="true"
         style="z-index: 2147483100;">
        <div class="absolute inset-0 bg-black/60" @click="closeCookieSettings()"></div>
        <div class="relative w-full max-w-md rounded-2xl border border-white/20 bg-slate-900 p-5 shadow-2xl">
            <h3 class="text-base font-semibold text-white">{{ __('Cookie settings') }}</h3>
            <p class="mt-1 text-xs text-gray-300">{{ __('Essential cookies are always on. Ads/marketing cookies are optional.') }}</p>

            <div class="mt-4 space-y-3">
                <div class="rounded-xl border border-white/15 bg-white/5 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-sm font-medium text-white">{{ __('Essential') }}</div>
                            <div class="text-xs text-gray-400">{{ __('Required for basic website functionality.') }}</div>
                        </div>
                        <span class="rounded-md bg-emerald-600/25 px-2 py-1 text-xs font-medium text-emerald-300">{{ __('Always on') }}</span>
                    </div>
                </div>
                <div class="rounded-xl border border-white/15 bg-white/5 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-sm font-medium text-white">{{ __('Ads / Marketing') }}</div>
                            <div class="text-xs text-gray-400">{{ __('Allows loading ad network scripts and ad measurement.') }}</div>
                        </div>
                        <button type="button"
                                @click="cookieSettingsAdsAllowed = !cookieSettingsAdsAllowed"
                                :class="cookieSettingsAdsAllowed ? 'bg-violet-600' : 'bg-gray-500'"
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out">
                            <span :class="cookieSettingsAdsAllowed ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition duration-200 ease-in-out"></span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-5 flex items-center justify-end gap-2">
                <button type="button"
                        @click="closeCookieSettings()"
                        class="rounded-lg border border-white/20 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10 transition-colors">
                    {{ __('Cancel') }}
                </button>
                <button type="button"
                        @click="saveCookieSettings()"
                        class="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-500 transition-colors">
                    {{ __('Save choices') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Dashboard logic moved to resources/js/pages/dashboard.js --}}
    
    <!-- Floating header scroll behavior (mobile only) -->
    <script>
    (function() {
        const header = document.getElementById('site-header');
        if (!header) return;
        
        let lastScrollTop = 0;
        let ticking = false;
        const scrollThreshold = 5;
        
        // Dynamically adjust body padding based on header height
        function adjustBodyPadding() {
            if (window.innerWidth < 640) {
                const headerHeight = header.offsetHeight;
                document.body.style.paddingTop = headerHeight + 'px';
            } else {
                document.body.style.paddingTop = '';
            }
        }
        
        // Initial adjustment
        adjustBodyPadding();
        
        // Recalculate on resize
        window.addEventListener('resize', function() {
            adjustBodyPadding();
            if (window.innerWidth >= 640) {
                header.style.transform = '';
            }
        });
        
        // Also recalculate after fonts load (can change header height)
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(adjustBodyPadding);
        }
        
        function handleScroll() {
            if (window.innerWidth >= 640) {
                header.style.transform = '';
                ticking = false;
                return;
            }
            
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop || 0;
            const scrollDelta = scrollTop - lastScrollTop;
            
            if (scrollDelta > scrollThreshold && scrollTop > 50) {
                header.style.transform = 'translateY(-100%)';
            } else if (scrollDelta < -scrollThreshold) {
                header.style.transform = 'translateY(0)';
            }
            if (scrollTop <= 10) {
                header.style.transform = 'translateY(0)';
            }
            
            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
            ticking = false;
        }
        
        window.addEventListener('scroll', function() {
            if (!ticking) {
                window.requestAnimationFrame(handleScroll);
                ticking = true;
            }
        }, { passive: true });
    })();
    </script>
    
    {{-- Weather alert toasts — top-center --}}
    <div class="fixed top-4 left-1/2 z-[9997] flex flex-col gap-2 pointer-events-none"
         style="transform: translateX(-50%); width: calc(100% - 2rem); max-width: 420px">
        <template x-for="toast in weatherToasts" :key="toast.id">
            <div class="pointer-events-auto flex items-stretch rounded-xl shadow-xl overflow-hidden"
                 style="background: #1e2130; border: 1px solid rgba(255,255,255,0.08)"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 -translate-y-3"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-3">
                {{-- Coloured accent strip --}}
                <div class="w-1 flex-shrink-0" :style="'background:'+toast.color"></div>
                {{-- Content --}}
                <div class="flex items-start gap-2.5 px-3 py-2.5 flex-1 min-w-0">
                    <span class="text-lg flex-shrink-0 mt-0.5" x-text="toast.icon"></span>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-semibold text-white leading-snug" x-text="toast.title"></div>
                        <div class="text-[10px] text-gray-400 mt-0.5 leading-snug" x-text="toast.message"></div>
                        @if($alertsFeatureEnabled)
                        <a href="{{ route('alerts') }}"
                           class="text-[10px] mt-1 inline-block hover:underline"
                           :style="'color:'+toast.color">{{ __('View alerts') }} →</a>
                        @endif
                    </div>
                    <button @click="dismissWeatherToast(toast.id)"
                            class="flex-shrink-0 text-gray-500 hover:text-gray-200 text-lg leading-none mt-0.5"
                            :aria-label="'{{ __('Dismiss') }}'">×</button>
                </div>
            </div>
        </template>
    </div>

    <!-- PWA Install Prompt (Mobile only, non-intrusive) -->
    <div id="pwa-install-prompt" class="fixed bottom-20 left-4 right-4 z-50 hidden" google-side-rail-overlap="true">
        <div class="glass rounded-2xl p-4 border border-white/20 shadow-xl max-w-md mx-auto">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-10 h-10 bg-weather-accent/20 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-weather-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white">{{ __('Install App') }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ __('Add to home screen for quick access') }}</p>
                </div>
                <button id="pwa-prompt-close" class="flex-shrink-0 p-1 text-gray-400 hover:text-white transition" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="flex gap-2 mt-3">
                <button id="pwa-prompt-install" class="flex-1 px-4 py-2 bg-weather-accent text-white text-sm font-medium rounded-lg hover:bg-blue-500 transition">
                    {{ __('Install') }}
                </button>
                <button id="pwa-prompt-later" class="px-4 py-2 text-gray-400 text-sm hover:text-white transition">
                    {{ __('Later') }}
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // PWA Install Prompt - Non-intrusive, respects user preference
        (function() {
            const STORAGE_KEY = 'pwa_prompt_state';
            const REMIND_AFTER_DAYS = 30;
            const prompt = document.getElementById('pwa-install-prompt');
            
            if (!prompt) return;
            
            // Check if already installed as PWA
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches 
                || window.navigator.standalone === true;
            if (isStandalone) return;
            
            // Only show on mobile
            const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
            if (!isMobile) return;

            // Keep the bottom slot free for AdSense mobile anchor ads.
            const hasAdSenseScript = Boolean(
                document.querySelector('script[src*="pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"]')
            );
            if (hasAdSenseScript) return;
            
            // Check user preference
            const state = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
            
            // Never show again if user chose that
            if (state.neverShow) return;
            
            // Check if we should wait before showing again
            if (state.dismissedAt) {
                const daysSinceDismiss = (Date.now() - state.dismissedAt) / (1000 * 60 * 60 * 24);
                if (daysSinceDismiss < REMIND_AFTER_DAYS) return;
            }
            
            // Don't show on first page load - wait a bit
            let deferredPrompt = null;
            
            // Capture the install prompt event (Chrome/Edge/Samsung)
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
            });
            
            // Show prompt after 5 seconds of being on page
            setTimeout(() => {
                prompt.classList.remove('hidden');
                prompt.style.animation = 'slideUp 0.3s ease-out';
            }, 5000);
            
            // Close button - dismiss for 30 days
            document.getElementById('pwa-prompt-close')?.addEventListener('click', () => {
                hidePrompt();
                localStorage.setItem(STORAGE_KEY, JSON.stringify({ dismissedAt: Date.now() }));
            });
            
            // Later button - same as close
            document.getElementById('pwa-prompt-later')?.addEventListener('click', () => {
                hidePrompt();
                localStorage.setItem(STORAGE_KEY, JSON.stringify({ dismissedAt: Date.now() }));
            });
            
            // Install button
            document.getElementById('pwa-prompt-install')?.addEventListener('click', async () => {
                if (deferredPrompt) {
                    // Chrome/Edge - use native prompt
                    deferredPrompt.prompt();
                    const { outcome } = await deferredPrompt.userChoice;
                    if (outcome === 'accepted') {
                        localStorage.setItem(STORAGE_KEY, JSON.stringify({ neverShow: true }));
                    }
                    deferredPrompt = null;
                } else {
                    // iOS/Safari - show instructions
                    const isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
                    if (isIOS) {
                        alert('Tap the share button (□↑) and then "Add to Home Screen"');
                    } else {
                        alert('Use your browser menu to "Add to Home Screen" or "Install App"');
                    }
                }
                hidePrompt();
            });
            
            function hidePrompt() {
                prompt.style.animation = 'slideDown 0.2s ease-in forwards';
                setTimeout(() => prompt.classList.add('hidden'), 200);
            }
        })();
    </script>
    
    <style>
        @keyframes slideUp {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes slideDown {
            from { transform: translateY(0); opacity: 1; }
            to { transform: translateY(100%); opacity: 0; }
        }
    </style>

    </div><!-- /#site-wrapper -->
</body>
</html>
