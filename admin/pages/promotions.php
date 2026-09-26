<?php
defined('KOLIBRI') or exit;

$promos     = db()->query('SELECT * FROM promotions ORDER BY sort, id DESC')->fetchAll();
$categories = db()->query('SELECT id, name FROM categories ORDER BY sort, id')->fetchAll();
$products   = db()->query('SELECT id, name FROM products ORDER BY name')->fetchAll();
?>
<div class="page-head reveal">
    <h1 class="page-title"><?= e($title) ?></h1>
    <button class="btn btn--primary btn--sm" type="button" data-promo-add><?= icon('plus', 'icon icon--sm') ?>Добавить акцию</button>
</div>
<p class="page-lead reveal" style="--i: 1">Акции показываются на сайте и в приложении в виде баннеров. Скидка применяется по промокоду или автоматически.</p>

<div class="promo-list" data-promo-list>
    <?php if (!$promos): ?>
        <section class="card empty-page reveal" style="--i: 2">
            <div class="empty-page__art" aria-hidden="true"><span></span><span></span><span></span></div>
            <h2 class="empty-page__title">Акций пока нет</h2>
            <p class="empty-page__text">Добавьте баннер, скидку или промокод, чтобы порадовать клиентов.</p>
            <button class="btn btn--primary" type="button" data-promo-add><?= icon('plus', 'icon icon--sm') ?>Добавить акцию</button>
        </section>
    <?php endif; ?>

    <?php foreach ($promos as $n => $promo):
        $banner = $promo['banner_desktop'] ?: ($promo['banner_mobile'] ?: $promo['banner_app']);
        $codes  = json_decode((string) $promo['promo_codes'], true) ?: []; ?>
        <article class="card promo reveal<?= $promo['is_active'] ? '' : ' is-off' ?>" style="--i: <?= min($n + 2, 9) ?>"
                 data-promo="<?= (int) $promo['id'] ?>" data-json="<?= e(promo_json($promo)) ?>">
            <?php if ($banner): ?>
                <img class="promo__img" src="<?= e(upload_url($banner)) ?>" alt="" loading="lazy">
            <?php else: ?>
                <span class="promo__img promo__img--empty"><?= icon('image') ?></span>
            <?php endif; ?>
            <div class="promo__body">
                <h2 class="promo__name"><?= e($promo['name']) ?></h2>
                <div class="promo__meta">
                    <span class="tag tag--blue"><?= e(promo_badge($promo)) ?></span>
                    <span class="tag"><?= icon('calendar', 'icon icon--xs') ?><?= e(promo_period_label($promo)) ?></span>
                    <?php if ($codes): ?><span class="tag tag--code"><?= e(implode(', ', array_slice($codes, 0, 3))) ?><?= count($codes) > 3 ? ' +' . (count($codes) - 3) : '' ?></span><?php endif; ?>
                </div>
            </div>
            <div class="tools">
                <button class="tool tool--danger" type="button" title="Удалить" data-promo-delete><?= icon('trash') ?></button>
                <button class="tool tool--dark" type="button" title="Редактировать" data-promo-edit><?= icon('gear') ?></button>
            </div>
            <label class="switch" title="Акция активна">
                <input type="checkbox" data-promo-toggle<?= $promo['is_active'] ? ' checked' : '' ?>>
                <span class="switch__track"></span>
            </label>
        </article>
    <?php endforeach; ?>
</div>

