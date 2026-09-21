<div class="text-[8px] sm:text-[10px] text-ui-muted line-clamp-2 break-words min-h-[2rem] sm:min-h-0 sm:truncate leading-tight">{{ __('Today') }}</div>
<div class="leading-tight">
    <div class="text-xs sm:text-base font-bold text-data-amber-500 data-value truncate">
        <span class="text-[8px] font-normal opacity-60">↑</span><span x-text="formatTemp(todayHigh)"></span>
    </div>
    <div class="text-[10px] sm:text-xs font-semibold text-data-cyan-500 truncate">
        <span class="text-[8px] font-normal opacity-60">↓</span><span x-text="formatTemp(todayLow)"></span>
    </div>
</div>
