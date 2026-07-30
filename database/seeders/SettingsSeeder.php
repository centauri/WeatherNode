<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class SettingsSeeder extends Seeder
{
    /**
     * Seed the default settings from the old PWS-Dashboard configuration.
     * These values are taken from meteouitgeest_current/_my_settings/settings.php
     */
    public function run(): void
    {
        $settings = [
            // ===== Station Configuration =====
            ['key' => 'station.name', 'value' => 'WeatherNode', 'type' => 'string', 'group' => 'station', 'description' => 'Weather station display name'],
            ['key' => 'station.location', 'value' => 'Waldijk - Uitgeest - Noord-Holland - Nederland', 'type' => 'string', 'group' => 'station', 'description' => 'Station location description'],
            ['key' => 'station.latitude', 'value' => '52.5163996', 'type' => 'float', 'group' => 'station', 'description' => 'Station latitude (decimal degrees)'],
            ['key' => 'station.longitude', 'value' => '4.7078991', 'type' => 'float', 'group' => 'station', 'description' => 'Station longitude (decimal degrees)'],
            ['key' => 'station.elevation', 'value' => '-1', 'type' => 'float', 'group' => 'station', 'description' => 'Station elevation in meters above sea level'],
            ['key' => 'station.timezone', 'value' => 'Europe/Amsterdam', 'type' => 'string', 'group' => 'station', 'description' => 'Station timezone'],
            ['key' => 'station.hardware', 'value' => 'WH4000SE', 'type' => 'string', 'group' => 'station', 'description' => 'Weather station hardware model'],
            ['key' => 'station.manufacturer', 'value' => 'fineoffset', 'type' => 'select', 'group' => 'station', 'description' => 'Weather station manufacturer', 'options' => 'fineoffset:Fine Offset/Ecowitt,davis:Davis Instruments,netatmo:Netatmo,ambient:Ambient Weather,weatherflow:WeatherFlow,other:Other'],
            ['key' => 'station.start_date', 'value' => '2020-12-06', 'type' => 'date', 'group' => 'station', 'description' => 'Date station started recording'],
            ['key' => 'station.wu_id', 'value' => 'IUITGE8', 'type' => 'string', 'group' => 'station', 'description' => 'Weather Underground Station ID'],
            ['key' => 'station.server_url', 'value' => 'https://meteouitgeest.nl/', 'type' => 'string', 'group' => 'station', 'description' => 'Public URL of this weather site'],

            // ===== Live Data Source =====
            ['key' => 'livedata.format', 'value' => 'ecoLcl', 'type' => 'select', 'group' => 'livedata', 'description' => 'Primary live data format/source', 'options' => 'ecoLcl:Ecowitt Local (push),ecowittAPI:Ecowitt Cloud API,wu:Weather Underground,cumulus:Cumulus,weewx:WeeWX,weathercat:WeatherCat,DWL:WeatherLink Cloud v1,DWL_v2api:WeatherLink Cloud v2,DWL_v2api_demo:WeatherLink Cloud v2 (Demo Mode),meteohub:Meteohub,wswin:WSWIN,weatherlink:WeatherLink Local,wifilogger:WiFiLogger,MB_rt:Meteobridge (realtime.txt),wf:WeatherFlow,AWapi:Ambient Weather API,wd:Weather Display'],
            ['key' => 'livedata.fetch_mode', 'value' => 'file', 'type' => 'select', 'group' => 'livedata', 'description' => 'How to fetch local live data', 'options' => 'file:Local file,local_api:Local API URL'],
            ['key' => 'livedata.file_path', 'value' => './ecowitt/ecco_lcl.arr', 'type' => 'string', 'group' => 'livedata', 'description' => 'Path to live data file (if applicable)'],
            ['key' => 'livedata.api_url', 'value' => '', 'type' => 'string', 'group' => 'livedata', 'description' => 'Local API URL for live data (if applicable)'],
            ['key' => 'livedata.rain_yearly_source', 'value' => 'station', 'type' => 'select', 'group' => 'livedata', 'description' => 'Source for yearly rain total', 'options' => 'station:Use Station Data,calculated:Calculate from Database'],

            // ===== Historical Data Source =====
            ['key' => 'history.source', 'value' => 'WU', 'type' => 'select', 'group' => 'history', 'description' => 'Historical/chart data source', 'options' => 'WU:Weather Underground API,local:Generate from live data,both:WU + supplement with live'],
            ['key' => 'history.auto_generate', 'value' => '1', 'type' => 'boolean', 'group' => 'history', 'description' => 'Automatically generate daily summaries from live readings'],
            ['key' => 'history.wu_csv_unit', 'value' => 'metric', 'type' => 'select', 'group' => 'history', 'description' => 'Unit system for WU CSV exports', 'options' => 'metric:Metric,imperial:Imperial'],
            ['key' => 'history.wu_sync_enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'history', 'description' => 'Enable scheduled Weather Underground history sync'],
            ['key' => 'history.wu_sync_days', 'value' => '7', 'type' => 'integer', 'group' => 'history', 'description' => 'Days back to sync from WU (scheduled job)'],
            ['key' => 'history.wu_sync_time', 'value' => '02:10', 'type' => 'string', 'group' => 'history', 'description' => 'Daily time for WU sync (HH:MM)'],
            ['key' => 'history.wu_sync_skip_existing', 'value' => '1', 'type' => 'boolean', 'group' => 'history', 'description' => 'Skip days that already have summaries when syncing WU'],

            // ===== Display Settings =====
            ['key' => 'display.language', 'value' => 'nl-nl', 'type' => 'select', 'group' => 'display', 'description' => 'Default interface language', 'options' => 'auto:Auto (browser),nl-nl:Nederlands,en-us:English (US),en-gb:English (UK),de-de:Deutsch,fr-fr:Français,es-es:Español,it-it:Italiano,pt-pt:Português (PT),pt-br:Português (BR),pl-pl:Polski,da-dk:Dansk,nn-no:Norsk,sv-se:Svenska,fi-fi:Suomi,el-gr:Ελληνικά,hr-hr:Hrvatski,sr-rs:Srpski,ca-es:Català'],
            ['key' => 'display.unit_system', 'value' => 'metric', 'type' => 'select', 'group' => 'display', 'description' => 'Default unit system', 'options' => 'auto:Auto (browser locale),metric:Metric (°C km/h mm hPa),imperial:Imperial (°F mph in inHg),uk:UK (°C mph mm hPa),scandinavia:Scandinavia (°C m/s mm hPa)'],
            ['key' => 'display.theme', 'value' => 'dark', 'type' => 'select', 'group' => 'display', 'description' => 'Default admin interface theme', 'options' => 'dark:Dark,light:Light,user:User preference'],
            ['key' => 'display.temperature_decimals', 'value' => '1', 'type' => 'select', 'group' => 'display', 'description' => 'Temperature decimal places', 'options' => '0:0,1:1,2:2'],
            ['key' => 'display.wind_decimals', 'value' => '1', 'type' => 'select', 'group' => 'display', 'description' => 'Wind speed decimal places', 'options' => '0:0,1:1,2:2'],
            ['key' => 'display.rain_decimals', 'value' => '1', 'type' => 'select', 'group' => 'display', 'description' => 'Rainfall decimal places', 'options' => '0:0,1:1,2:2'],
            ['key' => 'display.pressure_decimals', 'value' => '0', 'type' => 'select', 'group' => 'display', 'description' => 'Pressure decimal places', 'options' => '0:0,1:1,2:2'],
            ['key' => 'display.rainrate_unit', 'value' => '/h', 'type' => 'select', 'group' => 'display', 'description' => 'Rain rate display unit', 'options' => '/h:per hour (/h),/min:per minute (/min)'],

            // ===== Ecowitt Settings =====
            ['key' => 'ecowitt.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'ecowitt', 'description' => 'Enable Ecowitt data source'],
            ['key' => 'ecowitt.data_source', 'value' => 'local_file', 'type' => 'select', 'group' => 'ecowitt', 'description' => 'Ecowitt data source mode', 'options' => 'local_file:Local file,local_api:Local API,cloud_api:Cloud API'],
            ['key' => 'ecowitt.passkey', 'value' => '', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Passkey for local upload validation (leave empty to accept all)'],
            ['key' => 'ecowitt.secure_mode', 'value' => '0', 'type' => 'boolean', 'group' => 'ecowitt', 'description' => 'Require endpoint token and strict passkey validation for Ecowitt push receiver'],
            ['key' => 'ecowitt.secure_token', 'value' => '', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Optional endpoint token appended to /api/ecowitt/receive/{token}'],
            ['key' => 'ecowitt.ip_filter_enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'ecowitt', 'description' => 'Allow Ecowitt push uploads only from listed source IPs/CIDRs'],
            ['key' => 'ecowitt.ip_allowlist', 'value' => '', 'type' => 'text', 'group' => 'ecowitt', 'description' => 'Source IP allowlist for Ecowitt push uploads (newline/comma-separated IP or CIDR entries)'],
            ['key' => 'ecowitt.name_filter_enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'ecowitt', 'description' => 'Allow Ecowitt push uploads only from listed station names/models'],
            ['key' => 'ecowitt.name_allowlist', 'value' => '', 'type' => 'text', 'group' => 'ecowitt', 'description' => 'Station name/model allowlist for Ecowitt push uploads (newline/comma-separated partial matches)'],
            ['key' => 'ecowitt.application_key', 'value' => '', 'type' => 'encrypted', 'group' => 'ecowitt', 'description' => 'Ecowitt Application Key (for cloud API - from api.ecowitt.net)'],
            ['key' => 'ecowitt.api_key', 'value' => '', 'type' => 'encrypted', 'group' => 'ecowitt', 'description' => 'Ecowitt API Key (for cloud API)'],
            ['key' => 'ecowitt.mac_address', 'value' => '', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Ecowitt device MAC address (e.g., AA:BB:CC:DD:EE:FF)'],
            ['key' => 'ecowitt.api_base_url', 'value' => 'https://api.ecowitt.net/api/v3/', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Ecowitt API base URL (cloud or local gateway)'],
            ['key' => 'ecowitt.local_file', 'value' => './ecowitt/ecco_lcl.arr', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Path to local Ecowitt data file'],
            ['key' => 'ecowitt.lightning_sensor', 'value' => '1', 'type' => 'boolean', 'group' => 'ecowitt', 'description' => 'Station has lightning sensor'],
            ['key' => 'ecowitt.air_quality_sensor', 'value' => '0', 'type' => 'boolean', 'group' => 'ecowitt', 'description' => 'Station has air quality sensor'],
            ['key' => 'ecowitt.uv_sensor', 'value' => '1', 'type' => 'boolean', 'group' => 'ecowitt', 'description' => 'Station has UV sensor'],
            ['key' => 'ecowitt.solar_sensor', 'value' => '1', 'type' => 'boolean', 'group' => 'ecowitt', 'description' => 'Station has solar radiation sensor'],
            ['key' => 'ecowitt.soil_sensors', 'value' => '0', 'type' => 'boolean', 'group' => 'ecowitt', 'description' => 'Station has soil moisture/temp sensors'],
            ['key' => 'ecowitt.extra_temp_sensors', 'value' => '0', 'type' => 'integer', 'group' => 'ecowitt', 'description' => 'Number of extra temperature sensors (0-8)'],
            ['key' => 'ecowitt.pm25_sensors', 'value' => '0', 'type' => 'integer', 'group' => 'ecowitt', 'description' => 'Number of PM2.5 sensors (0-4)'],
            ['key' => 'ecowitt.co2_sensor', 'value' => '0', 'type' => 'boolean', 'group' => 'ecowitt', 'description' => 'Station has CO2 sensor'],
            ['key' => 'ecowitt.leak_sensors', 'value' => '0', 'type' => 'integer', 'group' => 'ecowitt', 'description' => 'Number of leak detection sensors (0-4)'],
            
            // Sensor labels for customization
            ['key' => 'ecowitt.temp1_label', 'value' => 'Extra Sensor 1', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Label for extra temperature sensor 1'],
            ['key' => 'ecowitt.temp2_label', 'value' => 'Extra Sensor 2', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Label for extra temperature sensor 2'],
            ['key' => 'ecowitt.temp3_label', 'value' => 'Extra Sensor 3', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Label for extra temperature sensor 3'],
            ['key' => 'ecowitt.temp4_label', 'value' => 'Extra Sensor 4', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Label for extra temperature sensor 4'],
            ['key' => 'ecowitt.soil1_label', 'value' => 'Soil Sensor 1', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Label for soil sensor 1'],
            ['key' => 'ecowitt.soil2_label', 'value' => 'Soil Sensor 2', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Label for soil sensor 2'],
            ['key' => 'ecowitt.pm25_1_label', 'value' => 'PM2.5 Sensor 1', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Label for PM2.5 sensor 1'],
            ['key' => 'ecowitt.pm25_2_label', 'value' => 'PM2.5 Sensor 2', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Label for PM2.5 sensor 2'],
            ['key' => 'ecowitt.pm25_3_label', 'value' => 'PM2.5 Sensor 3', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Label for PM2.5 sensor 3'],
            ['key' => 'ecowitt.pm25_4_label', 'value' => 'PM2.5 Sensor 4', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Label for PM2.5 sensor 4'],
            ['key' => 'ecowitt.leak_1_label', 'value' => 'Leak Sensor 1', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Label for leak sensor 1'],
            ['key' => 'ecowitt.leak_2_label', 'value' => 'Leak Sensor 2', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Label for leak sensor 2'],
            ['key' => 'ecowitt.leak_3_label', 'value' => 'Leak Sensor 3', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Label for leak sensor 3'],
            ['key' => 'ecowitt.leak_4_label', 'value' => 'Leak Sensor 4', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Label for leak sensor 4'],
            ['key' => 'ecowitt.co2_label', 'value' => 'CO2 Sensor', 'type' => 'string', 'group' => 'ecowitt', 'description' => 'Label for CO2 sensor'],

            // ===== Weather Underground =====
            ['key' => 'wunderground.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'wunderground', 'description' => 'Enable Weather Underground integration'],
            ['key' => 'wunderground.station_id', 'value' => 'IUITGE8', 'type' => 'string', 'group' => 'wunderground', 'description' => 'Weather Underground Station ID'],
            ['key' => 'wunderground.api_key', 'value' => '', 'type' => 'encrypted', 'group' => 'wunderground', 'description' => 'Weather Underground API Key'],
            ['key' => 'wunderground.start_date', 'value' => '2020-12-06', 'type' => 'date', 'group' => 'wunderground', 'description' => 'Start date for historical data'],
            ['key' => 'wunderground.upload_enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'wunderground', 'description' => 'Upload live data to Weather Underground'],
            ['key' => 'wunderground.upload_password', 'value' => '', 'type' => 'encrypted', 'group' => 'wunderground', 'description' => 'WU Upload password/key'],

            // ===== OpenWeatherMap =====
            ['key' => 'openweathermap.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'openweathermap', 'description' => 'Enable OpenWeatherMap for forecasts'],
            ['key' => 'openweathermap.api_key', 'value' => '', 'type' => 'encrypted', 'group' => 'openweathermap', 'description' => 'OpenWeatherMap API Key'],
            ['key' => 'openweathermap.language', 'value' => 'nl', 'type' => 'string', 'group' => 'openweathermap', 'description' => 'Forecast language code'],
            ['key' => 'openweathermap.units', 'value' => 'si', 'type' => 'select', 'group' => 'openweathermap', 'description' => 'Unit system', 'options' => 'si:Metric,imperial:Imperial'],

            // ===== Yr.no =====
            ['key' => 'yrno.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'yrno', 'description' => 'Enable Yr.no forecasts'],
            ['key' => 'yrno.location', 'value' => 'Nederland/Nord-Holland/Uitgeest/', 'type' => 'string', 'group' => 'yrno', 'description' => 'Yr.no location path'],

            // ===== Aeris Weather =====
            ['key' => 'aeris.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'aeris', 'description' => 'Enable Aeris Weather API'],
            ['key' => 'aeris.access_id', 'value' => '', 'type' => 'encrypted', 'group' => 'aeris', 'description' => 'Aeris Access ID'],
            ['key' => 'aeris.secret_key', 'value' => '', 'type' => 'encrypted', 'group' => 'aeris', 'description' => 'Aeris Secret Key'],
            ['key' => 'aeris.show_popup', 'value' => '0', 'type' => 'boolean', 'group' => 'aeris', 'description' => 'Show Aeris popup on map'],

            // ===== Air Quality =====
            ['key' => 'airquality.index_type', 'value' => 'eea', 'type' => 'select', 'group' => 'airquality', 'description' => 'Index Type', 'options' => 'eea:European (EEA),us:US EPA,uk:UK DAQI'],
            ['key' => 'waqi.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'airquality', 'description' => 'Enable WAQI air quality data'],
            ['key' => 'waqi.api_key', 'value' => '', 'type' => 'encrypted', 'group' => 'airquality', 'description' => 'WAQI API token'],
            ['key' => 'luftdaten.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'airquality', 'description' => 'Enable Luftdaten/Sensor.Community data'],
            ['key' => 'luftdaten.sensor_id', 'value' => '69616', 'type' => 'string', 'group' => 'airquality', 'description' => 'Luftdaten sensor ID (particulate/air quality)'],
            ['key' => 'luftdaten.sensor_type', 'value' => '0', 'type' => 'integer', 'group' => 'airquality', 'description' => 'Sensor type (0=default)'],
            ['key' => 'luftdaten_noise.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'airquality', 'description' => 'Enable Luftdaten/Sensor.Community noise sensor (DNMS)'],
            ['key' => 'luftdaten_noise.sensor_id', 'value' => '', 'type' => 'string', 'group' => 'airquality', 'description' => 'Luftdaten noise sensor ID (DNMS)'],
            ['key' => 'luftdaten_noise.poll_interval_minutes', 'value' => '5', 'type' => 'select', 'group' => 'airquality', 'description' => 'Noise poll interval (noise changes quickly; 5 min = fresher)', 'options' => '5:5 min,10:10 min,15:15 min,30:30 min'],
            ['key' => 'luftdaten_noise.refresh_on_load', 'value' => '0', 'type' => 'boolean', 'group' => 'airquality', 'description' => 'Refresh noise when cache older than 2 min on page/API load (fresher, one extra request per visitor when stale)'],
            ['key' => 'luftdaten_noise.refresh_on_load_max_age', 'value' => '2', 'type' => 'integer', 'group' => 'airquality', 'description' => 'Max cache age (minutes) before refresh on load; only used if refresh on load is enabled'],
            ['key' => 'purpleair.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'airquality', 'description' => 'Enable PurpleAir sensor data'],
            ['key' => 'purpleair.sensor_id', 'value' => '0', 'type' => 'string', 'group' => 'airquality', 'description' => 'PurpleAir sensor ID'],
            ['key' => 'purpleair.api_key', 'value' => '', 'type' => 'encrypted', 'group' => 'airquality', 'description' => 'PurpleAir API key'],
            ['key' => 'davis_aq.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'airquality', 'description' => 'Enable Davis AirLink sensor'],
            ['key' => 'davis_aq.sensor_id', 'value' => '0', 'type' => 'string', 'group' => 'airquality', 'description' => 'Davis AirLink sensor ID'],

            // ===== Aviation/METAR =====
            ['key' => 'metar.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'aviation', 'description' => 'Enable METAR data display'],
            ['key' => 'metar.api_key', 'value' => '', 'type' => 'encrypted', 'group' => 'aviation', 'description' => 'CheckWX API key'],
            ['key' => 'metar.primary_icao', 'value' => 'EHAM', 'type' => 'string', 'group' => 'aviation', 'description' => 'Primary ICAO airport code'],
            ['key' => 'metar.airport_name', 'value' => 'AMS', 'type' => 'string', 'group' => 'aviation', 'description' => 'Airport short name'],
            ['key' => 'metar.airport_distance', 'value' => '34', 'type' => 'integer', 'group' => 'aviation', 'description' => 'Distance to airport (km)'],
            ['key' => 'metar.show_popup', 'value' => '1', 'type' => 'boolean', 'group' => 'aviation', 'description' => 'Show METAR popup details'],

            // ===== Weather Alerts =====
            ['key' => 'alerts.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'alerts', 'description' => 'Enable weather alerts/warnings'],
            ['key' => 'alerts.source', 'value' => 'europe', 'type' => 'select', 'group' => 'alerts', 'description' => 'Alert data source', 'options' => 'europe:Meteoalarm (Europe),usa:NWS (USA),canada:Environment Canada,uk:Met Office (UK),australia:BOM (Australia)'],
            
            // Europe (Meteoalarm) settings
            ['key' => 'alerts.region_code', 'value' => 'NL011', 'type' => 'string', 'group' => 'alerts', 'description' => 'Meteoalarm region code (e.g., NL011 for Noord-Holland)'],
            ['key' => 'alerts.region_name', 'value' => '', 'type' => 'string', 'group' => 'alerts', 'description' => 'Friendly region name for alerts display (optional)'],
            
            // USA (NWS) settings  
            ['key' => 'alerts.us_state', 'value' => 'NY', 'type' => 'string', 'group' => 'alerts', 'description' => 'US state code (e.g., NY, CA, TX)'],
            ['key' => 'alerts.us_zone', 'value' => '', 'type' => 'string', 'group' => 'alerts', 'description' => 'NWS zone code (optional, for specific area)'],
            
            // Canada (Environment Canada) settings
            ['key' => 'alerts.province', 'value' => 'ON', 'type' => 'string', 'group' => 'alerts', 'description' => 'Canadian province code (e.g., ON, BC, QC)'],
            ['key' => 'alerts.ca_region_code', 'value' => 'on-143', 'type' => 'string', 'group' => 'alerts', 'description' => 'Environment Canada region code (e.g., on-143 for Toronto)'],
            
            // UK (Met Office) settings
            ['key' => 'alerts.uk_region', 'value' => 'se', 'type' => 'string', 'group' => 'alerts', 'description' => 'UK Met Office region code (e.g., se, ln, nw)'],
            
            // Australia (BOM) settings
            ['key' => 'alerts.au_state', 'value' => 'nsw', 'type' => 'string', 'group' => 'alerts', 'description' => 'Australian state code (e.g., nsw, vic, qld)'],

            // ===== Sensor health tracking (individual sensors: fail alert if one stops reporting) =====
            ['key' => 'sensor_health.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'sensors', 'description' => 'Track individual sensors over time and alert when one stops reporting (e.g. empty battery)'],
            ['key' => 'sensor_health.track_days', 'value' => '7', 'type' => 'integer', 'group' => 'sensors', 'description' => 'Consider a sensor "active" if it reported in the last N days'],
            ['key' => 'sensor_health.fail_minutes', 'value' => '120', 'type' => 'integer', 'group' => 'sensors', 'description' => 'Alert if an active sensor has not reported in this many minutes'],

            // ===== Lightning =====
            ['key' => 'lightning.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'lightning', 'description' => 'Show lightning data (from station sensor)'],
            ['key' => 'boltek.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'lightning', 'description' => 'Enable Boltek lightning detector'],
            ['key' => 'boltek.data_file', 'value' => 'demodata/NSRealtime.txt', 'type' => 'string', 'group' => 'lightning', 'description' => 'Path to Boltek data file'],

            // ===== Earthquakes =====
            ['key' => 'earthquakes.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'earthquakes', 'description' => 'Show nearby earthquake activity'],
            ['key' => 'earthquakes.radius_km', 'value' => '500', 'type' => 'integer', 'group' => 'earthquakes', 'description' => 'Search radius in kilometers'],
            ['key' => 'earthquakes.min_magnitude', 'value' => '2.5', 'type' => 'float', 'group' => 'earthquakes', 'description' => 'Minimum magnitude to display'],

            // ===== ISS / Space Stations =====
            ['key' => 'iss.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'iss', 'description' => 'Enable space station tracking'],
            ['key' => 'iss.show_iss', 'value' => '1', 'type' => 'boolean', 'group' => 'iss', 'description' => 'Show International Space Station'],
            ['key' => 'iss.show_tiangong', 'value' => '1', 'type' => 'boolean', 'group' => 'iss', 'description' => 'Show Tiangong Space Station'],
            ['key' => 'iss.astronauts_api_source', 'value' => 'corquaid', 'type' => 'select', 'group' => 'iss', 'description' => 'Astronaut data API source', 'options' => 'corquaid:corquaid.github.io (Recommended),open-notify:Open Notify API,n2yo:N2YO.com API'],
            ['key' => 'iss.n2yo_api_key', 'value' => '', 'type' => 'string', 'group' => 'iss', 'description' => 'N2YO.com API key (required if using N2YO source). Get your key at https://www.n2yo.com/api/'],
            ['key' => 'iss.astronauts_poll_frequency', 'value' => '60', 'type' => 'integer', 'group' => 'iss', 'description' => 'Astronaut data poll frequency (minutes)'],

            // ===== Snow =====
            ['key' => 'snow.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'snow', 'description' => 'Show snow depth data'],
            ['key' => 'snow.display_mode', 'value' => 'none', 'type' => 'select', 'group' => 'snow', 'description' => 'Snow display mode', 'options' => 'none:Disabled,manual:Manual entry,sensor:From sensor'],

            // ===== Webcam =====
            ['key' => 'webcam.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'webcam', 'description' => 'Enable webcam display'],
            ['key' => 'webcam.url', 'value' => 'https://www.meteouitgeest.nl/thumbnail/image.jpg', 'type' => 'string', 'group' => 'webcam', 'description' => 'Webcam image URL'],
            ['key' => 'webcam.refresh_interval', 'value' => '60', 'type' => 'integer', 'group' => 'webcam', 'description' => 'Webcam refresh interval (seconds)'],
            ['key' => 'webcam.full_image_url', 'value' => '', 'type' => 'string', 'group' => 'webcam', 'description' => 'Full resolution image URL (click to enlarge)'],
            ['key' => 'webcam.stream_url', 'value' => '', 'type' => 'string', 'group' => 'webcam', 'description' => 'Live stream URL (YouTube or Restreamer)'],
            ['key' => 'webcam.stream_type', 'value' => 'none', 'type' => 'select', 'group' => 'webcam', 'description' => 'Stream type', 'options' => 'none:None (image only),youtube:YouTube,restreamer:Restreamer'],
            ['key' => 'webcam.display_mode', 'value' => 'image', 'type' => 'select', 'group' => 'webcam', 'description' => 'Display mode', 'options' => 'image:Image only,stream:Stream only,both:Image with clickable stream'],

            // ===== Radar =====
            ['key' => 'radar.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'radar', 'description' => 'Enable rain radar display'],
            ['key' => 'radar.url', 'value' => 'https://cdn.knmi.nl/knmi/map/page/weer/actueel-weer/neerslagradar/WWWRADARTMP_loop.gif', 'type' => 'string', 'group' => 'radar', 'description' => 'Radar animation URL'],
            ['key' => 'radar.provider', 'value' => 'rainviewer', 'type' => 'select', 'group' => 'radar', 'description' => 'Radar provider', 'options' => 'knmi:KNMI,buienradar:Buienradar,rainviewer:RainViewer'],
            ['key' => 'radar.rainviewer_zoom', 'value' => '7', 'type' => 'integer', 'group' => 'radar', 'description' => 'RainViewer map zoom level (0=world, 1-7; max 7 as of 2026)'],
            ['key' => 'radar.rainviewer_mode', 'value' => 'api', 'type' => 'select', 'group' => 'radar', 'description' => 'RainViewer display mode', 'options' => 'api:API (animated map),iframe:Iframe embed'],
            ['key' => 'radar.frame_delay', 'value' => '1000', 'type' => 'select', 'group' => 'radar', 'description' => 'Animation speed between radar frames. Slower = fewer requests, less chance of rate limiting.', 'options' => '500:Fast (500ms),800:Normal (800ms),1000:Balanced (1000ms),1500:Slow (1500ms),2000:Very slow (2000ms)'],
            ['key' => 'radar.use_proxy', 'value' => '0', 'type' => 'boolean', 'group' => 'radar', 'description' => 'Use server-side tile caching. Prevents rate limiting and CORS issues. WARNING: May not work on shared hosting due to security restrictions (508 errors). Recommended for VPS/dedicated servers only.'],
            ['key' => 'radar.widget_provider', 'value' => '', 'type' => 'select', 'group' => 'radar', 'description' => 'Widget radar provider (leave empty to use main provider)', 'options' => ':Use main provider,knmi:KNMI,buienradar:Buienradar,rainviewer:RainViewer'],
            ['key' => 'radar.widget_rainviewer_mode', 'value' => 'api', 'type' => 'select', 'group' => 'radar', 'description' => 'Widget RainViewer display mode', 'options' => 'api:API (animated map),iframe:Iframe embed'],
            ['key' => 'radar.widget_future_frames_provider', 'value' => 'auto', 'type' => 'select', 'group' => 'radar', 'description' => 'Provider used for dashboard future radar frames', 'options' => 'auto:Auto,none:Disabled,knmi:KNMI,noaa:NOAA (US)'],

            // ===== Satellite =====
            ['key' => 'satellite.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'satellite', 'description' => 'Enable satellite imagery display'],
            ['key' => 'satellite.provider', 'value' => 'knmi', 'type' => 'select', 'group' => 'satellite', 'description' => 'Satellite provider', 'options' => 'knmi:KNMI (Local),nasa:NASA Worldview (Worldwide),custom:Custom URL'],
            ['key' => 'satellite.display_region', 'value' => 'europe', 'type' => 'select', 'group' => 'satellite', 'description' => 'Which satellite view to show on /radar', 'options' => 'europe:Local,world:Worldwide'],
            // NASA defaults (B): near-real-time + selectable daily + selectable truecolor/infrared
            ['key' => 'satellite.nasa.mode', 'value' => 'nrt', 'type' => 'select', 'group' => 'satellite', 'description' => 'NASA imagery mode', 'options' => 'nrt:Near real-time,daily:Daily mosaic'],
            ['key' => 'satellite.nasa.imagery', 'value' => 'truecolor', 'type' => 'select', 'group' => 'satellite', 'description' => 'NASA imagery type', 'options' => 'truecolor:True color,infrared:Infrared (thermal)'],
            // Defaults are tile templates (work in Leaflet). {time} will be replaced with today's YYYY-MM-DD in UTC.
            // Provider-specific storage (what the admin UI edits)
            ['key' => 'satellite.sources.knmi.europe_url', 'value' => 'https://www.meteociel.fr/accueil/sat24ir.gif', 'type' => 'string', 'group' => 'satellite', 'description' => 'KNMI: Europe satellite image URL (IR, updates frequently)'],
            ['key' => 'satellite.sources.knmi.world_url', 'value' => '', 'type' => 'string', 'group' => 'satellite', 'description' => 'KNMI: Worldwide tile URL template (optional)'],
            ['key' => 'satellite.sources.knmi.zoom', 'value' => '4', 'type' => 'integer', 'group' => 'satellite', 'description' => 'KNMI: Zoom level'],

            // Default NASA URLs start with NRT truecolor (time includes {datetime}).
            // If a user selects daily or infrared in admin UI, the app will rewrite these values accordingly.
            ['key' => 'satellite.sources.nasa.europe_url', 'value' => 'https://gibs.earthdata.nasa.gov/wmts/epsg3857/best/VIIRS_SNPP_CorrectedReflectance_TrueColor/default/{time_auto}/GoogleMapsCompatible_Level9/{z}/{y}/{x}.jpg', 'type' => 'string', 'group' => 'satellite', 'description' => 'NASA: Local tile URL template (supports {time_auto}, {time_yesterday}, {z}, {y}, {x})'],
            ['key' => 'satellite.sources.nasa.world_url', 'value' => 'https://gibs.earthdata.nasa.gov/wmts/epsg3857/best/VIIRS_SNPP_CorrectedReflectance_TrueColor/default/{time_auto}/GoogleMapsCompatible_Level9/{z}/{y}/{x}.jpg', 'type' => 'string', 'group' => 'satellite', 'description' => 'NASA: World tile URL template (supports {time_auto}, {time_yesterday}, {z}, {y}, {x})'],
            ['key' => 'satellite.sources.nasa.zoom', 'value' => '4', 'type' => 'integer', 'group' => 'satellite', 'description' => 'NASA: Zoom level'],

            ['key' => 'satellite.sources.custom.europe_url', 'value' => '', 'type' => 'string', 'group' => 'satellite', 'description' => 'Custom: Europe URL (image or tiles)'],
            ['key' => 'satellite.sources.custom.world_url', 'value' => '', 'type' => 'string', 'group' => 'satellite', 'description' => 'Custom: Worldwide URL (image or tiles)'],
            ['key' => 'satellite.sources.custom.zoom', 'value' => '4', 'type' => 'integer', 'group' => 'satellite', 'description' => 'Custom: Zoom level'],

            // Legacy keys kept for backward compatibility (not used by new UI)
            ['key' => 'satellite.knmi_url', 'value' => '', 'type' => 'string', 'group' => 'satellite', 'description' => 'Legacy Europe URL (deprecated)'],
            ['key' => 'satellite.nasa_url', 'value' => '', 'type' => 'string', 'group' => 'satellite', 'description' => 'Legacy worldwide URL (deprecated)'],
            ['key' => 'satellite.custom_url', 'value' => '', 'type' => 'string', 'group' => 'satellite', 'description' => 'Legacy custom URL (deprecated)'],
            ['key' => 'satellite.zoom', 'value' => '4', 'type' => 'integer', 'group' => 'satellite', 'description' => 'Legacy zoom (deprecated)'],

            // ===== Navigation Menu Toggles =====
            ['key' => 'navigation.forecast_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Forecast in navigation and allow route access'],
            ['key' => 'navigation.history_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show History in navigation and allow route access'],
            ['key' => 'navigation.statistics_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Statistics in navigation and allow route access'],
            ['key' => 'navigation.radar_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Radar in navigation and allow route access'],
            ['key' => 'navigation.satellite_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Satellite in navigation and allow route access'],
            ['key' => 'navigation.air_pollen_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Air & Pollen in navigation and allow route access'],
            ['key' => 'navigation.astronomy_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Astronomy in navigation and allow route access'],
            ['key' => 'navigation.sky_water_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Sky & Water in navigation and allow route access'],
            ['key' => 'navigation.fire_weather_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Fire Weather in navigation and allow route access'],
            ['key' => 'navigation.earthquakes_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Earthquakes in navigation and allow route access'],
            ['key' => 'navigation.alerts_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Alerts in navigation and allow route access'],

            // ===== Forecast Settings =====
            ['key' => 'forecast.default_source', 'value' => 'fct_yrno_block.php', 'type' => 'select', 'group' => 'forecast', 'description' => 'Default forecast source', 'options' => 'fct_yrno_block.php:Yr.no,fct_wu_block.php:Weather Underground,fct_darksky_block.php:OpenWeatherMap,fct_wxsim_block.php:WXSIM,fct_ec_block.php:Environment Canada,fct_tempest_block.php:WeatherFlow Tempest'],
            ['key' => 'forecast.sky_source', 'value' => 'ccn_metar_block.php', 'type' => 'select', 'group' => 'forecast', 'description' => 'Sky conditions source', 'options' => 'ccn_metar_block.php:METAR,ccn_ec_block.php:Environment Canada,ccn_noaa_block.php:NOAA'],
            // WXSIM plaintext forecast integration
            ['key' => 'wxsim.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'wxsim', 'description' => 'Enable WXSIM plaintext forecast integration'],
            ['key' => 'wxsim.file_path', 'value' => storage_path('app/wxsim/plaintext.txt'), 'type' => 'string', 'group' => 'wxsim', 'description' => 'Path to WXSIM plaintext forecast file (plaintext.txt). Leave as default if you copy the file into storage/app/wxsim/plaintext.txt'],
            // Environment Canada forecast integration
            ['key' => 'environment_canada.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'environment_canada', 'description' => 'Enable Environment Canada forecast integration'],
            ['key' => 'environment_canada.city_code', 'value' => '', 'type' => 'string', 'group' => 'environment_canada', 'description' => 'Override Environment Canada city code (e.g., on-118 for Toronto). Leave blank to auto-detect based on station location'],

            // ===== WeatherFlow =====
            ['key' => 'weatherflow.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'weatherflow', 'description' => 'Enable WeatherFlow Tempest station'],
            ['key' => 'weatherflow.station_id', 'value' => '0', 'type' => 'string', 'group' => 'weatherflow', 'description' => 'WeatherFlow station ID'],
            ['key' => 'weatherflow.api_token', 'value' => '', 'type' => 'encrypted', 'group' => 'weatherflow', 'description' => 'Tempest API token (tempestwx.com → Settings → Data Authorizations). Leave blank for public stations.'],

            // ===== Ambient Weather =====
            ['key' => 'ambient.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'ambient', 'description' => 'Enable Ambient Weather API'],
            ['key' => 'ambient.api_key', 'value' => '', 'type' => 'encrypted', 'group' => 'ambient', 'description' => 'Ambient Weather API Key'],
            ['key' => 'ambient.device_id', 'value' => '', 'type' => 'string', 'group' => 'ambient', 'description' => 'Ambient Weather device MAC'],

            // ===== Davis WeatherLink =====
            ['key' => 'weatherlink.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'weatherlink', 'description' => 'Enable Davis WeatherLink Cloud'],
            ['key' => 'weatherlink.type', 'value' => 'v2', 'type' => 'select', 'group' => 'weatherlink', 'description' => 'WeatherLink integration type', 'options' => 'v1:WeatherLink Cloud v1 API,v2:WeatherLink Cloud v2 API,airlink_local:AirLink Local API,wll_local:WeatherLink Live Local API'],
            ['key' => 'weatherlink.api_version', 'value' => 'v2', 'type' => 'select', 'group' => 'weatherlink', 'description' => 'API version (deprecated, use weatherlink.type)', 'options' => 'v1:Version 1,v2:Version 2'],
            ['key' => 'weatherlink.api_key', 'value' => '', 'type' => 'encrypted', 'group' => 'weatherlink', 'description' => 'WeatherLink API Key'],
            ['key' => 'weatherlink.api_secret', 'value' => '', 'type' => 'encrypted', 'group' => 'weatherlink', 'description' => 'WeatherLink API Secret'],
            ['key' => 'weatherlink.device_id', 'value' => '', 'type' => 'string', 'group' => 'weatherlink', 'description' => 'WeatherLink Device ID (v1 only)'],
            ['key' => 'weatherlink.station_id', 'value' => '0', 'type' => 'string', 'group' => 'weatherlink', 'description' => 'WeatherLink Station ID (v2 only, UUID or integer)'],
            ['key' => 'weatherlink.password', 'value' => '', 'type' => 'encrypted', 'group' => 'weatherlink', 'description' => 'WeatherLink.com password (v1 only)'],
            ['key' => 'weatherlink.demo_mode', 'value' => '0', 'type' => 'boolean', 'group' => 'weatherlink', 'description' => 'Enable demo mode (uses Davis Instruments demo station)'],
            ['key' => 'weatherlink.airlink_ip', 'value' => '', 'type' => 'string', 'group' => 'weatherlink', 'description' => 'AirLink device IP address or hostname'],
            ['key' => 'weatherlink.airlink_port', 'value' => '80', 'type' => 'integer', 'group' => 'weatherlink', 'description' => 'AirLink device port'],
            ['key' => 'weatherlink.wll_ip', 'value' => '', 'type' => 'string', 'group' => 'weatherlink', 'description' => 'WeatherLink Live device IP address or hostname'],
            ['key' => 'weatherlink.wll_port', 'value' => '80', 'type' => 'integer', 'group' => 'weatherlink', 'description' => 'WeatherLink Live device port'],
            ['key' => 'weatherlink.wll_udp_enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'weatherlink', 'description' => 'Enable UDP broadcast for WeatherLink Live'],
            ['key' => 'weatherlink.wll_udp_port', 'value' => '22222', 'type' => 'integer', 'group' => 'weatherlink', 'description' => 'UDP broadcast port'],
            ['key' => 'weatherlink.wll_udp_duration', 'value' => '1200', 'type' => 'integer', 'group' => 'weatherlink', 'description' => 'UDP broadcast duration in seconds'],

            // ===== Thresholds & Notifications =====
            ['key' => 'thresholds.uv_warning', 'value' => '8', 'type' => 'integer', 'group' => 'thresholds', 'description' => 'UV index warning threshold'],
            ['key' => 'thresholds.wind_gust_warning', 'value' => '22', 'type' => 'integer', 'group' => 'thresholds', 'description' => 'Wind gust warning (knots)'],
            ['key' => 'thresholds.heat_index_warning', 'value' => '30', 'type' => 'integer', 'group' => 'thresholds', 'description' => 'Heat index warning (°C)'],
            ['key' => 'thresholds.freeze_warning', 'value' => '0', 'type' => 'integer', 'group' => 'thresholds', 'description' => 'Freeze warning temperature (°C)'],

            // ===== Sensors Configuration =====
            ['key' => 'sensors.uv_solar', 'value' => 'both', 'type' => 'select', 'group' => 'sensors', 'description' => 'UV/Solar sensors present', 'options' => 'both:Both UV and Solar,uv:UV only,solar:Solar only,none:None'],
            ['key' => 'sensors.have_extra', 'value' => '0', 'type' => 'boolean', 'group' => 'sensors', 'description' => 'Station has extra sensors (soil, leaf, etc.)'],
            ['key' => 'sensors.extra_data_source', 'value' => 'use demodata', 'type' => 'string', 'group' => 'sensors', 'description' => 'Extra sensor data source'],

            // ===== Weather Visual Effects =====
            ['key' => 'effects.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'effects', 'description' => 'Enable weather visual effects on dashboard'],
            ['key' => 'effects.rain.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'effects', 'description' => 'Enable rain animation'],
            ['key' => 'effects.rain.intensity', 'value' => '50', 'type' => 'integer', 'group' => 'effects', 'description' => 'Rain intensity (10-100)'],
            ['key' => 'effects.rain.splash_on_cards', 'value' => '1', 'type' => 'boolean', 'group' => 'effects', 'description' => 'Show splash effects on cards'],
            ['key' => 'effects.snow.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'effects', 'description' => 'Enable snow animation'],
            ['key' => 'effects.snow.intensity', 'value' => '50', 'type' => 'integer', 'group' => 'effects', 'description' => 'Snow intensity (10-100)'],
            ['key' => 'effects.wind.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'effects', 'description' => 'Enable wind particles'],
            ['key' => 'effects.wind.intensity', 'value' => '50', 'type' => 'integer', 'group' => 'effects', 'description' => 'Wind particles intensity (10-100)'],
            ['key' => 'effects.lightning.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'effects', 'description' => 'Enable lightning flash effect'],
            ['key' => 'effects.sun.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'effects', 'description' => 'Enable sun rays effect'],
            ['key' => 'effects.clouds.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'effects', 'description' => 'Enable ambient clouds'],
            ['key' => 'effects.fog.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'effects', 'description' => 'Enable fog overlay'],
            ['key' => 'effects.test_mode', 'value' => '0', 'type' => 'boolean', 'group' => 'effects', 'description' => 'Test mode - show all effects regardless of weather'],
            ['key' => 'effects.test_effect', 'value' => 'rain', 'type' => 'select', 'group' => 'effects', 'description' => 'Effect to preview in test mode', 'options' => 'rain:Rain,snow:Snow,wind:Wind,lightning:Lightning,sun:Sun Rays,fog:Fog,all:All Effects'],

            // ===== Dashboard Widgets =====
            // Note: aurora, iss, extra_temps, soil, pm25, co2, leak, battery are OFF by default
            ['key' => 'widgets.enabled', 'value' => '["current","forecast","hourly","wind","rain","sun","moon","airquality","metar","radar","webcam","lightning","indoor"]', 'type' => 'json', 'group' => 'widgets', 'description' => 'Enabled dashboard widgets'],
            ['key' => 'widgets.rain_visualization', 'value' => 'ripple', 'type' => 'select', 'group' => 'widgets', 'description' => 'Rain widget artistic visualization style', 'options' => 'ripple:Ripple Pond,mountain:Mountain Lake,tree:Growing Tree,none:None (bars only)'],
            ['key' => 'widgets.layout', 'value' => '[{"id":"hero","widgets":["current"]},{"id":"row1","widgets":["forecast","wind","rain"]},{"id":"row2","widgets":["sun","moon","airquality"]},{"id":"row3","widgets":["metar","radar","webcam"]}]', 'type' => 'json', 'group' => 'widgets', 'description' => 'Dashboard widget layout'],
            ['key' => 'widgets.small_row', 'value' => '["advisory_c_small.php","wind_c_small.php","temp_c_small.php","lightning_station_small.php","earthquake_c_small.php"]', 'type' => 'json', 'group' => 'widgets', 'description' => 'Small widget row (top)'],
            ['key' => 'widgets.cols_extra', 'value' => '1', 'type' => 'integer', 'group' => 'widgets', 'description' => 'Extra columns in widget grid'],
            ['key' => 'widgets.rows_extra', 'value' => '0', 'type' => 'integer', 'group' => 'widgets', 'description' => 'Extra rows in widget grid'],

            // ===== SEO & Meta =====
            ['key' => 'seo.site_title', 'value' => 'WeatherNode - Live weather station', 'type' => 'string', 'group' => 'seo', 'description' => 'Site title (browser tab)'],
            ['key' => 'seo.site_description', 'value' => 'Live weather data from a local weather station: temperature, wind, precipitation, and forecast.', 'type' => 'textarea', 'group' => 'seo', 'description' => 'Site meta description'],
            ['key' => 'seo.site_keywords', 'value' => 'weather, weather station, temperature, wind, rain, forecast, ecowitt', 'type' => 'string', 'group' => 'seo', 'description' => 'Site keywords (comma-separated)'],
            ['key' => 'seo.og_image', 'value' => '', 'type' => 'string', 'group' => 'seo', 'description' => 'Social sharing image URL'],

            // ===== Contact & Social =====
            ['key' => 'contact.email', 'value' => '', 'type' => 'string', 'group' => 'contact', 'description' => 'Contact email address'],
            ['key' => 'contact.show_email', 'value' => '0', 'type' => 'boolean', 'group' => 'contact', 'description' => 'Show email on site'],
            ['key' => 'contact.twitter', 'value' => '', 'type' => 'string', 'group' => 'contact', 'description' => 'X profile URL'],
            ['key' => 'contact.twitter_enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'contact', 'description' => 'Show X link'],
            ['key' => 'contact.facebook', 'value' => '', 'type' => 'string', 'group' => 'contact', 'description' => 'Facebook page URL'],
            ['key' => 'contact.facebook_enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'contact', 'description' => 'Show Facebook link'],
            ['key' => 'contact.instagram', 'value' => '', 'type' => 'string', 'group' => 'contact', 'description' => 'Instagram profile URL'],
            ['key' => 'contact.youtube', 'value' => '', 'type' => 'string', 'group' => 'contact', 'description' => 'YouTube channel URL'],
            ['key' => 'contact.linkedin', 'value' => '', 'type' => 'string', 'group' => 'contact', 'description' => 'LinkedIn profile URL'],
            ['key' => 'contact.disclaimer', 'value' => 'Never base important decisions that could result in harm to people or property on this weather information.', 'type' => 'textarea', 'group' => 'contact', 'description' => 'Disclaimer text'],

            // ===== System =====
            ['key' => 'system.registration_enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'system', 'description' => 'Allow public user self-registration'],

            // ===== Footer Configuration =====
            ['key' => 'footer.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'footer', 'description' => 'Enable footer display'],
            ['key' => 'footer.show_station_info', 'value' => '1', 'type' => 'boolean', 'group' => 'footer', 'description' => 'Show station information section'],
            ['key' => 'footer.show_coordinates', 'value' => '1', 'type' => 'boolean', 'group' => 'footer', 'description' => 'Show station coordinates'],
            ['key' => 'footer.show_social', 'value' => '1', 'type' => 'boolean', 'group' => 'footer', 'description' => 'Show social media links section'],
            ['key' => 'footer.show_quick_links', 'value' => '1', 'type' => 'boolean', 'group' => 'footer', 'description' => 'Show quick links section'],
            ['key' => 'footer.show_legal', 'value' => '1', 'type' => 'boolean', 'group' => 'footer', 'description' => 'Show legal links section'],
            ['key' => 'footer.show_seo_text', 'value' => '0', 'type' => 'boolean', 'group' => 'footer', 'description' => 'Show optional SEO text in the fat footer for search engines to scrape'],
            ['key' => 'footer.custom_links', 'value' => '[]', 'type' => 'json', 'group' => 'footer', 'description' => 'Custom footer links (JSON array)'],

            // ===== Advanced =====
            ['key' => 'advanced.log_level', 'value' => 'info', 'type' => 'select', 'group' => 'advanced', 'description' => 'Application log verbosity threshold', 'options' => 'debug:Debug,info:Info,notice:Notice,warning:Warning,error:Error,critical:Critical,alert:Alert,emergency:Emergency'],
            ['key' => 'dashboard.hybrid_ssr_enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'advanced', 'description' => 'Enable hybrid SSR for dashboard first render (server HTML + JS hydration)'],

            // ===== Telemetry & Community Stations =====
            ['key' => 'telemetry.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'telemetry', 'description' => 'Enable telemetry - share station data with community map'],
            ['key' => 'telemetry.aggregator_url', 'value' => 'https://weathernode.dev/telemetry-aggregator/api/telemetry', 'type' => 'string', 'group' => 'telemetry', 'description' => 'Central aggregator service URL (handles GitHub updates)'],
            ['key' => 'telemetry.api_key', 'value' => '', 'type' => 'encrypted', 'group' => 'telemetry', 'description' => 'API key for aggregator authentication (optional)'],
            ['key' => 'telemetry.github_repo', 'value' => 'centauri/community-stations', 'type' => 'string', 'group' => 'telemetry', 'description' => 'GitHub repository for community stations (read-only, used for map display)'],
            ['key' => 'telemetry.github_file', 'value' => 'stations.json', 'type' => 'string', 'group' => 'telemetry', 'description' => 'JSON file path in GitHub repository (read-only)'],
            ['key' => 'telemetry.last_updated', 'value' => '', 'type' => 'string', 'group' => 'telemetry', 'description' => 'Last telemetry update timestamp'],
        ];

        foreach ($settings as $setting) {
            $existing = Setting::where('key', $setting['key'])->first();

            if (!$existing) {
                Setting::create($setting);
                Cache::forget("setting.{$setting['key']}");
                continue;
            }

            $updates = [
                'type' => $setting['type'],
                'group' => $setting['group'],
                'description' => $setting['description'] ?? $existing->description,
            ];

            if (array_key_exists('options', $setting)) {
                $updates['options'] = $setting['options'];
            }

            // For satellite URLs, update value only if empty or a known-bad legacy value.
            if (str_starts_with($setting['key'], 'satellite.') && array_key_exists('value', $setting)) {
                $current = $existing->value;
                $isEmpty = $current === null || trim((string) $current) === '';
                $knownBad = in_array(trim((string) $current), [
                    'https://cdn.knmi.nl/knmi/map/page/weer/actueel-weer/satelliet/WWWRADAREU_loop.gif',
                    'https://cdn.knmi.nl/knmi/map/page/weer/actueel-weer/satelliet/WWWRADAREU_loop.gif?t={{ time() }}',
                    'https://en.allmetsat.com/images/msg_vis.php',
                    'https://en.allmetsat.com/images/sat24_europe_vis.php',
                    'https://www.meteosat.int/images/latestImages/latestImages_EUMETSAT_MSG_RGBNatColourEnhncd_WesternEurope.jpg',
                    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                    'https://gibs.earthdata.nasa.gov/wmts/epsg3857/best/MODIS_Terra_CorrectedReflectance_TrueColor/default/{time}/GoogleMapsCompatible_Level9/{z}/{y}/{x}.jpg',
                ], true);

                if ($isEmpty || $knownBad) {
                    $updates['value'] = $setting['value'];
                }
            }

            $existing->fill($updates)->save();
            Cache::forget("setting.{$setting['key']}");
        }
    }
}
