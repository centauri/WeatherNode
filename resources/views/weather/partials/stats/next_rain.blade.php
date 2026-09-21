<div class="text-[8px] sm:text-[10px] text-ui-muted line-clamp-2 break-words min-h-[2rem] sm:min-h-0 sm:truncate leading-tight">{{ __('Next Rain') }}</div>
<div class="text-[10px] sm:text-base font-bold data-value line-clamp-2 break-words leading-tight min-h-[1.5rem] sm:min-h-0 sm:truncate"
     :class="nextRainInfo() ? 'text-data-blue-400' : 'text-ui-subtle'"
     x-text="nextRainLabel()">{{ $ssrNextRainLabel }}</div>
