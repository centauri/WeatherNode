<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\OgAppearance;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

/**
 * Generates dynamic 1200×630 Open Graph PNG cards for social sharing.
 *
 * Usage:
 *   $png = OgImageService::make()->homeCard($data);
 *   return response($png, 200, ['Content-Type' => 'image/png']);
 *
 * Driver resolution order (when og.driver = 'auto'):
 *   1. Imagick (higher quality text rendering)
 *   2. GD
 *   3. RuntimeException if neither is available
 */
class OgImageService
{
    // Canvas dimensions (standard OG image size)
    const W = 1200;
    const H = 630;

    // Background / surface colours (matches Tailwind config weather.* tokens)
    const BG        = '#0c1424';
    const SURFACE   = '#1a2332';
    const BORDER    = '#1e293b';

    // Accent colours per page type
    const BLUE    = '#3b82f6';   // home / general
    const CYAN    = '#06b6d4';   // forecast / astronomy
    const AMBER   = '#f59e0b';   // statistics / history
    const RED     = '#ef4444';   // fire weather
    const TEAL    = '#14b8a6';   // air quality
    const INDIGO  = '#6366f1';   // aviation
    const VIOLET  = '#8b5cf6';   // earthquakes / pressure
    const SLATE   = '#64748b';   // radar / satellite / generic

    // Text colours
    const WHITE     = '#ffffff';
    const PRIMARY   = '#e2e8f0';
    const SECONDARY = '#94a3b8';
    const MUTED     = '#475569';

    // Logo
    const LOGO_H    = 88;   // logo height in pixels (width is derived from aspect ratio)

    // Layout
    const PAD_X    = 70;   // horizontal padding
    const PAD_X_R  = 1130; // right-aligned x position
    const TOP_Y    = 36;   // first text row (below accent bar)
    const DIV1_Y   = 108;  // first divider y
    const DIV2_Y   = 555;  // bottom divider y
    const FOOT_Y   = 586;  // footer text y

    private ImageManager    $manager;
    private ImageInterface  $img;
    private string          $fontRegular;
    private string          $fontBold;
    private string          $currentAccent = self::BLUE;
    private string          $currentBrandText = self::BLUE;
    private array           $appearance = [];
    private bool            $classicAppearance = true;

    private function __construct() {}

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public static function make(): static
    {
        $svc = new static();
        $appearance = OgAppearance::current();
        $svc->appearance = $appearance['tokens'];
        $svc->classicAppearance = $appearance['classic'];
        $svc->setupDriver();
        $svc->setupFonts();
        return $svc;
    }

    /**
     * Return [gd_available, imagick_available].
     */
    public static function availableDrivers(): array
    {
        return [
            'gd'      => extension_loaded('gd'),
            'imagick' => extension_loaded('imagick'),
        ];
    }

    /**
     * Resolve which driver the current settings point to ('gd' or 'imagick').
     * Returns null if no driver is available.
     */
    public static function resolvedDriver(): ?string
    {
        $setting = Setting::getValue('og.driver', 'auto');
        if ($setting !== 'auto') {
            return extension_loaded($setting) ? $setting : null;
        }
        if (extension_loaded('imagick')) return 'imagick';
        if (extension_loaded('gd'))      return 'gd';
        return null;
    }

    // -------------------------------------------------------------------------
    // Card generators (each returns a PNG binary string)
    // -------------------------------------------------------------------------

    /**
     * Home / live-conditions card.
     *
     * Expected data keys: station_name, station_location, domain,
     *   temperature, feels_like, humidity, wind_speed, wind_dir_deg,
     *   pressure, unit_temp (°C|°F), unit_wind (km/h|mph), unit_pressure (hPa|inHg),
     *   updated_at (Carbon or string)
     */
    public function homeCard(array $d): string
    {
        $this->createCanvas(self::BLUE);
        $this->drawHeader($d['station_name'], $d['station_location'], 'LIVE WEATHER', self::BLUE);

        // Weather condition illustration (top-right quadrant, clear of temperature text)
        $cond = $this->detectCondition(
            $d['solar_rad']  ?? null,
            $d['rain_daily'] ?? null,
            $d['uv_index']   ?? null,
            $d['temperature'] ?? null
        );
        $this->drawWeatherIcon($cond, 950, 218, 65);

        // Big temperature
        $temp = ($d['temperature'] !== null ? number_format((float)$d['temperature'], 1) : '—') . ($d['unit_temp'] ?? '°C');
        $this->t($temp, self::PAD_X, 130, $this->fontBold, 88, $this->colour('fg'));

        // Feels like
        $feelsLine = [];
        if ($d['feels_like'] !== null) {
            $feelsLine[] = 'Feels like ' . number_format((float)$d['feels_like'], 1) . ($d['unit_temp'] ?? '°C');
        }
        if (($d['dew_point'] ?? null) !== null) {
            $feelsLine[] = 'Dew point ' . number_format((float)$d['dew_point'], 1) . ($d['unit_temp'] ?? '°C');
        }
        if ($feelsLine) {
            $this->t(implode('  ·  ', $feelsLine), self::PAD_X, 245, $this->fontRegular, 22, $this->colour('secondary'));
        }

        // Row 1: Humidity, Wind, Pressure
        $cols1 = [
            [self::PAD_X,        'HUMIDITY',  ($d['humidity'] !== null ? $d['humidity'] . '%' : '—')],
            [self::PAD_X + 360,  'WIND',      ($d['wind_speed'] !== null ? number_format((float)$d['wind_speed'], 1) . ' ' . ($d['unit_wind'] ?? 'km/h') . ' ' . $this->compass($d['wind_dir_deg'] ?? null) : '—')],
            [self::PAD_X + 720,  'PRESSURE',  ($d['pressure'] !== null ? number_format((float)$d['pressure'], 0) . ' ' . ($d['unit_pressure'] ?? 'hPa') : '—')],
        ];
        $this->drawStatsRow($cols1, 325, 365);

        // Row 2: Rain today, UV index, Solar radiation
        $rainVal  = ($d['rain_daily'] ?? null) !== null ? number_format((float)$d['rain_daily'], 1) . ' mm' : '—';
        $uvVal    = ($d['uv_index']   ?? null) !== null ? number_format((float)$d['uv_index'], 1)            : '—';
        $solarVal = ($d['solar_rad']  ?? null) !== null ? number_format((float)$d['solar_rad'], 0) . ' W/m²' : '—';
        $cols2 = [
            [self::PAD_X,        'RAIN TODAY', $rainVal],
            [self::PAD_X + 360,  'UV INDEX',   $uvVal],
            [self::PAD_X + 720,  'SOLAR',      $solarVal],
        ];
        $this->drawStatsRow($cols2, 445, 485);

        $this->drawFooter($d['updated_at'], $d['domain'],
            'Updated every minute · charts, UV, trends & history →');
        return $this->encode();
    }

