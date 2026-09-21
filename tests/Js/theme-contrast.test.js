import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const css = fs.readFileSync(new URL('../../public/css/public-theme.css', import.meta.url), 'utf8');
function paletteTokens(palette, mode) {
    const tokens = {};
    // Resolve the root token rules in stylesheet order, like the browser cascade.
    for (const match of css.matchAll(/(html\[data-public-theme[^{}]*)\{([^{}]*)\}/g)) {
        const selector = match[1].trim();
        if (selector.includes(' ') || selector.includes(',')) continue;
        const named = selector.match(/data-public-theme="([^"]+)"/);
        const scheme = selector.match(/data-color-mode="([^"]+)"/);
        if ((named && named[1] !== palette) || (scheme && scheme[1] !== mode)) continue;
        for (const token of match[2].matchAll(/--wn-([\w-]+):\s*(\d+ \d+ \d+);/g)) {
            tokens[token[1]] = token[2].split(' ').map(Number);
        }
    }
    return tokens;
}
const luminance = rgb => rgb.map(c => c / 255).map(c => c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4)
    .reduce((sum, c, i) => sum + c * [0.2126, 0.7152, 0.0722][i], 0);
const contrast = (a, b) => (Math.max(luminance(a), luminance(b)) + 0.05) / (Math.min(luminance(a), luminance(b)) + 0.05);
for (const palette of ['weathernode', 'ocean', 'forest', 'solar-flare']) for (const mode of ['dark', 'light']) {
    test(`${palette} ${mode}: primary interface text and filled buttons meet AA text contrast`, () => {
        const tokens = paletteTokens(palette, mode);
        const originalDark = palette === 'weathernode' && mode === 'dark';
        // Preserve the shipped muted/data shades for the original dark preset.
        // New palettes and every light preset meet the stronger contrast target.
        for (const role of ['fg', 'secondary', 'muted', 'link', ...(!originalDark ? ['subtle'] : [])]) {
            assert.ok(contrast(tokens[role], tokens.card) >= 4.5, `${role} on card`);
        }
        assert.ok(contrast([255, 255, 255], tokens['accent-strong']) >= 4.5, 'filled action text');
        if (originalDark) return;
        for (const [name, colour] of Object.entries(tokens).filter(([name]) => name.startsWith('data-'))) {
            assert.ok(contrast(colour, tokens.card) >= 4.5, `${name} data text on card`);
        }
    });
}

// Values from WeatherNode v2026.09.6, before public theming (not a new palette).
test('WeatherNode dark preserves the shipped navy, blue and measurement colours', () => {
    const tokens = paletteTokens('weathernode', 'dark');
    const original = {
        bg: [15, 20, 25], card: [26, 35, 50],
        'gradient-mid': [26, 39, 68], 'gradient-end': [30, 27, 75],
        accent: [59, 130, 246], 'accent-strong': [37, 99, 235],
        muted: [156, 163, 175], subtle: [107, 114, 128],
        body: [229, 231, 235], faint: [75, 85, 99],
        'data-amber-500': [245, 158, 11],
        'data-cyan-500': [6, 182, 212],
        'data-indigo-500': [99, 102, 241],
        'data-green-500': [34, 197, 94],
        'data-red-500': [239, 68, 68],
    };
    for (const [name, value] of Object.entries(original)) assert.deepEqual(tokens[name], value, name);
});

test('Ocean keeps its distinct teal surfaces and accents as an optional palette', () => {
    const tokens = paletteTokens('ocean', 'dark');
    assert.deepEqual(tokens.card, [19, 40, 48]);
    assert.deepEqual(tokens['accent-strong'], [15, 118, 110]);
    assert.notDeepEqual(tokens.card, paletteTokens('weathernode', 'dark').card);
});
