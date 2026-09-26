<?php
define('KOLIBRI_SITE', true);
require __DIR__ . '/admin/core/bootstrap.php';
require_once __DIR__ . '/admin/core/orders.php';
require __DIR__ . '/site/layout.php';

$source     = ($_GET['source'] ?? '') === 'app' ? 'app' : 'site';
$categories = storefront_catalog($source);
$points     = pickup_points();
$types      = delivery_types();
$now        = date('Y-m-d H:i:s');

// hero banners from "Акции и скидки"
$banners = [];
foreach (db()->query('SELECT * FROM promotions WHERE is_active = 1 ORDER BY sort, id DESC')->fetchAll() as $promo) {
    if ($promo['banner_desktop'] === '' && $promo['banner_mobile'] === '') {
        continue;
    }
    $options = promo_options(json_decode((string) $promo['options'], true) ?: []);
    $ended   = $promo['ends_at'] && $promo['ends_at'] < $now;
    $started = !$promo['starts_at'] || $promo['starts_at'] <= $now;
    if ($ended || ($options['active_only'] && !$started)) {
        continue;
    }
    $banners[] = $promo;
}

// what the storefront script needs
$products = [];
foreach ($categories as $category) {
    foreach ($category['products'] as $product) {
        $extra = $product['extra_data'];
        $products[(int) $product['id']] = [
            'name'  => $product['name'],
            'unit'  => trim($product['unit_amount'] . ' ' . $product['unit_name']),
            'price' => (float) $product['price'],
            'old'   => $product['old_price'] !== null ? (float) $product['old_price'] : null,
            'image' => upload_url($product['image']),
            'desc'  => (string) $product['description'],
            'comp'  => $extra['composition']['on'] ? trim($extra['composition']['text']) : '',
            'size'  => $extra['composition']['on'] ? array_filter(['Высота' => $extra['composition']['height'], 'Ширина' => $extra['composition']['width']]) : [],
            'min'   => $extra['limits']['on'] ? max(1, (int) $extra['limits']['min']) : 1,
            'max'   => min(99, $extra['limits']['on'] && (int) $extra['limits']['max'] > 0 ? (int) $extra['limits']['max'] : 99, $extra['stock']['on'] ? (int) $extra['stock']['qty'] : 99),
        ];
    }
}

$config = [
    'currency'  => config('currency', '₽'),
    'source'    => $source,
    'types'     => $types,
    'points'    => array_map(static fn ($p) => ['id' => (int) $p['id'], 'address' => $p['address'], 'hours' => $p['hours'], 'lat' => $p['lat'], 'lng' => $p['lng']], $points),
    'payments'  => enabled_payment_methods(),
    'askChange' => setting('pay_change', '1') === '1',
    'asap'      => setting('asap_enabled', '1') === '1',
    'preorders' => setting('preorders_enabled', '1') === '1',
    'minMinutes' => (int) setting('preorder_min_minutes', '60'),
    'maxDays'   => (int) setting('preorder_max_days', '14'),
    'step'      => (int) setting('preorder_step', '30'),
    'readyIn'   => READY_IN_OPTIONS,
    'schedule'  => schedule_value(setting('schedule')),
    'openNow'   => schedule_allows(new DateTimeImmutable()),
    'now'       => date('Y-m-d\TH:i:s'), // the shop's clock; pre-order slots use it, not the device clock
    'active'    => setting('shop_active', '1') === '1',
    'city'      => (string) setting('delivery_city', 'Новокузнецк'),
    'center'    => (string) setting('map_center', '53.7557, 87.1099'),
    'deliveryInfo' => (string) setting('delivery_info', ''),
    'orderMessage' => (string) setting('order_message', '') ?: 'Вы получите уведомление, когда наш оператор его примет.',
];

