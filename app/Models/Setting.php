<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
        'options',
    ];

    /**
     * Get a setting value by key
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = Cache::remember("setting.{$key}", 3600, function () use ($key) {
            return static::find($key);
        });

        if (!$setting) {
            return $default;
        }

        return $setting->getCastedValue();
    }

    /**
     * Set a setting value
     */
    public static function setValue(string $key, mixed $value, string $type = 'string', string $group = 'general'): void
    {
        $stringValue = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            'encrypted' => Crypt::encryptString($value),
            default => (string) $value,
        };

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $stringValue, 'type' => $type, 'group' => $group]
        );

        Cache::forget("setting.{$key}");
    }

    /**
     * Get all settings by group
     */
    public static function getByGroup(string $group): array
    {
        return static::where('group', $group)
            ->get()
            ->mapWithKeys(fn ($setting) => [$setting->key => $setting->getCastedValue()])
            ->toArray();
    }

    /**
     * Get casted value based on type
     */
    public function getCastedValue(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => $this->value === '1' || $this->value === 'true',
            'json' => $this->decodeJsonSafely($this->value),
            'encrypted' => $this->value ? $this->decryptSafely($this->value) : null,
            'float' => (float) $this->value,
            default => $this->value,
        };
    }

    /**
     * Safely decode JSON, returning empty array if decoding fails
     */
    private function decodeJsonSafely(string $value): array
    {
        if (empty($value) || $value === '[]') {
            return [];
        }
        
        $decoded = json_decode($value, true);
        
        // If decoding failed or didn't return an array, return empty array
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            \Log::warning('Failed to decode JSON setting value', [
                'key' => $this->key,
                'value' => $value,
                'error' => json_last_error_msg(),
            ]);
            return [];
        }
        
        return $decoded;
    }

    /**
     * Safely decrypt a value, returning null if decryption fails
     */
    private function decryptSafely(string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            // If decryption fails (e.g., corrupted data or wrong APP_KEY), return null
            // This prevents the entire page from crashing
            \Log::warning('Failed to decrypt setting value', [
                'key' => $this->key,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get options as array (for select fields)
     */
    public function getOptionsArray(): array
    {
        if (empty($this->options)) {
            return [];
        }
        
        $result = [];
        $pairs = explode(',', $this->options);
        foreach ($pairs as $pair) {
            $parts = explode(':', $pair, 2);
            if (count($parts) === 2) {
                $result[trim($parts[0])] = trim($parts[1]);
            }
        }
        return $result;
    }

    /**
     * Helper methods for common settings
     */
    public static function stationName(): string
    {
        return static::getValue('station.name', 'WeatherNode');
    }

    public static function stationLocation(): string
    {
        return static::getValue('station.location', 'Waldijk - Uitgeest - Noord-Holland');
    }

    public static function latitude(): float
    {
        return (float) static::getValue('station.latitude', 52.5163996);
    }

    public static function longitude(): float
    {
        return (float) static::getValue('station.longitude', 4.7078991);
    }

    public static function timezone(): string
    {
        return static::getValue('station.timezone', 'Europe/Amsterdam');
    }

    public static function defaultUnit(): string
    {
        return static::getValue('display.unit_system', 'metric');
    }

    public static function defaultLanguage(): string
    {
        return static::getValue('display.language', 'nl-nl');
    }

    public static function defaultTheme(): string
    {
        return static::getValue('display.theme', 'dark');
    }

    /**
     * Check if station coordinates are within Netherlands bounds
     */
    public static function isStationInNetherlands(): bool
    {
        $lat = static::latitude();
        $lon = static::longitude();

        // Netherlands approximate bounds
        // Latitude: ~50.75° to ~53.7° (south to north)
        // Longitude: ~3.2° to ~7.2° (west to east)
        return $lat >= 50.75 && $lat <= 53.7 && $lon >= 3.2 && $lon <= 7.2;
    }

    /**
     * Check if station is in a specific region (extensible for other providers)
     */
    public static function isStationInRegion(string $providerKey): bool
    {
        return match ($providerKey) {
            'knmi' => static::isStationInNetherlands(),
            // Add other regions as providers are implemented
            default => false,
        };
    }
}
