@extends('layouts.admin')

@section('title', __($groupInfo['label']))

@section('content')
@php
    $input = 'w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400';
    $card = 'bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700';
    $label = 'block text-sm font-medium text-gray-900 dark:text-white mb-1';
    $hint = 'text-xs text-gray-500 dark:text-gray-400 mb-2';
@endphp

<div class="w-full">
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">{{ __($groupInfo['label']) }}</li>
        </ol>
    </nav>

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __($groupInfo['label']) }}</h1>
        <p class="text-gray-500 dark:text-gray-400">{{ __('Read a Netatmo weather station: your own, or a public one you marked as favorite.') }}</p>
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

    <div class="mb-6 p-4 rounded-xl text-sm {{ $netatmo['connected'] ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200' : 'bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300' }}">
        <p class="font-medium">{{ $netatmo['connected'] ? __('Connected to Netatmo.') : __('Not connected to Netatmo yet.') }}</p>
        @if($netatmo['lastError'] !== '')
            <p class="mt-1 text-red-700 dark:text-red-300">{{ __('Last error:') }} {{ $netatmo['lastError'] }}</p>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.settings.update', 'netatmo') }}" class="space-y-6">
        @csrf

        <div class="{{ $card }} p-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">{{ __('1. Your Netatmo app') }}</h2>
            <ol class="list-decimal list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1 mb-4">
                <li>{!! __('Log in at :link and create an app. Any name works.', ['link' => '<a href="https://dev.netatmo.com/apps/" target="_blank" rel="noopener" class="text-blue-600 dark:text-blue-400 underline">dev.netatmo.com</a>']) !!}</li>
                <li>{{ __('Enter this as the redirect URI of the app:') }} <code class="px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-900 break-all">{{ $netatmo['redirectUri'] }}</code></li>
                <li>{{ __('Copy the client ID and client secret of the app here and save.') }}</li>
            </ol>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="netatmo_client_id" class="{{ $label }}">{{ __('Client ID') }}</label>
                    <input type="text" name="netatmo_client_id" id="netatmo_client_id" value="{{ old('netatmo_client_id', $netatmo['clientId']) }}" class="{{ $input }}" autocomplete="off">
                </div>
                <div>
                    <label for="netatmo_client_secret" class="{{ $label }}">{{ __('Client secret') }}</label>
                    <input type="password" name="netatmo_client_secret" id="netatmo_client_secret" value="" class="{{ $input }}" autocomplete="new-password"
                           placeholder="{{ $netatmo['hasSecret'] ? __('(configured - enter new value to change)') : '' }}">
                </div>
            </div>
        </div>

        <div class="{{ $card }} p-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">{{ __('2. Connect') }}</h2>
            <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">{{ __('Save the app first. Then log in at Netatmo and allow WeatherNode to read your weather station.') }}</p>
            @if($netatmo['clientId'] !== '' && $netatmo['hasSecret'])
                <a href="{{ route('admin.settings.netatmo.connect') }}" class="inline-block px-5 py-2.5 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-medium rounded-lg">
                    {{ $netatmo['connected'] ? __('Connect again') : __('Connect Netatmo') }}
                </a>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Enter the client ID and client secret of your Netatmo app first.') }}</p>
            @endif

            <div class="mt-5">
                <label for="netatmo_refresh_token" class="{{ $label }}">{{ __('Or paste a refresh token') }}</label>
                <p class="{{ $hint }}">{{ __('Made with the token generator on your app page at dev.netatmo.com, with the read_station scope.') }}</p>
                <input type="password" name="netatmo_refresh_token" id="netatmo_refresh_token" value="" class="{{ $input }}" autocomplete="new-password">
            </div>
        </div>

        <div class="{{ $card }} p-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">{{ __('3. Station') }}</h2>
            @if(!$netatmo['connected'])
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Connect first to see your stations.') }}</p>
            @elseif($netatmo['stations'] === [])
                <p class="text-sm text-gray-500 dark:text-gray-400">{!! __('No stations found. To use a public station, mark it as favorite on :link.', ['link' => '<a href="https://weathermap.netatmo.com" target="_blank" rel="noopener" class="text-blue-600 dark:text-blue-400 underline">weathermap.netatmo.com</a>']) !!}</p>
            @else
                <label for="netatmo_device_id" class="{{ $label }}">{{ __('Station to read') }}</label>
                <p class="{{ $hint }}">{!! __('Your own stations come first. Public stations you marked as favorite on :link are listed too.', ['link' => '<a href="https://weathermap.netatmo.com" target="_blank" rel="noopener" class="text-blue-600 dark:text-blue-400 underline">weathermap.netatmo.com</a>']) !!}</p>
                <select name="netatmo_device_id" id="netatmo_device_id" class="{{ $input }}">
                    @foreach($netatmo['stations'] as $station)
                        <option value="{{ $station['id'] }}" {{ $netatmo['deviceId'] === $station['id'] ? 'selected' : '' }}>
                            {{ $station['name'] }}{{ $station['city'] !== '' ? ', ' . $station['city'] : '' }}{{ $station['favorite'] ? ' (' . __('favorite') . ')' : '' }}
                        </option>
                    @endforeach
                </select>
            @endif
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                {!! __('To show this station on the dashboard, pick Netatmo on the :link page.', ['link' => '<a href="' . e(route('admin.settings.group', 'livedata')) . '" class="text-blue-600 dark:text-blue-400 underline">' . e(__('Live Data Source')) . '</a>']) !!}
                {{ __('Netatmo stations send new readings about every 10 minutes.') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-medium rounded-lg shadow-sm">{{ __('Save Changes') }}</button>
            <button type="button" id="netatmo-test" class="px-6 py-2.5 bg-white hover:bg-gray-50 dark:bg-gray-700 dark:hover:bg-gray-600 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white font-medium rounded-lg shadow-sm">{{ __('Test Connection') }}</button>
            <span id="netatmo-test-result" class="text-sm"></span>
        </div>
    </form>

    @if($netatmo['connected'])
        <form method="POST" action="{{ route('admin.settings.netatmo.disconnect') }}" class="mt-6">
            @csrf
            <button type="submit" class="text-sm text-red-600 dark:text-red-400 hover:underline">{{ __('Disconnect from Netatmo') }}</button>
        </form>
    @endif
</div>

@push('scripts')
<script>
(function () {
    const button = document.getElementById('netatmo-test');
    const result = document.getElementById('netatmo-test-result');
    button?.addEventListener('click', async function () {
        result.textContent = @json(__('Testing...'));
        result.className = 'text-sm text-gray-500';
        try {
            const response = await fetch(@json(route('admin.settings.test-api')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                body: JSON.stringify({ service: 'netatmo' }),
            });
            const data = await response.json();
            result.textContent = data.message || '';
            result.className = 'text-sm ' + (data.success ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300');
        } catch (error) {
            result.textContent = error.message;
            result.className = 'text-sm text-red-700 dark:text-red-300';
        }
    });
})();
</script>
@endpush
@endsection
