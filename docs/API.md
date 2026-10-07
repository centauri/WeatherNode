# WeatherNode API

WeatherNode exposes weather observations, forecasts, radar data, air quality data, and selected station services through a JSON API. This guide covers authentication, rate limits, endpoints, and a few practical integration examples.

The examples use `https://weather.example.com` as the WeatherNode address. Replace it with the address of your own installation.

## Quick start

Create an API key in **Admin > API Keys**, then send it in the `X-API-Key` header:

```bash
curl --fail --silent --show-error \
  -H "Accept: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  "https://weather.example.com/api/weather/current"
```

Most responses use this general shape:

```json
{
  "success": true,
  "data": {
    "temperature": 18.4,
    "humidity": 72,
    "wind_speed": 9.7
  }
}
```

Fields can be `null` when a sensor or data source is not available. Some disabled or unavailable services return HTTP 200 with `success: false`, so clients should check the `success` field as well as the HTTP status.

## Authentication

WeatherNode has two kinds of API keys:

- **Public keys** can read the weather, radar, and public air quality endpoints. They are suitable for browser dashboards and trusted home automation systems.
- **Private keys** can use every endpoint, including external provider data, telemetry, and forecast narration. Keep private keys on a server or another trusted device.

All normal API requests must send the key in the `X-API-Key` header.

```http
X-API-Key: YOUR_API_KEY
```

The `api_key` query parameter is accepted only for these image endpoints, where browsers may not be able to add a request header:

- `/api/radar/tile/*`
- `/api/radar/future-image`

Do not put a private key in a URL. URLs are commonly stored in browser history, access logs, and monitoring tools.

`POST /api/ecowitt/receive/{token?}` and `/api/wu/receive` are the only endpoints that do not use a WeatherNode API key. They have their own receiver security settings.

## Rate limits

The default limit is 120 requests per minute for each API key and client IP combination. An administrator can set a different limit when creating a key.

Radar tile and future image requests use a separate allowance of five times the key limit. A key with the default limit can therefore make up to 600 radar image requests per minute from one client IP.

When a limit is exceeded, WeatherNode returns HTTP 429 and a `Retry-After` header:

```json
{
  "error": "Rate limit exceeded"
}
```

## Language and units

Use `lang` or `locale` to select the language used for translated or generated text:

```text
/api/weather/forecast?lang=nl-nl
```

Weather readings are returned in canonical metric units. In most endpoints these are:

- Temperature: degrees Celsius
- Wind speed: kilometres per hour
- Pressure: hectopascals
- Rain: millimetres
- Distance: kilometres

The `units` preference used by the web interface does not convert API values. Integrations should convert values themselves when another unit system is needed.

## Errors

Authentication and rate limit errors use a small error object:

| Status | Meaning | Response |
| --- | --- | --- |
| 401 | No key was supplied | `{"error":"API key required"}` |
| 401 | The key is invalid or revoked | `{"error":"Invalid API key"}` |
| 403 | A public key was used for a private endpoint | `{"error":"Private API key required"}` |
| 429 | The key exceeded its request limit | `{"error":"Rate limit exceeded"}` |

Endpoint validation errors usually return `success: false` with a message. Laravel validation responses, such as those from forecast narration, return HTTP 422 with `message` and `errors`.

## Endpoint reference

Access values used below:

- **Public** means a valid public or private API key can be used.
- **Private** means a private API key is required.
- **Receiver** means the endpoint uses Ecowitt receiver security instead of a WeatherNode API key.

### Weather

| Method | Endpoint | Access | Description |
| --- | --- | --- | --- |
| GET | `/api/weather/dashboard` | Public | Combined payload used by the main dashboard |
| GET | `/api/weather/current` | Public | Latest station reading and station details |
| GET | `/api/weather/today` | Public | Daily minimum, maximum, average, rain, and wind summary |
| GET | `/api/weather/forecast` | Public | Daily and hourly forecast data |
| GET | `/api/weather/air-quality` | Public | WAQI data and local particulate readings |
| GET | `/api/weather/noise` | Public | Sensor.Community noise data when configured |
| GET | `/api/weather/astronomy` | Public | Sun, moon, meteor, aurora, and space data |
| GET | `/api/weather/metar` | Public | Aviation weather for the configured or requested ICAO station |
| GET | `/api/weather/history` | Public | Sampled historical values for one field |
| GET | `/api/weather/sensors` | Public | Available sensor groups and battery state |
| GET | `/api/weather/radar` | Public | RainViewer radar frame metadata |
| GET | `/api/weather/radar-nowcast` | Public | Configured short-term radar nowcast |
| GET | `/api/weather/radar-future-frames` | Public | Future radar frame metadata |
| GET | `/api/weather/solar-nowcast` | Public | Configured solar radiation nowcast |
| GET | `/api/weather/wms-layers` | Public | Available KNMI WMS layers and timestamps |
| GET | `/api/weather/wms-map` | Public | URL and legend for a KNMI WMS layer |

