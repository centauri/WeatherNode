@extends('layouts.admin')

@section('title', __('Community Telemetry'))

@section('content')
<div class="w-full" x-data="telemetrySettings()">
    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">{{ __('Community Telemetry') }}</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-4">
            <div class="p-3 rounded-xl bg-indigo-100 dark:bg-indigo-900/30">
                <svg class="w-8 h-8 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Community Telemetry') }}</h1>
                <p class="text-gray-500 dark:text-gray-400">{{ __('Share your station on the community map') }}</p>
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

    <!-- Privacy Notice -->
    <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl">
        <div class="flex items-start">
            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="font-medium text-blue-800 dark:text-blue-200 mb-1">{{ __('Privacy Notice') }}</p>
                <p class="text-sm text-blue-700 dark:text-blue-300">
                    {{ __('When enabled, your station data (name, hardware, and server URL) will be publicly visible on the community map.') }}
                    {{ __('Your exact location is never shared: coordinates are randomly offset by up to 100m on each upload, and the map adds additional jitter on every view.') }}
                    {{ __('This helps others discover weather stations around the world. You can disable this at any time.') }}
                </p>
            </div>
        </div>
    </div>

    <form action="{{ route('admin.settings.telemetry.update') }}" method="POST" class="space-y-6">
        @csrf
        
        <!-- Enable Toggle -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <label class="block text-lg font-semibold text-gray-900 dark:text-white mb-1">{{ __('Enable Community Telemetry') }}</label>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Share your station on the community map') }}</p>
                    </div>
                    <x-toggle-switch
                        :enabled="$settings['enabled']"
                        name="telemetry_enabled"
                        :labelEnabled="__('Enabled')"
                        :labelDisabled="__('Disabled')"
                    />
                </div>
            </div>
        </div>

        <!-- Station Data Preview -->
        @if($stationData)
        @php
            // The stored value is a key like "davis". Show the name.
            $makerLabels = \App\Models\Setting::find('station.manufacturer')?->getOptionsArray() ?? [];
            $makerLabel = $makerLabels[$stationData['manufacturer']] ?? $stationData['manufacturer'];
        @endphp
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Your Station Data') }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('This is what will be shared (if enabled)') }}</p>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('Station Name') }}:</span>
                        <span class="ml-2 font-medium text-gray-900 dark:text-white">{{ $stationData['name'] }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('Hardware') }}:</span>
                        <span class="ml-2 font-medium text-gray-900 dark:text-white">{{ $stationData['hardware'] ?: __('N/A') }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('Manufacturer') }}:</span>
                        <span class="ml-2 font-medium text-gray-900 dark:text-white">{{ $makerLabel ?: __('N/A') }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('Country') }}:</span>
                        <span class="ml-2 font-medium text-gray-900 dark:text-white">{{ $stationData['country_code'] ?: __('N/A') }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('Location') }}:</span>
                        <span class="ml-2 font-medium text-gray-900 dark:text-white">{{ number_format($stationData['latitude'], 4) }}, {{ number_format($stationData['longitude'], 4) }}</span>
                    </div>
                    <div class="md:col-span-2">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('Server URL') }}:</span>
                        <span class="ml-2 font-medium text-gray-900 dark:text-white break-all">{{ $stationData['url'] }}</span>
                    </div>
                </div>
                @php($localWarning = \App\Services\Telemetry\TelemetryService::localAddressWarning((string) $stationData['url']))
                @if($localWarning)
                    <div class="mt-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-800 dark:text-amber-200">
                        {{ __('Your station is shared with a local address. Visitors cannot open it, so the community map shows it as local only. You can still share it.') }}
                        <a href="{{ route('admin.settings.group', 'station') }}" class="font-medium underline">{{ __('Set a public Server URL in Station settings') }}</a>
                    </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Preserve current aggregator & repo settings (hidden) -->
        <input type="hidden" name="aggregator_url" value="{{ $settings['aggregator_url'] }}">
        <input type="hidden" name="github_repo" value="{{ $settings['github_repo'] }}">
        <input type="hidden" name="github_file" value="{{ $settings['github_file'] }}">

        <!-- Last Update Info -->
        @if($settings['last_updated'])
        <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                <span class="font-medium">{{ __('Last updated') }}:</span> 
                {{ \Carbon\Carbon::parse($settings['last_updated'])->format('Y-m-d H:i:s') }}
            </p>
        </div>
        @endif

        <!-- Actions -->
        <div class="flex items-center justify-between">
            <a href="{{ route('weather.community-stations') }}" 
               target="_blank"
               class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                {{ __('View Community Map') }} →
            </a>
            <div class="flex space-x-3">
                <button type="button" 
                        @click="updateNow()"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                    {{ __('Update Now') }}
                </button>
                <button type="submit" 
                        class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                    {{ __('Save Settings') }}
                </button>
            </div>
        </div>
    </form>
</div>

<script>
const telemetryI18n = {
    updatedSuccess: @json(__('Station data updated successfully!')),
    errorPrefix: @json(__('Error:')),
    errorUpdating: @json(__('Error updating:'))
};

function telemetrySettings() {
    return {
        updateNow() {
            fetch('{{ route("admin.settings.telemetry.update-now") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json',
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(telemetryI18n.updatedSuccess);
                    location.reload();
                } else {
                    alert(telemetryI18n.errorPrefix + ' ' + data.message);
                }
            })
            .catch(error => {
                alert(telemetryI18n.errorUpdating + ' ' + error.message);
            });
        }
    }
}
</script>
@endsection
