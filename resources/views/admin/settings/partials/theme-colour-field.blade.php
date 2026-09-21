<div>
    <label for="colour-{{ $key }}" class="block text-sm text-gray-300 mb-1">{{ __($label) }}</label>
    <div class="flex gap-2">
        <input type="color" id="colour-{{ $key }}" data-colour="{{ $key }}" value="{{ $draft['modes']['dark'][$key] }}" class="h-10 w-12 shrink-0 rounded border border-gray-600 bg-gray-900 cursor-pointer">
        <input type="text" data-hex="{{ $key }}" value="{{ $draft['modes']['dark'][$key] }}" aria-label="{{ __($label) }} (hex)" pattern="#[0-9a-fA-F]{6}" maxlength="7" required spellcheck="false" class="min-w-0 w-full rounded-lg border-gray-600 bg-gray-900 text-white font-mono text-sm">
    </div>
</div>
