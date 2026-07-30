<?php

namespace App\Models;

use App\Support\WindCompass;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeatherReading extends Model
{
    use HasFactory;

    protected $fillable = [
        'recorded_at',
        
        // Core temperature data
        'temperature',
        'temperature_indoor',
        'feels_like',
        'dew_point',
        'heat_index',
        'wind_chill',
        'wet_bulb',
        
        // Humidity
        'humidity',
        'humidity_indoor',
        'indoor_temperature',  // Legacy alias
        'indoor_humidity',     // Legacy alias
        
        // Extra temperature sensors (1-8)
        'temp_1', 'temp_2', 'temp_3', 'temp_4',
        'temp_5', 'temp_6', 'temp_7', 'temp_8',
        
        // Extra humidity sensors (1-8)
        'humidity_1', 'humidity_2', 'humidity_3', 'humidity_4',
        'humidity_5', 'humidity_6', 'humidity_7', 'humidity_8',
        
        // Pressure
        'pressure_abs',
        'pressure_rel',
        
        // Wind
        'wind_speed',
        'wind_gust',
        'wind_direction',
        'wind_speed_avg_10m',
        'wind_direction_avg_10m',
        'wind_gust_max_daily',
        
        // Rain
        'rain_rate',
        'rain_hourly',
        'rain_daily',
        'rain_weekly',
        'rain_monthly',
        'rain_yearly',
        'rain_event',
        'rain_total',
        
        // Solar & UV
        'uv_index',
        'solar_radiation',
        'solar_hours',
        'lux',
        
        // Soil sensors (1-8)
        'soil_temperature',  // Legacy single
        'soil_moisture',     // Legacy single
        'soil_temp_1', 'soil_temp_2', 'soil_temp_3', 'soil_temp_4',
        'soil_temp_5', 'soil_temp_6', 'soil_temp_7', 'soil_temp_8',
        'soil_moisture_1', 'soil_moisture_2', 'soil_moisture_3', 'soil_moisture_4',
        'soil_moisture_5', 'soil_moisture_6', 'soil_moisture_7', 'soil_moisture_8',
        
        // Leaf wetness sensors (1-8)
        'leaf_wetness_1', 'leaf_wetness_2', 'leaf_wetness_3', 'leaf_wetness_4',
        'leaf_wetness_5', 'leaf_wetness_6', 'leaf_wetness_7', 'leaf_wetness_8',
        
        // Water temperature
        'water_temperature',
        
        // PM2.5 air quality (channels 1-4)
        'pm25_ch1', 'pm25_ch2', 'pm25_ch3', 'pm25_ch4',
        'pm25_avg_24h_ch1', 'pm25_avg_24h_ch2', 'pm25_avg_24h_ch3', 'pm25_avg_24h_ch4',
        
        // PM10 air quality
        'pm10',
        'pm10_avg_24h',
        
        // CO2 sensor
        'co2',
        'co2_avg_24h',
        'co2_temp',
        'co2_humidity',
        
        // Water leak sensors (1-4)
        'leak_ch1', 'leak_ch2', 'leak_ch3', 'leak_ch4',
        
        // Lightning
        'lightning_distance',
        'lightning_time',
        'lightning_count',
        'lightning_count_daily',
        
        // Battery / sensor status
        'battery_status',
        
        // Station info
        'station_type',
        'station_model',
        'station_runtime',
        'station_freq',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'lightning_time' => 'datetime',
        'battery_status' => 'array',
        
        // Floats
        'temperature' => 'float',
        'temperature_indoor' => 'float',
        'feels_like' => 'float',
        'dew_point' => 'float',
        'heat_index' => 'float',
        'wind_chill' => 'float',
        'wet_bulb' => 'float',
        'humidity' => 'float',
        'humidity_indoor' => 'float',
        'pressure_rel' => 'float',
        'pressure_abs' => 'float',
        'wind_speed' => 'float',
        'wind_gust' => 'float',
        'wind_speed_avg_10m' => 'float',
        'wind_gust_max_daily' => 'float',
        'rain_rate' => 'float',
        'rain_hourly' => 'float',
        'rain_daily' => 'float',
        'rain_weekly' => 'float',
        'rain_monthly' => 'float',
        'rain_yearly' => 'float',
        'rain_event' => 'float',
        'rain_total' => 'float',
        'uv_index' => 'float',
        'solar_radiation' => 'float',
        'solar_hours' => 'float',
        'water_temperature' => 'float',
        
        // Extra temps
        'temp_1' => 'float', 'temp_2' => 'float', 'temp_3' => 'float', 'temp_4' => 'float',
        'temp_5' => 'float', 'temp_6' => 'float', 'temp_7' => 'float', 'temp_8' => 'float',
        
        // Soil temps
        'soil_temp_1' => 'float', 'soil_temp_2' => 'float', 'soil_temp_3' => 'float', 'soil_temp_4' => 'float',
        'soil_temp_5' => 'float', 'soil_temp_6' => 'float', 'soil_temp_7' => 'float', 'soil_temp_8' => 'float',
        
        // PM2.5
        'pm25_ch1' => 'float', 'pm25_ch2' => 'float', 'pm25_ch3' => 'float', 'pm25_ch4' => 'float',
        'pm25_avg_24h_ch1' => 'float', 'pm25_avg_24h_ch2' => 'float', 'pm25_avg_24h_ch3' => 'float', 'pm25_avg_24h_ch4' => 'float',
        
        // PM10
        'pm10' => 'float',
        'pm10_avg_24h' => 'float',
        
        // CO2 temp
        'co2_temp' => 'float',
        
        // Integers
        'wind_direction' => 'integer',
        'wind_direction_avg_10m' => 'integer',
        'lux' => 'integer',
        'co2' => 'integer',
        'co2_avg_24h' => 'integer',
        'co2_humidity' => 'integer',
        'lightning_distance' => 'integer',
        'lightning_count' => 'integer',
        'lightning_count_daily' => 'integer',
        'station_runtime' => 'integer',
        
        // Booleans
        'leak_ch1' => 'boolean', 'leak_ch2' => 'boolean', 'leak_ch3' => 'boolean', 'leak_ch4' => 'boolean',
    ];

    /**
     * Get the most recent reading
     */
    public static function mostRecent(): ?self
    {
        return static::orderBy('recorded_at', 'desc')->first();
    }

    /**
     * Get readings within a date range
     */
    public function scopeInDateRange($query, $start, $end)
    {
        return $query->whereBetween('recorded_at', [$start, $end]);
    }

    /**
     * Get today's readings
     */
    public function scopeToday($query)
    {
        return $query->whereDate('recorded_at', today());
    }

    /**
     * Get wind direction as compass point (English keys for i18n).
     */
    public function getWindDirectionCompassAttribute(): string
    {
        return WindCompass::fromDegrees($this->wind_direction !== null ? (float) $this->wind_direction : null);
    }

    /**
     * Alias kept for backwards compatibility; same as wind_direction_compass.
     */
    public function getWindDirectionCompassEnAttribute(): string
    {
        return $this->wind_direction_compass;
    }

    /**
     * Get Beaufort scale from wind speed (km/h)
     */
    public function getBeaufortAttribute(): int
    {
        $speed = $this->wind_speed ?? 0;
        if ($speed < 1) return 0;
        if ($speed < 6) return 1;
        if ($speed < 12) return 2;
        if ($speed < 20) return 3;
        if ($speed < 29) return 4;
        if ($speed < 39) return 5;
        if ($speed < 50) return 6;
        if ($speed < 62) return 7;
        if ($speed < 75) return 8;
        if ($speed < 89) return 9;
        if ($speed < 103) return 10;
        if ($speed < 117) return 11;
        return 12;
    }

    /**
     * Get Beaufort scale description (English keys for i18n).
     */
    public function getBeaufortDescriptionAttribute(): string
    {
        $descriptions = [
            0 => 'Calm',
            1 => 'Light air',
            2 => 'Light breeze',
            3 => 'Gentle breeze',
            4 => 'Moderate breeze',
            5 => 'Fresh breeze',
            6 => 'Strong breeze',
            7 => 'Near gale',
            8 => 'Gale',
            9 => 'Strong gale',
            10 => 'Storm',
            11 => 'Violent storm',
            12 => 'Hurricane',
        ];
        return $descriptions[$this->beaufort] ?? 'Unknown';
    }

    /**
     * Get UV level description (English keys for i18n).
     */
    public function getUvLevelAttribute(): string
    {
        $uv = $this->uv_index ?? 0;
        if ($uv < 3) return 'Low';
        if ($uv < 6) return 'Moderate';
        if ($uv < 8) return 'High';
        if ($uv < 11) return 'Very High';
        return 'Extreme';
    }

    /**
     * Get air quality level from PM2.5 (English keys for i18n / AQI lookup tables).
     */
    public function getPm25LevelAttribute(): ?string
    {
        $pm25 = $this->pm25_ch1;
        if ($pm25 === null) return null;
        
        if ($pm25 <= 12) return 'Good';
        if ($pm25 <= 35.4) return 'Moderate';
        if ($pm25 <= 55.4) return 'Unhealthy for Sensitive Groups';
        if ($pm25 <= 150.4) return 'Unhealthy';
        if ($pm25 <= 250.4) return 'Very Unhealthy';
        return 'Hazardous';
    }

    /**
     * Get pressure trend based on recent readings
     */
    public function getPressureTrendAttribute(): ?string
    {
        $previousReading = static::where('recorded_at', '<', $this->recorded_at)
            ->where('recorded_at', '>=', $this->recorded_at->copy()->subHours(3))
            ->orderBy('recorded_at', 'asc')
            ->first();
        
        $prev = $previousReading?->pressure_rel ?? $previousReading?->pressure_abs;
        $cur = $this->pressure_rel ?? $this->pressure_abs;

        if ($prev === null || $cur === null) {
            return null;
        }
        
        $diff = $cur - $prev;

        // Return English keys so __() can translate in API/views per locale
        if ($diff > 1.5) return 'Rising Rapidly';
        if ($diff > 0.5) return 'Rising';
        if ($diff < -1.5) return 'Falling Rapidly';
        if ($diff < -0.5) return 'Falling';
        return 'Stable';
    }

    /**
     * Check if any extra temperature sensors have data
     */
    public function hasExtraTemperatureSensors(): bool
    {
        for ($i = 1; $i <= 8; $i++) {
            if ($this->{"temp_{$i}"} !== null) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all active extra temperature sensors
     */
    public function getExtraTemperaturesAttribute(): array
    {
        $temps = [];
        for ($i = 1; $i <= 8; $i++) {
            $value = $this->{"temp_{$i}"};
            if ($value !== null) {
                $temps["temp_{$i}"] = $value;
            }
        }
        return $temps;
    }

    /**
     * Check if soil sensors have data
     */
    public function hasSoilSensors(): bool
    {
        for ($i = 1; $i <= 8; $i++) {
            if ($this->{"soil_moisture_{$i}"} !== null || $this->{"soil_temp_{$i}"} !== null) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all active soil sensors
     */
    public function getSoilSensorsAttribute(): array
    {
        $sensors = [];
        for ($i = 1; $i <= 8; $i++) {
            $moisture = $this->{"soil_moisture_{$i}"};
            $temp = $this->{"soil_temp_{$i}"};
            if ($moisture !== null || $temp !== null) {
                $sensors[$i] = [
                    'moisture' => $moisture,
                    'temperature' => $temp,
                ];
            }
        }
        return $sensors;
    }

    /**
     * Check if PM2.5 sensors have data
     */
    public function hasPm25Sensors(): bool
    {
        return $this->pm25_ch1 !== null || $this->pm25_ch2 !== null || 
               $this->pm25_ch3 !== null || $this->pm25_ch4 !== null;
    }

    /**
     * Check if any leak sensors are triggered
     */
    public function hasLeakAlert(): bool
    {
        return $this->leak_ch1 || $this->leak_ch2 || $this->leak_ch3 || $this->leak_ch4;
    }

    /**
     * Get lightning time ago in human-readable format
     */
    public function getLightningTimeAgoAttribute(): ?string
    {
        if (!$this->lightning_time) {
            return null;
        }
        return $this->lightning_time->diffForHumans();
    }

    /**
     * Best-effort timestamp of the most recent lightning strike.
     *
     * Prefers a sensor-provided strike time. When the feed only reports a daily
     * strike counter (no per-strike timestamp), derive it from the most recent
     * moment that counter increased. Only a genuine increase is reliable — some
     * feeds report the "count" as a running daily total, so "> 0" never resets and
     * would keep the effect running until midnight. Drives the dashboard lightning
     * effect's recency window.
     */
    public static function lastStrikeTime(): ?\Illuminate\Support\Carbon
    {
        $latest = static::query()->latest('recorded_at')->first();
        if (!$latest) {
            return null;
        }

        // Derive the strike time from the most recent increase of the daily counter.
        // This is the authoritative signal: the count keeps incrementing during a
        // storm even when the sensor's own lightning_time sticks on an earlier strike.
        $readings = static::query()
            ->where('recorded_at', '>=', now()->subHours(2))
            ->whereNotNull('lightning_count_daily')
            ->orderBy('recorded_at')
            ->get(['recorded_at', 'lightning_count_daily']);

        $derived = null;
        $previous = null;
        foreach ($readings as $row) {
            $daily = (int) $row->lightning_count_daily;
            if ($previous !== null && $daily > $previous) {
                $derived = $row->recorded_at;
            }
            $previous = $daily;
        }

        // Prefer whichever signal is more recent. A sensor timestamp that lags or
        // sticks on an old strike must not override a fresher counter increase.
        $sensor = $latest->lightning_time;
        if ($sensor && $derived) {
            return $sensor->greaterThan($derived) ? $sensor : $derived;
        }

        return $sensor ?? $derived;
    }

    /**
     * Convert to comprehensive API array
     */
    public function toApiArray(): array
    {
        return [
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'temperature' => $this->temperature,
            'temperature_indoor' => $this->temperature_indoor,
            'feels_like' => $this->feels_like,
            'dew_point' => $this->dew_point,
            'heat_index' => $this->heat_index,
            'wind_chill' => $this->wind_chill,
            'humidity' => $this->humidity,
            'humidity_indoor' => $this->humidity_indoor,
            'pressure_rel' => $this->pressure_rel,
            'pressure_abs' => $this->pressure_abs,
            'pressure_trend' => $this->pressure_trend,
            'wind_speed' => $this->wind_speed,
            'wind_gust' => $this->wind_gust,
            'wind_direction' => $this->wind_direction,
            'wind_direction_compass' => $this->wind_direction_compass,
            'wind_speed_avg_10m' => $this->wind_speed_avg_10m,
            'wind_gust_max_daily' => $this->wind_gust_max_daily,
            'beaufort' => $this->beaufort,
            'beaufort_description' => $this->beaufort_description,
            'rain_rate' => $this->rain_rate,
            'rain_hourly' => $this->rain_hourly,
            'rain_daily' => $this->rain_daily,
            'rain_weekly' => $this->rain_weekly,
            'rain_monthly' => $this->rain_monthly,
            'rain_yearly' => $this->rain_yearly,
            'rain_event' => $this->rain_event,
            'rain_total' => $this->rain_total,
            'uv_index' => $this->uv_index,
            'uv_level' => $this->uv_level,
            'solar_radiation' => $this->solar_radiation,
            'lux' => $this->lux,
            'lightning' => $this->lightning_count > 0 ? [
                'distance' => $this->lightning_distance,
                'count' => $this->lightning_count,
                'count_daily' => $this->lightning_count_daily,
                'last_strike' => $this->lightning_time?->toIso8601String(),
                'time_ago' => $this->lightning_time_ago,
            ] : null,
            'extra_temps' => $this->hasExtraTemperatureSensors() ? $this->extra_temperatures : null,
            'soil' => $this->hasSoilSensors() ? $this->soil_sensors : null,
            'pm25' => $this->hasPm25Sensors() ? [
                'ch1' => $this->pm25_ch1,
                'ch2' => $this->pm25_ch2,
                'ch3' => $this->pm25_ch3,
                'ch4' => $this->pm25_ch4,
                'avg_24h_ch1' => $this->pm25_avg_24h_ch1,
                'level' => $this->pm25_level,
            ] : null,
            'co2' => $this->co2 ? [
                'value' => $this->co2,
                'avg_24h' => $this->co2_avg_24h,
                'temp' => $this->co2_temp,
                'humidity' => $this->co2_humidity,
            ] : null,
            'leak_alert' => $this->hasLeakAlert(),
            'station' => [
                'type' => $this->station_type,
                'model' => $this->station_model,
                'runtime_hours' => $this->station_runtime ? round($this->station_runtime / 3600, 1) : null,
            ],
        ];
    }
}
