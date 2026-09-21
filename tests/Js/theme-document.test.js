import test from 'node:test';
import assert from 'node:assert/strict';
import {validTheme, contrast, contrastIssues, themeCss} from '../../public/js/theme-document.js';
const tokens = ['bg','card','fg','secondary','muted','subtle','accent','accent-strong','link','border','gradient-mid','gradient-end','raised','deep','soft','overlay','line','grid','accent-end'];
const colours = Object.fromEntries(tokens.map(key => [key, ['bg','card','raised','deep','soft','accent-strong'].includes(key) ? '#000000' : '#ffffff']));
const document = () => ({format:'weathernode-theme',version:1,name:'My theme',base:'weathernode',modes:{dark:{...colours},light:{...colours}}});
test('portable theme schema accepts both modes and rejects executable/unknown data', () => {
    assert.ok(validTheme(document(),tokens));
    for (const mutate of [t=>t.version=2,t=>t.version='1',t=>t.base='custom',t=>t.css='body{}',t=>t.modes.dark.card='url(https://example.com)',t=>delete t.modes.light,t=>t.modes.dark['data-red-500']='#ffffff',t=>t.name='']) {
        const theme=document();mutate(theme);assert.equal(Boolean(validTheme(theme,tokens)),false);
    }
});
test('contrast checks both modes, includes button text, and flags unreadable pairs', () => {
    assert.equal(contrast('#000000','#ffffff'),21);
    assert.deepEqual(contrastIssues(document()),[]);
    const theme=document();theme.modes.light.fg='#000000';theme.modes.dark['accent-strong']='#ffffff';
    const issues=contrastIssues(theme);
    assert.ok(issues.some(i=>i.mode==='light'&&i.text==='fg'&&i.ratio===1));
    assert.ok(issues.some(i=>i.mode==='dark'&&i.text==='white'&&i.ratio===1));
});
test('preview CSS is scoped to public modes and never emits names or weather token overrides', () => {
    const theme=document();theme.name='</style><script>bad</script>';
    const css=themeCss(theme);
    assert.ok(css.includes('html[data-public-theme][data-color-mode="light"]'));
    assert.ok(css.includes('--wn-card:0 0 0;'));
    assert.ok(!css.includes('<script>'));
    assert.ok(!css.includes('--wn-data-'));
});
