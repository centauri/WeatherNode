<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\FirstRunSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The first-run setup: where the station is, then where its readings come from.
 *
 * Values are written with Setting::setValue(), which is an updateOrCreate. The
 * generic settings save cannot be reused here because it only iterates rows
 * that already exist in the request, and the wizard writes a fixed set.
 */
class SetupController extends Controller
{
    /**
     * Formats that have a settings page of their own, so the owner lands where
     * the keys and addresses are actually entered. Everything else goes to the
     * live data page, which is where those formats are configured.
     */
    private const FORMAT_SETTINGS_GROUP = [
        'ecoLcl' => 'ecowitt',
        'ecowittAPI' => 'ecowitt',
        'wu' => 'wunderground',
        'DWL' => 'weatherlink',
        'DWL_v2api' => 'weatherlink',
        'DWL_v2api_demo' => 'weatherlink',
        'weatherlink' => 'weatherlink',
        'AWapi' => 'ambient',
        'wf' => 'weatherflow',
        'netatmo' => 'netatmo',
    ];

    /**
     * The steps are in an order, and the flag says which one is owed.
     *
     * Without this, step two was reachable and completable whatever step was
     * owed, so posting to it with step one unfinished left an install marked
     * done with no location. A finished install is sent to its settings pages
     * instead, and saving a step again cannot reopen a setup that is over.
     *
     * Skipped counts as unfinished on purpose: the notice links back here, and
     * it would be lying if this bounced them away.
     */
    private function guard(string $step): ?RedirectResponse
    {
        if (!FirstRunSetup::unfinished()) {
            return redirect()->route('admin.dashboard');
        }

        if ($step === FirstRunSetup::SOURCE && FirstRunSetup::state() === FirstRunSetup::STATION) {
            return redirect()->route('admin.setup.station');
        }

        return null;
    }

    public function station(): View|RedirectResponse
    {
        if ($redirect = $this->guard(FirstRunSetup::STATION)) {
            return $redirect;
        }

        $timezones = \DateTimeZone::listIdentifiers();
        sort($timezones);

        return view('admin.setup.station', [
            'timezones' => $timezones,
            'name' => Setting::stationName(),
            'location' => Setting::stationLocation(),
            'latitude' => Setting::latitude(),
            'longitude' => Setting::longitude(),
            'elevation' => (float) Setting::getValue('station.elevation', 0),
            'timezone' => trim((string) (Setting::getValue('station.timezone', '') ?? '')),
            'serverUrl' => self::currentSiteAddress(),
            // Nothing has been chosen yet at step one: the seeded UTC and the
            // Greenwich coordinates are placeholders, not answers. The page
            // may suggest the browser's own zone and open the map at world
            // view rather than implying that London is right.
            'nothingChosenYet' => FirstRunSetup::state() === FirstRunSetup::STATION,
            'step' => 1,
        ]);
    }

    public function storeStation(Request $request): RedirectResponse
    {
        if ($redirect = $this->guard(FirstRunSetup::STATION)) {
            return $redirect;
        }

        $validated = $request->validate(self::stationRules(), self::stationMessages());

        Setting::setValue('station.name', trim($validated['name']), 'string', 'station');
        Setting::setValue('station.location', trim((string) ($validated['location'] ?? '')), 'string', 'station');
        Setting::setValue('station.latitude', (string) $validated['latitude'], 'float', 'station');
        Setting::setValue('station.longitude', (string) $validated['longitude'], 'float', 'station');
        Setting::setValue('station.elevation', (string) ($validated['elevation'] ?? 0), 'float', 'station');
        Setting::setValue('station.timezone', $validated['timezone'], 'string', 'station');
        Setting::setValue('station.server_url', rtrim(trim((string) ($validated['server_url'] ?? '')), '/'), 'string', 'station');

        FirstRunSetup::moveTo(FirstRunSetup::SOURCE);

        return redirect()->route('admin.setup.source');
    }

    public function source(): View|RedirectResponse
    {
        if ($redirect = $this->guard(FirstRunSetup::SOURCE)) {
            return $redirect;
        }

        return view('admin.setup.source', [
            'formats' => self::formatOptions(),
            'current' => (string) Setting::getValue('livedata.format', ''),
            'manufacturers' => self::manufacturerOptions(),
            'manufacturer' => (string) Setting::getValue('station.manufacturer', ''),
            'hardware' => (string) Setting::getValue('station.hardware', ''),
            'manufacturerForFormat' => self::MANUFACTURER_FOR_FORMAT,
            'step' => 2,
        ]);
    }

