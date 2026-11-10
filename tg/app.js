/*
 * Kolibri — Telegram Mini App.
 * Telegram's own MainButton / BackButton / haptics / CloudStorage when opened
 * from the bot; an in-page button and localStorage in a normal browser.
 */
(function () {
    'use strict';

    var D = JSON.parse(document.getElementById('kolibri-data').textContent);
    var C = D.config;
    var P = D.products;
    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
    var shell = $('[data-shell]');
    var scroller = $('[data-scroller]');

    /* ---------- Telegram bridge ---------- */
    var W = window.Telegram && window.Telegram.WebApp;
    var inTg = !!(W && W.initData);
    var since = function (v) { return inTg && W.isVersionAtLeast && W.isVersionAtLeast(v); };
    var colors = {
        botanika: { bg: '#f6f1e9', btn: '#7d8b6a', text: '#ffffff' },
        nochnoy: { bg: '#06142a', btn: '#fdf0a6', text: '#0a1f3a' },
        pastel: { bg: '#ffe4ec', btn: '#7b2fd0', text: '#ffffff' }
    }[D.skin];

    var fakeBtn = $('[data-mainbtn]');
    var mainHandler = null;

    var TG = {
        main: function (text, onClick) {
            mainHandler = onClick;
            if (inTg) {
                if (text) {
                    W.MainButton.setParams({ text: text, color: colors.btn, text_color: colors.text, is_visible: true, is_active: true });
                } else {
                    W.MainButton.hide();
                }
                return;
            }
            fakeBtn.hidden = !text;
            if (text && $('[data-mainbtn-text]').textContent !== text) {
                $('[data-mainbtn-text]').textContent = text;
                restart(fakeBtn, 'is-pulse');
            }
        },
        busy: function (on) {
            if (inTg) {
                if (on) { W.MainButton.showProgress(false); } else { W.MainButton.hideProgress(); }
            } else {
                fakeBtn.classList.toggle('is-busy', on);
            }
        },
        back: function (show) {
            if (since('6.1')) {
                if (show) { W.BackButton.show(); } else { W.BackButton.hide(); }
            }
        },
        haptic: function (kind) {
            if (!since('6.1')) {
                if (navigator.vibrate) { navigator.vibrate(kind === 'success' ? [12, 60, 18] : 8); }
                return;
            }
            if (kind === 'success' || kind === 'error' || kind === 'warning') {
                W.HapticFeedback.notificationOccurred(kind);
            } else if (kind === 'select') {
                W.HapticFeedback.selectionChanged();
            } else {
                W.HapticFeedback.impactOccurred(kind || 'light');
            }
        },
        confirmClose: function (on) {
            if (since('6.2')) {
                if (on) { W.enableClosingConfirmation(); } else { W.disableClosingConfirmation(); }
            }
        }
    };

    if (inTg) {
        W.ready();
        W.expand();
        if (since('6.1')) {
            W.setHeaderColor(colors.bg);
            W.setBackgroundColor(colors.bg);
        }
        if (since('7.10')) { W.setBottomBarColor(colors.bg); }
        if (since('7.7')) { W.disableVerticalSwipes(); } // scrolling the catalog must not close the app
        W.MainButton.onClick(function () { if (mainHandler) { mainHandler(); } });
        if (since('6.1')) { W.BackButton.onClick(function () { back(); }); }
    }
    fakeBtn.addEventListener('click', function () { if (mainHandler) { mainHandler(); } });

    var tgUser = inTg && W.initDataUnsafe && W.initDataUnsafe.user ? W.initDataUnsafe.user : null;
    if (tgUser && tgUser.first_name) {
        $('[data-hello]').textContent = 'Привет, ' + tgUser.first_name + '!';
    }

    /* ---------- storage: Telegram CloudStorage syncs the cart across devices ---------- */
    var cloud = since('6.9') ? W.CloudStorage : null;
    var store = {
        get: function (key, fallback) {
            try {
                var raw = localStorage.getItem('kolibri.tg.' + key);
                return raw ? JSON.parse(raw) : fallback;
            } catch (e) { return fallback; }
        },
        set: function (key, value) {
            var raw = JSON.stringify(value);
            try { localStorage.setItem('kolibri.tg.' + key, raw); } catch (e) { /* private mode */ }
            if (cloud && raw.length < 4000) { cloud.setItem(key, raw, function () {}); }
        },
        pull: function (keys, done) {
            if (!cloud) { done(); return; }
            var finished = false;
            var finish = function () { if (!finished) { finished = true; done(); } };
            setTimeout(finish, 1200);
            cloud.getItems(keys, function (err, values) {
                if (!err && values) {
                    keys.forEach(function (k) {
                        if (values[k]) { try { localStorage.setItem('kolibri.tg.' + k, values[k]); } catch (e) { /* ignore */ } }
                    });
                }
                finish();
            });
        }
    };

    /* ---------- helpers ---------- */
    function money(n) { return Math.round(n) + ' ' + C.currency; }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function restart(el, cls) { el.classList.remove(cls); void el.offsetWidth; el.classList.add(cls); }
    function plural(n, one, few, many) {
        var m10 = n % 10, m100 = n % 100;
        return m10 === 1 && m100 !== 11 ? one : m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20) ? few : many;
    }
    var toastTimer;
    function toast(text) {
        var t = $('[data-toast]');
        t.textContent = text;
        t.classList.add('is-on');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { t.classList.remove('is-on'); }, 2600);
    }
    function post(endpoint, data) {
        var body = new FormData();
        body.append('_csrf', D.csrf);
        Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
        return fetch(D.api + endpoint, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: inTg ? { 'X-Tg-Init-Data': W.initData } : {}
        }).then(function (r) {
            return r.json().catch(function () { return { error: 'Ошибка сервера. Попробуйте ещё раз.' }; });
        });
    }

    /* ---------- state ---------- */
    var state = {
        items: [],
        promo: '',
        mode: Object.keys(C.types)[0] || 'pickup',
        when: C.asap && C.openNow ? 'now' : 'later',
        day: '',
        time: '',
        priced: null
    };
    function loadState() {
        state.items = store.get('cart', []).filter(function (i) { return P[i.id] && i.qty > 0; });
        var me = store.get('me', {});
        if (me.mode && C.types[me.mode]) { state.mode = me.mode; }
    }
    function saveCart() { store.set('cart', state.items); }

    function qtyOf(id) {
        var it = state.items.filter(function (i) { return i.id === id; })[0];
        return it ? it.qty : 0;
    }
    function setQty(id, qty) {
        var p = P[id];
        qty = qty <= 0 ? 0 : Math.min(p.max, Math.max(p.min, qty));
        var it = state.items.filter(function (i) { return i.id === id; })[0];
        if (!qty) {
            state.items = state.items.filter(function (i) { return i.id !== id; });
        } else if (it) {
            it.qty = qty;
        } else {
            state.items.push({ id: id, qty: qty });
        }
        state.priced = null;
        saveCart();
        syncCards();
        refreshMain();
    }
    function localTotal() {
        var sum = 0, count = 0;
        state.items.forEach(function (i) { sum += P[i.id].price * i.qty; count += i.qty; });
        return { sum: sum, count: count };
    }

    /* ---------- catalog cards ---------- */
    function syncCards() {
        $$('[data-product]').forEach(function (card) {
            var q = qtyOf(+card.getAttribute('data-product'));
            var add = $('[data-add]', card);
            var el = $('[data-qty]', card);
            if (String(q) !== el.textContent) {
                el.textContent = q;
                if (q) { restart(add, 'bump'); }
            }
            add.classList.toggle('has-qty', q > 0);
        });
        TG.confirmClose(state.items.length > 0);
    }

    function flyFrom(img) {
        if (!img || !img.src) { return; }
        var from = img.getBoundingClientRect();
        var host = shell.getBoundingClientRect();
        var size = 56;
        var f = document.createElement('div');
        f.className = 'fly';
        f.style.cssText = 'left:' + (from.left - host.left + from.width / 2 - size / 2) + 'px;top:' + (from.top - host.top + from.height / 2 - size / 2) + 'px;width:' + size + 'px;height:' + size + 'px';
        f.innerHTML = '<img src="' + esc(img.src) + '" alt="" style="width:100%;height:100%;object-fit:cover">';
        shell.appendChild(f);
        var toX = host.width / 2 - (from.left - host.left + from.width / 2);
        var toY = host.height - 30 - (from.top - host.top + from.height / 2);
        requestAnimationFrame(function () {
            f.style.transform = 'translate(' + toX + 'px,' + toY + 'px) scale(.3)';
            f.style.opacity = '.2';
        });
        setTimeout(function () { f.remove(); }, 800);
    }

    document.addEventListener('click', function (e) {
        var card = e.target.closest('[data-product]');
        if (!card) { return; }
        var id = +card.getAttribute('data-product');
        if (e.target.closest('[data-inc]')) {
            if (!C.active) { toast('Магазин сейчас не принимает заказы'); return; }
            var first = qtyOf(id) === 0;
            setQty(id, qtyOf(id) + 1);
            TG.haptic(first ? 'medium' : 'light');
            if (first) { flyFrom($('img', card)); }
            return;
        }
        if (e.target.closest('[data-dec]')) {
            setQty(id, qtyOf(id) - 1);
            TG.haptic('light');
            return;
        }
        if (!e.target.closest('[data-add]')) { openProduct(id); }
    });

    // reveal cards as they scroll in
    if ('IntersectionObserver' in window) {
        var reveal = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) { en.target.classList.add('is-in'); reveal.unobserve(en.target); }
            });
        }, { root: scroller, rootMargin: '0px 0px -6% 0px' });
        $$('.card').forEach(function (c) { reveal.observe(c); });
    } else {
        $$('.card').forEach(function (c) { c.classList.add('is-in'); });
    }

    /* ---------- category chips: scroll spy + sliding ink ---------- */
    var chips = $('[data-chips]');
    var ink = $('[data-ink]');
    var activeCat = null;
    function moveInk(chip) {
        if (!chip) { return; }
        ink.style.width = chip.offsetWidth + 'px';
        ink.style.transform = 'translateX(' + chip.offsetLeft + 'px)';
        var track = chip.parentNode;
        track.scrollTo({ left: chip.offsetLeft - track.clientWidth / 2 + chip.offsetWidth / 2, behavior: 'smooth' });
    }
    function setActive(id) {
        if (id === activeCat) { return; }
        activeCat = id;
        var current = null;
        $$('[data-chip]', chips).forEach(function (c) {
            var on = c.getAttribute('data-chip') === id;
            c.classList.toggle('is-active', on);
            if (on) { current = c; }
        });
        moveInk(current);
    }
    var spyLock = 0;
    scroller.addEventListener('scroll', function () {
        if (chips) { chips.classList.toggle('is-stuck', chips.getBoundingClientRect().top <= scroller.getBoundingClientRect().top + 1); }
        if (Date.now() < spyLock) { return; }
        var top = scroller.getBoundingClientRect().top + 90;
        var id = null;
        $$('[data-cat]').forEach(function (s) { if (s.getBoundingClientRect().top <= top) { id = s.getAttribute('data-cat'); } });
        setActive(id || ($$('[data-cat]')[0] && $$('[data-cat]')[0].getAttribute('data-cat')));
    }, { passive: true });
    if (chips) {
        chips.addEventListener('click', function (e) {
            var chip = e.target.closest('[data-chip]');
            if (!chip) { return; }
            var id = chip.getAttribute('data-chip');
            var section = $('[data-cat="' + id + '"]');
            spyLock = Date.now() + 700;
            setActive(id);
            TG.haptic('select');
            scroller.scrollTo({ top: section.offsetTop - chips.offsetHeight + 6, behavior: 'smooth' });
        });
        var firstChip = $('[data-chip]', chips);
        if (firstChip) {
            // fonts change chip widths; place the ink once they are in
            setActive(firstChip.getAttribute('data-chip'));
            (document.fonts ? document.fonts.ready : Promise.resolve()).then(function () { moveInk($('.chip.is-active', chips)); });
        }
    }

    /* ---------- navigation: a stack of views; Telegram's BackButton pops it ---------- */
    var stack = [];
    function top() { return stack[stack.length - 1] || null; }
    function open(name) {
        var el = $('[data-view="' + name + '"]');
        var prev = top();
        if (prev && prev !== 'product') { $('[data-view="' + prev + '"]').classList.add('is-under'); }
        stack.push(name);
        el.setAttribute('aria-hidden', 'false');
        el.classList.add('is-open');
        syncChrome();
    }
    function close(name) {
        var el = $('[data-view="' + name + '"]');
        el.classList.remove('is-open');
        el.setAttribute('aria-hidden', 'true');
        stack = stack.filter(function (n) { return n !== name; });
        var prev = top();
        if (prev) { $('[data-view="' + prev + '"]').classList.remove('is-under'); }
        syncChrome();
    }
    function back() {
        var name = top();
        if (!name) { return; }
        if (name === 'done') { closeAll(); return; }
        close(name);
        TG.haptic('light');
        refreshMain();
    }
    function closeAll() {
        stack.slice().reverse().forEach(close);
        refreshMain();
    }
    function syncChrome() {
        var views = stack.filter(function (n) { return n !== 'product'; });
        shell.classList.toggle('has-view', views.length > 0);
        shell.classList.toggle('has-sheet', top() === 'product');
        TG.back(stack.length > 0 && top() !== 'done');
    }
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-back]')) { back(); }
        var go = e.target.closest('[data-go]');
        if (go && go.getAttribute('data-go') === 'orders') { openOrders(); }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { back(); } });

    // the main button follows whatever is on top
    function refreshMain() {
        var view = top();
        var t = localTotal();
        if (!view) {
            TG.main(t.count ? 'Мой заказ · ' + t.count + ' ' + plural(t.count, 'товар', 'товара', 'товаров') + ' · ' + money(t.sum) : '', function () { openCart(); });
        } else if (view === 'product') {
            var p = P[sheetId];
            TG.main((qtyOf(sheetId) ? 'Сохранить · ' : 'Добавить · ') + money(p.price * sheetQty), function () {
                setQty(sheetId, sheetQty);
                TG.haptic('medium');
                close('product');
                refreshMain();
            });
        } else if (view === 'cart') {
            var total = state.priced ? state.priced.total : money(t.sum);
            TG.main(state.items.length ? 'Оформить заказ · ' + total : '', function () { openCheckout(); });
        } else if (view === 'checkout') {
            TG.main('Заказать · ' + (state.priced ? state.priced.total : money(t.sum)), submit);
        } else if (view === 'done') {
            TG.main('Вернуться в каталог', function () { closeAll(); scroller.scrollTo({ top: 0, behavior: 'smooth' }); });
        } else {
            TG.main('В каталог', closeAll);
        }
    }

    /* ---------- product sheet ---------- */
    var sheet = $('[data-view="product"]');
    var sheetId = null;
    var sheetQty = 1;
    function openProduct(id) {
        var p = P[id];
        sheetId = id;
        sheetQty = qtyOf(id) || p.min;
        $('[data-p-img]', sheet).innerHTML = p.image ? '<img src="' + esc(p.image) + '" alt="">' : '';
        $('[data-p-name]', sheet).textContent = p.name;
        $('[data-p-unit]', sheet).textContent = p.unit;
        $('[data-p-price]', sheet).innerHTML = money(p.price) + (p.old && p.old > p.price ? ' <s>' + money(p.old) + '</s>' : '');
        $('[data-p-desc]', sheet).textContent = p.desc;
        var comp = '';
        if (p.comp) { comp += '<b>Состав:</b> ' + esc(p.comp); }
        Object.keys(p.size || {}).forEach(function (k) { comp += (comp ? '\n' : '') + '<b>' + esc(k) + ':</b> ' + esc(p.size[k]); });
        $('[data-p-comp]', sheet).innerHTML = comp;
        $('[data-p-qty]', sheet).textContent = sheetQty;
        $('.sheet__scroll', sheet).scrollTop = 0;
        open('product');
        TG.haptic('light');
        refreshMain();
    }
    sheet.addEventListener('click', function (e) {
        var p = P[sheetId];
        if (e.target.closest('[data-p-inc]')) { sheetQty = Math.min(p.max, sheetQty + 1); }
        else if (e.target.closest('[data-p-dec]')) { sheetQty = Math.max(p.min, sheetQty - 1); }
        else { return; }
        $('[data-p-qty]', sheet).textContent = sheetQty;
        TG.haptic('select');
        refreshMain();
    });
    // drag the sheet down to close
    (function () {
        var panel = $('[data-sheet-panel]');
        var body = $('.sheet__scroll', sheet);
        var startY = null, dy = 0;
        panel.addEventListener('touchstart', function (e) { startY = body.scrollTop <= 0 ? e.touches[0].clientY : null; dy = 0; }, { passive: true });
        panel.addEventListener('touchmove', function (e) {
            if (startY === null) { return; }
            dy = Math.max(0, e.touches[0].clientY - startY);
            if (dy > 0) { panel.style.transition = 'none'; panel.style.transform = 'translateY(' + dy + 'px)'; }
        }, { passive: true });
        panel.addEventListener('touchend', function () {
            panel.style.transition = '';
            panel.style.transform = '';
            if (startY !== null && dy > 110) { back(); }
            startY = null;
        });
    })();

    /* ---------- cart ---------- */
    var cartView = $('[data-view="cart"]');
    var promoInput = $('[data-promo]');
    function openCart() {
        open('cart');
        renderCart();
        reprice();
    }
    function renderCart() {
        var lines = state.priced ? state.priced.lines : state.items.map(function (i) {
            var p = P[i.id];
            return { id: i.id, qty: i.qty, gift: false, name: p.name, unit: p.unit, image: p.image, total: money(p.price * i.qty) };
        });
        $('[data-lines]', cartView).innerHTML = lines.map(function (l) {
            return '<div class="line' + (l.gift ? ' line--gift' : '') + '" data-line="' + l.id + '">'
                + '<div class="line__img ph">' + (l.image ? '<img src="' + esc(l.image) + '" alt="">' : '') + '</div>'
                + '<div class="line__main"><div class="line__name">' + esc(l.name) + '</div>'
                + (l.unit ? '<div class="line__unit">' + esc(l.unit) + '</div>' : '')
                + '<div class="line__row">' + (l.gift ? '🎁 Подарок' : '<div class="stepper stepper--sm"><button type="button" data-l-dec aria-label="Меньше">' + svg('minus') + '</button><b>' + l.qty + '</b><button type="button" data-l-inc aria-label="Больше">' + svg('plus') + '</button></div>')
                + '<span class="price">' + esc(l.total) + '</span></div></div></div>';
        }).join('');
        var empty = !state.items.length;
        $('[data-cart-empty]', cartView).hidden = !empty;
        $('[data-promo-box]', cartView).hidden = empty;
        $('[data-clear]', cartView).hidden = empty;
        renderTotals();
    }
    function svg(name) {
        var d = { plus: 'M12 5v14M5 12h14', minus: 'M5 12h14' }[name];
        return '<svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="' + d + '"/></svg>';
    }
    function renderTotals() {
        var pr = state.priced;
        var t = localTotal();
        var html = '<div class="trow"><span>Товары</span><b>' + (pr ? pr.subtotal : money(t.sum)) + '</b></div>';
        if (pr && pr.discount) { html += '<div class="trow"><span>Скидка' + (pr.promo && pr.promo.code ? ' · ' + esc(pr.promo.code) : '') + '</span><b>' + pr.discount + '</b></div>'; }
        if (pr && pr.delivery) { html += '<div class="trow"><span>Доставка</span><b>' + pr.delivery + '</b></div>'; }
        html += '<div class="trow trow--total"><span>Итого</span><b>' + (pr ? pr.total : money(t.sum)) + '</b></div>';
        (pr && pr.errors || []).forEach(function (err) { html += '<p class="trow trow--warn">' + esc(err) + '</p>'; });
        $$('[data-totals]').forEach(function (box) { box.innerHTML = html; box.hidden = !state.items.length; });
    }
    var repriceSeq = 0;
    function reprice() {
        if (!state.items.length) { state.priced = null; renderCart(); refreshMain(); return Promise.resolve(); }
        var seq = ++repriceSeq;
        return post('cart.php', {
            items: JSON.stringify(state.items),
            delivery_type: state.mode,
            promo_code: state.promo,
            phone: phoneDigits(),
            source: 'tg'
        }).then(function (res) {
            if (seq !== repriceSeq || res.error) { return; }
            state.priced = res;
            // the server may drop unavailable items or adjust quantities
            var server = {};
            res.lines.forEach(function (l) { if (!l.gift) { server[l.id] = l.qty; } });
            state.items = state.items.filter(function (i) { return server[i.id]; }).map(function (i) { return { id: i.id, qty: server[i.id] }; });
            saveCart();
            syncCards();
            var msg = $('[data-promo-msg]');
            msg.textContent = state.promo ? (res.promoError || (res.promo ? '✓ ' + (res.promo.name || 'Промокод применён') : '')) : '';
            msg.classList.toggle('is-error', !!res.promoError);
            if (top() === 'cart') { renderCart(); } else { renderTotals(); }
            refreshMain();
        }).catch(function () { /* offline: keep local totals */ });
    }
    cartView.addEventListener('click', function (e) {
        var line = e.target.closest('[data-line]');
        if (line) {
            var id = +line.getAttribute('data-line');
            var inc = e.target.closest('[data-l-inc]');
            var dec = e.target.closest('[data-l-dec]');
            if (!inc && !dec) { return; }
            var next = qtyOf(id) + (inc ? 1 : -1);
            TG.haptic('light');
            if (next <= 0) {
                line.classList.add('is-removing');
                setTimeout(function () { setQty(id, 0); renderCart(); reprice(); }, 300);
                return;
            }
            setQty(id, next);
            renderCart();
            reprice();
            return;
        }
        if (e.target.closest('[data-clear]')) {
            var doClear = function (ok) {
                if (!ok) { return; }
                state.items = [];
                state.priced = null;
                saveCart();
                syncCards();
                renderCart();
                refreshMain();
            };
            if (since('6.2')) { W.showConfirm('Очистить корзину?', doClear); } else { doClear(window.confirm('Очистить корзину?')); }
            return;
        }
        if (e.target.closest('[data-promo-apply]')) {
            state.promo = promoInput.value.trim().toUpperCase();
            TG.haptic('light');
            reprice();
        }
    });
    promoInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('[data-promo-apply]').click(); promoInput.blur(); } });

    /* ---------- checkout ---------- */
    var form = $('[data-form]');
    var phoneInput = $('[data-phone]');

    function phoneDigits() {
        var d = (phoneInput.value || '').replace(/\D/g, '');
        if (d.length === 10) { d = '7' + d; }
        if (d.charAt(0) === '8') { d = '7' + d.slice(1); }
        return d;
    }
    function formatPhone(v) {
        var d = v.replace(/\D/g, '');
        if (!d) { return ''; }
        if (d.charAt(0) === '8') { d = '7' + d.slice(1); }
        if (d.charAt(0) !== '7') { d = '7' + d; }
        d = d.slice(0, 11);
        var out = '+7';
        if (d.length > 1) { out += ' ' + d.slice(1, 4); }
        if (d.length > 4) { out += ' ' + d.slice(4, 7); }
        if (d.length > 7) { out += '-' + d.slice(7, 9); }
        if (d.length > 9) { out += '-' + d.slice(9, 11); }
        return out;
    }
    phoneInput.addEventListener('input', function () {
        var pos = phoneInput.selectionStart === phoneInput.value.length;
        phoneInput.value = formatPhone(phoneInput.value);
        if (pos) { phoneInput.setSelectionRange(phoneInput.value.length, phoneInput.value.length); }
    });

    // phone from Telegram in one tap
    var shareBtn = $('[data-share-phone]');
    if (since('6.9')) {
        shareBtn.hidden = false;
        shareBtn.addEventListener('click', function () {
            W.requestContact(function (ok, res) {
                var num = ok && res && res.responseUnsafe && res.responseUnsafe.contact ? res.responseUnsafe.contact.phone_number : '';
                if (num) {
                    phoneInput.value = formatPhone(num);
                    TG.haptic('success');
                    reprice();
                }
            });
        });
    }

    function segment(name, value) {
        var seg = $('[data-seg="' + name + '"]');
        if (!seg) { return; }
        var btns = $$('.seg__btn', seg);
        seg.style.setProperty('--count', btns.length);
        btns.forEach(function (b, i) {
            var on = b.getAttribute('data-' + name) === value;
            b.classList.toggle('is-active', on);
            if (on) { seg.style.setProperty('--i', i); }
        });
    }
    function setMode(mode) {
        state.mode = mode;
        segment('mode', mode);
        $$('[data-pane]', form).forEach(function (p) { p.classList.toggle('is-active', p.getAttribute('data-pane') === mode); });
        $('[data-ready]', form).hidden = mode !== 'pickup';
    }
    function setWhen(when) {
        if (when === 'now' && !(C.asap && C.openNow)) { when = 'later'; }
        if (when === 'later' && !C.preorders) { when = 'now'; }
        state.when = when;
        segment('when', when);
        $('[data-when-pane="now"]', form).hidden = when !== 'now';
        $('[data-when-pane="later"]', form).hidden = when !== 'later';
        $('[data-closed]', form).hidden = !(C.asap && !C.openNow);
        var whenSeg = $('[data-seg="when"]', form);
        whenSeg.hidden = !(C.asap && C.openNow && C.preorders);
        if (when === 'later') { renderSlots(); }
    }

    // pre-order slots on the shop's clock, not the phone's
    var loadedAt = Date.now();
    var shopStart = (function () {
        var m = String(C.now || '').match(/^(\d+)-(\d+)-(\d+)T(\d+):(\d+):(\d+)/);
        return m ? new Date(+m[1], m[2] - 1, +m[3], +m[4], +m[5], +m[6]) : new Date();
    })();
    function shopNow() { return new Date(shopStart.getTime() + (Date.now() - loadedAt)); }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    var weekdays = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];
    var months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
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
            days.push({
                value: day.getFullYear() + '-' + pad(day.getMonth() + 1) + '-' + pad(day.getDate()),
                top: d === 0 ? 'Сегодня' : d === 1 ? 'Завтра' : weekdays[day.getDay()],
                sub: day.getDate() + ' ' + months[day.getMonth()],
                times: times
            });
        }
        return days;
    }
    var slotDays = [];
    function renderSlots() {
        slotDays = buildSlots();
        if (!slotDays.some(function (d) { return d.value === state.day; })) { state.day = slotDays[0] ? slotDays[0].value : ''; }
        $('[data-days]', form).innerHTML = slotDays.map(function (d) {
            return '<button type="button" class="slot' + (d.value === state.day ? ' is-active' : '') + '" data-day="' + d.value + '">' + d.top + '<small>' + d.sub + '</small></button>';
        }).join('') || '<p class="hint">Нет свободного времени — позвоните нам.</p>';
        var day = slotDays.filter(function (d) { return d.value === state.day; })[0];
        if (!day || day.times.indexOf(state.time) === -1) { state.time = day ? day.times[0] : ''; }
        $('[data-times]', form).innerHTML = day ? day.times.map(function (t) {
            return '<button type="button" class="slot' + (t === state.time ? ' is-active' : '') + '" data-time="' + t + '">' + t + '</button>';
        }).join('') : '';
    }

    form.addEventListener('click', function (e) {
        var b = e.target.closest('[data-mode]');
        if (b) { setMode(b.getAttribute('data-mode')); TG.haptic('select'); reprice(); saveMe(); return; }
        b = e.target.closest('[data-when]');
        if (b) { setWhen(b.getAttribute('data-when')); TG.haptic('select'); return; }
        b = e.target.closest('[data-day]');
        if (b) { state.day = b.getAttribute('data-day'); state.time = ''; renderSlots(); TG.haptic('select'); return; }
        b = e.target.closest('[data-time]');
        if (b) { state.time = b.getAttribute('data-time'); renderSlots(); TG.haptic('select'); }
    });
    form.addEventListener('change', function (e) {
        if (e.target.name === 'payment_method') { syncPayment(); TG.haptic('select'); }
        if (e.target.name === 'pickup_point_id') { TG.haptic('select'); }
        saveMe();
    });
    form.addEventListener('submit', function (e) { e.preventDefault(); submit(); });
    function syncPayment() {
        var pay = form.elements.payment_method;
        $('[data-change]', form).hidden = !(C.askChange && pay && pay.value === 'cash');
    }

    function saveMe() {
        var addr = {};
        $$('[data-addr]', form).forEach(function (i) { addr[i.getAttribute('data-addr')] = i.value.trim(); });
        var point = form.elements.pickup_point_id;
        store.set('me', {
            name: form.elements.name.value.trim(),
            phone: phoneInput.value,
            mode: state.mode,
            addr: addr,
            point: point ? point.value : ''
        });
    }
    function fillMe() {
        var me = store.get('me', {});
        if (!form.elements.name.value) { form.elements.name.value = me.name || (tgUser ? [tgUser.first_name, tgUser.last_name].filter(Boolean).join(' ') : ''); }
        if (!phoneInput.value && me.phone) { phoneInput.value = me.phone; }
        $$('[data-addr]', form).forEach(function (i) { if (!i.value && me.addr) { i.value = me.addr[i.getAttribute('data-addr')] || ''; } });
        if (me.point) {
            $$('input[name="pickup_point_id"]', form).forEach(function (r) { r.checked = r.value === me.point; });
        }
    }

    function openCheckout() {
        if (!C.active) { toast('Магазин сейчас не принимает заказы'); return; }
        fillMe();
        setMode(state.mode);
        setWhen(state.when);
        syncPayment();
        $('[data-error]', form).textContent = '';
        open('checkout');
        TG.haptic('light');
        reprice();
    }

    function fail(text, input) {
        var err = $('[data-error]', form);
        err.textContent = text;
        restart(err, 'shake');
        TG.haptic('error');
        if (input) {
            var field = input.closest('.field');
            if (field) { restart(field, 'is-invalid'); }
            input.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }

    var sending = false;
    function submit() {
        if (sending) { return; }
        var f = form.elements;
        if (!f.name.value.trim()) { fail('Как к вам обращаться?', f.name); return; }
        if (phoneDigits().length !== 11) { fail('Проверьте номер телефона', phoneInput); return; }
        if (state.mode === 'delivery' && f.address.value.trim().length < 5) { fail('Укажите адрес доставки', f.address); return; }
        if (state.mode === 'pickup' && !(f.pickup_point_id && f.pickup_point_id.value)) { fail('Выберите пункт самовывоза'); return; }
        if (state.when === 'later' && !state.time) { fail('Выберите время получения'); return; }

        var data = {
            items: JSON.stringify(state.items),
            source: 'tg',
            website: f.website.value,
            name: f.name.value.trim(),
            phone: phoneInput.value,
            delivery_type: state.mode,
            pickup_point_id: state.mode === 'pickup' && f.pickup_point_id ? f.pickup_point_id.value : '',
            address: state.mode === 'delivery' ? (C.city && f.address.value.indexOf(C.city) === -1 ? C.city + ', ' : '') + f.address.value.trim() : '',
            apartment: f.apartment.value,
            entrance: f.entrance.value,
            floor: f.floor.value,
            when: state.when,
            date: state.when === 'later' ? state.day : '',
            time: state.when === 'later' ? state.time : '',
            ready_in: f.ready_in.value,
            payment_method: f.payment_method ? f.payment_method.value : '',
            change_from: f.change_from.value,
            comment: f.comment.value,
            promo_code: state.promo
        };
        if (inTg) { data.tg_init = W.initData; }

        sending = true;
        TG.busy(true);
        $('[data-error]', form).textContent = '';
        saveMe();
        post('order.php', data).then(function (res) {
            if (res.error) { fail(res.error); return; }
            showDone(res, data.payment_method);
            state.items = [];
            state.priced = null;
            state.promo = '';
            promoInput.value = '';
            f.comment.value = '';
            saveCart();
            syncCards();
        }).catch(function () {
            fail('Нет соединения. Проверьте интернет и попробуйте ещё раз.');
        }).then(function () {
            sending = false;
            TG.busy(false);
        });
    }

    function showDone(res, payment) {
        var done = $('[data-view="done"]');
        $('[data-done-id]', done).textContent = '№' + res.id;
        $('[data-done-msg]', done).textContent = res.message;
        $('[data-done-place]', done).textContent = res.place;
        $('[data-done-time]', done).textContent = res.time;
        $('[data-done-total]', done).textContent = res.total;
        var pay = $('[data-done-pay]', done);
        if (pay) {
            pay.hidden = !(payment === 'transfer' && D.transfer);
            pay.textContent = 'Реквизиты для перевода:\n' + D.transfer;
        }
        $('.done__tg', done).hidden = !inTg;
        stack.slice().reverse().forEach(function (n) { $('[data-view="' + n + '"]').classList.remove('is-open', 'is-under'); });
        stack = [];
        open('done');
        TG.haptic('success');
        TG.confirmClose(false);
        refreshMain();
    }

    /* ---------- my orders ---------- */
    function openOrders() {
        var box = $('[data-orders]');
        open('orders');
        TG.haptic('light');
        refreshMain();
        if (!inTg) {
            box.innerHTML = '<div class="empty"><p>Откройте приложение из Telegram — здесь появятся ваши заказы и их статусы.</p></div>';
            return;
        }
        box.innerHTML = '<div class="empty"><p>Загружаем…</p></div>';
        post('tg-orders.php', { tg_init: W.initData }).then(function (res) {
            if (res.error) { box.innerHTML = '<div class="empty"><p>' + esc(res.error) + '</p></div>'; return; }
            if (!res.orders.length) {
                box.innerHTML = '<div class="empty"><p>Заказов пока нет.<br>Самое время выбрать букет 💐</p></div>';
                return;
            }
            var steps = ['new', 'accepted', 'delivering', 'done'];
            box.innerHTML = res.orders.map(function (o, n) {
                var at = o.status === 'ready' ? 2 : steps.indexOf(o.status);
                var track = o.status === 'cancelled' ? '' : '<div class="track">' + steps.map(function (s, i) { return '<i class="' + (i <= at ? 'is-on' : '') + '" style="animation-delay:' + (i * .12) + 's"></i>'; }).join('') + '</div>';
                return '<div class="order" style="animation-delay:' + (n * .06) + 's"><div class="order__head"><b>Заказ №' + o.id + '</b><span class="pill pill--' + esc(o.status) + '">' + esc(o.label) + '</span></div>'
                    + '<div class="order__date">' + esc(o.date) + ' · ' + esc(o.place) + '</div>'
                    + '<div class="order__items">' + o.items.map(esc).join('<br>') + '</div>'
                    + track + '<div class="order__foot"><span>Итого</span><span>' + esc(o.total) + '</span></div></div>';
            }).join('');
        }).catch(function () { box.innerHTML = '<div class="empty"><p>Нет соединения.</p></div>'; });
    }

    /* ---------- start ---------- */
    loadState();
    syncCards();
    setMode(state.mode);
    refreshMain();
    store.pull(['cart', 'me'], function () {
        loadState();
        syncCards();
        setMode(state.mode);
        refreshMain();
    });
})();
