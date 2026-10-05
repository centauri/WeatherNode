<?php

namespace App\Services\Telemetry;

use App\Models\Setting;
use App\Services\UserAgentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GitHubTelemetryService
{
    private string $repo;
    private string $file;
    private ?string $token;
    private string $baseUrl = 'https://api.github.com/repos/';

    public function __construct()
    {
        $this->repo = Setting::getValue('telemetry.github_repo', 'centauri/community-stations');
        $this->file = Setting::getValue('telemetry.github_file', 'stations.json');
        $this->token = Setting::getValue('telemetry.github_token', '');
    }

    /**
     * Read all stations.
     *
     * From raw.githubusercontent.com first: the API allows 60 calls an hour
     * per IP without a token, shared by every device behind one address, and
     * the page showed 0 stations once that ran out. The API is the fallback.
     * A failed read is remembered for a few minutes so page views do not keep
     * asking, and the last good list is shown meanwhile.
     */
    public function readStations(): ?array
    {
        $key = "github_stations_{$this->repo}_{$this->file}";

        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }
        if (Cache::has("{$key}_failed")) {
            return Cache::get("{$key}_last_good");
        }

        $stations = $this->readRaw() ?? $this->readApi();
        if ($stations === null) {
            Cache::put("{$key}_failed", true, now()->addMinutes(5));

            return Cache::get("{$key}_last_good");
        }

        Cache::put($key, $stations, now()->addMinutes(30));
        Cache::forever("{$key}_last_good", $stations);

        return $stations;
    }

    private function readRaw(): ?array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => UserAgentService::forExternalApi()])
                ->get("https://raw.githubusercontent.com/{$this->repo}/HEAD/{$this->file}");

            if (!$response->successful()) {
                Log::info('Could not read stations from raw GitHub', ['status' => $response->status(), 'repo' => $this->repo]);

                return null;
            }

            return $this->stationsFrom($response->body());
        } catch (\Exception $e) {
            Log::info('Exception reading stations from raw GitHub', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function readApi(): ?array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => UserAgentService::forExternalApi(),
                ])
                ->get($this->baseUrl . $this->repo . '/contents/' . $this->file);

            if (!$response->successful()) {
                Log::warning('Failed to read stations from GitHub', [
                    'status' => $response->status(),
                    'repo' => $this->repo,
                    'file' => $this->file,
                ]);

                return null;
            }

            $content = base64_decode((string) ($response->json('content') ?? ''), true);

            return $content === false || $content === '' ? null : $this->stationsFrom($content);
        } catch (\Exception $e) {
            Log::error('Exception reading stations from GitHub', ['error' => $e->getMessage(), 'repo' => $this->repo]);

            return null;
        }
    }

    private function stationsFrom(string $json): ?array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        return isset($data['stations']) && is_array($data['stations'])
            ? $data
            : ['stations' => [], 'last_updated' => null];
    }

    /**
     * Add or update a station in the GitHub repository.
     * Note: This requires a GitHub token with write access.
     * Retries automatically on SHA conflict (409) when another station
     * updated the file between our read and write.
     */
    public function addOrUpdateStation(array $stationData, int $maxRetries = 3): bool
    {
        if (empty($this->token)) {
            Log::warning('GitHub token not configured - cannot update stations');
            return false;
        }

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // Read current file + SHA in one call
                $raw = $this->readStationsRawWithSha();
                $currentData = $raw['data'];
                $fileSha = $raw['sha'];

                if ($currentData === null) {
                    $stations = [
                        'stations' => [$stationData],
                        'last_updated' => now()->toIso8601String(),
                    ];
                } else {
                    $stations = $this->mergeStation($currentData, $stationData);
                }

                // Write to GitHub
                $content = json_encode($stations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $url = $this->baseUrl . $this->repo . '/contents/' . $this->file;

                $payload = [
                    'message' => 'Update station: ' . $stationData['name'],
                    'content' => base64_encode($content),
                ];

                if ($fileSha) {
                    $payload['sha'] = $fileSha;
                }

                $response = Http::withToken($this->token)
                    ->withHeaders([
                        'Accept' => 'application/vnd.github.v3+json',
                        'User-Agent' => UserAgentService::forExternalApi(),
                    ])
                    ->put($url, $payload);

                if ($response->successful()) {
                    Cache::forget("github_stations_{$this->repo}_{$this->file}");
                    Log::info('Successfully updated station in GitHub', [
                        'station' => $stationData['name'],
                    ]);
                    return true;
                }

                // 409 Conflict = SHA mismatch (another station wrote first)
                if ($response->status() === 409 && $attempt < $maxRetries) {
                    Log::info('GitHub SHA conflict, retrying...', [
                        'attempt' => $attempt,
                        'station' => $stationData['name'],
                    ]);
                    usleep(random_int(500_000, 2_000_000)); // 0.5-2s jitter
                    continue;
                }

                Log::error('Failed to update station in GitHub', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            } catch (\Exception $e) {
                Log::error('Exception updating station in GitHub', [
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                ]);
                if ($attempt >= $maxRetries) {
                    return false;
                }
                usleep(random_int(500_000, 2_000_000));
            }
        }

        return false;
    }

    /**
     * Merge a station into the stations array (add or update, remove duplicates).
     */
    private function mergeStation(array $currentData, array $stationData): array
    {
        $stations = $currentData;
        $stationId = $stationData['id'];
        $stationUrl = $stationData['url'] ?? null;

        $found = false;
        $indicesToRemove = [];
        foreach ($stations['stations'] as $index => $station) {
            $matchesId = isset($station['id']) && $station['id'] === $stationId;
            $matchesUrl = $stationUrl && isset($station['url']) && rtrim($station['url'], '/') === rtrim($stationUrl, '/');

            if ($matchesId || $matchesUrl) {
                if (!$found) {
                    $stations['stations'][$index] = $stationData;
                    $found = true;
                } else {
                    $indicesToRemove[] = $index;
                }
            }
        }

        foreach ($indicesToRemove as $index) {
            unset($stations['stations'][$index]);
        }
        if (!empty($indicesToRemove)) {
            $stations['stations'] = array_values($stations['stations']);
        }

        if (!$found) {
            $stations['stations'][] = $stationData;
        }

        $stations['last_updated'] = now()->toIso8601String();
        return $stations;
    }

    /**
     * Remove a station from the GitHub repository
     */
    public function removeStation(string $stationId): bool
    {
        if (empty($this->token)) {
            Log::warning('GitHub token not configured - cannot remove station');
            return false;
        }

        try {
            $raw = $this->readStationsRawWithSha();

            if ($raw['data'] === null || empty($raw['data']['stations'])) {
                return true; // Already removed or doesn't exist
            }

            if (!$raw['sha']) {
                return false;
            }

            $stations = $raw['data'];
            $stations['stations'] = array_values(array_filter($stations['stations'], function ($station) use ($stationId) {
                return !isset($station['id']) || $station['id'] !== $stationId;
            }));
            $stations['last_updated'] = now()->toIso8601String();

            $url = $this->baseUrl . $this->repo . '/contents/' . $this->file;

            $response = Http::withToken($this->token)
                ->withHeaders([
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => UserAgentService::forExternalApi(),
                ])
                ->put($url, [
                    'message' => 'Remove station: ' . $stationId,
                    'content' => base64_encode(json_encode($stations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
                    'sha' => $raw['sha'],
                ]);

            if ($response->successful()) {
                Cache::forget("github_stations_{$this->repo}_{$this->file}");
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Exception removing station from GitHub', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Read stations + file SHA in a single API call (used for write operations).
     * Returns ['data' => ?array, 'sha' => ?string].
     */
    private function readStationsRawWithSha(): array
    {
        try {
            $url = $this->baseUrl . $this->repo . '/contents/' . $this->file;

            $headers = [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => UserAgentService::forExternalApi(),
            ];

            $response = $this->token
                ? Http::withToken($this->token)->withHeaders($headers)->get($url)
                : Http::withHeaders($headers)->get($url);

            if (!$response->successful()) {
                return ['data' => null, 'sha' => null];
            }

            $json = $response->json();
            $sha = $json['sha'] ?? null;

            if (!isset($json['content'])) {
                return ['data' => null, 'sha' => $sha];
            }

            $content = base64_decode($json['content'], true);
            if ($content === false) {
                return ['data' => null, 'sha' => $sha];
            }

            return [
                'data' => json_decode($content, true),
                'sha' => $sha,
            ];
        } catch (\Exception $e) {
            return ['data' => null, 'sha' => null];
        }
    }
}
