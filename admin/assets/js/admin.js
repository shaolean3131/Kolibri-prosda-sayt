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
})();
