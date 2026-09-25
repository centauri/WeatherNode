<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    // Not 'Laravel'. Docker never writes a .env, so this default is what a
    // containerised install calls itself: in the admin header, the page titles
    // and anywhere else the framework prints the application name.
    'name' => env('APP_NAME', 'WeatherNode'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Containerized Deployment
    |--------------------------------------------------------------------------
    |
    | Indicates whether the app is running as a containerized deployment
    | (for example Docker). This enables container-specific UX such as
    | first-run web setup flows.
    |
    */

    'containerized' => (bool) env('CONTAINERIZED', false),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    | On production, set APP_TIMEZONE in .env to match your station timezone
    | (e.g. Europe/Amsterdam) so that scheduled polling (weather:poll-external,
    | astronomy, etc.) runs on the hour in local time and "last run" displays
    | are correct. If left unset (UTC), tasks run at :00 UTC and can appear
    | one hour behind in CET/CEST.
    |
    */

    'timezone' => env('APP_TIMEZONE', date_default_timezone_get()),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    // Falls back to the key the Docker entrypoint generates and saves when
    // APP_KEY is unset or still the compose placeholder. The compose
    // scheduler container skips the entrypoint, so it needs to read the file
    // itself. No such file exists outside Docker, so this changes nothing
    // there.
    'key' => (static function () {
        $key = (string) env('APP_KEY', '');
        if ($key !== '' && ! str_contains($key, 'REPLACE_WITH_YOUR')) {
            return $key;
        }

        $file = storage_path('app/.app-key');
        $saved = is_readable($file) ? trim((string) file_get_contents($file)) : '';

        return $saved !== '' ? $saved : ($key !== '' ? $key : null);
    })(),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
