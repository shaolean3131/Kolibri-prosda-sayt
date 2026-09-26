(function () {
    'use strict';

    var UI = window.UI;
    var form = UI.$('[data-settings]');
    var timers = {};

    function save(name, value) {
        return UI.api('api/settings.php', { name: name, value: value }).then(function () {
            UI.toast('Сохранено');
        });
    }

    /** Saves after the user pauses typing; switches and selects save at once. */
    var pending = {};

    function queue(name, value, delay) {
        clearTimeout(timers[name]);
        pending[name] = value;
        timers[name] = setTimeout(function () {
            delete timers[name];
            delete pending[name];
            save(name, value);
        }, delay);
    }

    function scheduleValue(root) {
        var value = {};
        UI.$$('[data-day]', root).forEach(function (row) {
            var day = {};
            UI.$$('[data-k]', row).forEach(function (input) {
                day[input.getAttribute('data-k')] = input.type === 'checkbox' ? input.checked : input.value;
            });
            row.classList.toggle('is-closed', !day.open);
            row.classList.toggle('is-allday', day.allday);
            value[row.getAttribute('data-day')] = day;
        });
        return JSON.stringify(value);
    }

    function handle(e, typing) {
        var input = e.target;
        var schedule = input.closest('[data-schedule]');
        if (schedule) {
            queue(schedule.getAttribute('data-schedule'), scheduleValue(schedule), typing ? 900 : 300);
            return;
        }
        if (!input.name) {
            return;
        }
        if (input.type === 'checkbox') {
            if (input.hasAttribute('data-reveal')) {
                input.closest('.set-row').classList.toggle('is-open', input.checked);
            }
            queue(input.name, input.checked ? '1' : '0', 0);
        } else {
            queue(input.name, input.value, typing ? 900 : 0);
        }
    }

    form.addEventListener('input', function (e) {
        if (e.target.matches('textarea, input[type=text], input[type=number]')) {
            handle(e, true);
        }
    });
    form.addEventListener('change', function (e) { handle(e, false); });

    // send unsaved edits when the page is left mid-typing
    window.addEventListener('pagehide', function () {
        var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        Object.keys(pending).forEach(function (name) {
            clearTimeout(timers[name]);
            var data = new FormData();
            data.append('_csrf', token);
            data.append('name', name);
            data.append('value', pending[name]);
            navigator.sendBeacon('api/settings.php', data);
        });
        pending = {};
    });
})();

/* ---------- pickup points and Telegram (tab-specific) ---------- */
(function () {
    'use strict';

    var UI = window.UI;
    var points = UI.$('[data-points]');

    if (points) {
        var savers = {};
        var saveRow = function (row) {
            var id = row.getAttribute('data-point');
            clearTimeout(savers[id]);
            savers[id] = setTimeout(function () {
                var data = { action: 'save', id: id };
                UI.$$('[data-f]', row).forEach(function (input) {
                    data[input.getAttribute('data-f')] = input.type === 'checkbox' ? (input.checked ? 1 : 0) : input.value;
                });
                row.classList.toggle('is-off', !data.is_active);
                UI.api('api/pickup.php', data).then(function () { UI.toast('Сохранено'); });
            }, 700);
        };

        points.addEventListener('input', function (e) {
            if (e.target.matches('[data-f]')) {
                saveRow(e.target.closest('[data-point]'));
            }
        });
        points.addEventListener('change', function (e) {
            if (e.target.type === 'checkbox') {
                saveRow(e.target.closest('[data-point]'));
            }
        });
        points.addEventListener('click', function (e) {
            var del = e.target.closest('[data-point-delete]');
            if (!del) {
                return;
            }
            var row = del.closest('[data-point]');
            UI.confirm({ title: 'Удалить пункт самовывоза?', text: UI.$('[data-f="address"]', row).value || 'Пункт без адреса' }).then(function (ok) {
                if (ok) {
                    UI.api('api/pickup.php', { action: 'delete', id: row.getAttribute('data-point') }).then(function () {
                        UI.collapse(row);
                    });
                }
            });
        });

        UI.$('[data-point-add]').addEventListener('click', function () {
            UI.api('api/pickup.php', { action: 'create' }).then(function (res) {
                var tpl = UI.$('[data-point-template]').content.firstElementChild.cloneNode(true);
                tpl.setAttribute('data-point', res.id);
                tpl.classList.add('is-new');
                points.appendChild(tpl);
                UI.$('[data-f="address"]', tpl).focus();
            });
        });

        // the "Самовывоз" switch dims the list
        var pickupSwitch = document.querySelector('input[name="pickup_enabled"]');
        if (pickupSwitch) {
            pickupSwitch.addEventListener('change', function () {
                UI.$('[data-points-block]').classList.toggle('is-muted', !pickupSwitch.checked);
            });
        }
    }

    var chatsBox = UI.$('[data-tg-chats]');
    if (chatsBox) {
        var chatInput = document.querySelector('input[name="telegram_chat_ids"]');

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-tg]');
            if (btn) {
                UI.busy(btn, true);
                UI.api('api/telegram.php', { action: btn.getAttribute('data-tg') }).then(function (res) {
                    if (res.sent) {
                        UI.toast('Сообщение отправлено — проверьте Telegram');
                        return;
                    }
                    if (!res.chats.length) {
                        chatsBox.innerHTML = '<p class="muted">Чатов не найдено. Напишите боту /start и нажмите ещё раз.</p>';
                        return;
                    }
                    chatsBox.innerHTML = '';
                    res.chats.forEach(function (chat) {
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'tg__chat';
                        b.setAttribute('data-chat', chat.id);
                        b.innerHTML = '<b></b><small></small>';
                        b.querySelector('b').textContent = chat.title;
                        b.querySelector('small').textContent = chat.type + ' · ' + chat.id;
                        chatsBox.appendChild(b);
                    });
                }).catch(function () { /* toast shown */ }).then(function () {
                    UI.busy(btn, false);
                });
                return;
            }
            var chat = e.target.closest('[data-chat]');
            if (chat) {
                var ids = chatInput.value.split(/[\s,;]+/).filter(Boolean);
                var id = chat.getAttribute('data-chat');
                if (ids.indexOf(id) === -1) {
                    ids.push(id);
                }
                chatInput.value = ids.join(', ');
                chatInput.dispatchEvent(new Event('change', { bubbles: true }));
                chat.classList.add('is-added');
            }
        });
    }
})();
