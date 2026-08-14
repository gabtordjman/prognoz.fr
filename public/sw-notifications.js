/* Service worker — Web Push + notifications quand l’onglet Prognoz est en arrière-plan. */
'use strict';

self.addEventListener('push', function (event) {
    var payload = {
        title: 'Prognoz',
        body: '',
        url: '/',
        tag: 'prognoz-push',
        icon: ''
    };

    if (event.data) {
        try {
            var parsed = event.data.json();
            if (parsed && typeof parsed === 'object') {
                payload.title = parsed.title || payload.title;
                payload.body = parsed.body || payload.body;
                payload.url = parsed.url || payload.url;
                payload.tag = parsed.tag || payload.tag;
                payload.icon = parsed.icon || payload.icon;
            }
        } catch (e) {
            payload.body = event.data.text();
        }
    }

    event.waitUntil(
        self.registration.showNotification(payload.title, {
            body: payload.body,
            icon: payload.icon || undefined,
            tag: payload.tag,
            data: { url: payload.url },
            renotify: true
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = event.notification.data && event.notification.data.url;
    if (!url) return;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
            for (var i = 0; i < clients.length; i++) {
                var client = clients[i];
                if ('focus' in client) {
                    if ('navigate' in client) {
                        return client.navigate(url).then(function () { return client.focus(); });
                    }
                    client.focus();
                    return;
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        })
    );
});

self.addEventListener('message', function (event) {
    var data = event.data;
    if (!data || data.type !== 'SHOW_NOTIFICATION') return;

    var options = {
        body: data.body || '',
        tag: data.tag || ('prognoz-' + Date.now()),
        data: { url: data.url || '' },
        renotify: true
    };
    if (data.icon) {
        options.icon = data.icon;
    }

    event.waitUntil(
        self.registration.showNotification(data.title || 'Prognoz', options)
    );
});
