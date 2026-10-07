<?php

namespace App\Console\Commands;

use App\Models\DailySummary;
use App\Models\ClimateRecord;
use App\Models\WeatherReading;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Weather\EcowittService;
use App\Services\Weather\LocalFiles\LocalFileSourceService;
use App\Services\Weather\Normalization\WeatherReadingWriter;
use App\Services\Weather\Sources\AmbientWeatherAdapter;
use App\Services\Weather\Sources\WeatherFlowAdapter;
use App\Services\Weather\Sources\WeatherLinkAdapter;
use App\Services\Weather\Sources\WeatherLinkV1Adapter;
use App\Services\Weather\Sources\WundergroundAdapter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Setting;

class FetchWeatherData extends Command
{
    protected $signature = 'weather:fetch {--save : Save reading to database}';
    protected $description = 'Fetch current weather data from the configured source';

    public function handle(
        EcowittService $ecowitt,
        LocalFileSourceService $localFiles,
        WeatherReadingWriter $writer,
        WeatherFlowAdapter $weatherFlow,
        WeatherLinkAdapter $weatherLink,
        WeatherLinkV1Adapter $weatherLinkV1,
        AmbientWeatherAdapter $ambient,
        WundergroundAdapter $wunderground
    ): int
    {
        $format = Setting::getValue('livedata.format', 'ecoLcl');
        $this->info("Fetching weather data ({$format})...");

        // Self-healing: Check source file freshness and detect errors
        $sourceStatus = $this->checkSourceStatus($format);
        $hasError = false;
        $errorMessage = null;

        $data = null;
        $fetchAttempts = 0;
        $maxAttempts = 3;

        // Self-healing: Retry on transient errors
        while ($fetchAttempts < $maxAttempts && !$data) {
            $fetchAttempts++;
            if ($fetchAttempts > 1) {
                $this->warn("Retry attempt {$fetchAttempts}/{$maxAttempts}...");
                sleep(2); // Brief delay before retry
            }

            try {
                $data = $this->fetchByFormat($format, $ecowitt, $localFiles, $weatherFlow, $weatherLink, $weatherLinkV1, $ambient, $wunderground);
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                Log::error('Weather fetch error', ['attempt' => $fetchAttempts, 'error' => $errorMessage]);
            }
        }

        if (!$data) {
            $hasError = true;
            $this->error('Failed to fetch data from configured source after ' . $maxAttempts . ' attempts.');
            
            // Alert user about persistent failure
            $this->sendAlert('Weather data fetch failed', [
                'format' => $format,
                'attempts' => $maxAttempts,
                'error' => $errorMessage,
                'source_status' => $sourceStatus,
            ]);
            
            return Command::FAILURE;
        }

        $this->info('Data received successfully!');

        $fromDb = isset($data['__from_db']) && $data['__from_db'];
        $reading = $fromDb ? $data['__reading'] : null;

        if ($this->option('save')) {
            if ($fromDb) {
                // ecoLcl with receive (push): data already in DB, only update summary/records
                $this->updateDailySummary($reading);
                $this->checkClimateRecords($reading);
                return Command::SUCCESS;
            }

            // Self-healing: Always save data (better stale data than no data)
            // But warn if source is stale and check for duplicates
            $shouldSave = true;
            $duplicateWarning = false;

            if ($sourceStatus['stale']) {
                $lastReading = WeatherReading::mostRecent();
                if ($lastReading) {
                    $timeDiff = $lastReading->recorded_at->diffInMinutes(now());
                    // Only skip if we have a very recent reading (within 2 minutes) 
                    // and the source file timestamp matches the last reading timestamp
                    if ($timeDiff < 2) {
                        // Check if data timestamp matches last reading (likely duplicate)
                        $dataTimestamp = null;
                        if (isset($data['timestamp'])) {
                            $dataTimestamp = \Carbon\Carbon::parse($data['timestamp'])->format('Y-m-d H:i:s');
                        } elseif (isset($data['recorded_at'])) {
                            $dataTimestamp = \Carbon\Carbon::parse($data['recorded_at'])->format('Y-m-d H:i:s');
                        }
                        
                        if ($dataTimestamp && $lastReading->recorded_at->format('Y-m-d H:i:s') === $dataTimestamp) {
                            $shouldSave = false;
                            $duplicateWarning = true;
                        }
                    }
                }

                if ($shouldSave) {
                    $age = $sourceStatus['age_minutes'] ?? '?';
                    $this->warn("⚠️  Saving stale data (source file {$age} minutes old)");
                } else {
                    $this->warn('⚠️  Skipping duplicate reading (same timestamp as recent reading)');
                }
            }

            if ($shouldSave) {
                try {
                    $reading = $this->saveReading($format, $data, $ecowitt, $writer);

                    if ($reading === null) {
                        throw new \RuntimeException('The configured source reported no reading to save.');
                    }

                    $this->info("Saved reading ID: {$reading->id}");
                    
                    // Clear error state on successful save
                    $this->clearErrorState($format);
                } catch (\Exception $e) {
                    $hasError = true;
                    $this->error("Failed to save reading: {$e->getMessage()}");
                    Log::error('Weather save error', ['error' => $e->getMessage()]);
                    
                    // Alert on save failure
                    $this->sendAlert('Weather data save failed', [
                        'format' => $format,
                        'error' => $e->getMessage(),
                    ]);

                    $reading = null;
                }
            }

            // Alert if source has been stale for extended period
            if ($sourceStatus['stale'] && $sourceStatus['age_minutes'] > 30) {
                $this->sendAlert('Weather source file persistently stale', [
                    'format' => $format,
                    'file_path' => $sourceStatus['file_path'] ?? 'N/A',
                    'age_minutes' => $sourceStatus['age_minutes'],
                    'last_modified' => $sourceStatus['last_modified'] ?? 'N/A',
                ]);
            }
            
            // Nothing was stored, so there is nothing to summarise. Both of the
            // calls below require a reading, and passing the unset variable
            // turned a handled save failure into a fatal scheduled task.
            if (!$reading instanceof WeatherReading) {
                return Command::FAILURE;
            }

            // Update daily summary
            $this->updateDailySummary($reading);
            
            // Check for new records
            $this->checkClimateRecords($reading);
        } else {
            $displayData = $fromDb ? $reading->toApiArray() : $data;
            $this->table(
                ['Metric', 'Value'],
                $this->formatDataForTable($displayData)
            );
        }

        return Command::SUCCESS;
    }

