const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'meta-pixel.js'), 'utf8');
const values = new Map();
const storage = {
  getItem: key => values.get(key) ?? null,
  setItem: (key, value) => values.set(key, value)
};
const sessionValues = new Map();
const sessionStorage = {
  getItem: key => sessionValues.get(key) ?? null,
  setItem: (key, value) => sessionValues.set(key, value)
};

function load() {
  const listeners = new Map();
  const scripts = [];
  const window = {
    SmileBabyPixelConfig: {
      id: '1855843608929498',
      events: [{ name: 'Purchase', data: { value: 250, currency: 'RON' }, once_key: 'smilebaby_meta_purchase:SB-1', event_id: 'smilebaby-purchase-SB-1' }]
    },
    addEventListener: (name, listener) => listeners.set(name, listener)
  };
  const document = {
    createElement: () => ({}),
    head: { appendChild: script => scripts.push(script) }
  };
  vm.runInNewContext(source, { window, document, localStorage: storage, sessionStorage });
  return { window, listeners, scripts };
}

const first = load();
assert.equal(first.window.fbq, undefined, 'Meta must not load before marketing consent.');
assert.equal(first.scripts.length, 0);

storage.setItem('sb_cookie_consent', 'all');
first.listeners.get('smilebaby:consent')();
assert.equal(first.scripts.length, 1);
assert.equal(first.scripts[0].src, 'https://connect.facebook.net/en_US/fbevents.js');
const eventNames = Array.from(first.window.fbq.queue.filter(args => args[0] === 'trackSingle'), args => args[2]);
assert.deepEqual(eventNames, ['PageView', 'Purchase']);
assert.equal(values.get('smilebaby_meta_purchase:SB-1'), '1');

first.listeners.get('smilebaby:consent')();
assert.equal(first.window.fbq.queue.filter(args => args[0] === 'trackSingle').length, 2, 'Consent callback must not duplicate events.');
assert.equal(first.window.SmileBabyPixel.track('Search', { search_string: 'trusou' }), true);
assert.equal(first.window.SmileBabyPixel.track('Search', { search_string: 'trusou' }), false, 'Rapid duplicate searches must be suppressed.');

const repeat = load();
assert.deepEqual(Array.from(repeat.window.fbq.queue.filter(args => args[0] === 'trackSingle'), args => args[2]), ['PageView'], 'Reload must not duplicate Purchase.');

storage.setItem('sb_cookie_consent', 'necessary');
repeat.listeners.get('smilebaby:consent')();
assert.equal(repeat.window.fbq.queue.at(-1)[1], 'revoke', 'Revoking consent must stop future Meta events.');
assert.equal(repeat.window.SmileBabyPixel.track('AddToCart', { value: 10 }), false);
storage.setItem('sb_cookie_consent', 'all');
repeat.listeners.get('smilebaby:consent')();
assert.equal(repeat.window.fbq.queue.at(-1)[1], 'grant', 'Reaccepting consent must grant Meta tracking again.');
assert.equal(repeat.window.fbq.queue.filter(args => args[0] === 'trackSingle').length, 1, 'Reaccepting consent must not repeat PageView.');

console.log('Meta Pixel JavaScript checks passed.');
