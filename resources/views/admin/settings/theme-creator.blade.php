@extends('layouts.admin')
@section('title', __('Theme creator'))
@section('content')
<div class="space-y-6" id="theme-creator">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="text-2xl font-bold text-white">{{ __('Theme creator') }}</h1>
            <p class="text-gray-400 mt-2">{{ __('Create a custom palette. Weather and warning colours keep their meaning.') }}</p></div>
        <a href="{{ route('admin.settings.appearance') }}" class="text-cyan-400 hover:underline">← {{ __('Appearance') }}</a>
    </div>
    @if(session('success'))<p role="status" class="rounded-lg border border-emerald-600 p-4 text-emerald-300">{{ session('success') }}</p>@endif
    @if($errors->any())<div role="alert" class="rounded-lg border border-red-500 p-4 text-red-300">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <p id="creator-status" role="status" aria-live="polite" class="text-cyan-300" hidden></p>
    <p id="creator-error" role="alert" class="text-red-300" hidden></p>
    <noscript><p>{{ __('JavaScript is required for the theme creator.') }}</p></noscript>
    <div class="grid xl:grid-cols-2 gap-6 items-start">
        <form id="creator-form" method="POST" action="{{ route('admin.settings.theme-creator.save') }}" class="space-y-5">
            @csrf
            <textarea name="theme" id="theme-document" hidden></textarea>
            <div class="bg-gray-800/50 border border-gray-700 rounded-xl p-5 space-y-4">
                <div><label for="theme-name" class="block text-white mb-2">{{ __('Theme name') }}</label>
                    <input id="theme-name" maxlength="80" required value="{{ $draft['name'] }}" class="w-full bg-gray-900 border-gray-600 text-white rounded-lg"></div>
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1"><label for="preset" class="block text-white mb-2">{{ __('Start from a preset') }}</label>
                        <select id="preset" class="w-full bg-gray-900 border-gray-600 text-white rounded-lg">@foreach($palettes as $id => $label)<option value="{{ $id }}" @selected($draft['base'] === $id)>{{ __($label) }}</option>@endforeach</select></div>
                    <button type="button" id="load-preset" class="px-4 py-2 rounded-lg bg-gray-700 text-white">{{ __('Load preset') }}</button>
                </div>
                <p class="text-sm text-gray-400">{{ __('Loading a preset or importing a file replaces the draft. Nothing changes on the public site until you apply it.') }}</p>
                <div class="flex flex-wrap gap-3">
                    <button type="button" id="import-theme" class="px-4 py-2 rounded-lg border border-gray-600 text-white">{{ __('Import theme') }}</button>
                    <input id="theme-file" type="file" accept=".json,application/json" class="hidden" tabindex="-1">
                    <button type="button" id="export-theme" class="px-4 py-2 rounded-lg border border-gray-600 text-white">{{ __('Export theme') }}</button>
                </div>
            </div>
            <div class="bg-gray-800/50 border border-gray-700 rounded-xl p-5">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <h2 class="text-lg font-semibold text-white">{{ __('Colours') }}</h2>
                    <select id="edit-mode" aria-label="{{ __('Colour mode') }}" class="bg-gray-900 border-gray-600 text-white rounded-lg"><option value="dark">{{ __('Dark mode') }}</option><option value="light">{{ __('Light mode') }}</option></select>
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach(array_slice($tokens, 0, 12, true) as $key => $label)
                        @include('admin.settings.partials.theme-colour-field')
                    @endforeach
                </div>
                <details class="mt-5"><summary class="text-cyan-400 cursor-pointer">{{ __('More colours') }}</summary>
                    <div class="grid sm:grid-cols-2 gap-4 mt-4">@foreach(array_slice($tokens, 12, null, true) as $key => $label)@include('admin.settings.partials.theme-colour-field')@endforeach</div>
                </details>
            </div>
            <div class="rounded-xl border border-gray-700 p-4" aria-live="polite">
                <h2 class="font-semibold text-white">{{ __('Contrast check') }}</h2>
                <p class="text-sm text-gray-400 mt-1">{{ __('Aim for 4.5:1 for readable text. Warnings do not prevent saving.') }}</p>
                <ul id="contrast-results" class="mt-2 space-y-1 text-sm"></ul>
            </div>
            <p class="text-sm text-gray-400">{{ __('One custom theme is saved on this station. Export it before replacing it if you want to keep a copy.') }}</p>
            <div class="flex flex-wrap gap-3">
                <button type="submit" name="action" value="save" class="px-4 py-2 rounded-lg bg-gray-700 text-white" disabled>{{ __('Save theme') }}</button>
                <button type="submit" name="action" value="apply" class="px-4 py-2 rounded-lg bg-cyan-600 text-white" disabled>{{ __('Save and apply') }}</button>
            </div>
        </form>
        <div class="xl:sticky xl:top-4 space-y-3">
            <div class="flex items-center justify-between gap-3"><h2 class="text-lg font-semibold text-white">{{ __('Live preview') }}</h2>
                <select id="preview-effects" aria-label="{{ __('Visual effects') }}" class="bg-gray-900 border-gray-600 text-white rounded-lg"><option value="fx" @selected(\App\Models\Setting::getValue('appearance.theme', 'fx') !== 'flat')>{{ __('FX (rich)') }}</option><option value="fx-off">{{ __('FX off (static)') }}</option><option value="flat" @selected(\App\Models\Setting::getValue('appearance.theme', 'fx') === 'flat')>{{ __('Flat (simplified)') }}</option></select></div>
            <iframe id="theme-preview" title="{{ __('Live preview') }}" src="{{ route('admin.settings.theme-creator.preview') }}" class="w-full rounded-xl border border-gray-700" style="height: 660px"></iframe>
            <p class="text-sm text-gray-400">{{ __('Compact sample using dashboard styles. Layout, weather data and widget visuals may differ from your site.') }}</p>
        </div>
    </div>
    <form action="{{ route('admin.settings.theme-creator.reset') }}" method="POST" class="border-t border-gray-700 pt-5">
        @csrf
        <button class="text-cyan-400 hover:underline">{{ __('Restore original theme') }}</button>
        <p class="text-sm text-gray-400 mt-2">{{ __('Restores WeatherNode Dark and keeps your saved custom theme and FX preference.') }}</p>
    </form>
</div>
<script type="application/json" id="theme-creator-config">{!! json_encode(['draft' => $draft, 'presets' => $presets, 'tokens' => array_map(fn ($label) => __($label), $tokens), 'importUrl' => route('admin.settings.theme-creator.validate'), 'exportUrl' => route('admin.settings.theme-creator.export'), 'messages' => collect(['Invalid theme file. Use a WeatherNode theme file with both colour modes.', 'Theme files must be smaller than 20 KB.', 'Theme imported. Review it before saving or applying.', 'Unsaved changes', 'Unable to complete the request. Please reload and try again.', 'Theme name', 'All checked text colours meet 4.5:1.', 'Low contrast', 'Dark mode', 'Light mode', 'White button text'])->mapWithKeys(fn ($text) => [$text => __($text)])->all()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection
@push('scripts')
<script type="module" src="{{ asset('js/theme-creator.js') }}?v={{ filemtime(public_path('js/theme-creator.js')) }}"></script>
@endpush
