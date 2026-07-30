@extends('layouts.admin')

@section('title', __('Setup Guide'))

@section('content')
@php
    $ecowittSecureMode = (bool) \App\Models\Setting::getValue('ecowitt.secure_mode', false);
    $ecowittSecureToken = trim((string) \App\Models\Setting::getValue('ecowitt.secure_token', ''));
    $ecowittPath = '/api/ecowitt/receive' . (($ecowittSecureMode && $ecowittSecureToken !== '') ? '/' . $ecowittSecureToken : '');
@endphp
<div class="w-full space-y-8">
    <!-- Header -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl p-8 text-white">
        <h1 class="text-3xl font-bold mb-2">🚀 {{ __('WeatherNode Setup Guide') }}</h1>
        <p class="opacity-90">{{ __('Complete guide to configuring your weather station dashboard') }}</p>
    </div>

    <!-- Quick Start -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center">
                <span class="w-8 h-8 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mr-3 text-green-600 dark:text-green-400">1</span>
                {{ __('Quick Start Checklist') }}
            </h2>
        </div>
        <div class="p-6 space-y-4">
            <div class="flex items-start space-x-3">
                <input type="checkbox" class="mt-1 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700" disabled>
                <div>
                    <p class="font-medium text-gray-800 dark:text-white">{{ __('Configure Station Info') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Set your station name, location, coordinates, and timezone in') }}
                        <a href="{{ route('admin.settings.group', 'station') }}" class="text-blue-600 hover:underline">{{ __('Station Settings') }}</a>
                    </p>
                </div>
            </div>
            <div class="flex items-start space-x-3">
                <input type="checkbox" class="mt-1 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700" disabled>
                <div>
                    <p class="font-medium text-gray-800 dark:text-white">{{ __('Set Up Weather Station') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Choose your live data source (Ecowitt, local file/API, WeatherLink, etc.) in') }}
                        <a href="{{ route('admin.settings.group', 'livedata') }}" class="text-blue-600 hover:underline">{{ __('Live Data Source') }}</a>.
                        {{ __('Add provider keys in their own settings pages if needed.') }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ __('API keys and secrets are entered in masked fields; leave a field blank to keep the current value.') }}
                    </p>
                </div>
            </div>
            <div class="flex items-start space-x-3">
                <input type="checkbox" class="mt-1 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700" disabled>
                <div>
                    <p class="font-medium text-gray-800 dark:text-white">{{ __('Choose Dashboard Widgets') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Enable/disable dashboard cards and apply quick templates in') }}
                        <a href="{{ route('admin.settings.widgets') }}" class="text-blue-600 hover:underline">{{ __('Dashboard Widgets') }}</a>.
                        {{ __('Widget ordering is edited on the main dashboard page via Edit/Bewerk.') }}
                    </p>
                </div>
            </div>
            <div class="flex items-start space-x-3">
                <input type="checkbox" class="mt-1 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700" disabled>
                <div>
                    <p class="font-medium text-gray-800 dark:text-white">{{ __('Add API Keys') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Open') }}
                        <a href="{{ route('admin.api-keys.index') }}" class="text-blue-600 hover:underline">{{ __('Admin → API Keys') }}</a>
                        {{ __('to generate/manage keys for the built-in API (used by the dashboard).') }}
                        {{ __('External services like WAQI (air quality) and CheckWX (aviation) may also require their own API keys, configured in their settings pages.') }}
                    </p>
                </div>
            </div>
            <div class="flex items-start space-x-3">
                <input type="checkbox" class="mt-1 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700" disabled>
                <div>
                    <p class="font-medium text-gray-800 dark:text-white">{{ __('Configure Scheduler (Cron)') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Set up the scheduler cron entry and verify status in') }}
                        <a href="{{ route('admin.settings.group', 'scheduler') }}" class="text-blue-600 hover:underline">{{ __('Schedulers') }}</a>.
                    </p>
                </div>
            </div>
            <div class="flex items-start space-x-3">
                <input type="checkbox" class="mt-1 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700" disabled>
                <div>
                    <p class="font-medium text-gray-800 dark:text-white">{{ __('Run Initial Data Poll') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Execute') }}
                        <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">php artisan weather:fetch --save</code>
                        {{ __('and') }}
                        <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">php artisan weather:poll-external --force</code>
                        {{ __('to fill the cache') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- How It Works -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center">
                <span class="w-8 h-8 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center mr-3 text-indigo-600 dark:text-indigo-400">⚡</span>
                {{ __('How the Dashboard Works') }}
            </h2>
        </div>
        <div class="p-6">
            <div class="bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-900/20 dark:to-purple-900/20 rounded-lg p-6 mb-6">
                <h3 class="font-semibold text-gray-800 dark:text-white mb-3">{{ __('Cache-First Architecture') }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    {{ __('The dashboard is designed for') }} <strong>{{ __('instant loading') }}</strong>. {{ __('External API data is fetched by a background poller and stored in cache.') }}
                    {{ __('The dashboard only reads from cache — it never makes API calls during page loads.') }}
                </p>
                <div class="flex items-center justify-center text-sm">
                    <div class="bg-white dark:bg-gray-800 rounded-lg px-4 py-2 shadow">{{ __('External APIs') }}</div>
                    <div class="mx-2 text-gray-400">→ {{ __('poller') }} →</div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg px-4 py-2 shadow">{{ __('Cache') }}</div>
                    <div class="mx-2 text-gray-400">→ {{ __('reads') }} →</div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg px-4 py-2 shadow">{{ __('Dashboard') }}</div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                    <div class="text-2xl mb-2">⚡</div>
                    <p class="font-medium text-gray-800 dark:text-white">{{ __('Instant Loading') }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Dashboard loads in ~10ms') }}</p>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                    <div class="text-2xl mb-2">🔄</div>
                    <p class="font-medium text-gray-800 dark:text-white">{{ __('Always Fresh') }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Poller keeps data current') }}</p>
                </div>
                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                    <div class="text-2xl mb-2">🛡️</div>
                    <p class="font-medium text-gray-800 dark:text-white">{{ __('Resilient') }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Works if APIs are down') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Auto-Update System -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center">
                <span class="w-8 h-8 bg-emerald-100 dark:bg-emerald-900 rounded-full flex items-center justify-center mr-3 text-emerald-600 dark:text-emerald-400">🔄</span>
                {{ __('Auto-Update System') }}
            </h2>
        </div>
        <div class="p-6">
            <div class="bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-900/20 dark:to-teal-900/20 rounded-lg p-6 mb-6">
                <h3 class="font-semibold text-gray-800 dark:text-white mb-3">{{ __('Smart Dashboard Updates') }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    {{ __('The dashboard automatically refreshes data every 60 seconds, but only updates the UI when data actually changes. This provides a smooth, battery-friendly experience.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="flex items-center mb-2">
                        <div class="text-2xl mr-2">⏱️</div>
                        <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Update Indicators') }}</h3>
                    </div>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-2 ml-8">
                        <li>• {{ __('Status bar shows "Updated 30s ago"') }}</li>
                        <li>• {{ __('Green "Live" dot when data is fresh') }}</li>
                        <li>• {{ __('Each card shows actual data source timestamp (top-center, e.g., "🕐 15:53")') }}</li>
                        <li>• {{ __('Centered "OFFLINE" badge when data is stale') }}</li>
                        <li>• {{ __('Refresh spinner during data fetch') }}</li>
                    </ul>
                </div>

                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="flex items-center mb-2">
                        <div class="text-2xl mr-2">🔋</div>
                        <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Battery Friendly') }}</h3>
                    </div>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-2 ml-8">
                        <li>• {{ __('Pauses when browser tab is hidden') }}</li>
                        <li>• {{ __('Only animates changed values') }}</li>
                        <li>• {{ __('Smooth fade transitions') }}</li>
                        <li>• {{ __('Resumes instantly when tab is visible') }}</li>
                    </ul>
                </div>
            </div>

            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                <div class="flex items-start">
                    <div class="text-2xl mr-3">💡</div>
                    <div>
                        <p class="font-medium text-gray-800 dark:text-white mb-2">{{ __('Smart Change Detection') }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ __('The dashboard checks timestamps and data hashes to detect real changes. If the poller runs but gets the same data (e.g., sensor is offline), the UI won\'t flash unnecessarily.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Application Updates -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center">
                <span class="w-8 h-8 bg-emerald-100 dark:bg-emerald-900 rounded-full flex items-center justify-center mr-3 text-emerald-600 dark:text-emerald-400">🔄</span>
                {{ __('Application Updates') }}
            </h2>
        </div>
        <div class="p-6">
            <div class="bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-900/20 dark:to-teal-900/20 rounded-lg p-6 mb-6">
                <h3 class="font-semibold text-gray-800 dark:text-white mb-3">{{ __('In-App Update System') }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    {{ __('WeatherNode includes a comprehensive update system that allows you to update the application directly from the admin panel when your server supports it. All updates include automatic backups, validation, health checks, and rollback capability.') }}
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Access updates at') }} <a href="{{ route('admin.settings.updates') }}" class="text-blue-600 hover:underline font-medium">{{ __('Admin → Settings → Updates') }}</a>.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="flex items-center mb-2">
                        <div class="text-2xl mr-2">🌐</div>
                        <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Browser-Based Updates (Tier 1)') }}</h3>
                    </div>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-2 ml-8">
                        <li>• {{ __('Automatic backups before every update') }}</li>
                        <li>• {{ __('Pre-update validation (PHP, extensions, disk space)') }}</li>
                        <li>• {{ __('Health checks after deployment') }}</li>
                        <li>• {{ __('Auto-rollback on failure') }}</li>
                        <li>• {{ __('Zero-downtime atomic deployments') }}</li>
                        <li>• {{ __('One-click rollback to any version') }}</li>
                    </ul>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">
                        {{ __('Requirements: Write access, symlinks, ZIP extraction, Artisan execution') }}
                    </p>
                </div>

                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="flex items-center mb-2">
                        <div class="text-2xl mr-2">📦</div>
                        <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Manual ZIP Updates (Tier 0)') }}</h3>
                    </div>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-2 ml-8">
                        <li>• {{ __('Works on all hosting providers') }}</li>
                        <li>• {{ __('Download ZIP from GitHub releases') }}</li>
                        <li>• {{ __('Upload and extract over installation') }}</li>
                        <li>• {{ __('Manual migration execution') }}</li>
                        <li>• {{ __('Requires manual backup') }}</li>
                    </ul>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">
                        {{ __('Fallback method when browser updates aren\'t available') }}
                    </p>
                </div>
            </div>

            <div class="space-y-4 mb-6">
                <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                        <span class="text-green-600 dark:text-green-400">1</span>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800 dark:text-white">{{ __('Check Compatibility') }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('The updates page automatically checks if your server supports browser-based updates. It verifies write access, symlink support, ZIP extraction, and Artisan execution.') }}</p>
                    </div>
                </div>

                <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                        <span class="text-blue-600 dark:text-blue-400">2</span>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800 dark:text-white">{{ __('Preview Updates') }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Use the "Preview Update" button to test an update without deploying. This checks compatibility and requirements without making any changes.') }}</p>
                    </div>
                </div>

                <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                        <span class="text-purple-600 dark:text-purple-400">3</span>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800 dark:text-white">{{ __('Deploy Updates') }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Click "Update Now" to deploy. The system will automatically backup your data, validate requirements, download the update, verify integrity, deploy it, run health checks, and rollback if anything fails.') }}</p>
                    </div>
                </div>

                <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                        <span class="text-yellow-600 dark:text-yellow-400">4</span>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800 dark:text-white">{{ __('Rollback if Needed') }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('If an update causes issues, you can instantly rollback to any previous version from the "Previous Releases" section. Your data is preserved.') }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
                <h4 class="font-medium text-blue-800 dark:text-blue-200 mb-2">{{ __('Safety Features') }}</h4>
                <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-1 ml-4 list-disc">
                    <li>{{ __('Automatic backups: .env, database, and storage/ directory') }}</li>
                    <li>{{ __('Pre-update validation: PHP version, extensions, disk space, database schema') }}</li>
                    <li>{{ __('SHA256 checksum verification: Ensures downloaded files are authentic') }}</li>
                    <li>{{ __('Health checks: Verifies HTTP endpoints, database, and Artisan after deployment') }}</li>
                    <li>{{ __('Auto-rollback: Automatically reverts if health check fails') }}</li>
                    <li>{{ __('Filesystem lock: Prevents concurrent deployments') }}</li>
                    <li>{{ __('Maintenance mode: Site goes offline during critical update steps') }}</li>
                    <li>{{ __('Audit logging: Complete history of all update attempts with user attribution') }}</li>
                </ul>
            </div>

            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-4">
                <h4 class="font-semibold text-gray-800 dark:text-white mb-3">{{ __('Configuration') }}</h4>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">{{ __('Enable the updater in') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">.env</code>:</p>
                <div class="bg-gray-100 dark:bg-gray-900 rounded p-3 font-mono text-xs">
UPDATER_ENABLED=true<br>
UPDATER_GITHUB_REPO=centauri/WeatherNode<br>
UPDATER_NOTIFY_EMAIL=true
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                    {{ __('For Git-based updates (requires SSH access), also set') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">UPDATER_ALLOW_GIT=true</code>.
                </p>
            </div>

            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                <div class="flex items-start">
                    <div class="text-2xl mr-3">💡</div>
                    <div>
                        <p class="font-medium text-yellow-800 dark:text-yellow-200 mb-2">{{ __('Update Notifications') }}</p>
                        <p class="text-sm text-yellow-700 dark:text-yellow-300">
                            {{ __('Enable email notifications to be alerted when new versions are available. The system checks for updates daily at 2 AM and emails all admin users when a new release is published.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sensor Health Monitoring -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center">
                <span class="w-8 h-8 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center mr-3 text-red-600 dark:text-red-400">🔴</span>
                {{ __('Sensor Health Monitoring') }}
            </h2>
        </div>
        <div class="p-6">
            <div class="bg-gradient-to-r from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20 rounded-lg p-6 mb-6">
                <h3 class="font-semibold text-gray-800 dark:text-white mb-3">{{ __('Comprehensive Data Source Monitoring') }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    {{ __('The system monitors') }} <strong>{{ __('all data sources') }}</strong> {{ __('independently: sensor, forecast, astronomy, air quality, aurora, and METAR. Each source has its own health check and visual indicators on the dashboard.') }}
                </p>
                <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                    <li>• {{ __('Sensor data: stale after 5 minutes') }}</li>
                    <li>• {{ __('External APIs: stale after 60 minutes') }}</li>
                    <li>• {{ __('Each card shows actual data source timestamp at top-center (e.g., "🕐 15:53")') }}</li>
                    <li>• {{ __('Centered "OFFLINE" badge with age when data is stale') }}</li>
                </ul>
            </div>

            <div class="space-y-4 mb-6">
                <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                        <span class="text-red-600 dark:text-red-400">1</span>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800 dark:text-white">{{ __('Dashboard Indicators') }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Each card shows its data source timestamp at top-center. Large centered "OFFLINE" badge appears when data is stale, showing age in minutes.') }}</p>
                    </div>
                </div>

                <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 bg-orange-100 dark:bg-orange-900 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                        <span class="text-orange-600 dark:text-orange-400">2</span>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800 dark:text-white">{{ __('Email Alerts') }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Automatic email notification sent when sensor goes offline and when it recovers') }}</p>
                    </div>
                </div>

                <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                        <span class="text-yellow-600 dark:text-yellow-400">3</span>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800 dark:text-white">{{ __('Console Logging') }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ __('Check Laravel logs at') }}
                            <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">storage/logs/laravel.log</code>
                            {{ __('for sensor health events') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-4">
                <h3 class="font-semibold text-gray-800 dark:text-white mb-3">{{ __('Configure Email Alerts') }}</h3>
                <ol class="text-sm text-gray-600 dark:text-gray-400 space-y-2 list-decimal list-inside">
                    <li>{{ __('Go to') }} <a href="{{ route('admin.settings.index') }}" class="text-blue-600 hover:underline">{{ __('Settings') }}</a></li>
                    <li>{{ __('Add setting') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">alerts.enabled</code> = <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">true</code></li>
                    <li>{{ __('Add setting') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">alerts.email</code> = <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">your-email@example.com</code></li>
                    <li>{{ __('Configure mail provider in') }} <a href="{{ route('admin.settings.mail') }}" class="text-blue-600 hover:underline">{{ __('Admin → Settings → Mail') }}</a>:
                        <ul class="ml-6 mt-2 space-y-1 list-disc">
                            <li><strong>{{ __('OAuth2 (Recommended)') }}:</strong> {{ __('For Gmail and Microsoft/Office 365. Enter Client ID and Client Secret, then click "Authorize" to complete OAuth flow. Token refresh is automatic.') }}</li>
                            <li><strong>{{ __('Predefined SMTP') }}:</strong> {{ __('Brevo (EU, 300/day free), Mailjet (EU, 6K/month), Postmark (US, 100/month), Mailgun (US, 6K/month), or SMTP2Go (Asia, 1K/month). Enter credentials as shown.') }}</li>
                            <li><strong>{{ __('Custom SMTP') }}:</strong> {{ __('Manual configuration for any other SMTP provider.') }}</li>
                        </ul>
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded p-3 mt-2">
                            <p class="text-xs text-blue-800 dark:text-blue-200 mb-1"><strong>{{ __('Note:') }}</strong> {{ __('OAuth2 token refresh is fully implemented. Access tokens are automatically refreshed when sending emails. FROM address automatically uses your verified email address.') }}</p>
                        </div>
                        <div class="bg-gray-100 dark:bg-gray-900 rounded p-3 mt-2 font-mono text-xs">
                            <div class="text-gray-500 mb-1">{{ __('Legacy .env method (still supported):') }}</div>
MAIL_MAILER=smtp<br>
MAIL_HOST=smtp.gmail.com<br>
MAIL_PORT=587<br>
MAIL_USERNAME=your-email@gmail.com<br>
MAIL_PASSWORD=your-app-password<br>
MAIL_ENCRYPTION=tls
                        </div>
                    </li>
                </ol>
            </div>

            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="flex items-start">
                    <div class="text-2xl mr-3">🧪</div>
                    <div>
                        <p class="font-medium text-gray-800 dark:text-white mb-2">{{ __('Test Sensor Monitoring') }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ __('Run this command to manually check sensor health:') }}</p>
                        <code class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded text-sm">php artisan weather:check-sensor-health</code>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                            {{ __('Output will show sensor status and send alert if configured. This command runs automatically every 5 minutes via the scheduler (cron running schedule:run).') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Sources -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center">
                <span class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center mr-3 text-blue-600 dark:text-blue-400">2</span>
                {{ __('Weather Station Setup') }}
            </h2>
        </div>
        <div class="p-6">
            <p class="text-gray-600 dark:text-gray-300 mb-6">{{ __('WeatherNode supports multiple ways to receive live weather data from your station:') }}</p>
            
            <div class="space-y-6">
                <!-- Ecowitt Local Upload -->
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="flex items-center mb-2">
                        <span class="px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 text-xs rounded mr-2">{{ __('Recommended') }}</span>
                        <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Ecowitt Local Upload (Push)') }}</h3>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                        {{ __('Your Ecowitt gateway sends data directly to your server every minute. No API keys required for live data.') }}
                        {{ __('Select') }} <strong>{{ __('Ecowitt Local (push)') }}</strong> {{ __('in Live Data Source settings.') }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                        {{ __('Recommended: enable Secure Push Mode in Live Data Source and configure both endpoint token and passkey for internet-exposed stations.') }}
                    </p>
                    <div class="bg-gray-100 dark:bg-gray-900 rounded-lg p-4">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Setup in Ecowitt/WS View app:') }}</p>
                        <ol class="text-sm text-gray-600 dark:text-gray-400 list-decimal list-inside space-y-1">
                            <li>{{ __('Go to Device → Weather Services → Customized') }}</li>
                                    <li>{{ __('Enable "Customized" and configure:') }}
                                <ul class="ml-6 mt-1 list-disc">
                                    <li>{{ __('Server') }}: <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">{{ request()->getHost() }}</code></li>
                                    <li>{{ __('Path') }}: <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">{{ $ecowittPath }}</code></li>
                                    <li>{{ __('Port') }}: <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">80</code> ({{ __('or 443 for HTTPS') }})</li>
                                    <li>{{ __('Upload Interval') }}: {{ __('60 seconds') }}</li>
                                </ul>
                            </li>
                        </ol>
                        @if($ecowittSecureMode)
                            <p class="text-xs text-amber-700 dark:text-amber-300 mt-3">
                                {{ __('Secure Push Mode is enabled. Keep the token and passkey private and configure both in WS View.') }}
                            </p>
                        @endif
                    </div>
                </div>

                <!-- Ecowitt API -->
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-800 dark:text-white mb-2">{{ __('Ecowitt Cloud API (Pull)') }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                        {{ __('Fetch data from Ecowitt servers. Requires API keys from') }}
                        <a href="https://www.ecowitt.net/user/site" target="_blank" class="text-blue-600 hover:underline">ecowitt.net</a>.
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Select') }}
                        <strong>{{ __('Ecowitt Cloud API') }}</strong>
                        {{ __('in Live Data Source and configure keys in') }}
                        <a href="{{ route('admin.settings.group', 'ecowitt') }}" class="text-blue-600 hover:underline">{{ __('Ecowitt Settings') }}</a>.
                    </p>
                </div>

                <!-- Local File -->
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-800 dark:text-white mb-2">{{ __('Local File or Local API (Advanced)') }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                        {{ __('Read from local files (clientraw, realtime.txt, Ecowitt array) or a local API endpoint.') }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Set') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">livedata.format</code> {{ __('to your station type, then choose') }}
                        <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">livedata.fetch_mode</code> = <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">file</code>
                        {{ __('or') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">local_api</code> {{ __('and provide') }}
                        <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">livedata.file_path</code> {{ __('or') }}
                        <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">livedata.api_url</code> {{ __('in Live Data Source settings.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Polling Commands -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden" id="polling">
        <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center">
                <span class="w-8 h-8 bg-cyan-100 dark:bg-cyan-900 rounded-full flex items-center justify-center mr-3 text-cyan-600 dark:text-cyan-400">3</span>
                {{ __('Polling Commands') }}
            </h2>
        </div>
        <div class="p-6">
            <p class="text-gray-600 dark:text-gray-300 mb-4">
                {{ __('These commands fetch data from external APIs and store it in cache for fast dashboard access.') }}
            </p>
            
            <div class="space-y-4">
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Fetch live data from the configured source:') }}</p>
                    <div class="bg-gray-900 rounded-lg p-3">
                        <code class="text-green-400 text-sm">php artisan weather:fetch --save</code>
                    </div>
                </div>
                
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Poll ALL external APIs (forecast, air quality, alerts, etc.):') }}</p>
                    <div class="bg-gray-900 rounded-lg p-3">
                        <code class="text-green-400 text-sm">php artisan weather:poll-external</code>
                    </div>
                </div>
                
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Poll specific source:') }}</p>
                    <div class="bg-gray-900 rounded-lg p-3 space-y-1">
                        <code class="text-green-400 text-sm block">php artisan weather:poll-external --source=forecast</code>
                        <code class="text-green-400 text-sm block">php artisan weather:poll-external --source=airquality</code>
                        <code class="text-green-400 text-sm block">php artisan weather:poll-external --source=alerts</code>
                        <code class="text-green-400 text-sm block">php artisan weather:poll-external --source=aurora</code>
                        <code class="text-green-400 text-sm block">php artisan weather:poll-external --source=iss</code>
                        <code class="text-green-400 text-sm block">php artisan weather:poll-external --source=metar</code>
                        <code class="text-green-400 text-sm block">php artisan weather:poll-external --source=earthquake</code>
                        <code class="text-green-400 text-sm block">php artisan weather:poll-external --source=astronomy</code>
                        <code class="text-green-400 text-sm block">php artisan weather:poll-external --source=knmi_nowcast</code>
                        <code class="text-green-400 text-sm block">php artisan weather:poll-external --source=solar_forecast</code>
                        <code class="text-green-400 text-sm block">php artisan weather:poll-external --source=knmi_wms</code>
                        <code class="text-green-400 text-sm block">php artisan weather:poll-external --source=tide</code>
                        <code class="text-green-400 text-sm block">php artisan weather:poll-external --source=waves</code>
                        <code class="text-green-400 text-sm block">php artisan weather:poll-external --source=rivers</code>
                        <code class="text-green-400 text-sm block">php artisan weather:poll-external --source=pollen</code>
                    </div>
                </div>
                
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Force refresh (ignore existing cache):') }}</p>
                    <div class="bg-gray-900 rounded-lg p-3">
                        <code class="text-green-400 text-sm">php artisan weather:poll-external --force</code>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    <strong>💡 {{ __('Tip') }}:</strong>
                    {{ __('Run') }} <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">php artisan weather:poll-external --force</code>
                    {{ __('after initial setup to fill all caches immediately.') }}
                </p>
            </div>
        </div>
    </div>

    <!-- Admin-only Debug Overrides -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden" id="debug-overrides">
        <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center">
                <span class="w-8 h-8 bg-slate-100 dark:bg-slate-700 rounded-full flex items-center justify-center mr-3 text-slate-600 dark:text-slate-300">🧪</span>
                {{ __('Admin-only Debug Overrides') }}
            </h2>
        </div>
        <div class="p-6 space-y-4">
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    {{ __('You can temporarily override key values via URL query parameters to test visuals (storm wind, heavy rain, heat). These overrides only work for logged-in admins (is_admin). Visitors are ignored.') }}
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-400">{{ __('Parameter') }}</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-400">{{ __('Meaning') }}</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-400">{{ __('Example') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs text-gray-800 dark:text-white">debug_wind_speed</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ __('Wind speed (km/h)') }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-800 dark:text-white">80</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs text-gray-800 dark:text-white">debug_wind_dir</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ __('Wind direction (degrees)') }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-800 dark:text-white">240</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs text-gray-800 dark:text-white">debug_temp</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ __('Temperature (uses the same unit as current.temperature)') }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-800 dark:text-white">35</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs text-gray-800 dark:text-white">debug_rain_rate</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ __('Rain rate (mm/h)') }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-800 dark:text-white">6</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs text-gray-800 dark:text-white">debug_rain_daily</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ __('Daily rain accumulation (mm)') }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-800 dark:text-white">18</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="bg-gray-100 dark:bg-gray-900 rounded-lg p-4">
                <p class="text-sm text-gray-700 dark:text-gray-300 mb-2">{{ __('Example URLs:') }}</p>
                <div class="space-y-2 font-mono text-xs">
                    <div class="text-gray-800 dark:text-white">/?debug_wind_speed=80&amp;debug_wind_dir=240</div>
                    <div class="text-gray-800 dark:text-white">/?debug_temp=35</div>
                    <div class="text-gray-800 dark:text-white">/?debug_rain_rate=6&amp;debug_rain_daily=18</div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">
                    {{ __('Disable by removing query parameters and refreshing.') }}
                </p>
            </div>
        </div>
    </div>

    <!-- Cron Job Setup -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden" id="cron">
        <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center">
                <span class="w-8 h-8 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center mr-3 text-purple-600 dark:text-purple-400">4</span>
                {{ __('Cron Job Setup') }}
            </h2>
        </div>
        <div class="p-6">
            <div class="bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <p class="font-medium text-amber-800 dark:text-amber-200">{{ __('Important') }}</p>
                        <p class="text-sm text-amber-700 dark:text-amber-300">{{ __('Without a cron job, the cache won\'t stay fresh and dashboard cards may show stale or no data!') }}</p>
                    </div>
                </div>
            </div>

            <h3 class="font-semibold text-gray-800 dark:text-white mb-3">{{ __('Add ONE cron entry:') }}</h3>
            <div class="bg-gray-900 rounded-lg p-4 mb-4 space-y-2">
                <code class="text-green-400 text-sm block">* * * * * cd /path/to/weathernode && php artisan schedule:run >> /dev/null 2>&1</code>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-6">{{ __('Replace') }} <code>/path/to/weathernode</code> {{ __('with your actual installation path.') }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-6">
                {{ __('Verify scheduler health and see all tasks at') }}
                <a href="{{ route('admin.settings.group', 'scheduler') }}" class="text-blue-600 hover:underline">{{ __('Schedulers') }}</a>.
            </p>

            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                <h4 class="font-medium text-blue-800 dark:text-blue-200 mb-2">{{ __('Smart Interval Tracking') }}</h4>
                <p class="text-sm text-blue-700 dark:text-blue-300 mb-3">
                    {{ __('The scheduler runs every minute, but external poller commands use smart interval tracking to respect API rate limits. Each service tracks when it was last polled and only polls again when its interval has passed:') }}
                </p>
                <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-1 ml-4 list-disc mb-3">
                    <li><strong>{{ __('Every 15 min') }}:</strong> {{ __('Earthquakes, Weather Alerts, Rivers') }}</li>
                    <li><strong>{{ __('Every 30 min') }}:</strong> {{ __('Forecast, Air Quality, Aurora, METAR') }}</li>
                    <li><strong>{{ __('Every 60 min') }}:</strong> {{ __('ISS/Tiangong, Astronomy, Tides, Waves, Pollen') }}</li>
                </ul>
                <p class="text-xs text-blue-600 dark:text-blue-400 italic">
                    {{ __('Note: If you see "not due yet" in logs, the service was recently polled and is waiting for its interval. This is normal. Use') }}
                    <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">--force</code>
                    {{ __('to bypass intervals and poll immediately.') }}
                </p>
            </div>

            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6">
                <h4 class="font-medium text-green-800 dark:text-green-200 mb-2">{{ __('Self-Healing Health Check') }}</h4>
                <p class="text-sm text-green-700 dark:text-green-300 mb-3">
                    {{ __('The system includes an automatic health check that runs every 5 minutes to detect and recover from missing or invalid cache data. This ensures that if cache is cleared, missing data is automatically restored.') }}
                </p>
                <p class="text-sm text-green-700 dark:text-green-300 mb-2"><strong>{{ __('How it works:') }}</strong></p>
                <ul class="text-sm text-green-700 dark:text-green-300 space-y-1 ml-4 list-disc mb-3">
                    <li>{{ __('Runs every 5 minutes via the scheduler') }}</li>
                    <li>{{ __('Checks all critical caches: forecast, astronomy (sun/moon), air quality, aurora') }}</li>
                    <li>{{ __('Validates data structure (not just cache existence)') }}</li>
                    <li>{{ __('Automatically fetches missing data immediately') }}</li>
                    <li>{{ __('Maximum 5-minute delay to recover from cache clears') }}</li>
                </ul>
                <p class="text-xs text-green-600 dark:text-green-400 italic">
                    {{ __('This means missing data (like empty AQ, sun, or moon phase fields) will be automatically restored within 5 minutes maximum.') }}
                </p>
            </div>

            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6">
                <h4 class="font-medium text-green-800 dark:text-green-200 mb-2">{{ __('Cache Resilience') }}</h4>
                <p class="text-sm text-green-700 dark:text-green-300">
                    {{ __('Cache TTLs are set to 3-4x the polling interval. This means data persists even if the poller misses a few cycles') }}
                    {{ __('or APIs are temporarily unavailable. The dashboard will always show data.') }}
                </p>
            </div>

            <h3 class="font-semibold text-gray-800 dark:text-white mb-3">{{ __('Useful Commands:') }}</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-400">{{ __('Command') }}</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-400">{{ __('Description') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs text-gray-800 dark:text-white">weather:fetch --save</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ __('Fetch local weather station data') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs text-gray-800 dark:text-white">weather:poll-external</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ __('Poll external APIs (respects intervals)') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs text-gray-800 dark:text-white">weather:poll-external --force</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ __('Poll everything immediately (bypasses intervals)') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs text-gray-800 dark:text-white">weather:poll-external --source=forecast</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ __('Poll specific service only') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs text-gray-800 dark:text-white">weather:sync-wu</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ __('Sync recent Weather Underground history') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 p-3 bg-gray-100 dark:bg-gray-900 rounded-lg">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    <strong>{{ __('After initial setup') }}:</strong>
                    {{ __('Run') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">php artisan weather:poll-external --force</code>
                    {{ __('to fill all caches immediately.') }}
                </p>
            </div>
        </div>
    </div>

    <!-- Weather Alerts -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center">
                <span class="w-8 h-8 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center mr-3 text-red-600 dark:text-red-400">5</span>
                {{ __('Weather Alerts') }}
            </h2>
        </div>
        <div class="p-6">
            <p class="text-gray-600 dark:text-gray-300 mb-4">
                {{ __('WeatherNode supports weather warnings from 5 worldwide services:') }}
            </p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="flex items-center mb-2">
                        <span class="text-xl mr-2">🇪🇺</span>
                        <span class="font-semibold text-gray-800 dark:text-white">{{ __('Meteoalarm') }}</span>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('35 European countries. Region codes like NL011, DE031, FR075') }}</p>
                </div>
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="flex items-center mb-2">
                        <span class="text-xl mr-2">🇺🇸</span>
                        <span class="font-semibold text-gray-800 dark:text-white">{{ __('NWS (USA)') }}</span>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('All 50 US states. Select state + optional zone.') }}</p>
                </div>
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="flex items-center mb-2">
                        <span class="text-xl mr-2">🇨🇦</span>
                        <span class="font-semibold text-gray-800 dark:text-white">{{ __('Environment Canada') }}</span>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('All provinces/territories via RSS feed.') }}</p>
                </div>
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="flex items-center mb-2">
                        <span class="text-xl mr-2">🇬🇧</span>
                        <span class="font-semibold text-gray-800 dark:text-white">{{ __('Met Office (UK)') }}</span>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('UK regions via RSS feed.') }}</p>
                </div>
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 md:col-span-2">
                    <div class="flex items-center mb-2">
                        <span class="text-xl mr-2">🇦🇺</span>
                        <span class="font-semibold text-gray-800 dark:text-white">{{ __('BOM (Australia)') }}</span>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Australian states/territories. Note: BOM restricts automated access.') }}</p>
                </div>
            </div>
            
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Configure in') }}
                <a href="{{ route('admin.settings.alerts') }}" class="text-blue-600 hover:underline">{{ __('Admin → Settings → Weather Alerts') }}</a>
            </p>
        </div>
    </div>

    <!-- Webcam -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center">
                <span class="w-8 h-8 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center mr-3 text-purple-600 dark:text-purple-400">📷</span>
                {{ __('Webcam Widget') }}
            </h2>
        </div>
        <div class="p-6">
            <p class="text-gray-600 dark:text-gray-300 mb-4">
                {{ __('The webcam widget supports both static images and livestreams (YouTube or Restreamer).') }}
            </p>
            
            <div class="space-y-4 mb-6">
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-800 dark:text-white mb-2">{{ __('Display Modes') }}</h3>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-2">
                        <li><strong>{{ __('Image') }}:</strong> {{ __('Shows a static image that refreshes automatically at the configured interval (default: 60 seconds). Configure in') }} <a href="{{ route('admin.settings.group', 'webcam') }}" class="text-blue-600 hover:underline">{{ __('Webcam Settings') }}</a>.</li>
                        <li><strong>{{ __('Stream') }}:</strong> {{ __('Shows a YouTube or Restreamer livestream. The widget does NOT refresh automatically to avoid interrupting playback.') }}</li>
                        <li><strong>{{ __('Both') }}:</strong> {{ __('Shows the static image with a click-to-view livestream option. Image refreshes automatically, stream opens in a modal.') }}</li>
                    </ul>
                </div>

                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-800 dark:text-white mb-2">{{ __('Smart Refresh Behavior') }}</h3>
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3 mb-3">
                        <p class="text-sm text-green-800 dark:text-green-200">
                            <strong>✅ {{ __('Image Mode') }}:</strong> {{ __('Static images refresh automatically at the configured interval.') }}
                        </p>
                    </div>
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            <strong>⏸️ {{ __('Stream Mode') }}:</strong> {{ __('Livestreams do NOT refresh automatically. This prevents interruptions during playback.') }}
                        </p>
                    </div>
                </div>

                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-800 dark:text-white mb-2">{{ __('Configuration') }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                        {{ __('Configure in') }} <a href="{{ route('admin.settings.group', 'webcam') }}" class="text-blue-600 hover:underline">{{ __('Admin → Settings → Webcam') }}</a>:
                    </p>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1 ml-4 list-disc">
                        <li>{{ __('Display Mode: Choose Image, Stream, or Both') }}</li>
                        <li>{{ __('Image URL: URL to your webcam image') }}</li>
                        <li>{{ __('Refresh Interval: How often to refresh the image (only applies in Image mode)') }}</li>
                        <li>{{ __('Stream Type: YouTube or Restreamer') }}</li>
                        <li>{{ __('Stream URL: Your livestream URL') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- API Keys -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center">
                <span class="w-8 h-8 bg-orange-100 dark:bg-orange-900 rounded-full flex items-center justify-center mr-3 text-orange-600 dark:text-orange-400">6</span>
                {{ __('API Keys Reference') }}
            </h2>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-400">{{ __('Service') }}</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-400">{{ __('Where to get') }}</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-400">{{ __('Required?') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-white">{{ __('WeatherNode API (dashboard)') }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.api-keys.index') }}" class="text-blue-600 hover:underline">{{ __('Admin → API Keys') }}</a>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ __('Required for /api/* access') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-white">{{ __('Ecowitt API') }}</td>
                            <td class="px-4 py-3"><a href="https://www.ecowitt.net/user/site" target="_blank" class="text-blue-600 hover:underline">ecowitt.net</a></td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ __('If not using local upload') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-white">{{ __('WAQI') }}</td>
                            <td class="px-4 py-3"><a href="https://aqicn.org/data-platform/token/" target="_blank" class="text-blue-600 hover:underline">aqicn.org</a></td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ __('For air quality data') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-white">{{ __('CheckWX') }}</td>
                            <td class="px-4 py-3"><a href="https://www.checkwxapi.com/" target="_blank" class="text-blue-600 hover:underline">checkwxapi.com</a></td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ __('For METAR data') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-4">
                <strong>{{ __('Free APIs (no key required):') }}</strong>
                {{ __('Yr.no (forecasts), Luftdaten/Sensor.Community (air quality), NOAA (aurora), Open Notify (ISS), USGS (earthquakes), Meteoalarm (alerts)') }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                {{ __('Tip: The API Keys page auto-creates a public key the first time you open it. The website uses that key automatically for its own API calls.') }}
            </p>
        </div>
    </div>

    <!-- Visitor Analytics -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center">
                <span class="w-8 h-8 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center mr-3 text-indigo-600 dark:text-indigo-400">📊</span>
                {{ __('Visitor Analytics') }}
            </h2>
        </div>
        <div class="p-6 space-y-4">
            <div class="bg-gradient-to-r from-indigo-50 to-sky-50 dark:from-indigo-900/20 dark:to-sky-900/20 rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 dark:text-white mb-2">{{ __('Admin-only visitor insights') }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('View pageviews, uniques, referrers, countries, devices, browsers, search engines, and search terms at') }}
                    <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">/admin/visitors</code>.
                    {{ __('Raw IP logs are retained for 90 days; aggregates are stored indefinitely. IPs are encrypted at rest.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-2">{{ __('GeoIP (Local Database)') }}</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Download GeoLite2 Country and place it at') }}
                        <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">storage/app/private/geoip/GeoLite2-Country.mmdb</code>.
                        {{ __('Country charts will populate once the file is present.') }}
                        {{ __('For automated updates, store your license key in') }}
                        <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">MAXMIND_LICENSE_KEY</code>
                        {{ __('or upload') }}
                        <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">storage/app/private/geoip/GeoIP.conf</code>.
                        {{ __('Schedule') }}
                        <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">php artisan geoip:update</code>
                        {{ __('weekly to keep the database current.') }}
                    </p>
                </div>
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-2">{{ __('Search & Referrers') }}</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Referrer hostnames show where visitors came from. Search engines and search terms are captured when available.') }}
                        {{ __('Disable search-term storage via') }}
                        <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">VISITORLOG_STORE_SEARCH_TERMS=false</code>.
                    </p>
                </div>
            </div>

            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <h4 class="font-semibold text-gray-800 dark:text-white mb-2">{{ __('Initial Setup Checklist') }}</h4>
                <ol class="text-sm text-gray-600 dark:text-gray-400 space-y-2 list-decimal list-inside">
                    <li>{{ __('Set') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">VISITORLOG_IP_HASH_SALT</code> {{ __('in') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">.env</code></li>
                    <li>{{ __('Run') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">php artisan migrate</code></li>
                    <li>{{ __('Run') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">php artisan visitorlog:rollup</code> {{ __('to generate the first aggregate') }}</li>
                    <li>{{ __('Run') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">php artisan geoip:update</code> {{ __('to refresh the GeoLite2 database') }}</li>
                </ol>
            </div>

            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <h4 class="font-semibold text-gray-800 dark:text-white mb-2">{{ __('Retention Settings') }}</h4>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Raw logs are deleted after') }}
                    <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">VISITORLOG_RETENTION_DAYS</code>.
                    {{ __('To keep raw logs indefinitely, leave it empty or set a very large number.') }}
                </p>
            </div>
        </div>
    </div>

    <!-- Troubleshooting -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center">
                <span class="w-8 h-8 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mr-3 text-gray-600 dark:text-gray-400">?</span>
                {{ __('Troubleshooting') }}
            </h2>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <p class="font-medium text-gray-800 dark:text-white mb-1">{{ __('Dashboard shows "--" or empty cards') }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('→ Cache is empty. Run:') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">php artisan weather:poll-external --force</code><br>
                    {{ __('→ Check') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">/admin/settings/scheduler</code> {{ __('or run:') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">php artisan schedule:list</code>
                </p>
            </div>
            
            <div>
                <p class="font-medium text-gray-800 dark:text-white mb-1">{{ __('Dashboard loads slowly') }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('→ First load after PHP restart is slow (cold start) - subsequent loads should be instant') }}<br>
                    {{ __('→ If still slow, ensure the poller has filled the cache') }}
                </p>
            </div>
            
            <div>
                <p class="font-medium text-gray-800 dark:text-white mb-1">{{ __('Ecowitt local upload not working') }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('→ Verify endpoint URL in Ecowitt app matches your server') }}<br>
                    {{ __('→ Check server accepts POST to') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">{{ $ecowittPath }}</code><br>
                    {{ __('→ Check storage/logs/laravel.log for errors') }}
                </p>
            </div>
            
            <div>
                <p class="font-medium text-gray-800 dark:text-white mb-1">{{ __('API connection errors in logs') }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('→ SSL certificate issues are bypassed automatically') }}<br>
                    {{ __('→ Check API keys are configured correctly') }}<br>
                    {{ __('→ External APIs may be temporarily unavailable') }}
                </p>
            </div>
            
            <div>
                <p class="font-medium text-gray-800 dark:text-white mb-1">{{ __('500 Server Error') }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('→ Check storage/logs/laravel.log for details') }}<br>
                    {{ __('→ Ensure storage/ and bootstrap/cache/ are writable') }}<br>
                    {{ __('→ Run:') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">php artisan cache:clear</code>
                </p>
            </div>
            
            <div>
                <p class="font-medium text-gray-800 dark:text-white mb-1">{{ __('Update fails with compatibility check') }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('→ Check the detailed compatibility results on the updates page') }}<br>
                    {{ __('→ Ensure your server has write access, symlink support, and ZIP extraction') }}<br>
                    {{ __('→ Consider using manual ZIP update instead') }}<br>
                    {{ __('→ Check') }} <a href="{{ route('admin.settings.updates') }}" class="text-blue-600 hover:underline">{{ __('Admin → Settings → Updates') }}</a> {{ __('for detailed requirements') }}
                </p>
            </div>
            
            <div>
                <p class="font-medium text-gray-800 dark:text-white mb-1">{{ __('Update fails with health check') }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('→ The system automatically rolled back to the previous version') }}<br>
                    {{ __('→ Check the update history for error details') }}<br>
                    {{ __('→ Review release notes for breaking changes') }}<br>
                    {{ __('→ Contact support if the issue persists') }}
                </p>
            </div>
            
            <div>
                <p class="font-medium text-gray-800 dark:text-white mb-1">{{ __('"Class not found" error after deployment') }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('→ This often happens when bootstrap/cache/ was uploaded from dev') }}<br>
                    {{ __('→ Run:') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">rm -f bootstrap/cache/*.php</code><br>
                    {{ __('→ Then:') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">composer install --no-dev --optimize-autoloader</code><br>
                    {{ __('→ Finally:') }} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">php artisan optimize:clear</code>
                </p>
            </div>
            
            <div>
                <p class="font-medium text-gray-800 dark:text-white mb-1">{{ __('Radar shows 508 errors or not loading') }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('→ This happens on shared hosting when tile proxy is enabled') }}<br>
                    {{ __('→ Go to Settings → Radar → disable "Use server-side tile caching"') }}<br>
                    {{ __('→ The tile proxy feature only works on VPS/dedicated servers') }}<br>
                    {{ __('→ With proxy disabled, radar loads directly from RainViewer') }}
                </p>
            </div>
            
            <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Clear all caches:') }}</p>
                <div class="bg-gray-900 rounded-lg p-3">
                    <code class="text-green-400 text-sm">php artisan cache:clear && php artisan config:clear && php artisan view:clear</code>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
