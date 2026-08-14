/**
 * Polyfills pour IE11 / vieux Safari (thème rétro Prognoz).
 * Chargé avant les autres scripts quand wantsRetroUi().
 */
(function (window, document) {
    'use strict';

    /* NodeList / HTMLCollection forEach */
    if (window.NodeList && !NodeList.prototype.forEach) {
        NodeList.prototype.forEach = Array.prototype.forEach;
    }
    if (window.HTMLCollection && !HTMLCollection.prototype.forEach) {
        HTMLCollection.prototype.forEach = Array.prototype.forEach;
    }

    /* Element.matches */
    if (!Element.prototype.matches) {
        Element.prototype.matches =
            Element.prototype.msMatchesSelector ||
            Element.prototype.webkitMatchesSelector ||
            function (s) {
                var n = (this.document || this.ownerDocument).querySelectorAll(s);
                var i = n.length;
                while (--i >= 0 && n.item(i) !== this) {}
                return i > -1;
            };
    }

    /* Element.closest — remonte aussi depuis un nœud texte */
    if (!Element.prototype.closest) {
        Element.prototype.closest = function (sel) {
            var el = this;
            if (el && el.nodeType === 3) {
                el = el.parentElement || el.parentNode;
            }
            while (el && el.nodeType === 1) {
                if (el.matches(sel)) {
                    return el;
                }
                el = el.parentElement || el.parentNode;
            }
            return null;
        };
    }

    /* closest sûr pour event.target (texte inclus) */
    if (typeof window.prognozClosest !== 'function') {
        window.prognozClosest = function (el, sel) {
            if (!el) {
                return null;
            }
            if (el.nodeType === 3) {
                el = el.parentElement || el.parentNode;
            }
            return el && el.nodeType === 1 && el.closest ? el.closest(sel) : null;
        };
    }

    /* classList.toggle(token, force) — IE11 ignore le 2e argument */
    if (window.DOMTokenList && DOMTokenList.prototype) {
        var nativeToggle = DOMTokenList.prototype.toggle;
        DOMTokenList.prototype.toggle = function (token, force) {
            if (arguments.length > 1) {
                if (force) {
                    this.add(token);
                } else {
                    this.remove(token);
                }
                return !!force;
            }
            return nativeToggle.call(this, token);
        };
    }

    /* ChildNode.remove */
    if (!Element.prototype.remove) {
        Element.prototype.remove = function () {
            if (this.parentNode) {
                this.parentNode.removeChild(this);
            }
        };
    }

    /* Minimal Promise */
    if (typeof window.Promise === 'undefined') {
        function P(executor) {
            var self = this;
            self._state = 0;
            self._value = undefined;
            self._handlers = [];
            function resolve(v) {
                if (self._state) return;
                if (v && typeof v.then === 'function') {
                    v.then(resolve, reject);
                    return;
                }
                self._state = 1;
                self._value = v;
                flush();
            }
            function reject(e) {
                if (self._state) return;
                self._state = 2;
                self._value = e;
                flush();
            }
            function flush() {
                if (!self._state) return;
                setTimeout(function () {
                    var i, h;
                    for (i = 0; i < self._handlers.length; i++) {
                        h = self._handlers[i];
                        handle(h.onFulfilled, h.onRejected, h.resolve, h.reject);
                    }
                    self._handlers = [];
                }, 0);
            }
            function handle(onFulfilled, onRejected, resolve, reject) {
                try {
                    if (self._state === 1) {
                        if (typeof onFulfilled === 'function') {
                            resolve(onFulfilled(self._value));
                        } else {
                            resolve(self._value);
                        }
                    } else {
                        if (typeof onRejected === 'function') {
                            resolve(onRejected(self._value));
                        } else {
                            reject(self._value);
                        }
                    }
                } catch (err) {
                    reject(err);
                }
            }
            self.then = function (onFulfilled, onRejected) {
                return new P(function (resolve, reject) {
                    self._handlers.push({
                        onFulfilled: onFulfilled,
                        onRejected: onRejected,
                        resolve: resolve,
                        reject: reject
                    });
                    if (self._state) flush();
                });
            };
            self.catch = function (onRejected) {
                return self.then(null, onRejected);
            };
            self.finally = function (cb) {
                return self.then(
                    function (v) {
                        return P.resolve(typeof cb === 'function' ? cb() : null).then(function () { return v; });
                    },
                    function (e) {
                        return P.resolve(typeof cb === 'function' ? cb() : null).then(function () {
                            return P.reject(e);
                        });
                    }
                );
            };
            try {
                executor(resolve, reject);
            } catch (e) {
                reject(e);
            }
        }
        P.resolve = function (v) {
            return new P(function (resolve) { resolve(v); });
        };
        P.reject = function (e) {
            return new P(function (resolve, reject) { reject(e); });
        };
        P.all = function (arr) {
            return new P(function (resolve, reject) {
                if (!arr || !arr.length) {
                    resolve([]);
                    return;
                }
                var left = arr.length;
                var out = new Array(arr.length);
                arr.forEach(function (p, i) {
                    P.resolve(p).then(function (v) {
                        out[i] = v;
                        left -= 1;
                        if (!left) resolve(out);
                    }, reject);
                });
            });
        };
        window.Promise = P;
    }

    /* fetch via XHR */
    if (typeof window.fetch !== 'function') {
        window.fetch = function (url, options) {
            options = options || {};
            return new Promise(function (resolve, reject) {
                var xhr = new XMLHttpRequest();
                xhr.open((options.method || 'GET').toUpperCase(), url, true);
                xhr.withCredentials = options.credentials === 'include' || options.credentials === 'same-origin';
                var headers = options.headers || {};
                if (headers) {
                    if (typeof headers.forEach === 'function') {
                        headers.forEach(function (v, k) { xhr.setRequestHeader(k, v); });
                    } else {
                        for (var k in headers) {
                            if (Object.prototype.hasOwnProperty.call(headers, k)) {
                                xhr.setRequestHeader(k, headers[k]);
                            }
                        }
                    }
                }
                xhr.onload = function () {
                    var hdrs = {
                        get: function (name) {
                            return xhr.getResponseHeader(name);
                        }
                    };
                    resolve({
                        ok: xhr.status >= 200 && xhr.status < 300,
                        status: xhr.status,
                        statusText: xhr.statusText,
                        headers: hdrs,
                        url: url,
                        text: function () {
                            return Promise.resolve(xhr.responseText);
                        },
                        json: function () {
                            return Promise.resolve(JSON.parse(xhr.responseText));
                        }
                    });
                };
                xhr.onerror = function () {
                    reject(new TypeError('Network request failed'));
                };
                xhr.send(options.body || null);
            });
        };
    }
})(window, document);
