{{-- Shared public header: one stable toolbar, with preferences in a native modal drawer. --}}
<header id="site-header" class="glass border-b border-ui-line/10 sticky top-0 z-50 floating-header"
        google-side-rail-overlap="false" x-data="{ menuOpen: false }">
    <div class="public-header-grid max-w-7xl mx-auto px-4 py-3">
        <a href="{{ route('home') }}" class="public-header-brand flex items-center gap-3 min-w-0">
            <div class="w-8 h-8 shrink-0 bg-gradient-to-br from-ui-accent to-ui-accent-end rounded-lg flex items-center justify-center shadow-lg shadow-ui-accent/30" aria-hidden="true">
                <svg class="w-5 h-5 text-on-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
            </div>
            <div class="min-w-0">
                <h1 class="public-header-title text-lg font-bold" title="{{ \App\Models\Setting::stationName() }}" @if($dashboardHeader) x-text="station.name" @endif>{{ \App\Models\Setting::stationName() }}</h1>
                <p class="text-xs text-ui-muted truncate" title="{{ \App\Models\Setting::stationLocation() }}">{{ \App\Models\Setting::stationLocation() }}</p>
            </div>
        </a>
        <div class="public-header-clock text-xs sm:text-sm text-ui-secondary">
            <span class="live-indicator inline-block w-2 h-2 shrink-0 bg-green-500 rounded-full" aria-hidden="true"></span>
            <span class="font-display tabular-nums whitespace-nowrap" @if($dashboardHeader) x-text="currentTime" @else id="currentTime" @endif>--:--:--</span>
            <span class="text-ui-subtle" aria-hidden="true">|</span>
            <span class="whitespace-nowrap" @if($dashboardHeader) x-text="currentDate" @else id="currentDate" @endif>{{ $ssrDateLabel ?? '--' }}</span>
            <span class="text-ui-subtle text-xs whitespace-nowrap" @if($dashboardHeader) x-show="currentTimeZoneLabel" x-text="'( ' + currentTimeZoneLabel + ' )'" @else id="currentTimeZoneLabel" @endif></span>
        </div>
        <div class="public-header-actions flex items-center gap-2">
            @if($dashboardHeader && auth()->user()?->is_admin)
                <button type="button" x-cloak x-show="editMode" @click="toggleEditMode()" class="px-3 py-2 rounded-lg bg-ui-accent-strong text-on-accent text-sm">{{ __('Done') }}</button>
            @endif
            <button type="button" x-ref="menuButton" data-public-menu-button
                    @click="$refs.settings.showModal(); menuOpen = true"
                    :aria-expanded="menuOpen" aria-expanded="false" aria-controls="public-settings" aria-haspopup="dialog"
                    class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg border border-ui-line/20 bg-ui-overlay/5 hover:bg-ui-overlay/10 text-sm font-medium min-h-11">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <span>{{ __('Menu') }}</span>
            </button>
        </div>
    </div>
    <dialog id="public-settings" x-ref="settings" class="public-settings-dialog" aria-labelledby="public-settings-title"
            @keydown.tab="
                const items = [...$el.querySelectorAll('button:not([disabled]), select:not([disabled]), input:not([disabled]), a[href]')].filter(item => item.getClientRects().length);
                const first = items[0], last = items[items.length - 1];
                if ($event.shiftKey && document.activeElement === first) { $event.preventDefault(); last.focus(); }
                else if (!$event.shiftKey && document.activeElement === last) { $event.preventDefault(); first.focus(); }
            "
            @close="menuOpen = false; $refs.menuButton.focus()"
            @click="if ($event.target === $refs.settings) { const r = $refs.settings.getBoundingClientRect(); if ($event.clientX < r.left || $event.clientX > r.right || $event.clientY < r.top || $event.clientY > r.bottom) $refs.settings.close(); }">
        <div class="flex items-center justify-between gap-3 pb-5 border-b border-ui-line/10">
            <h2 id="public-settings-title" class="text-xl font-bold">{{ __('Settings') }}</h2>
            <button type="button" autofocus @click="$refs.settings.close()" aria-label="{{ __('Close') }}" class="inline-flex items-center justify-center w-11 h-11 rounded-lg hover:bg-ui-overlay/10">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="m6 6 12 12M6 18 18 6"/></svg>
            </button>
        </div>
        <div class="space-y-5 py-5">
            <x-public-theme-select :labelled="true" />
            @if(($siteTheme ?? 'fx') !== 'flat')
                <label class="flex items-center justify-between gap-3 text-sm font-medium text-ui-secondary">
                    <span>{{ __('Visual effects') }}</span>
                    <input type="checkbox" :checked="backgroundEffectsEnabled" @change="toggleBackgroundEffects()" class="w-5 h-5 rounded border-ui-border text-ui-accent-strong" data-public-fx-toggle>
                </label>
            @endif
            <label class="block text-sm font-medium text-ui-secondary">
                <span class="block mb-1.5">{{ __('Language') }}</span>
                <select data-public-language aria-label="{{ __('Language') }}" @change="window.location.assign($event.target.value)">
                    @foreach($localeOptions as $code => $meta)
                        <option value="{{ $dashboardHeader ? request()->fullUrlWithQuery(['lang' => $code]) : localeUrl($code) }}" @selected($activeLocale === $code)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium text-ui-secondary">
                <span class="block mb-1.5">{{ __('Units') }}</span>
                <select data-public-units aria-label="{{ __('Units') }}" @change="window.location.assign($event.target.value)">
                    @foreach($unitOptions as $code => $meta)
                        <option value="{{ request()->fullUrlWithQuery(['units' => $code]) }}" @selected($activeUnits === $code)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        @if(auth()->user()?->is_admin)
            <div class="border-t border-ui-line/10 pt-5 space-y-3">
                @if($dashboardHeader)
                    <button type="button" @click="toggleEditMode(); $refs.settings.close()" class="block w-full text-left px-4 py-3 rounded-lg bg-ui-overlay/5 hover:bg-ui-overlay/10" data-public-edit-button>
                        <span x-show="!editMode">{{ __('Edit') }}</span><span x-cloak x-show="editMode">{{ __('Done') }}</span>
                    </button>
                @endif
                <a href="{{ route('admin.dashboard') }}" class="block px-4 py-3 rounded-lg bg-ui-accent-strong text-on-accent hover:bg-ui-action">{{ __('Admin') }}</a>
            </div>
        @endif
    </dialog>
</header>
