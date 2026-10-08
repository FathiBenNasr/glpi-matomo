/*
 * Runs the real public/js/mtm-loader.js in a vm sandbox with a fake DOM:
 * it must read its settings from <meta> tags, refuse non-https containers,
 * and push the user identity to the data layer only when one is given.
 *
 *   node --test tests/*.test.js
 */
'use strict';
const test = require('node:test');
const assert = require('node:assert');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const SRC = fs.readFileSync(path.join(__dirname, '..', 'public', 'js', 'mtm-loader.js'), 'utf8');

function run(metas) {
    const inserted = [];
    const document = {
        querySelector(sel) {
            const m = /^meta\[name="([^"]+)"\]$/.exec(sel);
            if (!m || !(m[1] in metas)) return null;
            return { getAttribute: (a) => (a === 'content' ? metas[m[1]] : null) };
        },
        createElement: () => ({}),
        getElementsByTagName: () => [{ parentNode: { insertBefore: (el) => inserted.push(el) } }],
    };
    const window = {};
    window.window = window;
    window.document = document;
    vm.runInNewContext(SRC, window);
    return { mtm: window._mtm, inserted };
}

test('loads the https container and starts MTM', () => {
    const r = run({ 'glpi-matomo-container': 'https://stats.example.com/js/c.js' });
    assert.strictEqual(r.inserted.length, 1);
    assert.strictEqual(r.inserted[0].src, 'https://stats.example.com/js/c.js');
    assert.strictEqual(r.inserted[0].async, true);
    assert.strictEqual(r.mtm.length, 1);
    assert.strictEqual(r.mtm[0].event, 'mtm.Start');
});

test('does nothing without a container', () => {
    const r = run({});
    assert.strictEqual(r.inserted.length, 0);
    assert.strictEqual(r.mtm, undefined);
});

test('refuses a non-https container even if one reached the page', () => {
    for (const url of ['http://x/c.js', 'javascript:alert(1)', '//x/c.js', ' https://x/c.js']) {
        const r = run({ 'glpi-matomo-container': url });
        assert.strictEqual(r.inserted.length, 0, url);
    }
});

test('pushes the identity before the start event', () => {
    const r = run({ 'glpi-matomo-container': 'https://s/c.js', 'glpi-matomo-uid': 'abc123' });
    // objects come from the sandbox realm: compare their JSON, not their prototypes
    assert.strictEqual(JSON.stringify(r.mtm[0]), JSON.stringify({ glpiUserId: 'abc123' }));
    assert.strictEqual(r.mtm[1].event, 'mtm.Start');
});

test('no identity tag, no glpiUserId', () => {
    const r = run({ 'glpi-matomo-container': 'https://s/c.js', 'glpi-matomo-uid': '' });
    assert.ok(r.mtm.every((e) => !('glpiUserId' in e)));
});
