@extends('layouts.admin')

@section('title', __('Davis WeatherLink'))

@section('content')
@php
    $type = \App\Models\Setting::getValue('weatherlink.type', 'v2');
    $enabled = (bool) \App\Models\Setting::getValue('weatherlink.enabled', true);
    $demoMode = (bool) \App\Models\Setting::getValue('weatherlink.demo_mode', false);
    
    // Load all type-specific settings
    $all = [
        'v1' => [
            'device_id' => \App\Models\Setting::getValue('weatherlink.device_id', ''),
            'password' => \App\Models\Setting::getValue('weatherlink.password', ''),
            'api_key' => \App\Models\Setting::getValue('weatherlink.api_key', ''),
        ],
        'v2' => [
            'api_key' => \App\Models\Setting::getValue('weatherlink.api_key', ''),
            'api_secret' => \App\Models\Setting::getValue('weatherlink.api_secret', ''),
            'station_id' => \App\Models\Setting::getValue('weatherlink.station_id', ''),
            'demo_mode' => $demoMode,
        ],
        'airlink_local' => [
            'ip' => \App\Models\Setting::getValue('weatherlink.airlink_ip', ''),
            'port' => (int) \App\Models\Setting::getValue('weatherlink.airlink_port', 80),
        ],
        'wll_local' => [
            'ip' => \App\Models\Setting::getValue('weatherlink.wll_ip', ''),
            'port' => (int) \App\Models\Setting::getValue('weatherlink.wll_port', 80),
            'udp_enabled' => (bool) \App\Models\Setting::getValue('weatherlink.wll_udp_enabled', false),
            'udp_port' => (int) \App\Models\Setting::getValue('weatherlink.wll_udp_port', 22222),
            'udp_duration' => (int) \App\Models\Setting::getValue('weatherlink.wll_udp_duration', 1200),
        ],
    ];
    
    $current = $all[$type] ?? $all['v2'];
@endphp

