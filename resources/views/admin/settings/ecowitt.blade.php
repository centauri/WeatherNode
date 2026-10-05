@extends('layouts.admin')

@section('title', __($groupInfo['label']))

@section('content')
@php
    $source = $ecowitt['source'];
    $selected = old('ecowitt_source', $source ?? 'keep');
    $push = $ecowitt['push'];
    $cloud = $ecowitt['cloud'];
    $input = 'w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400';
    $card = 'bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700';
    $families = [
        'temps' => [__('Temperature and humidity'), __('WH31, WN31 and similar, channels 1 to 8')],
        'soil' => [__('Soil moisture'), __('WH51, channels 1 to 8')],
        'pm25' => [__('Air quality (PM2.5)'), __('WH41 and WH43, channels 1 to 4')],
        'leak' => [__('Water leak'), __('WH55, channels 1 to 4')],
        'co2' => [__('CO2'), __('WH45 or WH46')],
    ];
    $sources = [
        'push' => [__('Push from the console'), __('Your console or gateway sends each reading to this site. Fastest, and needs no Ecowitt account.')],
        'cloud' => [__('Ecowitt cloud'), __('This site fetches your readings from ecowitt.net every minute. Works when the console cannot reach this site.')],
        'file' => [__('Local file'), __('Another program writes your readings to a file on this server, for example an old PWS Dashboard set-up.')],
    ];
@endphp

