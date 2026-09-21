{{--
    Quick Stats Bar.

    Tiles come from App\Support\StatTileRegistry: enabled set from
    `widgets.stats_enabled`, order from `widgets.layout.stat_order`.

    Every tile whose navigation feature is on is emitted, including disabled
    ones — those render hidden so a dashboard left open picks the change up from
    its next payload poll instead of needing a reload. Tiles gated off by
    MenuFeatureMap are not emitted at all.

    Inherits the $ssr* values from the parent view.
--}}
@php
    $statTileIds = \App\Support\StatTileRegistry::renderableIds();
    $enabledStatTileIds = \App\Support\StatTileRegistry::enabledIds();
@endphp

@if(count($statTileIds) > 0)
    <div id="sortable-stats" class="quick-stats-bar mb-4">
        @foreach($statTileIds as $statTileId)
            <div class="stat-tile sortable-stat bg-weather-card card-3d rounded-lg p-1.5 sm:p-3 text-center border border-ui-line/5"
                 data-stat="{{ $statTileId }}"
                 x-show="isStatTileEnabled('{{ $statTileId }}')"
                 @unless(in_array($statTileId, $enabledStatTileIds, true)) style="display: none" @endunless
                 @mouseenter="tiltCard($event)" @mouseleave="resetCard($event)" @mousemove="tiltCard($event)">
                @include('weather.partials.stats.' . $statTileId)
            </div>
        @endforeach
    </div>
@endif