    /**
     * Forecast card.
     */
    public function forecastCard(array $d): string
    {
        $this->createCanvas(self::CYAN);
        $this->drawHeader($d['station_name'], $d['station_location'], 'FORECAST', self::CYAN);

        $days = $d['days'] ?? [];   // array of ['label','high','low','symbol','rain','wind']
        if (empty($days)) {
            $this->t('Forecast data unavailable', self::PAD_X, 220, $this->fontRegular, 32, $this->colour('secondary'));
        } else {
            $colW = (self::PAD_X_R - self::PAD_X) / min(count($days), 3);

            // ── Today: full details ───────────────────────────────────────────
            $day0 = $days[0] ?? null;
            if ($day0) {
                $x0 = (int)(self::PAD_X + $colW / 2);
                $this->t($day0['label'] ?? '', $x0, 140, $this->fontRegular, 22, $this->colour('secondary'), 'center');
                $hi = $day0['high'] !== null ? number_format((float)$day0['high'], 0) . ($d['unit_temp'] ?? '°C') : '—';
                $lo = $day0['low']  !== null ? number_format((float)$day0['low'], 0)  . ($d['unit_temp'] ?? '°C') : '—';
                $this->t($hi, $x0, 178, $this->fontBold,    52, $this->colour('fg'), 'center');
                $this->t($lo, $x0, 245, $this->fontRegular, 30, $this->colour('secondary'), 'center');
                if (!empty($day0['symbol'])) {
                    $this->t($day0['symbol'], $x0, 292, $this->fontRegular, 22, $this->colour('muted'), 'center');
                }
                $sub = [];
                if (($day0['rain'] ?? null) !== null) {
                    $sub[] = number_format((float)$day0['rain'], 1) . ' mm';
                }
                if (($day0['wind'] ?? null) !== null) {
                    $sub[] = number_format((float)$day0['wind'], 0) . ' ' . ($d['unit_wind'] ?? 'km/h');
                }
                if ($sub) {
                    $this->t(implode('  ·  ', $sub), $x0, 330, $this->fontRegular, 18, $this->colour('muted'), 'center');
                }
                $this->drawWeatherIcon($this->detectForecastCondition($day0), $x0, 440, 45);
            }

            // ── Days 2 & 3: icon teaser only (no temperatures) ───────────────
            foreach (array_slice($days, 1, 2) as $i => $day) {
                $x = (int)(self::PAD_X + ($i + 1) * $colW + $colW / 2);
                $this->t($day['label'] ?? '', $x, 185, $this->fontRegular, 22, $this->colour('secondary'), 'center');
                $this->drawWeatherIcon($this->detectForecastCondition($day), $x, 350, 60);
                $this->t('Tap for full temperatures & hourly forecast →',
                    $x, 430, $this->fontRegular, 13, $this->colour('muted'), 'center');
            }
        }

        $this->drawFooter($d['updated_at'], $d['domain'],
            'See the full 7-day forecast with hourly breakdown →');
        return $this->encode();
    }

    /**
     * History day card (/history/{date}).
     */
    public function historyCard(array $d): string
    {
        $this->createCanvas(self::AMBER);
        $this->drawHeader($d['station_name'], $d['station_location'], 'WEATHER HISTORY', self::AMBER);

        $dateLabel = $d['date_label'] ?? 'Today';
        $this->t($dateLabel, self::PAD_X, 130, $this->fontBold, 48, $this->colour('fg'));

        if ($d['has_data']) {
            $hiLo = (($d['temp_high'] !== null) ? number_format((float)$d['temp_high'], 1) : '—')
                . ' / ' .
                (($d['temp_low'] !== null) ? number_format((float)$d['temp_low'], 1) : '—')
                . ($d['unit_temp'] ?? '°C');
            $this->t($hiLo, self::PAD_X, 205, $this->fontRegular, 40, $this->colour('primary'));

            // Avg temp sub-line
            if (($d['temp_avg'] ?? null) !== null) {
                $this->t('avg ' . number_format((float)$d['temp_avg'], 1) . ($d['unit_temp'] ?? '°C'),
                    self::PAD_X, 262, $this->fontRegular, 20, $this->colour('secondary'));
            }

            // Row 1: Rain, Max Wind, Sun
            $cols1 = [
                [self::PAD_X,        'RAIN',     ($d['rain']     !== null ? number_format((float)$d['rain'], 1)     . ' mm'                        : '—')],
                [self::PAD_X + 360,  'MAX WIND', ($d['wind_max'] !== null ? number_format((float)$d['wind_max'], 1) . ' ' . ($d['unit_wind'] ?? 'km/h') : '—')],
                [self::PAD_X + 720,  'SUN',      ($d['sun_hours'] !== null ? number_format((float)$d['sun_hours'], 1) . ' h'                        : '—')],
            ];
            $this->drawStatsRow($cols1, 315, 355);

            // Row 2: Avg wind, UV max
            $avgWindVal = ($d['wind_avg'] ?? null) !== null
                ? number_format((float)$d['wind_avg'], 1) . ' ' . ($d['unit_wind'] ?? 'km/h') : '—';
            $uvMaxVal   = ($d['uv_max']   ?? null) !== null
                ? number_format((float)$d['uv_max'], 1) : '—';
            $cols2 = [
                [self::PAD_X,        'AVG WIND', $avgWindVal],
                [self::PAD_X + 360,  'UV MAX',   $uvMaxVal],
            ];
            $this->drawStatsRow($cols2, 435, 475);
        } else {
            $this->t('No data available for this date', self::PAD_X, 240, $this->fontRegular, 28, $this->colour('muted'));
        }

        $this->drawFooter($d['updated_at'], $d['domain'],
            'Browse the complete local weather archive →');
        return $this->encode();
    }

    /**
     * Statistics card (/statistics?year=YYYY).
     */
    public function statisticsCard(array $d): string
    {
        $this->createCanvas(self::AMBER);
        $this->drawHeader($d['station_name'], $d['station_location'], 'STATISTICS', self::AMBER);

        $year = $d['year'] ?? date('Y');
        $this->t((string)$year, self::PAD_X, 130, $this->fontBold, 72, $this->colour('fg'));
        $this->t('Weather statistics', self::PAD_X, 222, $this->fontRegular, 24, $this->colour('secondary'));

        // Row 1: Hottest, Coldest, Total Rain
        $row1 = [];
        if ($d['hottest_temp'] !== null) {
            $row1[] = [self::PAD_X,       'HOTTEST DAY', number_format((float)$d['hottest_temp'], 1) . ($d['unit_temp'] ?? '°C')];
        }
        if ($d['coldest_temp'] !== null) {
            $row1[] = [self::PAD_X + 360, 'COLDEST DAY', number_format((float)$d['coldest_temp'], 1) . ($d['unit_temp'] ?? '°C')];
        }
        if ($d['rain_total'] !== null) {
            $row1[] = [self::PAD_X + 720, 'TOTAL RAIN',  number_format((float)$d['rain_total'], 0) . ' mm'];
        }
        if (!empty($row1)) {
            $this->drawStatsRow($row1, 300, 340);
        }

        // Row 2: Avg temp, Rain days, Sun hours / Frost days
        $row2 = [];
        if (($d['avg_temp'] ?? null) !== null) {
            $row2[] = [self::PAD_X,       'AVG TEMP',   number_format((float)$d['avg_temp'], 1) . ($d['unit_temp'] ?? '°C')];
        }
        if (($d['rain_days'] ?? null) !== null) {
            $row2[] = [self::PAD_X + 360, 'RAIN DAYS',  (int)$d['rain_days'] . ' d'];
        }
        if (($d['sun_total'] ?? null) !== null) {
            $row2[] = [self::PAD_X + 720, 'SUN HOURS',  number_format((float)$d['sun_total'], 0) . ' h'];
        } elseif (($d['frost_days'] ?? null) !== null) {
            $row2[] = [self::PAD_X + 720, 'FROST DAYS', (int)$d['frost_days'] . ' d'];
        }
        if (!empty($row2)) {
            $this->drawStatsRow($row2, 420, 460);
        }

        $this->drawFooter($d['updated_at'], $d['domain'],
            'All records, extremes & climate trends →');
        return $this->encode();
    }

