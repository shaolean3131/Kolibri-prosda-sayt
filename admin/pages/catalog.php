<?php
defined('KOLIBRI') or exit;

$categories = catalog_tree();
$i = 1;
?>
<div class="page-head reveal">
    <h1 class="page-title"><?= e($title) ?></h1>
    <button class="btn btn--primary btn--sm" type="button" data-category-add><?= icon('plus', 'icon icon--sm') ?>Категория</button>
</div>

<div class="catalog-bar reveal" style="--i: 1" data-catalog-bar>
    <nav class="chips" data-chips>
        <?php foreach ($categories as $n => $category): ?>
            <a class="chip<?= $n === 0 ? ' is-active' : '' ?>" href="#cat-<?= (int) $category['id'] ?>" data-chip="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></a>
        <?php endforeach; ?>
    </nav>
    <label class="search">
        <?= icon('search', 'icon search__icon') ?>
        <input class="search__input" type="search" placeholder="Поиск товара" data-catalog-search aria-label="Поиск товара">
        <kbd class="search__kbd">Ctrl K</kbd>
    </label>
</div>

<div class="catalog" data-catalog>
    <?php if (!$categories): ?>
        <section class="card empty-page reveal" style="--i: 2">
            <div class="empty-page__art" aria-hidden="true"><span></span><span></span><span></span></div>
            <h2 class="empty-page__title">Каталог пока пуст</h2>
            <p class="empty-page__text">Создайте первую категорию, например «Монобукеты», и добавьте в неё товары.</p>
            <button class="btn btn--primary" type="button" data-category-add><?= icon('plus', 'icon icon--sm') ?>Добавить категорию</button>
        </section>
    <?php endif; ?>

    <?php foreach ($categories as $category): ?>
        <section class="cat reveal<?= $category['is_active'] ? '' : ' is-off' ?>" style="--i: <?= min(++$i, 8) ?>"
                 id="cat-<?= (int) $category['id'] ?>" data-category="<?= (int) $category['id'] ?>" data-name="<?= e($category['name']) ?>">
            <div class="row">
                <div class="card cat-card">
                    <?php if ($category['image']): ?>
                        <img class="cat-card__img" src="<?= e(upload_url($category['image'])) ?>" alt="" data-category-img>
                    <?php else: ?>
                        <img class="cat-card__img" alt="" data-category-img hidden>
                    <?php endif; ?>
                    <h2 class="cat-card__title"><?= e($category['name']) ?></h2>
                    <div class="tools">
                        <div class="move" data-move>
                            <button class="tool" type="button" data-move-up aria-label="Выше"><?= icon('arrow-up') ?></button>
                            <button class="tool" type="button" data-move-down aria-label="Ниже"><?= icon('arrow-down') ?></button>
                        </div>
                        <label class="tool" title="Фото категории">
                            <?= icon('camera') ?>
                            <input type="file" accept="image/jpeg,image/png,image/webp" hidden data-category-image>
                        </label>
                        <button class="tool tool--danger" type="button" title="Удалить категорию" data-category-delete><?= icon('trash') ?></button>
                        <button class="tool" type="button" title="Настройки категории" data-category-edit><?= icon('gear') ?></button>
                    </div>
                </div>
                <div class="row__side">
                    <button class="tool sort-btn" type="button" title="Порядок товаров" data-sort-toggle><?= icon('sort') ?></button>
                    <label class="switch" title="Показывать на сайте">
                        <input type="checkbox" data-category-toggle<?= $category['is_active'] ? ' checked' : '' ?>>
                        <span class="switch__track"></span>
                    </label>
                </div>
            </div>

            <div class="products" data-products>
                <?php foreach ($category['products'] as $product):
                    $extra  = product_extra($product['extra']);
                    $labels = product_labels($product['labels']); ?>
                    <div class="row row--product<?= $product['is_active'] ? '' : ' is-off' ?>" data-product="<?= (int) $product['id'] ?>"
                         data-search="<?= e(mb_strtolower($product['name'] . ' ' . $product['description'])) ?>"
                         data-json="<?= e(product_json($product)) ?>">
                        <article class="card product">
                            <?php if ($product['image']): ?>
                                <img class="product__img" src="<?= e(upload_url($product['image'])) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <span class="product__img product__img--empty"><?= icon('image') ?></span>
                            <?php endif; ?>
                            <div class="product__main">
                                <div class="product__top">
                                    <div class="product__info">
                                        <h3 class="product__name"><?= e($product['name']) ?></h3>
                                        <?php if ($product['description'] !== null && $product['description'] !== ''): ?>
                                            <p class="product__desc"><?= nl2br(e($product['description'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product__price">
                                        <b><?= e(format_price($product['price'])) ?></b>
                                        <?php if ($product['old_price'] !== null): ?><s><?= e(format_price($product['old_price'])) ?></s><?php endif; ?>
                                        <small><?= e($product['unit_amount'] . ' ' . $product['unit_name']) ?></small>
                                    </div>
                                    <div class="tools">
                                        <div class="move" data-move>
                                            <button class="tool" type="button" data-move-up aria-label="Выше"><?= icon('arrow-up') ?></button>
                                            <button class="tool" type="button" data-move-down aria-label="Ниже"><?= icon('arrow-down') ?></button>
                                        </div>
                                        <button class="tool tool--danger" type="button" title="Удалить товар" data-product-delete><?= icon('trash') ?></button>
                                        <button class="tool tool--dark" type="button" title="Редактировать" data-product-edit><?= icon('gear') ?></button>
                                    </div>
                                </div>

                                <div class="labels" data-labels>
                                    <?php foreach (PRODUCT_LABELS as $key => $label): ?>
                                        <button class="label<?= in_array($key, $labels, true) ? ' is-active' : '' ?>" type="button" data-label="<?= $key ?>"><?= e($label) ?></button>
                                    <?php endforeach; ?>
                                </div>

                                <div class="sections" data-extra-root>
                                    <?php foreach (PRODUCT_SECTIONS as $key => $sectionTitle): ?>
                                        <div class="section<?= $extra[$key]['on'] ? ' is-open' : '' ?>">
                                            <label class="switch switch--sm">
                                                <input type="checkbox" data-extra="<?= $key ?>.on"<?= $extra[$key]['on'] ? ' checked' : '' ?>>
                                                <span class="switch__track"></span>
                                                <span class="switch__label"><?= e($sectionTitle) ?></span>
                                            </label>
                                            <div class="section__body">
                                                <div class="section__inner"><?= render_section_fields($key, $extra[$key]) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </article>
                        <div class="row__side">
                            <label class="switch" title="Показывать на сайте">
                                <input type="checkbox" data-product-toggle<?= $product['is_active'] ? ' checked' : '' ?>>
                                <span class="switch__track"></span>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="row row--product row--add">
                    <button class="add-btn" type="button" data-product-add><?= icon('plus', 'icon icon--sm') ?>Добавить товар</button>
                    <div class="row__side"></div>
                </div>
            </div>
        </section>
    <?php endforeach; ?>

    <p class="catalog__nothing" data-search-empty hidden>Ничего не найдено</p>
</div>

<!-- product modal -->
<div class="modal" data-modal="product" aria-hidden="true">
    <div class="modal__backdrop" data-modal-close></div>
    <form class="modal__dialog" data-product-form novalidate>
        <header class="modal__head">
            <h2 class="modal__title" data-modal-title>Новый товар</h2>
            <button class="tool" type="button" data-modal-close aria-label="Закрыть"><?= icon('close') ?></button>
        </header>
        <div class="modal__body">
            <input type="hidden" name="id">
            <div class="panel panel--media">
                <div class="dropzone dropzone--square" data-dropzone>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-dropzone-input>
                    <input type="hidden" name="remove_image" value="0" data-dropzone-remove>
                    <div class="dropzone__empty">
                        <?= icon('image-up') ?>
                        <span><b>Загрузите</b> фото товара</span>
                        <small>jpg, png, webp</small>
                    </div>
                    <img class="dropzone__preview" alt="">
                    <button class="dropzone__clear" type="button" data-dropzone-clear aria-label="Удалить фото"><?= icon('close', 'icon icon--sm') ?></button>
                </div>
                <div class="panel__fields">
                    <label class="float">
                        <input class="float__input" name="name" placeholder=" " required maxlength="190">
                        <span class="float__label">Название</span>
                    </label>
                    <label class="float">
                        <textarea class="float__input" name="description" placeholder=" " rows="4" maxlength="3000"></textarea>
                        <span class="float__label">Описание</span>
                    </label>
                </div>
            </div>
            <div class="panel panel--grid">
                <label class="float">
                    <input class="float__input" name="price" placeholder=" " inputmode="decimal" required>
                    <span class="float__label">Цена, <?= e(config('currency', '₽')) ?></span>
                </label>
                <label class="float">
                    <input class="float__input" name="old_price" placeholder=" " inputmode="decimal">
                    <span class="float__label">Старая цена (зачёркнутая)</span>
                </label>
                <label class="float">
                    <input class="float__input" name="unit_amount" placeholder=" " value="1" maxlength="20">
                    <span class="float__label">Количество</span>
                </label>
                <label class="float">
                    <input class="float__input" name="unit_name" placeholder=" " value="шт" maxlength="30" list="unit-names">
                    <span class="float__label">Единица</span>
                </label>
                <label class="float float--wide">
                    <select class="float__input" name="category_id">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="float__label">Категория</span>
                </label>
            </div>
            <datalist id="unit-names"><option value="шт"><option value="букет"><option value="композиция"><option value="г"></datalist>
        </div>
        <footer class="modal__foot">
            <button class="btn btn--primary btn--xl" type="submit" data-submit>Сохранить</button>
        </footer>
    </form>
</div>

<!-- category modal -->
<div class="modal modal--sm" data-modal="category" aria-hidden="true">
    <div class="modal__backdrop" data-modal-close></div>
    <form class="modal__dialog" data-category-form novalidate>
        <header class="modal__head">
            <h2 class="modal__title" data-modal-title>Новая категория</h2>
            <button class="tool" type="button" data-modal-close aria-label="Закрыть"><?= icon('close') ?></button>
        </header>
        <div class="modal__body">
            <input type="hidden" name="id">
            <div class="panel">
                <label class="float">
                    <input class="float__input" name="name" placeholder=" " required maxlength="120">
                    <span class="float__label">Название категории</span>
                </label>
            </div>
        </div>
        <footer class="modal__foot">
            <button class="btn btn--primary btn--xl" type="submit" data-submit>Сохранить</button>
        </footer>
    </form>
</div>