site_head(config('app_name', 'Колибри') . ' — студия цветов');
?>
<div class="page" data-page>
    <?php site_header($categories); ?>

    <main class="container main">
        <?php if (setting('text_banner', '0') === '1' && trim((string) setting('text_banner_text', '')) !== ''): ?>
            <div class="notice-bar"><?= nl2br(e(setting('text_banner_text'))) ?></div>
        <?php endif; ?>
        <?php if (!$config['active']): ?>
            <div class="notice-bar notice-bar--warn">Магазин временно не принимает заказы. Скоро вернёмся!</div>
        <?php endif; ?>

        <section class="hero<?= count($banners) > 1 ? ' hero--slider' : '' ?>" data-hero>
            <?php if ($banners): ?>
                <div class="hero__track" data-hero-track>
                    <?php foreach ($banners as $n => $banner):
                        $desktop = upload_url($banner['banner_desktop'] ?: $banner['banner_mobile']);
                        $mobile  = upload_url($banner['banner_mobile'] ?: $banner['banner_desktop']); ?>
                        <a class="hero__slide" href="page.php?p=promotions#promo-<?= (int) $banner['id'] ?>" aria-label="<?= e($banner['name']) ?>">
                            <picture>
                                <source media="(max-width: 700px)" srcset="<?= e($mobile) ?>">
                                <img src="<?= e($desktop) ?>" alt="<?= e($banner['name']) ?>"<?= $n > 0 ? ' loading="lazy"' : '' ?>>
                            </picture>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if (count($banners) > 1): ?>
                    <div class="hero__dots" data-hero-dots>
                        <?php foreach ($banners as $n => $banner): ?><button type="button" class="<?= $n === 0 ? 'is-active' : '' ?>" aria-label="Слайд <?= $n + 1 ?>"></button><?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="hero__default">
                    <div class="hero__text">
                        <span class="hero__pill">Добро пожаловать!</span>
                        <h1 class="hero__title">Свежие букеты<br>с доставкой<br>и самовывозом</h1>
                    </div>
                    <?= bird_svg('hero__bird') ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($types): ?>
            <div class="mode-row">
                <div class="seg" data-seg="mode">
                    <?php foreach ($types as $key => $label): ?>
                        <button class="seg__btn" type="button" data-mode="<?= $key ?>"><?= e($label) ?></button>
                    <?php endforeach; ?>
                    <span class="seg__pill"></span>
                </div>
                <button class="place-btn" type="button" data-open="where">
                    <?= site_icon('pin') ?><span data-place-label>Выберите адрес</span><?= site_icon('chevron') ?>
                </button>
            </div>
        <?php endif; ?>

        <nav class="chips" data-chips>
            <?php foreach ($categories as $category): ?>
                <a class="chip" href="#c-<?= (int) $category['id'] ?>" data-chip="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if (!$categories): ?>
            <p class="empty-catalog">Каталог скоро появится 🌷</p>
        <?php endif; ?>

        <?php foreach ($categories as $category): ?>
            <section class="cat" id="c-<?= (int) $category['id'] ?>" data-cat="<?= (int) $category['id'] ?>">
                <h2 class="cat__title"><?= e($category['name']) ?></h2>
                <div class="grid">
                    <?php foreach ($category['products'] as $product):
                        $labels = product_labels($product['labels']); ?>
                        <article class="pcard" data-pid="<?= (int) $product['id'] ?>" tabindex="0" aria-label="<?= e($product['name']) ?>">
                            <div class="pcard__img">
                                <?php if ($product['image']): ?>
                                    <img src="<?= e(upload_url($product['image'])) ?>" alt="" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <?= bird_svg('pcard__placeholder') ?>
                                <?php endif; ?>
                                <?php if ($labels): ?>
                                    <div class="pcard__labels">
                                        <?php foreach ($labels as $label): ?><span class="plabel plabel--<?= e($label) ?>"><?= e(PRODUCT_LABELS[$label]) ?></span><?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="pcard__body">
                                <h3 class="pcard__name"><?= e($product['name']) ?> <span class="pcard__unit"><?= e(trim($product['unit_amount'] . ' ' . $product['unit_name'])) ?></span></h3>
                                <?php if ((string) $product['description'] !== ''): ?>
                                    <p class="pcard__desc"><?= e($product['description']) ?></p>
                                <?php endif; ?>
                                <div class="pcard__foot">
                                    <span class="pcard__price"><?= e(price_label((float) $product['price'])) ?><?php if ($product['old_price'] !== null): ?> <s><?= e(price_label((float) $product['old_price'])) ?></s><?php endif; ?></span>
                                    <div class="stepper stepper--sm" data-card-stepper hidden>
                                        <button class="stepper__btn" type="button" data-step="-1" aria-label="Меньше"><?= site_icon('minus') ?></button>
                                        <span class="stepper__val" data-qty>1</span>
                                        <button class="stepper__btn" type="button" data-step="1" aria-label="Больше"><?= site_icon('plus') ?></button>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>

    <?php site_footer(); ?>