    /**
     * Fire weather card (/fire-weather).
     */
    public function fireWeatherCard(array $d): string
    {
        $this->createCanvas(self::RED);
        $this->drawHeader($d['station_name'], $d['station_location'], 'FIRE WEATHER', self::RED);

        $dangerColor = match (strtolower($d['danger_level'] ?? '')) {
            'low'      => '#22c55e',
            'moderate' => self::AMBER,
            'high'     => '#f97316',
            'extreme'  => self::RED,
            default    => self::SLATE,
        };

        // ── Decorative sun-hot icon (right side, large) ──────────────────────
        $this->placeIconSvg('sun-hot', 980, 340, 155);

        // Subtle warm glow behind the icon
        try {
            $this->img->drawEllipse(980, 340, function ($draw) use ($dangerColor) {
                $draw->size(340, 340);
                $draw->background($dangerColor . '12');
            });
            $this->img->drawEllipse(980, 340, function ($draw) use ($dangerColor) {
                $draw->size(240, 240);
                $draw->background($dangerColor . '18');
            });
        } catch (\Throwable) {}

        // ── Angström index (big number, left) ────────────────────────────────
        $angstrom = ($d['angstrom'] ?? null) !== null
            ? number_format((float)$d['angstrom'], 1)
            : '—';
        $numColor = ($d['angstrom'] ?? null) !== null ? $dangerColor : $this->colour('muted');
        $this->t($angstrom, self::PAD_X, 125, $this->fontBold, 96, $numColor);
        $this->t('Angström Index', self::PAD_X, 248, $this->fontRegular, 24, $this->colour('secondary'));

        // ── Danger level badge ────────────────────────────────────────────────
        $danger = ucfirst(strtolower($d['danger_level'] ?? 'Unknown'));
        $this->rect(self::PAD_X, 285, 240, 46, $dangerColor . '22');
        $this->rect(self::PAD_X, 285, 5,   46, $dangerColor);
        $this->t($danger, self::PAD_X + 22, 285, $this->fontBold, 28, $dangerColor, 'left', 'top');

        // ── Fire danger scale bar ─────────────────────────────────────────────
        $scaleY  = 355;
        $scaleH  = 10;
        $scaleX0 = self::PAD_X;
        $levels  = [
            ['Low',      '#22c55e'],
            ['Moderate', self::AMBER],
            ['High',     '#f97316'],
            ['Extreme',  self::RED],
        ];
        $segW = 155;
        foreach ($levels as $i => [$lbl, $col]) {
            $sx      = $scaleX0 + $i * ($segW + 4);
            $active  = strtolower($lbl) === strtolower($d['danger_level'] ?? '');
            $opacity = $active ? 'ff' : '44';
            $this->rect($sx, $scaleY, $segW, $scaleH, $col . $opacity);
            $this->t($lbl, $sx + (int)($segW / 2), $scaleY + $scaleH + 8,
                $this->fontRegular, 14,
                $active ? $col : $this->colour('muted'),
                'center', 'top');
            // Active level marker triangle (dot above)
            if ($active) {
                try {
                    $this->img->drawEllipse($sx + (int)($segW / 2), $scaleY - 7, function ($draw) use ($col) {
                        $draw->size(10, 10);
                        $draw->background($col);
                    });
                } catch (\Throwable) {}
            }
        }

        // ── Stats row: temp, humidity, dry days ───────────────────────────────
        $cols1 = [
            [self::PAD_X,       'MAX TEMP',    ($d['max_temp']     ?? null) !== null ? number_format((float)$d['max_temp'],     1) . '°C' : '—'],
            [self::PAD_X + 220, 'MIN RH',      ($d['min_humidity'] ?? null) !== null ? (int)$d['min_humidity'] . '%'                      : '—'],
            [self::PAD_X + 420, 'DRY DAYS',    ($d['dry_days']     ?? null) !== null ? (int)$d['dry_days'] . ' d'                         : '—'],
        ];
        $this->drawStatsRow($cols1, 408, 438);

        // ── Stats row 2: 7-day and 30-day rain ────────────────────────────────
        $cols2 = [
            [self::PAD_X,       '7-DAY RAIN',  ($d['rain_7d']  ?? null) !== null ? number_format((float)$d['rain_7d'],  1) . ' mm' : '—'],
            [self::PAD_X + 220, '30-DAY RAIN', ($d['rain_30d'] ?? null) !== null ? number_format((float)$d['rain_30d'], 1) . ' mm' : '—'],
        ];
        $this->drawStatsRow($cols2, 485, 515);

        $this->drawFooter($d['updated_at'], $d['domain'],
            'Full fire risk analysis, trends & local weather →');
        return $this->encode();
    }

    /**
     * Air quality card (/air-quality).
     */
    public function airQualityCard(array $d): string
    {
        $this->createCanvas(self::TEAL);
        $this->drawHeader($d['station_name'], $d['station_location'], 'AIR QUALITY', self::TEAL);

        $aqi = $d['aqi'] ?? null;
        $aqiDisplay = $aqi !== null ? (string)(int)$aqi : '—';
        $aqiColor = $this->aqiColor($aqi);

        // Decorative concentric rings (right side, behind text)
        $this->drawAqiDecoration(940, 215, 88, $aqi, $aqiColor);

        $this->t($aqiDisplay, self::PAD_X, 130, $this->fontBold, 88, $aqiColor);
        $this->t('AQI — ' . ($d['aqi_label'] ?? 'Air Quality Index'), self::PAD_X, 245, $this->fontRegular, 24, $this->colour('secondary'));

        // Row 1: PM2.5, PM10, Source
        $cols1 = [
            [self::PAD_X,       'PM2.5',   ($d['pm25'] !== null ? number_format((float)$d['pm25'], 1) . ' μg/m³' : '—')],
            [self::PAD_X + 360, 'PM10',    ($d['pm10'] !== null ? number_format((float)$d['pm10'], 1) . ' μg/m³' : '—')],
            [self::PAD_X + 720, 'STATION', ($d['source'] ?? 'Sensor')],
        ];
        $this->drawStatsRow($cols1, 320, 360);

        // Row 2: 24h avg PM2.5, CO2
        $row2 = [];
        if (($d['pm25_24h'] ?? null) !== null) {
            $row2[] = [self::PAD_X, 'PM2.5 24H AVG', number_format((float)$d['pm25_24h'], 1) . ' μg/m³'];
        }
        if (($d['co2'] ?? null) !== null) {
            $row2[] = [self::PAD_X + 360, 'CO₂', number_format((float)$d['co2'], 0) . ' ppm'];
        }
        if (!empty($row2)) {
            $this->drawStatsRow($row2, 440, 480);
        }

        $this->drawFooter($d['updated_at'], $d['domain'],
            'Track air quality trends & sensor history →');
        return $this->encode();
    }

