{{-- ── Official / external alerts (Meteoalarm etc.) — always visible ──── --}}
<div class="mb-6">
    <h2 class="text-xs font-semibold uppercase tracking-widest text-ui-subtle mb-3">
        {{ __('Official Alerts') }}
    </h2>
    @if(count($externalAlerts) > 0)
        @foreach($externalAlerts as $alert)
            @php
                $color  = $alert['severity_color'] ?? '#FBEA55';
                $bg     = $color . '12';
                $border = $color . '50';
                $tagBg  = $color . '25';
            @endphp
            <article class="rounded-xl border p-4 mb-3"
                     style="border-color: {{ $border }}; background: {{ $bg }}">
                <div class="flex flex-wrap gap-2 items-center mb-2">
                    <span data-weather-colour-text class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                          style="background: {{ $tagBg }}; color: {{ $color }}">
                        {{ $alert['source_label'] ?? 'Meteoalarm' }}
                    </span>
                    <span class="text-xs font-medium text-ui-secondary">
                        {{ $alert['warning_type_label'] ?? $alert['warning_type'] ?? '' }}
                    </span>
                    <span class="ml-auto w-2.5 h-2.5 rounded-full flex-shrink-0 animate-pulse"
                          style="background: {{ $color }}"></span>
                </div>
                <h2 class="font-semibold text-ui-fg text-sm mb-1">{{ $alert['title'] }}</h2>
                <p class="text-sm text-ui-muted leading-relaxed">{{ $alert['description'] }}</p>
                @if (!empty($alert['link']))
                    <a href="{{ $alert['link'] }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-1 mt-3 text-xs text-ui-subtle hover:text-ui-fg transition-colors">
                        ↗ {{ __('More info') }}
                    </a>
                @endif
            </article>
        @endforeach
    @else
        <div class="bg-weather-card rounded-xl border border-ui-line/10 px-4 py-3 flex items-center gap-3">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 flex-shrink-0"></span>
            <span class="text-sm text-ui-muted">{{ __('No active official alerts') }}</span>
        </div>
    @endif
</div>

{{-- ── Local status sections (always visible) ─────────────────────────── --}}
<div>
    <h2 class="text-xs font-semibold uppercase tracking-widest text-ui-subtle mb-3">
        {{ __('Current Status') }}
    </h2>

    @if(empty($statusSections))
        <div class="bg-weather-card rounded-xl border border-ui-line/10 px-4 py-6 text-center text-sm text-ui-subtle">
            {{ __('No sensor data available yet. Data is polled every few minutes.') }}
        </div>
    @else
        <div class="bg-weather-card rounded-xl border border-ui-line/10 overflow-hidden">
            @foreach($statusSections as $section)
                @php
                    $isSub    = $section['sub'] ?? false;
                    $rowClass = 'flex items-center gap-3 py-2.5 '
                        . ($isSub ? 'pl-8 pr-4 ' : 'px-4 ')
                        . ($loop->last ? '' : 'border-b border-ui-line/5 ')
                        . ($section['severity'] > 0 ? 'bg-ui-overlay/[0.03] ' : '')
                        . ($section['link'] ? 'hover:bg-ui-overlay/5 transition-colors' : '');
                @endphp
                @if($section['link'] ?? null)
                    <a href="{{ $section['link'] }}" class="{{ $rowClass }}">
                @else
                    <div class="{{ $rowClass }}">
                @endif
                    @if($isSub)
                        {{-- Sub-row indent guide --}}
                        <span class="w-px h-4 bg-ui-overlay/20 flex-shrink-0 -ml-4 mr-1"></span>
                    @endif
                    {{-- Status dot --}}
                    <span class="rounded-full flex-shrink-0 {{ $isSub ? 'w-2 h-2' : 'w-2.5 h-2.5' }} {{ $section['severity'] > 0 ? 'animate-pulse' : '' }}"
                          style="background: {{ $section['color'] }}"></span>
                    {{-- Category / station label --}}
                    <span class="{{ $isSub ? 'text-xs text-ui-secondary' : 'text-sm text-ui-fg' }} flex-1 leading-tight">{{ $section['label'] }}</span>
                    {{-- Status label --}}
                    <span data-weather-colour-text class="{{ $isSub ? 'text-[11px]' : 'text-xs' }} font-medium" style="color: {{ $section['color'] }}">
                        {{ $section['status_label'] }}
                    </span>
                    {{-- Current value --}}
                    <span class="{{ $isSub ? 'text-[11px]' : 'text-xs' }} text-ui-subtle min-w-[80px] text-right tabular-nums">
                        {{ $section['value'] }}
                    </span>
                @if($section['link'] ?? null)
                    </a>
                @else
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>

{{-- Empty state when no status sections are available --}}
@if(empty($statusSections))
    <div class="text-center py-8 mt-2">
        <div class="text-data-green-400 text-3xl mb-2">✓</div>
        <p class="text-data-green-400 font-medium text-sm">{{ __('No active alerts') }}</p>
        @if($regionName)
            <p class="text-xs text-ui-subtle mt-1">{{ $regionName }}</p>
        @endif
    </div>
@endif
