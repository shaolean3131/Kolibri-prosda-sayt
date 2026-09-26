(function () {
    'use strict';

    var UI = window.UI;

    // reveal a hidden phone number on click
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-phone]');
        if (!btn || btn.classList.contains('is-shown')) {
            return;
        }
        btn.classList.add('is-loading');
        UI.api('api/clients.php', { action: 'phone', id: btn.getAttribute('data-phone') }).then(function (res) {
            btn.textContent = res.phone || '—';
            btn.classList.add('is-shown');
            if (res.phone) {
                var link = document.createElement('a');
                link.className = 'phone is-shown';
                link.href = 'tel:' + res.phone.replace(/[^\d+]/g, '');
                link.textContent = res.phone;
                btn.replaceWith(link);
            }
        }).catch(function () {
            btn.classList.remove('is-loading');
        });
    });

    // mobile: filters are folded behind one button
    var toggle = UI.$('[data-filters-toggle]');
    if (toggle) {
        toggle.addEventListener('click', function () {
            toggle.closest('[data-filters]').classList.toggle('is-open');
        });
    }

    // "Сбросить" inside a filter dropdown clears only that filter
    document.addEventListener('click', function (e) {
        var reset = e.target.closest('[data-filter-reset]');
        if (!reset) {
            return;
        }
        var menu = reset.closest('.filter__menu');
        UI.$$('input', menu).forEach(function (input) {
            if (input.type === 'radio') {
                input.checked = false;
            } else {
                input.value = '';
            }
        });
        reset.closest('form').submit();
    });
})();