    /**
     * Astronomy card (/astronomy).
     */
    public function astronomyCard(array $d): string
    {
        $this->createCanvas(self::CYAN);
        $this->drawHeader($d['station_name'], $d['station_location'], 'ASTRONOMY', self::CYAN);

        $this->t($d['date_label'] ?? date('j F Y'), self::PAD_X, 130, $this->fontBold, 40, $this->colour('fg'));

        // Moon phase SVG icon (top-right, same row as moon text)
        if (!empty($d['moon_phase'])) {
            $this->placeMoonIcon($d['moon_phase'], 1060, 192, 36);
        }

        // Moon phase + illumination
        $moonLine = $d['moon_phase'] ?? '';
        if (($d['moon_illumination'] ?? null) !== null) {
            $moonLine .= ($moonLine ? '  ·  ' : '') . (int)$d['moon_illumination'] . '% illuminated';
        }
        if ($moonLine) {
            $this->t($moonLine, self::PAD_X, 185, $this->fontRegular, 22, $this->colour('secondary'));
        }

        // Row 1: Sunrise, Sunset, Day length
        $cols1 = [
            [self::PAD_X,        'SUNRISE',    $d['sunrise']    ?? '—'],
            [self::PAD_X + 360,  'SUNSET',     $d['sunset']     ?? '—'],
            [self::PAD_X + 720,  'DAY LENGTH', $d['day_length'] ?? '—'],
        ];
        $this->drawStatsRow($cols1, 270, 310);

        // Row 2: Dawn (civil twilight begin) and Dusk
        $row2 = [];
        if (($d['dawn'] ?? null) !== null) {
            $row2[] = [self::PAD_X,       'DAWN (civil)', $d['dawn']];
        }
        if (($d['dusk'] ?? null) !== null) {
            $row2[] = [self::PAD_X + 360, 'DUSK (civil)', $d['dusk']];
        }
        if (!empty($row2)) {
            $this->drawStatsRow($row2, 400, 440);
        }

        // Sun-path arc (far-right side, between stats rows and footer)
        $this->drawSunArc(
            1000, 450, 100, 75,
            (isset($d['sunrise']) && strlen((string)$d['sunrise']) >= 4) ? (string)$d['sunrise'] : null,
            (isset($d['sunset'])  && strlen((string)$d['sunset'])  >= 4) ? (string)$d['sunset']  : null,
            self::CYAN
        );

        $this->drawFooter($d['updated_at'], $d['domain'],
            'Full sunrise, moon phase & twilight data →');
        return $this->encode();
    }

    /**
     * Aviation METAR card (/aviation/{icao}).
     */
    public function aviationCard(array $d): string
    {
        // Tint the card with flight-category colour for instant visual impact.
        $fc      = strtoupper($d['flight_category'] ?? '');
        $fcColor = match ($fc) {
            'VFR'  => '#22c55e',  // green
            'MVFR' => '#3b82f6',  // blue
            'IFR'  => '#ef4444',  // red
            'LIFR' => '#a855f7',  // magenta
            default => self::INDIGO,
        };

        $this->createCanvas($fcColor);
        $icao = strtoupper($d['icao'] ?? '');
        $this->drawHeader($d['station_name'], $d['station_location'], 'AVIATION', $fcColor);

        // ── ICAO code (hero text, left) ───────────────────────────────────────
        $this->t($icao, self::PAD_X, 125, $this->fontBold, 88, $this->colour('fg'));

        // ── Airport name ──────────────────────────────────────────────────────
        if (!empty($d['airport_name'])) {
            $this->t($d['airport_name'], self::PAD_X, 238, $this->fontRegular, 20, $this->colour('secondary'));
        }

        // ── Flight category badge ─────────────────────────────────────────────
        $fcLabel = !empty($fc) ? $fc : 'N/A';
        $fcDesc  = match ($fc) {
            'VFR'  => 'Visual Flight Rules',
            'MVFR' => 'Marginal VFR',
            'IFR'  => 'Instrument Flight Rules',
            'LIFR' => 'Low IFR',
            default => 'Category unknown',
        };
        $this->rect(self::PAD_X, 270, 340, 52, $fcColor . '22');
        $this->rect(self::PAD_X, 270, 6,   52, $fcColor);
        $this->t($fcLabel, self::PAD_X + 24, 270, $this->fontBold,    32, $fcColor,         'left', 'top');
        $this->t($fcDesc,  self::PAD_X + 90, 283, $this->fontRegular, 18, $this->colour('secondary'), 'left', 'top');

        // ── Compass rose (right side, centred in the open space) ─────────────
        if ($d['has_data']) {
            $this->drawCompassRose(1030, 250, 95, $d['wind_dir_deg'] ?? null);
        }

        if ($d['has_data']) {
            // ── Wind, Visibility, QNH ─────────────────────────────────────────
            $cols1 = [
                [self::PAD_X,       'WIND',       $d['wind']       ?? '—'],
                [self::PAD_X + 360, 'VISIBILITY', $d['visibility'] ?? '—'],
                [self::PAD_X + 720, 'QNH',        $d['qnh']        ?? '—'],
            ];
            $this->drawStatsRow($cols1, 360, 400);

            // ── Cloud layers summary ──────────────────────────────────────────
            if (!empty($d['clouds_summary'])) {
                $this->t('CLOUDS', self::PAD_X, 468, $this->fontRegular, 15, $this->colour('muted'));
                $this->t($d['clouds_summary'], self::PAD_X, 486, $this->fontRegular, 20, $this->colour('secondary'));
            }
        } else {
            $this->t('METAR data unavailable — visit site for live conditions',
                self::PAD_X, 380, $this->fontRegular, 26, $this->colour('muted'));
        }

        $this->drawFooter($d['updated_at'], $d['domain'],
            'See TAF outlook, METAR history & airport trends →');
        return $this->encode();
    }

    /**
     * Generic card for pages without specific data
     * (radar, satellite, lightning, earthquakes, community, etc.).
     */
    public function genericCard(array $d): string
    {
        $this->createCanvas($d['accent'] ?? self::SLATE);
        $this->drawHeader($d['station_name'], $d['station_location'], strtoupper($d['page_label'] ?? 'WEATHER'), $d['accent'] ?? self::SLATE);

        $this->t($d['page_title'] ?? 'Weather', self::PAD_X, 150, $this->fontBold, 72, $this->colour('fg'));
        $this->t($d['tagline'] ?? ($d['station_name'] . ' — ' . $d['station_location']),
            self::PAD_X, 255, $this->fontRegular, 28, $this->colour('secondary'));

        $this->drawFooter($d['updated_at'], $d['domain'],
            'Live weather station · data, charts & analysis →');
        return $this->encode();
    }

    // -------------------------------------------------------------------------
    // Drawing helpers
    // -------------------------------------------------------------------------

    private function createCanvas(string $accentColor): void
    {
        $accentColor = $this->brandAccent($accentColor);
        $this->currentAccent = $accentColor;
        $this->currentBrandText = $this->classicAppearance ? $accentColor : $this->colour('link');

        $this->img = $this->manager->create(self::W, self::H);
        $this->img->fill($this->colour('bg'));

        // Subtle accent glow bleeding down from the top
        $this->rect(0, 0, self::W, 300, $accentColor . '0e');  // ~5.5% tint across top
        $this->rect(0, 0, self::W, 120, $accentColor . '08');  // slightly stronger near top

        // Large faint decorative circle in the top-right corner
        try {
            $this->img->drawEllipse(1080, -30, static function ($draw) use ($accentColor) {
                $draw->size(340, 340);
                $draw->background($accentColor . '09');
                $draw->border(1, $accentColor . '22');
            });
        } catch (\Throwable $e) {
            // Non-fatal — driver may not support this shape
        }

        // Left accent bar (full height)
        $this->rect(0, 0, 6, self::H, $accentColor);

        // Top accent line (full width, thin)
        $this->rect(0, 0, self::W, 3, $accentColor);

        // Bottom-right corner accent
        $this->rect(self::W - 4, self::H - 100, 4, 100, $accentColor . '55');
    }

