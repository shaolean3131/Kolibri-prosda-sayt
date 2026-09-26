(function () {
    'use strict';

    var UI = window.UI;
    var $ = UI.$;
    var $$ = UI.$$;
    var API = 'api/promotions.php';
    var form = $('[data-promo-form]');
    var modal = form.closest('.modal');
    var typeNames = {};
    $$('[data-promo-type] option', form).forEach(function (o) { typeNames[o.value] = o.textContent; });

    /* ---------- accordion ---------- */

    form.addEventListener('click', function (e) {
        var head = e.target.closest('[data-acc-toggle]');
        if (head) {
            head.closest('[data-acc]').classList.toggle('is-open');
        }
    });

    /* ---------- dependent fields ---------- */

    function formatDate(value) {
        if (!value) {
            return '…';
        }
        var d = new Date(value);
        return ('0' + d.getDate()).slice(-2) + '.' + ('0' + (d.getMonth() + 1)).slice(-2) + '.' + d.getFullYear();
    }

    function sync() {
        var unlimited = form.elements.unlimited.checked;
        $('[data-dates]', form).classList.toggle('is-disabled', unlimited);
        $$('[data-dates] input', form).forEach(function (input) { input.disabled = unlimited; });
        $('[data-period-chip]', form).textContent = unlimited
            ? 'Бессрочный'
            : formatDate(form.elements.starts_at.value) + ' – ' + formatDate(form.elements.ends_at.value);

        var type = form.elements.type.value;
        $$('[data-show-for]', form).forEach(function (el) {
            el.hidden = el.getAttribute('data-show-for').split(' ').indexOf(type) === -1;
        });
        $('[data-value-label]', form).textContent = type === 'percent' ? 'Размер скидки, %' : 'Размер скидки, ₽';
        var value = form.elements.value.value;
        $('[data-type-chip]', form).textContent = type === 'percent' && value ? '−' + value + '%'
            : type === 'fixed' && value ? '−' + value + ' ₽'
            : typeNames[type];

        var byCategory = form.querySelector('[name="applies_to"]:checked').value === 'categories';
        $('[data-categories]', form).hidden = !byCategory || $('[data-show-for="percent fixed"]', form).hidden;
    }

    form.addEventListener('change', sync);
    form.addEventListener('input', sync);

    /* ---------- open / fill ---------- */

    function open(promo) {
        promo = promo || {};
        form.reset();
        form.elements.id.value = promo.id || '';
        form.elements.name.value = promo.name || '';
        form.elements.description.value = promo.description || '';
        form.elements.unlimited.checked = !promo.starts_at && !promo.ends_at;
        form.elements.starts_at.value = promo.starts_at || '';
        form.elements.ends_at.value = promo.ends_at || '';
        form.elements.type.value = promo.type || 'percent';
        form.elements.value.value = promo.value || '';
        form.elements.min_order.value = promo.min_order || '';
        form.elements.promo_codes.value = promo.promo_codes || '';
        form.querySelector('[name="applies_to"][value="' + (promo.applies_to || 'all') + '"]').checked = true;
        $$('[name="category_ids[]"]', form).forEach(function (box) {
            box.checked = (promo.category_ids || []).indexOf(+box.value) !== -1;
        });

        var options = promo.options || {};
        $$('[name^="options["]', form).forEach(function (input) {
            var key = input.name.slice(8, -1);
            if (input.type === 'checkbox') {
                input.checked = !!options[key];
            } else if (options[key]) {
                input.value = options[key];
            }
        });

        $$('[data-banner]', form).forEach(function (zone) {
            UI.resetDropzone(zone, promo[zone.getAttribute('data-banner')] || '');
        });

        $('[data-modal-title]', modal).textContent = promo.id ? 'Редактировать акцию' : 'Добавить акцию';
        $('[data-submit]', form).textContent = promo.id ? 'Сохранить' : 'Добавить';
        $('[data-promo-form-delete]', form).hidden = !promo.id;
        $$('[data-acc]', form).forEach(function (acc, i, all) {
            acc.classList.toggle('is-open', i === all.length - 1);
        });
        sync();
        UI.openModal(modal);
    }

    $$('[data-promo-add]').forEach(function (btn) {
        btn.addEventListener('click', function () { open(); });
    });

    /* ---------- list actions ---------- */

    function remove(card, id) {
        return UI.confirm({ title: 'Удалить акцию?', text: 'Баннеры и промокоды этой акции тоже будут удалены.' }).then(function (ok) {
            if (!ok) {
                return false;
            }
            return UI.api(API, { action: 'delete', id: id }).then(function () {
                if (card) {
                    UI.collapse(card);
                }
                UI.toast('Акция удалена');
                return true;
            });
        });
    }

    document.addEventListener('click', function (e) {
        var card = e.target.closest('[data-promo]');
        if (!card) {
            return;
        }
        if (e.target.closest('[data-promo-edit]')) {
            open(JSON.parse(card.getAttribute('data-json')));
        } else if (e.target.closest('[data-promo-delete]')) {
            remove(card, card.getAttribute('data-promo'));
        }
    });

    document.addEventListener('change', function (e) {
        if (!e.target.matches('[data-promo-toggle]')) {
            return;
        }
        var input = e.target;
        var card = input.closest('[data-promo]');
        card.classList.toggle('is-off', !input.checked);
        UI.api(API, { action: 'toggle', id: card.getAttribute('data-promo'), active: input.checked ? 1 : 0 })
            .then(function () { UI.toast(input.checked ? 'Акция включена' : 'Акция выключена'); })
            .catch(function () { input.checked = !input.checked; card.classList.toggle('is-off', !input.checked); });
    });

    $('[data-promo-form-delete]', form).addEventListener('click', function () {
        var id = form.elements.id.value;
        remove($('[data-promo="' + id + '"]'), id).then(function (done) {
            if (done) {
                UI.closeModal(modal);
            }
        });
    });

    /* ---------- save ---------- */

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!form.elements.name.value.trim()) {
            var field = form.elements.name.closest('.float');
            field.classList.remove('is-invalid');
            void field.offsetWidth;
            field.classList.add('is-invalid');
            form.elements.name.focus();
            return;
        }
        var submit = $('[data-submit]', form);
        var data = UI.formData(form);
        data.append('action', 'save');
        if (!form.elements.unlimited.checked) {
            data.set('unlimited', '0');
        }
        UI.busy(submit, true);
        UI.api(API, data).then(function () {
            UI.flash('Акция сохранена');
            location.reload();
        }).catch(function () {
            UI.busy(submit, false);
        });
    });
})();
