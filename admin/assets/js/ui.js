/*
 * Shared admin UI: API calls, toasts, modals, confirm dialog, image
 * dropzones and small animation helpers. Exposed as window.UI.
 */
window.UI = (function () {
    'use strict';

    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
    var csrfMeta = $('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function icon(name) {
        var paths = {
            check: '<path d="M5 12.5l4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>',
            error: '<path d="M12 7v6M12 16.5v.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>'
        };
        return '<svg class="icon icon--sm" viewBox="0 0 24 24" aria-hidden="true">' + paths[name] + '</svg>';
    }

    /* ---------- storage ---------- */

    var session = {
        get: function (key) { try { return sessionStorage.getItem(key); } catch (e) { return null; } },
        set: function (key, value) { try { sessionStorage.setItem(key, value); } catch (e) { /* ignore */ } },
        remove: function (key) { try { sessionStorage.removeItem(key); } catch (e) { /* ignore */ } }
    };

    /* ---------- api ---------- */

    function toFormData(data) {
        if (data instanceof FormData) {
            return data;
        }
        var form = new FormData();
        Object.keys(data || {}).forEach(function (key) {
            var value = data[key];
            if (Array.isArray(value)) {
                value.forEach(function (item) { form.append(key + '[]', item); });
            } else if (value !== undefined && value !== null) {
                form.append(key, value);
            }
        });
        return form;
    }

    function api(url, data) {
        var body = toFormData(data);
        body.set('_csrf', csrf);
        return fetch(url, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        }).then(function (res) {
            if (res.status === 401) {
                location.href = 'login.php';
            }
            return res.json().catch(function () { return {}; }).then(function (json) {
                if (!res.ok || json.error) {
                    throw new Error(json.error || 'Ошибка сервера (' + res.status + ')');
                }
                return json;
            });
        }, function () {
            throw new Error('Нет соединения с сервером');
        }).catch(function (err) {
            toast(err.message, 'error');
            throw err;
        });
    }

    /* ---------- toasts ---------- */

    var toastRoot = null;
    function toast(text, type) {
        if (!toastRoot) {
            toastRoot = document.createElement('div');
            toastRoot.className = 'toasts';
            toastRoot.setAttribute('aria-live', 'polite');
            document.body.appendChild(toastRoot);
        }
        var el = document.createElement('div');
        el.className = 'toast' + (type === 'error' ? ' toast--error' : '');
        el.innerHTML = '<span class="toast__icon">' + icon(type === 'error' ? 'error' : 'check') + '</span>';
        el.appendChild(document.createTextNode(text));
        toastRoot.appendChild(el);
        // keep at most three on screen
        while (toastRoot.children.length > 3) {
            toastRoot.removeChild(toastRoot.firstChild);
        }
        setTimeout(function () {
            el.classList.add('is-leaving');
            setTimeout(function () { el.remove(); }, 300);
        }, type === 'error' ? 4200 : 2200);
    }

    /** Shows a toast after the next page load (used before reloads). */
    function flash(text) {
        session.set('kolibri.flash', text);
    }

    var pending = session.get('kolibri.flash');
    if (pending) {
        session.remove('kolibri.flash');
        setTimeout(function () { toast(pending); }, 350);
    }

    /* ---------- modals ---------- */

    var openModals = [];

    function modal(nameOrEl) {
        return typeof nameOrEl === 'string' ? $('[data-modal="' + nameOrEl + '"]') : nameOrEl;
    }

    function openModal(nameOrEl) {
        var el = modal(nameOrEl);
        el.classList.add('is-open');
        el.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        openModals.push(el);
        var body = $('.modal__body', el);
        if (body) {
            body.scrollTop = 0;
        }
        setTimeout(function () {
            var first = $('input:not([type=hidden]):not([type=file]), textarea', el);
            if (first && window.innerWidth > 700) {
                first.focus({ preventScroll: true });
            }
        }, 180);
        return el;
    }

    function closeModal(nameOrEl) {
        var el = modal(nameOrEl);
        if (!el || !el.classList.contains('is-open')) {
            return;
        }
        el.classList.remove('is-open');
        el.setAttribute('aria-hidden', 'true');
        openModals = openModals.filter(function (m) { return m !== el; });
        if (!openModals.length) {
            document.body.classList.remove('modal-open');
        }
        if (el._onClose) {
            el._onClose();
        }
    }

    document.addEventListener('click', function (e) {
        var closer = e.target.closest('[data-modal-close]');
        if (closer) {
            closeModal(closer.closest('.modal'));
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && openModals.length) {
            closeModal(openModals[openModals.length - 1]);
        }
    });

    /* ---------- confirm ---------- */

    function confirmDialog(options) {
        return new Promise(function (resolve) {
            var el = document.createElement('div');
            el.className = 'modal modal--confirm';
            el.innerHTML =
                '<div class="modal__backdrop" data-modal-close></div>' +
                '<div class="modal__dialog" role="alertdialog" aria-modal="true">' +
                '<h2 class="confirm__title"></h2><p class="confirm__text"></p>' +
                '<div class="confirm__actions">' +
                '<button class="btn btn--light" type="button" data-modal-close>Отмена</button>' +
                '<button class="btn ' + (options.danger === false ? 'btn--primary' : 'btn--danger') + '" type="button" data-ok></button>' +
                '</div></div>';
            $('.confirm__title', el).textContent = options.title || 'Вы уверены?';
            $('.confirm__text', el).textContent = options.text || '';
            $('[data-ok]', el).textContent = options.ok || 'Удалить';
            document.body.appendChild(el);

            var result = false;
            el._onClose = function () {
                resolve(result);
                setTimeout(function () { el.remove(); }, 300);
            };
            $('[data-ok]', el).addEventListener('click', function () {
                result = true;
                closeModal(el);
            });
            requestAnimationFrame(function () {
                openModal(el);
                $('[data-ok]', el).focus({ preventScroll: true });
            });
        });
    }

    /* ---------- images ---------- */

    /** Downscales big photos in the browser so uploads stay small and fast. */
    function resizeImage(file, maxSide) {
        maxSide = maxSide || 1600;
        return new Promise(function (resolve) {
            if (!file || !/^image\/(jpeg|png|webp)$/.test(file.type)) {
                resolve(file);
                return;
            }
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () {
                URL.revokeObjectURL(url);
                var scale = Math.min(1, maxSide / Math.max(img.naturalWidth, img.naturalHeight));
                if (scale === 1 && file.size < 1.5 * 1024 * 1024) {
                    resolve(file);
                    return;
                }
                var canvas = document.createElement('canvas');
                canvas.width = Math.round(img.naturalWidth * scale);
                canvas.height = Math.round(img.naturalHeight * scale);
                canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                var type = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
                canvas.toBlob(function (blob) {
                    resolve(blob && blob.size < file.size ? new File([blob], file.name.replace(/\.\w+$/, '') + (type === 'image/png' ? '.png' : '.jpg'), { type: type }) : file);
                }, type, 0.86);
            };
            img.onerror = function () {
                URL.revokeObjectURL(url);
                resolve(file);
            };
            img.src = url;
        });
    }

    /* ---------- dropzones ---------- */

    function setupDropzone(zone) {
        var input = $('[data-dropzone-input]', zone);
        var preview = $('.dropzone__preview', zone);
        var remove = $('[data-dropzone-remove]', zone);

        function show(url) {
            if (url) {
                preview.src = url;
                zone.classList.add('has-image');
            } else {
                preview.removeAttribute('src');
                zone.classList.remove('has-image');
            }
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) {
                return;
            }
            zone.classList.add('is-busy');
            resizeImage(file).then(function (ready) {
                zone._file = ready;
                if (remove) {
                    remove.value = '0';
                }
                show(URL.createObjectURL(ready));
                zone.classList.remove('is-busy');
                zone.classList.remove('pop');
                void zone.offsetWidth;
                zone.classList.add('pop');
            });
        });

        ['dragenter', 'dragover'].forEach(function (type) {
            zone.addEventListener(type, function () { zone.classList.add('is-drag'); });
        });
        ['dragleave', 'drop'].forEach(function (type) {
            zone.addEventListener(type, function () { zone.classList.remove('is-drag'); });
        });

        var clear = $('[data-dropzone-clear]', zone);
        if (clear) {
            clear.addEventListener('click', function (e) {
                e.preventDefault();
                reset(zone);
                if (remove) {
                    remove.value = '1';
                }
            });
        }

        zone._show = show;
    }

    function reset(zone, url) {
        var input = $('[data-dropzone-input]', zone);
        input.value = '';
        zone._file = null;
        var remove = $('[data-dropzone-remove]', zone);
        if (remove) {
            remove.value = '0';
        }
        zone._show(url || '');
    }

    /** FormData for a form, using the resized image from each dropzone. */
    function formData(form) {
        var data = new FormData(form);
        $$('[data-dropzone]', form).forEach(function (zone) {
            var input = $('[data-dropzone-input]', zone);
            data.delete(input.name);
            if (zone._file) {
                data.set(input.name, zone._file, zone._file.name || 'image.jpg');
            }
        });
        return data;
    }

    $$('[data-dropzone]').forEach(setupDropzone);

    /* ---------- helpers ---------- */

    function debounce(fn, ms) {
        var timer;
        return function () {
            var args = arguments;
            var self = this;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(self, args); }, ms);
        };
    }

    /** Animates elements from their old position after a DOM change (FLIP). */
    function flip(elements, mutate) {
        var before = elements.map(function (el) { return el.getBoundingClientRect().top; });
        mutate();
        if (reduceMotion) {
            return;
        }
        elements.forEach(function (el, i) {
            var delta = before[i] - el.getBoundingClientRect().top;
            if (!delta) {
                return;
            }
            el.animate([{ transform: 'translateY(' + delta + 'px)' }, { transform: 'none' }], {
                duration: 380,
                easing: 'cubic-bezier(.2, .7, .2, 1)'
            });
        });
    }

    /** Collapses an element to zero height, then removes it. */
    function collapse(el) {
        return new Promise(function (resolve) {
            if (reduceMotion) {
                el.remove();
                resolve();
                return;
            }
            var height = el.offsetHeight;
            var anim = el.animate([
                { height: height + 'px', opacity: 1, transform: 'none' },
                { height: height + 'px', opacity: 0, transform: 'translateX(24px)', offset: 0.45 },
                { height: '0px', opacity: 0, transform: 'translateX(24px)', marginTop: '0px', marginBottom: '0px' }
            ], { duration: 460, easing: 'cubic-bezier(.2, .7, .2, 1)' });
            el.style.overflow = 'hidden';
            anim.onfinish = function () {
                el.remove();
                resolve();
            };
        });
    }

    function busy(button, on) {
        if (!button) {
            return;
        }
        button.disabled = on;
        button.classList.toggle('is-busy', on);
    }

    return {
        $: $,
        $$: $$,
        api: api,
        toast: toast,
        flash: flash,
        session: session,
        openModal: openModal,
        closeModal: closeModal,
        confirm: confirmDialog,
        resizeImage: resizeImage,
        resetDropzone: reset,
        formData: formData,
        debounce: debounce,
        flip: flip,
        collapse: collapse,
        busy: busy
    };
})();
