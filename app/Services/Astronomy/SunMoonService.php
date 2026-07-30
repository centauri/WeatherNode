<?php

namespace App\Services\Astronomy;

use App\Models\Setting;
use App\Support\WindCompass;
use Illuminate\Support\Facades\Cache;

/**
 * Sun and moon calculations for the station location.
 * Uses Setting::latitude(), Setting::longitude() and Setting::timezone()
 * for all rise/set times, positions and elevations.
 */
class SunMoonService
{
    private float $latitude;
    private float $longitude;
    private string $timezone;

    public function __construct()
    {
        $this->latitude = Setting::latitude();
        $this->longitude = Setting::longitude();
        $this->timezone = Setting::timezone();
    }

    /**
     * Get comprehensive sun data for a specific date
     */
    public function getSunData(\DateTime $date = null): array
    {
        $date = $date ?? now();
        $stationTz = new \DateTimeZone($this->timezone);
        $dateStation = (clone $date)->setTimezone($stationTz);
        $cacheKey = "sun_data_{$dateStation->format('Y-m-d_H')}_{$this->latitude}_{$this->longitude}";

        return Cache::remember($cacheKey, 300, function () use ($date) {
            $sunInfo = date_sun_info(
                $date->getTimestamp(),
                $this->latitude,
                $this->longitude
            );

            // Get yesterday's sun info for day length comparison
            $yesterday = $date->copy()->subDay();
            $yesterdaySunInfo = date_sun_info(
                $yesterday->getTimestamp(),
                $this->latitude,
                $this->longitude
            );

            // Calculate sun position (elevation and azimuth)
            $sunPosition = $this->calculateSunPosition($date);

            $dayLengthToday = $sunInfo['sunset'] - $sunInfo['sunrise'];
            $dayLengthYesterday = $yesterdaySunInfo['sunset'] - $yesterdaySunInfo['sunrise'];
            $dayLengthChange = $dayLengthToday - $dayLengthYesterday;

            return [
                'sunrise' => $this->formatTime($sunInfo['sunrise']),
                'sunset' => $this->formatTime($sunInfo['sunset']),
                'solar_noon' => $this->formatTime($sunInfo['transit']),
                'civil_twilight_begin' => $this->formatTime($sunInfo['civil_twilight_begin']),
                'civil_twilight_end' => $this->formatTime($sunInfo['civil_twilight_end']),
                'nautical_twilight_begin' => $this->formatTime($sunInfo['nautical_twilight_begin']),
                'nautical_twilight_end' => $this->formatTime($sunInfo['nautical_twilight_end']),
                'astronomical_twilight_begin' => $this->formatTime($sunInfo['astronomical_twilight_begin']),
                'astronomical_twilight_end' => $this->formatTime($sunInfo['astronomical_twilight_end']),
                'day_length' => $this->calculateDayLength($sunInfo['sunrise'], $sunInfo['sunset']),
                'day_length_seconds' => $dayLengthToday,
                'day_length_change' => $this->formatDayLengthChange($dayLengthChange),
                'day_length_change_seconds' => $dayLengthChange,
                'elevation' => round($sunPosition['elevation'], 1),
                'azimuth' => round($sunPosition['azimuth'], 1),
                'direction' => $this->getDirection($sunPosition['azimuth']),
                'is_up' => $sunPosition['elevation'] > 0,
                'position_percent' => $this->calculateSunPositionPercent($sunInfo, $date),
            ];
        });
    }

    /**
     * Get comprehensive moon data for a specific date
     */
    public function getMoonData(\DateTime $date = null): array
    {
        $date = $date ?? now();
        $stationTz = new \DateTimeZone($this->timezone);
        $dateStation = (clone $date)->setTimezone($stationTz);
        $cacheKey = "moon_data_{$dateStation->format('Y-m-d_H')}_{$this->latitude}_{$this->longitude}";

        return Cache::remember($cacheKey, 300, function () use ($date) {
            // Calculate moon phase using MoonPhase algorithm
            $moonPhase = $this->calculateMoonPhaseData($date);
            
            // Calculate moonrise/moonset (uses station timezone internally)
            $moonTimes = $this->calculateMoonTimes($date);
            
            // Calculate moon position (uses station timezone internally)
            $moonPosition = $this->calculateMoonPosition($date);

            return [
                'phase' => $moonPhase['phase'],
                'phase_name' => $this->translatePhaseName($moonPhase['phase_name']),
                'illumination' => round($moonPhase['illumination'] * 100, 1),
                'age' => round($moonPhase['age'], 1),
                'distance' => round($moonPhase['distance']),
                'diameter' => round($moonPhase['diameter'], 4),
                'emoji' => $this->getMoonEmoji($moonPhase['phase']),
                'icon' => $this->getMoonIcon($moonPhase['phase']),
                'moonrise' => $moonTimes['moonrise'],
                'moonset' => $moonTimes['moonset'],
                'new_moon' => $this->formatDate($moonPhase['new_moon']),
                'next_new_moon' => $this->formatDate($moonPhase['next_new_moon']),
                'full_moon' => $this->formatDate($moonPhase['full_moon']),
                'next_full_moon' => $this->formatDate($moonPhase['next_full_moon']),
                'first_quarter' => $this->formatDate($moonPhase['first_quarter']),
                'next_first_quarter' => $this->formatDate($moonPhase['next_first_quarter']),
                'last_quarter' => $this->formatDate($moonPhase['last_quarter']),
                'next_last_quarter' => $this->formatDate($moonPhase['next_last_quarter']),
                'elevation' => round($moonPosition, 1),
                'is_up' => $moonPosition > 0,
            ];
        });
    }

    /**
     * Get legacy format for backwards compatibility
     */
    public function getSunTimes(\DateTime $date = null): array
    {
        $sunData = $this->getSunData($date);
        return [
            'sunrise' => $sunData['sunrise'],
            'sunset' => $sunData['sunset'],
            'solar_noon' => $sunData['solar_noon'],
            'civil_twilight_begin' => $sunData['civil_twilight_begin'],
            'civil_twilight_end' => $sunData['civil_twilight_end'],
            'nautical_twilight_begin' => $sunData['nautical_twilight_begin'],
            'nautical_twilight_end' => $sunData['nautical_twilight_end'],
            'astronomical_twilight_begin' => $sunData['astronomical_twilight_begin'],
            'astronomical_twilight_end' => $sunData['astronomical_twilight_end'],
            'day_length' => $sunData['day_length'],
            'elevation' => $sunData['elevation'],
            'azimuth' => $sunData['azimuth'],
        ];
    }

    /**
     * Get legacy format for backwards compatibility
     */
    public function getMoonPhase(\DateTime $date = null): array
    {
        $moonData = $this->getMoonData($date);
        return [
            'phase' => $moonData['phase'],
            'phase_name' => $moonData['phase_name'],
            'illumination' => $moonData['illumination'],
            'emoji' => $moonData['emoji'],
            'moonrise' => $moonData['moonrise'],
            'moonset' => $moonData['moonset'],
            'distance' => $moonData['distance'],
        ];
    }

    /**
     * Calculate sun position (elevation and azimuth)
     * Based on Keith Burnett's algorithm
     */
    private function calculateSunPosition(\DateTime $date): array
    {
        $timestamp = $date->getTimestamp();
        $timezone = new \DateTimeZone($this->timezone);
        $dateTime = new \DateTime("@$timestamp");
        $dateTime->setTimezone($timezone);
        
        $tdiff = $dateTime->format('Z') / 3600;
        $year = (int) $dateTime->format('Y');
        $month = (int) $dateTime->format('n');
        $day = (int) $dateTime->format('j');
        $hour = (float) $dateTime->format('G') + ((float) $dateTime->format('i') / 60);

        // Calculate Julian day
        $FNday = (367 * $year) - floor(7 * ($year + floor(($month + 9) / 12)) / 4) 
                 + (floor(275 * $month / 9) + $day - 730531.5 + (-$tdiff) / 24) + ($hour / 24);

        // Sun's mean longitude
        $lsun = fmod((280.461 + (0.9856474 * $FNday)), 360);
        
        // Mean anomaly
        $gee = fmod((357.528 + (0.9856003 * $FNday)), 360);
        
        // Ecliptic longitude
        $lambda = ($lsun + 1.915 * sin(deg2rad($gee))) + (0.02 * sin(deg2rad(2 * $gee)));
        
        // Obliquity of ecliptic
        $dsj2k = (367 * $year) - floor(7 * ($year + floor(($month + 9) / 12)) / 4) 
                 + (floor(275 * $month / 9) + $day - 730531.5 + (-$tdiff) / 24);
        $epsilon = (23.439 - (0.0000004 * $dsj2k));
        
        $eff = (180 / M_PI);
        $tee = pow(tan(deg2rad($epsilon / 2)), 2);
        
        // Right ascension
        $rasun = $lambda - $eff * $tee * sin(deg2rad(2 * $lambda)) 
                 + $eff / 2 * pow($tee, 2) * sin(deg2rad(4 * $lambda));
        
        // Declination
        $decsun = rad2deg(asin(sin(deg2rad($epsilon)) * sin(deg2rad($lambda))));
        
        // Hour angle
        $hasun = fmod((280.46061837 + (360.98564736629 * $FNday) 
                 + ($this->longitude - $rasun)), 360);
        
        // Altitude (elevation)
        $elevation = rad2deg(asin(
            sin(deg2rad($decsun)) * sin(deg2rad($this->latitude)) 
            + cos(deg2rad($decsun)) * cos(deg2rad($this->latitude)) * cos(deg2rad($hasun))
        ));

        // Azimuth calculation
        $coslat = cos(deg2rad($this->latitude));
        $sinlat = sin(deg2rad($this->latitude));
        $rasunr = deg2rad($rasun);
        $ddat = ($decsun + (20.0383 * cos($rasunr)) / 36 * ($FNday / 36525));
        $cosddat = cos(deg2rad($ddat));
        $sinddat = sin(deg2rad($ddat));
        $sinHA = sin(deg2rad($hasun));
        $sinalt = sin(deg2rad($elevation));

        $suny = (-1 * $coslat * $cosddat * $sinHA);
        $sunx = ($sinddat - $sinlat * $sinalt);
        $sunA1 = atan2($suny, $sunx);

        if ($sunx < 0) {
            $sunazr = (M_PI + $sunA1);
        } elseif ($suny < 0) {
            $sunazr = ((2 * M_PI) + $sunA1);
        } else {
            $sunazr = ($sunA1);
        }

        $azimuth = rad2deg($sunazr);
        if ($azimuth < 0) {
            $azimuth += 360;
        }

        return [
            'elevation' => $elevation,
            'azimuth' => $azimuth,
        ];
    }

