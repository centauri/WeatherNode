{{-- Blocking, small local assets apply the visitor preference before the first paint.
     Independent of Vite so error pages also work when the build is unavailable. --}}
<link rel="stylesheet" href="{{ asset('css/public-theme.css') }}?v={{ filemtime(public_path('css/public-theme.css')) }}">
@php
    $appearance = \App\Support\PublicAppearance::settings();
    $visitorPalettes = \App\Support\PublicAppearance::visitorPalettes();
    $activeCustomTheme = $appearance['custom'] ?? (isset($visitorPalettes['custom']) ? \App\Support\CustomTheme::stored(true) : null);
    $paletteBases = [];
    foreach ($visitorPalettes as $id => $label) {
        $paletteBases[$id] = $id === 'custom' ? $activeCustomTheme['base'] : $id;
    }
@endphp
@if($activeCustomTheme)
    <style id="public-custom-theme">{!! \App\Support\CustomTheme::css($activeCustomTheme) !!}</style>
@endif
<script type="application/json" id="public-theme-config">{!! json_encode(['defaultPalette' => isset($appearance['custom']) ? 'custom' : $appearance['palette'], 'palettes' => (object) $paletteBases], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('js/public-theme.js') }}?v={{ filemtime(public_path('js/public-theme.js')) }}"></script>