    /**
     * Update or create daily summary
     */
    private function updateDailySummary(WeatherReading $reading): void
    {
        $date = $reading->recorded_at->toDateString();
        $summary = DailySummary::whereDate('date', $date)->first();
        
        if (!$summary) {
            $summary = new DailySummary();
            $summary->date = $reading->recorded_at->startOfDay();
        }

        // Update high temperature
        if ($summary->temp_high === null || $reading->temperature > $summary->temp_high) {
            $summary->temp_high = $reading->temperature;
            $summary->temp_high_time = $reading->recorded_at->format('H:i:s');
        }

        // Update low temperature
        if ($summary->temp_low === null || $reading->temperature < $summary->temp_low) {
            $summary->temp_low = $reading->temperature;
            $summary->temp_low_time = $reading->recorded_at->format('H:i:s');
        }

        // Update wind max
        if ($summary->wind_max === null || $reading->wind_gust > $summary->wind_max) {
            $summary->wind_max = $reading->wind_gust;
            $summary->wind_max_time = $reading->recorded_at->format('H:i:s');
        }

        // Update rain total
        $summary->rain_total = $reading->rain_daily;

        // Update rain rate max
        if ($summary->rain_rate_max === null || $reading->rain_rate > $summary->rain_rate_max) {
            $summary->rain_rate_max = $reading->rain_rate;
        }

        // Update UV max
        if ($summary->uv_max === null || $reading->uv_index > $summary->uv_max) {
            $summary->uv_max = $reading->uv_index;
        }

        // Update solar max
        if ($summary->solar_max === null || $reading->solar_radiation > $summary->solar_max) {
            $summary->solar_max = $reading->solar_radiation;
        }

        // Sunshine hours is a running daily total from Cumulus/WD when available.
        if ($reading->solar_hours !== null
            && ($summary->solar_hours === null || $reading->solar_hours > $summary->solar_hours)) {
            $summary->solar_hours = $reading->solar_hours;
        }

        // Update humidity high/low (incremental — no extra query needed)
        if ($reading->humidity !== null) {
            if ($summary->humidity_high === null || $reading->humidity > $summary->humidity_high) {
                $summary->humidity_high = (int) $reading->humidity;
            }
            if ($summary->humidity_low === null || $reading->humidity < $summary->humidity_low) {
                $summary->humidity_low = (int) $reading->humidity;
            }
        }

        // Calculate average temperature from today's readings
        $todayReadings = WeatherReading::whereDate('recorded_at', $date)->get();
        $summary->temp_avg = $todayReadings->avg('temperature');
        $avgHumidity = $todayReadings->avg('humidity');
        $summary->humidity_avg = $avgHumidity !== null ? (int) round($avgHumidity) : null;
        $summary->wind_avg = $todayReadings->avg('wind_speed');

        $summary->save();
    }

