# Issue #12 WeatherNode Branding Implementation Plan

> **For agentic workers:** Implement task-by-task. No DB migrations. Do not overwrite existing setting values.

**Goal:** Neutralize hardcoded MeteoUitgeest/Uitgeest branding to WeatherNode without migrating DB rows.

**Architecture:** Change code/seeder defaults and i18n keys only. Existing `settings` values stay as-is via create-if-missing seeder behavior.

**Tech Stack:** Laravel Blade, PHP Setting model, JSON lang files, SettingsSeeder

## Global Constraints

- No database migrations
- No seeder overwrite of existing setting values
- Keep Uitgeest geography + webcam URL in seeder
- Default radar provider → rainviewer (code + seeder only)
- Login: hardcoded WeatherNode + smaller station name subtitle
- Leave `meteouitgeest_ads_consent_v1` alone
- No Co-authored-by / Made with Cursor / Test plan in PR

---

## File map

| File | Change |
|------|--------|
| `app/Models/Setting.php` | `stationName()` fallback → WeatherNode |
| `database/seeders/SettingsSeeder.php` | name, SEO, radar.provider |
| `resources/views/auth/login.blade.php` | brand + subtitle + meta |
| `resources/views/layouts/admin.blade.php` | config fallback WeatherNode |
| `resources/views/weather/{dashboard,layout,radar}.blade.php` | SEO/webcam/radar defaults |
| `resources/views/admin/help.blade.php` | WeatherNode copy + paths |
| `resources/views/admin/settings/{notifications,group}.blade.php` | examples / radar default |
| `app/Console/Commands/{FetchWeatherData,CheckSensorHealth,MigrateHistoricalData}.php` | brand strings |
| `resources/lang/*.json` | rename place-specific keys |

---

### Task 1: Branch from main

- [ ] Checkout main, pull, create `fix/issue-12-weathernode-branding`

### Task 2: Defaults (PHP/Blade)

- [ ] Setting::stationName fallback
- [ ] SettingsSeeder name/SEO/radar
- [ ] Blade radar fallbacks knmi→rainviewer
- [ ] SEO/webcam/help/admin/console string updates
- [ ] Login UX

### Task 3: i18n key renames

- [ ] Update all call sites to new keys
- [ ] Script/update all 18 locale JSON files
- [ ] Grep verify no leftover user-facing MeteoUitgeest hardcodes in views/app

### Task 4: Ship

- [ ] Commit (no co-author)
- [ ] Push + open PR linking #12 (summary only)