#### Current conditions

`GET /api/weather/current`

The `data` object contains the latest recorded values. Common fields include:

- `recorded_at`
- `temperature`, `feels_like`, `dew_point`, `heat_index`, `wind_chill`
- `humidity`, `pressure_rel`, `pressure_abs`
- `wind_speed`, `wind_gust`, `wind_direction`
- `rain_rate`, `rain_hourly`, `rain_daily`
- `uv_index`, `solar_radiation`
- Extra temperature, soil, particulate, CO2, leak, and lightning fields when those sensors are present

The `station` object contains the configured station name, location, latitude, and longitude.

#### Forecast

`GET /api/weather/forecast`

Optional query parameters:

| Parameter | Description |
| --- | --- |
| `lang` or `locale` | Language for generated forecast text, for example `nl-nl` or `en-gb` |

The response contains `data.daily`, `data.hourly`, and `meta`. Daily forecasts can include temperature, precipitation, wind, symbols, and generated narrative text. Hourly entries depend on the configured forecast provider.

#### Weather history

`GET /api/weather/history`

Query parameters:

| Parameter | Default | Accepted values |
| --- | --- | --- |
| `period` | `24h` | `24h`, `48h`, `7d`, `30d`, `1y` |
| `field` | `temperature` | A supported weather or sensor field |

Common fields include `temperature`, `humidity`, `pressure_rel`, `wind_speed`, `wind_gust`, `wind_direction`, `rain_rate`, `rain_daily`, `uv_index`, `solar_radiation`, `pm25_ch1`, `pm10`, `co2`, `lightning_count`, and `lightning_distance`.

```bash
curl --fail --silent --show-error \
  -H "X-API-Key: YOUR_API_KEY" \
  "https://weather.example.com/api/weather/history?period=24h&field=temperature"
```

Example response:

```json
{
  "success": true,
  "data": [
    {
      "time": "2026-08-09T12:00:00+00:00",
      "value": 18.4
    }
  ],
  "field": "temperature",
  "period": "24h",
  "sampling": {
    "bucket_seconds": 300,
    "max_points": 300
  }
}
```

#### METAR

`GET /api/weather/metar`

The optional `icao` parameter selects a four-letter ICAO station:

```text
/api/weather/metar?icao=EHAM
```

An invalid ICAO code returns HTTP 422. When METAR support is disabled, or when it is on but no airport has been chosen, the endpoint returns a successful response with `data: null`. A fresh install has no airport: it is asked for on the aviation settings page, and nothing is fetched until one is set.

#### KNMI WMS

`GET /api/weather/wms-map`

Query parameters:

| Parameter | Default | Description |
| --- | --- | --- |
| `layer` | None | Layer key returned by `/api/weather/wms-layers` |
| `style` | `default` | WMS legend style |
| `time` | `latest` | Requested WMS time |
| `width` | `512` | Map width in pixels |
| `height` | `512` | Map height in pixels |
| `opacity` | `1` | Requested opacity |

At present, `time` and `opacity` are accepted for compatibility but are not applied to the generated map URL. The `style` value is used for the legend URL. Use `/api/weather/wms-layers` to discover valid layer keys and available provider times.

### Public data and radar

| Method | Endpoint | Access | Description |
| --- | --- | --- | --- |
| GET | `/api/ecowitt/status` | Public | Receiver status, station details, and battery state |
| GET | `/api/data/luftdaten` | Public | Sensor.Community air quality data |
| GET | `/api/radar/frames` | Public | Cached RainViewer frame metadata |
| GET | `/api/radar/tile/{path}` | Public | Cached PNG radar tile |
| GET | `/api/radar/future-image` | Public | Cached PNG from an allowed future radar provider |

Radar tile paths use the RainViewer format:

