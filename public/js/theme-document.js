export const MAX_THEME_BYTES = 20000;
const hex = /^#[0-9a-fA-F]{6}$/;
const exactKeys = (value, keys) => value && typeof value === 'object' && !Array.isArray(value)
    && Object.keys(value).length === keys.length && keys.every(key => Object.hasOwn(value, key));
export function validTheme(theme, tokens) {
    return exactKeys(theme, ['format', 'version', 'name', 'base', 'modes'])
        && theme.format === 'weathernode-theme' && theme.version === 1
        && typeof theme.name === 'string' && theme.name.trim().length > 0 && [...theme.name].length <= 80
        && !/\p{C}/u.test(theme.name)
        && ['weathernode', 'ocean', 'forest', 'solar-flare'].includes(theme.base)
        && exactKeys(theme.modes, ['dark', 'light'])
        && ['dark', 'light'].every(mode => exactKeys(theme.modes[mode], tokens)
            && tokens.every(key => typeof theme.modes[mode][key] === 'string' && hex.test(theme.modes[mode][key])));
}
export const rgb = colour => colour.slice(1).match(/../g).map(value => parseInt(value, 16));
export function contrast(a, b) {
    const luminance = colour => rgb(colour).map(value => value / 255)
        .map(value => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4)
        .reduce((sum, value, index) => sum + value * [0.2126, 0.7152, 0.0722][index], 0);
    const first = luminance(a), second = luminance(b);
    return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05);
}
export function contrastIssues(theme) {
    const issues = [];
    for (const mode of ['dark', 'light']) {
        const colours = theme.modes[mode];
        const pairs = [['fg', 'bg'], ['fg', 'card'], ['fg', 'raised'], ['fg', 'deep'], ['fg', 'soft'],
            ['secondary', 'card'], ['muted', 'card'], ['subtle', 'card'], ['link', 'card'], ['white', 'accent-strong']];
        for (const [text, surface] of pairs) {
            const ratio = contrast(text === 'white' ? '#ffffff' : colours[text], colours[surface]);
            if (ratio < 4.5) issues.push({ mode, text, surface, ratio });
        }
    }
    return issues;
}
export function themeCss(theme) {
    // Call only after schema validation. These aliases keep the custom palette independent of preset compatibility colours.
    const aliases = {body:'fg', faint:'subtle', disabled:'soft', inactive:'soft', divider:'border', 'slate-deep':'deep', 'slate-soft':'soft', 'slate-border':'border', action:'accent', 'action-deep':'accent-strong'};
    return ['dark', 'light'].map(mode => `html[data-public-theme][data-custom-theme][data-color-mode="${mode}"]{--wn-custom:1;`
        + Object.entries(theme.modes[mode]).map(([key, value]) => `--wn-${key}:${rgb(value).join(' ')};`).join('')
        + Object.entries(aliases).map(([key, value]) => `--wn-${key}:var(--wn-${value});`).join('')
        + `--wn-browser-color:${theme.modes[mode].card};}`).join('');
}
