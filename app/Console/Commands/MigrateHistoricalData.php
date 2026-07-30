<?php

namespace App\Console\Commands;

use App\Models\DailySummary;
use App\Models\ClimateRecord;
use App\Models\WeatherReading;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class MigrateHistoricalData extends Command
{
    protected $signature = 'weather:migrate-history 
                            {source_path? : Path to the old PWS-Dashboard public_html folder}
                            {--daily-only : Only import daily summaries, skip detailed readings}
                            {--year= : Only import data from specific year}';

    protected $description = 'Import historical weather data from PWS-Dashboard metric files';

    protected int $imported = 0;
    protected int $skipped = 0;
    protected int $updated = 0;

    public function handle(): int
    {
        $sourcePath = $this->argument('source_path') ?? '/Users/pauladmiraal/DEV/meteouitgeest_current/public_html';
        $wudataPath = $sourcePath . '/wudata';

        $this->info("🌤️  WeatherNode Historical Data Migration");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("Source: {$wudataPath}");
        $this->newLine();

        if (!File::isDirectory($wudataPath)) {
            $this->error("wudata folder not found at: {$wudataPath}");
            return Command::FAILURE;
        }

        // First, import from metric array files (most comprehensive data)
        $this->info("📊 Importing from metric files...");
        $this->importFromMetricFiles($wudataPath);

        // Then, supplement with daily detail files
        if (!$this->option('daily-only')) {
            $this->newLine();
            $this->info("📅 Importing detailed readings from daily files...");
            $this->importFromDailyFiles($wudataPath);
        }

        $this->newLine();
        $this->info("✅ Migration complete!");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Daily Summaries Imported', $this->imported],
                ['Daily Summaries Updated', $this->updated],
                ['Skipped', $this->skipped],
                ['Total in Database', DailySummary::count()],
                ['Climate Records', ClimateRecord::count()],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Import from IUITGE8-metric-YYYY.arr files (serialized PHP arrays)
     * 
     * Format: a:N:{i:YYYYMMDD;s:XX:"YYYY-MM-DD,TempHigh,TempAvg,TempLow,DewHigh,DewAvg,DewLow,HumHigh,HumAvg,HumLow,PressMax,PressMin,WindMax,WindAvg,GustMax,Rain";...}
     */
    protected function importFromMetricFiles(string $wudataPath): void
    {
        $targetYear = $this->option('year');
        
        // Find all metric files
        $metricFiles = File::glob($wudataPath . '/IUITGE8-metric-*.arr');
        
        if (empty($metricFiles)) {
            $this->warn("No metric files found in {$wudataPath}");
            return;
        }

        $bar = $this->output->createProgressBar(count($metricFiles));
        $bar->start();

        foreach ($metricFiles as $metricFile) {
            // Extract year from filename
            if (preg_match('/metric-(\d{4})\.arr$/', $metricFile, $matches)) {
                $year = $matches[1];
                
                if ($targetYear && $year !== $targetYear) {
                    $bar->advance();
                    continue;
                }

                $this->processMetricFile($metricFile, $year);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Process a single metric file
     */
    protected function processMetricFile(string $filePath, string $year): void
    {
        $content = @file_get_contents($filePath);
        if (!$content) {
            $this->skipped++;
            return;
        }

        // Unserialize the PHP array
        $data = @unserialize($content);
        if (!is_array($data)) {
            $this->warn("  Could not unserialize: " . basename($filePath));
            $this->skipped++;
            return;
        }

        foreach ($data as $dateKey => $csvLine) {
            try {
                // Parse the CSV line
                // Format: Date,TempHigh,TempAvg,TempLow,DewHigh,DewAvg,DewLow,HumHigh,HumAvg,HumLow,PressMax,PressMin,WindMax,WindAvg,GustMax,Rain
                $parts = explode(',', $csvLine);
                
                if (count($parts) < 16) {
                    continue;
                }

                $date = $parts[0];
                
                // Validate date format
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    continue;
                }

                $summaryData = [
                    'date' => $date,
                    'temp_high' => $this->parseValue($parts[1]),
                    'temp_avg' => $this->parseValue($parts[2]),
                    'temp_low' => $this->parseValue($parts[3]),
                    // Dewpoint: parts[4], parts[5], parts[6]
                    'humidity_high' => $this->parseIntValue($parts[7]),
                    'humidity_avg' => $this->parseIntValue($parts[8]),
                    'humidity_low' => $this->parseIntValue($parts[9]),
                    'pressure_high' => $this->parseValue($parts[10]),
                    'pressure_low' => $this->parseValue($parts[11]),
                    'wind_max' => $this->parseValue($parts[14]), // Gust max
                    'wind_avg' => $this->parseValue($parts[13]),
                    'rain_total' => $this->parseValue($parts[15]) * 10, // Convert cm to mm
                ];

                // Create or update daily summary
                $existing = DailySummary::where('date', $date)->first();
                
                if ($existing) {
                    $existing->update($summaryData);
                    $this->updated++;
                } else {
                    DailySummary::create($summaryData);
                    $this->imported++;
                }

                // Update climate records
                $this->updateClimateRecords($summaryData);

            } catch (\Exception $e) {
                $this->skipped++;
                continue;
            }
        }
    }

    /**
     * Import from daily detail files (YYYYMM-daily folders)
     */
    protected function importFromDailyFiles(string $wudataPath): void
    {
        $targetYear = $this->option('year');
        $years = File::directories($wudataPath);

        foreach ($years as $yearPath) {
            $year = basename($yearPath);
            
            if (!is_numeric($year)) {
                continue;
            }
            
            if ($targetYear && $year !== $targetYear) {
                continue;
            }

            $this->info("  Year: {$year}");

            // Find all daily data folders
            $dailyFolders = File::directories($yearPath);
            
            foreach ($dailyFolders as $dailyFolder) {
                if (!str_contains($dailyFolder, '-daily')) {
                    continue;
                }

                $this->processMonthFolder($dailyFolder);
            }
        }
    }

    /**
     * Process a month's daily data folder
     */
    protected function processMonthFolder(string $folderPath): void
    {
        $files = File::files($folderPath);
        
        if (empty($files)) {
            return;
        }

        foreach ($files as $file) {
            if ($file->getExtension() !== 'txt') {
                continue;
            }

            $filename = $file->getFilename();
            
            // Extract date from filename: IUITGE8-day-20241208.txt
            if (preg_match('/day-(\d{8})\.txt$/', $filename, $matches)) {
                $dateStr = $matches[1];
                $date = Carbon::createFromFormat('Ymd', $dateStr)->format('Y-m-d');
                
                $this->processDayFile($file->getPathname(), $date);
            }
        }
    }

    /**
     * Process a single day's data file and store detailed readings
     */
    protected function processDayFile(string $filePath, string $date): void
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return;
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return;
        }

        $batch = [];
        $temperatures = [];
        $humidities = [];
        $pressures = [];
        $windSpeeds = [];
        $windGusts = [];
        $lastRainDaily = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue;
            }

            $data = array_combine($headers, $row);
            $time = $data['Time'] ?? null;
            
            if (!$time || $time === 'Time') {
                continue;
            }

            try {
                $recordedAt = Carbon::parse($time);
            } catch (\Exception $e) {
                continue;
            }

            // Collect for stats
            $temp = $this->parseValue($data['TemperatureC'] ?? null);
            if ($temp !== null) $temperatures[] = $temp;
            
            $hum = $this->parseIntValue($data['Humidity'] ?? null);
            if ($hum !== null) $humidities[] = $hum;
            
            $pres = $this->parseValue($data['PressurehPa'] ?? null);
            if ($pres !== null) $pressures[] = $pres;
            
            $wind = $this->parseValue($data['WindSpeedKMH'] ?? null);
            if ($wind !== null) $windSpeeds[] = $wind;
            
            $gust = $this->parseValue($data['WindSpeedGustKMH'] ?? null);
            if ($gust !== null) $windGusts[] = $gust;

            $rainDaily = $this->parseValue($data['dailyrainMM'] ?? null);
            if ($rainDaily !== null) $lastRainDaily = $rainDaily;

            $batch[] = [
                'recorded_at' => $recordedAt,
                'temperature' => $temp,
                'dew_point' => $this->parseValue($data['DewpointC'] ?? null),
                'humidity' => $hum,
                'pressure_rel' => $pres,
                'wind_speed' => $wind,
                'wind_gust' => $gust,
                'wind_direction' => $this->parseIntValue($data['WindDirectionDegrees'] ?? null),
                'rain_hourly' => $this->parseValue($data['HourlyPrecipMM'] ?? null),
                'rain_daily' => $rainDaily,
                'solar_radiation' => $this->parseValue($data['SolarRadiationWatts/m^2'] ?? null),
                'uv_index' => $this->parseValue($data['UV'] ?? null),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Insert in batches of 500
            if (count($batch) >= 500) {
                // Only insert if not already existing
                WeatherReading::insertOrIgnore($batch);
                $batch = [];
            }
        }

        fclose($handle);

        // Insert remaining
        if (!empty($batch)) {
            WeatherReading::insertOrIgnore($batch);
        }

        // Update daily summary if we have enough data
        if (!empty($temperatures) && count($temperatures) > 10) {
            $summary = DailySummary::where('date', $date)->first();
            
            if (!$summary) {
                DailySummary::create([
                    'date' => $date,
                    'temp_high' => max($temperatures),
                    'temp_low' => min($temperatures),
                    'temp_avg' => round(array_sum($temperatures) / count($temperatures), 2),
                    'humidity_high' => !empty($humidities) ? max($humidities) : null,
                    'humidity_low' => !empty($humidities) ? min($humidities) : null,
                    'humidity_avg' => !empty($humidities) ? round(array_sum($humidities) / count($humidities)) : null,
                    'pressure_high' => !empty($pressures) ? max($pressures) : null,
                    'pressure_low' => !empty($pressures) ? min($pressures) : null,
                    'wind_avg' => !empty($windSpeeds) ? round(array_sum($windSpeeds) / count($windSpeeds), 1) : null,
                    'wind_max' => !empty($windGusts) ? max($windGusts) : null,
                    'rain_total' => $lastRainDaily,
                ]);
                $this->imported++;
            }
        }
    }

    /**
     * Update climate records for the day
     */
    protected function updateClimateRecords(array $data): void
    {
        $date = Carbon::parse($data['date']);
        $month = $date->month;
        $day = $date->day;
        $year = $date->year;

        $record = ClimateRecord::firstOrNew([
            'month' => $month,
            'day' => $day,
        ]);

        $tempHigh = $data['temp_high'] ?? null;
        $tempLow = $data['temp_low'] ?? null;
        $windMax = $data['wind_max'] ?? null;
        $rainTotal = $data['rain_total'] ?? null;

        // Update record high
        if ($tempHigh !== null && ($record->record_high === null || $tempHigh > $record->record_high)) {
            $record->record_high = $tempHigh;
            $record->record_high_year = $year;
        }

        // Update record low
        if ($tempLow !== null && ($record->record_low === null || $tempLow < $record->record_low)) {
            $record->record_low = $tempLow;
            $record->record_low_year = $year;
        }

        // Update record wind
        if ($windMax !== null && ($record->record_wind === null || $windMax > $record->record_wind)) {
            $record->record_wind = $windMax;
            $record->record_wind_year = $year;
        }

        // Update record rain
        if ($rainTotal !== null && ($record->record_rain === null || $rainTotal > $record->record_rain)) {
            $record->record_rain = $rainTotal;
            $record->record_rain_year = $year;
        }

        // Calculate running averages
        if ($tempHigh !== null && $tempLow !== null) {
            $avgCount = $record->avg_count ?? 0;
            $record->avg_high = $record->avg_high
                ? (($record->avg_high * $avgCount) + $tempHigh) / ($avgCount + 1)
                : $tempHigh;
            $record->avg_low = $record->avg_low
                ? (($record->avg_low * $avgCount) + $tempLow) / ($avgCount + 1)
                : $tempLow;
            $record->avg_temp = ($record->avg_high + $record->avg_low) / 2;
            $record->avg_count = $avgCount + 1;
        }

        if ($rainTotal !== null) {
            $rainCount = $record->rain_count ?? 0;
            $record->avg_precipitation = $record->avg_precipitation
                ? (($record->avg_precipitation * $rainCount) + $rainTotal) / ($rainCount + 1)
                : $rainTotal;
            $record->rain_count = $rainCount + 1;
        }

        $record->save();
    }

    protected function parseValue(?string $value): ?float
    {
        if ($value === null || $value === '' || $value === 'unknown' || $value === '--') {
            return null;
        }
        $val = floatval($value);
        return ($val == 0 && $value !== '0' && $value !== '0.0' && $value !== '0.00') ? null : $val;
    }

    protected function parseIntValue(?string $value): ?int
    {
        if ($value === null || $value === '' || $value === 'unknown' || $value === '--') {
            return null;
        }
        return intval($value);
    }
}
