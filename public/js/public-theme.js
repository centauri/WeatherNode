/* Public preferences only: deliberately independent of the admin's "theme" key. */
(() => {
    'use strict';
    const root = document.documentElement;
    if (!root.hasAttribute('data-public-theme')) return;
    const key = 'weathernode.public.color-mode';
    const paletteKey = 'weathernode.public.palette';
    const modes = ['light', 'dark', 'system'];
    const defaultMode = modes.includes(root.dataset.defaultColorMode) ? root.dataset.defaultColorMode : 'dark';
    const defaultBase = root.dataset.publicTheme;
    let config = {};
    try { config = JSON.parse(document.getElementById('public-theme-config')?.textContent || '{}'); } catch { /* Older cached pages have no palette configuration. */ }
    const defaultPalette = config.defaultPalette || defaultBase;
    const palettes = config.palettes || {};
    const permitted = value => Object.hasOwn(palettes, value);
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    const read = name => { try { return localStorage.getItem(name); } catch { return null; } };
    const persist = (name, value) => {
        try {
            if (value === 'default') localStorage.removeItem(name);
            else localStorage.setItem(name, value);
        } catch { /* Denied storage still allows changes in the current page. */ }
    };
    const readPalette = (clearInvalid = false) => {
        const value = read(paletteKey);
        if (permitted(value)) return value;
        // Clear revoked choices on page load. An older tab must not erase a newly offered choice.
        if (clearInvalid && value !== null) persist(paletteKey, 'default');
        return 'default';
    };
    let preference = modes.includes(read(key)) ? read(key) : 'default';
    let palettePreference = readPalette(true);

    function apply() {
        const mode = preference === 'default' ? defaultMode : preference;
        const resolved = mode === 'system' ? (system.matches ? 'dark' : 'light') : mode;
        const palette = palettePreference === 'default' ? defaultPalette : palettePreference;
        root.dataset.publicTheme = palettePreference === 'default' ? defaultBase : palettes[palette];
        root.toggleAttribute('data-custom-theme', palette === 'custom');
        root.dataset.colorMode = resolved;
        root.classList.toggle('dark', resolved === 'dark');
        root.style.colorScheme = resolved;
        document.querySelectorAll('[data-public-theme-select]').forEach(select => { select.value = preference; });
        document.querySelectorAll('[data-public-palette-select]').forEach(select => { select.value = palettePreference; });
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.content = getComputedStyle(root).getPropertyValue('--wn-browser-color').trim();
        window.dispatchEvent(new CustomEvent('weathernode:theme-change', { detail: { mode: resolved, preference, palette, palettePreference } }));
    }
    apply();
    document.addEventListener('DOMContentLoaded', apply, { once: true });
    document.addEventListener('change', event => {
        if (event.target.matches('[data-public-theme-select]')) {
            preference = modes.includes(event.target.value) ? event.target.value : 'default';
            persist(key, preference);
        } else if (event.target.matches('[data-public-palette-select]')) {
            palettePreference = permitted(event.target.value) ? event.target.value : 'default';
            persist(paletteKey, palettePreference);
        } else return;
        apply();
    });
    system.addEventListener('change', () => {
        if (preference === 'system' || (preference === 'default' && defaultMode === 'system')) apply();
    });
    window.addEventListener('storage', event => {
        if (![key, paletteKey, null].includes(event.key)) return;
        preference = modes.includes(read(key)) ? read(key) : 'default';
        palettePreference = readPalette();
        apply();
    });
})();
