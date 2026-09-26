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

})();
