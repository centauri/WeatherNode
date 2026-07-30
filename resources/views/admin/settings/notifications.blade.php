@extends('layouts.admin')

@section('title', __('Notifications Settings'))

@section('content')
<div class="w-full">
    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">{{ __('Notifications') }}</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-4">
            <div class="p-3 rounded-xl bg-red-100 dark:bg-red-900/30">
                <svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Notifications') }}</h1>
                <p class="text-gray-500 dark:text-gray-400">{{ __('Configure alert methods and notification preferences') }}</p>
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

    <form action="{{ route('admin.settings.notifications.update') }}" method="POST" class="space-y-6">
        @csrf
        
        <!-- Global Enable Toggle -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Enable Notifications') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Receive alerts when system issues are detected') }}</p>
                    </div>
                    <x-toggle-switch
                        :enabled="($settings['enabled'] ?? false)"
                        name="notifications_enabled"
                        :labelEnabled="__('Enabled')"
                        :labelDisabled="__('Disabled')"
                    />
                </div>
            </div>
        </div>

        <!-- Notification Method -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Notification Method') }}</label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Choose how you want to receive notifications') }}</p>
            </div>
            <div class="p-5 space-y-4">
                <div class="space-y-3">
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="radio" name="method" value="email" 
                               {{ ($settings['method'] ?? 'email') === 'email' ? 'checked' : '' }}
                               class="w-4 h-4 text-red-600 focus:ring-red-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                        <div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Email') }}</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Receive notifications via email') }}</p>
                        </div>
                    </label>
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="radio" name="method" value="webhook" 
                               {{ ($settings['method'] ?? 'email') === 'webhook' ? 'checked' : '' }}
                               class="w-4 h-4 text-red-600 focus:ring-red-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                        <div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Webhook') }}</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Send notifications to a webhook URL (for integrations)') }}</p>
                        </div>
                    </label>
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="radio" name="method" value="both" 
                               {{ ($settings['method'] ?? 'email') === 'both' ? 'checked' : '' }}
                               class="w-4 h-4 text-red-600 focus:ring-red-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                        <div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Both') }}</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Send via email and webhook') }}</p>
                        </div>
                    </label>
                </div>

                <!-- Email Configuration -->
                <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('Email Address') }}</label>
                    <input type="email" name="email" value="{{ old('email', $settings['email'] ?? '') }}" 
                           placeholder="your-email@example.com"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ __('Configure mail provider in') }} 
                        <a href="{{ route('admin.settings.mail') }}" class="text-blue-600 hover:underline">{{ __('Admin → Settings → Mail') }}</a>
                    </p>
                </div>

                <!-- Webhook Configuration -->
                <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('Webhook URL') }}</label>
                    <input type="url" name="webhook_url" value="{{ old('webhook_url', $settings['webhook_url'] ?? '') }}" 
                           placeholder="https://hooks.example.com/notifications"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Webhook will receive POST requests with JSON payload') }}</p>
                </div>
            </div>
        </div>

        <!-- Alert Types -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('What to Notify About') }}</label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Select which events should trigger notifications') }}</p>
            </div>
            <div class="p-5 space-y-4">
                <div class="flex items-center justify-between py-2">
                    <div>
                        <label class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Sensor Offline') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('When weather station stops sending data') }}</p>
                    </div>
                    <x-toggle-switch
                        :enabled="($settings['sensor_offline'] ?? true)"
                        name="sensor_offline"
                    />
                </div>

                <div class="flex items-center justify-between py-2 border-t border-gray-100 dark:border-gray-700">
                    <div>
                        <label class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Data Fetch Failed') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('When fetching data from source fails') }}</p>
                    </div>
                    <x-toggle-switch
                        :enabled="($settings['data_fetch_failed'] ?? true)"
                        name="data_fetch_failed"
                    />
                </div>

                <div class="flex items-center justify-between py-2 border-t border-gray-100 dark:border-gray-700">
                    <div>
                        <label class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Data Save Failed') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('When saving weather data to database fails') }}</p>
                    </div>
                    <x-toggle-switch
                        :enabled="($settings['data_save_failed'] ?? true)"
                        name="data_save_failed"
                    />
                </div>

                <div class="flex items-center justify-between py-2 border-t border-gray-100 dark:border-gray-700">
                    <div>
                        <label class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Source File Stale') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('When local data file hasn\'t been updated in 30+ minutes') }}</p>
                    </div>
                    <x-toggle-switch
                        :enabled="($settings['source_file_stale'] ?? true)"
                        name="source_file_stale"
                    />
                </div>

                <div class="flex items-center justify-between py-2 border-t border-gray-100 dark:border-gray-700">
                    <div>
                        <label class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Cache Missing') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('When critical cache data is missing (forecast, AQ, etc.)') }}</p>
                    </div>
                    <x-toggle-switch
                        :enabled="($settings['cache_missing'] ?? false)"
                        name="cache_missing"
                    />
                </div>

                <div class="flex items-center justify-between py-2 border-t border-gray-100 dark:border-gray-700">
                    <div>
                        <label class="text-sm font-medium text-gray-900 dark:text-white">{{ __('API Error') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('When external API calls fail repeatedly') }}</p>
                    </div>
                    <x-toggle-switch
                        :enabled="($settings['api_error'] ?? false)"
                        name="api_error"
                    />
                </div>
            </div>
        </div>

        <!-- Info Box -->
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="text-sm text-blue-800 dark:text-blue-200">
                    <p class="font-medium mb-1">{{ __('Email Configuration') }}</p>
                    <p class="text-xs mb-2">{{ __('To receive email notifications, configure your mail provider:') }}</p>
                    <div class="space-y-2 text-xs">
                        <p><strong>{{ __('Modern Method (Recommended):') }}</strong></p>
                        <p class="ml-4">
                            {{ __('Go to') }} 
                            <a href="{{ route('admin.settings.mail') }}" class="text-blue-700 dark:text-blue-300 hover:underline font-medium">{{ __('Admin → Settings → Mail') }}</a>
                            {{ __('and configure:') }}
                        </p>
                        <ul class="ml-8 list-disc space-y-1">
                            <li><strong>{{ __('OAuth2') }}:</strong> {{ __('For Gmail and Microsoft/Office 365. Enter Client ID/Secret, click "Authorize". Token refresh is automatic.') }}</li>
                            <li><strong>{{ __('Predefined SMTP') }}:</strong> {{ __('Brevo (EU), Mailjet (EU), Postmark, Mailgun, or SMTP2Go. Just enter your credentials.') }}</li>
                            <li><strong>{{ __('Custom SMTP') }}:</strong> {{ __('Manual configuration for any other SMTP server.') }}</li>
                        </ul>
                        <p class="mt-3 text-xs opacity-75"><strong>{{ __('Legacy .env Method (still supported):') }}</strong></p>
                        <pre class="mt-1 text-xs bg-blue-100 dark:bg-blue-800 rounded p-2 overflow-x-auto">MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="WeatherNode"</pre>
                        <p class="mt-2 text-xs opacity-75">{{ __('Note: Gmail and Microsoft require OAuth2. App passwords are deprecated.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors">
                {{ __('Save Settings') }}
            </button>
        </div>
    </form>
</div>
@endsection
