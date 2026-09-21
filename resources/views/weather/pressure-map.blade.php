@extends('weather.layout')

@section('title', __('Pressure Map') . ' - ' . \App\Models\Setting::stationName())
@section('meta_description', __('Pressure map page meta description', ['location' => \App\Models\Setting::stationLocation() ?: \App\Models\Setting::stationName()]))

@section('content')
<div class="max-w-6xl mx-auto space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold">{{ __('Pressure Map') }}</h1>
            <p class="text-ui-muted">{{ __('Pressure map page intro', ['location' => \App\Models\Setting::stationLocation() ?: \App\Models\Setting::stationName()]) }}</p>
            <p class="text-sm text-ui-subtle mt-1">{{ __('Source') }}: NOAA/NWS</p>
        </div>
    </div>

    <div class="bg-weather-card rounded-2xl border border-ui-line/10 overflow-hidden">
        <div class="px-4 py-3 border-b border-ui-line/10">
            <label for="mapSelect" class="sr-only">{{ __('Pressure Map') }}</label>
            <select id="mapSelect"
                    onchange="changeMap(this.value)"
                    class="w-full md:w-auto text-sm px-3 py-2 rounded-lg border border-ui-line/20 bg-ui-overlay/10 hover:bg-ui-overlay/20 transition focus:outline-none focus:ring-2 focus:ring-blue-500/60">
                @foreach ($mapLabels as $name => $label)
                    <option value="{{ $name }}" class="bg-weather-card">{{ __($label) }}</option>
                @endforeach
            </select>
        </div>

        <div data-theme-surface="dark" class="relative bg-black/20" style="height: clamp(360px, 66vh, 740px); height: clamp(360px, 66dvh, 740px);">
            <div class="loading absolute inset-0 flex items-center justify-center text-ui-fg text-lg">{{ __('Loading') }}...</div>
            <img id="pressureMapImage"
                 class="absolute inset-0 w-full h-full object-contain"
                 src=""
                 alt="{{ __('Pressure Map') }}"
                 style="display: none;"
                 onload="this.style.display='block'; document.querySelector('.loading').style.display='none';"
                 onerror="this.style.display='none'; document.querySelector('.loading').textContent='{{ __('Failed to load map') }}';">
        </div>
    </div>

    <article class="bg-weather-card rounded-2xl p-6 border border-ui-line/10 prose prose-invert prose-sm max-w-none">
        <h2 class="text-lg font-semibold mb-3">{{ __('Pressure map page about heading') }}</h2>
        <p class="text-ui-secondary mb-3">{{ __('Pressure map page about body 1') }}</p>
        <p class="text-ui-secondary mb-3">{{ __('Pressure map page about body 2') }}</p>
        <p class="text-ui-secondary mb-3">{{ __('Pressure map page about body 3') }}</p>
        <footer class="text-xs text-ui-subtle mt-4 pt-4 border-t border-ui-line/10">{{ __('Pressure map page sources') }}</footer>
    </article>
</div>

<script>
        // Served through this app rather than hotlinked: the charts are fetched
        // once per refresh window, downscaled, and cached on disk.
        const maps = @json($mapUrls);
        const mapOrder = @json($mapOrder);

        let currentMap = @json($defaultMap ?? 'atlantic');

        function setActiveButton(mapType) {
            document.getElementById('mapSelect').value = mapType;
        }

        function showMap(mapType, attempted) {
            currentMap = mapType;
            setActiveButton(mapType);

            const img = document.getElementById('pressureMapImage');
            const loading = document.querySelector('.loading');
            img.style.display = 'none';
            loading.style.display = 'block';
            loading.textContent = attempted.length
                ? '{{ __('Loading') }}... (' + mapType + ')'
                : '{{ __('Loading') }}...';

            img.onerror = function() {
                // Walk the whole list. Handing over to the next chart used to clear
                // this handler, so two charts down at once left the page loading.
                const next = mapOrder.find(m => m !== mapType && attempted.indexOf(m) === -1);
                if (!next) {
                    loading.textContent = '{{ __('Failed to load map') }}';
                    img.style.display = 'none';
                    return;
                }
                showMap(next, attempted.concat(mapType));
            };
            img.onload = function() {
                img.style.display = 'block';
                loading.style.display = 'none';
            };
            img.src = maps[mapType] + '?_' + Date.now();
        }

        function changeMap(mapType) {
            showMap(mapType, []);
        }

        // Load initial map (from server: station location or ?map=)
        changeMap(currentMap);

        // Refresh every 15 minutes, in place: a failed refresh keeps the chart
        // already on screen instead of blanking it or switching maps.
        setInterval(() => {
            const refreshed = new Image();
            refreshed.onload = function() {
                document.getElementById('pressureMapImage').src = refreshed.src;
            };
            refreshed.src = maps[currentMap] + '?_' + Date.now();
        }, 15 * 60 * 1000);
</script>
@endsection