    /**
     * Check and update climate records
     */
    private function checkClimateRecords(WeatherReading $reading): void
    {
        $month = $reading->recorded_at->month;
        $day = $reading->recorded_at->day;

        $record = ClimateRecord::firstOrNew([
            'month' => $month,
            'day' => $day,
        ]);

        $updated = $record->updateIfRecord(
            $reading->temperature,
            $reading->temperature,
            $reading->wind_gust,
            $reading->rain_daily
        );

        if ($updated) {
            Log::info("New climate record set for {$month}/{$day}");
            $this->info("New climate record set!");
        }
    }

    /**
     * Format data for table display
     */
    private function formatDataForTable(array $data): array
    {
        $rows = [];

        // The Ecowitt local file holds raw push fields (tempf, ...).
        if (isset($data['tempf'])) {
            $data = app(\App\Services\Weather\EcowittPushParser::class)->parse($data);
        }

        if (isset($data['outdoor']) || isset($data['wind']) || isset($data['pressure'])) {
            $outdoor = $data['outdoor'] ?? [];
            $wind = $data['wind'] ?? [];
            $pressure = $data['pressure'] ?? [];
            $rain = $data['rainfall'] ?? [];

            if (isset($outdoor['temperature'])) {
                $rows[] = ['Temperature', $this->getValue($outdoor['temperature']) . '°C'];
            }
            if (isset($outdoor['humidity'])) {
                $rows[] = ['Humidity', $this->getValue($outdoor['humidity']) . '%'];
            }
            if (isset($outdoor['feels_like'])) {
                $rows[] = ['Feels Like', $this->getValue($outdoor['feels_like']) . '°C'];
            }
            if (isset($wind['wind_speed'])) {
                $rows[] = ['Wind Speed', $this->getValue($wind['wind_speed']) . ' km/h'];
            }
            if (isset($wind['wind_gust'])) {
                $rows[] = ['Wind Gust', $this->getValue($wind['wind_gust']) . ' km/h'];
            }
            if (isset($wind['wind_direction'])) {
                $rows[] = ['Wind Direction', $this->getValue($wind['wind_direction']) . '°'];
            }
            if (isset($rain['daily'])) {
                $rows[] = ['Rain Today', $this->getValue($rain['daily']) . ' mm'];
            }
            if (isset($pressure['relative'])) {
                $rows[] = ['Pressure', $this->getValue($pressure['relative']) . ' hPa'];
            }
        } else {
            if (isset($data['temperature'])) {
                $rows[] = ['Temperature', $data['temperature'] . '°C'];
            }
            if (isset($data['humidity'])) {
                $rows[] = ['Humidity', $data['humidity'] . '%'];
            }
            if (isset($data['feels_like'])) {
                $rows[] = ['Feels Like', $data['feels_like'] . '°C'];
            }
            if (isset($data['wind_speed'])) {
                $rows[] = ['Wind Speed', $data['wind_speed'] . ' km/h'];
            }
            if (isset($data['wind_gust'])) {
                $rows[] = ['Wind Gust', $data['wind_gust'] . ' km/h'];
            }
            if (isset($data['wind_direction'])) {
                $rows[] = ['Wind Direction', $data['wind_direction'] . '°'];
            }
            if (isset($data['rain_daily'])) {
                $rows[] = ['Rain Today', $data['rain_daily'] . ' mm'];
            }
            if (isset($data['pressure_rel'])) {
                $rows[] = ['Pressure', $data['pressure_rel'] . ' hPa'];
            }
        }

        return $rows;
    }

