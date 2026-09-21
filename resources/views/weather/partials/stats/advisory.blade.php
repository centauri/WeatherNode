<div class="text-[8px] sm:text-[10px] text-ui-muted line-clamp-2 break-words min-h-[2rem] sm:min-h-0 sm:truncate leading-tight">{{ __('Advisory') }}</div>
<div class="text-[10px] sm:text-base font-bold data-value line-clamp-2 break-words leading-tight min-h-[1.5rem] sm:min-h-0 sm:truncate"
     :class="tempAdvisory()?.color ?? 'text-ui-muted'"
     x-text="tempAdvisory()?.label ?? '--'">{{ $ssrTempAdvisoryLabel }}</div>