    /**
     * Draw the shared header: logo + station name (top left), page label badge (top right),
     * location subtitle, and a horizontal divider.
     */
    private function drawHeader(string $stationName, string $location, string $pageLabel, string $accentColor): void
    {
        $accentColor = $this->brandAccent($accentColor);
        // Logo — placed at the very left of the header; returns actual width or 0 if unavailable
        $logoWidth = $this->placeLogo(self::PAD_X, 10);

        // Station name and location — offset right of the logo when present
        $textX = self::PAD_X + ($logoWidth > 0 ? $logoWidth + 18 : 0);
        $this->t($stationName, $textX, self::TOP_Y, $this->fontBold, 24, $this->colour('fg'));
        $this->t($location,    $textX, self::TOP_Y + 34, $this->fontRegular, 17, $this->colour('secondary'));

        // Page type badge (top right) — coloured pill with left accent border
        $badgeW = 240;
        $badgeX = self::PAD_X_R - $badgeW;
        $badgeY = 26;
        $badgeH = 38;
        $this->rect($badgeX,     $badgeY, $badgeW, $badgeH, $accentColor . '22');
        $this->rect($badgeX,     $badgeY, 4,       $badgeH, $accentColor);
        $this->t($pageLabel, self::PAD_X_R - 14, $badgeY + (int)($badgeH / 2),
            $this->fontRegular, 14, $this->currentBrandText, 'right', 'middle');

        // Divider
        $this->rect(self::PAD_X, self::DIV1_Y, self::PAD_X_R - self::PAD_X, 1, $this->colour('border'));
    }

    /**
     * Draw bottom divider + footer line with timestamp and domain.
     */
    private function drawFooter(mixed $updatedAt, string $domain, string $cta = 'More data, charts & history →'): void
    {
        // Bottom divider
        $this->rect(self::PAD_X, self::DIV2_Y, self::PAD_X_R - self::PAD_X, 1, $this->colour('border'));

        // Timestamp
        $ts = '';
        if ($updatedAt instanceof \Carbon\Carbon) {
            $ts = $updatedAt->format('j M Y, H:i');
        } elseif (is_string($updatedAt) && $updatedAt !== '') {
            $ts = $updatedAt;
        }
        if ($ts !== '') {
            $this->t($ts, self::PAD_X, self::FOOT_Y, $this->fontRegular, 17, $this->colour('muted'));
        }

        // Domain (right-aligned, accent coloured)
        $this->t($domain, self::PAD_X_R, self::FOOT_Y, $this->fontRegular, 17, $this->currentBrandText, 'right');

        // CTA — targeted per-card line to pull visitors in
        $this->t($cta, self::PAD_X_R, self::FOOT_Y + 22,
            $this->fontRegular, 14, $this->colour('secondary'), 'right');
    }

    /**
     * Draw a row of label + value columns.
     *
     * @param array $cols  [ [x, label, value], ... ]
     * @param int   $labelY  y-position for the label text
     * @param int   $valueY  y-position for the value text
     */
    private function drawStatsRow(array $cols, int $labelY, int $valueY): void
    {
        foreach ($cols as [$x, $label, $value]) {
            $this->t($label, $x, $labelY, $this->fontRegular, 15, $this->colour('muted'));
            $this->t($value, $x, $valueY, $this->fontBold,    32, $this->colour('primary'));
        }
    }

    private function colour(string $token): string
    {
        return $this->appearance[$token] ?? match ($token) {
            'bg' => self::BG,
            'card' => self::SURFACE,
            'fg' => self::WHITE,
            'primary' => $this->classicAppearance ? self::PRIMARY : ($this->appearance['fg'] ?? self::PRIMARY),
            'secondary' => self::SECONDARY,
            'muted' => self::MUTED,
            'border' => self::BORDER,
            'accent' => self::BLUE,
            'link' => self::BLUE,
            default => self::PRIMARY,
        };
    }

    private function brandAccent(string $classicAccent): string
    {
        return $this->classicAppearance ? $classicAccent : $this->colour('accent');
    }

    // -------------------------------------------------------------------------
    // Visual illustrations
    // -------------------------------------------------------------------------

    /** Derive a weather condition token from current sensor data. */
    private function detectCondition(
        ?float $solarRad, ?float $rainDaily, ?float $uvIndex, ?float $temp
    ): string {
        if ($rainDaily !== null) {
            if ($rainDaily > 5)   return ($temp !== null && $temp < 2) ? 'snow' : 'rain';
            if ($rainDaily > 0.5) return 'drizzle';
        }
        if ($solarRad !== null) {
            if ($solarRad > 350) return 'sunny';
            if ($solarRad > 80)  return 'partly_cloudy';
            return 'cloudy';
        }
        if ($uvIndex !== null) {
            if ($uvIndex > 4) return 'sunny';
            if ($uvIndex > 1) return 'partly_cloudy';
        }
        return 'partly_cloudy';
    }

    /** Derive a condition token from a forecast day array. */
    private function detectForecastCondition(array $day): string
    {
        $sym  = strtolower($day['symbol'] ?? '');
        $rain = (float)($day['rain'] ?? 0);
        if (str_contains($sym, 'snow'))    return 'snow';
        if (str_contains($sym, 'thunder')) return 'storm';
        if ($rain > 5 || str_contains($sym, 'heavy rain')) return 'rain';
        if ($rain > 0.5 || str_contains($sym, 'rain') || str_contains($sym, 'shower')) return 'drizzle';
        if (str_contains($sym, 'overcast')) return 'cloudy';
        if (str_contains($sym, 'cloudy') && !str_contains($sym, 'partly')) return 'cloudy';
        if (str_contains($sym, 'partly') || str_contains($sym, 'fair')) return 'partly_cloudy';
        if ($rain > 0) return 'drizzle';
        return 'sunny';
    }

    /**
     * Draw a weather icon at (cx, cy) with radius r.
     *
     * Tries to composite the real SVG icon used on the site (works with Imagick;
     * requires the weather-static directory to exist in public/).
     * Falls back to the hand-drawn GD shapes when SVG loading is unavailable.
     */
    private function drawWeatherIcon(string $cond, int $cx, int $cy, int $r): void
    {
        static $condMap = [
            'sunny'         => 'clear-day',
            'partly_cloudy' => 'partly-cloudy-day',
            'cloudy'        => 'cloudy',
            'rain'          => 'rain',
            'storm'         => 'thunderstorms-overcast-rain',
            'drizzle'       => 'drizzle',
            'snow'          => 'snow',
        ];

        $iconName = $condMap[$cond] ?? 'partly-cloudy-day';

        // Try the actual site SVG (works when Imagick + SVG support is present)
        if ($this->placeIconSvg($iconName, $cx, $cy, $r)) {
            return;
        }

        // Fallback: hand-drawn shapes (always works with GD)
        try {
            match ($cond) {
                'sunny'         => $this->drawSunIcon($cx, $cy, $r),
                'partly_cloudy' => $this->drawPartlyCloudyIcon($cx, $cy, $r),
                'rain', 'storm' => $this->drawRainIcon($cx, $cy, $r, false),
                'drizzle'       => $this->drawRainIcon($cx, $cy, $r, true),
                'snow'          => $this->drawSnowIcon($cx, $cy, $r),
                default         => $this->drawCloudIcon($cx, $cy, $r, '#94a3b8'),
            };
        } catch (\Throwable) {}
    }

