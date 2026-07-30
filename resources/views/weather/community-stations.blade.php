@extends('weather.layout')

@section('title', __('Community Stations') . ' - ' . \App\Models\Setting::stationName())
@section('og_image', route('og.generic', ['page' => 'community']))

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<style>
    #map {
        height: 600px;
        width: 100%;
        border-radius: 1rem;
        z-index: 1;
    }
    .leaflet-popup-content {
        margin: 12px;
    }
    .station-popup {
        min-width: 200px;
    }
    .station-popup h3 {
        font-weight: 600;
        margin-bottom: 8px;
        color: #1f2937;
    }
    .station-popup p {
        margin: 4px 0;
        font-size: 0.875rem;
        color: #4b5563;
    }
    .station-popup a {
        color: #3b82f6;
        text-decoration: none;
    }
    .station-popup a:hover {
        text-decoration: underline;
    }
</style>
@endpush

@section('content')
<div class="space-y-6" x-data="communityStations()">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold">{{ __('Community Stations') }}</h1>
            <p class="text-gray-400">{{ __('Discover weather stations around the world') }}</p>
        </div>
        @if($lastUpdated)
        <div class="text-sm text-gray-400">
            {{ __('Last updated') }}: {{ \Carbon\Carbon::parse($lastUpdated)->format('Y-m-d H:i') }}
        </div>
        @endif
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-gradient-to-br from-weather-card to-weather-card/50 rounded-xl p-4 border border-white/10">
            <div class="text-2xl font-bold text-blue-400" x-text="stations.length"></div>
            <div class="text-sm text-gray-400">{{ __('Total stations') }}</div>
        </div>
        <div class="bg-gradient-to-br from-weather-card to-weather-card/50 rounded-xl p-4 border border-white/10">
            <div class="text-2xl font-bold text-green-400" x-text="uniqueHardware.length"></div>
            <div class="text-sm text-gray-400">{{ __('Hardware types') }}</div>
        </div>
        <div class="bg-gradient-to-br from-weather-card to-weather-card/50 rounded-xl p-4 border border-white/10">
            <div class="text-2xl font-bold text-purple-400" x-text="uniqueCountries.length"></div>
            <div class="text-sm text-gray-400">{{ __('Countries') }}</div>
        </div>
    </div>

    <!-- Map -->
    <div class="bg-gradient-to-br from-weather-card to-weather-card/50 rounded-xl p-4 border border-white/10">
        <div id="map"></div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-gradient-to-br from-weather-card to-weather-card/50 rounded-xl p-4 border border-white/10">
        <div class="flex flex-col md:flex-row gap-3">
            <!-- Search -->
            <div class="flex-1 relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text"
                       x-model.debounce.300ms="searchQuery"
                       placeholder="{{ __('Search stations...') }}"
                       class="w-full bg-weather-card border border-white/10 rounded-lg pl-10 pr-4 py-2 text-white placeholder-gray-500 text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            <!-- Country Filter -->
            <select x-model="selectedCountry"
                    class="bg-weather-card border border-white/10 rounded-lg px-4 py-2 text-white text-sm">
                <option value="">{{ __('All countries') }}</option>
                <template x-for="cc in uniqueCountries" :key="cc">
                    <option :value="cc" x-text="countryFlag(cc) + ' ' + countryName(cc)"></option>
                </template>
            </select>

            <!-- Hardware Filter -->
            <select x-model="selectedHardware"
                    class="bg-weather-card border border-white/10 rounded-lg px-4 py-2 text-white text-sm">
                <option value="">{{ __('All hardware') }}</option>
                <template x-for="hw in uniqueHardware" :key="hw">
                    <option :value="hw" x-text="hw"></option>
                </template>
            </select>

            <!-- Clear Filters -->
            <button x-show="hasActiveFilters"
                    x-transition
                    @click="clearFilters()"
                    class="text-sm text-gray-400 hover:text-white px-3 py-2 whitespace-nowrap">
                {{ __('Clear filters') }}
            </button>
        </div>

        <!-- Filter result count -->
        <div class="mt-2 text-sm text-gray-500" x-show="hasActiveFilters" x-transition>
            <span x-text="filteredStations.length"></span> {{ __('of') }}
            <span x-text="stations.length"></span> {{ __('stations') }}
        </div>
    </div>

    <!-- Station List (grouped by country) -->
    <div class="bg-gradient-to-br from-weather-card to-weather-card/50 rounded-xl p-6 border border-white/10">
        <h2 class="text-xl font-semibold mb-4">{{ __('All stations') }}</h2>

        <!-- Empty: no stations at all -->
        <template x-if="stations.length === 0">
            <div class="text-center py-8 text-gray-400">
                <p>{{ __('No stations available yet.') }}</p>
                <p class="text-sm mt-2">{{ __('Enable telemetry in admin settings to add your station!') }}</p>
            </div>
        </template>

        <!-- Empty: filters returned nothing -->
        <template x-if="stations.length > 0 && filteredStations.length === 0">
            <div class="text-center py-8 text-gray-400">
                <p>{{ __('No stations match your filters.') }}</p>
                <button @click="clearFilters()" class="text-blue-400 hover:underline text-sm mt-2">
                    {{ __('Clear filters') }}
                </button>
            </div>
        </template>

        <!-- Country groups -->
        <div x-show="filteredStations.length > 0" class="space-y-6">
            <template x-for="[countryCode, stationsInCountry] in groupedStations" :key="countryCode">
                <div>
                    <!-- Country Header -->
                    <div class="flex items-center gap-2 mb-3 pb-2 border-b border-white/10">
                        <span class="text-lg" x-text="countryFlag(countryCode)"></span>
                        <h3 class="font-semibold text-white" x-text="countryName(countryCode)"></h3>
                        <span class="text-xs text-gray-500 bg-white/5 rounded-full px-2 py-0.5"
                              x-text="stationsInCountry.length"></span>
                    </div>

                    <!-- Station Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <template x-for="station in stationsInCountry" :key="station.id">
                            <div class="bg-white/5 rounded-lg p-4 hover:bg-white/10 transition-colors">
                                <h4 class="font-semibold text-white mb-2" x-text="station.name"></h4>
                                <div class="space-y-1 text-sm text-gray-400">
                                    <p x-show="station.hardware">
                                        <span class="text-gray-500">{{ __('Hardware') }}:</span>
                                        <span x-text="station.hardware"></span>
                                    </p>
                                    <p x-show="station.url">
                                        <a :href="station.url" target="_blank" class="text-blue-400 hover:underline">{{ __('Visit station') }} &rarr;</a>
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
const communityI18n = {
    hardware: @json(__('Hardware')),
    manufacturer: @json(__('Manufacturer')),
    visitStation: @json(__('Visit station')),
    updated: @json(__('Updated')),
    unknownCountry: @json(__('Unknown country')),
};

