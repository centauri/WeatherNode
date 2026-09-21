/* Public preference only: deliberately independent of the admin's "theme" key. */
(() => {
    'use strict';
    const root = document.documentElement;
    if (!root.hasAttribute('data-public-theme')) return;
    const key = 'weathernode.public.color-mode';
    const modes = ['light', 'dark', 'system'];
    const defaultMode = modes.includes(root.dataset.defaultColorMode) ? root.dataset.defaultColorMode : 'dark';
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    const read = () => { try { return localStorage.getItem(key); } catch { return null; } };
    let preference = modes.includes(read()) ? read() : 'default';

    function apply() {
        const mode = preference === 'default' ? defaultMode : preference;
        const resolved = mode === 'system' ? (system.matches ? 'dark' : 'light') : mode;
        root.dataset.colorMode = resolved;
        root.classList.toggle('dark', resolved === 'dark');
        root.style.colorScheme = resolved;
        document.querySelectorAll('[data-public-theme-select]').forEach(select => { select.value = preference; });
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.content = getComputedStyle(root).getPropertyValue('--wn-browser-color').trim();
        window.dispatchEvent(new CustomEvent('weathernode:theme-change', { detail: { mode: resolved, preference } }));
    }
    apply();
    document.addEventListener('DOMContentLoaded', apply, { once: true });
    document.addEventListener('change', event => {
        if (!event.target.matches('[data-public-theme-select]')) return;
        preference = modes.includes(event.target.value) ? event.target.value : 'default';
        try {
            if (preference === 'default') localStorage.removeItem(key);
            else localStorage.setItem(key, preference);
        } catch { /* Private browsing may deny persistence; the current page still changes. */ }
        apply();
    });
    system.addEventListener('change', () => {
        if (preference === 'system' || (preference === 'default' && defaultMode === 'system')) apply();
    });
    window.addEventListener('storage', event => {
        if (event.key !== key && event.key !== null) return;
        preference = modes.includes(read()) ? read() : 'default';
        apply();
    });
})();
