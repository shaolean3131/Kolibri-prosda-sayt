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
