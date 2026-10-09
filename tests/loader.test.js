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

function run(metas, location) {
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
    const window = { location: location || { pathname: '/front/central.php', search: '', hash: '' } };
    window.window = window;
    window.document = document;
    vm.runInNewContext(SRC, window);
    return { mtm: window._mtm, inserted };
}

test('loads the https container and starts MTM', () => {
    const r = run({ 'glpi-matomo-container': 'https://stats.example.com/js/container_AbC123.js' });
    assert.strictEqual(r.inserted.length, 1);
    assert.strictEqual(r.inserted[0].src, 'https://stats.example.com/js/container_AbC123.js');
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
    for (const url of ['http://x/c.js', 'javascript:alert(1)', '//x/c.js', ' https://x/c.js',
        'https://evil.example/payload.js', 'https://x/js/container_A1.js?y=1', 'https://u@x/js/container_A1.js',
        'https://x/../container_A1.js']) {
        const r = run({ 'glpi-matomo-container': url });
        assert.strictEqual(r.inserted.length, 0, url);
    }
});

test('pushes the identity before the start event', () => {
    const r = run({ 'glpi-matomo-container': 'https://s.example/js/container_C1.js', 'glpi-matomo-uid': 'abc123' });
    // objects come from the sandbox realm: compare their JSON, not their prototypes
    assert.strictEqual(JSON.stringify(r.mtm[0]), JSON.stringify({ glpiUserId: 'abc123' }));
    assert.strictEqual(r.mtm[1].event, 'mtm.Start');
});

test('no identity tag, no glpiUserId', () => {
    const r = run({ 'glpi-matomo-container': 'https://s.example/js/container_C1.js', 'glpi-matomo-uid': '' });
    assert.ok(r.mtm.every((e) => !('glpiUserId' in e)));
});

// M-12: mutable third-party code never runs in administration pages.
test('injects nothing on a privileged page, even if the tags reached it', () => {
    for (const pathname of ['/front/config.form.php', '/glpi/front/profile.form.php', '/front/user.form.php',
        '/front/preference.php', '/front/authldap.form.php', '/plugins/matomo/front/config.php', '/ajax/rule.php']) {
        const r = run({ 'glpi-matomo-container': 'https://s.example/js/container_C1.js', 'glpi-matomo-uid': 'abc' },
            { pathname, search: '', hash: '' });
        assert.strictEqual(r.inserted.length, 0, pathname);
        assert.strictEqual(r.mtm, undefined, pathname);
    }
});

// M-13: the tracker records the page URL; a token in it must never leave.
test('injects nothing when the URL carries a secret', () => {
    for (const search of ['?password_forget_token=abc', '?x=1&PASSWORD_FORGET_TOKEN=abc', '?password%5Fforget%5Ftoken=abc',
        '?_glpi_csrf_token=abc', '?code=a&state=b', '?%E0%A4%A=1']) {
        const r = run({ 'glpi-matomo-container': 'https://s.example/js/container_C1.js' },
            { pathname: '/front/lostpassword.php', search, hash: '' });
        assert.strictEqual(r.inserted.length, 0, search);
    }
    const h = run({ 'glpi-matomo-container': 'https://s.example/js/container_C1.js' },
        { pathname: '/front/central.php', search: '', hash: '#access_token=abc' });
    assert.strictEqual(h.inserted.length, 0, 'fragment');
});

test('an ordinary page with harmless parameters is tracked', () => {
    const r = run({ 'glpi-matomo-container': 'https://s.example/js/container_C1.js' },
        { pathname: '/front/ticket.form.php', search: '?id=12', hash: '#tab' });
    assert.strictEqual(r.inserted.length, 1);
});