<div class="w-full" x-data="{ source: @js($selected) }">
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">{{ __($groupInfo['label']) }}</li>
        </ol>
    </nav>

    <div class="mb-8 flex items-center space-x-4">
        <div class="p-3 rounded-xl bg-{{ $groupInfo['color'] }}-100 dark:bg-{{ $groupInfo['color'] }}-900/30">
            <svg class="w-8 h-8 text-{{ $groupInfo['color'] }}-600 dark:text-{{ $groupInfo['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
            </svg>
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __($groupInfo['label']) }}</h1>
            <p class="text-gray-500 dark:text-gray-400">{{ __('How your Ecowitt station sends data, and what its sensors are called.') }}</p>
        </div>
    </div>

    <div class="mb-6 p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 text-sm text-gray-600 dark:text-gray-300">
        <p class="font-medium text-gray-900 dark:text-white mb-1">{{ __('Not branded Ecowitt? This page is still for you.') }}</p>
        <p>
            {{ __('Ecowitt stations are made by Fine Offset, which also sells them under other names, such as Froggit, Sainlogic, Misol, Aercus, ELV, Pantech and Steinberg Systems. If your console or gateway works with the WS View Plus or Ecowitt app, or has a Customized upload, use these settings.') }}
            {{ __('Ambient Weather stations are Fine Offset too, but have their own cloud: use the Ambient Weather source for that, or these settings for a Customized upload.') }}
        </p>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-green-800 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-red-800 dark:text-red-200">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl">
            <ul class="list-disc list-inside text-sm text-red-800 dark:text-red-200 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.settings.update', $group) }}" method="POST" class="space-y-6">
        @csrf

        {{-- 1. Connection --}}
        <section class="{{ $card }}">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">1. {{ __('How does your station send data?') }}</h2>
                @if($source === null)
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                        {{ __('Your live data comes from another source (:source). Pick an Ecowitt option only if you want to switch to Ecowitt.', ['source' => $ecowitt['liveFormat']]) }}
                    </p>
                @endif
            </div>

            <div class="p-5 grid gap-3 md:grid-cols-3">
                @foreach($sources as $value => [$title, $hint])
                    <label class="relative flex cursor-pointer rounded-lg border p-4 transition"
                           :class="source === '{{ $value }}' ? 'border-blue-500 ring-2 ring-blue-500 bg-blue-50/50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'">
                        <input type="radio" name="ecowitt_source" value="{{ $value }}" class="sr-only" x-model="source" {{ $selected === $value ? 'checked' : '' }}>
                        <span>
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ $title }}</span>
                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @if($source === null)
                <div class="px-5 -mt-2 pb-4">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="radio" name="ecowitt_source" value="keep" x-model="source" {{ $selected === 'keep' ? 'checked' : '' }} class="text-blue-600">
                        {{ __('Keep my current live data source') }}
                    </label>
                </div>
            @endif

            {{-- Push --}}
            <div x-show="source === 'push'" x-cloak class="p-5 border-t border-gray-100 dark:border-gray-700 space-y-5">
                <div class="p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-sm text-blue-900 dark:text-blue-100">
                    <p class="font-semibold mb-2">{{ __('Set up your console') }}</p>
                    <p class="mb-2">{{ __('In the WS View Plus or Ecowitt app, open your gateway, go to Weather Services, choose Customized and fill in:') }}</p>
                    <dl class="grid grid-cols-[auto,1fr] gap-x-4 gap-y-1 font-mono text-xs">
                        <dt class="text-blue-700 dark:text-blue-300">{{ __('Protocol') }}</dt><dd>Ecowitt</dd>
                        <dt class="text-blue-700 dark:text-blue-300">{{ __('Server') }}</dt><dd>{{ $push['host'] }}</dd>
                        <dt class="text-blue-700 dark:text-blue-300">{{ __('Path') }}</dt><dd id="ecowitt_endpoint_preview">{{ $push['endpoint'] }}</dd>
                        <dt class="text-blue-700 dark:text-blue-300">{{ __('Port') }}</dt><dd>{{ $push['port'] }}</dd>
                        <dt class="text-blue-700 dark:text-blue-300">{{ __('Interval') }}</dt><dd>60</dd>
                    </dl>
                </div>

                <div>
                    <label for="ecowitt_passkey" class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Passkey') }}</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Optional. Only readings carrying this passkey are stored. Leave empty to accept any.') }}</p>
                    <input type="text" name="ecowitt_passkey" id="ecowitt_passkey" value="{{ old('ecowitt_passkey', $push['passkey']) }}" class="{{ $input }} font-mono" autocomplete="off">
                </div>

                <details class="rounded-lg border border-gray-200 dark:border-gray-700" {{ $push['secureMode'] || $push['ipFilterEnabled'] || $push['nameFilterEnabled'] ? 'open' : '' }}>
                    <summary class="cursor-pointer select-none p-4 text-sm font-medium text-gray-900 dark:text-white">{{ __('Extra security') }}</summary>
                    <div class="p-4 pt-0 space-y-5">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Secure push mode') }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Only accept readings sent to a secret path, with the passkey above.') }}</p>
                                </div>
                                <x-toggle-switch :enabled="$push['secureMode']" name="ecowitt_secure_mode" :showLabel="false" />
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="text" name="ecowitt_secure_token" id="ecowitt_secure_token" value="{{ old('ecowitt_secure_token', $push['secureToken']) }}" class="{{ $input }} font-mono" placeholder="{{ __('Secret path token') }}" autocomplete="off">
                                <button type="button" id="ecowitt_generate_token" class="shrink-0 px-3 py-2 text-xs font-medium rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600">{{ __('Generate') }}</button>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Limit by source IP') }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('One IP address or CIDR range per line.') }}</p>
                                </div>
                                <x-toggle-switch :enabled="$push['ipFilterEnabled']" name="ecowitt_ip_filter_enabled" :showLabel="false" />
                            </div>
                            <textarea name="ecowitt_ip_allowlist" rows="3" class="{{ $input }} font-mono text-sm" placeholder="203.0.113.10&#10;198.51.100.0/24">{{ old('ecowitt_ip_allowlist', $push['ipAllowlist']) }}</textarea>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Limit by station name or model') }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('One per line, matched anywhere in the name, type or model.') }}</p>
                                </div>
                                <x-toggle-switch :enabled="$push['nameFilterEnabled']" name="ecowitt_name_filter_enabled" :showLabel="false" />
                            </div>
                            <textarea name="ecowitt_name_allowlist" rows="2" class="{{ $input }} text-sm" placeholder="GW2000&#10;WS3900">{{ old('ecowitt_name_allowlist', $push['nameAllowlist']) }}</textarea>
                        </div>
                    </div>
                </details>
            </div>

            {{-- Cloud --}}
            <div x-show="source === 'cloud'" x-cloak class="p-5 border-t border-gray-100 dark:border-gray-700 space-y-5">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ __('Create both keys on ecowitt.net under your account, Private Center, API Keys.') }}
                    <a href="https://www.ecowitt.net/home/user" target="_blank" rel="noopener" class="text-blue-600 dark:text-blue-400 hover:underline">ecowitt.net</a>
                </p>

                <div class="grid gap-4 md:grid-cols-2">
                    @foreach(['application_key' => [__('Application key'), $cloud['hasApplicationKey']], 'api_key' => [__('API key'), $cloud['hasApiKey']]] as $field => [$label, $has])
                        <div>
                            <label for="ecowitt_{{ $field }}" class="block text-sm font-medium text-gray-900 dark:text-white mb-1">{{ $label }}</label>
                            <input type="text" name="ecowitt_{{ $field }}" id="ecowitt_{{ $field }}" autocomplete="off" data-lpignore="true"
                                   style="-webkit-text-security: disc; text-security: disc;"
                                   placeholder="{{ $has ? __('Saved. Type a new one to change it.') : '' }}"
                                   class="{{ $input }} font-mono">
                            @if($has)
                                <p class="mt-1 text-xs text-green-600 dark:text-green-400">{{ __('Saved') }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div>
                    <label for="ecowitt_mac_address" class="block text-sm font-medium text-gray-900 dark:text-white mb-1">{{ __('Station MAC address') }}</label>
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="text" name="ecowitt_mac_address" id="ecowitt_mac_address" value="{{ old('ecowitt_mac_address', $cloud['mac']) }}"
                               class="{{ $input }} font-mono md:max-w-xs" placeholder="AA:BB:CC:DD:EE:FF" autocomplete="off">
                        <button type="button" id="ecowitt-find-stations" class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-600">
                            {{ __('Find my stations') }}
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Find my stations lists the stations on your Ecowitt account, using the keys above.') }}</p>
                    <div id="ecowitt-stations" class="mt-3 hidden"></div>
                    <input type="hidden" name="ecowitt_apply_location" id="ecowitt_apply_location" value="0">
                    <input type="hidden" name="ecowitt_station_latitude" id="ecowitt_station_latitude">
                    <input type="hidden" name="ecowitt_station_longitude" id="ecowitt_station_longitude">
                    <input type="hidden" name="ecowitt_station_timezone" id="ecowitt_station_timezone">
                </div>

                @if($cloud['hasApplicationKey'] && $cloud['hasApiKey'] && $cloud['mac'] !== '')
                    <p id="ecowitt-cloud-status" class="text-sm text-gray-600 dark:text-gray-300" data-url="{{ route('admin.settings.ecowitt.status') }}">
                        <span class="inline-block w-2 h-2 rounded-full bg-gray-400 mr-2 align-middle" data-dot></span><span data-text>{{ __('Checking the station...') }}</span>
                    </p>
                @endif

                <details class="text-sm">
                    <summary class="cursor-pointer select-none text-gray-600 dark:text-gray-300">{{ __('Advanced') }}</summary>
                    <div class="mt-3">
                        <label for="ecowitt_api_base_url" class="block text-sm font-medium text-gray-900 dark:text-white mb-1">{{ __('API address') }}</label>
                        <input type="url" name="ecowitt_api_base_url" id="ecowitt_api_base_url" value="{{ old('ecowitt_api_base_url', $cloud['baseUrl']) }}" class="{{ $input }} font-mono text-sm">
                    </div>
                </details>
            </div>

            {{-- File --}}
            <div x-show="source === 'file'" x-cloak class="p-5 border-t border-gray-100 dark:border-gray-700">
                <label for="ecowitt_local_file" class="block text-sm font-medium text-gray-900 dark:text-white mb-1">{{ __('File path') }}</label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Relative to the WeatherNode folder, or a full path. The file holds the same fields a push does.') }}</p>
                <input type="text" name="ecowitt_local_file" id="ecowitt_local_file" value="{{ old('ecowitt_local_file', $ecowitt['localFile']) }}" class="{{ $input }} font-mono">
            </div>

            <div class="p-5 border-t border-gray-100 dark:border-gray-700 flex flex-wrap items-center gap-3" x-show="source !== 'keep'">
                <button type="button" id="test-connection-btn" class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-600">
                    {{ __('Test connection') }}
                </button>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Tests the saved settings. Save first after a change.') }}</span>
                <div id="test-result" class="w-full hidden"></div>
            </div>
        </section>

        {{-- 2. Rain gauge --}}
        @if($ecowitt['rainGauge'])
            <section class="{{ $card }} p-5">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">2. {{ __('Rain gauge') }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">{{ __($ecowitt['rainGauge']->description) }}</p>
                <select name="ecowitt_rain_gauge" id="ecowitt_rain_gauge" class="{{ $input }} md:max-w-md">
                    @foreach($ecowitt['rainGauge']->getOptionsArray() as $value => $label)
                        <option value="{{ $value }}" @selected((string) $ecowitt['rainGauge']->value === (string) $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </section>
        @endif

        {{-- 3. Sensor names --}}
        <section class="{{ $card }}" x-data="{ all: false }">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">3. {{ __('Sensor names') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Give extra sensors a name, such as Greenhouse or Pond. The latest reading is shown so you can tell the channels apart.') }}
                    </p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" x-model="all" class="rounded text-blue-600">
                    {{ __('Show unused channels') }}
                </label>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($families as $family => [$title, $hint])
                    @php
                        $rows = $ecowitt['channels'][$family] ?? [];
                        $used = collect($rows)->filter(fn ($r) => $r['reading'] !== null)->count();
                    @endphp
                    <div class="p-5" @if($used === 0) x-show="all" x-cloak @endif>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $title }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ $hint }}</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach($rows as $row)
                                <div class="flex items-center gap-3" @if($row['reading'] === null) x-show="all" x-cloak @endif>
                                    <label for="{{ $row['field'] }}" class="w-14 shrink-0 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $row['channel'] ? __('Ch :n', ['n' => $row['channel']]) : '' }}
                                    </label>
                                    <input type="text" name="{{ $row['field'] }}" id="{{ $row['field'] }}" value="{{ old($row['field'], $row['label']) }}" maxlength="50" class="{{ $input }} text-sm">
                                    <span class="w-24 shrink-0 text-right text-sm tabular-nums {{ $row['reading'] === null ? 'text-gray-400 dark:text-gray-500' : 'text-gray-900 dark:text-white' }}">
                                        {{ $row['reading'] ?? __('no data') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- 4. Gap filling --}}
        @php($backfill = $ecowitt['backfill'])
        <section class="{{ $card }} p-5 space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">4. {{ __('Fill gaps from the Ecowitt cloud') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('When this site was down or the push stopped, the missing readings are fetched from the Ecowitt cloud every hour. Works with any source once the cloud keys and MAC address above are set.') }}
                    </p>
                </div>
                <x-toggle-switch :enabled="$backfill['enabled']" name="ecowitt_backfill_enabled" :showLabel="false" />
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <label for="ecowitt_backfill_days" class="text-sm text-gray-700 dark:text-gray-300">{{ __('Look back') }}</label>
                <input type="number" name="ecowitt_backfill_days" id="ecowitt_backfill_days" min="1" max="{{ $backfill['maxDays'] }}"
                       value="{{ old('ecowitt_backfill_days', $backfill['days']) }}" class="{{ $input }} w-24">
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('days (up to :max)', ['max' => $backfill['maxDays']]) }}</span>
                <button type="submit" form="ecowitt-backfill-form" class="ml-auto px-4 py-2 text-sm font-medium rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-600">
                    {{ __('Fill gaps now') }}
                </button>
            </div>
            @if($backfill['lastRun'])
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Last run :ago: :gaps gaps, :count readings added.', [
                        'ago' => \Carbon\Carbon::parse($backfill['lastRun']['at'])->diffForHumans(),
                        'gaps' => $backfill['lastRun']['gaps'],
                        'count' => $backfill['lastRun']['inserted'],
                    ]) }}
                    @if($backfill['lastRun']['error'])
                        <span class="text-red-600 dark:text-red-400">{{ $backfill['lastRun']['error'] }}</span>
                    @endif
                </p>
            @endif
        </section>

        <div class="flex items-center justify-between">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">&larr; {{ __('Back to Settings') }}</a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-medium rounded-lg transition shadow-sm">{{ __('Save Changes') }}</button>
        </div>
    </form>

    <form id="ecowitt-backfill-form" action="{{ route('admin.settings.ecowitt.backfill') }}" method="POST" class="hidden">@csrf</form>

    {{-- Import --}}
    <details class="mt-8 {{ $card }}">
        <summary class="cursor-pointer select-none p-5 text-lg font-semibold text-gray-900 dark:text-white">{{ __('Import an old .arr file') }}</summary>
        <div class="px-5 pb-5">
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                {{ __('Import data from an old PWS Dashboard / Ecowitt setup. Upload the .arr file that was generated by the previous installation.') }}
                {{ __('This imports a single data snapshot containing the latest reading from your old setup. It will be stored as a new weather reading.') }}
            </p>
            <form action="{{ route('admin.settings.ecowitt.import') }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="file" name="arr_file" id="arr_file" accept=".arr" required
                       class="text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-green-50 file:text-green-700 dark:file:bg-green-900/30 dark:file:text-green-400 cursor-pointer">
                <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors">{{ __('Import Data') }}</button>
            </form>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Accepted: .arr files up to 2 MB') }}</p>
        </div>
    </details>