    /**
     * Place an icon from the site's weather icon set, centred at (cx, cy).
     *
     * Resolution order:
     *  1. icons/weather-png/{name}.png  — pre-rasterised PNGs, work with any driver incl. GD
     *  2. icons/weather-static/{name}.svg — SVG, works when Imagick + SVG support is present
     *  3. icons/weather/{name}.svg        — same, animated variant
     *
     * Returns true when the icon was composited successfully.
     */
    private function placeIconSvg(string $iconName, int $cx, int $cy, int $r): bool
    {
        $size       = $r * 2;
        $candidates = [
            public_path("icons/weather-png/{$iconName}.png"),
            public_path("icons/weather-fill-static/{$iconName}.svg"),
            public_path("icons/weather-static/{$iconName}.svg"),
            public_path("icons/weather/{$iconName}.svg"),
        ];

        foreach ($candidates as $path) {
            if (!file_exists($path)) {
                continue;
            }
            try {
                $icon = $this->manager->read($path)->scale(width: $size);
                $this->img->place($icon, 'top-left', $cx - $r, $cy - (int)($icon->height() / 2));
                return true;
            } catch (\Throwable) {}
        }
        return false;
    }

    /**
     * Place a moon-phase SVG icon centred at (cx, cy) with radius r.
     * Maps the phase_name string (e.g. "First Quarter") to the matching moon-*.svg.
     */
    private function placeMoonIcon(string $phaseName, int $cx, int $cy, int $r): void
    {
        static $phaseMap = [
            'new'             => 'moon-new',
            'waxing crescent' => 'moon-waxing-crescent',
            'first quarter'   => 'moon-first-quarter',
            'waxing gibbous'  => 'moon-waxing-gibbous',
            'full'            => 'moon-full',
            'waning gibbous'  => 'moon-waning-gibbous',
            'last quarter'    => 'moon-last-quarter',
            'waning crescent' => 'moon-waning-crescent',
        ];

        $lower    = strtolower($phaseName);
        $iconName = null;
        foreach ($phaseMap as $keyword => $icon) {
            if (str_contains($lower, $keyword)) {
                $iconName = $icon;
                break;
            }
        }
        if ($iconName) {
            $this->placeIconSvg($iconName, $cx, $cy, $r);
        }
    }

    private function drawSunIcon(int $cx, int $cy, int $r): void
    {
        $color = '#fbbf24';
        // Soft glow halo
        $this->img->drawEllipse($cx, $cy, static function ($d) use ($r, $color) {
            $d->size(($r + 30) * 2, ($r + 30) * 2);
            $d->background($color . '12');
        });
        // Sun disc
        $this->img->drawEllipse($cx, $cy, static function ($d) use ($r, $color) {
            $d->size($r * 2, $r * 2);
            $d->background($color . 'cc');
        });
        // 8 rays
        $r1 = $r + 8;
        $r2 = $r + 26;
        for ($i = 0; $i < 8; $i++) {
            $a  = $i * M_PI / 4;
            $x1 = (int)round($cx + $r1 * cos($a));
            $y1 = (int)round($cy + $r1 * sin($a));
            $x2 = (int)round($cx + $r2 * cos($a));
            $y2 = (int)round($cy + $r2 * sin($a));
            $c  = $color . 'cc';
            $this->img->drawLine(static function ($l) use ($x1, $y1, $x2, $y2, $c) {
                $l->from($x1, $y1);
                $l->to($x2, $y2);
                $l->color($c);
                $l->width(3);
            });
        }
    }

    private function drawCloudIcon(int $cx, int $cy, int $r, string $color, string $alpha = 'bb'): void
    {
        $c  = $color . $alpha;
        $rh = max(1, (int)($r * 0.65));
        // Main body
        $this->img->drawEllipse($cx + (int)($r * 0.05), $cy, static function ($d) use ($r, $rh, $c) {
            $d->size((int)($r * 2.0), $rh * 2);
            $d->background($c);
        });
        // Left bump
        $this->img->drawEllipse($cx - (int)($r * 0.5), $cy - (int)($r * 0.28), static function ($d) use ($r, $c) {
            $d->size((int)($r * 1.2), (int)($r * 0.85));
            $d->background($c);
        });
        // Right bump
        $this->img->drawEllipse($cx + (int)($r * 0.45), $cy - (int)($r * 0.20), static function ($d) use ($r, $c) {
            $d->size((int)($r * 1.1), (int)($r * 0.80));
            $d->background($c);
        });
    }

    private function drawPartlyCloudyIcon(int $cx, int $cy, int $r): void
    {
        // Small sun, slightly offset upper-right
        $this->drawSunIcon($cx + (int)($r * 0.28), $cy - (int)($r * 0.22), (int)($r * 0.62));
        // Cloud in front (lower-left), slightly transparent
        $this->drawCloudIcon($cx - (int)($r * 0.08), $cy + (int)($r * 0.12), (int)($r * 0.80), '#94a3b8', 'd0');
    }

    private function drawRainIcon(int $cx, int $cy, int $r, bool $light): void
    {
        $cloudY = $cy - (int)($r * 0.22);
        $this->drawCloudIcon($cx, $cloudY, (int)($r * 0.85), '#64748b', 'cc');
        $dropColor = '#60a5fa';
        $count     = $light ? 3 : 5;
        $startX    = $cx - (int)($r * 0.50);
        $spacing   = (int)($r * 0.26);
        for ($i = 0; $i < $count; $i++) {
            $dx = $startX + $i * $spacing;
            $dy = $cloudY + (int)($r * 0.65) + (($i % 2 === 0) ? 0 : (int)($r * 0.18));
            $dw = max(3, (int)($r * 0.10));
            $dh = max(6, (int)($r * 0.28));
            $dc = $dropColor . 'cc';
            $this->img->drawEllipse($dx, $dy, static function ($d) use ($dw, $dh, $dc) {
                $d->size($dw, $dh);
                $d->background($dc);
            });
        }
    }

    private function drawSnowIcon(int $cx, int $cy, int $r): void
    {
        $cloudY = $cy - (int)($r * 0.22);
        $this->drawCloudIcon($cx, $cloudY, (int)($r * 0.85), '#94a3b8', 'cc');
        $dotColor = '#e2e8f0cc';
        $startX   = $cx - (int)($r * 0.45);
        $spacing  = (int)($r * 0.24);
        for ($i = 0; $i < 5; $i++) {
            $dx = $startX + $i * $spacing;
            $dy = $cloudY + (int)($r * 0.65) + (($i % 2 === 0) ? 0 : (int)($r * 0.18));
            $ds = max(4, (int)($r * 0.16));
            $dc = $dotColor;
            $this->img->drawEllipse($dx, $dy, static function ($d) use ($ds, $dc) {
                $d->size($ds, $ds);
                $d->background($dc);
            });
        }
    }

    /**
     * Draw decorative concentric rings for the AQI card.
     * Uses the AQI colour to create a subtle "target" effect.
     */
    private function drawAqiDecoration(int $cx, int $cy, int $r, ?float $aqi, string $color): void
    {
        try {
            // Outer faint ring
            $this->img->drawEllipse($cx, $cy, static function ($d) use ($r, $color) {
                $d->size($r * 2, $r * 2);
                $d->background($color . '0b');
                $d->border(1, $color . '30');
            });
            // Middle ring
            $r2 = (int)($r * 0.70);
            $this->img->drawEllipse($cx, $cy, static function ($d) use ($r2, $color) {
                $d->size($r2 * 2, $r2 * 2);
                $d->background($color . '16');
                $d->border(1, $color . '50');
            });
            // Inner ring
            $r3 = (int)($r * 0.42);
            $this->img->drawEllipse($cx, $cy, static function ($d) use ($r3, $color) {
                $d->size($r3 * 2, $r3 * 2);
                $d->background($color . '25');
                $d->border(2, $color . '80');
            });
        } catch (\Throwable) {}
    }

