/*
 * Kolibri storefront: cart, checkout, product window, delivery/pickup
 * choice, search, sticky header and the "install app" card.
 */
(function () {
    'use strict';

    var K = window.KOLIBRI;
    var C = K.config;
    var P = K.products;

    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
    var csrf = $('meta[name="csrf-token"]').getAttribute('content');
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var desktop = window.matchMedia('(min-width: 1280px)');

    var store = {
        get: function (key, fallback) {
            try {
                var raw = localStorage.getItem('kolibri.' + key);
                return raw === null ? fallback : JSON.parse(raw);
            } catch (e) {
                return fallback;
            }
        },
        set: function (key, value) {
            try { localStorage.setItem('kolibri.' + key, JSON.stringify(value)); } catch (e) { /* private mode */ }
        }
    };

    var isApp = C.source === 'app' || window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    var source = isApp ? 'app' : 'site';

    function money(n) {
        var v = Math.round(n * 100) / 100;
        return String(v).replace('.', ',') + ' ' + C.currency;
    }
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }
    function plural(n, one, few, many) {
        var m = Math.abs(n) % 100, m1 = m % 10;
        if (m > 10 && m < 20) { return many; }
        if (m1 > 1 && m1 < 5) { return few; }
        return m1 === 1 ? one : many;
    }
    function debounce(fn, ms) {
        var t;
        return function () { clearTimeout(t); t = setTimeout(fn, ms); };
    }
    function restart(el, cls) {
        if (!el) { return; }
        el.classList.remove(cls);
        void el.offsetWidth;
        el.classList.add(cls);
    }
    function bird() {
        var tpl = $('.pcard__placeholder') || $('.logo__bird');
        var svg = tpl.cloneNode(true);
        svg.setAttribute('class', 'pcard__placeholder');
        return svg.outerHTML;
    }

    /* ======================================================================
       State
       ====================================================================== */

    var types = Object.keys(C.types);
    var state = {
        items: store.get('cart', []).filter(function (i) { return P[i.id] && i.qty > 0; }),
        mode: store.get('mode', null),
        point: store.get('point', null),
        addr: store.get('addr', { address: '', apartment: '', entrance: '', floor: '' }),
        promo: store.get('promo', ''),
        when: 'now',
        priced: null,
        pricedKey: ''
    };
    if (types.indexOf(state.mode) === -1) {
        state.mode = types.indexOf('pickup') !== -1 ? 'pickup' : (types[0] || 'pickup');
    }
    if (!C.points.some(function (p) { return p.id === state.point; })) {
        state.point = C.points[0] ? C.points[0].id : null;
    }

    function save() {
        store.set('cart', state.items);
        store.set('mode', state.mode);
        store.set('point', state.point);
        store.set('addr', state.addr);
        store.set('promo', state.promo);
    }

    function qtyOf(id) {
        var item = state.items.filter(function (i) { return i.id === id; })[0];
        return item ? item.qty : 0;
    }

    function setQty(id, qty) {
        var p = P[id];
        if (!p) { return; }
        qty = Math.min(qty, p.max);
        var existing = state.items.filter(function (i) { return i.id === id; })[0];
        if (qty < p.min || qty <= 0) {
            state.items = state.items.filter(function (i) { return i.id !== id; });
        } else if (existing) {
            existing.qty = qty;
        } else {
            state.items.push({ id: id, qty: qty });
        }
        save();
        render();
        reprice();
    }

    function localTotals() {
        var sum = 0, count = 0;
        state.items.forEach(function (i) {
            sum += P[i.id].price * i.qty;
            count += i.qty;
        });
        return { sum: sum, count: count };
    }

    function pricingKey() {
        return JSON.stringify([state.items, state.mode, state.promo, phoneDigits()]);
    }

    /* ======================================================================
       Modals, dropdown
       ====================================================================== */

    var openStack = [];

    function openModal(name) {
        var el = $('[data-smodal="' + name + '"]');
        if (!el) { return null; }
        el.classList.add('is-open');
        el.setAttribute('aria-hidden', 'false');
        openStack.push(el);
        document.body.classList.add('lock');
        return el;
    }

    function closeModal(el) {
        if (!el || !el.classList.contains('is-open')) { return; }
        el.classList.remove('is-open');
        el.setAttribute('aria-hidden', 'true');
        openStack = openStack.filter(function (m) { return m !== el; });
        var dialog = $('.smodal__dialog', el);
        if (dialog) { dialog.style.transform = ''; }
        if (!openStack.length && !(document.body.classList.contains('cart-open') && !desktop.matches)) {
            document.body.classList.remove('lock');
        }
    }

    document.addEventListener('click', function (e) {
        var closer = e.target.closest('[data-close]');
        if (closer) {
            closeModal(closer.closest('.smodal'));
            return;
        }
        var opener = e.target.closest('[data-open]');
        if (opener) {
            closeDropdowns();
            var name = opener.getAttribute('data-open');
            if (name === 'where') { openWhere(); }
            else if (name === 'search') { openSearch(); }
            else { openModal(name); }
            return;
        }
        var toggle = e.target.closest('[data-dd-toggle]');
        if (toggle) {
            var wrap = toggle.closest('[data-dd]');
            var open = !wrap.classList.contains('is-open');
            closeDropdowns();
            wrap.classList.toggle('is-open', open);
            return;
        }
        if (!e.target.closest('.dd')) { closeDropdowns(); }
    });

    function closeDropdowns() {
        $$('[data-dd].is-open').forEach(function (d) { d.classList.remove('is-open'); });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') { return; }
        closeDropdowns();
        if (openStack.length) {
            closeModal(openStack[openStack.length - 1]);
        } else if (document.body.classList.contains('cart-open')) {
            closeCart();
        }
    });

    /* ======================================================================
       Header: glass on scroll, category bar, scrollspy, to-top
       ====================================================================== */

    var hdr = $('[data-hdr]');
    var mainChips = $('[data-chips]');
    var toTop = $('[data-to-top]');
    var ticking = false;

    function onScroll() {
        ticking = false;
        var y = window.scrollY;
        hdr.classList.toggle('is-scrolled', y > 8);
        if (mainChips && mainChips.children.length) {
            hdr.classList.toggle('is-cats', mainChips.getBoundingClientRect().bottom < 0);
        }
        toTop.classList.toggle('is-shown', y > 900);
    }
    window.addEventListener('scroll', function () {
        if (!ticking) {
            ticking = true;
            requestAnimationFrame(onScroll);
        }
    }, { passive: true });
    onScroll();

    toTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' }); });

    function setActiveChip(id) {
        $$('[data-chip]').forEach(function (chip) {
            var on = chip.getAttribute('data-chip') === id;
            chip.classList.toggle('is-active', on);
            if (on) {
                var nav = chip.parentNode;
                if (nav.scrollWidth > nav.clientWidth) {
                    nav.scrollTo({ left: chip.offsetLeft - nav.clientWidth / 2 + chip.offsetWidth / 2, behavior: 'smooth' });
                }
            }
        });
    }

    if ('IntersectionObserver' in window) {
        var seen = {};
        var spy = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { seen[en.target.getAttribute('data-cat')] = en.isIntersecting; });
            var first = $$('[data-cat]').filter(function (s) { return seen[s.getAttribute('data-cat')]; })[0];
            if (first) { setActiveChip(first.getAttribute('data-cat')); }
        }, { rootMargin: '-110px 0px -55% 0px' });
        $$('[data-cat]').forEach(function (s) { spy.observe(s); });

        // cards fade in as they scroll into view
        var reveal = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (!en.isIntersecting) { return; }
                var card = en.target;
                var index = Array.prototype.indexOf.call(card.parentNode.children, card);
                card.style.setProperty('--d', (index % 4) * 0.07 + 's');
                card.classList.add('reveal-in');
                reveal.unobserve(card);
            });
        }, { rootMargin: '0px 0px -40px 0px' });
        $$('.pcard').forEach(function (c) { reveal.observe(c); });
    } else {
        $$('.pcard').forEach(function (c) { c.classList.add('reveal-in'); });
    }

    $$('.pcard__img img').forEach(function (img) {
        if (img.complete && img.naturalWidth) {
            img.classList.add('is-loaded');
        } else {
            img.addEventListener('load', function () { img.classList.add('is-loaded'); });
            img.addEventListener('error', function () { img.classList.add('is-loaded'); });
        }
    });

    /* ======================================================================
       Hero slider
       ====================================================================== */

    var track = $('[data-hero-track]');
    if (track && track.children.length > 1) {
        var slide = 0;
        var total = track.children.length;
        var dots = $$('[data-hero-dots] button');
        var go = function (n) {
            slide = (n + total) % total;
            track.style.transform = 'translateX(' + (-100 * slide) + '%)';
            dots.forEach(function (d, i) { d.classList.toggle('is-active', i === slide); });
        };
        var timer = setInterval(function () { go(slide + 1); }, 5500);
        dots.forEach(function (d, i) {
            d.addEventListener('click', function () { clearInterval(timer); go(i); });
        });
        var startX = null;
        track.addEventListener('pointerdown', function (e) { startX = e.clientX; });
        track.addEventListener('pointerup', function (e) {
            if (startX === null) { return; }
            var dx = e.clientX - startX;
            startX = null;
            if (Math.abs(dx) > 40) {
                clearInterval(timer);
                go(slide + (dx < 0 ? 1 : -1));
            }
        });
    }

    /* ======================================================================
       Segmented controls, delivery mode
       ====================================================================== */

    function setSeg(seg, index) {
        seg.style.setProperty('--n', $$('.seg__btn', seg).length);
        seg.style.setProperty('--i', index);
        $$('.seg__btn', seg).forEach(function (b, i) { b.classList.toggle('is-active', i === index); });
    }

    function placeLabel() {
        if (state.mode === 'pickup') {
            var p = C.points.filter(function (x) { return x.id === state.point; })[0];
            return p ? p.address : 'Выберите пункт самовывоза';
        }
        return state.addr.address ? state.addr.address + (state.addr.apartment ? ', кв. ' + state.addr.apartment : '') : 'Укажите адрес доставки';
    }

    function setMode(mode) {
        if (types.indexOf(mode) === -1) { return; }
        state.mode = mode;
        save();
        $$('[data-seg="mode"]').forEach(function (seg) {
            var btns = $$('.seg__btn', seg);
            btns.forEach(function (b, i) { if (b.getAttribute('data-mode') === mode) { setSeg(seg, i); } });
        });
        $$('[data-place-label]').forEach(function (el) { el.textContent = placeLabel(); });
        $$('[data-pane]').forEach(function (p) { p.classList.toggle('is-active', p.getAttribute('data-pane') === mode); });
        var title = $('[data-checkout-title]');
        if (title) { title.textContent = C.types[mode]; }
        updateMap();
        syncWhen();
        render();
        reprice();
    }

    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-mode]');
        if (b) { setMode(b.getAttribute('data-mode')); }
    });

    /* ======================================================================
       Delivery / pickup window with the map
       ====================================================================== */

    var mapBox = $('[data-map]');
    var mapSrc = '';

    function coords(lat, lng) {
        return lat && lng ? { lat: lat, lng: lng } : null;
    }

    function updateMap() {
        if (!mapBox || !$('[data-smodal="where"]').classList.contains('is-open')) { return; }
        var c = null, zoom = 11, marker = false;
        if (state.mode === 'pickup') {
            var p = C.points.filter(function (x) { return x.id === state.point; })[0];
            c = p ? coords(p.lat, p.lng) : null;
            zoom = 16;
            marker = !!c;
        }
        if (!c) {
            var parts = String(C.center).split(/[\s,;]+/);
            c = coords(parts[0], parts[1]);
            zoom = 11;
        }
        if (!c) { return; }
        var src = 'https://yandex.ru/map-widget/v1/?ll=' + c.lng + '%2C' + c.lat + '&z=' + zoom + (marker ? '&pt=' + c.lng + '%2C' + c.lat + '%2Cpm2dgm' : '');
        if (src === mapSrc) { return; }
        mapSrc = src;
        mapBox.innerHTML = '<iframe src="' + src + '" title="Карта" loading="lazy" allowfullscreen></iframe>';
    }

    function openWhere() {
        var modal = openModal('where');
        if (!modal) { return; }
        $$('[data-point]', modal).forEach(function (r) { r.checked = +r.value === state.point; });
        $$('[data-addr]', modal).forEach(function (input) { input.value = state.addr[input.getAttribute('data-addr')] || ''; });
        $$('[data-pane]', modal).forEach(function (p) { p.classList.toggle('is-active', p.getAttribute('data-pane') === state.mode); });
        updateMap();
    }

    document.addEventListener('change', function (e) {
        if (e.target.matches('[data-point]')) {
            state.point = +e.target.value;
            save();
            updateMap();
            $$('[data-place-label]').forEach(function (el) { el.textContent = placeLabel(); });
        }
    });

    var whereDone = $('[data-where-done]');
    if (whereDone) {
        whereDone.addEventListener('click', function () {
            var modal = whereDone.closest('.smodal');
            if (state.mode === 'delivery') {
                var addr = {};
                $$('[data-addr]', modal).forEach(function (input) { addr[input.getAttribute('data-addr')] = input.value.trim(); });
                if (addr.address.length < 5) {
                    restart($('[data-addr="address"]', modal).closest('.field'), 'is-invalid');
                    $('[data-addr="address"]', modal).focus();
                    return;
                }
                state.addr = addr;
            } else if (!state.point && C.points.length) {
                restart($('.point-card', modal), 'is-invalid');
                return;
            }
            save();
            $$('[data-place-label]').forEach(function (el) { el.textContent = placeLabel(); });
            closeModal(modal);
        });
    }

    // prefill the city into an empty address
    $$('[data-addr="address"]').forEach(function (input) {
        input.addEventListener('focus', function () {
            if (!input.value && C.city) {
                input.value = C.city + ', ';
            }
        });
    });

    /* ======================================================================
       Product window
       ====================================================================== */

    var pm = $('[data-smodal="product"]');
    var pmQty = 1;
    var pmId = null;

    function openProduct(id) {
        var p = P[id];
        if (!p) { return; }
        pmId = id;
        pmQty = qtyOf(id) || p.min;
        $('[data-pm-img]', pm).innerHTML = p.image ? '<img src="' + esc(p.image) + '" alt="">' : bird();
        $('[data-pm-name]', pm).textContent = p.name;
        $('[data-pm-price]', pm).textContent = money(p.price);
        $('[data-pm-old]', pm).textContent = p.old ? money(p.old) : '';
        $('[data-pm-unit]', pm).textContent = p.unit;
        var desc = esc(p.desc);
        if (p.comp) {
            desc += '<h4>Состав</h4>' + esc(p.comp);
        }
        var sizes = Object.keys(p.size || {});
        if (sizes.length) {
            desc += '<h4>Размер</h4>' + sizes.map(function (k) { return esc(k) + ': ' + esc(p.size[k]) + ' см'; }).join(' · ');
        }
        $('[data-pm-desc]', pm).innerHTML = desc;
        $('[data-pm-desc]', pm).scrollTop = 0;
        updatePm();
        openModal('product');
    }

    function updatePm(direction) {
        var p = P[pmId];
        var val = $('[data-pm-stepper] [data-qty]', pm);
        val.textContent = pmQty;
        if (direction) { restart(val, direction > 0 ? 'tick-up' : 'tick-down'); }
        $('[data-pm-stepper] [data-step="-1"]', pm).disabled = pmQty <= p.min;
        $('[data-pm-stepper] [data-step="1"]', pm).disabled = pmQty >= p.max;
        $('[data-pm-total]', pm).textContent = money(p.price * pmQty);
        $('[data-pm-buy] span', pm).textContent = qtyOf(pmId) ? 'Обновить' : 'В корзину';
    }

    $('[data-pm-stepper]', pm).addEventListener('click', function (e) {
        var b = e.target.closest('[data-step]');
        if (!b || b.disabled) { return; }
        var dir = +b.getAttribute('data-step');
        pmQty = Math.max(P[pmId].min, Math.min(P[pmId].max, pmQty + dir));
        updatePm(dir);
    });

    $('[data-pm-buy]', pm).addEventListener('click', function () {
        if (!C.active) {
            restart($('[data-pm-buy]', pm), 'shake');
            return;
        }
        var img = $('[data-pm-img] img', pm) || $('[data-pm-img]', pm);
        var wasEmpty = !state.items.length;
        fly(img);
        setQty(pmId, pmQty);
        closeModal(pm);
        if (desktop.matches && wasEmpty) {
            setTimeout(function () { openCart('cart'); }, 450);
        }
    });

    // drag the product sheet down to close it (phones)
    (function () {
        var dialog = $('.smodal__dialog', pm);
        var startY = null, dy = 0;
        dialog.addEventListener('touchstart', function (e) {
            startY = dialog.scrollTop <= 0 ? e.touches[0].clientY : null;
            dy = 0;
        }, { passive: true });
        dialog.addEventListener('touchmove', function (e) {
            if (startY === null) { return; }
            dy = Math.max(0, e.touches[0].clientY - startY);
            if (dy > 0) {
                dialog.style.transition = 'none';
                dialog.style.transform = 'translateY(' + dy + 'px)';
            }
        }, { passive: true });
        dialog.addEventListener('touchend', function () {
            if (startY === null) { return; }
            dialog.style.transition = '';
            if (dy > 110) {
                closeModal(pm);
            } else {
                dialog.style.transform = '';
            }
            startY = null;
        });
    })();

    /* ---------- product cards ---------- */

    document.addEventListener('click', function (e) {
        var card = e.target.closest('.pcard');
        if (!card) { return; }
        var id = +card.getAttribute('data-pid');
        var step = e.target.closest('[data-step]');
        if (step) {
            var dir = +step.getAttribute('data-step');
            var next = qtyOf(id) + dir;
            if (dir < 0 && next < P[id].min) { next = 0; }
            setQty(id, next);
            var val = $('[data-qty]', card);
            restart(val, dir > 0 ? 'tick-up' : 'tick-down');
            if (dir > 0) { bump(); }
            return;
        }
        openProduct(id);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.classList && e.target.classList.contains('pcard')) {
            openProduct(+e.target.getAttribute('data-pid'));
        }
    });

    /* ---------- "fly to cart" ---------- */

    function cartTarget() {
        var candidates = $$('[data-cart-open]').filter(function (el) {
            var r = el.getBoundingClientRect();
            return r.width > 0 && r.height > 0 && getComputedStyle(el).visibility !== 'hidden' && !el.closest('[aria-hidden="true"]');
        });
        var bar = $('[data-cart-bar]');
        if (bar && !bar.hidden && getComputedStyle(bar).display !== 'none') { return bar; }
        return candidates[0] || null;
    }

    function fly(fromEl) {
        var target = cartTarget();
        if (reduceMotion || !fromEl || !target) { return; }
        var a = fromEl.getBoundingClientRect();
        var b = target.getBoundingClientRect();
        var size = Math.min(a.width, a.height, 220);
        var ghost = document.createElement('div');
        ghost.className = 'fly';
        ghost.style.cssText = 'left:' + (a.left + a.width / 2 - size / 2) + 'px;top:' + (a.top + a.height / 2 - size / 2) + 'px;width:' + size + 'px;height:' + size + 'px;';
        ghost.innerHTML = fromEl.tagName === 'IMG' ? '<img src="' + esc(fromEl.src) + '" alt="">' : '<div style="width:100%;height:100%;background:#f3e6ff"></div>';
        document.body.appendChild(ghost);
        var dx = b.left + b.width / 2 - (a.left + a.width / 2);
        var dy = b.top + b.height / 2 - (a.top + a.height / 2);
        var anim = ghost.animate([
            { transform: 'translate(0,0) scale(1)', opacity: 1, borderRadius: '16px' },
            { transform: 'translate(' + dx * 0.45 + 'px,' + (dy * 0.45 - 90) + 'px) scale(.55)', opacity: 1, offset: 0.45 },
            { transform: 'translate(' + dx + 'px,' + dy + 'px) scale(.12)', opacity: 0.4, borderRadius: '50%' }
        ], { duration: 750, easing: 'cubic-bezier(.45,.05,.55,.95)' });
        anim.onfinish = function () {
            ghost.remove();
            bump();
        };
    }

    function bump() {
        $$('[data-cart-open]').forEach(function (b) { restart(b, 'bump'); });
    }

    /* ======================================================================
       Rendering: cart button, card steppers, cart lines, summary
       ====================================================================== */

    var linesBox = $('[data-lines]');

    function priceSynced() {
        return state.priced && state.pricedKey === pricingKey();
    }

    function render() {
        var t = localTotals();
        var totalText = priceSynced() ? state.priced.total : money(t.sum);
        $$('[data-cart-total]').forEach(function (el) { el.textContent = t.count ? totalText : '0 ' + C.currency; });
        $$('.cart-btn').forEach(function (b) { b.classList.toggle('has-items', t.count > 0); });
        var bar = $('[data-cart-bar]');
        if (bar) { bar.hidden = t.count === 0; }

        $$('.pcard').forEach(function (card) {
            var id = +card.getAttribute('data-pid');
            var q = qtyOf(id);
            var stepper = $('[data-card-stepper]', card);
            stepper.hidden = q === 0;
            if (q) { $('[data-qty]', card).textContent = q; }
        });

        renderLines();
        renderSummary();

        var empty = t.count === 0;
        $('[data-cart-empty]').hidden = !empty;
        $$('[data-cart-filled]').forEach(function (el) { el.hidden = empty; });
        $('[data-cart-clear]').style.visibility = empty ? 'hidden' : '';
    }

    function renderLines() {
        var wanted = state.items.slice();
        var gifts = priceSynced() ? state.priced.lines.filter(function (l) { return l.gift; }) : [];
        var keys = wanted.map(function (i) { return 'p' + i.id; }).concat(gifts.map(function (g) { return 'g' + g.id; }));

        $$('.line', linesBox).forEach(function (el) {
            if (keys.indexOf(el.getAttribute('data-key')) === -1 && !el.classList.contains('is-leaving')) {
                el.classList.add('is-leaving');
                var h = el.offsetHeight;
                var anim = el.animate([
                    { height: h + 'px', opacity: 1 },
                    { height: h + 'px', opacity: 0, transform: 'translateX(30px)', offset: 0.4 },
                    { height: '0px', opacity: 0, transform: 'translateX(30px)', marginTop: '-14px' }
                ], { duration: reduceMotion ? 1 : 420, easing: 'cubic-bezier(.2,.7,.2,1)' });
                anim.onfinish = function () { el.remove(); };
            }
        });

        wanted.forEach(function (item) {
            var p = P[item.id];
            var el = $('.line[data-key="p' + item.id + '"]:not(.is-leaving)', linesBox);
            if (!el) {
                el = document.createElement('div');
                el.className = 'line';
                el.setAttribute('data-key', 'p' + item.id);
                el.setAttribute('data-id', item.id);
                el.innerHTML = '<div class="line__img">' + (p.image ? '<img src="' + esc(p.image) + '" alt="">' : bird()) + '</div>'
                    + '<div class="line__body"><div class="line__name">' + esc(p.name) + ' <span>' + esc(p.unit) + '</span></div>'
                    + '<div class="line__row"><span class="line__price" data-line-price></span>'
                    + '<div class="stepper stepper--sm"><button class="stepper__btn" type="button" data-line-step="-1" aria-label="Меньше"><svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14"/></svg></button>'
                    + '<span class="stepper__val" data-qty></span>'
                    + '<button class="stepper__btn" type="button" data-line-step="1" aria-label="Больше"><svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></button></div>'
                    + '</div></div>';
                linesBox.appendChild(el);
            }
            $('[data-qty]', el).textContent = item.qty;
            $('[data-line-price]', el).textContent = money(p.price * item.qty);
            $('[data-line-step="1"]', el).disabled = item.qty >= p.max;
        });

        gifts.forEach(function (g) {
            if ($('.line[data-key="g' + g.id + '"]', linesBox)) { return; }
            var el = document.createElement('div');
            el.className = 'line line--gift';
            el.setAttribute('data-key', 'g' + g.id);
            el.innerHTML = '<div class="line__img">' + (g.image ? '<img src="' + esc(g.image) + '" alt="">' : bird()) + '</div>'
                + '<div class="line__body"><div class="line__name">' + esc(g.name) + ' <span>подарок</span></div>'
                + '<div class="line__row"><span class="line__price">🎁 0 ' + esc(C.currency) + '</span></div></div>';
            linesBox.appendChild(el);
        });
    }

    linesBox.addEventListener('click', function (e) {
        var b = e.target.closest('[data-line-step]');
        if (!b) { return; }
        var id = +b.closest('.line').getAttribute('data-id');
        var dir = +b.getAttribute('data-line-step');
        var next = qtyOf(id) + dir;
        if (dir < 0 && next < P[id].min) { next = 0; }
        setQty(id, next);
        var val = $('.line[data-key="p' + id + '"] [data-qty]', linesBox);
        restart(val, dir > 0 ? 'tick-up' : 'tick-down');
    });

    function summaryHtml() {
        var t = localTotals();
        if (!t.count) { return ''; }
        var synced = priceSynced();
        var pr = state.priced;
        var rows = '<div class="summary__row"><span>Товары в заказе <small>' + t.count + ' шт.</small></span><span>' + esc(synced ? pr.subtotal : money(t.sum)) + '</span></div>';
        if (synced && pr.discount) {
            rows += '<div class="summary__row"><span>Скидка' + (pr.promo && pr.promo.code ? ' <small>' + esc(pr.promo.code) + '</small>' : '') + '</span><span class="ok">' + esc(pr.discount) + '</span></div>';
        }
        if (state.mode === 'delivery') {
            rows += '<div class="summary__row"><span>Доставка</span><span>' + esc(synced ? pr.delivery : '…') + '</span></div>';
        }
        rows += '<div class="summary__row summary__row--total"><span>Итого</span><span>' + esc(synced ? pr.total : money(t.sum)) + '</span></div>';
        return rows;
    }

    function renderSummary() {
        var html = summaryHtml();
        $$('[data-summary]').forEach(function (el) { el.innerHTML = html; });
        var errors = priceSynced() ? state.priced.errors : [];
        $('[data-cart-error]').textContent = errors.join(' ');
        var go = $('[data-go="checkout"]');
        if (go) { go.disabled = errors.length > 0 || !C.active; }

        var msg = $('[data-promo-msg]');
        var promoBox = $('[data-promo]');
        msg.className = 'promo__msg';
        promoBox.classList.remove('is-applied');
        msg.textContent = '';
        if (priceSynced() && state.promo) {
            if (state.priced.promo) {
                msg.textContent = 'Промокод применён: ' + state.priced.promo.name;
                msg.classList.add('is-ok');
                promoBox.classList.add('is-applied');
            } else if (state.priced.promoError) {
                msg.textContent = state.priced.promoError;
                msg.classList.add('is-error');
            }
        } else if (priceSynced() && state.priced.promo) {
            msg.textContent = 'Акция: ' + state.priced.promo.name;
            msg.classList.add('is-ok');
        }
    }

    /* ---------- server pricing ---------- */

    var repriceNow = function () {
        if (!state.items.length) {
            state.priced = null;
            render();
            return;
        }
        var key = pricingKey();
        var body = new FormData();
        body.append('_csrf', csrf);
        body.append('items', JSON.stringify(state.items));
        body.append('delivery_type', state.mode);
        body.append('promo_code', state.promo);
        body.append('phone', phoneDigits());
        body.append('source', source);
        fetch('api/cart.php', { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (key !== pricingKey() || res.error) { return; }
                // the server may drop unavailable items or adjust quantities
                var changed = false;
                var server = {};
                res.lines.forEach(function (l) { if (!l.gift) { server[l.id] = l.qty; } });
                state.items = state.items.filter(function (i) {
                    if (!(i.id in server)) { changed = true; return false; }
                    if (server[i.id] !== i.qty) { i.qty = server[i.id]; changed = true; }
                    return true;
                });
                if (changed) { save(); }
                state.priced = res;
                state.pricedKey = pricingKey();
                render();
            })
            .catch(function () { /* offline: keep local totals */ });
    };
    var reprice = debounce(repriceNow, 300);

    /* ---------- promo code ---------- */

    var promoInput = $('[data-promo-input]');
    promoInput.value = state.promo;
    $('[data-promo]').classList.toggle('has-text', !!state.promo);
    promoInput.addEventListener('input', function () {
        $('[data-promo]').classList.toggle('has-text', promoInput.value.trim() !== '');
        if (!promoInput.value.trim() && state.promo) {
            state.promo = '';
            save();
            reprice();
        }
    });
    function applyPromo() {
        state.promo = promoInput.value.trim().toUpperCase();
        promoInput.value = state.promo;
        save();
        repriceNow();
    }
    $('[data-promo-apply]').addEventListener('click', applyPromo);
    promoInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            applyPromo();
        }
    });

    /* ======================================================================
       Cart panel & views
       ====================================================================== */

    var cart = $('[data-cart]');

    function showView(name) {
        var order = ['cart', 'checkout', 'done'];
        $$('.cart__view', cart).forEach(function (v) {
            var n = v.getAttribute('data-view');
            v.classList.toggle('is-active', n === name);
            v.classList.toggle('is-back', order.indexOf(n) < order.indexOf(name));
        });
    }

    function openCart(view) {
        showView(view || 'cart');
        document.body.classList.add('cart-open');
        cart.setAttribute('aria-hidden', 'false');
        if (!desktop.matches) { document.body.classList.add('lock'); }
        render();
        reprice();
    }

    function closeCart() {
        document.body.classList.remove('cart-open');
        cart.setAttribute('aria-hidden', 'true');
        if (!openStack.length) { document.body.classList.remove('lock'); }
        if ($('[data-view="done"]', cart).classList.contains('is-active')) {
            setTimeout(function () { showView('cart'); }, 500);
        }
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-cart-open]')) {
            if (document.body.classList.contains('cart-open') && desktop.matches) {
                closeCart();
            } else {
                openCart('cart');
            }
        } else if (e.target.closest('[data-cart-close]')) {
            closeCart();
        } else if (e.target.closest('[data-cart-clear]')) {
            state.items = [];
            save();
            render();
            reprice();
        } else if (e.target.closest('[data-go]')) {
            var to = e.target.closest('[data-go]').getAttribute('data-go');
            if (to === 'checkout') {
                prepareCheckout();
            }
            showView(to);
        }
    });

    desktop.addEventListener && desktop.addEventListener('change', function () {
        if (desktop.matches) {
            document.body.classList.remove('lock');
        }
    });

    /* ======================================================================
       Checkout
       ====================================================================== */

    var form = $('[data-checkout]');
    var phoneInput = form.elements.phone;

    function phoneDigits() {
        var d = (phoneInput.value || '').replace(/\D/g, '');
        if (d.length === 10) { d = '7' + d; }
        return d;
    }

    function formatPhone(value) {
        var d = value.replace(/\D/g, '');
        if (!d) { return ''; }
        if (d[0] === '8') { d = '7' + d.slice(1); }
        if (d[0] === '9') { d = '7' + d; }
        if (d[0] !== '7') { d = '7' + d; }
        d = d.slice(0, 11);
        var out = '+7';
        if (d.length > 1) { out += ' (' + d.slice(1, 4); }
        if (d.length >= 4) { out += ')'; }
        if (d.length > 4) { out += ' ' + d.slice(4, 7); }
        if (d.length > 7) { out += '-' + d.slice(7, 9); }
        if (d.length > 9) { out += '-' + d.slice(9, 11); }
        return out;
    }

    phoneInput.addEventListener('input', function () {
        var caretAtEnd = phoneInput.selectionStart === phoneInput.value.length;
        phoneInput.value = formatPhone(phoneInput.value);
        if (caretAtEnd) { phoneInput.setSelectionRange(phoneInput.value.length, phoneInput.value.length); }
        if (phoneDigits().length === 11) { reprice(); }
    });
    phoneInput.addEventListener('focus', function () {
        if (!phoneInput.value) { phoneInput.value = '+7 ('; }
    });
    phoneInput.addEventListener('blur', function () {
        if (phoneInput.value === '+7 (' || phoneInput.value === '+7') { phoneInput.value = ''; }
    });

    var whenSeg = $('[data-seg="when"]', form);
    var whenBtns = $$('.seg__btn', whenSeg);

    function whenAllowed(when) {
        return when === 'now' ? C.asap && C.openNow : C.preorders;
    }

    function syncWhen() {
        whenBtns.forEach(function (b) { b.disabled = !whenAllowed(b.getAttribute('data-when')); });
        if (!whenAllowed(state.when)) {
            state.when = whenAllowed('later') ? 'later' : 'now';
        }
        setSeg(whenSeg, state.when === 'now' ? 0 : 1);
        $('[data-closed-note]', form).hidden = C.openNow || !C.asap;
        $('[data-ready-in]', form).hidden = !(state.when === 'now' && state.mode === 'pickup');
        $('[data-later]', form).hidden = state.when !== 'later';
        if (state.when === 'later') { fillDates(); }
    }

    whenSeg.addEventListener('click', function (e) {
        var b = e.target.closest('[data-when]');
        if (!b || b.disabled) { return; }
        state.when = b.getAttribute('data-when');
        syncWhen();
    });

    var months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
    var weekdays = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
    var slotDays = [];

    function pad(n) { return (n < 10 ? '0' : '') + n; }

    // the shop's local time, advanced by the time spent on the page
    var loadedAt = Date.now();
    var shopStart = (function () {
        var m = String(C.now || '').match(/^(\d+)-(\d+)-(\d+)T(\d+):(\d+):(\d+)/);
        return m ? new Date(+m[1], m[2] - 1, +m[3], +m[4], +m[5], +m[6]) : new Date();
    })();
    function shopNow() {
        return new Date(shopStart.getTime() + (Date.now() - loadedAt));
    }

    function buildSlots() {
        var now = shopNow();
        var earliest = new Date(now.getTime() + C.minMinutes * 60000);
        var days = [];
        for (var d = 0; d <= C.maxDays; d++) {
            var day = new Date(now.getFullYear(), now.getMonth(), now.getDate() + d);
            var sch = C.schedule[day.getDay() || 7];
            if (!sch || !sch.open) { continue; }
            var from = (sch.allday ? '00:00' : sch.from).split(':');
            var to = (sch.allday ? '23:59' : sch.to).split(':');
            var start = new Date(day.getFullYear(), day.getMonth(), day.getDate(), +from[0], +from[1]);
            var end = new Date(day.getFullYear(), day.getMonth(), day.getDate(), +to[0], +to[1]);
            if (end <= start) { end = new Date(day.getFullYear(), day.getMonth(), day.getDate(), 23, 59); }
            var times = [];
            for (var t = start; t < end; t = new Date(t.getTime() + C.step * 60000)) {
                if (t >= earliest) { times.push(pad(t.getHours()) + ':' + pad(t.getMinutes())); }
            }
            if (!times.length) { continue; }
            var label = d === 0 ? 'Сегодня' : d === 1 ? 'Завтра' : weekdays[day.getDay()];
            days.push({
                value: day.getFullYear() + '-' + pad(day.getMonth() + 1) + '-' + pad(day.getDate()),
                label: label + ', ' + day.getDate() + ' ' + months[day.getMonth()],
                times: times
            });
        }
        return days;
    }

    function fillDates() {
        slotDays = buildSlots();
        var dateSel = $('[data-date]', form);
        var prev = dateSel.value;
        dateSel.innerHTML = slotDays.map(function (d) { return '<option value="' + d.value + '">' + esc(d.label) + '</option>'; }).join('');
        if (prev && slotDays.some(function (d) { return d.value === prev; })) { dateSel.value = prev; }
        fillTimes();
    }

    function fillTimes() {
        var dateSel = $('[data-date]', form);
        var timeSel = $('[data-time]', form);
        var day = slotDays.filter(function (d) { return d.value === dateSel.value; })[0];
        var prev = timeSel.value;
        timeSel.innerHTML = day ? day.times.map(function (t) { return '<option>' + t + '</option>'; }).join('') : '';
        if (prev && day && day.times.indexOf(prev) !== -1) { timeSel.value = prev; }
    }

    $('[data-date]', form).addEventListener('change', fillTimes);

    var payment = $('[data-payment]', form);
    function syncPayment() {
        $('[data-change]', form).hidden = !(C.askChange && payment.value === 'cash');
    }
    payment.addEventListener('change', syncPayment);

    function prepareCheckout() {
        var saved = store.get('me', {});
        if (!form.elements.name.value && saved.name) { form.elements.name.value = saved.name; }
        if (!phoneInput.value && saved.phone) { phoneInput.value = saved.phone; }
        $('[data-checkout-title]').textContent = C.types[state.mode] || 'Оформление';
        syncWhen();
        syncPayment();
        $('[data-checkout-error]').textContent = '';
    }

    function invalid(input) {
        var field = input.closest('.field') || input;
        restart(field, 'is-invalid');
        input.focus();
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var err = $('[data-checkout-error]');
        err.textContent = '';
        if (!form.elements.name.value.trim()) { invalid(form.elements.name); return; }
        if (phoneDigits().length !== 11) { invalid(phoneInput); return; }
        if (state.mode === 'pickup' && !state.point) { openWhere(); return; }
        if (state.mode === 'delivery' && (state.addr.address || '').length < 5) { openWhere(); return; }
        if (state.when === 'later' && !$('[data-time]', form).value) {
            err.textContent = 'Нет свободного времени для предзаказа — позвоните нам.';
            return;
        }

        var data = new FormData(form);
        data.append('_csrf', csrf);
        data.append('items', JSON.stringify(state.items));
        data.append('delivery_type', state.mode);
        data.append('pickup_point_id', state.point || '');
        data.append('address', state.addr.address || '');
        data.append('apartment', state.addr.apartment || '');
        data.append('entrance', state.addr.entrance || '');
        data.append('floor', state.addr.floor || '');
        data.append('when', state.when);
        data.append('promo_code', state.promo);
        data.append('source', source);
        if (state.when !== 'later') {
            data.delete('date');
            data.delete('time');
        }

        var btn = $('[data-submit]', form);
        btn.classList.add('is-busy');
        btn.disabled = true;
        fetch('api/order.php', { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { error: 'Ошибка сервера. Попробуйте ещё раз.' }; }); })
            .then(function (res) {
                if (res.error) {
                    err.textContent = res.error;
                    restart(err, 'shake');
                    return;
                }
                store.set('me', { name: form.elements.name.value.trim(), phone: phoneInput.value });
                showDone(res);
                state.items = [];
                state.promo = '';
                promoInput.value = '';
                $('[data-promo]').classList.remove('has-text');
                form.elements.comment.value = '';
                save();
                render();
            })
            .catch(function () {
                err.textContent = 'Нет соединения. Проверьте интернет и попробуйте ещё раз.';
            })
            .then(function () {
                btn.classList.remove('is-busy');
                btn.disabled = false;
            });
    });

    function showDone(res) {
        $('[data-done-title]').textContent = 'Заказ #' + res.id + ' принят!';
        $('[data-done-text]').textContent = C.orderMessage;
        $('[data-done-summary]').innerHTML =
            '<div class="summary__row"><span>' + esc(C.types[state.mode] || '') + '</span><b>' + esc(res.place) + '</b></div>'
            + '<div class="summary__row"><span>Когда</span><b>' + esc(res.time) + '</b></div>'
            + '<div class="summary__row summary__row--total"><span>Итого</span><span>' + esc(res.total) + '</span></div>';
        showView('done');
        petals();
    }

    function petals() {
        if (reduceMotion) { return; }
        var colors = ['#f7a8c4', '#f3c6d9', '#c9a7f5', '#fdf0a6', '#ffd1dc', '#b9e4c9'];
        for (var i = 0; i < 28; i++) {
            var p = document.createElement('span');
            p.className = 'petal';
            p.style.left = Math.random() * 100 + 'vw';
            p.style.background = colors[i % colors.length];
            p.style.setProperty('--t', (2.4 + Math.random() * 1.8) + 's');
            p.style.setProperty('--x', (Math.random() * 240 - 120) + 'px');
            p.style.setProperty('--r', (Math.random() * 720 - 360) + 'deg');
            p.style.animationDelay = Math.random() * 0.6 + 's';
            document.body.appendChild(p);
            setTimeout(p.remove.bind(p), 5000);
        }
    }

    /* ======================================================================
       Search
       ====================================================================== */

    var searchInput = $('[data-search-input]');
    var results = $('[data-search-results]');

    function openSearch() {
        openModal('search');
        searchInput.value = '';
        results.innerHTML = '';
        setTimeout(function () { searchInput.focus(); }, 60);
    }

    searchInput.addEventListener('input', function () {
        var q = searchInput.value.trim().toLowerCase();
        if (!q) {
            results.innerHTML = '';
            return;
        }
        var found = Object.keys(P).filter(function (id) {
            return (P[id].name + ' ' + P[id].desc + ' ' + P[id].comp).toLowerCase().indexOf(q) !== -1;
        }).slice(0, 30);
        results.innerHTML = found.length ? found.map(function (id, i) {
            var p = P[id];
            return '<button class="sr" type="button" data-sr="' + id + '" style="animation-delay:' + i * 0.03 + 's">'
                + '<span class="sr__img">' + (p.image ? '<img src="' + esc(p.image) + '" alt="">' : '') + '</span>'
                + '<span class="sr__name">' + esc(p.name) + '</span><span class="sr__price">' + money(p.price) + '</span></button>';
        }).join('') : '<div class="sr-empty">Ничего не нашлось 🌿</div>';
    });

    results.addEventListener('click', function (e) {
        var r = e.target.closest('[data-sr]');
        if (!r) { return; }
        closeModal($('[data-smodal="search"]'));
        openProduct(+r.getAttribute('data-sr'));
    });

    /* ======================================================================
       Install as an app (PWA)
       ====================================================================== */

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('sw.js').catch(function () { /* not critical */ });
        });
    }

    var install = $('[data-install]');
    var deferred = null;
    var dismissedAt = store.get('installDismissed', 0);
    var mayShow = !isApp && Date.now() - dismissedAt > 7 * 864e5;

    function showInstall() {
        if (!mayShow || !install.hidden) { return; }
        install.hidden = false;
    }
    function hideInstall() {
        install.classList.add('is-leaving');
        setTimeout(function () { install.hidden = true; install.classList.remove('is-leaving'); }, 350);
    }

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferred = e;
        setTimeout(showInstall, 3500);
    });

    var ios = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
    if (ios && mayShow) {
        $('[data-install-text]').innerHTML = 'Нажмите «Поделиться» ' + '<svg class="i" style="width:16px;height:16px;display:inline;vertical-align:-3px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V3.5M8 7.5l4-4 4 4M6 11H5v9.5h14V11h-1"/></svg> и «На экран Домой».';
        $('[data-install-go]').textContent = 'Понятно';
        setTimeout(showInstall, 5000);
    }

    $('[data-install-go]').addEventListener('click', function () {
        if (deferred) {
            deferred.prompt();
            deferred.userChoice.then(function () { deferred = null; });
        }
        store.set('installDismissed', Date.now());
        hideInstall();
    });
    $('[data-install-close]').addEventListener('click', function () {
        store.set('installDismissed', Date.now());
        hideInstall();
    });

    /* ======================================================================
       Start
       ====================================================================== */

    $$('[data-seg]').forEach(function (seg) { setSeg(seg, 0); });
    setMode(state.mode);
    render();
    if (state.items.length) { reprice(); }
})();
