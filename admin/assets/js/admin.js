(function () {
    'use strict';

    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

    var storage = {
        get: function (key) { try { return localStorage.getItem(key); } catch (e) { return null; } },
        set: function (key, value) { try { localStorage.setItem(key, value); } catch (e) { /* private mode */ } }
    };

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

    /* ---------- dashboard ---------- */

    var cards = $$('[data-chart]');
    if (!cards.length) {
        return;
    }

    var grid = $('.dash-grid');
    var currency = grid.getAttribute('data-currency') || '₽';
    var nf = new Intl.NumberFormat('ru-RU');
    var fmtMoney = function (v) { return nf.format(v) + ' ' + currency; };
    var fmtCount = function (v) { return nf.format(v); };

    var BLUE = '#1d7cf2';
    var METRICS = {
        revenue_orders: {
            empty: function (d) { return d.totals.orders === 0; },
            series: function (d) {
                return [
                    { name: 'Заказы', values: d.orders, type: 'bar', color: '#cfe1fd', axis: 'right', format: fmtCount, integer: true },
                    { name: 'Выручка', values: d.revenue, type: 'line', color: BLUE, axis: 'left', format: fmtMoney }
                ];
            },
            summary: function (d) {
                return [['Выручка', fmtMoney(d.totals.revenue), BLUE], ['Заказы', fmtCount(d.totals.orders), '#9cc3fb']];
            }
        },
        revenue: {
            empty: function (d) { return d.totals.orders === 0; },
            series: function (d) { return [{ name: 'Выручка', values: d.revenue, type: 'line', color: BLUE, format: fmtMoney }]; },
            summary: function (d) { return [['Всего', fmtMoney(d.totals.revenue)]]; }
        },
        orders: {
            empty: function (d) { return d.totals.orders === 0; },
            series: function (d) { return [{ name: 'Заказы', values: d.orders, type: 'bar', color: BLUE, format: fmtCount, integer: true }]; },
            summary: function (d) { return [['Всего', fmtCount(d.totals.orders)]]; }
        },
        avg_check: {
            empty: function (d) { return d.totals.orders === 0; },
            series: function (d) { return [{ name: 'Средний чек', values: d.avg_check, type: 'line', color: '#7c5cff', format: fmtMoney }]; },
            summary: function (d) { return [['За период', fmtMoney(d.totals.avg_check)]]; }
        },
        new_clients: {
            empty: function (d) { return d.totals.new_clients === 0; },
            series: function (d) { return [{ name: 'Новые клиенты', values: d.new_clients, type: 'bar', color: '#1aa333', format: fmtCount, integer: true }]; },
            summary: function (d) { return [['Всего', fmtCount(d.totals.new_clients)]]; }
        }
    };

    var cache = {};
    function load(period, force) {
        if (force || !cache[period]) {
            cache[period] = fetch('api/stats.php?period=' + encodeURIComponent(period), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            }).then(function (res) {
                if (res.status === 401) {
                    location.href = 'login.php';
                }
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.json();
            }).catch(function (err) {
                delete cache[period];
                throw err;
            });
        }
        return cache[period];
    }

    function emptyState(body, text) {
        body.innerHTML = '<div class="chart-empty">' + text + '</div>';
    }

    function drawCard(card, animate) {
        var body = $('[data-chart-body]', card);
        var metric = METRICS[card.getAttribute('data-chart')];
        var data = card._data;
        if (!data) {
            return;
        }
        if (metric.empty(data)) {
            $('[data-chart-summary]', card).innerHTML = '';
            emptyState(body, 'Нет данных за выбранный период');
            return;
        }
        window.KChart.render(body, { labels: data.labels, series: metric.series(data), animate: animate });
    }

    function renderCard(card, force) {
        var period = card._period;
        var metric = METRICS[card.getAttribute('data-chart')];
        var body = $('[data-chart-body]', card);
        var summary = $('[data-chart-summary]', card);

        card.classList.add('is-loading');
        load(period, force).then(function (data) {
            if (card._period !== period) {
                return; // period changed while loading
            }
            card._data = data;
            summary.innerHTML = metric.empty(data) ? '' : metric.summary(data).map(function (item) {
                var dot = item[2] ? '<span class="legend-dot" style="background:' + item[2] + '"></span>' : '';
                return '<div class="summary-item">' + dot + item[0] + '<b>' + item[1] + '</b></div>';
            }).join('');
            drawCard(card, true);
        }).catch(function () {
            summary.innerHTML = '';
            emptyState(body, 'Не удалось загрузить данные');
        }).then(function () {
            card.classList.remove('is-loading');
        });
    }

    function setSelect(select, value) {
        $$('.dropdown-item', select).forEach(function (item) {
            var active = item.getAttribute('data-value') === value;
            item.classList.toggle('is-active', active);
            if (active) {
                $('[data-period-label]', select).textContent = item.firstChild.textContent;
            }
        });
    }

    function downloadCsv(card) {
        var data = card._data;
        if (!data) {
            return;
        }
        var series = METRICS[card.getAttribute('data-chart')].series(data);
        var rows = [['Период'].concat(series.map(function (s) { return s.name; }))];
        data.labels.forEach(function (label, i) {
            rows.push([label].concat(series.map(function (s) { return s.values[i]; })));
        });
        var csv = '﻿' + rows.map(function (r) {
            return r.map(function (cell) { return '"' + String(cell).replace(/"/g, '""') + '"'; }).join(';');
        }).join('\r\n');
        var link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        link.download = card.getAttribute('data-chart') + '-' + data.period + '.csv';
        document.body.appendChild(link);
        link.click();
        setTimeout(function () { URL.revokeObjectURL(link.href); link.remove(); }, 0);
    }

    var pageSelect = $('.page-period');
    var initial = storage.get('kolibri.dashboard.period') || 'week';
    if (!$('[data-value="' + initial + '"]', pageSelect)) {
        initial = 'week';
    }
    setSelect(pageSelect, initial);

    cards.forEach(function (card) {
        card._period = initial;
        setSelect($('[data-period-select]', card), initial);
        renderCard(card);

        // redraw (without animation) when the card changes size
        var body = $('[data-chart-body]', card);
        var lastWidth = 0;
        var frame = 0;
        new ResizeObserver(function (entries) {
            var width = Math.round(entries[0].contentRect.width);
            if (width === lastWidth) {
                return;
            }
            var first = lastWidth === 0;
            lastWidth = width;
            if (first) {
                return;
            }
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(function () { drawCard(card, false); });
        }).observe(body);
    });

    document.addEventListener('click', function (e) {
        var option = e.target.closest('[data-period-select] .dropdown-item');
        if (option) {
            var select = option.closest('[data-period-select]');
            var value = option.getAttribute('data-value');
            setSelect(select, value);

            if (select === pageSelect) {
                storage.set('kolibri.dashboard.period', value);
                cards.forEach(function (card) {
                    setSelect($('[data-period-select]', card), value);
                    if (card._period !== value) {
                        card._period = value;
                        renderCard(card);
                    }
                });
            } else {
                var card = select.closest('[data-chart]');
                card._period = value;
                renderCard(card);
            }
            return;
        }

        var action = e.target.closest('[data-chart-action]');
        if (action) {
            var target = action.closest('[data-chart]');
            if (action.getAttribute('data-chart-action') === 'csv') {
                downloadCsv(target);
            } else {
                renderCard(target, true);
            }
        }
    });
})();