```text
/api/radar/tile/v2/radar/{timestamp}/{size}/{z}/{x}/{y}/{color}/{options}.png
```

`GET /api/radar/future-image` requires a URL-encoded HTTPS `url` parameter. Only providers allowed by the WeatherNode server are accepted.

These two image endpoints return `image/png`, not JSON. On an upstream error they can return a transparent placeholder image so maps do not break.

### Private data

| Method | Endpoint | Access | Description |
| --- | --- | --- | --- |
| GET | `/api/data/alerts` | Private | Weather alerts and highest severity |
| GET | `/api/data/earthquakes` | Private | Recent earthquakes and statistics |
| GET | `/api/data/purpleair` | Private | PurpleAir data when configured |
| GET | `/api/data/air-quality` | Private | Combined air quality sources |
| GET | `/api/data/lightning` | Private | Configured lightning source |
| GET | `/api/data/external` | Private | Combined external data cache |
| GET | `/api/sources/aeris` | Private | AerisWeather source data |
| GET | `/api/sources/weatherlink` | Private | WeatherLink source data |
| GET | `/api/sources/ambient` | Private | Ambient Weather source data |
| GET | `/api/sources/weatherflow` | Private | WeatherFlow source data |
| GET | `/api/telemetry/stations` | Private | Community station telemetry |
| POST | `/api/forecast/narrate` | Private | Generate readable text from structured forecast data |

Private source endpoints may include data obtained with third-party credentials. Only give private keys to integrations you trust.

#### Forecast narration

`POST /api/forecast/narrate`

Send JSON containing any available forecast fields:

```bash
curl --fail --silent --show-error \
  -X POST \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_PRIVATE_API_KEY" \
  --data '{
    "date": "2026-08-10",
    "min_temp_c": 13,
    "max_temp_c": 22,
    "wind_ms": 4.2,
    "wind_dir_deg": 240,
    "precip_prob_pct": 35,
    "precip_mm": 1.4,
    "cloud_pct": 60
  }' \
  "https://weather.example.com/api/forecast/narrate"
```

The response contains the supplied date and generated text:

```json
{
  "date": "2026-08-10",
  "text": "..."
}
```

### Ecowitt receiver

| Method | Endpoint | Access | Description |
| --- | --- | --- | --- |
| POST | `/api/ecowitt/receive/{token?}` | Receiver | Receive a push from an Ecowitt station |

Configure the endpoint in WS View or the Ecowitt app under the customized weather service settings.

```text
https://weather.example.com/api/ecowitt/receive
```

When secure receiver mode is enabled, use the configured path token:

```text
https://weather.example.com/api/ecowitt/receive/YOUR_RECEIVER_TOKEN
```

Ecowitt sends form data containing fields such as `tempf`, `humidity`, `baromrelin`, `windspeedmph`, `winddir`, `rainratein`, and `solarradiation`. WeatherNode converts incoming imperial values to canonical metric values before storage.

The receiver returns plain text:

| Status | Response | Meaning |
| --- | --- | --- |
| 200 | `OK` | Reading accepted |
| 400 | `Invalid data` | The payload did not contain a usable reading |
| 403 | Plain-text reason | Token, passkey, source IP, or station filter rejected the push |
| 503 | Plain-text reason | Secure receiver settings are incomplete |

### Wunderground upload receiver

| Method | Endpoint | Access | Description |
| --- | --- | --- | --- |
| GET | `/api/wu/receive` | Receiver | Receive an upload in the Weather Underground format (POST works too) |

WeeWX, Meteobridge and many consoles can upload in the Weather Underground format to an address of your choosing. Pick **Wunderground upload (push)** as the live data source and save. The Live Data Source page then shows the address and a station key. Senders put the key in the `PASSWORD` field. The station `ID` can be anything.

```text
https://weather.example.com/api/wu/receive?ID=weathernode&PASSWORD=YOUR_STATION_KEY&dateutc=now&tempf=54.1&humidity=81&baromin=29.92&windspeedmph=8.5&winddir=225&dailyrainin=0.12
```

Supported fields include `tempf`, `dewptf`, `humidity`, `baromin`, `windspeedmph`, `windgustmph`, `winddir`, `rainin` (rain in the last hour), `dailyrainin`, `solarradiation`, `UV`, `indoortempf`, `indoorhumidity`, soil, leaf wetness and `AqPM2.5`. A value of `-9999` means the sensor has no value. WeatherNode converts the US units to metric and saves at most one reading every 55 seconds, so rapidfire uploads every few seconds are fine.