<!-- promotion modal -->
<div class="modal" data-modal="promo" aria-hidden="true">
    <div class="modal__backdrop" data-modal-close></div>
    <form class="modal__dialog" data-promo-form novalidate>
        <header class="modal__head">
            <h2 class="modal__title" data-modal-title>Добавить акцию</h2>
            <div class="modal__head-tools">
                <button class="tool tool--danger" type="button" title="Удалить акцию" data-promo-form-delete><?= icon('trash') ?></button>
                <button class="tool" type="button" data-modal-close aria-label="Закрыть"><?= icon('close') ?></button>
            </div>
        </header>
        <div class="modal__body">
            <input type="hidden" name="id">
            <div class="panel">
                <label class="float">
                    <input class="float__input" name="name" placeholder=" " maxlength="190" required>
                    <span class="float__label">Название</span>
                </label>
                <label class="float">
                    <textarea class="float__input" name="description" placeholder=" " rows="2" maxlength="3000"></textarea>
                    <span class="float__label">Описание</span>
                </label>
                <div class="banners">
                    <?php foreach (PROMO_BANNERS as $field => $banner): ?>
                        <div class="banners__item banners__item--<?= $field ?>">
                            <div class="panel__label"><?= e($banner['title']) ?></div>
                            <div class="dropzone" data-dropzone data-banner="<?= $field ?>">
                                <input type="file" name="<?= $field ?>" accept="image/jpeg,image/png,image/webp" data-dropzone-input>
                                <input type="hidden" name="<?= $field ?>_remove" value="0" data-dropzone-remove>
                                <div class="dropzone__empty">
                                    <?= icon('image-up') ?>
                                    <span><b>Загрузите</b> сюда баннер</span>
                                    <small><?= e($banner['size']) ?> | jpg, png</small>
                                </div>
                                <img class="dropzone__preview" alt="">
                                <button class="dropzone__clear" type="button" data-dropzone-clear aria-label="Удалить баннер"><?= icon('close', 'icon icon--sm') ?></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <section class="acc" data-acc>
                <button class="acc__head" type="button" data-acc-toggle>
                    <span>Сроки акции</span><span class="acc__chip" data-period-chip>Бессрочный</span>
                    <?= icon('chevron', 'icon acc__chevron') ?>
                </button>
                <div class="acc__body"><div class="acc__inner">
                    <label class="switch switch--sm">
                        <input type="checkbox" name="unlimited" value="1" checked data-unlimited>
                        <span class="switch__track"></span><span class="switch__label">Бессрочная акция</span>
                    </label>
                    <div class="acc__grid" data-dates>
                        <label class="float"><input class="float__input" type="datetime-local" name="starts_at" placeholder=" "><span class="float__label">Начало</span></label>
                        <label class="float"><input class="float__input" type="datetime-local" name="ends_at" placeholder=" "><span class="float__label">Окончание</span></label>
                    </div>
                </div></div>
            </section>

            <section class="acc" data-acc>
                <button class="acc__head" type="button" data-acc-toggle>
                    <span>Тип акции и меню</span><span class="acc__chip" data-type-chip></span>
                    <?= icon('chevron', 'icon acc__chevron') ?>
                </button>
                <div class="acc__body"><div class="acc__inner">
                    <div class="acc__grid">
                        <label class="float">
                            <select class="float__input" name="type" data-promo-type>
                                <?php foreach (PROMO_TYPES as $value => $label): ?>
                                    <option value="<?= $value ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="float__label">Тип</span>
                        </label>
                        <label class="float" data-show-for="percent fixed">
                            <input class="float__input" name="value" placeholder=" " inputmode="decimal">
                            <span class="float__label" data-value-label>Размер скидки</span>
                        </label>
                        <label class="float" data-show-for="gift">
                            <select class="float__input" name="options[gift_product_id]">
                                <option value="0">Выберите товар</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= (int) $product['id'] ?>"><?= e($product['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="float__label">Подарок</span>
                        </label>
                        <label class="float" data-show-for="percent fixed gift">
                            <input class="float__input" name="min_order" placeholder=" " inputmode="decimal">
                            <span class="float__label">Минимальная сумма заказа, <?= e(config('currency', '₽')) ?></span>
                        </label>
                    </div>
                    <div class="segmented" data-show-for="percent fixed">
                        <label><input type="radio" name="applies_to" value="all" checked><span>Всё меню</span></label>
                        <label><input type="radio" name="applies_to" value="categories"><span>Выбранные категории</span></label>
                    </div>
                    <div class="checks checks--grid" data-categories>
                        <?php foreach ($categories as $category): ?>
                            <label class="check"><input type="checkbox" name="category_ids[]" value="<?= (int) $category['id'] ?>"><span></span><?= e($category['name']) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div></div>
            </section>

            <label class="float float--gray">
                <input class="float__input" name="promo_codes" placeholder=" " maxlength="2000">
                <span class="float__label">Промокоды (через запятую)</span>
            </label>

            <section class="acc is-open" data-acc>
                <button class="acc__head" type="button" data-acc-toggle>
                    <span>Настройки</span>
                    <?= icon('chevron', 'icon acc__chevron') ?>
                </button>
                <div class="acc__body"><div class="acc__inner acc__inner--list">
                    <?php foreach (promo_option_list() as $key => $option): ?>
                        <div class="option">
                            <label class="check"><input type="checkbox" name="options[<?= $key ?>]" value="1"><span></span><?= e($option['label']) ?></label>
                            <?php if (isset($option['select'])): ?>
                                <select class="inline-select" name="options[<?= $key ?>_value]" aria-label="<?= e($option['label']) ?>">
                                    <?php foreach ($option['select'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>"><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                            <?php if (isset($option['after'])): ?><span class="option__after"><?= e($option['after']) ?></span><?php endif; ?>
                            <?php if (isset($option['hint'])): ?><span class="hint" tabindex="0" data-tip="<?= e($option['hint']) ?>">?</span><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div></div>
            </section>
        </div>
        <footer class="modal__foot">
            <button class="btn btn--primary btn--xl" type="submit" data-submit>Добавить</button>
        </footer>
    </form>
</div>
