@props(['icon' => 'battery', 'percentage' => 100])
{{-- The same icons as the dashboard battery card: a battery whose bar follows
     the charge, the schematic capacitor symbol, or a plug for mains power.
     Colour comes from the parent's text colour. --}}
@if($icon === 'capacitor')
    <svg data-battery-icon="capacitor" {{ $attributes->merge(['class' => 'w-5 h-5']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
        <path d="M2 12h7M15 12h7M9 5v14M15 5v14"/>
    </svg>
@elseif($icon === 'mains')
    <svg data-battery-icon="mains" {{ $attributes->merge(['class' => 'w-5 h-5']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M9 3v5M15 3v5M6 8h12v3a6 6 0 0 1-12 0V8zM12 17v4"/>
    </svg>
@else
    <svg data-battery-icon="battery" {{ $attributes->merge(['class' => 'w-5 h-5']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true">
        <rect x="2" y="7" width="17" height="10" rx="2"/>
        <path d="M21 10.5v3" stroke-linecap="round"/>
        <rect x="4.5" y="9.5" height="5" rx="0.5" fill="currentColor" stroke="none" width="{{ max(1.5, min(12, ((int) $percentage / 100) * 12)) }}"/>
    </svg>
@endif
