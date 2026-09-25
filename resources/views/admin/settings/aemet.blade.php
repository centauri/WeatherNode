@extends('layouts.admin')

@section('title', __($groupInfo['label']))

@section('content')
<div class="w-full">
    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">{{ __($groupInfo['label']) }}</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-4">
            <div class="p-3 rounded-xl bg-amber-100 dark:bg-amber-900/30">
                <svg class="w-8 h-8 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __($groupInfo['label']) }}</h1>
                <p class="text-gray-500 dark:text-gray-400">{{ __($groupInfo['description']) }}</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="text-green-800 dark:text-green-200">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10A8 8 0 112 10a8 8 0 0116 0zm-8-4a1 1 0 00-.894.553l-3 6A1 1 0 007 14h6a1 1 0 00.894-1.447l-3-6A1 1 0 0010 6zm1 6a1 1 0 10-2 0 1 1 0 002 0zm-1 4a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
                <p class="text-red-800 dark:text-red-200">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <!-- Information Card -->
    <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl">
        <h3 class="font-semibold text-blue-900 dark:text-blue-200 mb-1">{{ __('How to obtain AEMET OpenData credentials?') }}</h3>
        <p class="text-sm text-blue-700 dark:text-blue-300 leading-relaxed mb-2">
            {{ __('The AEMET integration allows querying daily and hourly weather forecasts for municipalities in Spain.') }}
        </p>
        <ul class="list-disc list-inside text-sm text-blue-700 dark:text-blue-300 space-y-1">
            <li>{{ __('Get your free API Key by registering at') }} <a href="https://opendata.aemet.es/centrodedescargas/altaUsuario" target="_blank" class="underline font-semibold hover:text-blue-900 dark:hover:text-blue-100">AEMET OpenData</a>.</li>
            <li>{{ __('The Municipality Code is the 5-digit INE code (e.g. 28079 for Madrid, 08019 for Barcelona).') }}</li>
        </ul>
    </div>

    <!-- Settings Form -->
    <form action="{{ route('admin.settings.update', $group) }}" method="POST" class="space-y-6">
        @csrf

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm divide-y divide-gray-100 dark:divide-gray-700">
            @forelse($settings as $setting)
                @php
                    $formKey = str_replace('.', '_', $setting->key);
                @endphp
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex-1">
                            <label for="{{ $formKey }}" class="block font-medium text-gray-900 dark:text-white">
                                {{ __($setting->description ?? $setting->key) }}
                            </label>
                            <span class="text-xs font-mono text-gray-400">{{ $setting->key }}</span>
                        </div>

                        <div class="sm:w-80">
                            @if($setting->type === 'encrypted' || str_contains($setting->key, 'api_key'))
                                {{-- Left empty on purpose: a masked value such as ******** is
                                     posted back like any other and was saved as the key. An
                                     empty field keeps the stored key. --}}
                                <input type="password"
                                       id="{{ $formKey }}"
                                       name="{{ $formKey }}"
                                       value=""
                                       autocomplete="new-password"
                                       placeholder="{{ $setting->value ? __('(configured - enter new value to change)') : __('Enter your AEMET API Key') }}"
                                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-amber-500 focus:border-amber-500 dark:bg-gray-700 dark:text-white">
                                @if($setting->value)
                                    <p class="mt-1 text-xs text-green-600 dark:text-green-400">{{ __('Configured (leave empty to keep current value)') }}</p>
                                @endif
                            @else
                                <input type="text"
                                       id="{{ $formKey }}"
                                       name="{{ $formKey }}"
                                       value="{{ $setting->value }}"
                                       placeholder="{{ __('e.g. 28079') }}"
                                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-amber-500 focus:border-amber-500 dark:bg-gray-700 dark:text-white">
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-gray-500 dark:text-gray-400">
                    {{ __('No settings registered for AEMET.') }}
                </div>
            @endforelse
        </div>

        <div class="flex items-center justify-between pt-4">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-medium">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="px-6 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-medium rounded-lg shadow-sm transition-colors">
                {{ __('Save Settings') }}
            </button>
        </div>
    </form>
</div>
@endsection