    /**
     * Calculate moon position (altitude only for simplicity)
     */
    private function calculateMoonPosition(\DateTime $date): float
    {
        $rad = M_PI / 180;
        $daySeconds = 86400;
        $J1970 = 2440588;
        $J2000 = 2451545;

        $toJulian = function (\DateTime $d) use ($daySeconds, $J1970): float {
            return ($d->getTimestamp() / $daySeconds) - 0.5 + $J1970;
        };

        $toDays = function (\DateTime $d) use ($toJulian, $J2000): float {
            return $toJulian($d) - $J2000;
        };

        $rightAscension = function (float $l, float $b) use ($rad): float {
            $e = $rad * 23.4397;
            return atan2(sin($l) * cos($e) - tan($b) * sin($e), cos($l));
        };

        $declination = function (float $l, float $b) use ($rad): float {
            $e = $rad * 23.4397;
            return asin(sin($b) * cos($e) + cos($b) * sin($e) * sin($l));
        };

        $siderealTime = function (float $d, float $lw) use ($rad): float {
            return $rad * (280.16 + 360.9856235 * $d) - $lw;
        };

        $moonCoords = function (float $d) use ($rad, $rightAscension, $declination): array {
            $L = $rad * (218.316 + 13.176396 * $d);
            $M = $rad * (134.963 + 13.064993 * $d);
            $F = $rad * (93.272 + 13.229350 * $d);

            $l = $L + $rad * 6.289 * sin($M);
            $b = $rad * 5.128 * sin($F);

            return [
                'ra' => $rightAscension($l, $b),
                'dec' => $declination($l, $b),
            ];
        };

        $lw = -$this->longitude * $rad;
        $phi = $this->latitude * $rad;
        $d = $toDays($date);
        $c = $moonCoords($d);
        $H = $siderealTime($d, $lw) - $c['ra'];

        $h = asin(sin($phi) * sin($c['dec']) + cos($phi) * cos($c['dec']) * cos($H));
        return rad2deg($h);
    }

    /**
     * Calculate moonrise and moonset times
     * Based on Keith Burnett / Matt Hackmann algorithm
     * Uses station timezone so rise/set times are always in local time for the station.
     */
    private function calculateMoonTimes(\DateTime $date): array
    {
        $stationTz = new \DateTimeZone($this->timezone);
        $dateLocal = (clone $date)->setTimezone($stationTz);
        $month = (int) $dateLocal->format('n');
        $day = (int) $dateLocal->format('j');
        $year = (int) $dateLocal->format('Y');
        $offsetSeconds = $dateLocal->getOffset();
        $timezoneHours = round($offsetSeconds / 3600, 1);

        // MJD for the local calendar date — do NOT subtract timezone here.
        // The algorithm scans hours 0-24 in UT and moonLmst() already
        // incorporates longitude. The timezone offset is added to the
        // resulting UT rise/set times to convert them to local time.
        $mjd = $this->modifiedJulianDate($month, $day, $year);

        $latRad = deg2rad($this->latitude);
        $sinho = 0.0023271056;
        $sglat = sin($latRad);
        $cglat = cos($latRad);

        $rise = false;
        $set = false;
        $utrise = $utset = 0;
        $hour = 1;
        $ym = $this->moonSinAlt($mjd, $hour - 1, $this->longitude, $cglat, $sglat) - $sinho;

        while ($hour < 25 && (!$set || !$rise)) {
            $yz = $this->moonSinAlt($mjd, $hour, $this->longitude, $cglat, $sglat) - $sinho;
            $yp = $this->moonSinAlt($mjd, $hour + 1, $this->longitude, $cglat, $sglat) - $sinho;

            $quadout = $this->moonQuad($ym, $yz, $yp);
            $nz = $quadout[0];
            $z1 = $quadout[1];
            $z2 = $quadout[2];
            $ye = $quadout[4];

            if ($nz == 1) {
                if ($ym < 0) {
                    $utrise = $hour + $z1;
                    $rise = true;
                } else {
                    $utset = $hour + $z1;
                    $set = true;
                }
            }

            if ($nz == 2) {
                if ($ye < 0) {
                    $utrise = $hour + $z2;
                    $utset = $hour + $z1;
                } else {
                    $utrise = $hour + $z1;
                    $utset = $hour + $z2;
                }
            }

            $ym = $yp;
            $hour += 2.0;
        }

        $moonrise = null;
        $moonset = null;

        if ($rise) {
            $localRise = $utrise + $timezoneHours;
            if ($localRise >= 24) $localRise -= 24;
            if ($localRise < 0) $localRise += 24;
            $riseTime = $this->convertMoonTime($localRise);
            $moonrise = sprintf('%02d:%02d', $riseTime['hrs'], $riseTime['min']);
        }

        if ($set) {
            $localSet = $utset + $timezoneHours;
            if ($localSet >= 24) $localSet -= 24;
            if ($localSet < 0) $localSet += 24;
            $setTime = $this->convertMoonTime($localSet);
            $moonset = sprintf('%02d:%02d', $setTime['hrs'], $setTime['min']);
        }

        return [
            'moonrise' => $moonrise,
            'moonset' => $moonset,
        ];
    }

    private function modifiedJulianDate(int $month, int $day, int $year): float
    {
        if ($month <= 2) {
            $month += 12;
            $year--;
        }

        $a = 10000 * $year + 100 * $month + $day;
        $b = 0;
        
        if ($a <= 15821004.1) {
            $b = -2 * (int)(($year + 4716) / 4) - 1179;
        } else {
            $b = (int)($year / 400) - (int)($year / 100) + (int)($year / 4);
        }

        $a = 365 * $year - 679004;
        return $a + $b + (int)(30.6001 * ($month + 1)) + $day;
    }

    private function moonSinAlt(float $mjd, float $hour, float $glon, float $cglat, float $sglat): float
    {
        $mjd += $hour / 24;
        $t = ($mjd - 51544.5) / 36525;
        $objpos = $this->minimoon($t);

        $ra = $objpos[1];
        $dec = $objpos[0];
        $decRad = deg2rad($dec);
        $tau = 15 * ($this->moonLmst($mjd, $glon) - $ra);

        return $sglat * sin($decRad) + $cglat * cos($decRad) * cos(deg2rad($tau));
    }

    private function moonLmst(float $mjd, float $glon): float
    {
        $d = $mjd - 51544.5;
        $t = $d / 36525;
        $lst = $this->moonDegRange(280.46061839 + 360.98564736629 * $d + 0.000387933 * $t * $t - $t * $t * $t / 38710000);
        return $lst / 15 + $glon / 15;
    }

    private function moonDegRange(float $x): float
    {
        $b = $x / 360;
        $a = 360 * ($b - (int)$b);
        return $a < 0 ? $a + 360 : $a;
    }

    private function minimoon(float $t): array
    {
        $p2 = 6.283185307;
        $arc = 206264.8062;
        $coseps = 0.91748;
        $sineps = 0.39778;

        $lo = $this->moonFrac(0.606433 + 1336.855225 * $t);
        $l = $p2 * $this->moonFrac(0.374897 + 1325.552410 * $t);
        $l2 = $l * 2;
        $ls = $p2 * $this->moonFrac(0.993133 + 99.997361 * $t);
        $d = $p2 * $this->moonFrac(0.827361 + 1236.853086 * $t);
        $d2 = $d * 2;
        $f = $p2 * $this->moonFrac(0.259086 + 1342.227825 * $t);
        $f2 = $f * 2;

        $sinls = sin($ls);
        $sinf2 = sin($f2);

        $dl = 22640 * sin($l);
        $dl += -4586 * sin($l - $d2);
        $dl += 2370 * sin($d2);
        $dl += 769 * sin($l2);
        $dl += -668 * $sinls;
        $dl += -412 * $sinf2;
        $dl += -212 * sin($l2 - $d2);
        $dl += -206 * sin($l + $ls - $d2);
        $dl += 192 * sin($l + $d2);
        $dl += -165 * sin($ls - $d2);
        $dl += -125 * sin($d);
        $dl += -110 * sin($l + $ls);
        $dl += 148 * sin($l - $ls);
        $dl += -55 * sin($f2 - $d2);

        $s = $f + ($dl + 412 * $sinf2 + 541 * $sinls) / $arc;
        $h = $f - $d2;
        $n = -526 * sin($h);
        $n += 44 * sin($l + $h);
        $n += -31 * sin(-$l + $h);
        $n += -23 * sin($ls + $h);
        $n += 11 * sin(-$ls + $h);
        $n += -25 * sin(-$l2 + $f);
        $n += 21 * sin(-$l + $f);

        $L_moon = $p2 * $this->moonFrac($lo + $dl / 1296000);
        $B_moon = (18520.0 * sin($s) + $n) / $arc;

        $cb = cos($B_moon);
        $x = $cb * cos($L_moon);
        $v = $cb * sin($L_moon);
        $w = sin($B_moon);
        $y = $coseps * $v - $sineps * $w;
        $z = $sineps * $v + $coseps * $w;
        $rho = sqrt(1 - $z * $z);
        $dec = (360 / $p2) * atan($z / $rho);
        $ra = (48 / $p2) * atan($y / ($x + $rho));
        $ra = $ra < 0 ? $ra + 24 : $ra;

        return [$dec, $ra];
    }

    private function moonFrac(float $x): float
    {
        $x -= (int)$x;
        return $x < 0 ? $x + 1 : $x;
    }

    private function moonQuad(float $ym, float $yz, float $yp): array
    {
        $nz = $z1 = $z2 = 0;
        $a = 0.5 * ($ym + $yp) - $yz;
        $b = 0.5 * ($yp - $ym);
        $c = $yz;
        $xe = -$b / (2 * $a);
        $ye = ($a * $xe + $b) * $xe + $c;
        $dis = $b * $b - 4 * $a * $c;
        
        if ($dis > 0) {
            $dx = 0.5 * sqrt($dis) / abs($a);
            $z1 = $xe - $dx;
            $z2 = $xe + $dx;
            $nz = abs($z1) < 1 ? $nz + 1 : $nz;
            $nz = abs($z2) < 1 ? $nz + 1 : $nz;
            $z1 = $z1 < -1 ? $z2 : $z1;
        }

        return [$nz, $z1, $z2, $xe, $ye];
    }

    private function convertMoonTime(float $hours): array
    {
        $hrs = (int)($hours * 60 + 0.5) / 60.0;
        $h = (int)($hrs);
        $m = (int)(60 * ($hrs - $h) + 0.5);
        return ['hrs' => $h, 'min' => $m];
    }

