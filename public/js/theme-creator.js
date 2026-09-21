import {MAX_THEME_BYTES, validTheme, contrastIssues} from './theme-document.js';
const config = JSON.parse(document.getElementById('theme-creator-config').textContent);
const form = document.getElementById('creator-form');
const name = document.getElementById('theme-name');
const frame = document.getElementById('theme-preview');
const modeSelect = document.getElementById('edit-mode');
const effects = document.getElementById('preview-effects');
// Match the visitor's FX toggle unless the station uses Flat. Never change that preference here.
try {
    if (effects.value === 'fx' && localStorage.getItem('backgroundEffectsEnabled') === 'false') effects.value = 'fx-off';
} catch { /* Preview remains usable when storage is unavailable. */ }
const status = document.getElementById('creator-status');
const error = document.getElementById('creator-error');
const file = document.getElementById('theme-file');
const tokens = Object.keys(config.tokens);
// Long translated titles can make the fixed mobile admin header taller.
const header = document.getElementById('mobile-header');
const main = document.getElementById('main-content');
function fitHeader() {
    if (innerWidth < 768) main.style.setProperty('padding-top', `${header.getBoundingClientRect().height + 16}px`, 'important');
    else main.style.removeProperty('padding-top');
}
new ResizeObserver(fitHeader).observe(header);
window.addEventListener('resize', fitHeader);
fitHeader();
const t = key => config.messages[key] || config.tokens[key] || key;
let draft = structuredClone(config.draft), mode = 'dark', dirty = false;
const announce = message => { status.textContent = message; status.hidden = !message; error.hidden = true; };
const fail = message => { error.textContent = message; error.hidden = false; };
function update(changed = false) {
    draft.name = name.value;
    if (changed) { dirty = true; announce(t('Unsaved changes')); }
    document.getElementById('theme-document').value = JSON.stringify(draft);
    frame.contentWindow?.postMessage({type:'weathernode:theme-preview', theme:draft, mode, effects:effects.value}, location.origin);
    const list = document.getElementById('contrast-results');
    list.replaceChildren();
    const issues = contrastIssues(draft);
    for (const issue of issues) {
        const item = document.createElement('li');
        item.className = 'text-amber-300';
        item.textContent = `${t('Low contrast')} · ${t(issue.mode === 'dark' ? 'Dark mode' : 'Light mode')} · ${issue.text === 'white' ? t('White button text') : t(issue.text)} / ${t(issue.surface)}: ${issue.ratio.toFixed(2)}:1`;
        list.append(item);
    }
    if (!issues.length) {
        const item = document.createElement('li');item.className = 'text-emerald-300';item.textContent = t('All checked text colours meet 4.5:1.');list.append(item);
    }
}
function showFields() {
    for (const key of tokens) {
        document.querySelector(`[data-colour="${key}"]`).value = draft.modes[mode][key];
        document.querySelector(`[data-hex="${key}"]`).value = draft.modes[mode][key];
    }
}
for (const input of document.querySelectorAll('[data-colour], [data-hex]')) {
    input.addEventListener('input', () => {
        if (!/^#[0-9a-fA-F]{6}$/.test(input.value)) { dirty = true; return; }
        const key = input.dataset.colour || input.dataset.hex;
        draft.modes[mode][key] = input.value.toLowerCase();
        document.querySelector(`[data-colour="${key}"]`).value = input.value;
        document.querySelector(`[data-hex="${key}"]`).value = input.value;
        update(true);
    });
}
name.addEventListener('input', () => update(true));
modeSelect.addEventListener('change', () => {
    if (!form.reportValidity()) { modeSelect.value = mode; return; }
    mode = modeSelect.value;showFields();update();
});
effects.addEventListener('change', () => update());
frame.addEventListener('load', () => update());
document.getElementById('load-preset').addEventListener('click', () => {
    draft = structuredClone(config.presets[document.getElementById('preset').value]);
    name.value = draft.name;showFields();update(true);
});
async function request(url, document) {
    const response = await fetch(url, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN':form.querySelector('[name=_token]').value}, body:JSON.stringify({theme:JSON.stringify(document)})});
    if (!response.headers.get('content-type')?.includes('application/json')) throw new Error(t('Unable to complete the request. Please reload and try again.'));
    if (!response.ok) {
        const data = await response.json();
        throw new Error(data.errors?.theme?.[0] || t('Unable to complete the request. Please reload and try again.'));
    }
    return response;
}
document.getElementById('import-theme').addEventListener('click', () => file.click());
file.addEventListener('change', async () => {
    const selected = file.files[0];if (!selected) return;
    try {
        if (selected.size > MAX_THEME_BYTES) throw new Error(t('Theme files must be smaller than 20 KB.'));
        let imported;
        try { imported = JSON.parse(await selected.text()); } catch { throw new Error(t('Invalid theme file. Use a WeatherNode theme file with both colour modes.')); }
        if (!validTheme(imported, tokens)) throw new Error(t('Invalid theme file. Use a WeatherNode theme file with both colour modes.'));
        const result = await (await request(config.importUrl, imported)).json();
        draft = result.theme;name.value = draft.name;document.getElementById('preset').value = draft.base;
        showFields();update(true);announce(t('Theme imported. Review it before saving or applying.'));
    } catch (e) { fail(e.message); }
    finally { file.value = ''; }
});
document.getElementById('export-theme').addEventListener('click', async () => {
    if (!form.reportValidity()) return;
    try {
        const response = await request(config.exportUrl, draft);
        const url = URL.createObjectURL(await response.blob());
        const link = document.createElement('a');link.href = url;link.download = response.headers.get('content-disposition')?.match(/filename="([^"]+)"/)?.[1] || 'weathernode-theme.json';
        link.click();setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (e) { fail(e.message); }
});
form.addEventListener('submit', event => {
    if (!validTheme(draft, tokens)) { event.preventDefault();fail(t('Invalid theme file. Use a WeatherNode theme file with both colour modes.'));return; }
    dirty = false;
});
window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault();event.returnValue = ''; } });
for (const button of form.querySelectorAll('button[type=submit]')) button.disabled = false;
showFields();update();