</div>

<!-- cart / checkout / success -->
<aside class="cart" data-cart aria-label="Корзина" aria-hidden="true">
    <div class="cart__backdrop" data-cart-close></div>
    <div class="cart__panel">
        <section class="cart__view is-active" data-view="cart">
            <header class="cart__head">
                <h2 class="cart__title">Мой заказ</h2>
                <button class="round-btn" type="button" data-cart-clear aria-label="Очистить корзину"><?= site_icon('trash') ?></button>
                <button class="round-btn" type="button" data-cart-close aria-label="Закрыть"><?= site_icon('close') ?></button>
            </header>
            <div class="cart__scroll">
                <?php if (count($types) > 1): ?>
                    <div class="seg seg--sm" data-seg="mode">
                        <?php foreach ($types as $key => $label): ?>
                            <button class="seg__btn" type="button" data-mode="<?= $key ?>"><?= e($label) ?></button>
                        <?php endforeach; ?>
                        <span class="seg__pill"></span>
                    </div>
                <?php endif; ?>
                <button class="place-btn place-btn--sm" type="button" data-open="where"><?= site_icon('pin') ?><span data-place-label>Выберите адрес</span></button>
                <div class="lines" data-lines></div>
                <div class="cart-empty" data-cart-empty>
                    <?= bird_svg('cart-empty__bird') ?>
                    <p>В корзине пока пусто.<br>Выберите букет, который порадует!</p>
                </div>
                <div class="cart__filled" data-cart-filled>
                    <label class="promo" data-promo>
                        <input class="promo__input" type="text" placeholder="Промокод" autocomplete="off" autocapitalize="characters" data-promo-input>
                        <button class="promo__apply" type="button" data-promo-apply>Применить</button>
                    </label>
                    <p class="promo__msg" data-promo-msg></p>
                    <div class="summary" data-summary></div>
                    <p class="cart__error" data-cart-error></p>
                </div>
            </div>
            <footer class="cart__foot" data-cart-filled>
                <button class="big-btn" type="button" data-go="checkout">Продолжить оформление</button>
            </footer>
        </section>

        <section class="cart__view" data-view="checkout">
            <form class="checkout" data-checkout novalidate>
                <header class="cart__head">
                    <button class="round-btn" type="button" data-go="cart" aria-label="Назад"><span class="flip"><?= site_icon('arrow') ?></span></button>
                    <h2 class="cart__title" data-checkout-title>Оформление</h2>
                    <button class="round-btn" type="button" data-cart-close aria-label="Закрыть"><?= site_icon('close') ?></button>
                </header>
                <div class="cart__scroll">
                    <button class="place-btn place-btn--sm" type="button" data-open="where"><?= site_icon('pin') ?><span data-place-label>Выберите адрес</span></button>
                    <div class="fields">
                        <label class="field"><?= site_icon('user') ?><input name="name" placeholder="Ваше имя" autocomplete="name" maxlength="120" required></label>
                        <label class="field"><?= site_icon('phone') ?><input name="phone" type="tel" placeholder="Ваш номер телефона" autocomplete="tel" inputmode="tel" required data-phone-mask></label>
                    </div>
                    <div class="seg seg--sm seg--dark" data-seg="when">
                        <button class="seg__btn" type="button" data-when="now">В ближайшее время</button>
                        <button class="seg__btn" type="button" data-when="later">Предзаказ</button>
                        <span class="seg__pill"></span>
                    </div>
                    <p class="checkout__note" data-closed-note hidden>Сейчас мы закрыты — оформите предзаказ на удобное время.</p>
                    <label class="field field--select" data-ready-in>
                        <?= site_icon('clock') ?>
                        <select name="ready_in"><?php foreach (READY_IN_OPTIONS as $m): ?><option value="<?= $m ?>"<?= $m === 30 ? ' selected' : '' ?>>Заберу через <?= $m ?> мин</option><?php endforeach; ?></select>
                        <span class="field__caption">Через сколько минут заберёте?</span>
                    </label>
                    <div class="fields fields--row" data-later hidden>
                        <label class="field field--select"><select name="date" data-date></select><span class="field__caption">Дата</span></label>
                        <label class="field field--select"><select name="time" data-time></select><span class="field__caption">Время</span></label>
                    </div>
                    <label class="field field--select">
                        <?= site_icon('wallet') ?>
                        <select name="payment_method" data-payment>
                            <?php foreach (enabled_payment_methods() as $key => $label): ?><option value="<?= $key ?>"><?= e($label) ?></option><?php endforeach; ?>
                        </select>
                        <span class="field__caption">Способ оплаты</span>
                    </label>
                    <label class="field" data-change hidden><?= site_icon('wallet') ?><input name="change_from" inputmode="numeric" placeholder="С какой суммы подготовить сдачу?" maxlength="10"></label>
                    <label class="field field--area"><?= site_icon('comment') ?><textarea name="comment" rows="2" placeholder="Комментарий к заказу" maxlength="1000"></textarea></label>
                    <input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div class="summary" data-summary></div>
                    <p class="cart__error" data-checkout-error></p>
                </div>
                <footer class="cart__foot">
                    <button class="big-btn" type="submit" data-submit>Заказать</button>
                    <p class="checkout__legal">Нажимая «Заказать», вы соглашаетесь с <a href="page.php?p=terms" target="_blank">условиями</a> и <a href="page.php?p=privacy" target="_blank">политикой конфиденциальности</a>.</p>
                </footer>
            </form>
        </section>

        <section class="cart__view" data-view="done">
            <header class="cart__head">
                <span></span>
                <button class="round-btn" type="button" data-cart-close aria-label="Закрыть"><?= site_icon('close') ?></button>
            </header>
            <div class="cart__scroll done">
                <div class="done__check"><svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="24"/><path d="M15 27l7 7 15-16"/></svg></div>
                <h2 class="done__title" data-done-title>Заказ принят!</h2>
                <p class="done__text" data-done-text></p>
                <div class="summary summary--done" data-done-summary></div>
            </div>
            <footer class="cart__foot">
                <button class="big-btn" type="button" data-cart-close>Вернуться в каталог</button>
            </footer>
        </section>
    </div>