    /**
     * Calculate detailed moon phase data using MoonPhase algorithm
     */
    private function calculateMoonPhaseData(\DateTime $date): array
    {
        $timestamp = $date->getTimestamp();
        
        // Astronomical constants
        $epoch = 2444238.5;
        $elonge = 278.833540;
        $elongp = 282.596403;
        $eccent = 0.016718;
        $sunsmax = 1.495985e8;
        $sunangsiz = 0.533128;
        $mmlong = 64.975464;
        $mmlongp = 349.383063;
        $mlnode = 151.950429;
        $minc = 5.145396;
        $mecc = 0.054900;
        $mangsiz = 0.5181;
        $msmax = 384401;
        $synmonth = 29.53058868;

        $pdate = $timestamp / 86400 + 2440587.5;
        $Day = $pdate - $epoch;
        $N = $this->fixAngle((360 / 365.2422) * $Day);
        $M = $this->fixAngle($N + $elonge - $elongp);
        $Ec = $this->kepler($M, $eccent);
        $Ec = sqrt((1 + $eccent) / (1 - $eccent)) * tan($Ec / 2);
        $Ec = 2 * rad2deg(atan($Ec));
        $Lambdasun = $this->fixAngle($Ec + $elongp);
        $F = ((1 + $eccent * cos(deg2rad($Ec))) / (1 - $eccent * $eccent));
        $SunDist = $sunsmax / $F;

        $ml = $this->fixAngle(13.1763966 * $Day + $mmlong);
        $MM = $this->fixAngle($ml - 0.1114041 * $Day - $mmlongp);
        $MN = $this->fixAngle($mlnode - 0.0529539 * $Day);
        $Ev = 1.2739 * sin(deg2rad(2 * ($ml - $Lambdasun) - $MM));
        $Ae = 0.1858 * sin(deg2rad($M));
        $A3 = 0.37 * sin(deg2rad($M));
        $MmP = $MM + $Ev - $Ae - $A3;
        $mEc = 6.2886 * sin(deg2rad($MmP));
        $A4 = 0.214 * sin(deg2rad(2 * $MmP));
        $lP = $ml + $Ev + $mEc - $Ae + $A4;
        $V = 0.6583 * sin(deg2rad(2 * ($lP - $Lambdasun)));
        $lPP = $lP + $V;
        $NP = $MN - 0.16 * sin(deg2rad($M));

        $MoonAge = $lPP - $Lambdasun;
        $MoonPhase = (1 - cos(deg2rad($MoonAge))) / 2;
        $MoonDist = ($msmax * (1 - $mecc * $mecc)) / (1 + $mecc * cos(deg2rad($MmP + $mEc)));
        $MoonDFrac = $MoonDist / $msmax;
        $MoonAng = $mangsiz / $MoonDFrac;

        $phase = $this->fixAngle($MoonAge) / 360;
        $illumination = $MoonPhase;
        $age = $synmonth * $phase;

        // Get phase quarters
        $quarters = $this->calculatePhaseQuarters($timestamp, $synmonth);

        return [
            'phase' => $phase,
            'illumination' => $illumination,
            'age' => $age,
            'distance' => $MoonDist,
            'diameter' => $MoonAng,
            'sun_distance' => $SunDist,
            'phase_name' => $this->getEnglishPhaseName($phase),
            'new_moon' => $quarters[0],
            'first_quarter' => $quarters[1],
            'full_moon' => $quarters[2],
            'last_quarter' => $quarters[3],
            'next_new_moon' => $quarters[4],
            'next_first_quarter' => $quarters[5],
            'next_full_moon' => $quarters[6],
            'next_last_quarter' => $quarters[7],
        ];
    }

    private function calculatePhaseQuarters(int $timestamp, float $synmonth): array
    {
        $sdate = $timestamp / 86400 + 2440587.5;
        $adate = $sdate - 45;
        $ats = $timestamp - 86400 * 45;
        $yy = (int) gmdate('Y', $ats);
        $mm = (int) gmdate('n', $ats);

        $k1 = floor(($yy + (($mm - 1) * (1 / 12)) - 1900) * 12.3685);
        $nt1 = $this->meanPhase($adate, $k1, $synmonth);

        while (true) {
            $adate += $synmonth;
            $k2 = $k1 + 1;
            $nt2 = $this->meanPhase($adate, $k2, $synmonth);
            
            if (abs($nt2 - $sdate) < 0.75) {
                $nt2 = $this->truePhase($k2, 0.0, $synmonth);
            }
            
            if ($nt1 <= $sdate && $nt2 > $sdate) {
                break;
            }
            
            $nt1 = $nt2;
            $k1 = $k2;
        }

        $data = [
            $this->truePhase($k1, 0.0, $synmonth),
            $this->truePhase($k1, 0.25, $synmonth),
            $this->truePhase($k1, 0.5, $synmonth),
            $this->truePhase($k1, 0.75, $synmonth),
            $this->truePhase($k2, 0.0, $synmonth),
            $this->truePhase($k2, 0.25, $synmonth),
            $this->truePhase($k2, 0.5, $synmonth),
            $this->truePhase($k2, 0.75, $synmonth),
        ];

        return array_map(fn($v) => ($v - 2440587.5) * 86400, $data);
    }

    private function meanPhase(float $sdate, float $k, float $synmonth): float
    {
        $t = ($sdate - 2415020.0) / 36525;
        $t2 = $t * $t;
        $t3 = $t2 * $t;

        return 2415020.75933 + $synmonth * $k
            + 0.0001178 * $t2
            - 0.000000155 * $t3
            + 0.00033 * sin(deg2rad(166.56 + 132.87 * $t - 0.009173 * $t2));
    }

    private function truePhase(float $k, float $phase, float $synmonth): float
    {
        $k += $phase;
        $t = $k / 1236.85;
        $t2 = $t * $t;
        $t3 = $t2 * $t;
        
        $pt = 2415020.75933
            + $synmonth * $k
            + 0.0001178 * $t2
            - 0.000000155 * $t3
            + 0.00033 * sin(deg2rad(166.56 + 132.87 * $t - 0.009173 * $t2));

        $m = 359.2242 + 29.10535608 * $k - 0.0000333 * $t2 - 0.00000347 * $t3;
        $mprime = 306.0253 + 385.81691806 * $k + 0.0107306 * $t2 + 0.00001236 * $t3;
        $f = 21.2964 + 390.67050646 * $k - 0.0016528 * $t2 - 0.00000239 * $t3;

        if ($phase < 0.01 || abs($phase - 0.5) < 0.01) {
            $pt += (0.1734 - 0.000393 * $t) * sin(deg2rad($m))
                + 0.0021 * sin(deg2rad(2 * $m))
                - 0.4068 * sin(deg2rad($mprime))
                + 0.0161 * sin(deg2rad(2 * $mprime))
                - 0.0004 * sin(deg2rad(3 * $mprime))
                + 0.0104 * sin(deg2rad(2 * $f))
                - 0.0051 * sin(deg2rad($m + $mprime))
                - 0.0074 * sin(deg2rad($m - $mprime))
                + 0.0004 * sin(deg2rad(2 * $f + $m))
                - 0.0004 * sin(deg2rad(2 * $f - $m))
                - 0.0006 * sin(deg2rad(2 * $f + $mprime))
                + 0.0010 * sin(deg2rad(2 * $f - $mprime))
                + 0.0005 * sin(deg2rad($m + 2 * $mprime));
        } elseif (abs($phase - 0.25) < 0.01 || abs($phase - 0.75) < 0.01) {
            $pt += (0.1721 - 0.0004 * $t) * sin(deg2rad($m))
                + 0.0021 * sin(deg2rad(2 * $m))
                - 0.6280 * sin(deg2rad($mprime))
                + 0.0089 * sin(deg2rad(2 * $mprime))
                - 0.0004 * sin(deg2rad(3 * $mprime))
                + 0.0079 * sin(deg2rad(2 * $f))
                - 0.0119 * sin(deg2rad($m + $mprime))
                - 0.0047 * sin(deg2rad($m - $mprime))
                + 0.0003 * sin(deg2rad(2 * $f + $m))
                - 0.0004 * sin(deg2rad(2 * $f - $m))
                - 0.0006 * sin(deg2rad(2 * $f + $mprime))
                + 0.0021 * sin(deg2rad(2 * $f - $mprime))
                + 0.0003 * sin(deg2rad($m + 2 * $mprime))
                + 0.0004 * sin(deg2rad($m - 2 * $mprime))
                - 0.0003 * sin(deg2rad(2 * $m + $mprime));

            if ($phase < 0.5) {
                $pt += 0.0028 - 0.0004 * cos(deg2rad($m)) + 0.0003 * cos(deg2rad($mprime));
            } else {
                $pt += -0.0028 + 0.0004 * cos(deg2rad($m)) - 0.0003 * cos(deg2rad($mprime));
            }
        }

        return $pt;
    }

    private function fixAngle(float $a): float
    {
        return $a - 360 * floor($a / 360);
    }

    private function kepler(float $m, float $ecc): float
    {
        $epsilon = 0.000001;
        $e = $m = deg2rad($m);
        
        do {
            $delta = $e - $ecc * sin($e) - $m;
            $e -= $delta / (1 - $ecc * cos($e));
        } while (abs($delta) > $epsilon);
        
        return $e;
    }

    private function getEnglishPhaseName(float $phase): string
    {
        return match (true) {
            $phase < 0.0625 || $phase >= 0.9375 => 'New Moon',
            $phase < 0.1875 => 'Waxing Crescent',
            $phase < 0.3125 => 'First Quarter',
            $phase < 0.4375 => 'Waxing Gibbous',
            $phase < 0.5625 => 'Full Moon',
            $phase < 0.6875 => 'Waning Gibbous',
            $phase < 0.8125 => 'Last Quarter',
            default => 'Waning Crescent',
        };
    }

    private function translatePhaseName(string $name): string
    {
        return $name;
    }

    private function getMoonEmoji(float $phase): string
    {
        return match (true) {
            $phase < 0.0625 || $phase >= 0.9375 => '🌑',
            $phase < 0.1875 => '🌒',
            $phase < 0.3125 => '🌓',
            $phase < 0.4375 => '🌔',
            $phase < 0.5625 => '🌕',
            $phase < 0.6875 => '🌖',
            $phase < 0.8125 => '🌗',
            default => '🌘',
        };
    }

    private function getMoonIcon(float $phase): string
    {
        return match (true) {
            $phase < 0.0625 || $phase >= 0.9375 => 'moon-new',
            $phase < 0.1875 => 'moon-waxing-crescent',
            $phase < 0.3125 => 'moon-first-quarter',
            $phase < 0.4375 => 'moon-waxing-gibbous',
            $phase < 0.5625 => 'moon-full',
            $phase < 0.6875 => 'moon-waning-gibbous',
            $phase < 0.8125 => 'moon-last-quarter',
            default => 'moon-waning-crescent',
        };
    }

    private function formatTime(int|bool $timestamp): ?string
    {
        if ($timestamp === false || $timestamp === true) {
            return null;
        }
        $dt = new \DateTime("@$timestamp");
        $dt->setTimezone(new \DateTimeZone($this->timezone));
        return $dt->format('H:i');
    }

    private function formatDate(int|float $timestamp): string
    {
        $dt = new \DateTime('@' . (int) $timestamp);
        $dt->setTimezone(new \DateTimeZone($this->timezone));
        return $dt->format('j M');
    }

    private function calculateDayLength(int|bool $sunrise, int|bool $sunset): ?string
    {
        if ($sunrise === false || $sunset === false) {
            return null;
        }

        $seconds = $sunset - $sunrise;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return sprintf('%d:%02d', $hours, $minutes);
    }

    private function formatDayLengthChange(int $seconds): string
    {
        $absSeconds = abs($seconds);
        $minutes = floor($absSeconds / 60);
        $secs = $absSeconds % 60;
        $sign = $seconds >= 0 ? '+' : '-';

        if ($minutes > 0) {
            return sprintf('%s%dm %ds', $sign, $minutes, $secs);
        }
        return sprintf('%s%ds', $sign, $secs);
    }

    private function calculateSunPositionPercent(array $sunInfo, \DateTime $date): float
    {
        $now = $date->getTimestamp();
        $sunrise = $sunInfo['sunrise'];
        $sunset = $sunInfo['sunset'];

        if ($sunrise === false || $sunset === false) {
            return 50;
        }

        if ($now < $sunrise) {
            return 0;
        }
        if ($now > $sunset) {
            return 100;
        }

        return round(($now - $sunrise) / ($sunset - $sunrise) * 100, 1);
    }

    private function getDirection(float $degrees): string
    {
        return WindCompass::fromDegrees($degrees);
    }