function communityStations() {
    return {
        stations: @json($stations),
        searchQuery: '',
        selectedCountry: '',
        selectedHardware: '',

        get uniqueHardware() {
            return [...new Set(this.stations.map(s => s.hardware).filter(Boolean))].sort();
        },

        get uniqueCountries() {
            return [...new Set(this.stations.map(s => s.country_code).filter(Boolean))].sort();
        },

        get filteredStations() {
            const q = this.searchQuery.toLowerCase();
            return this.stations.filter(s => {
                if (q && !s.name.toLowerCase().includes(q) && !(s.hardware || '').toLowerCase().includes(q)) return false;
                if (this.selectedCountry && s.country_code !== this.selectedCountry) return false;
                if (this.selectedHardware && s.hardware !== this.selectedHardware) return false;
                return true;
            });
        },

        get groupedStations() {
            const groups = {};
            this.filteredStations.forEach(s => {
                const cc = s.country_code || '_unknown';
                if (!groups[cc]) groups[cc] = [];
                groups[cc].push(s);
            });
            return Object.entries(groups).sort(([a], [b]) => {
                if (a === '_unknown') return 1;
                if (b === '_unknown') return -1;
                return this.countryName(a).localeCompare(this.countryName(b));
            });
        },

        get hasActiveFilters() {
            return this.searchQuery || this.selectedCountry || this.selectedHardware;
        },

        clearFilters() {
            this.searchQuery = '';
            this.selectedCountry = '';
            this.selectedHardware = '';
        },

        countryFlag(code) {
            if (!code || code === '_unknown') return '\uD83C\uDF10';
            return code.toUpperCase().replace(/./g, c => String.fromCodePoint(127397 + c.charCodeAt(0)));
        },

        countryName(code) {
            if (!code || code === '_unknown') return communityI18n.unknownCountry;
            try {
                const locale = window.Meteo?.jsLocale || 'en-US';
                return new Intl.DisplayNames([locale], { type: 'region' }).of(code);
            } catch {
                return code;
            }
        },

        init() {
            this.initMap();
        },

        /**
         * Add a random offset within ~100 m to anonymize station locations.
         */
        jitterCoords(lat, lon) {
            const maxOffsetLat = 100 / 111320;
            const cosLat = Math.cos(lat * Math.PI / 180);
            const maxOffsetLon = cosLat > 0 ? 100 / (111320 * cosLat) : maxOffsetLat;
            const angle = Math.random() * 2 * Math.PI;
            const dist  = Math.sqrt(Math.random());
            return [
                lat + dist * maxOffsetLat * Math.cos(angle),
                lon + dist * maxOffsetLon * Math.sin(angle),
            ];
        },

        initMap() {
            const map = L.map('map').setView([20, 0], 2);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);

            let markers = L.markerClusterGroup({
                chunkedLoading: true,
                maxClusterRadius: 50,
            });

            const self = this;

            const buildMarkers = () => {
                markers.clearLayers();
                self.stations.forEach(station => {
                    if (station.latitude && station.longitude) {
                        const [jLat, jLon] = self.jitterCoords(station.latitude, station.longitude);
                        const marker = L.marker([jLat, jLon]);

                        const popupContent = `
                            <div class="station-popup">
                                <h3>${self.escapeHtml(station.name)}</h3>
                                ${station.hardware ? `<p><strong>${communityI18n.hardware}:</strong> ${self.escapeHtml(station.hardware)}</p>` : ''}
                                ${station.manufacturer ? `<p><strong>${communityI18n.manufacturer}:</strong> ${self.escapeHtml(station.manufacturer)}</p>` : ''}
                                ${station.url ? `<p><a href="${self.escapeHtml(station.url)}" target="_blank">${communityI18n.visitStation} &rarr;</a></p>` : ''}
                                ${station.updated_at ? `<p class="text-xs text-gray-500">${communityI18n.updated}: ${new Date(station.updated_at).toLocaleDateString(window.Meteo?.jsLocale || 'en-US')}</p>` : ''}
                            </div>
                        `;

                        marker.bindPopup(popupContent);
                        markers.addLayer(marker);
                    }
                });
            };

            buildMarkers();
            map.addLayer(markers);

            // Re-jitter markers on every zoom change
            map.on('zoomend', () => buildMarkers());

            // Fit bounds to show all markers
            if (this.stations.length > 0) {
                const bounds = markers.getBounds();
                if (bounds.isValid()) {
                    map.fitBounds(bounds, { padding: [50, 50] });
                }
            }
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }
}
</script>
@endpush
@endsection
