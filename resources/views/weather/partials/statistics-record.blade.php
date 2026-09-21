@php
    $activeUnits = $activeUnits ?? 'metric';
    $top = $record['top'] ?? null;
    $list = $record['list'] ?? [];
    $hasData = $top !== null;

    $formatValue = function ($value) use ($unit, $activeUnits, $format) {
        if ($value === null) return '--';
        return match ($format) {
            'temperature' => $unit->temperature($value, $activeUnits),
            'rain' => $unit->rain($value, $activeUnits),
            'wind' => $unit->wind($value, $activeUnits),
            'pressure' => $unit->pressure($value, $activeUnits),
            'percent' => round($value) . '%',
            'solar' => round($value) . ' W/m²',
            'hours' => round($value, 1) . ' h',
            default => round($value, 1),
        };
    };
@endphp
<div class="space-y-2" x-data="{ expanded: false }">
    <!-- Top Record -->
    <div class="flex justify-between items-center p-3 bg-ui-overlay/5 rounded-lg">
        <div class="flex items-center gap-2">
            <span class="text-ui-muted">{{ $label }}</span>
            @if(count($list) > 1)
                <button @click="expanded = !expanded" class="text-xs text-data-blue-400 hover:text-data-blue-300 transition-colors">
                    <span x-show="!expanded">{{ __('Top :count', ['count' => count($list)]) }}</span>
                    <span x-show="expanded" x-cloak>{{ __('Hide') }}</span>
                </button>
            @endif
        </div>
        <div class="text-right">
            @if($hasData)
                <span class="font-bold {{ $color }}">{{ $formatValue($top['value']) }}</span>
                <span class="text-xs text-ui-subtle ml-2">{{ $top['date'] ?? '' }}</span>
            @else
                <span class="text-ui-subtle">--</span>
            @endif
        </div>
    </div>

    <!-- Expanded Top 5 List -->
    @if(count($list) > 1)
        <div x-show="expanded" x-cloak x-transition class="ml-4 space-y-1">
            @foreach(array_slice($list, 1) as $i => $item)
                <div class="flex justify-between items-center p-2 text-sm bg-ui-overlay/[0.02] rounded">
                    <span class="text-ui-subtle">#{{ $i + 2 }}</span>
                    <div class="text-right">
                        <span class="{{ $color }} opacity-75">{{ $formatValue($item['value']) }}</span>
                        <span class="text-xs text-ui-faint ml-2">{{ $item['date'] ?? '' }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
