<?php

namespace App\Providers;

use App\Contracts\Nlg\Narrator;
use App\Contracts\Nlg\Rephraser;
use App\Services\Nlg\ForecastNarrator;
use App\Services\Nlg\Rephrasers\OllamaRephraser;
use App\Services\Nlg\Rephrasers\OpenAiCompatibleRephraser;
use App\Services\OpenData\AemetProvider;
use App\Services\OpenData\BomProvider;
use App\Services\OpenData\DwdProvider;
use App\Services\OpenData\EcmwfProvider;
use App\Services\OpenData\JmaProvider;
use App\Services\OpenData\KnmiProvider;
use App\Services\OpenData\MeteoFranceProvider;
use App\Services\OpenData\MetOfficeProvider;
use App\Services\OpenData\NoaaProvider;
use App\Services\Mail\MailConfigService;
use App\Services\OpenData\OpenDataProviderRegistry;
use App\Models\Setting;
use App\Support\PublicAppearance;
use App\Services\Security\ApiKeyService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind Narrator interface to ForecastNarrator
        $this->app->bind(Narrator::class, ForecastNarrator::class);

        // Bind Rephraser interface based on DB settings (optional)
        $this->app->bind(Rephraser::class, function () {
            try {
                if (!Setting::getValue('nlg.llm_enabled', false)) {
                    return null;
                }

                $provider = Setting::getValue('nlg.provider', 'openai');
                $preset = config("nlg.providers.{$provider}", config('nlg.providers.openai'));

                $baseUrl = Setting::getValue('nlg.base_url', '') ?: ($preset['base_url'] ?? '');
                $model = Setting::getValue('nlg.model', '') ?: ($preset['default_model'] ?? '');

                if (($preset['type'] ?? 'compatible') === 'ollama') {
                    return new OllamaRephraser(
                        hostUrl: $baseUrl ?: 'http://localhost:11434',
                        model: $model ?: 'llama3',
                    );
                }

                // All other providers use OpenAI-compatible chat/completions
                $apiKey = Setting::getValue('nlg.api_key', '')
                    ?: env('OPENAI_API_KEY', '')    // fallback for migration
                    ?: env('NLG_COMPAT_KEY', '');

                // Optional reasoning effort for reasoning-capable models (OpenAI o-series, and
                // various Groq / OpenRouter / Cerebras models). Empty = omit the param so plain
                // chat models are unaffected. Set per active model in Admin > Settings > NLG.
                $reasoningEffort = (string) Setting::getValue('nlg.reasoning_effort', '');

                return new OpenAiCompatibleRephraser(
                    baseUrl: $baseUrl ?: 'https://api.openai.com/v1',
                    apiKey: $apiKey,
                    model: $model ?: 'gpt-4o-mini',
                    reasoningEffort: $reasoningEffort,
                );
            } catch (Throwable $e) {
                return null;
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // migrate:fresh, migrate:refresh, migrate:reset, migrate:rollback and
        // db:wipe all destroy data, and --force skips the confirmation that
        // would otherwise stop them. Updates only ever run "migrate --force",
        // which is additive, so nothing legitimate needs these on a live
        // install and an accidental one costs the user their database.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        if (! app()->runningInConsole()) {
            $configuredAppUrl = trim((string) config('app.url', ''));
            $effectiveAppUrl = $configuredAppUrl;

            // Keep custom host ports (e.g. :8089) when APP_URL host matches the current request
            // but APP_URL does not include a port. This avoids language/alternate links dropping it.
            $requestScheme = request()->getScheme();
            $requestHost = request()->getHost();
            $requestPort = request()->getPort();
            $requestUsesNonStandardPort = ! in_array($requestPort, [80, 443], true);

            if ($configuredAppUrl === '') {
                $effectiveAppUrl = request()->getSchemeAndHttpHost();
            } else {
                $configuredHost = parse_url($configuredAppUrl, PHP_URL_HOST);
                $configuredPort = parse_url($configuredAppUrl, PHP_URL_PORT);

                if (
                    is_string($configuredHost)
                    && $configuredHost !== ''
                    && strcasecmp($configuredHost, $requestHost) === 0
                    && ! is_int($configuredPort)
                    && $requestUsesNonStandardPort
                ) {
                    $effectiveAppUrl = sprintf('%s://%s:%d', $requestScheme, $requestHost, $requestPort);
                }
            }

            if ($effectiveAppUrl !== '') {
                URL::forceRootUrl($effectiveAppUrl);

                $scheme = parse_url($effectiveAppUrl, PHP_URL_SCHEME);
                if (is_string($scheme) && $scheme !== '') {
                    URL::forceScheme($scheme);
                }
            }
        }

        // Register Open Data providers
        $this->registerOpenDataProviders();

        // Apply mail configuration
        try {
            $mailService = app(MailConfigService::class);
            $mailService->applyConfiguration();
        } catch (\Exception $e) {
            // Ignore errors during boot (settings might not be initialized yet)
        }

        if (app()->runningInConsole() && !app()->environment('testing')) {
            return;
        }

        $publicApiKey = app(ApiKeyService::class)->getOrCreatePublicKey();
        View::share('publicApiKey', $publicApiKey);

        try {
            $siteTheme = Setting::getValue('appearance.theme', 'fx');
        } catch (Throwable $e) {
            $siteTheme = 'fx';
        }
        View::share('siteTheme', $siteTheme);
        View::composer(['weather.*', 'auth.login', 'auth.initial-admin-setup', 'errors.*'], function ($view) {
            $view->with('publicAppearance', PublicAppearance::settings());
        });

        $weatherIconsPath = $siteTheme === 'flat' ? 'icons/weather-static' : 'icons/weather';
        View::share('weatherIconsPath', $weatherIconsPath);

        $weatherIconsBaseUrl = $siteTheme === 'flat' ? '/icons/weather-static' : '/icons/weather';
        View::share('weatherIconsBaseUrl', $weatherIconsBaseUrl);
    }

    /**
     * Register all Open Data providers
     */
    private function registerOpenDataProviders(): void
    {
        // Implemented providers
        OpenDataProviderRegistry::register(new KnmiProvider());
        OpenDataProviderRegistry::register(new AemetProvider());

        // Placeholder providers (coming soon)
        OpenDataProviderRegistry::register(new MetOfficeProvider());
        OpenDataProviderRegistry::register(new NoaaProvider());
        OpenDataProviderRegistry::register(new DwdProvider());
        OpenDataProviderRegistry::register(new MeteoFranceProvider());
        OpenDataProviderRegistry::register(new JmaProvider());
        OpenDataProviderRegistry::register(new EcmwfProvider());
        OpenDataProviderRegistry::register(new BomProvider());
    }
}