The receiver returns plain text, the way Weather Underground does:

| Status | Response | Meaning |
| --- | --- | --- |
| 200 | `success` | Upload accepted |
| 400 | `invalid data: no tempf` | The upload had no temperature |
| 401 | `ERROR: wrong station key` | The `PASSWORD` did not match the station key |
| 403 | `ERROR: ...` | Wunderground upload is not the live data source |
| 503 | `ERROR: ...` | No station key yet. Save the Live Data Source page first |

## Integration examples

The following examples are starting points. Field availability depends on the sensors and services enabled on your WeatherNode installation.

### Home Assistant

Home Assistant can read the current endpoint with its built-in [RESTful Sensor](https://www.home-assistant.io/integrations/sensor.rest/) integration.

Add this to `configuration.yaml`:

```yaml
rest:
  - resource: https://weather.example.com/api/weather/current
    headers:
      X-API-Key: !secret weathernode_api_key
      Accept: application/json
    scan_interval: 60
    sensor:
      - name: WeatherNode temperature
        unique_id: weathernode_temperature
        value_template: "{{ value_json.data.temperature }}"
        unit_of_measurement: "°C"
        device_class: temperature
        state_class: measurement
      - name: WeatherNode humidity
        unique_id: weathernode_humidity
        value_template: "{{ value_json.data.humidity }}"
        unit_of_measurement: "%"
        device_class: humidity
        state_class: measurement
      - name: WeatherNode wind speed
        unique_id: weathernode_wind_speed
        value_template: "{{ value_json.data.wind_speed }}"
        unit_of_measurement: "km/h"
        device_class: wind_speed
        state_class: measurement
```

Store the key in `secrets.yaml`:

```yaml
weathernode_api_key: YOUR_API_KEY
```

Restart Home Assistant after checking the configuration. A public key is sufficient for this integration.

### Node-RED

Use an **inject** node, a **function** node, and an **http request** node.

Put this in the function node:

```javascript
msg.method = "GET";
msg.url = "https://weather.example.com/api/weather/current";
msg.headers = {
    Accept: "application/json",
    "X-API-Key": env.get("WEATHERNODE_API_KEY"),
};

return msg;
```

Set the HTTP request node to return a parsed JSON object. Keeping the key in a Node-RED environment variable avoids storing it directly in an exported flow.

### Telegraf

Telegraf can poll WeatherNode and write selected numeric fields to InfluxDB or another supported output.

```toml
[[inputs.http]]
  urls = ["https://weather.example.com/api/weather/current"]
  method = "GET"
  interval = "60s"
  timeout = "10s"
  data_format = "json_v2"

  [inputs.http.headers]
    Accept = "application/json"
    X-API-Key = "${WEATHERNODE_API_KEY}"

  [[inputs.http.json_v2.object]]
    path = "data"
    included_keys = [
      "temperature",
      "humidity",
      "pressure_rel",
      "wind_speed",
      "wind_gust",
      "rain_rate",
      "solar_radiation"
    ]
```

Set `WEATHERNODE_API_KEY` in the Telegraf service environment before starting it.

### Python

```python
import os

import requests

base_url = "https://weather.example.com"
api_key = os.environ["WEATHERNODE_API_KEY"]

response = requests.get(
    f"{base_url}/api/weather/current",
    headers={
        "Accept": "application/json",
        "X-API-Key": api_key,
    },
    timeout=10,
)
response.raise_for_status()

payload = response.json()
if not payload.get("success"):
    raise RuntimeError(payload.get("message", "WeatherNode returned no data"))

print(payload["data"]["temperature"])
```

## Operational notes

- API keys are shown in full only when they are created. Store them in a password manager or secret store.
- Revoke a key from **Admin > API Keys** when an integration no longer needs access.
- Use a separate key for each integration. This makes revocation and rate limit changes safer.
- Poll current conditions no more often than the station updates. Once per minute is a sensible starting point for most installations.
- The dashboard payload is cached briefly and is useful when one client needs many widgets at once.
- Endpoint fields may grow as WeatherNode adds sensors and providers. Clients should ignore fields they do not recognise.
- WeatherNode does not currently publish a versioned API path. Check release notes before relying on newly added fields.
