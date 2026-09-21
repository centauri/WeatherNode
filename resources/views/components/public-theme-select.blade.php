@php($visitorPalettes = \App\Support\PublicAppearance::visitorPalettes())
@if($visitorPalettes)
<label class="public-theme-control">
    <span class="sr-only">{{ __('Colour palette') }}</span>
    <select data-public-palette-select aria-label="{{ __('Colour palette') }}" title="{{ __('Colour palette') }}">
        <option value="default">{{ __('Station default') }}</option>
        @foreach($visitorPalettes as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
    </select>
</label>
@endif
<label class="public-theme-control">
    <span class="sr-only">{{ __('Colour mode') }}</span>
    <select data-public-theme-select aria-label="{{ __('Colour mode') }}">
        <option value="default">{{ __('Station default') }}</option>
        <option value="light">{{ __('Light mode') }}</option>
        <option value="dark">{{ __('Dark mode') }}</option>
        <option value="system">{{ __('System') }}</option>
    </select>
</label>
