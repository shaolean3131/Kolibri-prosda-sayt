(function () {
    'use strict';

    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

    /* ---------- dropdowns ---------- */

    function closeDropdowns(except) {
        $$('[data-dropdown].is-open').forEach(function (dd) {
            if (dd !== except) {
                dd.classList.remove('is-open');
                var toggle = $('[data-dropdown-toggle]', dd);
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                }
            }
        });
    }

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-dropdown-toggle]');
        if (toggle) {
            var dd = toggle.closest('[data-dropdown]');
            var open = !dd.classList.contains('is-open');
            closeDropdowns(dd);
            dd.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', String(open));
            return;
        }
        if (e.target.closest('.dropdown-item')) {
            closeDropdowns();
            return;
        }
        if (!e.target.closest('.dropdown-menu')) {
            closeDropdowns();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeDropdowns();
            document.body.classList.remove('sidebar-open');
        }
    });

    /* ---------- sidebar ---------- */

    $$('[data-nav-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var group = btn.closest('[data-nav-group]');
            var open = !group.classList.contains('is-open');
            $$('[data-nav-group].is-open').forEach(function (other) {
                if (other !== group) {
                    other.classList.remove('is-open');
                    $('[data-nav-toggle]', other).setAttribute('aria-expanded', 'false');
                }
            });
            group.classList.toggle('is-open', open);
            btn.setAttribute('aria-expanded', String(open));
        });
    });

    $$('[data-sidebar-open]').forEach(function (btn) {
        btn.addEventListener('click', function () { document.body.classList.add('sidebar-open'); });
    });
    $$('[data-sidebar-close]').forEach(function (btn) {
        btn.addEventListener('click', function () { document.body.classList.remove('sidebar-open'); });
    });

    /* ---------- filter chips (clients, orders) ---------- */

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-filters-toggle]');
        if (toggle) {
            toggle.closest('[data-filters]').classList.toggle('is-open');
            return;
        }
        // "Сбросить" inside a filter dropdown clears only that filter
        var reset = e.target.closest('[data-filter-reset]');
        if (reset) {
            $$('input', reset.closest('.filter__menu')).forEach(function (input) {
                if (input.type === 'radio' || input.type === 'checkbox') {
                    input.checked = false;
                } else {
                    input.value = '';
                }
            });
            reset.closest('form').submit();
        }
    });

    /* ---------- new orders: badge, toast and a soft chime ---------- */

    var lastOrder = +document.body.getAttribute('data-last-order') || 0;

    function chime() {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            [880, 1175].forEach(function (freq, i) {
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.frequency.value = freq;
                osc.type = 'sine';
                gain.gain.setValueAtTime(0.0001, ctx.currentTime + i * 0.18);
                gain.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + i * 0.18 + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + i * 0.18 + 0.5);
                osc.connect(gain).connect(ctx.destination);
                osc.start(ctx.currentTime + i * 0.18);
                osc.stop(ctx.currentTime + i * 0.18 + 0.55);
            });
        } catch (err) { /* audio not allowed yet */ }
    }

    function setBadge(count) {
        var link = $('.nav-link[href$="p=orders"]');
        if (!link) {
            return;
        }
        var badge = $('.badge', link);
        if (!count) {
            if (badge) {
                badge.remove();
            }
            return;
        }
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'badge badge--blue';
            link.appendChild(badge);
        }
        if (badge.textContent !== String(count)) {
            badge.textContent = count;
            badge.style.animation = 'none';
            void badge.offsetWidth;
            badge.style.animation = '';
        }
    }

    function poll() {
        if (document.hidden || !window.fetch) {
            return;
        }
        fetch('api/orders.php?action=poll&after=' + lastOrder, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (res) {
                if (!res) {
                    return;
                }
                setBadge(res.new);
                if (res.orders.length) {
                    lastOrder = res.orders[res.orders.length - 1].id;
                    res.orders.slice(-3).forEach(function (o) {
                        window.UI.toast('Новый заказ #' + o.id + ' · ' + o.total);
                    });
                    chime();
                    var bell = $('.bell');
                    if (bell && !$('.bell__dot', bell)) {
                        bell.insertAdjacentHTML('beforeend', '<span class="bell__dot"></span>');
                    }
                }
            })
            .catch(function () { /* offline: try again later */ });
    }

    setInterval(poll, 20000);
    document.addEventListener('visibilitychange', poll);
})();
