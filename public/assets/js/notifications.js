(function (global) {
    'use strict';

    var LS_ENABLED = 'prognoz_notify_enabled';
    var LS_WINS    = 'prognoz_notify_wins';
    var LS_CHAT    = 'prognoz_notify_chat';
    var LS_PROMPT  = 'prognoz_notify_prompt_dismissed';

    function supportsNotifications() {
        return typeof global.Notification !== 'undefined';
    }

    function readFlag(key, defaultOn) {
        try {
            var v = localStorage.getItem(key);
            if (v === null) return defaultOn;
            return v === '1';
        } catch (e) {
            return defaultOn;
        }
    }

    function writeFlag(key, on) {
        try {
            localStorage.setItem(key, on ? '1' : '0');
        } catch (e) { /* ignore */ }
    }

    function apiBase() {
        return global.PRONO_API || '/api/';
    }

    function apiUrl(path) {
        path = String(path || '').replace(/^\//, '');
        path = path.replace(/\.php(?=\?|$)/i, '');
        return apiBase() + path;
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = global.atob(base64);
        var arr = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; ++i) {
            arr[i] = raw.charCodeAt(i);
        }
        return arr;
    }

    var PrognozNotify = {
        supportsNotifications: supportsNotifications,
        _swRegistration: null,
        _pushSubscription: null,

        getPermission: function () {
            if (!supportsNotifications()) return 'unsupported';
            return Notification.permission;
        },

        isEnabled: function () {
            if (!supportsNotifications()) return false;
            if (Notification.permission !== 'granted') return false;
            return readFlag(LS_ENABLED, true);
        },

        winsEnabled: function () {
            return readFlag(LS_WINS, true);
        },

        chatEnabled: function () {
            return readFlag(LS_CHAT, true);
        },

        setEnabled: function (on) {
            writeFlag(LS_ENABLED, !!on);
        },

        setWinsEnabled: function (on) {
            writeFlag(LS_WINS, !!on);
        },

        setChatEnabled: function (on) {
            writeFlag(LS_CHAT, !!on);
        },

        shouldShowPrompt: function () {
            if (!supportsNotifications()) return false;
            if (Notification.permission === 'granted') return false;
            if (Notification.permission === 'denied') return false;
            try {
                return localStorage.getItem(LS_PROMPT) !== '1';
            } catch (e) {
                return true;
            }
        },

        dismissPrompt: function () {
            try {
                localStorage.setItem(LS_PROMPT, '1');
            } catch (e) { /* ignore */ }
        },

        init: function () {
            if (!('serviceWorker' in global.navigator)) {
                return Promise.resolve(null);
            }
            var swUrl = global.PRONO_SW_URL || '/sw-notifications.js';
            var swScope = global.PRONO_SW_SCOPE || '/';
            return global.navigator.serviceWorker.register(swUrl, { scope: swScope })
                .then(function (reg) {
                    PrognozNotify._swRegistration = reg;
                    return reg;
                })
                .catch(function () {
                    return null;
                });
        },

        subscribePush: function () {
            if (!this.isEnabled()) {
                return Promise.resolve(false);
            }
            if (!('serviceWorker' in global.navigator) || !('PushManager' in global)) {
                return Promise.resolve(false);
            }

            return this.init().then(function () {
                return global.navigator.serviceWorker.ready;
            }).then(function (reg) {
                return fetch(apiUrl('push_vapid.php'), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data || !data.ok || !data.publicKey) {
                            return false;
                        }
                        return reg.pushManager.getSubscription().then(function (existing) {
                            if (existing) {
                                PrognozNotify._pushSubscription = existing;
                                return existing;
                            }
                            return reg.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: urlBase64ToUint8Array(data.publicKey)
                            });
                        }).then(function (sub) {
                            if (!sub) return false;
                            PrognozNotify._pushSubscription = sub;
                            return fetch(apiUrl('push_subscribe.php'), {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({
                                    subscription: sub.toJSON(),
                                    csrf_token: global.PRONO_CSRF || ''
                                })
                            }).then(function (r) { return r.json(); })
                              .then(function (res) { return !!(res && res.ok); });
                        });
                    });
            }).catch(function () {
                return false;
            });
        },

        unsubscribePush: function () {
            var sub = this._pushSubscription;
            if (!sub && global.navigator.serviceWorker) {
                return global.navigator.serviceWorker.ready
                    .then(function (reg) { return reg.pushManager.getSubscription(); })
                    .then(function (existing) {
                        if (!existing) return true;
                        var endpoint = existing.endpoint;
                        return existing.unsubscribe().then(function () {
                            return fetch(apiUrl('push_subscribe.php'), {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    action: 'unsubscribe',
                                    endpoint: endpoint,
                                    csrf_token: global.PRONO_CSRF || ''
                                })
                            });
                        }).then(function () { return true; });
                    })
                    .catch(function () { return false; });
            }
            if (!sub) return Promise.resolve(true);
            var endpoint = sub.endpoint;
            return sub.unsubscribe().then(function () {
                return fetch(apiUrl('push_subscribe.php'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'unsubscribe',
                        endpoint: endpoint,
                        csrf_token: global.PRONO_CSRF || ''
                    })
                });
            }).then(function () { return true; }).catch(function () { return false; });
        },

        requestPermission: function () {
            if (!supportsNotifications()) {
                return Promise.resolve('unsupported');
            }
            if (Notification.permission === 'granted') {
                this.setEnabled(true);
                return this.init()
                    .then(function () { return PrognozNotify.subscribePush(); })
                    .then(function () { return 'granted'; });
            }
            if (Notification.permission === 'denied') {
                return Promise.resolve('denied');
            }
            return Notification.requestPermission().then(function (result) {
                if (result === 'granted') {
                    PrognozNotify.setEnabled(true);
                    return PrognozNotify.init()
                        .then(function () { return PrognozNotify.subscribePush(); })
                        .then(function () { return result; });
                }
                return result;
            });
        },

        show: function (title, body, options) {
            options = options || {};
            if (!this.isEnabled() && !options.force) return Promise.resolve(null);
            if (!options.force && !global.document.hidden) return Promise.resolve(null);

            var icon = options.icon || (global.PRONO_NOTIF_ICON || '');
            var tag  = options.tag || ('prognoz-' + Date.now());
            var url  = options.url || '';
            var notifOptions = {
                body: body,
                icon: icon || undefined,
                tag: tag,
                data: { url: url },
                renotify: true
            };

            if ('serviceWorker' in global.navigator) {
                return global.navigator.serviceWorker.ready.then(function (reg) {
                    return reg.showNotification(title, notifOptions);
                }).catch(function () {
                    try {
                        var n = new Notification(title, {
                            body: body,
                            icon: icon || undefined,
                            tag: tag
                        });
                        if (url) {
                            n.onclick = function () {
                                global.focus();
                                global.location.href = url;
                                n.close();
                            };
                        }
                        setTimeout(function () { n.close(); }, options.ttl || 8000);
                        return n;
                    } catch (e) {
                        return null;
                    }
                });
            }

            try {
                var legacy = new Notification(title, {
                    body: body,
                    icon: icon || undefined,
                    tag: tag
                });
                if (url) {
                    legacy.onclick = function () {
                        global.focus();
                        global.location.href = url;
                        legacy.close();
                    };
                }
                setTimeout(function () { legacy.close(); }, options.ttl || 8000);
                return Promise.resolve(legacy);
            } catch (e) {
                return Promise.resolve(null);
            }
        },

        testNotification: function () {
            var self = this;
            return this.requestPermission().then(function (perm) {
                if (perm !== 'granted') return { ok: false, message: 'Permission refusée.' };
                return fetch(apiUrl('push_test.php'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (data && data.ok) {
                        return data;
                    }
                    return self.show('Prognoz — test', 'Notification locale (sans push serveur).', {
                        force: true,
                        tag: 'prognoz-local-test',
                        url: global.PRONO_DASHBOARD_URL || '/account/dashboard'
                    }).then(function () {
                        return { ok: true, message: 'Notification locale affichée (push serveur indisponible).' };
                    });
                });
            });
        },

        getDiagnostics: function () {
            var self = this;
            var out = {
                permission: this.getPermission(),
                enabled: this.isEnabled(),
                serviceWorker: !!('serviceWorker' in global.navigator),
                pushManager: !!('PushManager' in global),
                pushConfigured: false,
                pushSubscribed: false,
                pushMissing: []
            };

            if (!('serviceWorker' in global.navigator)) {
                return Promise.resolve(out);
            }

            return fetch(apiUrl('push_vapid.php'), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    out.pushConfigured = !!(data && data.ok);
                    out.pushMissing = (data && data.missing) ? data.missing : [];
                    if (data && data.has_vapid === false) {
                        out.pushMissing.push('vapid');
                    }
                    if (data && data.has_vendor === false) {
                        out.pushMissing.push('vendor');
                    }
                    out.pushMissing = out.pushMissing.filter(function (v, i, a) { return a.indexOf(v) === i; });
                    return global.navigator.serviceWorker.ready;
                })
                .then(function (reg) {
                    if (!reg.pushManager) return out;
                    return reg.pushManager.getSubscription().then(function (sub) {
                        out.pushSubscribed = !!sub;
                        return out;
                    });
                })
                .catch(function () { return out; });
        },

        syncUi: function () {
            var statusEl = global.document.getElementById('notifyStatus');
            var diagEl = global.document.getElementById('notifyDiagnostics');
            var enableBtn = global.document.getElementById('btnEnableNotify');
            var disableBtn = global.document.getElementById('btnDisableNotify');
            var winsCb = global.document.getElementById('notifyWins');
            var chatCb = global.document.getElementById('notifyChat');
            var perm = this.getPermission();
            var enabled = this.isEnabled();

            if (winsCb) winsCb.checked = this.winsEnabled();
            if (chatCb) chatCb.checked = this.chatEnabled();

            if (statusEl) {
                if (perm === 'unsupported') {
                    statusEl.textContent = 'Non pris en charge par ce navigateur.';
                } else if (perm === 'denied') {
                    statusEl.textContent = 'Bloquées — autorisez Prognoz dans les paramètres du site (icône cadenas).';
                } else if (enabled) {
                    statusEl.textContent = 'Activées — alertes push (gains, messages communauté, fin de saison).';
                } else {
                    statusEl.textContent = 'Désactivées — cliquez sur « Autoriser » puis « Tester ».';
                }
            }

            if (enableBtn) {
                enableBtn.hidden = perm === 'unsupported' || (enabled && perm === 'granted');
            }
            if (disableBtn) {
                disableBtn.hidden = !enabled;
            }

            if (diagEl) {
                this.getDiagnostics().then(function (d) {
                    var pushDetail;
                    if (d.pushConfigured) {
                        pushDetail = 'configuré';
                    } else {
                        var parts = ['non configuré'];
                        if (d.pushMissing && d.pushMissing.indexOf('vapid') >= 0) {
                            parts.push('clés VAPID absentes du .env serveur');
                        }
                        if (d.pushMissing && d.pushMissing.indexOf('vendor') >= 0) {
                            parts.push('vendor/ absent');
                        }
                        pushDetail = parts.join(' — ');
                    }
                    var subDetail = d.pushSubscribed ? 'actif' : 'inactif';
                    if (d.pushSubscribed && !d.pushConfigured) {
                        subDetail += ' (navigateur OK, serveur ne peut pas envoyer — réautoriser après fix admin)';
                    }
                    var lines = [
                        'Permission : ' + d.permission,
                        'Service worker : ' + (d.serviceWorker ? 'oui' : 'non'),
                        'Push serveur : ' + pushDetail,
                        'Abonnement push : ' + subDetail
                    ];
                    diagEl.textContent = lines.join(' · ');
                });
            }
        }
    };

    if (supportsNotifications() && Notification.permission === 'granted' && readFlag(LS_ENABLED, true)) {
        PrognozNotify.init().then(function () {
            return PrognozNotify.subscribePush();
        });
    }

    global.PrognozNotify = PrognozNotify;
})(window);