    public function storeSource(Request $request): RedirectResponse
    {
        if ($redirect = $this->guard(FirstRunSetup::SOURCE)) {
            return $redirect;
        }

        $validated = $request->validate([
            'format' => ['required', 'string', Rule::in(array_keys(self::formatOptions()))],
            'manufacturer' => ['nullable', 'string', Rule::in(array_keys(self::manufacturerOptions()))],
            'hardware' => ['nullable', 'string', 'max:255'],
        ]);

        Setting::setValue('livedata.format', $validated['format'], 'select', 'livedata');
        Setting::setValue('station.manufacturer', (string) ($validated['manufacturer'] ?? ''), 'select', 'station');
        Setting::setValue('station.hardware', trim((string) ($validated['hardware'] ?? '')), 'string', 'station');

        FirstRunSetup::moveTo(FirstRunSetup::DONE);

        return redirect()
            ->route('admin.settings.group', self::FORMAT_SETTINGS_GROUP[$validated['format']] ?? 'livedata')
            ->with('success', __('Setup finished. Enter the details for your station below.'));
    }

    /**
     * "I will do this later": stops the redirect, leaves the notice.
     *
     * Guarded like the steps themselves. Without this, posting here was the
     * one way left to move a finished setup backwards and reopen the wizard
     * on an install that was done with it.
     */
    public function skip(): RedirectResponse
    {
        if (!FirstRunSetup::unfinished()) {
            return redirect()->route('admin.dashboard');
        }

        FirstRunSetup::moveTo(FirstRunSetup::SKIPPED);

        return redirect()->route('admin.dashboard');
    }

    /**
     * Which maker each format usually implies, used only to preselect the
     * dropdown. Anyone can change it: plenty of people run Ecowitt hardware
     * through other software, and the other way round.
     */
    private const MANUFACTURER_FOR_FORMAT = [
        'ecoLcl' => 'fineoffset',
        'ecowittAPI' => 'fineoffset',
        'DWL' => 'davis',
        'DWL_v2api' => 'davis',
        'DWL_v2api_demo' => 'davis',
        'weatherlink' => 'davis',
        'AWapi' => 'ambient',
        'wf' => 'weatherflow',
        'netatmo' => 'netatmo',
    ];

    /**
     * The address this site is reachable at, as best the app can tell.
     *
     * This is what the community map links back to, and nobody should have to
     * go and look it up. A stored value wins; otherwise APP_URL, unless that
     * is still the framework's stock localhost, in which case the host the
     * admin is actually browsing is the better guess. Always editable.
     */
    public static function currentSiteAddress(): string
    {
        $stored = trim((string) Setting::getValue('station.server_url', ''));
        if ($stored !== '') {
            return $stored;
        }

        $configured = rtrim(trim((string) config('app.url', '')), '/');
        if ($configured !== '' && $configured !== 'http://localhost') {
            return $configured;
        }

        $fromRequest = rtrim(trim((string) request()->getSchemeAndHttpHost()), '/');

        return $fromRequest !== '' ? $fromRequest : $configured;
    }

    /** @return array<string, string> */
    public static function manufacturerOptions(): array
    {
        return Setting::find('station.manufacturer')?->getOptionsArray() ?? [];
    }

    /**
     * The formats the app supports, read from the setting itself so this list
     * cannot drift away from the one on the live data page.
     *
     * @return array<string, string>
     */
    public static function formatOptions(): array
    {
        return Setting::find('livedata.format')?->getOptionsArray() ?? [];
    }

    /**
     * Shared with the ordinary station settings page, so a coordinate that the
     * wizard refuses cannot be typed in later through the back door. That page
     * names its fields station_latitude and so on, hence the prefix.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function stationRules(string $prefix = ''): array
    {
        return [
            $prefix . 'name' => ['required', 'string', 'max:255'],
            $prefix . 'location' => ['nullable', 'string', 'max:255'],
            $prefix . 'latitude' => ['required', 'numeric', 'between:-90,90'],
            $prefix . 'longitude' => ['required', 'numeric', 'between:-180,180'],
            $prefix . 'elevation' => ['nullable', 'numeric', 'between:-500,9000'],
            $prefix . 'server_url' => ['nullable', 'string', 'url', 'max:255'],
            $prefix . 'timezone' => ['required', 'string', Rule::in(\DateTimeZone::listIdentifiers())],
        ];
    }

    /** @return array<string, string> */
    public static function stationMessages(string $prefix = ''): array
    {
        return [
            $prefix . 'latitude.required' => __('The station needs a latitude. Blank is not the same as unknown: it would be read as zero, which is a place in the Gulf of Guinea.'),
            $prefix . 'latitude.between' => __('Latitude runs from -90 at the south pole to 90 at the north pole.'),
            $prefix . 'longitude.required' => __('The station needs a longitude.'),
            $prefix . 'longitude.between' => __('Longitude runs from -180 to 180.'),
            $prefix . 'timezone.in' => __('Pick a timezone from the list.'),
        ];
    }
}