    private function getValue($item): string
    {
        if (is_array($item) && isset($item['value'])) {
            return (string) $item['value'];
        }
        return (string) $item;
    }

    private function fetchByFormat(
        string $format,
        EcowittService $ecowitt,
        LocalFileSourceService $localFiles,
        WeatherFlowAdapter $weatherFlow,
        WeatherLinkAdapter $weatherLink,
        WeatherLinkV1Adapter $weatherLinkV1,
        AmbientWeatherAdapter $ambient,
        WundergroundAdapter $wunderground
    ): ?array
    {
        if (in_array($format, ['wd', 'meteohub', 'wswin', 'cumulus', 'weathercat', 'weewx', 'weatherlink', 'wifilogger', 'MB_rt'], true)) {
            return $localFiles->fetchCurrent();
        }

        if ($format === 'wu') {
            return $wunderground->fetch();
        }

        if ($format === 'wf') {
            return $weatherFlow->fetch();
        }

        if ($format === 'AWapi') {
            return $ambient->fetch();
        }

        if ($format === 'DWL') {
            return $weatherLinkV1->fetch();
        }

        if ($format === 'DWL_v2api' || $format === 'DWL_v2api_demo') {
            // Enable demo mode in settings if using demo format
            if ($format === 'DWL_v2api_demo') {
                Setting::setValue('weatherlink.demo_mode', '1', 'boolean', 'weatherlink');
            }
            return $weatherLink->fetch();
        }

        // Wunderground uploads are stored as they arrive; only the summary is left to do.
        if ($format === \App\Support\WuPush::FORMAT) {
            $reading = WeatherReading::mostRecent();
            return $reading !== null ? ['__from_db' => true, '__reading' => $reading] : null;
        }

        // ecoLcl: try file/API first; when using receive (push) only, fall back to latest from DB
        if ($format === 'ecoLcl') {
            $data = $ecowitt->fetchRealTimeData();
            if ($data !== null) {
                return $data;
            }
            $reading = WeatherReading::mostRecent();
            if ($reading !== null) {
                return ['__from_db' => true, '__reading' => $reading];
            }
            return null;
        }

        return $ecowitt->fetchRealTimeData();
    }

    private function saveReading(string $format, array $data, EcowittService $ecowitt, WeatherReadingWriter $writer): ?WeatherReading
    {
        if (in_array($format, ['wd', 'meteohub', 'wswin', 'cumulus', 'weathercat', 'weewx', 'weatherlink', 'wifilogger', 'MB_rt'], true)) {
            return $writer->store($data);
        }

        if (in_array($format, ['wu', 'wf', 'AWapi', 'DWL', 'DWL_v2api', 'DWL_v2api_demo'], true)) {
            return $writer->store($data);
        }

        return $ecowitt->saveReading($data);
    }

    /**
     * Check if format uses local file source
     */
    private function isLocalFileSource(string $format): bool
    {
        return in_array($format, ['wd', 'meteohub', 'wswin', 'cumulus', 'weathercat', 'weewx', 'weatherlink', 'wifilogger', 'MB_rt', 'ecoLcl'], true);
    }

