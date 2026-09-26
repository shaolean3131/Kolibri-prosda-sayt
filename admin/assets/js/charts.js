/*
 * Tiny SVG chart renderer for the dashboard: bars, smooth lines and a
 * bars + line combo with two axes. No dependencies.
 *
 *   KChart.render(container, {
 *     labels: ['Пн 1', ...],
 *     series: [{ name, values, type: 'bar'|'line', color, axis: 'left'|'right', format, integer }],
 *     animate: true
 *   });
 */
(function () {
    'use strict';

    var NS = 'http://www.w3.org/2000/svg';
    var gradientId = 0;

    function svg(name, attrs, parent) {
        var node = document.createElementNS(NS, name);
        for (var key in attrs) {
            node.setAttribute(key, attrs[key]);
        }
        if (parent) {
            parent.appendChild(node);
        }
        return node;
    }

    function niceStep(raw) {
        if (raw <= 0) {
            return 1;
        }
        var exp = Math.pow(10, Math.floor(Math.log10(raw)));
        var f = raw / exp;
        var nice = f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10;
        return nice * exp;
    }

    function shortNumber(v) {
        var fmt = function (n) { return String(Math.round(n * 10) / 10).replace('.', ','); };
        if (v >= 1e6) {
            return fmt(v / 1e6) + ' млн';
        }
        if (v >= 1e3) {
            return fmt(v / 1e3) + ' тыс';
        }
        return fmt(v);
    }

    function smoothPath(points) {
        var d = 'M' + points[0][0] + ',' + points[0][1];
        for (var i = 1; i < points.length; i++) {
            var p0 = points[i - 1];
            var p1 = points[i];
            var mx = (p1[0] - p0[0]) / 2;
            d += ' C' + (p0[0] + mx) + ',' + p0[1] + ' ' + (p1[0] - mx) + ',' + p1[1] + ' ' + p1[0] + ',' + p1[1];
        }
        return d;
    }

    function render(container, cfg) {
        container.innerHTML = '';

        var wrap = document.createElement('div');
        wrap.className = 'chart' + (cfg.animate === false ? ' no-anim' : '');
        container.appendChild(wrap);

        var rect = container.getBoundingClientRect();
        var w = Math.max(rect.width, 120);
        var h = Math.max(rect.height, 120);
        var labels = cfg.labels;
        var n = labels.length;
        var hasRight = cfg.series.some(function (s) { return s.axis === 'right'; });
        var pad = { t: 10, r: hasRight ? 40 : 6, b: 28, l: 48 };
        var plotW = w - pad.l - pad.r;
        var plotH = h - pad.t - pad.b;
        var band = plotW / n;
        var ticks = 4;

        // scales per axis
        var axes = {};
        var integerAxis = {};
        cfg.series.forEach(function (s) {
            var axis = s.axis || 'left';
            var max = Math.max.apply(null, s.values.concat([0]));
            axes[axis] = Math.max(axes[axis] || 0, max);
            integerAxis[axis] = integerAxis[axis] !== false && !!s.integer;
        });
        Object.keys(axes).forEach(function (axis) {
            var step = niceStep(axes[axis] / ticks);
            axes[axis] = (integerAxis[axis] ? Math.max(1, Math.ceil(step)) : step) * ticks;
        });
        var y = function (value, axis) {
            return pad.t + plotH - (value / axes[axis || 'left']) * plotH;
        };
        var cx = function (i) { return pad.l + band * (i + 0.5); };

        var root = svg('svg', { viewBox: '0 0 ' + w + ' ' + h, role: 'img' }, wrap);
        var defs = svg('defs', {}, root);

        // grid + y labels
        var grid = svg('g', { class: 'chart__grid' }, root);
        var axisG = svg('g', { class: 'chart__axis' }, root);
        for (var t = 0; t <= ticks; t++) {
            var gy = pad.t + plotH - (plotH / ticks) * t;
            svg('line', { x1: pad.l, x2: w - pad.r, y1: gy, y2: gy }, grid);
            Object.keys(axes).forEach(function (axis) {
                var text = svg('text', {
                    x: axis === 'left' ? pad.l - 10 : w - pad.r + 10,
                    y: gy + 4,
                    'text-anchor': axis === 'left' ? 'end' : 'start'
                }, axisG);
                text.textContent = shortNumber((axes[axis] / ticks) * t);
            });
        }

        // x labels (thinned out so they never overlap)
        var every = Math.max(1, Math.ceil(n / Math.max(1, Math.floor(plotW / 58))));
        labels.forEach(function (label, i) {
            if (i % every !== 0) {
                return;
            }
            var text = svg('text', { x: cx(i), y: h - 6, 'text-anchor': 'middle' }, axisG);
            text.textContent = label;
        });

        // bars
        var bars = [];
        var barSeries = cfg.series.filter(function (s) { return s.type === 'bar'; });
        barSeries.forEach(function (s) {
            var bw = Math.max(2, Math.min(band * 0.56, 34));
            var g = svg('g', {}, root);
            s.values.forEach(function (v, i) {
                if (v <= 0) {
                    bars[i] = bars[i] || [];
                    return;
                }
                var top = y(v, s.axis);
                var bar = svg('rect', {
                    class: 'chart__bar',
                    x: cx(i) - bw / 2,
                    y: top,
                    width: bw,
                    height: Math.max(1, pad.t + plotH - top),
                    rx: Math.min(5, bw / 3),
                    fill: s.color
                }, g);
                bar.style.animationDelay = (i * (0.35 / n)).toFixed(3) + 's';
                (bars[i] = bars[i] || []).push(bar);
            });
        });

        // lines
        var guide = svg('line', { class: 'chart__guide', y1: pad.t, y2: pad.t + plotH }, root);
        var dots = [];
        cfg.series.filter(function (s) { return s.type === 'line'; }).forEach(function (s) {
            var points = s.values.map(function (v, i) { return [cx(i), y(v, s.axis)]; });
            var id = 'kchart-grad-' + (++gradientId);
            var grad = svg('linearGradient', { id: id, x1: 0, y1: 0, x2: 0, y2: 1 }, defs);
            svg('stop', { offset: 0, 'stop-color': s.color, 'stop-opacity': 0.16 }, grad);
            svg('stop', { offset: 1, 'stop-color': s.color, 'stop-opacity': 0 }, grad);

            var line = smoothPath(points);
            var base = pad.t + plotH;
            svg('path', {
                class: 'chart__area',
                d: line + ' L' + points[n - 1][0] + ',' + base + ' L' + points[0][0] + ',' + base + ' Z',
                fill: 'url(#' + id + ')'
            }, root);
            var path = svg('path', { class: 'chart__line', d: line, stroke: s.color }, root);
            var len = Math.ceil(path.getTotalLength());
            path.style.strokeDasharray = len;
            path.style.setProperty('--len', len);

            dots.push(points.map(function (p) {
                return svg('circle', { class: 'chart__dot', cx: p[0], cy: p[1], r: 5, fill: s.color }, root);
            }));
        });
        dots.forEach(function (list) {
            list.forEach(function (dot) { dot.style.display = 'none'; });
        });

        // hover
        var tip = document.createElement('div');
        tip.className = 'chart-tip';
        wrap.appendChild(tip);
        var overlay = svg('rect', { x: pad.l, y: 0, width: plotW, height: h, fill: 'transparent' }, root);
        var current = -1;

        function show(i) {
            if (i === current) {
                return;
            }
            if (current >= 0) {
                (bars[current] || []).forEach(function (b) { b.classList.remove('is-hover'); });
                dots.forEach(function (list) { list[current].style.display = 'none'; });
            }
            current = i;
            (bars[i] || []).forEach(function (b) { b.classList.add('is-hover'); });
            dots.forEach(function (list) { list[i].style.display = ''; });
            guide.setAttribute('x1', cx(i));
            guide.setAttribute('x2', cx(i));

            var html = '<div class="chart-tip__label">' + labels[i] + '</div>';
            cfg.series.forEach(function (s) {
                html += '<div class="chart-tip__row"><span><span class="legend-dot" style="background:' + s.color + '"></span>'
                    + s.name + '</span><b>' + s.format(s.values[i]) + '</b></div>';
            });
            tip.innerHTML = html;
            var left = cx(i) + 14;
            if (left + tip.offsetWidth > w) {
                left = cx(i) - 14 - tip.offsetWidth;
            }
            tip.style.left = Math.max(0, left) + 'px';
            tip.style.top = pad.t + 'px';
            wrap.classList.add('is-hover');
        }

        function hide() {
            wrap.classList.remove('is-hover');
        }

        overlay.addEventListener('pointermove', function (e) {
            var box = root.getBoundingClientRect();
            var x = (e.clientX - box.left) * (w / box.width) - pad.l;
            show(Math.min(n - 1, Math.max(0, Math.floor(x / band))));
        });
        overlay.addEventListener('pointerleave', hide);
    }

    window.KChart = { render: render };
})();
