import test from 'node:test';
import assert from 'node:assert/strict';

import {
    AVIATION_SCENE_IDS,
    AVIATION_SCENE_STORAGE_KEY,
    loadAviationScene,
    normalizeAviationScene,
    saveAviationScene,
} from '../../resources/js/pages/aviation-scenes.js';

test('scene ids and fallback stay stable for the Alpine selector', () => {
    assert.deepEqual([...AVIATION_SCENE_IDS], ['village', 'schiphol', 'arctic', 'volcanic']);
    for (const id of AVIATION_SCENE_IDS) assert.equal(normalizeAviationScene(id), id);
    assert.equal(normalizeAviationScene('cloud-city'), 'village');
    assert.equal(normalizeAviationScene(null), 'village');
});

test('scene persistence uses the public namespaced key', () => {
    const values = new Map();
    const storage = {
        getItem: key => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
    };
    assert.equal(loadAviationScene(storage), 'village');
    assert.equal(saveAviationScene(storage, 'arctic'), 'arctic');
    assert.equal(values.get(AVIATION_SCENE_STORAGE_KEY), 'arctic');
    assert.equal(loadAviationScene(storage), 'arctic');
});

test('blocked or corrupt storage safely falls back to village', () => {
    const blocked = {
        getItem() { throw new Error('denied'); },
        setItem() { throw new Error('denied'); },
    };
    assert.equal(loadAviationScene(blocked), 'village');
    assert.equal(saveAviationScene(blocked, 'volcanic'), 'volcanic');
    assert.equal(loadAviationScene({ getItem: () => 'obsolete-scene' }), 'village');
});