</aside>

<!-- product -->
<div class="smodal smodal--product" data-smodal="product" aria-hidden="true">
    <div class="smodal__backdrop" data-close></div>
    <div class="smodal__dialog pm" role="dialog" aria-modal="true">
        <div class="pm__grab" aria-hidden="true"></div>
        <div class="pm__img" data-pm-img></div>
        <div class="pm__info">
            <button class="round-btn smodal__close" type="button" data-close aria-label="Закрыть"><?= site_icon('close') ?></button>
            <h2 class="pm__name" data-pm-name></h2>
            <div class="pm__price"><b data-pm-price></b><s data-pm-old></s><span class="pm__dot">•</span><span data-pm-unit></span></div>
            <div class="pm__desc" data-pm-desc></div>
            <div class="pm__foot">
                <div class="stepper" data-pm-stepper>
                    <button class="stepper__btn" type="button" data-step="-1" aria-label="Меньше"><?= site_icon('minus') ?></button>
                    <span class="stepper__val" data-qty>1</span>
                    <button class="stepper__btn" type="button" data-step="1" aria-label="Больше"><?= site_icon('plus') ?></button>
                </div>
                <button class="buy-btn" type="button" data-pm-buy><span>В корзину</span><i></i><b data-pm-total></b></button>
            </div>
        </div>
    </div>
</div>

