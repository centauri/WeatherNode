import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import fs from 'node:fs';

const source = fs.readFileSync(new URL('../../public/js/public-theme.js', import.meta.url), 'utf8');
const key = 'weathernode.public.color-mode';
function browser({ defaultMode = 'dark', stored, osDark = false, blocked = false, publicPage = true } = {}) {
    const listeners = {};
    const storage = new Map([['theme', 'light']]);
    if (stored) storage.set(key, stored);
    const selects = [{ value: null }, { value: null }];
    const root = {
        dataset: { defaultColorMode: defaultMode }, style: {},
        hasAttribute: () => publicPage,
        classList: { toggle: (_, active) => { root.dark = active; } },
    };
    const media = { matches: osDark, addEventListener: (_, fn) => { listeners.os = fn; } };
    const window = { matchMedia: () => media, dispatchEvent: e => { listeners.lastEvent = e; }, addEventListener: (name, fn) => { listeners[name] = fn; } };
    const document = { documentElement: root, querySelectorAll: () => selects, querySelector: () => null, addEventListener: (name, fn) => { listeners[name] = fn; } };
    const localStorage = {
        getItem: name => { if (blocked) throw new Error('denied'); return storage.get(name) ?? null; },
        setItem: (name, value) => { if (blocked) throw new Error('denied'); storage.set(name, value); },
        removeItem: name => { if (blocked) throw new Error('denied'); storage.delete(name); },
    };
    vm.runInNewContext(source, { document, window, localStorage, CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } } });
    return { root, storage, selects, listeners,
        change: value => listeners.change({ target: { matches: () => true, value } }),
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