</div>

@push('scripts')
<script>
(function () {
    const csrf = '{{ csrf_token() }}';
    const post = (url, body) => fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(body),
    }).then((r) => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); });

    function message(box, text, ok) {
        box.replaceChildren();
        box.classList.remove('hidden');
        const p = document.createElement('p');
        p.className = 'text-sm p-3 rounded-lg ' + (ok
            ? 'bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200'
            : 'bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200');
        p.textContent = text;
        box.appendChild(p);
    }

    // Secure push path: keep the preview in step and offer a random token.
    const token = document.getElementById('ecowitt_secure_token');
    const preview = document.getElementById('ecowitt_endpoint_preview');
    const secure = document.querySelector('input[name="ecowitt_secure_mode"]');
    function updatePreview() {
        if (!token || !preview) return;
        token.value = token.value.replace(/[^A-Za-z0-9_-]/g, '');
        const on = secure && secure.value === '1' && token.value !== '';
        preview.textContent = '/api/ecowitt/receive' + (on ? '/' + token.value : '');
    }
    token?.addEventListener('input', updatePreview);
    document.addEventListener('click', () => setTimeout(updatePreview, 0));
    document.getElementById('ecowitt_generate_token')?.addEventListener('click', () => {
        const bytes = window.crypto.getRandomValues(new Uint8Array(16));
        token.value = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
        updatePreview();
    });

    // Find my stations. Names come from Ecowitt, so they go in as text.
    const findBtn = document.getElementById('ecowitt-find-stations');
    const list = document.getElementById('ecowitt-stations');
    const mac = document.getElementById('ecowitt_mac_address');
    findBtn?.addEventListener('click', async () => {
        findBtn.disabled = true;
        try {
            const data = await post('{{ route('admin.settings.ecowitt.stations') }}', {
                application_key: document.getElementById('ecowitt_application_key')?.value || '',
                api_key: document.getElementById('ecowitt_api_key')?.value || '',
            });
            message(list, data.message || '', data.success);
            const ul = document.createElement('ul');
            ul.className = 'mt-2 divide-y divide-gray-200 dark:divide-gray-700 border border-gray-200 dark:border-gray-700 rounded-lg';
            (data.stations || []).forEach((station) => {
                const li = document.createElement('li');
                li.className = 'p-3 space-y-2';
                const head = document.createElement('div');
                head.className = 'flex items-center justify-between gap-3';
                const info = document.createElement('div');
                info.className = 'min-w-0';
                const name = document.createElement('p');
                name.className = 'font-medium text-gray-900 dark:text-white truncate';
                name.textContent = station.name || station.mac;
                const meta = document.createElement('p');
                meta.className = 'text-xs text-gray-500 dark:text-gray-400 break-all';
                meta.textContent = [station.mac, station.model, station.timezone].filter(Boolean).join(' · ');
                info.append(name, meta);
                const use = document.createElement('button');
                use.type = 'button';
                use.className = 'shrink-0 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm';
                use.textContent = '{{ __('Use') }}';
                head.append(info, use);
                li.appendChild(head);

                let locationBox = null;
                if (station.latitude !== null && station.longitude !== null && station.timezone) {
                    const label = document.createElement('label');
                    label.className = 'flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300';
                    locationBox = document.createElement('input');
                    locationBox.type = 'checkbox';
                    locationBox.className = 'rounded text-blue-600';
                    const text = document.createElement('span');
                    text.textContent = '{{ __('Also use its location and time zone for my station') }} (' +
                        station.latitude + ', ' + station.longitude + ', ' + station.timezone + ')';
                    label.append(locationBox, text);
                    li.appendChild(label);
                }

                use.addEventListener('click', () => {
                    mac.value = station.mac;
                    const apply = locationBox && locationBox.checked;
                    document.getElementById('ecowitt_apply_location').value = apply ? '1' : '0';
                    document.getElementById('ecowitt_station_latitude').value = apply ? station.latitude : '';
                    document.getElementById('ecowitt_station_longitude').value = apply ? station.longitude : '';
                    document.getElementById('ecowitt_station_timezone').value = apply ? station.timezone : '';
                    message(list, '{{ __('Filled in. Click Save Changes to keep it.') }}', true);
                });
                ul.appendChild(li);
            });
            if (ul.children.length) list.appendChild(ul);
        } catch (error) {
            message(list, '{{ __('Could not fetch stations:') }} ' + error.message, false);
        } finally {
            findBtn.disabled = false;
        }
    });

    // Cloud station status.
    const status = document.getElementById('ecowitt-cloud-status');
    if (status) {
        fetch(status.dataset.url, { headers: { 'Accept': 'application/json' } })
            .then((r) => r.json())
            .then((data) => {
                status.querySelector('[data-text]').textContent = data.message || '';
                status.querySelector('[data-dot]').className = 'inline-block w-2 h-2 rounded-full mr-2 align-middle ' +
                    (data.success && data.online ? 'bg-green-500' : data.success && data.online === false ? 'bg-red-500' : 'bg-gray-400');
            })
            .catch(() => { status.querySelector('[data-text]').textContent = '{{ __('Could not reach the Ecowitt cloud.') }}'; });
    }

    // Test the saved connection.
    const testBtn = document.getElementById('test-connection-btn');
    const testResult = document.getElementById('test-result');
    testBtn?.addEventListener('click', async () => {
        testBtn.disabled = true;
        try {
            const data = await post('{{ route('admin.settings.test-api') }}', { service: 'ecowitt' });
            message(testResult, data.message || '', data.success);
        } catch (error) {
            message(testResult, error.message, false);
        } finally {
            testBtn.disabled = false;
        }
    });
})();
</script>
@endpush
@endsection
