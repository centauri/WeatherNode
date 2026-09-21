@extends('weather.layout')

@section('title', ($activeTab ?? 'airquality') === 'pollen' ? __('Pollen Forecast') . ' - ' . \App\Models\Setting::stationName() : (($activeTab ?? 'airquality') === 'noise' ? __('Noise Level') . ' - ' . \App\Models\Setting::stationName() : __('Air Quality') . ' - ' . \App\Models\Setting::stationName()))

@section('meta_description', __('Air quality page meta description', ['location' => $stationLocation]))
@section('og_image', route('og.air-quality'))

@section('content')
@php $activeTab = $activeTab ?? 'airquality'; @endphp

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold">
                @if($activeTab === 'pollen')🌿 {{ __('Pollen Forecast') }}@elseif($activeTab === 'noise')🔊 {{ __('Noise Level') }}@else🌬️ {{ __('Air Quality') }}@endif
            </h1>
            <p class="text-ui-muted">
                @if($activeTab === 'pollen'){{ __('pollen page intro', ['location' => $stationLocation]) }}@elseif($activeTab === 'noise'){{ __('noise page intro', ['location' => $stationLocation]) }}@else{{ __('Air quality page intro', ['location' => $stationLocation]) }}@endif
            </p>
        </div>
        @if($activeTab === 'airquality')
        <!-- Index Type Switcher (AQI tab only) -->
        <div class="flex items-center gap-2">
            <label for="indexTypeSwitcher" class="text-sm text-ui-muted hidden sm:block">{{ __('Index') }}:</label>
            <select id="indexTypeSwitcher"
                    onchange="switchIndexType(this.value)"
                    class="bg-weather-card border border-ui-line/10 text-ui-fg text-sm rounded-lg px-3 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                <option value="eea" {{ ($indexType ?? 'us') === 'eea' ? 'selected' : '' }}>{{ __('European (EEA)') }}</option>
                <option value="us" {{ ($indexType ?? 'us') === 'us' ? 'selected' : '' }}>{{ __('US EPA') }}</option>
                <option value="uk" {{ ($indexType ?? 'us') === 'uk' ? 'selected' : '' }}>{{ __('UK DAQI') }}</option>
            </select>
        </div>
        @endif
    </div>
    <script>
        function switchIndexType(type) {
            document.cookie = `aqi_index_type=${type};path=/;max-age=31536000;SameSite=Lax`;
            window.location.reload();
        }
    </script>

    <!-- Tab navigation -->
    <div class="flex gap-1 border-b border-ui-line/10">
        <a href="{{ route('airquality') }}"
           class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors
                  {{ $activeTab === 'airquality' ? 'bg-ui-accent-strong text-on-accent text-ui-fg shadow-lg shadow-ui-accent/20' : 'text-ui-muted hover:text-ui-fg hover:bg-ui-overlay/5' }}">
            🌬️ {{ __('Air Quality') }}
        </a>
        @if($settings['luftdaten_noise_enabled'] ?? false)
        <a href="{{ route('noise') }}"
           class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors
                  {{ $activeTab === 'noise' ? 'bg-purple-600 text-on-accent text-ui-fg shadow-lg shadow-purple-600/20' : 'text-ui-muted hover:text-ui-fg hover:bg-ui-overlay/5' }}">
            🔊 {{ __('Noise') }}
        </a>
        @endif
        <a href="{{ route('pollen') }}"
           class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors
                  {{ $activeTab === 'pollen' ? 'bg-green-600 text-on-accent text-ui-fg shadow-lg shadow-green-600/20' : 'text-ui-muted hover:text-ui-fg hover:bg-ui-overlay/5' }}">
            🌿 {{ __('Pollen') }}
        </a>
    </div>

    @if($activeTab === 'airquality')
    @php
        $waqi = $waqi ?? [];
        $aqi = $waqi['aqi'] ?? null;
        $indexType = $indexType ?? 'us';
        $category = $waqi['category'] ?? ['level' => 'Unknown', 'color' => '#888', 'description' => 'No data available'];

        // Map the category color from hex to Tailwind class
        $hexColor = $category['color'] ?? '#888';
        $categoryColor = match(true) {
            $aqi === null => 'gray',
            str_contains($hexColor, '00e4') || str_contains($hexColor, '50f0') || str_contains($hexColor, '50cc') || str_contains($hexColor, '9cff') || str_contains($hexColor, '31ff') || str_contains($hexColor, '31cf') => 'green',
            str_contains($hexColor, 'ffff') || str_contains($hexColor, 'f0e6') || str_contains($hexColor, 'ff0') || str_contains($hexColor, 'ffcf') => 'yellow',
            str_contains($hexColor, 'ff7e') || str_contains($hexColor, 'ff9a') => 'orange',
            str_contains($hexColor, 'ff00') || str_contains($hexColor, 'ff50') || str_contains($hexColor, 'ff64') || str_contains($hexColor, '9900') => 'red',
            str_contains($hexColor, '8f3f') || str_contains($hexColor, '9600') || str_contains($hexColor, 'ce30') || str_contains($hexColor, '7d21') => 'purple',
            default => 'rose',
        };
        $categoryText = __($category['level'] ?? 'Unknown');

        // Index type display name
        $indexTypeName = match($indexType) {
            'eea' => __('European (EEA)'),
            'uk' => __('UK DAQI'),
            default => __('US EPA'),
        };
    @endphp

    <!-- Main AQI Display -->
    <div class="bg-gradient-to-br from-{{ $categoryColor }}-900/30 to-weather-card rounded-2xl p-6 border border-{{ $categoryColor }}-500/20">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div class="flex items-center gap-6">
                <div class="w-28 h-28 rounded-full bg-{{ $categoryColor }}-500/20 flex items-center justify-center border-4 border-{{ $categoryColor }}-500/40">
                    <div class="text-center">
                    <div id="waqi-aqi" class="text-4xl font-bold text-{{ $categoryColor }}-400">{{ $aqi ?? '--' }}</div>
                        <div class="text-xs text-ui-muted">{{ __('AQI') }}</div>
                    </div>
                </div>
                <div>
                    <h2 class="text-2xl font-semibold text-{{ $categoryColor }}-400">{{ $categoryText }}</h2>
                    <p class="text-ui-muted">{{ __($category['description']) }}</p>
                    @if($waqi && isset($waqi['updated_at']))
                        <p class="text-sm text-ui-subtle mt-1">
                            {{ __('Last update') }}: <time id="waqi-update-time" class="local-time" data-utc="{{ \Carbon\Carbon::parse($waqi['updated_at'])->utc()->toIso8601String() }}">{{ \Carbon\Carbon::parse($waqi['updated_at'])->utc()->format('H:i') }} UTC</time>
                        </p>
                    @endif
                </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-ui-overlay/5 rounded-xl">
                    <div class="text-xs text-ui-muted mb-1">PM2.5</div>
                    <div id="waqi-pm25" class="text-2xl font-bold">{{ $waqi['pollutants']['pm25'] ?? '--' }}</div>
                    <div class="text-xs text-ui-subtle">µg/m³</div>
                </div>
                <div class="text-center p-4 bg-ui-overlay/5 rounded-xl">
                    <div class="text-xs text-ui-muted mb-1">PM10</div>
                    <div id="waqi-pm10" class="text-2xl font-bold">{{ $waqi['pollutants']['pm10'] ?? '--' }}</div>
                    <div class="text-xs text-ui-subtle">µg/m³</div>
                </div>
                <div class="text-center p-4 bg-ui-overlay/5 rounded-xl">
                    <div class="text-xs text-ui-muted mb-1">O₃</div>
                    <div id="waqi-o3" class="text-2xl font-bold">{{ $waqi['pollutants']['o3'] ?? '--' }}</div>
                    <div class="text-xs text-ui-subtle">µg/m³</div>
                </div>
                <div class="text-center p-4 bg-ui-overlay/5 rounded-xl">
                    <div class="text-xs text-ui-muted mb-1">NO₂</div>
                    <div id="waqi-no2" class="text-2xl font-bold">{{ $waqi['pollutants']['no2'] ?? '--' }}</div>
                    <div class="text-xs text-ui-subtle">µg/m³</div>
                </div>
            </div>
        </div>
    </div>

    <!-- AQI Scale -->
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <h3 class="font-semibold mb-4">{{ __('AQI Scale') }} <span class="text-sm font-normal text-ui-subtle">({{ $indexTypeName }})</span></h3>
        @if($indexType === 'eea')
            {{-- European EEA Scale (1-6) --}}
            <div class="flex h-6 rounded-lg overflow-hidden mb-3">
                <div class="flex-1" style="background-color: #50f0e6" title="{{ __('Good') }}"></div>
                <div class="flex-1" style="background-color: #50ccaa" title="{{ __('Fair') }}"></div>
                <div class="flex-1" style="background-color: #f0e641" title="{{ __('Moderate') }}"></div>
                <div class="flex-1" style="background-color: #ff5050" title="{{ __('Poor') }}"></div>
                <div class="flex-1" style="background-color: #960032" title="{{ __('Very Poor') }}"></div>
                <div class="flex-1" style="background-color: #7d2181" title="{{ __('Extremely Poor') }}"></div>
            </div>
            <div class="flex text-xs text-ui-muted justify-between">
                <span>1<br>{{ __('Good') }}</span>
                <span>2<br>{{ __('Fair') }}</span>
                <span>3<br>{{ __('Moderate') }}</span>
                <span>4<br>{{ __('Poor') }}</span>
                <span>5<br>{{ __('Very Poor') }}</span>
                <span>6<br>{{ __('Extr. Poor') }}</span>
            </div>
        @elseif($indexType === 'uk')
            {{-- UK DAQI Scale (1-10) --}}
            <div class="flex h-6 rounded-lg overflow-hidden mb-3">
                <div class="flex-1" style="background-color: #9cff9c" title="{{ __('Low') }} 1"></div>
                <div class="flex-1" style="background-color: #31ff00" title="{{ __('Low') }} 2"></div>
                <div class="flex-1" style="background-color: #31cf00" title="{{ __('Low') }} 3"></div>
                <div class="flex-1" style="background-color: #ffff00" title="{{ __('Moderate') }} 4"></div>
                <div class="flex-1" style="background-color: #ffcf00" title="{{ __('Moderate') }} 5"></div>
                <div class="flex-1" style="background-color: #ff9a00" title="{{ __('Moderate') }} 6"></div>
                <div class="flex-1" style="background-color: #ff6464" title="{{ __('High') }} 7"></div>
                <div class="flex-1" style="background-color: #ff0000" title="{{ __('High') }} 8"></div>
                <div class="flex-1" style="background-color: #990000" title="{{ __('High') }} 9"></div>
                <div class="flex-1" style="background-color: #ce30ff" title="{{ __('Very High') }} 10"></div>
            </div>
            <div class="flex text-xs text-ui-muted justify-between">
                <span>1-3<br>{{ __('Low') }}</span>
                <span>4-6<br>{{ __('Moderate') }}</span>
                <span>7-9<br>{{ __('High') }}</span>
                <span>10<br>{{ __('Very High') }}</span>
            </div>
        @else
            {{-- US EPA Scale (0-500) --}}
            <div class="flex h-6 rounded-lg overflow-hidden mb-3">
                <div class="flex-1 bg-green-500 text-on-accent" title="{{ __('Good') }}"></div>
                <div class="flex-1 bg-yellow-500" title="{{ __('Moderate') }}"></div>
                <div class="flex-1 bg-orange-500 text-on-accent" title="{{ __('Unhealthy for Sensitive Groups') }}"></div>
                <div class="flex-1 bg-red-500 text-on-accent" title="{{ __('Unhealthy') }}"></div>
                <div class="flex-1 bg-purple-500 text-on-accent" title="{{ __('Very Unhealthy') }}"></div>
                <div class="flex-1 bg-rose-900 text-on-accent" title="{{ __('Hazardous') }}"></div>
            </div>
            <div class="flex text-xs text-ui-muted justify-between">
                <span>0-50<br>{{ __('Good') }}</span>
                <span>51-100<br>{{ __('Moderate') }}</span>
                <span>101-150<br>{{ __('Unhealthy') }}*</span>
                <span>151-200<br>{{ __('Unhealthy') }}</span>
                <span>201-300<br>{{ __('Very Unhealthy') }}</span>
                <span>300+<br>{{ __('Hazardous') }}</span>
            </div>
        @endif
    </div>

    @php
        $noiseEnabled = $settings['luftdaten_noise_enabled'] ?? false;
        $luftEnabled  = $settings['luftdaten_enabled'] ?? false;

        $adviceLevel = 'good';
        if ($aqi !== null) {
            if ($indexType === 'eea') {
                $adviceLevel = match(true) {
                    $aqi <= 2 => 'good',
                    $aqi <= 3 => 'moderate',
                    default   => 'poor',
                };
            } elseif ($indexType === 'uk') {
                $adviceLevel = match(true) {
                    $aqi <= 3 => 'good',
                    $aqi <= 6 => 'moderate',
                    default   => 'poor',
                };
            } else {
                $adviceLevel = match(true) {
                    $aqi <= 50  => 'good',
                    $aqi <= 100 => 'moderate',
                    default     => 'poor',
                };
            }
        }
    @endphp

    <!-- Data Sources -->
    <!-- Row 1: WAQI + Luftdaten (2-col) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @if($settings['waqi_enabled'])
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold">{{ __('WAQI - Official') }}</h3>
                <a href="https://waqi.info" target="_blank" class="text-xs text-data-blue-400 hover:underline">waqi.info →</a>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
                    <span class="text-ui-muted">{{ __('Station') }}</span>
                    <span>{{ $waqi['station'] ?? __('Unknown') }}</span>
                </div>
                <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
                    <span class="text-ui-muted">{{ __('Mode') }}</span>
                    <span>{{ $settings['waqi_station_mode'] === 'auto' ? __('Nearest') : __('Manual') }}</span>
                </div>
                @if($settings['waqi_station_mode'] === 'manual' && $settings['waqi_station_id'])
                <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
                    <span class="text-ui-muted">{{ __('Station ID') }}</span>
                    <span>{{ $settings['waqi_station_id'] }}</span>
                </div>
                @endif
                <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
                    <span class="text-ui-muted">{{ __('AQI') }}</span>
                    <span class="text-{{ $categoryColor }}-400 font-bold">{{ $aqi ?? '--' }}</span>
                </div>
                @if($waqi && isset($waqi['dominant_pollutant']))
                <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
                    <span class="text-ui-muted">{{ __('Dominant') }}</span>
                    <span class="uppercase">{{ $waqi['dominant_pollutant'] }}</span>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($luftEnabled)
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold">{{ __('Luftdaten - Local') }}</h3>
                <a href="https://sensor.community" target="_blank" class="text-xs text-data-blue-400 hover:underline">sensor.community →</a>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
                    <span class="text-ui-muted">{{ __('Sensor ID') }}</span>
                    <span>{{ $settings['luftdaten_sensor_id'] ?: __('Not configured') }}</span>
                </div>
                @if($luftdaten && isset($luftdaten['formatted']))
                @php
                    $pm25 = $luftdaten['formatted']['pm25']['value'] ?? null;
                    $pm10 = $luftdaten['formatted']['pm10']['value'] ?? null;
                    $luftdatenAqi = $luftdaten['formatted']['aqi'] ?? null;
                @endphp
                <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
                    <span class="text-ui-muted">PM2.5</span>
                    <span id="luftdaten-pm25">{{ $pm25 !== null ? number_format($pm25, 1) : '--' }} µg/m³</span>
                </div>
                @if($luftdatenAqi)
                <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
                    <span class="text-ui-muted">{{ __('AQI') }}</span>
                    <span data-weather-colour-text id="luftdaten-aqi" style="color: {{ $luftdatenAqi['color'] }}">{{ $luftdatenAqi['value'] }} - {{ __($luftdatenAqi['level']) }}</span>
                </div>
                @endif
                <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
                    <span class="text-ui-muted">PM10</span>
                    <span id="luftdaten-pm10">{{ $pm10 !== null ? number_format($pm10, 1) : '--' }} µg/m³</span>
                </div>
                @if(isset($luftdaten['timestamp']))
                <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
                    <span class="text-ui-muted">{{ __('Last update') }}</span>
                    <time id="luftdaten-update-time" class="local-time" data-utc="{{ \Carbon\Carbon::parse($luftdaten['timestamp'], 'UTC')->toIso8601String() }}">{{ \Carbon\Carbon::parse($luftdaten['timestamp'], 'UTC')->format('H:i') }} UTC</time>
                </div>
                @endif
                @else
                <div class="p-4 bg-yellow-500/10 rounded-lg border border-yellow-500/20">
                    <p class="text-sm text-data-yellow-400">{{ __('No data available from the Luftdaten sensor.') }}</p>
                    <p class="text-xs text-ui-subtle mt-1">{{ __('Check if the sensor ID is set correctly.') }}</p>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>

    <!-- Health advice: full width, 3-col sub-cards -->
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <h3 class="font-semibold mb-4">💡 {{ __('Health advice') }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @if($aqi === null || $adviceLevel === 'good')
            <div class="p-4 bg-green-500/10 rounded-xl border border-green-500/20">
                <div class="text-data-green-400 font-semibold mb-2">✓ {{ __('General public') }}</div>
                <p class="text-sm text-ui-muted">{{ __('Air quality is good. Enjoy outdoor activities!') }}</p>
            </div>
            <div class="p-4 bg-green-500/10 rounded-xl border border-green-500/20">
                <div class="text-data-green-400 font-semibold mb-2">✓ {{ __('Sensitive groups') }}</div>
                <p class="text-sm text-ui-muted">{{ __('No restrictions for people with respiratory conditions.') }}</p>
            </div>
            <div class="p-4 bg-green-500/10 rounded-xl border border-green-500/20">
                <div class="text-data-green-400 font-semibold mb-2">✓ {{ __('Sports & activity') }}</div>
                <p class="text-sm text-ui-muted">{{ __('Excellent conditions for outdoor sports.') }}</p>
            </div>
            @elseif($adviceLevel === 'moderate')
            <div class="p-4 bg-yellow-500/10 rounded-xl border border-yellow-500/20">
                <div class="text-data-yellow-400 font-semibold mb-2">⚠ {{ __('General public') }}</div>
                <p class="text-sm text-ui-muted">{{ __('Air quality is acceptable for most people.') }}</p>
            </div>
            <div class="p-4 bg-yellow-500/10 rounded-xl border border-yellow-500/20">
                <div class="text-data-yellow-400 font-semibold mb-2">⚠ {{ __('Sensitive groups') }}</div>
                <p class="text-sm text-ui-muted">{{ __('Limit prolonged outdoor activity if sensitive.') }}</p>
            </div>
            <div class="p-4 bg-yellow-500/10 rounded-xl border border-yellow-500/20">
                <div class="text-data-yellow-400 font-semibold mb-2">⚠ {{ __('Sports & activity') }}</div>
                <p class="text-sm text-ui-muted">{{ __('Consider less intensive outdoor activities.') }}</p>
            </div>
            @else
            <div class="p-4 bg-red-500/10 rounded-xl border border-red-500/20">
                <div class="text-data-red-400 font-semibold mb-2">✗ {{ __('General public') }}</div>
                <p class="text-sm text-ui-muted">{{ __('Limit outdoor activity. Consider staying indoors.') }}</p>
            </div>
            <div class="p-4 bg-red-500/10 rounded-xl border border-red-500/20">
                <div class="text-data-red-400 font-semibold mb-2">✗ {{ __('Sensitive groups') }}</div>
                <p class="text-sm text-ui-muted">{{ __('Avoid outdoor activity. Keep medication at hand.') }}</p>
            </div>
            <div class="p-4 bg-red-500/10 rounded-xl border border-red-500/20">
                <div class="text-data-red-400 font-semibold mb-2">✗ {{ __('Sports & activity') }}</div>
                <p class="text-sm text-ui-muted">{{ __('No outdoor sports recommended. Choose indoor activities.') }}</p>
            </div>
            @endif
        </div>
    </div>

    <!-- Info -->
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <h3 class="font-semibold mb-4">ℹ️ {{ __('About the sources') }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-ui-muted">
            <div>
                <h4 class="font-medium text-ui-fg mb-2">{{ __('WAQI (World Air Quality Index)') }}</h4>
                <p>{{ __('Official monitoring stations from RIVM and other government institutions. Measurements are accurate but stations may be further away.') }}</p>
            </div>
            <div>
                <h4 class="font-medium text-ui-fg mb-2">{{ __('Sensor.Community (Luftdaten)') }}</h4>
                <p>{{ __('Citizen sensor network with local sensors. Data quality can vary but provides a more local view of air quality.') }}</p>
            </div>
        </div>
    </div>

    <!-- About air quality (scientific) -->
    <article class="bg-weather-card rounded-2xl border border-ui-line/10 p-6 md:p-8" aria-labelledby="airquality-about-heading">
        <h2 id="airquality-about-heading" class="text-xl font-semibold mb-4">{{ __('Air quality page about heading') }}</h2>
        <div class="prose prose-invert prose-sm max-w-none text-ui-secondary space-y-4">
            <p>{{ __('Air quality page about body 1') }}</p>
        </div>
        <footer class="mt-6 pt-4 border-t border-ui-line/10">
            <p class="text-xs text-ui-subtle">{{ __('Air quality page sources') }}</p>
        </footer>
    </article>

    @elseif($activeTab === 'noise')
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{--  NOISE TAB                                                             --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @php
        $nd         = $luftdaten_noise;
        $noiseAvg   = $nd['formatted']['noise_avg']['value'] ?? null;
        $noiseMin   = $nd['formatted']['noise_min']['value'] ?? null;
        $noiseMax   = $nd['formatted']['noise_max']['value'] ?? null;
        $noiseLevel = $nd['noise_level'] ?? null;
        $nlColor    = $noiseLevel['color'] ?? '#6b7280';
        $nlName     = $noiseLevel['level'] ?? null;
        $nlDesc     = $noiseLevel['description'] ?? null;
    @endphp

    @if(!($settings['luftdaten_noise_enabled'] ?? false))
    {{-- Sensor not enabled --}}
    <div class="bg-weather-card rounded-2xl p-8 border border-ui-line/10 text-center">
        <div class="text-4xl mb-3">🔊</div>
        <h3 class="text-lg font-semibold mb-2">{{ __('Noise sensor not enabled') }}</h3>
        <p class="text-ui-muted text-sm">{{ __('The noise sensor has not been configured yet. Please check back later.') }}</p>
    </div>
    @else

    {{-- Hero: current noise level --}}
    <div class="rounded-2xl p-6 border bg-weather-card" style="border-color: {{ $nlColor }}40">
        <div class="flex flex-col md:flex-row md:items-center gap-6">
            <div class="flex items-center gap-5">
                <div class="w-28 h-28 rounded-full flex items-center justify-center border-4 shrink-0"
                     style="background-color: {{ $nlColor }}20; border-color: {{ $nlColor }}60">
                    <div class="text-center">
                        <div data-weather-colour-text class="text-3xl font-bold leading-none" id="noise-avg" style="color: {{ $nlColor }}">
                            {{ $noiseAvg !== null ? number_format($noiseAvg, 1) : '--' }}
                        </div>
                        <div class="text-xs text-ui-muted mt-1">dB(A)</div>
                        <div class="text-[9px] text-ui-subtle uppercase tracking-wide">L<sub>Aeq</sub></div>
                    </div>
                </div>
                <div>
                    @if($nlName)
                    <div id="noise-level-bar" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg mb-2"
                         style="background-color: {{ $nlColor }}20; border: 1px solid {{ $nlColor }}40">
                        <span data-weather-colour-text id="noise-level-text" class="font-semibold" style="color: {{ $nlColor }}">{{ __($nlName) }} — {{ __($nlDesc) }}</span>
                    </div>
                    @endif
                    <p class="text-sm text-ui-muted">{{ __('Current noise level at :location', ['location' => $stationLocation]) }}</p>
                    @if(isset($nd['timestamp']))
                    <p class="text-xs text-ui-subtle mt-1">{{ __('Last update') }}: <time id="noise-update-time" class="local-time" data-utc="{{ \Carbon\Carbon::parse($nd['timestamp'], 'UTC')->toIso8601String() }}">{{ \Carbon\Carbon::parse($nd['timestamp'], 'UTC')->format('H:i') }} UTC</time></p>
                    @endif
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 md:ml-auto">
                <div class="text-center p-4 bg-ui-overlay/5 rounded-xl border border-ui-line/10">
                    <div class="text-xs text-ui-muted mb-1">{{ __('Minimum') }}</div>
                    <div class="text-xl font-bold" id="noise-min">{{ $noiseMin !== null ? number_format($noiseMin, 1) : '--' }}</div>
                    <div class="text-xs text-ui-subtle">dB(A)</div>
                </div>
                <div class="text-center p-4 bg-ui-overlay/5 rounded-xl border border-ui-line/10">
                    <div class="text-xs text-ui-muted mb-1">{{ __('Maximum') }}</div>
                    <div class="text-xl font-bold" id="noise-max">{{ $noiseMax !== null ? number_format($noiseMax, 1) : '--' }}</div>
                    <div class="text-xs text-ui-subtle">dB(A)</div>
                </div>
            </div>
        </div>
        @if(!$nd)
        <div class="mt-4 p-4 bg-yellow-500/10 rounded-lg border border-yellow-500/20">
            <p class="text-sm text-data-yellow-400">{{ __('No data available from the noise sensor.') }}</p>
            <p class="text-xs text-ui-subtle mt-1">{{ __('Check sensor ID and that the sensor is a DNMS noise sensor.') }}</p>
        </div>
        @endif
    </div>

    {{-- Noise level scale --}}
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <h3 class="font-semibold mb-4">📊 {{ __('Noise level scale') }}</h3>
        @php
        $noiseScale = [
            ['level' => 'Very Quiet',     'desc' => 'Whisper, rural area',          'range' => '< 30 dB',  'color' => '#4CAF50'],
            ['level' => 'Quiet',          'desc' => 'Library, quiet office',        'range' => '30–40 dB', 'color' => '#8BC34A'],
            ['level' => 'Moderate',       'desc' => 'Moderate rainfall',            'range' => '40–50 dB', 'color' => '#CDDC39'],
            ['level' => 'Noticeable',     'desc' => 'Normal conversation',          'range' => '50–60 dB', 'color' => '#FFEB3B'],
            ['level' => 'Loud',           'desc' => 'Busy traffic, vacuum cleaner', 'range' => '60–70 dB', 'color' => '#FFC107'],
            ['level' => 'Very Loud',      'desc' => 'Busy street, alarm clock',     'range' => '70–80 dB', 'color' => '#FF9800'],
            ['level' => 'Extremely Loud', 'desc' => 'Heavy traffic, lawn mower',    'range' => '80–90 dB', 'color' => '#FF5722'],
            ['level' => 'Harmful',        'desc' => 'Risk of hearing damage',       'range' => '≥ 90 dB',  'color' => '#F44336'],
        ];
        @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach($noiseScale as $scale)
        <div class="flex items-start gap-3 p-3 rounded-xl border {{ ($nlName === $scale['level']) ? 'ring-2 ring-ui-line/30' : '' }}"
             style="background-color: {{ $scale['color'] }}15; border-color: {{ $scale['color'] }}30">
            <div class="w-3 h-3 rounded-full mt-0.5 shrink-0" style="background-color: {{ $scale['color'] }}"></div>
            <div>
                <div data-weather-colour-text class="text-xs font-semibold" style="color: {{ $scale['color'] }}">{{ __($scale['level']) }}</div>
                <div class="text-[11px] text-ui-muted">{{ $scale['range'] }}</div>
                <div class="text-[10px] text-ui-subtle">{{ __($scale['desc']) }}</div>
            </div>
        </div>
        @endforeach
        </div>
    </div>

    {{-- 24-hour Grafana history --}}
    @if(!empty($grafana_noise_history_url))
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <div class="mb-3">
            <h3 class="font-semibold">📈 {{ __('24-hour noise history') }}</h3>
        </div>
        <iframe
            src="{{ $grafana_noise_history_url }}"
            class="w-full rounded-lg border border-ui-line/10"
            style="height: 320px;"
            loading="lazy"
            title="{{ __('Noise history') }}"
        ></iframe>
        <p class="text-xs text-ui-subtle mt-2">{{ __('Noise history dashboard by Madavi, data from Sensor.Community') }}</p>
    </div>
    @endif

    {{-- WHO guidelines + health effects --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- WHO noise guidelines -->
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">🏥 {{ __('WHO noise guidelines') }}</h3>
            <p class="text-sm text-ui-muted mb-4">{{ __('WHO noise guidelines intro') }}</p>
            <div class="space-y-2">
                <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
                    <span class="text-ui-secondary text-sm">{{ __('Residential (day)') }}</span>
                    <span class="font-semibold text-data-yellow-400">&lt; 55 dB</span>
                </div>
                <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
                    <span class="text-ui-secondary text-sm">{{ __('Residential (night)') }}</span>
                    <span class="font-semibold text-data-green-400">&lt; 45 dB</span>
                </div>
                <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
                    <span class="text-ui-secondary text-sm">{{ __('Road traffic (L_den)') }}</span>
                    <span class="font-semibold text-data-yellow-400">&lt; 53 dB</span>
                </div>
                <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
                    <span class="text-ui-secondary text-sm">{{ __('Aircraft noise (L_den)') }}</span>
                    <span class="font-semibold text-data-yellow-400">&lt; 45 dB</span>
                </div>
                <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
                    <span class="text-ui-secondary text-sm">{{ __('Work environment (8h)') }}</span>
                    <span class="font-semibold text-data-orange-400">&lt; 80 dB</span>
                </div>
            </div>
            <p class="text-xs text-ui-subtle mt-3">{{ __('Source: WHO Environmental Noise Guidelines for the European Region, 2018') }}</p>
        </div>

        <!-- Health effects of noise -->
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">💊 {{ __('Health effects of noise') }}</h3>
            <div class="space-y-3">
                <div class="p-3 bg-green-500/10 rounded-xl border border-green-500/20">
                    <div class="text-data-green-400 font-semibold text-sm mb-1">{{ __('Below 55 dB') }}</div>
                    <p class="text-xs text-ui-muted">{{ __('noise effect below 55') }}</p>
                </div>
                <div class="p-3 bg-yellow-500/10 rounded-xl border border-yellow-500/20">
                    <div class="text-data-yellow-400 font-semibold text-sm mb-1">{{ __('55–65 dB') }}</div>
                    <p class="text-xs text-ui-muted">{{ __('noise effect 55 to 65') }}</p>
                </div>
                <div class="p-3 bg-orange-500/10 rounded-xl border border-orange-500/20">
                    <div class="text-data-orange-400 font-semibold text-sm mb-1">{{ __('65–75 dB') }}</div>
                    <p class="text-xs text-ui-muted">{{ __('noise effect 65 to 75') }}</p>
                </div>
                <div class="p-3 bg-red-500/10 rounded-xl border border-red-500/20">
                    <div class="text-data-red-400 font-semibold text-sm mb-1">{{ __('Above 75 dB') }}</div>
                    <p class="text-xs text-ui-muted">{{ __('noise effect above 75') }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Measurement explanation + About DNMS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Measurement explanation -->
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">📐 {{ __('Measurement explanation') }}</h3>
            <div class="space-y-3 text-sm">
                <div class="p-3 bg-ui-overlay/5 rounded-lg">
                    <div class="font-semibold text-ui-fg mb-1">L<sub>Aeq</sub> — {{ __('Average') }}</div>
                    <p class="text-ui-muted text-xs">{{ __('LAeq explained') }}</p>
                </div>
                <div class="p-3 bg-ui-overlay/5 rounded-lg">
                    <div class="font-semibold text-ui-fg mb-1">L<sub>A,min</sub> — {{ __('Minimum') }}</div>
                    <p class="text-ui-muted text-xs">{{ __('LAmin explained') }}</p>
                </div>
                <div class="p-3 bg-ui-overlay/5 rounded-lg">
                    <div class="font-semibold text-ui-fg mb-1">L<sub>A,max</sub> — {{ __('Maximum') }}</div>
                    <p class="text-ui-muted text-xs">{{ __('LAmax explained') }}</p>
                </div>
                <div class="p-3 bg-ui-overlay/5 rounded-lg">
                    <div class="font-semibold text-ui-fg mb-1">dB(A)</div>
                    <p class="text-ui-muted text-xs">{{ __('dBA explained') }}</p>
                </div>
            </div>
        </div>

        <!-- About the DNMS sensor -->
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">🔬 {{ __('About the DNMS sensor') }}</h3>
            <p class="text-sm text-ui-muted mb-4">{{ __('dnms sensor description') }}</p>
            <div class="space-y-2">
                <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
                    <span class="text-ui-muted text-sm">{{ __('Sensor ID') }}</span>
                    <span class="text-sm">{{ $settings['luftdaten_noise_sensor_id'] ?: __('Not configured') }}</span>
                </div>
                <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
                    <span class="text-ui-muted text-sm">{{ __('Network') }}</span>
                    <a href="https://sensor.community" target="_blank" rel="noopener noreferrer" class="text-data-blue-400 hover:underline text-sm">Sensor.Community</a>
                </div>
                <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
                    <span class="text-ui-muted text-sm">{{ __('Sensor type') }}</span>
                    <span class="text-sm">DNMS (ICS-43434)</span>
                </div>
                <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
                    <span class="text-ui-muted text-sm">{{ __('Update interval') }}</span>
                    <span class="text-sm">~145 {{ __('seconds') }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- About noise pollution article --}}
    <article class="bg-weather-card rounded-2xl border border-ui-line/10 p-6 md:p-8" aria-labelledby="noise-about-heading">
        <h2 id="noise-about-heading" class="text-xl font-semibold mb-4">{{ __('About noise pollution') }}</h2>
        <div class="prose prose-invert prose-sm max-w-none text-ui-secondary space-y-4">
            <p>{{ __('noise about body 1') }}</p>
            <p>{{ __('noise about body 2') }}</p>
            <p>{{ __('noise about body 3') }}</p>
        </div>
        <footer class="mt-6 pt-4 border-t border-ui-line/10">
            <p class="text-xs text-ui-subtle">{{ __('noise page sources') }}</p>
        </footer>
    </article>

    @endif {{-- end noise enabled check --}}

    @else
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{--  POLLEN TAB                                                           --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @php
        $p       = $pollen ?? null;
        $today   = $p['today']    ?? null;
        $forecast = $p['forecast'] ?? [];
        $species = $p['species']  ?? null;
        $healthRecs = $p['health_recommendations'] ?? [];
        $sources    = $p['sources'] ?? [];

        $riskBg = [
            'None'      => 'bg-green-500/10 border-green-500/20 text-data-green-400',
            'Low'       => 'bg-lime-500/10 border-lime-500/20 text-data-lime-400',
            'Moderate'  => 'bg-yellow-500/10 border-yellow-500/20 text-data-yellow-400',
            'High'      => 'bg-orange-500/10 border-orange-500/20 text-data-orange-400',
            'Very High' => 'bg-red-500/10 border-red-500/20 text-data-red-400',
        ];

        $translateRisk = fn($r) => match($r) {
            'None'      => __('None'),
            'Low'       => __('Low'),
            'Moderate'  => __('Moderate'),
            'High'      => __('High'),
            'Very High' => __('Very High'),
            default     => $r,
        };
    @endphp

    @if(!$p)
    {{-- No pollen data --}}
    <div class="bg-weather-card rounded-2xl p-8 border border-ui-line/10 text-center">
        <div class="text-4xl mb-3">🌿</div>
        <h3 class="text-lg font-semibold mb-2">{{ __('No pollen data available') }}</h3>
        <p class="text-ui-muted text-sm mb-4">{{ __('Pollen data has not been fetched yet. Please check back later.') }}</p>
    </div>
    @else

    {{-- Today's overview --}}
    @php
        $overall = $today['overall_risk'] ?? 'None';
        $overallIndex = $today['overall_risk_index'] ?? 0;
        $overallColor = $today['overall_color'] ?? '#22c55e';
        $overallCls = $riskBg[$overall] ?? $riskBg['None'];
    @endphp
    <div class="rounded-2xl p-6 border {{ $overallCls }} bg-gradient-to-br from-weather-card to-transparent">
        <div class="flex flex-col md:flex-row md:items-center gap-6">
            <div class="flex items-center gap-5">
                <div class="w-24 h-24 rounded-full flex items-center justify-center border-4"
                     style="background-color: {{ $overallColor }}20; border-color: {{ $overallColor }}40">
                    <div class="text-center">
                        <div data-weather-colour-text class="text-3xl font-bold" style="color: {{ $overallColor }}">{{ $overallIndex }}</div>
                        <div class="text-[9px] text-ui-muted uppercase tracking-wide">{{ __('Overall') }}</div>
                    </div>
                </div>
                <div>
                    <h2 class="text-2xl font-semibold" style="color: {{ $overallColor }}">{{ $translateRisk($overall) }}</h2>
                    <p class="text-ui-muted">{{ __('Overall Pollen Risk') }}</p>
                    @if(isset($p['updated_at']))
                    <p class="text-xs text-ui-subtle mt-1">{{ __('Last update') }}: <time class="local-time" data-utc="{{ $p['updated_at'] }}">{{ \Carbon\Carbon::parse($p['updated_at'])->format('H:i') }} UTC</time></p>
                    @endif
                </div>
            </div>
            {{-- Per-type today badges --}}
            <div class="grid grid-cols-3 gap-3 md:ml-auto">
                @foreach(['grass' => ['label' => __('Grass'), 'icon' => '🌾'], 'tree' => ['label' => __('Tree'), 'icon' => '🌳'], 'weed' => ['label' => __('Weed'), 'icon' => '🌿']] as $cat => $meta)
                @php
                    $t = $today[$cat] ?? ['risk' => 'None', 'risk_index' => 0, 'count' => null, 'color' => '#22c55e'];
                    $tCls = $riskBg[$t['risk']] ?? $riskBg['None'];
                @endphp
                <div class="text-center p-3 rounded-xl border {{ $tCls }}">
                    <div class="text-lg mb-1">{{ $meta['icon'] }}</div>
                    <div class="text-xs text-ui-muted mb-1">{{ $meta['label'] }}</div>
                    <div class="text-sm font-semibold">{{ $translateRisk($t['risk']) }}</div>
                    @if($t['count'] !== null && $t['count'] > 0)
                        <div class="text-[10px] text-ui-subtle">{{ number_format($t['count'], 1) }} gr/m³</div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Health advice --}}
    @php
        $adviceLevel = match(true) {
            $overallIndex >= 4 => 'very_high',
            $overallIndex >= 3 => 'high',
            $overallIndex >= 2 => 'moderate',
            $overallIndex >= 1 => 'low',
            default            => 'none',
        };
        $adviceCls = match($adviceLevel) {
            'very_high' => 'red', 'high' => 'orange', 'moderate' => 'yellow', default => 'green',
        };
    @endphp
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <h3 class="font-semibold mb-4">💡 {{ __('Allergy Advice') }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @if($adviceLevel === 'none' || $adviceLevel === 'low')
                <div class="p-3 bg-green-500/10 rounded-xl border border-green-500/20 text-sm text-ui-secondary">✓ {{ __('Low pollen risk - safe for most people') }}</div>
                <div class="p-3 bg-green-500/10 rounded-xl border border-green-500/20 text-sm text-ui-secondary">✓ {{ __('Suitable for outdoor activities') }}</div>
            @elseif($adviceLevel === 'moderate')
                <div class="p-3 bg-yellow-500/10 rounded-xl border border-yellow-500/20 text-sm text-ui-secondary">⚠ {{ __('Moderate pollen risk - sensitive people may experience symptoms') }}</div>
                <div class="p-3 bg-yellow-500/10 rounded-xl border border-yellow-500/20 text-sm text-ui-secondary">⚠ {{ __('Keep windows closed during peak pollen hours') }}</div>
            @elseif($adviceLevel === 'high')
                <div class="p-3 bg-orange-500/10 rounded-xl border border-orange-500/20 text-sm text-ui-secondary">⚠ {{ __('High pollen risk - allergy sufferers should limit outdoor exposure') }}</div>
                <div class="p-3 bg-orange-500/10 rounded-xl border border-orange-500/20 text-sm text-ui-secondary">⚠ {{ __('Take antihistamine medication if needed') }}</div>
            @else
                <div class="p-3 bg-red-500/10 rounded-xl border border-red-500/20 text-sm text-ui-secondary">✗ {{ __('Very high pollen risk - avoid prolonged outdoor activities') }}</div>
                <div class="p-3 bg-red-500/10 rounded-xl border border-red-500/20 text-sm text-ui-secondary">✗ {{ __('Keep windows and doors closed') }}</div>
            @endif
            <div class="p-3 bg-ui-overlay/5 rounded-xl text-sm text-ui-muted">ℹ {{ __('Pollen peaks in dry, warm, windy weather') }}</div>
            <div class="p-3 bg-ui-overlay/5 rounded-xl text-sm text-ui-muted">ℹ {{ __('Rain can temporarily reduce airborne pollen') }}</div>
        </div>
    </div>

    {{-- 5-day forecast chart --}}
    @if(count($forecast) > 0)
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <h3 class="font-semibold mb-4">📅 {{ __('5-day Pollen Forecast') }}</h3>
        <div id="pollen-forecast-chart"></div>
        <script id="pollen-chart-data" type="application/json">
        {!! json_encode([
            'forecast' => collect($forecast)->map(fn($d) => [
                'date'       => $d['date'],
                'date_label' => $d['date_label'] ?? \Carbon\Carbon::parse($d['date'])->format('D'),
                'grass'      => $d['grass'] ?? ['risk_index' => 0, 'count' => null],
                'tree'       => $d['tree']  ?? ['risk_index' => 0, 'count' => null],
                'weed'       => $d['weed']  ?? ['risk_index' => 0, 'count' => null],
            ])->values()->all(),
            'labels' => [
                'grass'     => __('Grass'),
                'tree'      => __('Tree'),
                'weed'      => __('Weed'),
                'none'      => __('None'),
                'low'       => __('Low'),
                'moderate'  => __('Moderate'),
                'high'      => __('High'),
                'very_high' => __('Very High'),
            ],
        ]) !!}
        </script>
    </div>
    @endif

    {{-- Day cards (visual alternative / supplement to chart) --}}
    @if(count($forecast) > 0)
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
        @foreach($forecast as $day)
        @php
            $dayOverall = max($day['grass']['risk_index'] ?? 0, $day['tree']['risk_index'] ?? 0, $day['weed']['risk_index'] ?? 0);
            $dayColor   = \App\Services\Pollen\PollenAggregator::riskColor($dayOverall);
            $dayLabel   = \App\Services\Pollen\PollenAggregator::riskLabel($dayOverall);
        @endphp
        <div class="bg-weather-card rounded-xl p-4 border border-ui-line/10 text-center">
            <div class="text-sm font-medium text-ui-secondary mb-2">
                {{ $day['date_label'] ?? \Carbon\Carbon::parse($day['date'])->format('D') }}
            </div>
            <div class="text-xs text-ui-subtle mb-3">{{ \Carbon\Carbon::parse($day['date'])->format('j M') }}</div>
            <div class="space-y-1.5">
                @foreach(['grass' => '🌾', 'tree' => '🌳', 'weed' => '🌿'] as $cat => $icon)
                @php $dc = $day[$cat] ?? ['risk_index' => 0, 'risk' => 'None', 'color' => '#22c55e']; @endphp
                <div class="flex items-center justify-between text-xs">
                    <span class="text-ui-muted">{{ $icon }}</span>
                    <span data-weather-colour-text class="font-medium" style="color: {{ $dc['color'] ?? \App\Services\Pollen\PollenAggregator::riskColor($dc['risk_index']) }}">
                        {{ $translateRisk($dc['risk']) }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Species breakdown --}}
    @if($species)
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <h3 class="font-semibold mb-4">🔬 {{ __('Species Breakdown') }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach(['tree' => ['label' => __('Tree'), 'icon' => '🌳'], 'grass' => ['label' => __('Grass'), 'icon' => '🌾'], 'weed' => ['label' => __('Weed'), 'icon' => '🌿']] as $cat => $meta)
            @if(!empty($species[$cat]))
            <div>
                <h4 class="text-sm font-medium text-ui-secondary mb-2">{{ $meta['icon'] }} {{ $meta['label'] }}</h4>
                <div class="space-y-1.5">
                    @foreach($species[$cat] as $plant => $data)
                    @php
                        $pIdx = is_array($data) ? ($data['risk_index'] ?? 0) : 0;
                        $pRisk = is_array($data) ? ($data['risk'] ?? 'None') : 'None';
                        $pColor = \App\Services\Pollen\PollenAggregator::riskColor($pIdx);
                    @endphp
                    <div class="flex items-center justify-between p-2 bg-ui-overlay/5 rounded-lg text-sm">
                        <span class="text-ui-secondary">{{ __($plant) }}</span>
                        <span data-weather-colour-text class="font-medium text-xs" style="color: {{ $pColor }}">{{ $translateRisk($pRisk) }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
            @endforeach
        </div>
    </div>
    @endif

    {{-- Data sources --}}
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <h3 class="font-semibold mb-4">ℹ️ {{ __('Pollen Sources') }}</h3>
        <div class="flex flex-wrap gap-3">
            @if(in_array('openmeteo', $sources))
            <div class="flex items-center gap-2 px-3 py-2 bg-green-500/10 border border-green-500/20 rounded-lg text-sm">
                <div class="w-2 h-2 rounded-full bg-green-400"></div>
                <span class="text-data-green-400 font-medium">Open-Meteo</span>
                <span class="text-ui-subtle">— {{ __('Free, global, grains/m³') }}</span>
            </div>
            @endif
            @if(in_array('google', $sources))
            <div class="flex items-center gap-2 px-3 py-2 bg-blue-500/10 border border-blue-500/20 rounded-lg text-sm">
                <div class="w-2 h-2 rounded-full bg-blue-400"></div>
                <span class="text-data-blue-400 font-medium">Google Pollen API</span>
                <span class="text-ui-subtle">— {{ __('Risk index + plant names') }}</span>
            </div>
            @endif
            @if(in_array('ambee', $sources))
            <div class="flex items-center gap-2 px-3 py-2 bg-amber-500/10 border border-amber-500/20 rounded-lg text-sm">
                <div class="w-2 h-2 rounded-full bg-amber-400"></div>
                <span class="text-data-amber-400 font-medium">Ambee</span>
                <span class="text-ui-subtle">— {{ __('Species breakdown + counts') }}</span>
            </div>
            @endif
        </div>
    </div>

    {{-- About pollen --}}
    <article class="bg-weather-card rounded-2xl border border-ui-line/10 p-6 md:p-8">
        <h2 class="text-xl font-semibold mb-4">{{ __('About pollen data') }}</h2>
        <div class="prose prose-invert prose-sm max-w-none text-ui-secondary space-y-4">
            <p>{{ __('pollen about body 1') }}</p>
            <p>{{ __('pollen about body 2') }}</p>
        </div>
        <div class="mt-4 grid grid-cols-2 md:grid-cols-5 gap-2 text-xs">
            @foreach(['None' => '#22c55e', 'Low' => '#84cc16', 'Moderate' => '#eab308', 'High' => '#f97316', 'Very High' => '#ef4444'] as $lvl => $col)
            <div class="flex items-center gap-2 p-2 bg-ui-overlay/5 rounded-lg">
                <div class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $col }}"></div>
                <span class="text-ui-secondary">{{ $translateRisk($lvl) }}</span>
            </div>
            @endforeach
        </div>
    </article>

    @endif {{-- end $p check --}}
    @endif {{-- end tab check --}}

</div>

@push('scripts')
<script>
(function() {
    // Convert UTC ISO string to browser-local HH:MM
    function toLocalTime(isoOrPlain) {
        try {
            // Sensor.Community timestamps come as "YYYY-MM-DD HH:MM:SS" (plain UTC, no Z)
            var s = isoOrPlain.replace(' ', 'T');
            if (!s.endsWith('Z') && !s.match(/[+-]\d{2}:\d{2}$/)) s += 'Z';
            var dt = new Date(s);
            return isNaN(dt.getTime()) ? null : dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch(e) { return null; }
    }

    function setLocalTime(el, isoOrPlain) {
        if (!el || !isoOrPlain) return;
        var formatted = toLocalTime(isoOrPlain);
        if (formatted) {
            el.setAttribute('data-utc', isoOrPlain);
            el.textContent = formatted;
        }
    }

    // Render all server-pre-filled local-time elements on first load
    document.querySelectorAll('time.local-time[data-utc]').forEach(function(el) {
        var formatted = toLocalTime(el.dataset.utc);
        if (formatted) el.textContent = formatted;
    });

    // AJAX refresh: update only the data values — Grafana iframe is never touched
    async function refreshData() {
        var headers = (typeof window.Meteo?.apiHeaders === 'function') ? window.Meteo.apiHeaders() : {};

        // 1. Noise sensor (/api/weather/noise)
        try {
            var r = await fetch('/api/weather/noise', { headers: headers });
            if (r.ok) {
                var j = await r.json();
                var d = j.success && j.data ? j.data : null;
                if (d) {
                    var fmt = d.formatted || {};
                    var lvl = d.noise_level || {};

                    var avgEl  = document.getElementById('noise-avg');
                    var minEl  = document.getElementById('noise-min');
                    var maxEl  = document.getElementById('noise-max');
                    var barEl  = document.getElementById('noise-level-bar');
                    var txtEl  = document.getElementById('noise-level-text');
                    var timeEl = document.getElementById('noise-update-time');

                    // Only update the numeric value; dB(A) is in separate elements to keep font sizes correct
                    if (avgEl  && fmt.noise_avg?.value  != null) avgEl.textContent  = fmt.noise_avg.value.toFixed(1);
                    if (minEl  && fmt.noise_min?.value  != null) minEl.textContent  = fmt.noise_min.value.toFixed(1);
                    if (maxEl  && fmt.noise_max?.value  != null) maxEl.textContent  = fmt.noise_max.value.toFixed(1);

                    if (barEl && lvl.color) {
                        barEl.style.backgroundColor = lvl.color + '20';
                        barEl.style.border = '1px solid ' + lvl.color + '40';
                    }
                    if (txtEl && lvl.level && lvl.description) {
                        if (lvl.color) txtEl.style.color = lvl.color;
                        txtEl.textContent = lvl.level + ' \u2014 ' + lvl.description;
                    }
                    if (d.timestamp) setLocalTime(timeEl, d.timestamp);
                }
            }
        } catch(e) {}

        // 2. WAQI air quality (/api/weather/air-quality)
        try {
            var r2 = await fetch('/api/weather/air-quality', { headers: headers });
            if (r2.ok) {
                var j2 = await r2.json();
                var w = j2.waqi;
                if (w) {
                    var poll = w.pollutants || {};
                    var set = function(id, val) { var el = document.getElementById(id); if (el && val != null) el.textContent = val; };
                    set('waqi-aqi',  w.aqi  ?? null);
                    set('waqi-pm25', poll.pm25 ?? null);
                    set('waqi-pm10', poll.pm10 ?? null);
                    set('waqi-o3',   poll.o3   ?? null);
                    set('waqi-no2',  poll.no2  ?? null);
                    if (w.updated_at) setLocalTime(document.getElementById('waqi-update-time'), w.updated_at);
                }
            }
        } catch(e) {}

        // 3. Luftdaten PM sensor (/api/data/luftdaten)
        try {
            var r3 = await fetch('/api/data/luftdaten', { headers: headers });
            if (r3.ok) {
                var j3 = await r3.json();
                var ld = j3.success && j3.data ? j3.data : null;
                if (ld && ld.formatted) {
                    var lPm25 = ld.formatted.pm25?.value;
                    var lPm10 = ld.formatted.pm10?.value;
                    var pm25El = document.getElementById('luftdaten-pm25');
                    var pm10El = document.getElementById('luftdaten-pm10');
                    if (pm25El && lPm25 != null) pm25El.textContent = lPm25.toFixed(1) + ' µg/m³';
                    if (pm10El && lPm10 != null) pm10El.textContent = lPm10.toFixed(1) + ' µg/m³';
                    if (ld.timestamp) setLocalTime(document.getElementById('luftdaten-update-time'), ld.timestamp);
                }
            }
        } catch(e) {}
    }

    // Refresh every 60 seconds without touching the page or any iframes
    setInterval(refreshData, 60000);
})();
</script>
@if(isset($activeTab) && $activeTab === 'pollen')
@vite('resources/js/pages/pollen-charts.js')
@endif
@endpush
@endsection
