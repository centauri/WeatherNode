<div class="text-[8px] sm:text-[10px] text-ui-muted line-clamp-2 break-words min-h-[2rem] sm:min-h-0 sm:truncate leading-tight">{{ __('Earthquakes') }}</div>
<div class="text-xs sm:text-lg font-bold data-value truncate" x-text="earthquakes.length > 0 ? earthquakes.length : '✓'">{{ $ssrEarthquakeCountLabel }}</div>
