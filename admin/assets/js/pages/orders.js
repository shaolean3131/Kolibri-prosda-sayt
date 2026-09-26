(function () {
    'use strict';

    var UI = window.UI;
    var $ = UI.$;
    var $$ = UI.$$;
    var API = 'api/orders.php';
    var modal = $('[data-modal="order"]');
    var body = $('[data-order-body]', modal);
    var statuses = JSON.parse(modal.getAttribute('data-statuses'));
    var reasons = JSON.parse(modal.getAttribute('data-reasons'));
    var flow = ['new', 'accepted', 'ready', 'delivering', 'done'];
    var current = null;

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function render(o) {
        current = o;
        $('[data-o="title"]', modal).textContent = 'Заказ #' + o.id;
        $('[data-o="sub"]', modal).textContent = o.date + ' · ' + o.source;

        var steps = flow.filter(function (s) { return o.type === 'pickup' ? s !== 'delivering' : s !== 'ready'; });
        var reached = steps.indexOf(o.status);
        var html = '<div class="steps' + (o.status === 'cancelled' ? ' is-cancelled' : '') + '">';
        steps.forEach(function (s, i) {
            var label = s === 'done' && o.type === 'pickup' ? 'Выдан' : statuses[s].label;
            html += '<button class="step' + (i < reached ? ' is-done' : '') + (i === reached ? ' is-active' : '') + '" type="button" data-set="' + s + '">'
                + '<span class="step__dot"></span>' + esc(label) + '</button>';
        });
        html += '</div>';
        if (o.status === 'cancelled') {
            html += '<div class="order-cancelled">Заказ отменён' + (o.reason ? ': ' + esc(o.reason) : '') + ' <button class="link-btn" type="button" data-set="new">Вернуть в работу</button></div>';
        }

        html += '<div class="order-grid">'
            + block('Клиент', '<b>' + esc(o.name) + '</b><br><a class="table__link" href="tel:' + esc(o.phone.replace(/[^\d+]/g, '')) + '">' + esc(o.phone) + '</a>')
            + block(o.type === 'pickup' ? 'Самовывоз' : 'Доставка', esc(o.place) + '<br><span class="muted">' + esc(o.time) + '</span>')
            + '</div>';

        var items = o.items.map(function (i) {
            return '<div class="order-line"><span>' + esc(i.name) + ' <span class="muted">' + esc(i.unit) + '</span></span><span class="order-line__qty">× ' + i.qty + '</span><b>' + esc(i.total) + '</b></div>';
        }).join('');
        var sum = '<div class="order-sum"><span>Товары</span><span>' + esc(o.subtotal) + '</span></div>';
        if (o.discount) {
            sum += '<div class="order-sum"><span>Скидка' + (o.promo ? ' · ' + esc(o.promo) : '') + '</span><span class="green">' + esc(o.discount) + '</span></div>';
        }
        if (o.delivery) {
            sum += '<div class="order-sum"><span>Доставка</span><span>' + esc(o.delivery) + '</span></div>';
        }
        sum += '<div class="order-sum order-sum--total"><span>Итого</span><span>' + esc(o.total) + '</span></div>';
        html += block('Состав заказа', items + sum);
        html += block('Оплата', esc(o.payment));
        if (o.comment) {
            html += block('Комментарий', esc(o.comment).replace(/\n/g, '<br>'));
        }

        if (o.status !== 'cancelled') {
            html += '<div class="order-cancel"><button class="btn btn--light btn--sm danger-text" type="button" data-cancel-open>Отменить заказ</button>'
                + '<div class="order-cancel__reasons" hidden>' + reasons.map(function (r) {
                    return '<button class="chip-btn" type="button" data-cancel="' + esc(r) + '">' + esc(r) + '</button>';
                }).join('') + '</div></div>';
        }
        body.innerHTML = html;
    }

    function block(title, content) {
        return '<section class="order-block"><h3 class="order-block__title">' + esc(title) + '</h3><div class="order-block__body">' + content + '</div></section>';
    }

    function open(id) {
        body.innerHTML = '<div class="skeleton skeleton--order"></div>';
        $('[data-o="title"]', modal).textContent = 'Заказ #' + id;
        $('[data-o="sub"]', modal).textContent = '';
        UI.openModal(modal);
        UI.api(API, { action: 'get', id: id }).then(function (res) { render(res.order); })
            .catch(function () { UI.closeModal(modal); });
    }

    function setStatus(status, reason) {
        UI.api(API, { action: 'status', id: current.id, status: status, reason: reason || '' }).then(function (res) {
            render(res.order);
            var row = $('[data-order="' + current.id + '"]');
            if (row) {
                var cell = $('[data-status-cell]', row);
                cell.className = 'status status--' + statuses[status].color + ' pop';
                cell.textContent = statuses[status].label;
                row.classList.toggle('is-new', status === 'new');
            }
            UI.toast('Статус: ' + statuses[status].label);
        });
    }

    body.addEventListener('click', function (e) {
        var set = e.target.closest('[data-set]');
        if (set) {
            setStatus(set.getAttribute('data-set'));
            return;
        }
        if (e.target.closest('[data-cancel-open]')) {
            $('.order-cancel__reasons', body).hidden = false;
            e.target.closest('[data-cancel-open]').hidden = true;
            return;
        }
        var cancel = e.target.closest('[data-cancel]');
        if (cancel) {
            setStatus('cancelled', cancel.getAttribute('data-cancel'));
        }
    });

    document.addEventListener('click', function (e) {
        var phone = e.target.closest('[data-order-phone]');
        if (phone) {
            UI.api(API, { action: 'phone', id: phone.getAttribute('data-order-phone') }).then(function (res) {
                var link = document.createElement('a');
                link.className = 'phone is-shown';
                link.href = 'tel:' + res.phone.replace(/[^\d+]/g, '');
                link.textContent = res.phone;
                phone.replaceWith(link);
            });
            return;
        }
        var row = e.target.closest('[data-order]');
        if (row && !e.target.closest('a, button')) {
            open(row.getAttribute('data-order'));
        }
    });

    document.addEventListener('keydown', function (e) {
        var row = e.target.closest && e.target.closest('[data-order]');
        if (row && e.key === 'Enter') {
            open(row.getAttribute('data-order'));
        }
    });

    var openId = +modal.getAttribute('data-open-order');
    if (openId) {
        open(openId);
    }
})();
