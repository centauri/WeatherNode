import test from 'node:test';
import assert from 'node:assert/strict';

import {
    AVIATION_SCENE_IDS,
    AVIATION_SCENE_STORAGE_KEY,
    loadAviationScene,
    loadAviationScenePreference,
    normalizeAviationScene,
    resolveAviationScene,
    saveAviationScene,
} from '../../resources/js/pages/aviation-scenes.js';

test('scene ids and fallback stay stable for the Alpine selector', () => {
    assert.deepEqual([...AVIATION_SCENE_IDS], ['village', 'schiphol', 'arctic', 'volcanic', 'spaceport']);
    for (const id of AVIATION_SCENE_IDS) assert.equal(normalizeAviationScene(id), id);
    assert.equal(normalizeAviationScene('cloud-city'), 'schiphol');
    assert.equal(normalizeAviationScene(null), 'schiphol');
});

test('scene persistence uses the public namespaced key', () => {
    const values = new Map();
    const storage = {
        getItem: key => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
    };
    assert.equal(loadAviationScene(storage), 'schiphol');
    assert.equal(saveAviationScene(storage, 'arctic'), 'arctic');
    assert.equal(values.get(AVIATION_SCENE_STORAGE_KEY), 'arctic');
    assert.equal(loadAviationScene(storage), 'arctic');
});

test('blocked, corrupt, or missing storage safely falls back to Schiphol', () => {
    const blocked = {
        getItem() { throw new Error('denied'); },
        setItem() { throw new Error('denied'); },
    };
    assert.equal(loadAviationScene(blocked), 'schiphol');
    assert.equal(saveAviationScene(blocked, 'volcanic'), 'volcanic');
    assert.equal(loadAviationScene({ getItem: () => 'obsolete-scene' }), 'schiphol');
});

test('visitor storage falls back to the saved station default and reset follows it', () => {
    const values = new Map();
    const storage = {
        getItem: key => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
        removeItem: key => values.delete(key),
    };
    assert.equal(loadAviationScene(storage, 'arctic'), 'arctic');
    values.set(AVIATION_SCENE_STORAGE_KEY, 'volcanic');
    assert.equal(loadAviationScene(storage, 'arctic'), 'volcanic');
    values.set(AVIATION_SCENE_STORAGE_KEY, 'obsolete-scene');
    assert.equal(loadAviationScene(storage, 'arctic'), 'arctic');
    assert.equal(saveAviationScene(storage, 'default', 'arctic'), 'arctic');
    assert.equal(values.has(AVIATION_SCENE_STORAGE_KEY), false);
    assert.equal(loadAviationScenePreference(storage), 'default');
    assert.equal(resolveAviationScene('default', 'arctic'), 'arctic');
    assert.equal(resolveAviationScene('volcanic', 'arctic'), 'volcanic');
});

test('spaceport is a drawable scene and saved scene choices remain intact', async () => {
    const { drawAviationScene } = await import('../../resources/js/pages/aviation-scenes.js');
    const ctx = new Proxy({}, { get: (target, key) => target[key] ?? (() => {}) });
    assert.equal(drawAviationScene(ctx, 'spaceport', { width: 640, height: 180 }), true);
    assert.equal(normalizeAviationScene('village'), 'village');
    assert.equal(normalizeAviationScene('arctic'), 'arctic');
    assert.equal(normalizeAviationScene('volcanic'), 'volcanic');
});

// Exercise the drawing geometry rather than just checking named scene options.
test('windsock droops in calm air, lifts with wind and remains visible at zero knots', async () => {
    const { drawWindsock } = await import('../../resources/js/pages/aviation-scenes.js');
    const ctx = new Proxy({}, { get: (target, key) => target[key] ?? (() => {}) });
    const calm = drawWindsock(ctx, 100, 200, 0, 0, false);
    const breeze = drawWindsock(ctx, 100, 200, 7.5, 0, false);
    const strong = drawWindsock(ctx, 100, 200, 15, 0, false);
    assert.equal(calm.endX, 100);
    assert.ok(calm.endY > calm.topY);
    assert.ok(breeze.endX > calm.endX && breeze.endX < strong.endX);
    assert.ok(breeze.endY > strong.endY && breeze.endY < calm.endY);
    assert.equal(strong.endY, strong.topY);
    for (const sock of [calm, breeze, strong]) {
        assert.equal(sock.segments.length, 5);
        assert.ok(sock.segments.every(s => s.points.flat().every(Number.isFinite)));
    }
});

test('windsock flutters in wind but stays still with motion disabled', async () => {
    const { drawWindsock } = await import('../../resources/js/pages/aviation-scenes.js');
    const ctx = new Proxy({}, { get: (target, key) => target[key] ?? (() => {}) });
    const render = (time, motion) => drawWindsock(ctx, 100, 200, 20, time, motion);
    assert.notDeepEqual(render(0, true).segments, render(1, true).segments);
    assert.deepEqual(render(0, false), render(1, false));
});