<div class="w-full">
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">{{ __('Davis WeatherLink') }}</li>
        </ol>
    </nav>

    <div class="mb-8">
        <div class="flex items-center space-x-4">
            <div class="p-3 rounded-xl bg-amber-100 dark:bg-amber-900/30">
                <svg class="w-8 h-8 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Davis WeatherLink Configuration') }}</h1>
                <p class="text-gray-500 dark:text-gray-400">{{ __('Configure your Davis WeatherLink integration. Select your integration type first; relevant fields will appear below.') }}</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="text-green-800 dark:text-green-200">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    <form action="{{ route('admin.settings.update', 'weatherlink') }}" method="POST" class="space-y-6">
        @csrf

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm divide-y divide-gray-100 dark:divide-gray-700">
            {{-- Integration Type Selector (FIRST) --}}
            <div class="p-5">
                <div class="space-y-2">
                    <label for="weatherlink_type" class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Integration Type') }}</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Choose your WeatherLink integration type first; the corresponding configuration fields will appear below.') }}</p>
                    <select name="weatherlink_type"
                            id="weatherlink_type"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                        <option value="v1" {{ $type === 'v1' ? 'selected' : '' }}>{{ __('WeatherLink Cloud v1 API') }}</option>
                        <option value="v2" {{ $type === 'v2' ? 'selected' : '' }}>{{ __('WeatherLink Cloud v2 API') }}</option>
                        <option value="airlink_local" {{ $type === 'airlink_local' ? 'selected' : '' }}>{{ __('AirLink Local API') }}</option>
                        <option value="wll_local" {{ $type === 'wll_local' ? 'selected' : '' }}>{{ __('WeatherLink Live Local API') }}</option>
                    </select>
                </div>
            </div>

            {{-- Enable/Disable Toggle --}}
            <div class="p-5">
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Enabled') }}</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Enable WeatherLink integration') }}</p>
                    <div class="w-full">
                        <x-toggle-switch
                            :enabled="$enabled"
                            name="weatherlink_enabled"
                            :labelEnabled="__('Enabled')"
                            :labelDisabled="__('Disabled')"
                        />
                    </div>
                </div>
            </div>

            {{-- v1 API Configuration --}}
            <div class="p-5" id="weatherlink-v1-config" style="display: {{ $type === 'v1' ? 'block' : 'none' }};">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('WeatherLink Cloud v1 API Configuration') }}</h3>
                <div class="space-y-4">
                    <div>
                        <label for="weatherlink_device_id" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('Device ID') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Your WeatherLink device ID') }}</p>
                        <input type="text"
                               name="weatherlink_device_id"
                               id="weatherlink_device_id"
                               value="{{ $all['v1']['device_id'] }}"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400"
                               placeholder="Enter device ID" />
                    </div>
                    <div>
                        <label for="wl_v1_pass" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('Password') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Your WeatherLink.com password') }}</p>
                        <input type="text"
                               name="wl_v1_pass"
                               id="wl_v1_pass"
                               autocomplete="off"
                               data-lpignore="true"
                               style="-webkit-text-security: disc; text-security: disc;"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 font-mono"
                               placeholder="{{ $all['v1']['password'] ? __('(configured - enter new value to change)') : __('Enter password') }}" />
                        @if($all['v1']['password'])
                            <p class="mt-1 text-xs text-green-600 dark:text-green-400">{{ __('Configured (leave empty to keep current value)') }}</p>
                        @endif
                    </div>
                    <div>
                        <label for="wl_v1_key" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('API Key') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Your WeatherLink v1 API key') }}</p>
                        <input type="text"
                               name="wl_v1_key"
                               id="wl_v1_key"
                               autocomplete="off"
                               data-lpignore="true"
                               style="-webkit-text-security: disc; text-security: disc;"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 font-mono"
                               placeholder="{{ $all['v1']['api_key'] ? __('(configured - enter new value to change)') : __('Enter API key') }}" />
                        @if($all['v1']['api_key'])
                            <p class="mt-1 text-xs text-green-600 dark:text-green-400">{{ __('Configured (leave empty to keep current value)') }}</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- v2 API Configuration --}}
            <div class="p-5" id="weatherlink-v2-config" style="display: {{ $type === 'v2' ? 'block' : 'none' }};">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('WeatherLink Cloud v2 API Configuration') }}</h3>
                <div class="space-y-4">
                    <div>
                        <label for="wl_v2_key" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('API Key') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Your WeatherLink v2 API key from weatherlink.com/account') }}</p>
                        <input type="text"
                               name="wl_v2_key"
                               id="wl_v2_key"
                               autocomplete="off"
                               data-lpignore="true"
                               style="-webkit-text-security: disc; text-security: disc;"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 font-mono"
                               placeholder="{{ $all['v2']['api_key'] ? __('(configured - enter new value to change)') : __('Enter API key') }}" />
                        @if($all['v2']['api_key'])
                            <p class="mt-1 text-xs text-green-600 dark:text-green-400">{{ __('Configured (leave empty to keep current value)') }}</p>
                        @endif
                    </div>
                    <div>
                        <label for="wl_v2_secret" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('API Secret') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Your WeatherLink v2 API secret from weatherlink.com/account') }}</p>
                        <input type="text"
                               name="wl_v2_secret"
                               id="wl_v2_secret"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 font-mono"
                               placeholder="{{ $all['v2']['api_secret'] ? __('(configured - enter new value to change)') : __('Enter API secret') }}" />
                        @if($all['v2']['api_secret'])
                            <p class="mt-1 text-xs text-green-600 dark:text-green-400">{{ __('Configured (leave empty to keep current value)') }}</p>
                        @endif
                    </div>
                    <div>
                        <label for="weatherlink_station_id" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('Station ID') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Your WeatherLink station ID (UUID or integer). You can find this by calling the /stations endpoint.') }}</p>
                        <input type="text"
                               name="weatherlink_station_id"
                               id="weatherlink_station_id"
                               value="{{ $all['v2']['station_id'] }}"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400"
                               placeholder="Enter station ID" />
                        <button type="button" id="fetch-stations-btn" class="mt-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm transition">
                            {{ __('Fetch Available Stations') }}
                        </button>
                        <div id="stations-result" class="mt-3 hidden"></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('Demo Mode') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Enable demo mode to use Davis Instruments demo station (no credentials required)') }}</p>
                        <x-toggle-switch
                            :enabled="$all['v2']['demo_mode']"
                            name="weatherlink_demo_mode"
                            :labelEnabled="__('Enabled')"
                            :labelDisabled="__('Disabled')"
                        />
                    </div>
                </div>
            </div>

            {{-- AirLink Local Configuration --}}
            <div class="p-5" id="weatherlink-airlink-config" style="display: {{ $type === 'airlink_local' ? 'block' : 'none' }};">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('AirLink Local API Configuration') }}</h3>
                <div class="space-y-4">
                    <div>
                        <label for="weatherlink_airlink_ip" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('IP Address or Hostname') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('IP address or hostname of your AirLink device (e.g., 192.168.1.100 or airlink-100008.local)') }}</p>
                        <input type="text"
                               name="weatherlink_airlink_ip"
                               id="weatherlink_airlink_ip"
                               value="{{ $all['airlink_local']['ip'] }}"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400"
                               placeholder="192.168.1.100" />
                    </div>
                    <div>
                        <label for="weatherlink_airlink_port" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('Port') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('HTTP port (default: 80)') }}</p>
                        <input type="number"
                               name="weatherlink_airlink_port"
                               id="weatherlink_airlink_port"
                               value="{{ $all['airlink_local']['port'] }}"
                               min="1"
                               max="65535"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400" />
                    </div>
                </div>
            </div>

            {{-- WeatherLink Live Local Configuration --}}
            <div class="p-5" id="weatherlink-wll-config" style="display: {{ $type === 'wll_local' ? 'block' : 'none' }};">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('WeatherLink Live Local API Configuration') }}</h3>
                <div class="space-y-4">
                    <div>
                        <label for="weatherlink_wll_ip" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('IP Address or Hostname') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('IP address or hostname of your WeatherLink Live device') }}</p>
                        <input type="text"
                               name="weatherlink_wll_ip"
                               id="weatherlink_wll_ip"
                               value="{{ $all['wll_local']['ip'] }}"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400"
                               placeholder="192.168.1.100" />
                    </div>
                    <div>
                        <label for="weatherlink_wll_port" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('HTTP Port') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('HTTP port for current conditions API (default: 80)') }}</p>
                        <input type="number"
                               name="weatherlink_wll_port"
                               id="weatherlink_wll_port"
                               value="{{ $all['wll_local']['port'] }}"
                               min="1"
                               max="65535"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('Enable UDP Broadcast') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Enable real-time UDP data broadcast') }}</p>
                        <x-toggle-switch
                            :enabled="$all['wll_local']['udp_enabled']"
                            name="weatherlink_wll_udp_enabled"
                            :labelEnabled="__('Enabled')"
                            :labelDisabled="__('Disabled')"
                        />
                    </div>
                    <div id="weatherlink-wll-udp-config" style="display: {{ $all['wll_local']['udp_enabled'] ? 'block' : 'none' }};">
                        <div class="space-y-4">
                            <div>
                                <label for="weatherlink_wll_udp_port" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('UDP Port') }}</label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('UDP broadcast port (default: 22222)') }}</p>
                                <input type="number"
                                       name="weatherlink_wll_udp_port"
                                       id="weatherlink_wll_udp_port"
                                       value="{{ $all['wll_local']['udp_port'] }}"
                                       min="1"
                                       max="65535"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400" />
                            </div>
                            <div>
                                <label for="weatherlink_wll_udp_duration" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('Broadcast Duration') }}</label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Duration in seconds (default: 1200 = 20 minutes, max: 86400 = 24 hours)') }}</p>
                                <input type="number"
                                       name="weatherlink_wll_udp_duration"
                                       id="weatherlink_wll_udp_duration"
                                       value="{{ $all['wll_local']['udp_duration'] }}"
                                       min="1"
                                       max="86400"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Test Connection Button --}}
            <div class="p-5">
                <button type="button" id="test-connection-btn" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-medium rounded-lg transition shadow-sm">
                    {{ __('Test Connection') }}
                </button>
                <div id="test-result" class="mt-4 hidden"></div>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                ← {{ __('Back to Settings') }}
            </a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-medium rounded-lg transition shadow-sm">
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    const typeSelect = document.getElementById('weatherlink_type');
    const v1Config = document.getElementById('weatherlink-v1-config');
    const v2Config = document.getElementById('weatherlink-v2-config');
    const airlinkConfig = document.getElementById('weatherlink-airlink-config');
    const wllConfig = document.getElementById('weatherlink-wll-config');
    const udpConfig = document.getElementById('weatherlink-wll-udp-config');
    const udpEnabled = document.querySelector('input[name="weatherlink_wll_udp_enabled"]');
    const testBtn = document.getElementById('test-connection-btn');
    const testResult = document.getElementById('test-result');

    function updateVisibility() {
        const type = typeSelect.value;
        v1Config.style.display = type === 'v1' ? 'block' : 'none';
        v2Config.style.display = type === 'v2' ? 'block' : 'none';
        airlinkConfig.style.display = type === 'airlink_local' ? 'block' : 'none';
        wllConfig.style.display = type === 'wll_local' ? 'block' : 'none';
    }

    function updateUdpVisibility() {
        const enabled = udpEnabled?.checked || false;
        if (udpConfig) {
            udpConfig.style.display = enabled ? 'block' : 'none';
        }
    }

    typeSelect?.addEventListener('change', updateVisibility);
    udpEnabled?.addEventListener('change', updateUdpVisibility);

    // Test connection
    testBtn?.addEventListener('click', async function() {
        const type = typeSelect.value;
        testResult.classList.add('hidden');
        testBtn.disabled = true;
        testBtn.textContent = '{{ __('Testing...') }}';

        try {
            const response = await fetch('{{ route('admin.settings.test-api') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    service: 'weatherlink',
                    type: type
                })
            });

            // Check if response is OK and is JSON
            if (!response.ok) {
                throw new Error('HTTP ' + response.status + ': ' + response.statusText);
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                throw new Error('Expected JSON but received: ' + contentType + '. Response: ' + text.substring(0, 200));
            }

            const data = await response.json();
            testResult.classList.remove('hidden');
            testResult.className = 'mt-4 p-4 rounded-lg ' + (data.success ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800');
            testResult.innerHTML = '<p class="' + (data.success ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200') + '">' + (data.message || (data.success ? 'Connection successful!' : 'Connection failed!')) + '</p>';
        } catch (error) {
            testResult.classList.remove('hidden');
            testResult.className = 'mt-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800';
            testResult.innerHTML = '<p class="text-red-800 dark:text-red-200">Error testing connection: ' + (error.message || 'Unknown error') + '</p>';
        } finally {
            testBtn.disabled = false;
            testBtn.textContent = '{{ __('Test Connection') }}';
        }
    });

    // Station picker: list the stations this API key can read and fill in the
    // chosen one. Station names come from WeatherLink, so they are inserted as
    // text, never as HTML.
    const stationsBtn = document.getElementById('fetch-stations-btn');
    const stationsResult = document.getElementById('stations-result');
    const stationIdInput = document.getElementById('weatherlink_station_id');

    function showStationsMessage(message, ok) {
        stationsResult.replaceChildren();
        stationsResult.classList.remove('hidden');
        const p = document.createElement('p');
        p.className = 'text-sm ' + (ok ? 'text-gray-700 dark:text-gray-300' : 'text-red-700 dark:text-red-300');
        p.textContent = message;
        stationsResult.appendChild(p);
    }

    stationsBtn?.addEventListener('click', async function () {
        stationsBtn.disabled = true;
        stationsBtn.textContent = '{{ __('Fetching...') }}';

        try {
            const response = await fetch('{{ route('admin.settings.weatherlink.stations') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    api_key: document.getElementById('wl_v2_key')?.value || '',
                    api_secret: document.getElementById('wl_v2_secret')?.value || ''
                })
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const data = await response.json();
            showStationsMessage(data.message || '', data.success);

            const list = document.createElement('ul');
            list.className = 'mt-2 divide-y divide-gray-200 dark:divide-gray-700 border border-gray-200 dark:border-gray-700 rounded-lg';

            (data.stations || []).forEach(function (station) {
                const id = station.station_id_uuid || station.station_id;

                const item = document.createElement('li');
                item.className = 'flex items-center justify-between gap-3 p-3';

                const info = document.createElement('div');
                info.className = 'min-w-0';
                const name = document.createElement('p');
                name.className = 'font-medium text-gray-900 dark:text-white truncate';
                name.textContent = station.name || ('{{ __('Station') }} ' + station.station_id);
                const meta = document.createElement('p');
                meta.className = 'text-xs text-gray-500 dark:text-gray-400 break-all';
                meta.textContent = [station.location, 'ID ' + station.station_id, station.active ? null : '{{ __('inactive') }}']
                    .filter(Boolean).join(' · ');
                info.append(name, meta);

                const use = document.createElement('button');
                use.type = 'button';
                use.className = 'shrink-0 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm transition';
                use.textContent = '{{ __('Use') }}';
                use.addEventListener('click', function () {
                    stationIdInput.value = id;
                    stationIdInput.focus();
                    showStationsMessage('{{ __('Station ID filled in. Click Save Changes to keep it.') }}', true);
                });

                item.append(info, use);
                list.appendChild(item);
            });

            if (list.children.length) {
                stationsResult.appendChild(list);
            }
        } catch (error) {
            showStationsMessage('{{ __('Could not fetch stations:') }} ' + (error.message || 'unknown error'), false);
        } finally {
            stationsBtn.disabled = false;
            stationsBtn.textContent = '{{ __('Fetch Available Stations') }}';
        }
    });

    // Initialize visibility
    updateVisibility();
    updateUdpVisibility();
})();
</script>
@endpush
@endsection
