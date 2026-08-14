(function () {
    'use strict';

    var chatMessages = document.getElementById('chatMessages');
    var chatForm = document.getElementById('chatForm');
    var chatInput = document.getElementById('chatInput');
    var chatTyping = document.getElementById('chatTyping');
    if (!chatMessages || !chatForm || !chatInput) return;

    var dernierId = 0;
    var pollTimer = null;
    var initialLoaded = false;
    var myPseudo = window.CURRENT_USER_PSEUDO || 'Vous';
    var typingTimer = null;
    var typingHeartbeat = null;
    var typingActive = false;
    var sending = false;
    var seenIds = {};
    var i18n = window.PRONO_CHAT_I18N || {};

    function formatHeure(dateStr) {
        var d = new Date(String(dateStr).replace(' ', 'T') + 'Z');
        if (isNaN(d.getTime())) {
            d = new Date(String(dateStr).replace(' ', 'T'));
        }
        return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    }

    function escapeHtml(str) {
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function userInitials(pseudo) {
        var s = String(pseudo || '').trim();
        if (!s) return '?';
        var parts = s.split(/[\s_\-]+/).filter(Boolean);
        if (parts.length >= 2) {
            return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase();
        }
        return s.substring(0, Math.min(2, s.length)).toUpperCase();
    }

    function userAvatarColor(pseudo) {
        var s = String(pseudo || '').toLowerCase().trim();
        var hash = 0;
        for (var i = 0; i < s.length; i++) {
            hash = ((hash << 5) - hash) + s.charCodeAt(i);
            hash |= 0;
        }
        var hues = [145, 168, 195, 32, 12, 280, 220, 45, 350, 200];
        var hue = hues[Math.abs(hash) % hues.length];
        return 'hsl(' + hue + ', 44%, 36%)';
    }

    function avatarHtml(pseudo, avatarUrl) {
        var title = escapeHtml(pseudo);
        if (avatarUrl) {
            return '<span class="user-avatar user-avatar-sm chat-avatar has-photo" title="' + title + '">' +
                '<img src="' + escapeHtml(avatarUrl) + '" alt="" width="28" height="28"></span>';
        }
        return '<span class="user-avatar user-avatar-sm chat-avatar" style="background-color:' + userAvatarColor(pseudo) + ';" title="' + title + '">' +
            '<span class="user-avatar-initials">' + escapeHtml(userInitials(pseudo)) + '</span></span>';
    }

    function resolveAvatarUrl(msg, displayPseudo) {
        if (msg && msg.avatar_url) return String(msg.avatar_url);
        var estMoi = msg && msg.user_id == CURRENT_USER_ID;
        if (estMoi && window.CURRENT_USER_AVATAR) return String(window.CURRENT_USER_AVATAR);
        return '';
    }

    function updateChatCursor(messageId) {
        if (!window.COMMUNITY_ID || !messageId) return;
        try {
            var cursors = JSON.parse(localStorage.getItem('prognoz_chat_cursors') || '{}') || {};
            var key = String(window.COMMUNITY_ID);
            cursors[key] = Math.max(parseInt(cursors[key], 10) || 0, parseInt(messageId, 10));
            localStorage.setItem('prognoz_chat_cursors', JSON.stringify(cursors));
        } catch (e) { /* ignore */ }
    }

    function profileHref(userId) {
        if (!userId) return '';
        var dash = (typeof window.PRONO_DASHBOARD_URL === 'string') ? window.PRONO_DASHBOARD_URL : '';
        var base = dash.replace(/dashboard(\.php)?$/i, '');
        if (!base) base = 'account/';
        return base + 'profile?id=' + encodeURIComponent(String(userId));
    }

    function ajouterMessage(msg) {
        if (!msg) return false;
        var id = parseInt(msg.id, 10) || 0;
        // Anti-doublon : envoi local + réponse du poll en même temps
        if (id > 0) {
            if (seenIds[id]) {
                dernierId = Math.max(dernierId, id);
                return false;
            }
            seenIds[id] = true;
        }

        var div = document.createElement('div');
        var estMoi = msg.user_id == CURRENT_USER_ID;
        var displayPseudo = estMoi ? myPseudo : (msg.pseudo || '?');
        var adminBadge = msg.is_site_admin
            ? ' <span class="badge-admin" title="Admin">ADMIN</span>'
            : '';
        var authorHtml = estMoi
            ? '<span class="author">Vous</span>' + adminBadge
            : (msg.user_id
                ? '<a class="author author-link" href="' + escapeHtml(profileHref(msg.user_id)) + '">' + escapeHtml(msg.pseudo) + '</a>' + adminBadge
                : '<span class="author">' + escapeHtml(msg.pseudo) + '</span>' + adminBadge);
        var avHtml = avatarHtml(displayPseudo, resolveAvatarUrl(msg, displayPseudo));
        var avatarBlock = estMoi || !msg.user_id
            ? avHtml
            : '<a class="chat-avatar-link" href="' + escapeHtml(profileHref(msg.user_id)) + '" title="Voir le profil">' + avHtml + '</a>';
        div.className = 'chat-msg' + (estMoi ? ' mine' : '');
        if (id > 0) {
            div.setAttribute('data-msg-id', String(id));
        }
        div.innerHTML =
            '<div class="chat-msg-head">' +
                avatarBlock +
                '<div class="chat-msg-meta">' +
                    authorHtml +
                    ' <time class="chat-time">' + formatHeure(msg.created_at) + '</time>' +
                '</div>' +
            '</div>' +
            '<div class="bubble">' + escapeHtml(msg.contenu) + '</div>';
        chatMessages.appendChild(div);
        if (id > 0) {
            dernierId = Math.max(dernierId, id);
            updateChatCursor(id);
        }
        return true;
    }

    function scrollEnBas() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function showEmptyHint() {
        if (chatMessages.querySelector('.chat-empty-hint')) return;
        var p = document.createElement('p');
        p.className = 'chat-empty-hint';
        p.textContent = 'Aucun message.';
        chatMessages.appendChild(p);
    }

    function clearEmptyHint() {
        var el = chatMessages.querySelector('.chat-empty-hint');
        if (el) el.remove();
    }

    function showChatError(msg) {
        var existing = document.getElementById('chatSendError');
        if (!existing) {
            existing = document.createElement('div');
            existing.id = 'chatSendError';
            existing.className = 'chat-send-error';
            chatForm.parentNode.insertBefore(existing, chatForm);
        }
        existing.textContent = msg || 'Envoi impossible.';
        existing.hidden = false;
    }

    function clearChatError() {
        var existing = document.getElementById('chatSendError');
        if (existing) {
            existing.hidden = true;
            existing.textContent = '';
        }
    }

    function tpl(str, vars) {
        var out = String(str || '');
        Object.keys(vars || {}).forEach(function (k) {
            out = out.split('{' + k + '}').join(vars[k]);
        });
        return out;
    }

    function typingLabel(kind) {
        var s = (i18n && i18n['typing_' + kind]) ? String(i18n['typing_' + kind]) : '';
        var missing = !s || s.indexOf('com.chat_typing') === 0;
        if (kind !== 'many' && s.indexOf('{name') === -1) {
            missing = true;
        }
        if (missing) {
            if (kind === 'one') return '{name} est en train d’écrire…';
            if (kind === 'two') return '{name1} et {name2} sont en train d’écrire…';
            return 'Plusieurs personnes sont en train d’écrire…';
        }
        return s;
    }

    function renderTyping(list) {
        if (!chatTyping) return;
        list = Array.isArray(list) ? list : [];
        list = list.filter(function (row) {
            return row && row.pseudo && String(row.pseudo).indexOf('com.chat_typing') !== 0
                && String(row.pseudo) !== 'com_typing';
        });
        if (list.length === 0) {
            chatTyping.hidden = true;
            chatTyping.textContent = '';
            return;
        }
        var text;
        if (list.length === 1) {
            text = tpl(typingLabel('one'), { name: list[0].pseudo });
        } else if (list.length === 2) {
            text = tpl(typingLabel('two'), {
                name1: list[0].pseudo,
                name2: list[1].pseudo
            });
        } else {
            text = typingLabel('many');
        }
        chatTyping.textContent = text;
        chatTyping.hidden = false;
    }

    var apiBase = window.PRONO_API || '/api/';

    function apiUrl(path) {
        path = String(path || '').replace(/^\//, '');
        path = path.replace(/\.php(?=\?|$)/i, '');
        return apiBase + path;
    }

    function parseJson(r) {
        return r.json().then(function (data) {
            if (!r.ok && data && data.message) {
                throw new Error(data.message);
            }
            return data;
        });
    }

    function postTyping(active) {
        return fetch(apiUrl('typing.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                community_id: COMMUNITY_ID,
                typing: !!active,
                csrf_token: window.PRONO_CSRF || ''
            })
        }).catch(function () {});
    }

    function setTypingActive(active) {
        active = !!active;
        if (active === typingActive) return;
        typingActive = active;
        postTyping(active);
        if (typingHeartbeat) {
            clearInterval(typingHeartbeat);
            typingHeartbeat = null;
        }
        if (active) {
            typingHeartbeat = setInterval(function () {
                postTyping(true);
            }, 2500);
        }
    }

    function onInputActivity() {
        var hasText = chatInput.value.trim().length > 0;
        if (!hasText) {
            setTypingActive(false);
            return;
        }
        setTypingActive(true);
        if (typingTimer) clearTimeout(typingTimer);
        typingTimer = setTimeout(function () {
            setTypingActive(false);
        }, 4000);
    }

    function rafraichir(opts) {
        opts = opts || {};
        var url = apiUrl('fetch_messages.php?community_id=' + COMMUNITY_ID + '&depuis_id=' + dernierId);
        if (opts.initial) {
            url += '&initial=1';
        }

        return fetch(url, { credentials: 'same-origin' })
            .then(parseJson)
            .then(function (data) {
                if (!data.success) {
                    return;
                }
                if (data.messages && data.messages.length > 0) {
                    var added = false;
                    data.messages.forEach(function (m) {
                        if (ajouterMessage(m)) added = true;
                    });
                    if (added) {
                        clearEmptyHint();
                        scrollEnBas();
                    }
                } else if (opts.initial && !initialLoaded) {
                    showEmptyHint();
                }
                if (Object.prototype.hasOwnProperty.call(data, 'typing')) {
                    renderTyping(data.typing);
                }
                initialLoaded = true;
            })
            .catch(function () {});
    }

    function startPolling() {
        if (pollTimer) return;
        rafraichir({ initial: true }).then(function () {
            // 3s : messages + typing un peu plus réactifs
            pollTimer = setInterval(function () {
                rafraichir();
            }, 3000);
        });
    }

    chatInput.addEventListener('input', onInputActivity);
    chatInput.addEventListener('blur', function () {
        if (typingTimer) clearTimeout(typingTimer);
        setTypingActive(false);
    });

    window.addEventListener('beforeunload', function () {
        if (!typingActive) return;
        try {
            navigator.sendBeacon(
                apiUrl('typing.php'),
                new Blob([JSON.stringify({
                    community_id: COMMUNITY_ID,
                    typing: false,
                    csrf_token: window.PRONO_CSRF || ''
                })], { type: 'application/json' })
            );
        } catch (e) { /* ignore */ }
    });

    chatForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var contenu = chatInput.value.trim();
        if (!contenu || sending) return;

        if (typingTimer) clearTimeout(typingTimer);
        setTypingActive(false);
        clearChatError();
        sending = true;
        chatInput.disabled = true;

        fetch(apiUrl('send_message.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                community_id: COMMUNITY_ID,
                contenu: contenu,
                csrf_token: window.PRONO_CSRF || ''
            })
        })
            .then(parseJson)
            .then(function (data) {
                if (data.success && data.message) {
                    chatInput.value = '';
                    clearEmptyHint();
                    if (ajouterMessage(data.message)) {
                        scrollEnBas();
                    }
                    return;
                }
                showChatError((data && data.message) || 'Envoi impossible.');
            })
            .catch(function (err) {
                showChatError((err && err.message) || 'Envoi impossible.');
            })
            .finally(function () {
                sending = false;
                chatInput.disabled = false;
                chatInput.focus();
            });
    });

    startPolling();
})();