    /**
     * Draw a sun-path arc matching the quadratic-bezier style used on the astronomy page.
     *
     * The arc is a quadratic bezier curve from (cx-rx, cy) through control point (cx, cy-ry*2)
     * to (cx+rx, cy), rendered as 100 gradient-coloured filled ellipses so it works identically
     * in both GD and Imagick without needing drawLine or SVG support.
     *
     * Gradient: dark-navy at the ends → orange at ¼/¾ → amber at the peak (matching the
     * linearGradient used in the site's SVG arc).
     */
    private function drawSunArc(
        int $cx, int $cy, int $rx, int $ry,
        ?string $sunrise, ?string $sunset, string $accentColor
    ): void {
        try {
            $x0  = $cx - $rx;       // left  endpoint (sunrise side)
            $y0  = $cy;
            $x2  = $cx + $rx;       // right endpoint (sunset side)
            $y2  = $cy;
            $cpX = $cx;             // bezier control point (horizontally centred)
            $cpY = $cy - $ry * 2;   // raises control point so arc peak ≈ cy - ry

            // Thin horizon baseline drawn as a rectangle (no drawLine needed)
            $this->rect($x0 - 12, $cy, ($x2 - $x0 + 24), 1, '#ffffff18');

            // Bezier arc — 100 gradient-coloured dots
            $steps = 100;
            for ($i = 0; $i <= $steps; $i++) {
                $t  = $i / $steps;
                $mt = 1 - $t;
                $bx = (int)round($mt * $mt * $x0 + 2 * $mt * $t * $cpX + $t * $t * $x2);
                $by = (int)round($mt * $mt * $y0 + 2 * $mt * $t * $cpY + $t * $t * $y2);
                $dc = $this->sunArcColor($t);
                $this->img->drawEllipse($bx, $by, static function ($d) use ($dc) {
                    $d->size(5, 5);
                    $d->background($dc);
                });
            }

            // Sunrise dot (orange) at the left horizon endpoint
            $src = '#fb923c';
            $this->img->drawEllipse($x0, $cy, static function ($d) use ($src) {
                $d->size(12, 12);
                $d->background($src);
            });

            // Sunset dot (amber) at the right horizon endpoint
            $ssc = '#f59e0b';
            $this->img->drawEllipse($x2, $cy, static function ($d) use ($ssc) {
                $d->size(12, 12);
                $d->background($ssc);
            });

            // Time labels below the endpoint dots
            if ($sunrise) {
                $this->t($sunrise, $x0, $cy + 14, $this->fontRegular, 14, '#94a3b8', 'center', 'top');
            }
            if ($sunset) {
                $this->t($sunset, $x2, $cy + 14, $this->fontRegular, 14, '#94a3b8', 'center', 'top');
            }

            // Current sun / nighttime moon position
            if ($sunrise && $sunset
                && preg_match('/^\d{1,2}:\d{2}$/', $sunrise)
                && preg_match('/^\d{1,2}:\d{2}$/', $sunset)
            ) {
                [$srH, $srM] = explode(':', $sunrise);
                [$ssH, $ssM] = explode(':', $sunset);
                $srMins  = (int)$srH * 60 + (int)$srM;
                $ssMins  = (int)$ssH * 60 + (int)$ssM;
                $nowMins = (int)now()->format('H') * 60 + (int)now()->format('i');

                if ($ssMins > $srMins && $nowMins >= $srMins && $nowMins <= $ssMins) {
                    // Daytime: place the sun dot at its bezier position
                    $t   = ($nowMins - $srMins) / ($ssMins - $srMins);
                    $mt  = 1 - $t;
                    $sunX = (int)round($mt * $mt * $x0 + 2 * $mt * $t * $cpX + $t * $t * $x2);
                    $sunY = (int)round($mt * $mt * $y0 + 2 * $mt * $t * $cpY + $t * $t * $y2);

                    $this->img->drawEllipse($sunX, $sunY, static function ($d) {
                        $d->size(30, 30);
                        $d->background('#fbbf2428');
                    });
                    $this->img->drawEllipse($sunX, $sunY, static function ($d) {
                        $d->size(18, 18);
                        $d->background('#fbbf24');
                    });
                } else {
                    // Nighttime: crescent moon suggestion near the arc peak
                    $moonX = $cx + (int)($rx * 0.15);
                    $moonY = $cy - $ry;
                    $background = $this->colour('bg');
                    $this->img->drawEllipse($moonX, $moonY, static function ($d) {
                        $d->size(18, 18);
                        $d->background('#e2e8f080');
                    });
                    $this->img->drawEllipse($moonX + 6, $moonY - 4, static function ($d) use ($background) {
                        $d->size(14, 14);
                        $d->background($background);
                    });
                }
            }
        } catch (\Throwable) {}
    }

