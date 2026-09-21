import {validTheme, themeCss} from './theme-document.js';
const tokens = JSON.parse(document.getElementById('preview-tokens').textContent);
window.addEventListener('message', event => {
    if (event.origin !== location.origin || event.source !== window.parent || event.data?.type !== 'weathernode:theme-preview') return;
    const {theme, mode, effects} = event.data;
    if (!validTheme(theme, tokens) || !['dark', 'light'].includes(mode) || !['fx', 'fx-off', 'flat'].includes(effects)) return;
    document.documentElement.dataset.publicTheme = theme.base;
    document.documentElement.dataset.colorMode = mode;
    document.documentElement.classList.toggle('dark', mode === 'dark');
    document.body.classList.toggle('theme-flat', effects === 'flat');
    document.body.classList.toggle('effects-disabled', effects !== 'fx');
    const background = document.getElementById('preview-background');
    background.classList.toggle('weather-bg--animated', effects === 'fx');
    background.classList.toggle('weather-bg--static', effects !== 'fx');
    document.getElementById('preview-colours').textContent = themeCss(theme);
    document.getElementById('preview-name').textContent = theme.name;
});
