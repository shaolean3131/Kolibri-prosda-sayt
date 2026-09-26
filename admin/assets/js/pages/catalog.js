(function () {
    'use strict';

    var UI = window.UI;
    var $ = UI.$;
    var $$ = UI.$$;
    var API = 'api/catalog.php';
    var catalog = $('[data-catalog]');

    function productRow(el) { return el.closest('[data-product]'); }
    function categoryOf(el) { return el.closest('[data-category]'); }
    function idOf(el, attr) { return el.getAttribute(attr); }

    /* ---------- restore position after a reload ---------- */

    var target = UI.session.get('kolibri.catalog.target');
    if (target) {
        UI.session.remove('kolibri.catalog.target');
        var el = $(target);
        if (el) {
            el.scrollIntoView({ block: 'center' });
            el.classList.add('is-highlight');
        }
    }

    function reloadTo(selector, message) {
        UI.session.set('kolibri.catalog.target', selector);
        UI.flash(message);
        location.reload();
    }

    /* ---------- category chips: scroll + scrollspy ---------- */

    var chips = $('[data-chips]');
    var bar = $('[data-catalog-bar]');

    function setActiveChip(id) {
        $$('.chip', chips).forEach(function (chip) {
            var active = chip.getAttribute('data-chip') === id;
            chip.classList.toggle('is-active', active);
            if (active && chips.scrollWidth > chips.clientWidth) {
                chips.scrollTo({ left: chip.offsetLeft - chips.clientWidth / 2 + chip.offsetWidth / 2, behavior: 'smooth' });
            }
        });
    }

    if ('IntersectionObserver' in window) {
        var visible = {};
        var spy = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                visible[entry.target.getAttribute('data-category')] = entry.isIntersecting;
            });
            var first = $$('[data-category]').filter(function (s) { return visible[s.getAttribute('data-category')]; })[0];
            if (first) {
                setActiveChip(first.getAttribute('data-category'));
            }
        }, { rootMargin: '-120px 0px -55% 0px' });
        $$('[data-category]').forEach(function (section) { spy.observe(section); });

        // shadow under the sticky bar once it sticks
        var sentinel = document.createElement('div');
        bar.parentNode.insertBefore(sentinel, bar);
        new IntersectionObserver(function (entries) {
            bar.classList.toggle('is-stuck', !entries[0].isIntersecting);
        }).observe(sentinel);
    }

    /* ---------- search (Ctrl K) ---------- */

    var search = $('[data-catalog-search]');
    var nothing = $('[data-search-empty]');

    search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase();
        var found = 0;
        $$('[data-category]').forEach(function (section) {
            var nameMatch = q !== '' && section.getAttribute('data-name').toLowerCase().indexOf(q) !== -1;
            var shown = 0;
            $$('[data-product]', section).forEach(function (row) {
                var match = !q || nameMatch || row.getAttribute('data-search').indexOf(q) !== -1;
                row.hidden = !match;
                shown += match ? 1 : 0;
            });
            section.hidden = q !== '' && !nameMatch && shown === 0;
            section.classList.toggle('is-searching', q !== '');
            found += section.hidden ? 0 : 1;
        });
        nothing.hidden = found > 0 || !$('[data-category]');
    });

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            search.focus();
            search.select();
        } else if (e.key === 'Escape' && document.activeElement === search) {
            search.value = '';
            search.dispatchEvent(new Event('input'));
            search.blur();
        }
    });

    /* ---------- switches ---------- */

    catalog.addEventListener('change', function (e) {
        var input = e.target;

        if (input.matches('[data-category-toggle]')) {
            var section = categoryOf(input);
            section.classList.toggle('is-off', !input.checked);
            UI.api(API, { action: 'category.toggle', id: idOf(section, 'data-category'), active: input.checked ? 1 : 0 })
                .then(function () { UI.toast(input.checked ? 'Категория показана на сайте' : 'Категория скрыта'); })
                .catch(function () { input.checked = !input.checked; section.classList.toggle('is-off', !input.checked); });
            return;
        }

        if (input.matches('[data-product-toggle]')) {
            var row = productRow(input);
            row.classList.toggle('is-off', !input.checked);
            UI.api(API, { action: 'product.toggle', id: idOf(row, 'data-product'), active: input.checked ? 1 : 0 })
                .then(function () { UI.toast(input.checked ? 'Товар показан на сайте' : 'Товар скрыт'); })
                .catch(function () { input.checked = !input.checked; row.classList.toggle('is-off', !input.checked); });
            return;
        }

        if (input.matches('[data-category-image]')) {
            uploadCategoryImage(input);
            return;
        }

        if (input.matches('[data-extra]')) {
            if (/\.on$/.test(input.getAttribute('data-extra'))) {
                input.closest('.section').classList.toggle('is-open', input.checked);
            }
            saveExtra(productRow(input));
        }
    });

    catalog.addEventListener('input', function (e) {
        if (e.target.matches('input[data-extra]:not([type=checkbox]), textarea[data-extra]')) {
            saveExtra(productRow(e.target));
        }
    });

    /* ---------- product "extra" sections: autosave ---------- */

    function collectExtra(row) {
        var extra = {};
        $$('[data-extra]', row).forEach(function (input) {
            var path = input.getAttribute('data-extra').split('.');
            var section = extra[path[0]] = extra[path[0]] || {};
            var field = path[1];
            if (input.type === 'checkbox' && input.hasAttribute('value')) {
                section[field] = section[field] || [];
                if (input.checked) {
                    section[field].push(input.value);
                }
            } else if (input.type === 'checkbox') {
                section[field] = input.checked;
            } else {
                section[field] = input.value;
            }
        });
        return extra;
    }

    function saveExtra(row) {
        if (!row._saveExtra) {
            row._saveExtra = UI.debounce(function () {
                UI.api(API, { action: 'product.extra', id: idOf(row, 'data-product'), extra: JSON.stringify(collectExtra(row)) })
                    .then(function () { UI.toast('Сохранено'); });
            }, 700);
        }
        row._saveExtra();
    }

    /* ---------- clicks ---------- */

    catalog.addEventListener('click', function (e) {
        var btn = e.target.closest('button');
        if (!btn) {
            return;
        }

        if (btn.matches('[data-label]')) {
            var row = productRow(btn);
            btn.classList.toggle('is-active');
            var labels = $$('[data-label].is-active', row).map(function (b) { return b.getAttribute('data-label'); });
            UI.api(API, { action: 'product.labels', id: idOf(row, 'data-product'), labels: labels })
                .catch(function () { btn.classList.toggle('is-active'); });
        } else if (btn.matches('[data-product-edit]')) {
            openProduct(JSON.parse(productRow(btn).getAttribute('data-json')));
        } else if (btn.matches('[data-product-add]')) {
            openProduct({ category_id: idOf(categoryOf(btn), 'data-category') });
        } else if (btn.matches('[data-product-delete]')) {
            deleteProduct(productRow(btn));
        } else if (btn.matches('[data-category-edit]')) {
            var section = categoryOf(btn);
            openCategory({ id: idOf(section, 'data-category'), name: section.getAttribute('data-name') });
        } else if (btn.matches('[data-category-delete]')) {
            deleteCategory(categoryOf(btn));
        } else if (btn.matches('[data-sort-toggle]')) {
            var sorting = categoryOf(btn).classList.toggle('is-sorting');
            btn.classList.toggle('is-active', sorting);
        } else if (btn.matches('[data-move-up], [data-move-down]')) {
            move(btn, btn.matches('[data-move-up]') ? -1 : 1);
        }
    });

    $$('[data-category-add]').forEach(function (btn) {
        btn.addEventListener('click', function () { openCategory({}); });
    });

    /* ---------- delete ---------- */

    function deleteProduct(row) {
        var name = JSON.parse(row.getAttribute('data-json')).name;
        UI.confirm({ title: 'Удалить товар?', text: name + ' — будет удалён без возможности восстановления.' }).then(function (ok) {
            if (!ok) {
                return;
            }
            UI.api(API, { action: 'product.delete', id: idOf(row, 'data-product') }).then(function () {
                UI.collapse(row);
                UI.toast('Товар удалён');
            });
        });
    }

    function deleteCategory(section) {
        var count = $$('[data-product]', section).length;
        UI.confirm({
            title: 'Удалить категорию?',
            text: count ? 'Вместе с категорией «' + section.getAttribute('data-name') + '» будут удалены все её товары (' + count + ').' : 'Категория «' + section.getAttribute('data-name') + '» будет удалена.'
        }).then(function (ok) {
            if (!ok) {
                return;
            }
            var id = idOf(section, 'data-category');
            UI.api(API, { action: 'category.delete', id: id }).then(function () {
                UI.collapse(section);
                var chip = $('[data-chip="' + id + '"]');
                if (chip) {
                    chip.remove();
                }
                var option = $('option[value="' + id + '"]', productForm);
                if (option) {
                    option.remove();
                }
                UI.toast('Категория удалена');
            });
        });
    }

    /* ---------- category photo ---------- */

    function uploadCategoryImage(input) {
        var section = categoryOf(input);
        var file = input.files && input.files[0];
        if (!file) {
            return;
        }
        UI.resizeImage(file, 1200).then(function (ready) {
            var data = new FormData();
            data.append('action', 'category.image');
            data.append('id', idOf(section, 'data-category'));
            data.append('image', ready, ready.name || 'image.jpg');
            return UI.api(API, data);
        }).then(function (res) {
            var img = $('[data-category-img]', section);
            img.src = res.image;
            img.hidden = false;
            img.classList.remove('pop');
            void img.offsetWidth;
            img.classList.add('pop');
            UI.toast('Фото категории обновлено');
        }).catch(function () { /* toast already shown */ }).then(function () {
            input.value = '';
        });
    }

    /* ---------- sorting ---------- */

    function move(btn, dir) {
        var isProduct = !!productRow(btn);
        var item = isProduct ? productRow(btn) : categoryOf(btn);
        var list = isProduct ? $$('[data-product]', item.parentNode) : $$('[data-category]', catalog);
        var index = list.indexOf(item);
        var other = list[index + dir];
        if (!other) {
            item.animate([{ transform: 'translateY(0)' }, { transform: 'translateY(' + (dir * 6) + 'px)' }, { transform: 'translateY(0)' }], { duration: 260 });
            return;
        }

        UI.flip(list, function () {
            other.parentNode.insertBefore(item, dir < 0 ? other : other.nextSibling);
        });

        if (isProduct) {
            var section = categoryOf(item);
            UI.api(API, {
                action: 'product.reorder',
                category_id: idOf(section, 'data-category'),
                ids: $$('[data-product]', section).map(function (r) { return idOf(r, 'data-product'); })
            });
        } else {
            var ids = $$('[data-category]', catalog).map(function (s) { return idOf(s, 'data-category'); });
            ids.forEach(function (id) {
                var chip = $('[data-chip="' + id + '"]');
                if (chip) {
                    chips.appendChild(chip);
                }
            });
            UI.api(API, { action: 'category.reorder', ids: ids });
        }
    }

    /* ---------- product modal ---------- */

    var productForm = $('[data-product-form]');
    var productModal = productForm.closest('.modal');

    function openProduct(product) {
        productForm.reset();
        productForm.elements.id.value = product.id || '';
        productForm.elements.name.value = product.name || '';
        productForm.elements.description.value = product.description || '';
        productForm.elements.price.value = product.price || '';
        productForm.elements.old_price.value = product.old_price || '';
        productForm.elements.unit_amount.value = product.unit_amount || '1';
        productForm.elements.unit_name.value = product.unit_name || 'шт';
        productForm.elements.category_id.value = product.category_id;
        UI.resetDropzone($('[data-dropzone]', productForm), product.image || '');
        $('[data-modal-title]', productModal).textContent = product.id ? 'Редактировать товар' : 'Новый товар';
        UI.openModal(productModal);
    }

    productForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!productForm.elements.name.value.trim()) {
            shake(productForm.elements.name);
            return;
        }
        if (!productForm.elements.price.value.trim()) {
            shake(productForm.elements.price);
            return;
        }
        var submit = $('[data-submit]', productForm);
        var data = UI.formData(productForm);
        data.append('action', 'product.save');
        UI.busy(submit, true);
        UI.api(API, data).then(function (res) {
            reloadTo('[data-product="' + res.id + '"]', 'Товар сохранён');
        }).catch(function () {
            UI.busy(submit, false);
        });
    });

    /* ---------- category modal ---------- */

    var categoryForm = $('[data-category-form]');
    var categoryModal = categoryForm.closest('.modal');

    function openCategory(category) {
        categoryForm.reset();
        categoryForm.elements.id.value = category.id || '';
        categoryForm.elements.name.value = category.name || '';
        $('[data-modal-title]', categoryModal).textContent = category.id ? 'Категория' : 'Новая категория';
        UI.openModal(categoryModal);
    }

    categoryForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!categoryForm.elements.name.value.trim()) {
            shake(categoryForm.elements.name);
            return;
        }
        var submit = $('[data-submit]', categoryForm);
        var data = new FormData(categoryForm);
        data.append('action', 'category.save');
        UI.busy(submit, true);
        UI.api(API, data).then(function (res) {
            reloadTo('#cat-' + res.id, 'Категория сохранена');
        }).catch(function () {
            UI.busy(submit, false);
        });
    });

    function shake(input) {
        var field = input.closest('.float') || input;
        field.classList.remove('is-invalid');
        void field.offsetWidth;
        field.classList.add('is-invalid');
        input.focus();
    }
})();