    /**
     * Interpolate the sun-arc gradient colour.
     *
     * Matches the site's linearGradient:
     *   0 %  → #1e3a5f (dark navy)
     *  25 %  → #f97316 (orange)
     *  50 %  → #fbbf24 (amber — arc peak)
     *  75 %  → #f97316 (orange)
     * 100 %  → #1e3a5f (dark navy)
     */
    private function sunArcColor(float $t): string
    {
        static $stops = [
            [0.00, 0x1e, 0x3a, 0x5f],
            [0.25, 0xf9, 0x73, 0x16],
            [0.50, 0xfb, 0xbf, 0x24],
            [0.75, 0xf9, 0x73, 0x16],
            [1.00, 0x1e, 0x3a, 0x5f],
        ];

        $prev = $stops[0];
        $next = $stops[count($stops) - 1];
        for ($i = 0; $i < count($stops) - 1; $i++) {
            if ($t >= $stops[$i][0] && $t <= $stops[$i + 1][0]) {
                $prev = $stops[$i];
                $next = $stops[$i + 1];
                break;
            }
        }

        $range = $next[0] - $prev[0];
        $f     = $range > 0 ? ($t - $prev[0]) / $range : 0;
        $r     = (int)round($prev[1] + ($next[1] - $prev[1]) * $f);
        $g     = (int)round($prev[2] + ($next[2] - $prev[2]) * $f);
        $b     = (int)round($prev[3] + ($next[3] - $prev[3]) * $f);
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Draw a compass rose showing the wind direction.
     * $windDirDeg is the standard meteorological bearing (0 = from N, 90 = from E, …).
     */
    private function drawCompassRose(int $cx, int $cy, int $r, ?int $windDirDeg): void
    {
        try {
            $ac     = $this->currentAccent ?? self::INDIGO;
            $face   = $ac . '18';
            $border = $ac . '66';
            $tick   = $ac . '99';

            // Compass face
            $this->img->drawEllipse($cx, $cy, static function ($d) use ($r, $face, $border) {
                $d->size($r * 2, $r * 2);
                $d->background($face);
                $d->border(1, $border);
            });

            // 8 tick marks rendered as dot chains (GD-safe: no drawLine)
            for ($i = 0; $i < 8; $i++) {
                $a      = $i * M_PI / 4;
                $isCard = ($i % 2 === 0);
                $r1     = $isCard ? $r - 14 : $r - 9;
                $r2     = $r - 2;
                $steps  = $isCard ? 5 : 3;
                for ($s = 0; $s <= $steps; $s++) {
                    $rr = $r1 + ($r2 - $r1) * $s / $steps;
                    $dx = (int)round($cx + $rr * sin($a));
                    $dy = (int)round($cy - $rr * cos($a));
                    $ds = $isCard ? 3 : 2;
                    $this->img->drawEllipse($dx, $dy, static function ($d) use ($tick, $ds) {
                        $d->size($ds, $ds);
                        $d->background($tick);
                    });
                }
            }

            // "N" label
            $this->t('N', $cx, $cy - $r + 5, $this->fontBold, 13, $ac, 'center', 'top');

            // Wind direction arrow rendered as a dot chain (GD-safe)
            if ($windDirDeg !== null) {
                $rad   = $windDirDeg * M_PI / 180;
                $tipR  = $r - 8;
                $tailR = -(int)($r * 0.35);
                $steps = 18;
                for ($s = 0; $s <= $steps; $s++) {
                    $rr = $tailR + ($tipR - $tailR) * $s / $steps;
                    $dx = (int)round($cx + $rr * sin($rad));
                    $dy = (int)round($cy - $rr * cos($rad));
                    $this->img->drawEllipse($dx, $dy, static function ($d) use ($ac) {
                        $d->size(4, 4);
                        $d->background($ac);
                    });
                }
                // Arrowhead dot at tip
                $tipX = (int)round($cx + $tipR * sin($rad));
                $tipY = (int)round($cy - $tipR * cos($rad));
                $this->img->drawEllipse($tipX, $tipY, static function ($d) use ($ac) {
                    $d->size(10, 10);
                    $d->background($ac);
                });
            }

            // Centre dot
            $this->img->drawEllipse($cx, $cy, static function ($d) use ($border) {
                $d->size(6, 6);
                $d->background($border);
            });
        } catch (\Throwable) {}
    }

    /** Draw text at (x, y) using the given font, size, and colour. */
    private function t(string $text, int $x, int $y, string $fontPath, int $size, string $color,
        string $align = 'left', string $valign = 'top'): void
    {
        $fp = $fontPath;
        $sz = $size;
        // Sample the rendered surface, including badge fills and the top tint.
        $background = $this->classicAppearance ? null : $this->img->pickColor(
            max(0, min(self::W - 1, $x + ($align === 'right' ? -1 : 1))),
            max(0, min(self::H - 1, $y))
        )->toHex('#');
        $c  = $this->readableTextColour($color, $background);
        $a  = $align;
        $va = $valign;

        $this->img->text($text, $x, $y, static function ($font) use ($fp, $sz, $c, $a, $va) {
            $font->filename($fp);
            $font->size($sz);
            $font->color($c);
            $font->align($a);
            $font->valign($va);
        });
    }

    /** Keep semantic hues where possible, darkening/lightening only when text would fail contrast. */
    private function readableTextColour(string $colour, ?string $surface = null): string
    {
        if ($this->classicAppearance || ! preg_match('/^#([0-9a-f]{6})$/i', $colour, $match)) {
            return $colour;
        }

        $rgb = array_map('hexdec', str_split($match[1], 2));
        $background = array_map('hexdec', str_split(substr($surface ?? $this->colour('bg'), 1), 2));
        if ($this->contrastRatio($rgb, $background) >= 4.5) {
            return $colour;
        }

        $black = [0, 0, 0];
        $white = [255, 255, 255];
        $target = $this->contrastRatio($black, $background) >= $this->contrastRatio($white, $background)
            ? $black
            : $white;
        for ($amount = 0.1; $amount <= 1; $amount += 0.1) {
            $candidate = array_map(
                static fn (int $channel, int $toward): int => (int) round($channel + ($toward - $channel) * $amount),
                $rgb,
                $target
            );
            if ($this->contrastRatio($candidate, $background) >= 4.5) {
                return sprintf('#%02x%02x%02x', ...$candidate);
            }
        }

        return $target === [0, 0, 0] ? '#000000' : '#ffffff';
    }

    private function contrastRatio(array $a, array $b): float
    {
        $lighter = max($this->luminance($a), $this->luminance($b));
        $darker = min($this->luminance($a), $this->luminance($b));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function luminance(array $rgb): float
    {
        $channels = array_map(static function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /** Draw a filled rectangle. */
    private function rect(int $x, int $y, int $w, int $h, string $color): void
    {
        $this->img->drawRectangle($x, $y, static function ($draw) use ($w, $h, $color) {
            $draw->size($w, $h);
            $draw->background($color);
        });
    }

    /**
     * Composite the site logo onto the canvas at the given position.
     * Scales the logo to LOGO_H pixels tall (preserving aspect ratio).
     * Returns the actual rendered width so callers can offset text, or 0 if the logo is unavailable.
     */
    private function placeLogo(int $x, int $y): int
    {
        $logoPath = public_path('images/logo_full_256w.png');
        if (!file_exists($logoPath)) {
            return 0;
        }

        try {
            $logo = $this->manager->read($logoPath)->scale(height: self::LOGO_H);
            $this->img->place($logo, 'top-left', $x, $y);
            return $logo->width();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Encode the current canvas to a PNG binary string. */
    private function encode(): string
    {
        return (string) $this->img->toPng();
    }

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    /** Convert wind-direction degrees to 8-point compass abbreviation. */
    private function compass(?float $deg): string
    {
        if ($deg === null) return '';
        $dirs = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW', 'N'];
        return $dirs[(int) round($deg / 45) % 8];
    }

    /** Return a hex colour appropriate for the given AQI value. */
    private function aqiColor(?float $aqi): string
    {
        if ($aqi === null) return $this->colour('secondary');
        if ($aqi <= 50)  return '#22c55e';
        if ($aqi <= 100) return self::AMBER;
        if ($aqi <= 150) return '#f97316';
        if ($aqi <= 200) return self::RED;
        return '#8b5cf6';
    }

    // -------------------------------------------------------------------------
    // Setup
    // -------------------------------------------------------------------------

    private function setupDriver(): void
    {
        $resolved = static::resolvedDriver();

        if ($resolved === null) {
            throw new \RuntimeException('OG image generation requires the GD or Imagick PHP extension, but neither is available.');
        }

        $this->manager = match ($resolved) {
            'imagick' => new ImageManager(new ImagickDriver()),
            default   => new ImageManager(new GdDriver()),
        };
    }

    private function setupFonts(): void
    {
        $base = base_path('resources/fonts');

        $regular = $base . '/Inter-Regular.ttf';
        $bold    = $base . '/Inter-Bold.ttf';

        // Fall back to a system font if bundled fonts are missing.
        if (!file_exists($regular)) {
            $regular = $this->findSystemFont() ?? $regular;
        }
        if (!file_exists($bold)) {
            // The Bold copy is the same variable font; visual differentiation
            // is achieved through size hierarchy in the card layouts.
            $bold = file_exists($base . '/Inter-Regular.ttf')
                ? $base . '/Inter-Regular.ttf'
                : ($this->findSystemFont() ?? $regular);
        }

        $this->fontRegular = $regular;
        $this->fontBold    = $bold;
    }

    /** Look for a usable system TTF font on common Linux/macOS paths. */
    private function findSystemFont(): ?string
    {
        $candidates = [
            // Linux (Debian/Ubuntu/CentOS)
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
            '/usr/share/fonts/truetype/noto/NotoSans-Regular.ttf',
            // macOS
            '/Library/Fonts/Arial Unicode.ttf',
            '/System/Library/Fonts/SFNS.ttf',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) return $path;
        }
        return null;
    }
}
