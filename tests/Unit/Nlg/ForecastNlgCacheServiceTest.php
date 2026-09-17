<?php

namespace Tests\Unit\Nlg;

use App\Contracts\Nlg\BatchRephraser;
use App\Contracts\Nlg\Narrator;
use App\Contracts\Nlg\Rephraser;
use App\Services\Nlg\ForecastNlgCacheService;
use App\Services\Nlg\RephraseBudget;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class ForecastNlgCacheServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = new Container();
        Container::setInstance($app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        $app->instance('config', new ConfigRepository([
            'localization.locales' => [
                'nl-nl' => ['name' => 'Dutch'],
                'en-us' => ['name' => 'English'],
            ],
            'nlg.tones' => [
                'brief' => [
                    'max_sentences' => 2,
                    'max_tokens' => 60,
                    'style_notes' => 'Concise, no filler, no emojis.',
                ],
            ],
        ]));

        Cache::swap(new CacheRepository(new ArrayStore()));
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();

        parent::tearDown();
    }

    public function test_cache_drafts_for_locale_stores_both_cache_keys(): void
    {
        $service = new ForecastNlgCacheService();
        $narrator = new class implements Narrator
        {
            public function narrate(array $payload, array $options = []): string
            {
                return ($options['locale'] ?? 'unknown') . ':' . ($payload['date'] ?? 'missing-date');
            }
        };

        $entries = [
            ['date' => '2026-03-13', 'payload' => ['date' => '2026-03-13']],
        ];

        $count = $service->cacheDraftsForLocale($entries, 'nl-nl', $narrator);

        $this->assertSame(1, $count);
        $this->assertSame('nl-nl:2026-03-13', Cache::get(ForecastNlgCacheService::draftCacheKey('nl-nl', '2026-03-13', 'metric')));
        $this->assertSame('nl-nl:2026-03-13', Cache::get(ForecastNlgCacheService::finalCacheKey('nl-nl', '2026-03-13', 'metric')));
    }

    public function test_rephrase_for_locale_skips_unchanged_entries_after_success(): void
    {
        $service = new ForecastNlgCacheService();
        $narrator = new class implements Narrator
        {
            public function narrate(array $payload, array $options = []): string
            {
                return 'draft:' . ($payload['date'] ?? 'missing-date');
            }
        };

        $rephraser = new class implements Rephraser
        {
            public int $calls = 0;

            public function rewrite(string $draft, array $facts, string $tone = 'brief', ?string $locale = null): string
            {
                $this->calls++;

                return $draft . ':ai';
            }
        };

        $entries = [
            ['date' => '2026-03-13', 'payload' => ['date' => '2026-03-13']],
        ];

        $service->cacheDraftsForLocale($entries, 'en-us', $narrator);

        $first = $service->rephraseForLocale($entries, 'en-us', $narrator, $rephraser, 'brief');
        $second = $service->rephraseForLocale($entries, 'en-us', $narrator, $rephraser, 'brief');

        $this->assertSame(['updated' => 1, 'skipped' => 0, 'fallback' => 0, 'budgetExhausted' => false], $first);
        $this->assertSame(['updated' => 0, 'skipped' => 1, 'fallback' => 0, 'budgetExhausted' => false], $second);
        $this->assertSame(1, $rephraser->calls);
        $this->assertSame('draft:2026-03-13:ai', Cache::get(ForecastNlgCacheService::finalCacheKey('en-us', '2026-03-13', 'metric')));
    }

    public function test_rephrase_for_locale_force_bypasses_unchanged_hash_skip(): void
    {
        $service = new ForecastNlgCacheService();
        $narrator = new class implements Narrator
        {
            public function narrate(array $payload, array $options = []): string
            {
                return 'draft:' . ($payload['date'] ?? 'missing-date');
            }
        };

        $rephraser = new class implements Rephraser
        {
            public int $calls = 0;

            public function rewrite(string $draft, array $facts, string $tone = 'brief', ?string $locale = null): string
            {
                $this->calls++;

                return $draft . ':ai:' . $this->calls;
            }
        };

        $entries = [
            ['date' => '2026-03-13', 'payload' => ['date' => '2026-03-13']],
        ];

        $service->cacheDraftsForLocale($entries, 'en-us', $narrator);

        $first = $service->rephraseForLocale($entries, 'en-us', $narrator, $rephraser, 'brief');
        $second = $service->rephraseForLocale($entries, 'en-us', $narrator, $rephraser, 'brief', true);

        $this->assertSame(['updated' => 1, 'skipped' => 0, 'fallback' => 0, 'budgetExhausted' => false], $first);
        $this->assertSame(['updated' => 1, 'skipped' => 0, 'fallback' => 0, 'budgetExhausted' => false], $second);
        $this->assertSame(2, $rephraser->calls);
        $this->assertSame('draft:2026-03-13:ai:2', Cache::get(ForecastNlgCacheService::finalCacheKey('en-us', '2026-03-13', 'metric')));
    }

    public function test_cache_drafts_for_locale_preserves_existing_ai_final_when_draft_is_unchanged(): void
    {
        $service = new ForecastNlgCacheService();
        $narrator = new class implements Narrator
        {
            public function narrate(array $payload, array $options = []): string
            {
                return 'draft:' . ($payload['date'] ?? 'missing-date');
            }
        };

        $entries = [
            ['date' => '2026-03-13', 'payload' => ['date' => '2026-03-13']],
        ];

        $service->cacheDraftsForLocale($entries, 'en-us', $narrator);
        Cache::put(ForecastNlgCacheService::finalCacheKey('en-us', '2026-03-13', 'metric'), 'draft:2026-03-13:ai', now()->addMinutes(45));

        $service->cacheDraftsForLocale($entries, 'en-us', $narrator);

        $this->assertSame('draft:2026-03-13', Cache::get(ForecastNlgCacheService::draftCacheKey('en-us', '2026-03-13', 'metric')));
        $this->assertSame('draft:2026-03-13:ai', Cache::get(ForecastNlgCacheService::finalCacheKey('en-us', '2026-03-13', 'metric')));
    }

    public function test_rephrase_for_locale_reruns_when_hash_matches_but_final_has_fallen_back_to_draft(): void
    {
        $service = new ForecastNlgCacheService();
        $narrator = new class implements Narrator
        {
            public function narrate(array $payload, array $options = []): string
            {
                return 'draft:' . ($payload['date'] ?? 'missing-date');
            }
        };

        $rephraser = new class implements Rephraser
        {
            public int $calls = 0;

            public function rewrite(string $draft, array $facts, string $tone = 'brief', ?string $locale = null): string
            {
                $this->calls++;

                return $draft . ':ai';
            }
        };

        $entries = [
            ['date' => '2026-03-13', 'payload' => ['date' => '2026-03-13']],
        ];

        $service->cacheDraftsForLocale($entries, 'en-us', $narrator);
        $service->rephraseForLocale($entries, 'en-us', $narrator, $rephraser, 'brief');

        Cache::put(
            ForecastNlgCacheService::finalCacheKey('en-us', '2026-03-13', 'metric'),
            'draft:2026-03-13',
            now()->addMinutes(45)
        );

        $result = $service->rephraseForLocale($entries, 'en-us', $narrator, $rephraser, 'brief');

        $this->assertSame(['updated' => 1, 'skipped' => 0, 'fallback' => 0, 'budgetExhausted' => false], $result);
        $this->assertSame(2, $rephraser->calls);
        $this->assertSame('draft:2026-03-13:ai', Cache::get(ForecastNlgCacheService::finalCacheKey('en-us', '2026-03-13', 'metric')));
    }

    public function test_rephrase_for_locale_keeps_draft_when_budget_is_exhausted(): void
    {
        $service = new ForecastNlgCacheService();
        $narrator = new class implements Narrator
        {
            public function narrate(array $payload, array $options = []): string
            {
                return 'draft:' . ($payload['date'] ?? 'missing-date');
            }
        };

        $rephraser = new class implements Rephraser
        {
            public int $calls = 0;

            public function rewrite(string $draft, array $facts, string $tone = 'brief', ?string $locale = null): string
            {
                $this->calls++;

                return $draft . ':ai';
            }
        };

        // A budget that is fully exhausted — every reserve is denied.
        $budget = new class(new CacheRepository(new ArrayStore())) extends RephraseBudget
        {
            public function tryReserve(string $providerId): bool
            {
                return false;
            }
        };

        $entries = [
            ['date' => '2026-03-13', 'payload' => ['date' => '2026-03-13']],
        ];

        $service->cacheDraftsForLocale($entries, 'en-us', $narrator);

        $result = $service->rephraseForLocale($entries, 'en-us', $narrator, $rephraser, 'brief', false, $budget, 'cerebras');

        $this->assertTrue($result['budgetExhausted']);
        $this->assertSame(0, $rephraser->calls, 'rephraser must not be called when the budget is exhausted');
        $this->assertSame(
            'draft:2026-03-13',
            Cache::get(ForecastNlgCacheService::finalCacheKey('en-us', '2026-03-13', 'metric')),
            'the deterministic draft is kept as the final text',
        );
    }

    public function test_rephrase_for_locale_uses_a_single_batch_request_for_batch_rephrasers(): void
    {
        $service = new ForecastNlgCacheService();
        $narrator = new class implements Narrator
        {
            public function narrate(array $payload, array $options = []): string
            {
                return 'draft:' . ($payload['date'] ?? 'missing-date');
            }
        };

        $rephraser = new class implements Rephraser, BatchRephraser
        {
            public int $rewriteCalls = 0;

            public int $batchCalls = 0;

            public function rewrite(string $draft, array $facts, string $tone = 'brief', ?string $locale = null): string
            {
                $this->rewriteCalls++;

                return $draft . ':ai';
            }

            public function rewriteBatch(array $items, string $tone = 'brief', ?callable $reserveSlot = null, ?string $locale = null): array
            {
                $this->batchCalls++;

                $out = [];
                foreach ($items as $id => $item) {
                    if ($reserveSlot !== null) {
                        $reserveSlot();
                    }
                    $out[$id] = $item['draft'] . ':ai';
                }

                return $out;
            }
        };

        $entries = [
            ['date' => '2026-03-13', 'payload' => ['date' => '2026-03-13']],
            ['date' => '2026-03-14', 'payload' => ['date' => '2026-03-14']],
            ['date' => '2026-03-15', 'payload' => ['date' => '2026-03-15']],
        ];

        $service->cacheDraftsForLocale($entries, 'en-us', $narrator);

        $result = $service->rephraseForLocale($entries, 'en-us', $narrator, $rephraser, 'brief');

        $this->assertSame(1, $rephraser->batchCalls, 'all days for a locale must go out in one request');
        $this->assertSame(0, $rephraser->rewriteCalls, 'the per-entry path must not be used for batch rephrasers');
        $this->assertSame(['updated' => 3, 'skipped' => 0, 'fallback' => 0, 'budgetExhausted' => false], $result);
        $this->assertSame('draft:2026-03-14:ai', Cache::get(ForecastNlgCacheService::finalCacheKey('en-us', '2026-03-14', 'metric')));
    }

    public function test_batch_rephrase_keeps_drafts_and_flags_exhaustion_when_budget_denies(): void
    {
        $service = new ForecastNlgCacheService();
        $narrator = new class implements Narrator
        {
            public function narrate(array $payload, array $options = []): string
            {
                return 'draft:' . ($payload['date'] ?? 'missing-date');
            }
        };

        $rephraser = new class implements Rephraser, BatchRephraser
        {
            public function rewrite(string $draft, array $facts, string $tone = 'brief', ?string $locale = null): string
            {
                return $draft . ':ai';
            }

            // Mirror the real rephraser: a denied reserve aborts and keeps drafts.
            public function rewriteBatch(array $items, string $tone = 'brief', ?callable $reserveSlot = null, ?string $locale = null): array
            {
                if ($reserveSlot !== null && !$reserveSlot()) {
                    return array_map(fn ($item) => $item['draft'], $items);
                }

                return array_map(fn ($item) => $item['draft'] . ':ai', $items);
            }
        };

        $budget = new class(new CacheRepository(new ArrayStore())) extends RephraseBudget
        {
            public function tryReserve(string $providerId): bool
            {
                return false;
            }

            public function lastSkipReason(): ?string
            {
                return 'hour';
            }
        };

        $entries = [
            ['date' => '2026-03-13', 'payload' => ['date' => '2026-03-13']],
        ];

        $service->cacheDraftsForLocale($entries, 'en-us', $narrator);

        $result = $service->rephraseForLocale($entries, 'en-us', $narrator, $rephraser, 'brief', false, $budget, 'cerebras');

        $this->assertTrue($result['budgetExhausted']);
        $this->assertSame(
            'draft:2026-03-13',
            Cache::get(ForecastNlgCacheService::finalCacheKey('en-us', '2026-03-13', 'metric')),
            'the deterministic draft is kept when the budget denies the batch',
        );
    }

    public function test_resolve_locales_accepts_language_shorthand_and_prefers_selected_locales(): void
    {
        $service = new ForecastNlgCacheService();

        $resolved = $service->resolveLocales('nl,en', ['nl-nl', 'en-us']);

        $this->assertSame(['nl-nl', 'en-us'], $resolved);
    }

    public function test_resolve_ai_days_limit_supports_numeric_values_and_all(): void
    {
        $service = new ForecastNlgCacheService();

        $this->assertSame(3, $service->resolveAiDaysLimit(null));
        $this->assertSame(5, $service->resolveAiDaysLimit('5'));
        $this->assertNull($service->resolveAiDaysLimit('all'));
    }
}
