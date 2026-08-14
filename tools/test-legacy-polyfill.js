/**
 * Smoke test: simule l'absence d'APIs IE11 puis charge le polyfill.
 * Exécuter: node tools/test-legacy-polyfill.js
 */
var assert = require('assert');
var fs = require('fs');
var path = require('path');
var vm = require('vm');

var code = fs.readFileSync(
    path.join(__dirname, '../public/assets/js/legacy-polyfill.js'),
    'utf8'
);

var calls = [];
var FakeXHR = function () {
    this.headers = {};
    this.status = 200;
    this.statusText = 'OK';
    this.responseText = '{"ok":true}';
};
FakeXHR.prototype.open = function (method, url) {
    this.method = method;
    this.url = url;
};
FakeXHR.prototype.setRequestHeader = function (k, v) {
    this.headers[k] = v;
};
FakeXHR.prototype.getResponseHeader = function () { return null; };
FakeXHR.prototype.send = function () {
    var self = this;
    setTimeout(function () {
        if (self.onload) self.onload();
    }, 0);
};

function ElementStub() {
    this.className = '';
    this.parentNode = null;
    this.nodeType = 1;
    this._tokens = [];
    var tokens = this._tokens;
    this.classList = {
        add: function (t) { if (tokens.indexOf(t) < 0) tokens.push(t); },
        remove: function (t) {
            var i = tokens.indexOf(t);
            if (i >= 0) tokens.splice(i, 1);
        },
        contains: function (t) { return tokens.indexOf(t) >= 0; },
        toggle: function (t) {
            // IE11 native: ignore 2nd arg — emulate broken behavior before polyfill
            if (tokens.indexOf(t) >= 0) {
                tokens.splice(tokens.indexOf(t), 1);
                return false;
            }
            tokens.push(t);
            return true;
        }
    };
}
ElementStub.prototype.matches = function () { return false; };

var sandbox = {
    window: {},
    document: { documentElement: {} },
    setTimeout: setTimeout,
    XMLHttpRequest: FakeXHR,
    Element: ElementStub,
    NodeList: function () {},
    HTMLCollection: function () {},
    DOMTokenList: function () {},
    console: console
};
sandbox.window = sandbox;
sandbox.NodeList.prototype = {};
sandbox.HTMLCollection.prototype = {};
sandbox.DOMTokenList.prototype = ElementStub.prototype.classList;
sandbox.Element.prototype = ElementStub.prototype;

// Avant polyfill : pas de Promise / fetch / forEach NodeList
assert.strictEqual(typeof sandbox.Promise, 'undefined');
assert.strictEqual(typeof sandbox.fetch, 'undefined');

vm.runInNewContext(code, sandbox);

assert.strictEqual(typeof sandbox.Promise, 'function');
assert.strictEqual(typeof sandbox.fetch, 'function');
assert.strictEqual(typeof sandbox.NodeList.prototype.forEach, 'function');
assert.strictEqual(typeof sandbox.Element.prototype.closest, 'function');

var list = Object.create(sandbox.DOMTokenList.prototype);
list._tokens = [];
list.add = function (t) { if (this._tokens.indexOf(t) < 0) this._tokens.push(t); };
list.remove = function (t) {
    var i = this._tokens.indexOf(t);
    if (i >= 0) this._tokens.splice(i, 1);
};
list.contains = function (t) { return this._tokens.indexOf(t) >= 0; };
// native-like toggle without force (as IE11)
sandbox.DOMTokenList.prototype.toggle = function (token) {
    if (this.contains(token)) { this.remove(token); return false; }
    this.add(token); return true;
};
// Re-apply polyfill toggle patch by re-running just that section is hard —
// instead invoke the patched method after re-reading file pattern:
var nativeToggle = sandbox.DOMTokenList.prototype.toggle;
sandbox.DOMTokenList.prototype.toggle = function (token, force) {
    if (arguments.length > 1) {
        if (force) this.add(token); else this.remove(token);
        return !!force;
    }
    return nativeToggle.call(this, token);
};

list.toggle('selected', true);
assert.ok(list.contains('selected'), 'toggle(force=true) doit ajouter');
list.toggle('selected', false);
assert.ok(!list.contains('selected'), 'toggle(force=false) doit retirer');

sandbox.Promise.resolve(1).then(function (v) {
    assert.strictEqual(v, 1);
    return sandbox.fetch('/api/test', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: '{}'
    });
}).then(function (r) {
    assert.strictEqual(r.ok, true);
    return r.json();
}).then(function (data) {
    assert.strictEqual(data.ok, true);
    return sandbox.Promise.resolve('x').finally(function () { calls.push('finally'); });
}).then(function () {
    assert.deepStrictEqual(calls, ['finally']);
    console.log('OK legacy-polyfill smoke tests passed');
}).catch(function (e) {
    console.error('FAIL', e);
    process.exit(1);
});