<!-- delivery / pickup -->
<div class="smodal smodal--where" data-smodal="where" aria-hidden="true">
    <div class="smodal__backdrop" data-close></div>
    <div class="smodal__dialog where" role="dialog" aria-modal="true" aria-label="Способ получения">
        <div class="where__side">
            <?php if (count($types) > 1): ?>
                <div class="seg" data-seg="mode">
                    <?php foreach ($types as $key => $label): ?>
                        <button class="seg__btn" type="button" data-mode="<?= $key ?>"><?= e($label) ?></button>
                    <?php endforeach; ?>
                    <span class="seg__pill"></span>
                </div>
            <?php endif; ?>
            <div class="where__pane" data-pane="pickup">
                <?php if (!$points): ?><p class="muted">Пункты самовывоза пока не добавлены.</p><?php endif; ?>
                <?php foreach ($points as $point): ?>
                    <label class="point-card">
                        <input type="radio" name="point" value="<?= (int) $point['id'] ?>" data-point>
                        <span class="point-card__text"><b><?= e($point['address']) ?></b><?php if ($point['hours'] !== ''): ?><small><?= e($point['hours']) ?></small><?php endif; ?></span>
                        <span class="point-card__radio"></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="where__pane" data-pane="delivery">
                <div class="fields">
                    <label class="field field--caption"><input data-addr="address" placeholder=" " autocomplete="street-address"><span class="field__caption">Адрес доставки</span></label>
                    <div class="fields__row">
                        <label class="field field--caption"><input data-addr="apartment" placeholder=" " maxlength="20"><span class="field__caption">Квартира</span></label>
                        <label class="field field--caption"><input data-addr="entrance" placeholder=" " maxlength="20"><span class="field__caption">Подъезд</span></label>
                        <label class="field field--caption"><input data-addr="floor" placeholder=" " maxlength="20"><span class="field__caption">Этаж</span></label>
                    </div>
                </div>
                <?php if ($config['deliveryInfo'] !== ''): ?><p class="where__info"><?= nl2br(e($config['deliveryInfo'])) ?></p><?php endif; ?>
            </div>
            <button class="big-btn big-btn--arrow" type="button" data-where-done>Продолжить <?= site_icon('arrow') ?></button>
        </div>
        <div class="where__map" data-map></div>
        <button class="round-btn smodal__close" type="button" data-close aria-label="Закрыть"><?= site_icon('close') ?></button>
    </div>
</div>

<!-- search -->
<div class="smodal smodal--search" data-smodal="search" aria-hidden="true">
    <div class="smodal__backdrop" data-close></div>
    <div class="smodal__dialog search-box" role="dialog" aria-label="Поиск">
        <label class="search-box__field"><?= site_icon('search') ?><input type="search" placeholder="Найти букет, цветы, композицию…" data-search-input autocomplete="off"></label>
        <button class="round-btn" type="button" data-close aria-label="Закрыть"><?= site_icon('close') ?></button>
        <div class="search-box__results" data-search-results></div>
    </div>
</div>

<?php site_contacts_modal($points); ?>

<!-- install as an app -->
<div class="install" data-install hidden>
    <img class="install__icon" src="assets/icons/icon-192.png" alt="">
    <div class="install__text">
        <b>Скачивай наше приложение</b>
        <span data-install-text>Получай эксклюзивные бонусы, скидки, акции и промокоды.</span>
    </div>
    <button class="install__btn" type="button" data-install-go>Установить</button>
    <button class="install__close" type="button" data-install-close aria-label="Закрыть"><?= site_icon('close') ?></button>
</div>

<button class="to-top" type="button" data-to-top aria-label="Наверх"><?= site_icon('up') ?></button>
<button class="cart-bar" type="button" data-cart-open data-cart-bar hidden><?= site_icon('basket') ?><span>Корзина</span><b data-cart-total>0 ₽</b></button>

<script>window.KOLIBRI = <?= json_encode(['config' => $config, 'products' => $products], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(site_asset('site.js')) ?>" defer></script>
<?= setting('snippet_body', '') ?>
</body>
</html>
