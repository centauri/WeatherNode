# User Guide (WeatherNode)

This guide is for **visitors** using the dashboard.

Admin documentation: see `ADMIN_GUIDE.md`.

## Dashboard basics

- **Auto refresh**: The dashboard fetches updated data periodically. Cards update only when data changes.
- **Per-card timestamps**: Each card shows the actual source timestamp (not just page refresh time).
- **Offline indicators**: When a data source goes stale, the related card shows an **OFFLINE** badge.

## What you can do

- View current conditions, forecasts, and optional sensor widgets.
- If the site is configured with radar/satellite/astronomy pages, you can open them from the UI.

## Appearance

Open **Menu** in the public header, then use **Colour mode** to choose **Light**, **Dark**, or **System** (follow your device). **Station default** removes your override and follows the owner's setting. Your choice is remembered in this browser for this station; it does not change the admin panel. If browser storage is unavailable, the selector still works for the current page.

The station owner chooses the default colour palette and FX/Flat presentation. If the owner enables visitor theme choices, the menu’s **Colour palette** selector offers those themes plus **Station default**. Your palette and colour-mode choices are remembered independently. If the owner removes your selected theme, the next page load returns to the station default. Colour mode and palette do not change weather data or warning categories.

The same menu contains visual effects, language and units. Close it with the close button, Escape, or a click outside the panel. On phones, the clock stays below the station name; on wider screens it shares the header row.