    /**
     * Self-healing: Check source file status and detect errors
     * Returns array with status information
     */
    private function checkSourceStatus(string $format): array
    {
        $fetchMode = Setting::getValue('livedata.fetch_mode', 'file');
        
        // Only check file-based sources
        if ($fetchMode !== 'file' || !$this->isLocalFileSource($format)) {
            return ['stale' => false, 'error' => false];
        }

        $filePath = Setting::getValue('livedata.file_path', '');
        if (empty($filePath)) {
            // ecoLcl with receive (push): no file path – treat DB as source
            if ($format === 'ecoLcl') {
                $recent = WeatherReading::where('recorded_at', '>=', now()->subMinutes(15))->orderBy('id', 'desc')->first();
                if ($recent) {
                    return ['stale' => false, 'error' => false];
                }
            }
            return ['stale' => false, 'error' => true, 'error_message' => 'File path not configured'];
        }

        // A web address is fetched like an API, there is no file to check.
        if (str_starts_with($filePath, 'http://') || str_starts_with($filePath, 'https://')) {
            return ['stale' => false, 'error' => false];
        }

        // Resolve path
        $resolvedPath = str_starts_with($filePath, DIRECTORY_SEPARATOR)
            ? $filePath
            : base_path($filePath);

        if (!file_exists($resolvedPath)) {
            // ecoLcl with receive (push): file not used – treat DB as source
            if ($format === 'ecoLcl') {
                $recent = WeatherReading::where('recorded_at', '>=', now()->subMinutes(15))->orderBy('id', 'desc')->first();
                if ($recent) {
                    return ['stale' => false, 'error' => false];
                }
            }
            $this->warn("⚠️  Source file not found: {$resolvedPath}");
            return [
                'stale' => true,
                'error' => true,
                'error_message' => 'File not found',
                'file_path' => $resolvedPath,
            ];
        }

        // Check file permissions
        if (!is_readable($resolvedPath)) {
            $this->warn("⚠️  Source file not readable: {$resolvedPath}");
            return [
                'stale' => true,
                'error' => true,
                'error_message' => 'File not readable (permission denied)',
                'file_path' => $resolvedPath,
            ];
        }

        $fileTime = filemtime($resolvedPath);
        $lastModified = \Carbon\Carbon::createFromTimestamp($fileTime);
        $minutesAgo = $lastModified->diffInMinutes(now());

        $isStale = $minutesAgo > 5;

        if ($isStale) {
            $this->warn("⚠️  Source file is stale: last modified {$lastModified->diffForHumans()} ({$minutesAgo} minutes ago)");
            $this->warn("   File: {$resolvedPath}");
            
            // Log warning for monitoring
            Log::warning('Weather source file is stale', [
                'file' => $resolvedPath,
                'last_modified' => $lastModified->toDateTimeString(),
                'minutes_ago' => $minutesAgo,
            ]);
        }

        return [
            'stale' => $isStale,
            'error' => false,
            'file_path' => $resolvedPath,
            'last_modified' => $lastModified->toDateTimeString(),
            'age_minutes' => $minutesAgo,
        ];
    }

    /**
     * Send alert notification (email or webhook)
     * Checks notification settings and alert type preferences
     */
    private function sendAlert(string $subject, array $details): void
    {
        app(NotificationDispatcher::class)
            ->send($subject, $details, $this->getAlertType($subject));
    }

    /**
     * Map alert subject to alert type setting key
     */
    private function getAlertType(string $subject): ?string
    {
        $subjectLower = strtolower($subject);
        
        if (str_contains($subjectLower, 'offline') || str_contains($subjectLower, 'sensor')) {
            return 'sensor_offline';
        }
        if (str_contains($subjectLower, 'fetch failed') || str_contains($subjectLower, 'fetch error')) {
            return 'data_fetch_failed';
        }
        if (str_contains($subjectLower, 'save failed') || str_contains($subjectLower, 'save error')) {
            return 'data_save_failed';
        }
        if (str_contains($subjectLower, 'stale') || str_contains($subjectLower, 'file')) {
            return 'source_file_stale';
        }
        if (str_contains($subjectLower, 'cache') || str_contains($subjectLower, 'missing')) {
            return 'cache_missing';
        }
        if (str_contains($subjectLower, 'api') || str_contains($subjectLower, 'error')) {
            return 'api_error';
        }
        
        return null; // Unknown type, allow by default
    }

    /**
     * Clear error state (called on successful save)
     */
    private function clearErrorState(string $format): void
    {
        $errorKey = "weather_fetch_error_{$format}";
        Cache::forget($errorKey);
    }
}
