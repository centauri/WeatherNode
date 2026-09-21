import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import fs from 'node:fs';

const source = fs.readFileSync(new URL('../../public/js/public-theme.js', import.meta.url), 'utf8');
const key = 'weathernode.public.color-mode';
const paletteKey = 'weathernode.public.palette';
function browser({ defaultMode = 'dark', stored, osDark = false, blocked = false, publicPage = true, defaultPalette = 'weathernode', defaultBase = defaultPalette, palettes = {}, storedPalette } = {}) {
    const listeners = {};
    const storage = new Map([['theme', 'light']]);
    if (stored) storage.set(key, stored);
    if (storedPalette) storage.set(paletteKey, storedPalette);
    const selects = [{ value: null }, { value: null }];
    const paletteSelects = [{ value: null }, { value: null }];
    const root = {
        dataset: { defaultColorMode: defaultMode, publicTheme: defaultBase }, style: {},
        hasAttribute: () => publicPage,
        toggleAttribute: (_, enabled) => { root.custom = enabled; },
        classList: { toggle: (_, active) => { root.dark = active; } },
    };
    const media = { matches: osDark, addEventListener: (_, fn) => { listeners.os = fn; } };
    const window = { matchMedia: () => media, dispatchEvent: e => { listeners.lastEvent = e; }, addEventListener: (name, fn) => { listeners[name] = fn; } };
    const document = { documentElement: root, getElementById: () => ({textContent:JSON.stringify({defaultPalette,palettes})}), querySelectorAll: selector => selector === '[data-public-theme-select]' ? selects : paletteSelects, querySelector: () => null, addEventListener: (name, fn) => { listeners[name] = fn; } };
    const localStorage = {
        getItem: name => { if (blocked) throw new Error('denied'); return storage.get(name) ?? null; },
        setItem: (name, value) => { if (blocked) throw new Error('denied'); storage.set(name, value); },
        removeItem: name => { if (blocked) throw new Error('denied'); storage.delete(name); },
    };
    vm.runInNewContext(source, { document, window, localStorage, CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } } });
    return { root, storage, selects, paletteSelects, listeners,
        change: value => listeners.change({ target: { matches: selector => selector === '[data-public-theme-select]', value } }),
        palette: value => listeners.change({ target: { matches: selector => selector === '[data-public-palette-select]', value } }),
        os: dark => { media.matches = dark; listeners.os(); },
    };
}

test('default dark is applied before DOM ready, separate from admin preference', () => {
    const b = browser(); assert.equal(b.root.dark, true); assert.equal(b.root.dataset.colorMode, 'dark');
    assert.equal(b.storage.get('theme'), 'light');
});
test('owner default, visitor override, reset and responsive selectors agree', () => {
    const b = browser({ defaultMode: 'light', stored: 'dark' });
    assert.equal(b.root.dark, true);
    b.change('light'); assert.equal(b.storage.get(key), 'light'); assert.equal(b.root.dark, false);
    b.change('default'); assert.equal(b.storage.has(key), false); assert.equal(b.root.dark, false);
    assert.ok(b.selects.every(s => s.value === 'default'));
});
test('system follows OS only while selected, including owner system default', () => {
    const b = browser({ defaultMode: 'system' });
    b.os(true); assert.equal(b.root.dark, true);
    b.change('light'); b.os(true); assert.equal(b.root.dark, false);
    b.change('system'); assert.equal(b.root.dark, true);
    b.os(false); assert.equal(b.root.dark, false);
});
test('blocked storage still allows mode changes on the current page', () => {
    const b = browser({ blocked: true }); b.change('light'); assert.equal(b.root.dark, false);
    b.change('default'); assert.equal(b.root.dark, true);
});
test('invalid preferences fall back; other tabs can reset the preference', () => {
    const b = browser({ stored: 'invalid', defaultMode: 'light' }); assert.equal(b.root.dark, false);
    b.storage.set(key, 'dark'); b.listeners.storage({ key }); assert.equal(b.root.dark, true);
    b.storage.delete(key); b.listeners.storage({ key: null }); assert.equal(b.root.dark, false);
});
test('does not run on the admin interface', () => {
    const b = browser({ publicPage: false }); assert.equal(b.root.dark, undefined); assert.equal(b.listeners.change, undefined);
});


test('visitor palettes apply before paint and sync both selectors without changing colour mode', () => {
    const b = browser({ palettes: {ocean:'ocean',forest:'forest'}, storedPalette:'ocean', stored:'light' });
    assert.equal(b.root.dataset.publicTheme,'ocean');assert.equal(b.root.dark,false);
    b.palette('forest');assert.equal(b.storage.get(paletteKey),'forest');assert.equal(b.root.dataset.publicTheme,'forest');
    assert.ok(b.paletteSelects.every(s=>s.value==='forest'));assert.equal(b.storage.get(key),'light');
    assert.equal(b.listeners.lastEvent.detail.palette,'forest');
    b.palette('default');assert.equal(b.root.dataset.publicTheme,'weathernode');assert.equal(b.storage.has(paletteKey),false);
});
test('unapproved, removed and disabled palettes fall back and clear the stale preference', () => {
    for (const palettes of [{ocean:'ocean'},{}]) {
        const b=browser({defaultPalette:'forest',palettes,storedPalette:'solar-flare'});
        assert.equal(b.root.dataset.publicTheme,'forest');assert.equal(b.storage.has(paletteKey),false);
        b.palette('custom');assert.equal(b.root.dataset.publicTheme,'forest');
    }
});
test('custom CSS is active only for the custom palette, including a custom station default', () => {
    const b=browser({defaultPalette:'custom',defaultBase:'ocean',palettes:{weathernode:'weathernode',custom:'ocean'}});
    assert.equal(b.root.custom,true);b.palette('weathernode');assert.equal(b.root.custom,false);
    b.palette('custom');assert.equal(b.root.custom,true);assert.equal(b.root.dataset.publicTheme,'ocean');
    b.palette('default');assert.equal(b.root.custom,true);assert.equal(b.root.dataset.publicTheme,'ocean');
});
test('palette preferences survive blocked storage in the page and follow storage changes in other tabs', () => {
    const b=browser({blocked:true,palettes:{forest:'forest'}});b.palette('forest');assert.equal(b.root.dataset.publicTheme,'forest');
    const shared=browser({palettes:{forest:'forest'}});shared.storage.set(paletteKey,'forest');shared.listeners.storage({key:paletteKey});
    assert.equal(shared.root.dataset.publicTheme,'forest');shared.storage.delete(paletteKey);shared.listeners.storage({key:null});
    assert.equal(shared.root.dataset.publicTheme,'weathernode');assert.equal(shared.storage.get('theme'),'light');
});

test('an older tab cannot erase a newly approved palette selected in a newer tab', () => {
    const older=browser({palettes:{ocean:'ocean'}});
    older.storage.set(paletteKey,'solar-flare');older.listeners.storage({key:paletteKey});
    assert.equal(older.root.dataset.publicTheme,'weathernode');
    assert.equal(older.storage.get(paletteKey),'solar-flare');
});