    /**
     * Get upcoming astronomical events
     */
    public function getUpcomingEvents(int $days = 90): array
    {
        $events = [];
        $today = now();
        $endDate = $today->copy()->addDays($days);

        // Moon phases (including Blue Moon detection)
        // Use a smarter approach: find the closest day to each phase target
        // Lunar cycle is ~29.5 days, so check every ~7 days for phase transitions
        $phaseThreshold = 0.035; // ~1 day tolerance (3.5% of cycle ≈ 1 day)
        $addedMoonEvents = []; // Track added events to avoid duplicates

        for ($i = 0; $i < $days; $i++) {
            $date = $today->copy()->addDays($i);
            $moonData = $this->getMoonData($date);
            $phase = $moonData['phase'];
            $dateKey = $date->format('Y-m-d');

            // New Moon (phase near 0 or 1)
            if (($phase < $phaseThreshold || $phase > (1 - $phaseThreshold)) && !isset($addedMoonEvents[$dateKey.'_new'])) {
                // Check if this is closer to new moon than adjacent days
                $prevPhase = $this->getMoonData($date->copy()->subDay())['phase'];
                $nextPhase = $this->getMoonData($date->copy()->addDay())['phase'];
                $currentDist = min($phase, 1 - $phase);
                $prevDist = min($prevPhase, 1 - $prevPhase);
                $nextDist = min($nextPhase, 1 - $nextPhase);

                if ($currentDist <= $prevDist && $currentDist <= $nextDist) {
                    $events[] = [
                        'date' => $dateKey,
                        'formatted_date' => $date->format('j M'),
                        'type' => 'moon',
                        'event' => 'New Moon',
                        'emoji' => '🌑',
                    ];
                    $addedMoonEvents[$dateKey.'_new'] = true;
                }
            }
            // Full Moon (phase near 0.5)
            elseif (abs($phase - 0.5) < $phaseThreshold && !isset($addedMoonEvents[$dateKey.'_full'])) {
                $prevPhase = $this->getMoonData($date->copy()->subDay())['phase'];
                $nextPhase = $this->getMoonData($date->copy()->addDay())['phase'];
                $currentDist = abs($phase - 0.5);
                $prevDist = abs($prevPhase - 0.5);
                $nextDist = abs($nextPhase - 0.5);

                if ($currentDist <= $prevDist && $currentDist <= $nextDist) {
                    $isSupermoon = $this->isSupermoon($date);
                    $blueMoon = $this->checkBlueMoon($date, $phase);

                    if ($blueMoon) {
                        $events[] = $blueMoon;
                    } else {
                        $events[] = [
                            'date' => $dateKey,
                            'formatted_date' => $date->format('j M'),
                            'type' => 'moon',
                            'event' => $isSupermoon ? 'Supermoon' : 'Full Moon',
                            'emoji' => $isSupermoon ? '🌝' : '🌕',
                        ];
                    }
                    $addedMoonEvents[$dateKey.'_full'] = true;
                }
            }
            // First Quarter (phase near 0.25)
            elseif (abs($phase - 0.25) < $phaseThreshold && !isset($addedMoonEvents[$dateKey.'_first'])) {
                $prevPhase = $this->getMoonData($date->copy()->subDay())['phase'];
                $nextPhase = $this->getMoonData($date->copy()->addDay())['phase'];
                $currentDist = abs($phase - 0.25);
                $prevDist = abs($prevPhase - 0.25);
                $nextDist = abs($nextPhase - 0.25);

                if ($currentDist <= $prevDist && $currentDist <= $nextDist) {
                    $events[] = [
                        'date' => $dateKey,
                        'formatted_date' => $date->format('j M'),
                        'type' => 'moon',
                        'event' => 'First Quarter',
                        'emoji' => '🌓',
                    ];
                    $addedMoonEvents[$dateKey.'_first'] = true;
                }
            }
            // Last Quarter (phase near 0.75)
            elseif (abs($phase - 0.75) < $phaseThreshold && !isset($addedMoonEvents[$dateKey.'_last'])) {
                $prevPhase = $this->getMoonData($date->copy()->subDay())['phase'];
                $nextPhase = $this->getMoonData($date->copy()->addDay())['phase'];
                $currentDist = abs($phase - 0.75);
                $prevDist = abs($prevPhase - 0.75);
                $nextDist = abs($nextPhase - 0.75);

                if ($currentDist <= $prevDist && $currentDist <= $nextDist) {
                    $events[] = [
                        'date' => $dateKey,
                        'formatted_date' => $date->format('j M'),
                        'type' => 'moon',
                        'event' => 'Last Quarter',
                        'emoji' => '🌗',
                    ];
                    $addedMoonEvents[$dateKey.'_last'] = true;
                }
            }
        }

        // Solstices and Equinoxes
        $seasonalEvents = $this->getSeasonalEvents();
        foreach ($seasonalEvents as $event) {
            $eventDate = strtotime($event['date']);
            if ($eventDate >= $today->timestamp && $eventDate <= $endDate->timestamp) {
                $events[] = $event;
            }
        }

        // Eclipses
        $eclipses = $this->getEclipses();
        foreach ($eclipses as $eclipse) {
            $eclipseDate = strtotime($eclipse['date']);
            if ($eclipseDate >= $today->timestamp && $eclipseDate <= $endDate->timestamp) {
                // Check visibility for station location
                $eclipse['visible_here'] = $this->isEclipseVisible($eclipse);
                $events[] = $eclipse;
            }
        }

        // Meteor shower peaks (upcoming, not just active)
        $meteorPeaks = $this->getMeteorShowerPeaks();
        foreach ($meteorPeaks as $meteor) {
            $peakDate = strtotime($meteor['date']);
            if ($peakDate >= $today->timestamp && $peakDate <= $endDate->timestamp) {
                $events[] = $meteor;
            }
        }

        // Planetary events (conjunctions, oppositions, elongations)
        $planetaryEvents = $this->getPlanetaryEvents();
        foreach ($planetaryEvents as $event) {
            $eventDate = strtotime($event['date']);
            if ($eventDate >= $today->timestamp && $eventDate <= $endDate->timestamp) {
                $events[] = $event;
            }
        }

        // Planetary parades / multi-planet alignments (6- or 7-planet)
        $paradeEvents = $this->getPlanetaryParadeEvents();
        foreach ($paradeEvents as $event) {
            $eventDate = strtotime($event['date']);
            if ($eventDate >= $today->timestamp && $eventDate <= $endDate->timestamp) {
                $events[] = $event;
            }
        }

        // Earth orbital events (perihelion/aphelion)
        $earthEvents = $this->getEarthOrbitalEvents();
        foreach ($earthEvents as $event) {
            $eventDate = strtotime($event['date']);
            if ($eventDate >= $today->timestamp && $eventDate <= $endDate->timestamp) {
                $events[] = $event;
            }
        }

        // Venus brilliancy events
        $venusEvents = $this->getVenusBrilliancyEvents();
        foreach ($venusEvents as $event) {
            $eventDate = strtotime($event['date']);
            if ($eventDate >= $today->timestamp && $eventDate <= $endDate->timestamp) {
                $events[] = $event;
            }
        }

        // Comet events
        $cometEvents = $this->getCometEvents();
        foreach ($cometEvents as $event) {
            $eventDate = strtotime($event['date']);
            if ($eventDate >= $today->timestamp && $eventDate <= $endDate->timestamp) {
                $events[] = $event;
            }
        }

        // Zodiacal light viewing periods
        $zodiacalEvents = $this->getZodiacalLightEvents();
        foreach ($zodiacalEvents as $event) {
            $eventDate = strtotime($event['date']);
            if ($eventDate >= $today->timestamp && $eventDate <= $endDate->timestamp) {
                $events[] = $event;
            }
        }

        // Transits (Mercury, Venus across the Sun)
        $transitEvents = $this->getTransitEvents();
        foreach ($transitEvents as $event) {
            $eventDate = strtotime($event['date']);
            if ($eventDate >= $today->timestamp && $eventDate <= $endDate->timestamp) {
                $events[] = $event;
            }
        }

        // Sort by date
        usort($events, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        // Attach hint key to each event (for translation) if not already set
        foreach ($events as &$event) {
            if (!isset($event['hint'])) {
                $hintKey = $this->getEventHintKey($event);
                if ($hintKey !== null) {
                    $event['hint'] = $hintKey;
                }
            }
        }
        unset($event);

        return $events;
    }

    /**
     * Return the translation key for an event's explanation (hint), or null if none.
     */
    private function getEventHintKey(array $event): ?string
    {
        $name = $event['event'] ?? '';
        $type = $event['type'] ?? '';

        if ($type === 'comet') {
            return 'Comet hint';
        }
        if ($type === 'meteor') {
            return 'Meteor shower peak hint';
        }

        $hintMap = [
            'New Moon' => 'New Moon hint',
            'First Quarter' => 'First Quarter hint',
            'Full Moon' => 'Full Moon hint',
            'Last Quarter' => 'Last Quarter hint',
            'Supermoon' => 'Supermoon hint',
            'Blue Moon' => 'Blue Moon hint',
            'Spring Equinox' => 'Spring Equinox hint',
            'Summer Solstice' => 'Summer Solstice hint',
            'Autumn Equinox' => 'Autumn Equinox hint',
            'Winter Solstice' => 'Winter Solstice hint',
            'Penumbral Lunar Eclipse' => 'Penumbral Lunar Eclipse hint',
            'Total Solar Eclipse' => 'Total Solar Eclipse hint',
            'Partial Solar Eclipse' => 'Partial Solar Eclipse hint',
            'Annular Solar Eclipse' => 'Annular Solar Eclipse hint',
            'Total Lunar Eclipse' => 'Total Lunar Eclipse hint',
            'Partial Lunar Eclipse' => 'Partial Lunar Eclipse hint',
            'Hybrid Solar Eclipse' => 'Hybrid Solar Eclipse hint',
            'Mercury at greatest elongation' => 'Mercury at greatest elongation hint',
            'Venus at greatest elongation' => 'Venus at greatest elongation hint',
            'Venus at greatest brilliancy' => 'Venus at greatest brilliancy hint',
            'Mars at opposition' => 'Mars at opposition hint',
            'Jupiter at opposition' => 'Jupiter at opposition hint',
            'Saturn at opposition' => 'Saturn at opposition hint',
            'Uranus at opposition' => 'Uranus at opposition hint',
            'Neptune at opposition' => 'Neptune at opposition hint',
            'Mars-Saturn conjunction' => 'Planetary conjunction hint',
            'Jupiter-Mercury conjunction' => 'Planetary conjunction hint',
            'Venus-Saturn conjunction' => 'Planetary conjunction hint',
            'Venus-Jupiter conjunction' => 'Planetary conjunction hint',
            'Venus-Mars conjunction' => 'Planetary conjunction hint',
            'Venus-Neptune conjunction' => 'Planetary conjunction hint',
            'Saturn-Neptune conjunction' => 'Saturn-Neptune conjunction hint',
            'Jupiter-Saturn great conjunction' => 'Jupiter-Saturn great conjunction hint',
            'Earth at Perihelion' => 'Earth at Perihelion hint',
            'Earth at Aphelion' => 'Earth at Aphelion hint',
            'Zodiacal Light (evening)' => 'Zodiacal Light (evening) hint',
            'Zodiacal Light (morning)' => 'Zodiacal Light (morning) hint',
            'Seven-planet parade' => 'Seven-planet parade hint',
            'Six-planet alignment (morning)' => 'Six-planet alignment morning hint',
            'Six-planet alignment (evening)' => 'Six-planet alignment evening hint',
            'Transit of Mercury' => 'Transit of Mercury hint',
        ];

        return $hintMap[$name] ?? null;
    }

    /**
     * Check if a full moon is a supermoon (within 90% of perigee)
     */
    private function isSupermoon(\DateTime $date): bool
    {
        // Supermoon dates 2024-2030 (full moons at or near perigee)
        $supermoons = [
            '2024-09-18', '2024-10-17', '2024-11-15',
            '2025-10-07', '2025-11-05', '2025-12-04',
            '2026-05-01', '2026-05-31', '2026-06-29', '2026-11-24', '2026-12-23',
            '2027-01-22', '2027-06-18', '2027-07-18', '2027-08-17',
            '2028-06-06', '2028-07-06', '2028-08-04', '2028-09-03',
            '2029-05-26', '2029-06-25', '2029-07-24', '2029-08-23',
            '2030-05-16', '2030-06-14', '2030-07-13', '2030-08-12',
        ];

        $dateStr = $date->format('Y-m-d');
        foreach ($supermoons as $supermoon) {
            if (abs(strtotime($dateStr) - strtotime($supermoon)) < 86400) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get solstices and equinoxes for current and next year
     */
    private function getSeasonalEvents(): array
    {
        $year = (int)date('Y');
        $events = [];

        // Seasonal events are approximately the same each year, with slight variations
        // Using accurate astronomical data for 2024-2030
        $seasonalData = [
            2024 => [
                ['date' => '2024-03-20', 'event' => 'Spring Equinox', 'emoji' => '🌸'],
                ['date' => '2024-06-20', 'event' => 'Summer Solstice', 'emoji' => '☀️'],
                ['date' => '2024-09-22', 'event' => 'Autumn Equinox', 'emoji' => '🍂'],
                ['date' => '2024-12-21', 'event' => 'Winter Solstice', 'emoji' => '❄️'],
            ],
            2025 => [
                ['date' => '2025-03-20', 'event' => 'Spring Equinox', 'emoji' => '🌸'],
                ['date' => '2025-06-21', 'event' => 'Summer Solstice', 'emoji' => '☀️'],
                ['date' => '2025-09-22', 'event' => 'Autumn Equinox', 'emoji' => '🍂'],
                ['date' => '2025-12-21', 'event' => 'Winter Solstice', 'emoji' => '❄️'],
            ],
            2026 => [
                ['date' => '2026-03-20', 'event' => 'Spring Equinox', 'emoji' => '🌸'],
                ['date' => '2026-06-21', 'event' => 'Summer Solstice', 'emoji' => '☀️'],
                ['date' => '2026-09-23', 'event' => 'Autumn Equinox', 'emoji' => '🍂'],
                ['date' => '2026-12-21', 'event' => 'Winter Solstice', 'emoji' => '❄️'],
            ],
            2027 => [
                ['date' => '2027-03-20', 'event' => 'Spring Equinox', 'emoji' => '🌸'],
                ['date' => '2027-06-21', 'event' => 'Summer Solstice', 'emoji' => '☀️'],
                ['date' => '2027-09-23', 'event' => 'Autumn Equinox', 'emoji' => '🍂'],
                ['date' => '2027-12-22', 'event' => 'Winter Solstice', 'emoji' => '❄️'],
            ],
            2028 => [
                ['date' => '2028-03-20', 'event' => 'Spring Equinox', 'emoji' => '🌸'],
                ['date' => '2028-06-20', 'event' => 'Summer Solstice', 'emoji' => '☀️'],
                ['date' => '2028-09-22', 'event' => 'Autumn Equinox', 'emoji' => '🍂'],
                ['date' => '2028-12-21', 'event' => 'Winter Solstice', 'emoji' => '❄️'],
            ],
            2029 => [
                ['date' => '2029-03-20', 'event' => 'Spring Equinox', 'emoji' => '🌸'],
                ['date' => '2029-06-21', 'event' => 'Summer Solstice', 'emoji' => '☀️'],
                ['date' => '2029-09-22', 'event' => 'Autumn Equinox', 'emoji' => '🍂'],
                ['date' => '2029-12-21', 'event' => 'Winter Solstice', 'emoji' => '❄️'],
            ],
            2030 => [
                ['date' => '2030-03-20', 'event' => 'Spring Equinox', 'emoji' => '🌸'],
                ['date' => '2030-06-21', 'event' => 'Summer Solstice', 'emoji' => '☀️'],
                ['date' => '2030-09-22', 'event' => 'Autumn Equinox', 'emoji' => '🍂'],
                ['date' => '2030-12-21', 'event' => 'Winter Solstice', 'emoji' => '❄️'],
            ],
            2031 => [
                ['date' => '2031-03-20', 'event' => 'Spring Equinox', 'emoji' => '🌸'],
                ['date' => '2031-06-21', 'event' => 'Summer Solstice', 'emoji' => '☀️'],
                ['date' => '2031-09-22', 'event' => 'Autumn Equinox', 'emoji' => '🍂'],
                ['date' => '2031-12-21', 'event' => 'Winter Solstice', 'emoji' => '❄️'],
            ],
            2032 => [
                ['date' => '2032-03-20', 'event' => 'Spring Equinox', 'emoji' => '🌸'],
                ['date' => '2032-06-20', 'event' => 'Summer Solstice', 'emoji' => '☀️'],
                ['date' => '2032-09-22', 'event' => 'Autumn Equinox', 'emoji' => '🍂'],
                ['date' => '2032-12-21', 'event' => 'Winter Solstice', 'emoji' => '❄️'],
            ],
            2033 => [
                ['date' => '2033-03-20', 'event' => 'Spring Equinox', 'emoji' => '🌸'],
                ['date' => '2033-06-21', 'event' => 'Summer Solstice', 'emoji' => '☀️'],
                ['date' => '2033-09-22', 'event' => 'Autumn Equinox', 'emoji' => '🍂'],
                ['date' => '2033-12-21', 'event' => 'Winter Solstice', 'emoji' => '❄️'],
            ],
        ];

        // Adjust names for Southern hemisphere
        $isSouthernHemisphere = $this->latitude < 0;

        // Include current year through 5 years ahead
        $endYear = min($year + 4, 2033);
        foreach (range($year, $endYear) as $y) {
            if (!isset($seasonalData[$y])) continue;

            foreach ($seasonalData[$y] as $event) {
                $eventName = $event['event'];

                // Swap season names for Southern hemisphere
                if ($isSouthernHemisphere) {
                    $swaps = [
                        'Spring Equinox' => 'Autumn Equinox',
                        'Autumn Equinox' => 'Spring Equinox',
                        'Summer Solstice' => 'Winter Solstice',
                        'Winter Solstice' => 'Summer Solstice',
                    ];
                    $eventName = $swaps[$eventName] ?? $eventName;

                    $emojiSwaps = [
                        '🌸' => '🍂',
                        '🍂' => '🌸',
                        '☀️' => '❄️',
                        '❄️' => '☀️',
                    ];
                    $event['emoji'] = $emojiSwaps[$event['emoji']] ?? $event['emoji'];
                }

                $events[] = [
                    'date' => $event['date'],
                    'formatted_date' => date('j M', strtotime($event['date'])),
                    'type' => 'seasonal',
                    'event' => $eventName,
                    'emoji' => $event['emoji'],
                ];
            }
        }

        return $events;
    }

    /**
     * Get solar and lunar eclipses 2024-2033 (at least 5 years from current)
     */
    private function getEclipses(): array
    {
        return [
            // 2024
            ['date' => '2024-03-25', 'event' => 'Penumbral Lunar Eclipse', 'emoji' => '🌘', 'type' => 'eclipse', 'formatted_date' => '25 Mar', 'visibility' => 'Americas'],
            ['date' => '2024-04-08', 'event' => 'Total Solar Eclipse', 'emoji' => '🌑', 'type' => 'eclipse', 'formatted_date' => '8 Apr', 'visibility' => 'North America'],
            ['date' => '2024-09-18', 'event' => 'Partial Lunar Eclipse', 'emoji' => '🌘', 'type' => 'eclipse', 'formatted_date' => '18 Sep', 'visibility' => 'Americas, Europe, Africa'],
            ['date' => '2024-10-02', 'event' => 'Annular Solar Eclipse', 'emoji' => '🌒', 'type' => 'eclipse', 'formatted_date' => '2 Oct', 'visibility' => 'South America'],
            // 2025
            ['date' => '2025-03-14', 'event' => 'Total Lunar Eclipse', 'emoji' => '🌑', 'type' => 'eclipse', 'formatted_date' => '14 Mar', 'visibility' => 'Americas, Europe, Africa'],
            ['date' => '2025-03-29', 'event' => 'Partial Solar Eclipse', 'emoji' => '🌒', 'type' => 'eclipse', 'formatted_date' => '29 Mar', 'visibility' => 'Europe, North Africa'],
            ['date' => '2025-09-07', 'event' => 'Total Lunar Eclipse', 'emoji' => '🌑', 'type' => 'eclipse', 'formatted_date' => '7 Sep', 'visibility' => 'Europe, Africa, Asia'],
            ['date' => '2025-09-21', 'event' => 'Partial Solar Eclipse', 'emoji' => '🌒', 'type' => 'eclipse', 'formatted_date' => '21 Sep', 'visibility' => 'Australia, Antarctica'],
            // 2026
            ['date' => '2026-03-03', 'event' => 'Total Lunar Eclipse', 'emoji' => '🌑', 'type' => 'eclipse', 'formatted_date' => '3 Mar', 'visibility' => 'Asia, Australia, Pacific'],
            ['date' => '2026-08-12', 'event' => 'Total Solar Eclipse', 'emoji' => '🌑', 'type' => 'eclipse', 'formatted_date' => '12 Aug', 'visibility' => 'Europe, North Africa'],
            ['date' => '2026-08-28', 'event' => 'Partial Lunar Eclipse', 'emoji' => '🌘', 'type' => 'eclipse', 'formatted_date' => '28 Aug', 'visibility' => 'Americas, Europe, Africa'],
            // 2027
            ['date' => '2027-02-06', 'event' => 'Annular Solar Eclipse', 'emoji' => '🌒', 'type' => 'eclipse', 'formatted_date' => '6 Feb', 'visibility' => 'South America, Antarctica'],
            ['date' => '2027-02-20', 'event' => 'Penumbral Lunar Eclipse', 'emoji' => '🌘', 'type' => 'eclipse', 'formatted_date' => '20 Feb', 'visibility' => 'Americas'],
            ['date' => '2027-07-18', 'event' => 'Penumbral Lunar Eclipse', 'emoji' => '🌘', 'type' => 'eclipse', 'formatted_date' => '18 Jul', 'visibility' => 'Americas, Europe, Africa'],
            ['date' => '2027-08-02', 'event' => 'Total Solar Eclipse', 'emoji' => '🌑', 'type' => 'eclipse', 'formatted_date' => '2 Aug', 'visibility' => 'Europe, North Africa, Middle East'],
            // 2028
            ['date' => '2028-01-12', 'event' => 'Partial Lunar Eclipse', 'emoji' => '🌘', 'type' => 'eclipse', 'formatted_date' => '12 Jan', 'visibility' => 'Americas, Europe, Africa'],
            ['date' => '2028-01-26', 'event' => 'Annular Solar Eclipse', 'emoji' => '🌒', 'type' => 'eclipse', 'formatted_date' => '26 Jan', 'visibility' => 'South America'],
            ['date' => '2028-07-06', 'event' => 'Partial Lunar Eclipse', 'emoji' => '🌘', 'type' => 'eclipse', 'formatted_date' => '6 Jul', 'visibility' => 'Americas, Europe, Africa'],
            ['date' => '2028-07-22', 'event' => 'Total Solar Eclipse', 'emoji' => '🌑', 'type' => 'eclipse', 'formatted_date' => '22 Jul', 'visibility' => 'Australia, New Zealand'],
            // 2029
            ['date' => '2029-01-14', 'event' => 'Total Lunar Eclipse', 'emoji' => '🌑', 'type' => 'eclipse', 'formatted_date' => '14 Jan', 'visibility' => 'Americas, Europe, Africa'],
            ['date' => '2029-06-12', 'event' => 'Partial Solar Eclipse', 'emoji' => '🌒', 'type' => 'eclipse', 'formatted_date' => '12 Jun', 'visibility' => 'Arctic, Scandinavia'],
            ['date' => '2029-07-11', 'event' => 'Partial Lunar Eclipse', 'emoji' => '🌘', 'type' => 'eclipse', 'formatted_date' => '11 Jul', 'visibility' => 'Americas'],
            ['date' => '2029-12-05', 'event' => 'Partial Solar Eclipse', 'emoji' => '🌒', 'type' => 'eclipse', 'formatted_date' => '5 Dec', 'visibility' => 'Antarctica, South America'],
            // 2030
            ['date' => '2030-06-01', 'event' => 'Annular Solar Eclipse', 'emoji' => '🌒', 'type' => 'eclipse', 'formatted_date' => '1 Jun', 'visibility' => 'Europe, North Africa, Asia'],
            ['date' => '2030-06-15', 'event' => 'Partial Lunar Eclipse', 'emoji' => '🌘', 'type' => 'eclipse', 'formatted_date' => '15 Jun', 'visibility' => 'Europe, Africa, Asia'],
            ['date' => '2030-11-25', 'event' => 'Total Solar Eclipse', 'emoji' => '🌑', 'type' => 'eclipse', 'formatted_date' => '25 Nov', 'visibility' => 'Southern Africa, Australia'],
            ['date' => '2030-12-09', 'event' => 'Penumbral Lunar Eclipse', 'emoji' => '🌘', 'type' => 'eclipse', 'formatted_date' => '9 Dec', 'visibility' => 'Asia, Australia, Pacific'],
            // 2031
            ['date' => '2031-05-06', 'event' => 'Penumbral Lunar Eclipse', 'emoji' => '🌘', 'type' => 'eclipse', 'formatted_date' => '6 May', 'visibility' => 'Americas, Europe, Africa'],
            ['date' => '2031-05-21', 'event' => 'Annular Solar Eclipse', 'emoji' => '🌒', 'type' => 'eclipse', 'formatted_date' => '21 May', 'visibility' => 'Africa, Asia, Australia'],
            ['date' => '2031-06-05', 'event' => 'Penumbral Lunar Eclipse', 'emoji' => '🌘', 'type' => 'eclipse', 'formatted_date' => '5 Jun', 'visibility' => 'Asia, Australia, Americas'],
            ['date' => '2031-10-29', 'event' => 'Penumbral Lunar Eclipse', 'emoji' => '🌘', 'type' => 'eclipse', 'formatted_date' => '29 Oct', 'visibility' => 'Americas, Europe, Africa'],
            ['date' => '2031-11-14', 'event' => 'Hybrid Solar Eclipse', 'emoji' => '🌑', 'type' => 'eclipse', 'formatted_date' => '14 Nov', 'visibility' => 'Pacific, Americas'],
            // 2032
            ['date' => '2032-04-25', 'event' => 'Total Lunar Eclipse', 'emoji' => '🌑', 'type' => 'eclipse', 'formatted_date' => '25 Apr', 'visibility' => 'Americas, Europe, Africa'],
            ['date' => '2032-05-09', 'event' => 'Annular Solar Eclipse', 'emoji' => '🌒', 'type' => 'eclipse', 'formatted_date' => '9 May', 'visibility' => 'South America, South Africa'],
            ['date' => '2032-10-18', 'event' => 'Total Lunar Eclipse', 'emoji' => '🌑', 'type' => 'eclipse', 'formatted_date' => '18 Oct', 'visibility' => 'Asia, Australia, Americas'],
            ['date' => '2032-11-03', 'event' => 'Partial Solar Eclipse', 'emoji' => '🌒', 'type' => 'eclipse', 'formatted_date' => '3 Nov', 'visibility' => 'Asia'],
            // 2033
            ['date' => '2033-03-30', 'event' => 'Total Solar Eclipse', 'emoji' => '🌑', 'type' => 'eclipse', 'formatted_date' => '30 Mar', 'visibility' => 'North America, Russia, Alaska'],
            ['date' => '2033-04-14', 'event' => 'Total Lunar Eclipse', 'emoji' => '🌑', 'type' => 'eclipse', 'formatted_date' => '14 Apr', 'visibility' => 'Americas, Europe, Africa'],
            ['date' => '2033-09-23', 'event' => 'Partial Solar Eclipse', 'emoji' => '🌒', 'type' => 'eclipse', 'formatted_date' => '23 Sep', 'visibility' => 'South America, Antarctica'],
            ['date' => '2033-10-08', 'event' => 'Total Lunar Eclipse', 'emoji' => '🌑', 'type' => 'eclipse', 'formatted_date' => '8 Oct', 'visibility' => 'Americas, Europe, Africa'],
        ];
    }

    /**
     * Check if an eclipse is visible from station location
     */
    private function isEclipseVisible(array $eclipse): bool
    {
        $visibility = strtolower($eclipse['visibility'] ?? '');

        // Check if Europe is mentioned for European stations
        if ($this->latitude > 35 && $this->latitude < 72 && $this->longitude > -25 && $this->longitude < 40) {
            return str_contains($visibility, 'europe') || str_contains($visibility, 'scandinavia');
        }

        // Check Americas
        if ($this->longitude > -170 && $this->longitude < -30) {
            return str_contains($visibility, 'america');
        }

        // Check Asia
        if ($this->longitude > 60 && $this->longitude < 180 && $this->latitude > -10) {
            return str_contains($visibility, 'asia');
        }

        // Check Australia
        if ($this->longitude > 110 && $this->longitude < 180 && $this->latitude < -10) {
            return str_contains($visibility, 'australia') || str_contains($visibility, 'pacific');
        }

        // Check Africa
        if ($this->longitude > -20 && $this->longitude < 55 && $this->latitude > -35 && $this->latitude < 35) {
            return str_contains($visibility, 'africa');
        }

        return false;
    }

    /**
     * Get meteor shower peak dates (not ranges)
     */
    private function getMeteorShowerPeaks(): array
    {
        $year = (int)date('Y');
        $events = [];

        $showers = [
            ['name' => 'Quadrantids', 'month' => 1, 'day' => 3, 'rate' => 120, 'emoji' => '☄️'],
            ['name' => 'Lyrids', 'month' => 4, 'day' => 22, 'rate' => 18, 'emoji' => '☄️'],
            ['name' => 'Eta Aquariids', 'month' => 5, 'day' => 6, 'rate' => 50, 'emoji' => '☄️'],
            ['name' => 'Delta Aquariids', 'month' => 7, 'day' => 30, 'rate' => 20, 'emoji' => '☄️'],
            ['name' => 'Perseids', 'month' => 8, 'day' => 12, 'rate' => 100, 'emoji' => '🌠'],
            ['name' => 'Draconids', 'month' => 10, 'day' => 8, 'rate' => 10, 'emoji' => '☄️'],
            ['name' => 'Orionids', 'month' => 10, 'day' => 21, 'rate' => 20, 'emoji' => '☄️'],
            ['name' => 'Taurids', 'month' => 11, 'day' => 5, 'rate' => 5, 'emoji' => '☄️'],
            ['name' => 'Leonids', 'month' => 11, 'day' => 17, 'rate' => 15, 'emoji' => '☄️'],
            ['name' => 'Geminids', 'month' => 12, 'day' => 14, 'rate' => 150, 'emoji' => '🌠'],
            ['name' => 'Ursids', 'month' => 12, 'day' => 22, 'rate' => 10, 'emoji' => '☄️'],
        ];

        // 5 years of meteor peaks
        foreach (range($year, $year + 4) as $y) {
            foreach ($showers as $shower) {
                $date = sprintf('%d-%02d-%02d', $y, $shower['month'], $shower['day']);
                $events[] = [
                    'date' => $date,
                    'formatted_date' => date('j M', strtotime($date)),
                    'type' => 'meteor',
                    'event' => $shower['name'] . ' peak',
                    'emoji' => $shower['emoji'],
                    'rate' => $shower['rate'],
                    'peak' => true,
                ];
            }
        }

        return $events;
    }

    /**
     * Get planetary parade / alignment events (6- or 7-planet alignments in the sky).
     * These are rare windows when many planets are visible in the same part of the sky.
     */
    private function getPlanetaryParadeEvents(): array
    {
        return [
            ['date' => '2025-02-28', 'event' => 'Seven-planet parade', 'emoji' => '🌟', 'type' => 'special', 'formatted_date' => '28 Feb', 'hint' => 'Seven-planet parade hint'],
            ['date' => '2025-08-10', 'event' => 'Six-planet alignment (morning)', 'emoji' => '🌟', 'type' => 'special', 'formatted_date' => '10 Aug', 'hint' => 'Six-planet alignment morning hint'],
            ['date' => '2026-02-28', 'event' => 'Six-planet alignment (evening)', 'emoji' => '🌟', 'type' => 'special', 'formatted_date' => '28 Feb', 'hint' => 'Six-planet alignment evening hint'],
        ];
    }

    /**
     * Get planetary events (conjunctions, oppositions, elongations)
     */
    private function getPlanetaryEvents(): array
    {
        // Major planetary events 2024-2030
        return [
            // 2024
            ['date' => '2024-01-12', 'event' => 'Mercury at greatest elongation', 'emoji' => '☿️', 'type' => 'planet', 'formatted_date' => '12 Jan'],
            ['date' => '2024-04-10', 'event' => 'Mars-Saturn conjunction', 'emoji' => '🪐', 'type' => 'planet', 'formatted_date' => '10 Apr'],
            ['date' => '2024-06-03', 'event' => 'Jupiter-Mercury conjunction', 'emoji' => '🪐', 'type' => 'planet', 'formatted_date' => '3 Jun'],
            ['date' => '2024-09-08', 'event' => 'Saturn at opposition', 'emoji' => '🪐', 'type' => 'planet', 'formatted_date' => '8 Sep'],
            ['date' => '2024-11-17', 'event' => 'Uranus at opposition', 'emoji' => '🔵', 'type' => 'planet', 'formatted_date' => '17 Nov'],
            ['date' => '2024-12-07', 'event' => 'Jupiter at opposition', 'emoji' => '🟠', 'type' => 'planet', 'formatted_date' => '7 Dec'],
            // 2025
            ['date' => '2025-01-16', 'event' => 'Mars at opposition', 'emoji' => '🔴', 'type' => 'planet', 'formatted_date' => '16 Jan'],
            ['date' => '2025-01-18', 'event' => 'Venus-Saturn conjunction', 'emoji' => '🪐', 'type' => 'planet', 'formatted_date' => '18 Jan'],
            ['date' => '2025-03-29', 'event' => 'Venus at greatest elongation', 'emoji' => '✨', 'type' => 'planet', 'formatted_date' => '29 Mar'],
            ['date' => '2025-07-04', 'event' => 'Mercury at greatest elongation', 'emoji' => '☿️', 'type' => 'planet', 'formatted_date' => '4 Jul'],
            ['date' => '2025-08-12', 'event' => 'Venus-Jupiter conjunction', 'emoji' => '🌟', 'type' => 'planet', 'formatted_date' => '12 Aug'],
            ['date' => '2025-09-21', 'event' => 'Saturn at opposition', 'emoji' => '🪐', 'type' => 'planet', 'formatted_date' => '21 Sep'],
            ['date' => '2025-09-23', 'event' => 'Neptune at opposition', 'emoji' => '🔵', 'type' => 'planet', 'formatted_date' => '23 Sep'],
            ['date' => '2025-11-21', 'event' => 'Uranus at opposition', 'emoji' => '🔵', 'type' => 'planet', 'formatted_date' => '21 Nov'],
            ['date' => '2025-12-07', 'event' => 'Mercury at greatest elongation', 'emoji' => '☿️', 'type' => 'planet', 'formatted_date' => '7 Dec'],
            // 2026
            ['date' => '2026-01-10', 'event' => 'Jupiter at opposition', 'emoji' => '🟠', 'type' => 'planet', 'formatted_date' => '10 Jan'],
            ['date' => '2026-02-16', 'event' => 'Saturn-Neptune conjunction', 'emoji' => '🪐', 'type' => 'planet', 'formatted_date' => '16 Feb', 'hint' => 'Saturn-Neptune conjunction hint'],
            ['date' => '2026-02-19', 'event' => 'Mercury at greatest elongation', 'emoji' => '☿️', 'type' => 'planet', 'formatted_date' => '19 Feb'],
            ['date' => '2026-03-07', 'event' => 'Venus-Neptune conjunction', 'emoji' => '✨', 'type' => 'planet', 'formatted_date' => '7 Mar'],
            ['date' => '2026-03-08', 'event' => 'Venus-Saturn conjunction', 'emoji' => '🪐', 'type' => 'planet', 'formatted_date' => '8 Mar'],
            ['date' => '2026-08-15', 'event' => 'Venus at greatest elongation', 'emoji' => '✨', 'type' => 'planet', 'formatted_date' => '15 Aug'],
            ['date' => '2026-09-26', 'event' => 'Neptune at opposition', 'emoji' => '🔵', 'type' => 'planet', 'formatted_date' => '26 Sep'],
            ['date' => '2026-10-04', 'event' => 'Saturn at opposition', 'emoji' => '🪐', 'type' => 'planet', 'formatted_date' => '4 Oct'],
            ['date' => '2026-10-11', 'event' => 'Mercury at greatest elongation', 'emoji' => '☿️', 'type' => 'planet', 'formatted_date' => '11 Oct'],
            ['date' => '2026-11-09', 'event' => 'Mars at opposition', 'emoji' => '🔴', 'type' => 'planet', 'formatted_date' => '9 Nov'],
            ['date' => '2026-11-24', 'event' => 'Uranus at opposition', 'emoji' => '🔵', 'type' => 'planet', 'formatted_date' => '24 Nov'],
            // 2027
            ['date' => '2027-02-11', 'event' => 'Jupiter at opposition', 'emoji' => '🟠', 'type' => 'planet', 'formatted_date' => '11 Feb'],
            ['date' => '2027-02-19', 'event' => 'Venus-Mars conjunction', 'emoji' => '🌟', 'type' => 'planet', 'formatted_date' => '19 Feb'],
            ['date' => '2027-04-01', 'event' => 'Mercury at greatest elongation', 'emoji' => '☿️', 'type' => 'planet', 'formatted_date' => '1 Apr'],
            ['date' => '2027-09-28', 'event' => 'Neptune at opposition', 'emoji' => '🔵', 'type' => 'planet', 'formatted_date' => '28 Sep'],
            ['date' => '2027-10-18', 'event' => 'Saturn at opposition', 'emoji' => '🪐', 'type' => 'planet', 'formatted_date' => '18 Oct'],
            ['date' => '2027-11-28', 'event' => 'Uranus at opposition', 'emoji' => '🔵', 'type' => 'planet', 'formatted_date' => '28 Nov'],
            // 2028
            ['date' => '2028-01-12', 'event' => 'Mars at opposition', 'emoji' => '🔴', 'type' => 'planet', 'formatted_date' => '12 Jan'],
            ['date' => '2028-03-12', 'event' => 'Jupiter at opposition', 'emoji' => '🟠', 'type' => 'planet', 'formatted_date' => '12 Mar'],
            ['date' => '2028-09-16', 'event' => 'Neptune at opposition', 'emoji' => '🔵', 'type' => 'planet', 'formatted_date' => '16 Sep'],
            ['date' => '2028-10-30', 'event' => 'Saturn at opposition', 'emoji' => '🪐', 'type' => 'planet', 'formatted_date' => '30 Oct'],
            ['date' => '2028-12-01', 'event' => 'Uranus at opposition', 'emoji' => '🔵', 'type' => 'planet', 'formatted_date' => '1 Dec'],
            // 2029
            ['date' => '2029-02-17', 'event' => 'Mars at opposition', 'emoji' => '🔴', 'type' => 'planet', 'formatted_date' => '17 Feb'],
            ['date' => '2029-04-12', 'event' => 'Jupiter at opposition', 'emoji' => '🟠', 'type' => 'planet', 'formatted_date' => '12 Apr'],
            ['date' => '2029-09-19', 'event' => 'Neptune at opposition', 'emoji' => '🔵', 'type' => 'planet', 'formatted_date' => '19 Sep'],
            ['date' => '2029-11-13', 'event' => 'Saturn at opposition', 'emoji' => '🪐', 'type' => 'planet', 'formatted_date' => '13 Nov'],
            ['date' => '2029-12-05', 'event' => 'Uranus at opposition', 'emoji' => '🔵', 'type' => 'planet', 'formatted_date' => '5 Dec'],
            // 2030
            ['date' => '2030-03-25', 'event' => 'Mars at opposition', 'emoji' => '🔴', 'type' => 'planet', 'formatted_date' => '25 Mar'],
            ['date' => '2030-05-13', 'event' => 'Jupiter at opposition', 'emoji' => '🟠', 'type' => 'planet', 'formatted_date' => '13 May'],
            ['date' => '2030-06-21', 'event' => 'Jupiter-Saturn great conjunction', 'emoji' => '🌟', 'type' => 'planet', 'formatted_date' => '21 Jun'],
            ['date' => '2030-09-21', 'event' => 'Neptune at opposition', 'emoji' => '🔵', 'type' => 'planet', 'formatted_date' => '21 Sep'],
            ['date' => '2030-11-27', 'event' => 'Saturn at opposition', 'emoji' => '🪐', 'type' => 'planet', 'formatted_date' => '27 Nov'],
            ['date' => '2030-12-08', 'event' => 'Uranus at opposition', 'emoji' => '🔵', 'type' => 'planet', 'formatted_date' => '8 Dec'],
            // 2031
            ['date' => '2031-01-29', 'event' => 'Mars at opposition', 'emoji' => '🔴', 'type' => 'planet', 'formatted_date' => '29 Jan'],
            ['date' => '2031-04-04', 'event' => 'Jupiter at opposition', 'emoji' => '🟠', 'type' => 'planet', 'formatted_date' => '4 Apr'],
            ['date' => '2031-09-25', 'event' => 'Neptune at opposition', 'emoji' => '🔵', 'type' => 'planet', 'formatted_date' => '25 Sep'],
            ['date' => '2031-11-15', 'event' => 'Saturn at opposition', 'emoji' => '🪐', 'type' => 'planet', 'formatted_date' => '15 Nov'],
            ['date' => '2031-12-27', 'event' => 'Uranus at opposition', 'emoji' => '🔵', 'type' => 'planet', 'formatted_date' => '27 Dec'],
            // 2032
            ['date' => '2032-02-19', 'event' => 'Mars at opposition', 'emoji' => '🔴', 'type' => 'planet', 'formatted_date' => '19 Feb'],
            ['date' => '2032-04-17', 'event' => 'Jupiter at opposition', 'emoji' => '🟠', 'type' => 'planet', 'formatted_date' => '17 Apr'],
            ['date' => '2032-09-27', 'event' => 'Neptune at opposition', 'emoji' => '🔵', 'type' => 'planet', 'formatted_date' => '27 Sep'],
            ['date' => '2032-11-02', 'event' => 'Saturn at opposition', 'emoji' => '🪐', 'type' => 'planet', 'formatted_date' => '2 Nov'],
            ['date' => '2032-12-17', 'event' => 'Uranus at opposition', 'emoji' => '🔵', 'type' => 'planet', 'formatted_date' => '17 Dec'],
            // 2033
            ['date' => '2033-03-03', 'event' => 'Mars at opposition', 'emoji' => '🔴', 'type' => 'planet', 'formatted_date' => '3 Mar'],
            ['date' => '2033-04-28', 'event' => 'Jupiter at opposition', 'emoji' => '🟠', 'type' => 'planet', 'formatted_date' => '28 Apr'],
            ['date' => '2033-09-30', 'event' => 'Neptune at opposition', 'emoji' => '🔵', 'type' => 'planet', 'formatted_date' => '30 Sep'],
            ['date' => '2033-10-18', 'event' => 'Saturn at opposition', 'emoji' => '🪐', 'type' => 'planet', 'formatted_date' => '18 Oct'],
            ['date' => '2033-12-07', 'event' => 'Uranus at opposition', 'emoji' => '🔵', 'type' => 'planet', 'formatted_date' => '7 Dec'],
        ];
    }

    /**
     * Get Earth's perihelion and aphelion dates
     */
    private function getEarthOrbitalEvents(): array
    {
        // Earth orbital events 2024-2033
        return [
            ['date' => '2024-01-03', 'event' => 'Earth at Perihelion', 'emoji' => '🌍', 'type' => 'earth', 'formatted_date' => '3 Jan'],
            ['date' => '2024-07-05', 'event' => 'Earth at Aphelion', 'emoji' => '🌏', 'type' => 'earth', 'formatted_date' => '5 Jul'],
            ['date' => '2025-01-04', 'event' => 'Earth at Perihelion', 'emoji' => '🌍', 'type' => 'earth', 'formatted_date' => '4 Jan'],
            ['date' => '2025-07-03', 'event' => 'Earth at Aphelion', 'emoji' => '🌏', 'type' => 'earth', 'formatted_date' => '3 Jul'],
            ['date' => '2026-01-03', 'event' => 'Earth at Perihelion', 'emoji' => '🌍', 'type' => 'earth', 'formatted_date' => '3 Jan'],
            ['date' => '2026-07-06', 'event' => 'Earth at Aphelion', 'emoji' => '🌏', 'type' => 'earth', 'formatted_date' => '6 Jul'],
            ['date' => '2027-01-03', 'event' => 'Earth at Perihelion', 'emoji' => '🌍', 'type' => 'earth', 'formatted_date' => '3 Jan'],
            ['date' => '2027-07-05', 'event' => 'Earth at Aphelion', 'emoji' => '🌏', 'type' => 'earth', 'formatted_date' => '5 Jul'],
            ['date' => '2028-01-05', 'event' => 'Earth at Perihelion', 'emoji' => '🌍', 'type' => 'earth', 'formatted_date' => '5 Jan'],
            ['date' => '2028-07-03', 'event' => 'Earth at Aphelion', 'emoji' => '🌏', 'type' => 'earth', 'formatted_date' => '3 Jul'],
            ['date' => '2029-01-02', 'event' => 'Earth at Perihelion', 'emoji' => '🌍', 'type' => 'earth', 'formatted_date' => '2 Jan'],
            ['date' => '2029-07-06', 'event' => 'Earth at Aphelion', 'emoji' => '🌏', 'type' => 'earth', 'formatted_date' => '6 Jul'],
            ['date' => '2030-01-03', 'event' => 'Earth at Perihelion', 'emoji' => '🌍', 'type' => 'earth', 'formatted_date' => '3 Jan'],
            ['date' => '2030-07-04', 'event' => 'Earth at Aphelion', 'emoji' => '🌏', 'type' => 'earth', 'formatted_date' => '4 Jul'],
            ['date' => '2031-01-03', 'event' => 'Earth at Perihelion', 'emoji' => '🌍', 'type' => 'earth', 'formatted_date' => '3 Jan'],
            ['date' => '2031-07-05', 'event' => 'Earth at Aphelion', 'emoji' => '🌏', 'type' => 'earth', 'formatted_date' => '5 Jul'],
            ['date' => '2032-01-04', 'event' => 'Earth at Perihelion', 'emoji' => '🌍', 'type' => 'earth', 'formatted_date' => '4 Jan'],
            ['date' => '2032-07-03', 'event' => 'Earth at Aphelion', 'emoji' => '🌏', 'type' => 'earth', 'formatted_date' => '3 Jul'],
            ['date' => '2033-01-04', 'event' => 'Earth at Perihelion', 'emoji' => '🌍', 'type' => 'earth', 'formatted_date' => '4 Jan'],
            ['date' => '2033-07-06', 'event' => 'Earth at Aphelion', 'emoji' => '🌏', 'type' => 'earth', 'formatted_date' => '6 Jul'],
        ];
    }

    /**
     * Get Venus greatest brilliancy dates
     */
    private function getVenusBrilliancyEvents(): array
    {
        // Venus at greatest brilliancy 2024-2033
        return [
            ['date' => '2024-12-04', 'event' => 'Venus at greatest brilliancy', 'emoji' => '💫', 'type' => 'planet', 'formatted_date' => '4 Dec'],
            ['date' => '2025-02-16', 'event' => 'Venus at greatest brilliancy', 'emoji' => '💫', 'type' => 'planet', 'formatted_date' => '16 Feb'],
            ['date' => '2026-07-10', 'event' => 'Venus at greatest brilliancy', 'emoji' => '💫', 'type' => 'planet', 'formatted_date' => '10 Jul'],
            ['date' => '2026-09-19', 'event' => 'Venus at greatest brilliancy', 'emoji' => '💫', 'type' => 'planet', 'formatted_date' => '19 Sep'],
            ['date' => '2028-02-13', 'event' => 'Venus at greatest brilliancy', 'emoji' => '💫', 'type' => 'planet', 'formatted_date' => '13 Feb'],
            ['date' => '2028-04-27', 'event' => 'Venus at greatest brilliancy', 'emoji' => '💫', 'type' => 'planet', 'formatted_date' => '27 Apr'],
            ['date' => '2029-09-17', 'event' => 'Venus at greatest brilliancy', 'emoji' => '💫', 'type' => 'planet', 'formatted_date' => '17 Sep'],
            ['date' => '2029-11-29', 'event' => 'Venus at greatest brilliancy', 'emoji' => '💫', 'type' => 'planet', 'formatted_date' => '29 Nov'],
            ['date' => '2030-03-30', 'event' => 'Venus at greatest brilliancy', 'emoji' => '💫', 'type' => 'planet', 'formatted_date' => '30 Mar'],
            ['date' => '2031-08-22', 'event' => 'Venus at greatest brilliancy', 'emoji' => '💫', 'type' => 'planet', 'formatted_date' => '22 Aug'],
            ['date' => '2032-01-08', 'event' => 'Venus at greatest brilliancy', 'emoji' => '💫', 'type' => 'planet', 'formatted_date' => '8 Jan'],
            ['date' => '2033-06-14', 'event' => 'Venus at greatest brilliancy', 'emoji' => '💫', 'type' => 'planet', 'formatted_date' => '14 Jun'],
        ];
    }

    /**
     * Get known comet appearances (perihelion or peak visibility) 2024-2033
     */
    private function getCometEvents(): array
    {
        return [
            // 2024
            ['date' => '2024-10-12', 'event' => 'Comet C/2023 A3 (Tsuchinshan-ATLAS)', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '12 Oct'],
            // 2025
            ['date' => '2025-01-13', 'event' => 'Comet 45P/Honda-Mrkos-Pajdušáková', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '13 Jan'],
            ['date' => '2025-11-08', 'event' => 'Comet C/2025 A6 (Lemmon)', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '8 Nov'],
            ['date' => '2025-11-15', 'event' => 'Comet 210P/Christensen', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '15 Nov'],
            // 2026
            ['date' => '2026-01-05', 'event' => 'Comet 24P/Schaumasse', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '5 Jan'],
            ['date' => '2026-01-20', 'event' => 'Comet C/2024 E1 (Wierzchos)', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '20 Jan'],
            ['date' => '2026-06-25', 'event' => 'Comet 14P/Wolf', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '25 Jun'],
            ['date' => '2026-08-04', 'event' => 'Comet 10P/Tempel 2', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '4 Aug'],
            ['date' => '2026-11-28', 'event' => 'Comet 29P/Schwassmann-Wachmann', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '28 Nov'],
            // 2027
            ['date' => '2027-02-04', 'event' => 'Comet 2P/Encke', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '4 Feb'],
            ['date' => '2027-08-04', 'event' => 'Comet 9P/Tempel 1', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '4 Aug'],
            // 2028
            ['date' => '2028-03-01', 'event' => 'Comet 6P/d\'Arrest', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '1 Mar'],
            ['date' => '2028-04-09', 'event' => 'Comet 67P/Churyumov-Gerasimenko', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '9 Apr'],
            // 2029
            ['date' => '2029-05-26', 'event' => 'Comet 4P/Faye', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '26 May'],
            ['date' => '2029-06-11', 'event' => 'Comet 73P/Schwassmann-Wachmann', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '11 Jun'],
            // 2030
            ['date' => '2030-05-22', 'event' => 'Comet 2P/Encke', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '22 May'],
            ['date' => '2030-10-11', 'event' => 'Comet 22P/Kopff', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '11 Oct'],
            // 2031
            ['date' => '2031-01-17', 'event' => 'Comet 8P/Tuttle', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '17 Jan'],
            ['date' => '2031-09-02', 'event' => 'Comet 2P/Encke', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '2 Sep'],
            // 2032
            ['date' => '2032-06-15', 'event' => 'Comet 21P/Giacobini-Zinner', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '15 Jun'],
            ['date' => '2032-12-26', 'event' => 'Comet 2P/Encke', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '26 Dec'],
            // 2033
            ['date' => '2033-04-20', 'event' => 'Comet 2P/Encke', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '20 Apr'],
            ['date' => '2033-07-28', 'event' => 'Comet 9P/Tempel 1', 'emoji' => '☄️', 'type' => 'comet', 'formatted_date' => '28 Jul'],
        ];
    }

    /**
     * Get Mercury and Venus transits
     */
    private function getTransitEvents(): array
    {
        return [
            // Mercury transits (visible from parts of Earth)
            ['date' => '2032-11-13', 'event' => 'Transit of Mercury', 'emoji' => '☿️', 'type' => 'transit', 'formatted_date' => '13 Nov', 'visibility' => 'Europe, Africa, Americas'],
            // Venus transits are rare (next in 2117)
        ];
    }

    /**
     * Check for Blue Moon (second full moon in a month)
     */
    private function checkBlueMoon(\DateTime $date, float $phase): ?array
    {
        if (abs($phase - 0.5) >= 0.02) {
            return null; // Not a full moon
        }

        // Check if there's another full moon earlier in the same month
        $monthStart = $date->copy()->startOfMonth();
        $currentDay = (int)$date->format('j');

        // Only check if we're past day 20 (blue moon can only happen late in month)
        if ($currentDay < 20) {
            return null;
        }

        // Look for a full moon in the first half of the month
        for ($i = 1; $i <= 15; $i++) {
            $checkDate = $monthStart->copy()->addDays($i - 1);
            $moonData = $this->getMoonData($checkDate);
            if (abs($moonData['phase'] - 0.5) < 0.02) {
                // Found an earlier full moon this month - this is a Blue Moon!
                return [
                    'date' => $date->format('Y-m-d'),
                    'formatted_date' => $date->format('j M'),
                    'type' => 'moon',
                    'event' => 'Blue Moon',
                    'emoji' => '🔵',
                ];
            }
        }

        return null;
    }

    /**
     * Get zodiacal light viewing periods (best in spring/autumn)
     */
    private function getZodiacalLightEvents(): array
    {
        $year = (int)date('Y');
        $events = [];

        // Zodiacal light is best seen:
        // - In spring (Feb-Mar) in the evening after sunset
        // - In autumn (Sep-Oct) in the morning before sunrise
        // Only visible from locations with minimal light pollution

        // 5 years of zodiacal light periods
        foreach (range($year, $year + 4) as $y) {
            $events[] = [
                'date' => "$y-03-01",
                'formatted_date' => date('j M', strtotime("$y-03-01")),
                'type' => 'special',
                'event' => 'Zodiacal Light (evening)',
                'emoji' => '🌌',
            ];
            $events[] = [
                'date' => "$y-09-15",
                'formatted_date' => date('j M', strtotime("$y-09-15")),
                'type' => 'special',
                'event' => 'Zodiacal Light (morning)',
                'emoji' => '🌌',
            ];
        }

        return $events;
    }

    /**
     * Get meteor shower schedule
     */
    public function getMeteorShowers(): array
    {
        $year = date('Y');
        
        return [
            ['name' => 'Quadrantids', 'from' => "$year-01-01", 'to' => "$year-01-05", 'peak' => true],
            ['name' => 'Lyrids', 'from' => "$year-04-16", 'to' => "$year-04-25"],
            ['name' => 'Lyrids peak', 'from' => "$year-04-21", 'to' => "$year-04-22", 'peak' => true],
            ['name' => 'Eta Aquariids', 'from' => "$year-05-04", 'to' => "$year-05-07"],
            ['name' => 'Delta Aquariids', 'from' => "$year-07-21", 'to' => "$year-07-23"],
            ['name' => 'Perseids', 'from' => "$year-08-01", 'to' => "$year-08-10"],
            ['name' => 'Perseids peak', 'from' => "$year-08-11", 'to' => "$year-08-13", 'peak' => true],
            ['name' => 'Draconids peak', 'from' => "$year-10-07", 'to' => "$year-10-07", 'peak' => true],
            ['name' => 'Orionids peak', 'from' => "$year-10-20", 'to' => "$year-10-21", 'peak' => true],
            ['name' => 'South Taurids peak', 'from' => "$year-11-04", 'to' => "$year-11-05", 'peak' => true],
            ['name' => 'North Taurids peak', 'from' => "$year-11-11", 'to' => "$year-11-11", 'peak' => true],
            ['name' => 'Leonids', 'from' => "$year-11-13", 'to' => "$year-11-29"],
            ['name' => 'Leonids peak', 'from' => "$year-11-17", 'to' => "$year-11-18", 'peak' => true],
            ['name' => 'Geminids', 'from' => "$year-11-30", 'to' => "$year-12-12"],
            ['name' => 'Geminids peak', 'from' => "$year-12-13", 'to' => "$year-12-14", 'peak' => true],
            ['name' => 'Ursids', 'from' => "$year-12-17", 'to' => "$year-12-25"],
            ['name' => 'Ursids peak', 'from' => "$year-12-21", 'to' => "$year-12-22", 'peak' => true],
        ];
    }
}
