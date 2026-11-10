<?php
/*
 * Telegram Mini App: the shop inside Telegram. Opened from the bot's menu
 * button; works in a normal browser too (preview with an in-page button).
 * Same catalog, prices and orders as the website.
 */
define('KOLIBRI_SITE', true);
require dirname(__DIR__) . '/admin/core/bootstrap.php';
require_once dirname(__DIR__) . '/admin/core/telegram_app.php';
require dirname(__DIR__) . '/site/layout.php';
require dirname(__DIR__) . '/site/data.php';

// Telegram Web shows Mini Apps in an iframe
header_remove('X-Frame-Options');
header("Content-Security-Policy: frame-ancestors 'self' https://web.telegram.org https://*.telegram.org");

$skin       = tg_skin();
$categories = storefront_catalog('app');
$products   = storefront_products($categories);
$config     = storefront_config('tg');
$banners    = array_map(static fn ($b) => [
    'id'    => (int) $b['id'],
    'name'  => $b['name'],
    'image' => upload_url($b['banner_mobile'] ?: $b['banner_desktop']),
], storefront_banners());

$phone  = (string) setting('contact_phone', '');
$points = $config['points'];
$name   = (string) config('app_name', 'Колибри');
$base   = rtrim((string) config('site_url', '/'), '/') . '/';

$fonts = [
    'botanika' => 'family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&family=Inter:wght@400;500;600;700',
    'nochnoy'  => 'family=Unbounded:wght@500;700&family=Manrope:wght@400;500;600;700;800',
    'pastel'   => 'family=Onest:wght@400;500;600;700;800;900',
];
$heroLines = [
    'botanika' => ['Букеты,', 'собранные', '<i>с любовью</i>'],
    'nochnoy'  => ['Цветы', 'даже ночью'],
    'pastel'   => ['Свежие', 'букеты', 'за час'],
];

$data = [
    'skin'       => $skin,
    'csrf'       => csrf_token(),
    'api'        => $base . 'api/',
    'config'     => $config,
    'categories' => array_map(static fn ($c) => ['id' => (int) $c['id'], 'name' => $c['name'], 'items' => array_map(static fn ($p) => (int) $p['id'], $c['products'])], $categories),
    'products'   => $products,
    'phone'      => $phone,
    'transfer'   => (string) setting('pay_transfer_details', ''),
];

$tgIcon = static function (string $name): string {
    $icons = [
        'bag'     => '<path d="M5 8h14l-1 12H6z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/>',
        'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'tg'      => '<path d="M21 4 3 11l6 2 2 6 3-4 5 4z" fill="currentColor" stroke="none"/>',
        'gift'    => '<rect x="3.5" y="8" width="17" height="4" rx="1"/><path d="M5 12v8h14v-8M12 8v12M12 8c-1.5-3-5-4-5.5-2S9 8 12 8c3 0 6-.5 5.5-2S13.5 5 12 8"/>',
    ];
    return '<svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icons[$name] ?? '') . '</svg>';
};
$icon = static fn (string $name): string => site_icon($name);
?>
<!doctype html>
<html lang="ru" class="skin-<?= $skin ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <title><?= e($name) ?> — студия цветов</title>
    <meta name="format-detection" content="telephone=no">
    <meta name="robots" content="noindex">
    <link rel="icon" href="<?= e($base) ?>admin/assets/img/kolibri-mark.svg" type="image/svg+xml">
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script>
        (function (d) {
            var tg = window.Telegram && window.Telegram.WebApp;
            d.documentElement.classList.add(tg && tg.initData ? 'in-tg' : 'no-tg');
            if (tg && tg.platform) { d.documentElement.classList.add('tg-' + tg.platform.replace(/[^a-z]/g, '')); }
        })(document);
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?<?= $fonts[$skin] ?>&display=swap">
    <link rel="stylesheet" href="app.css?v=<?= filemtime(__DIR__ . '/app.css') ?>">
</head>
<body>
<div class="shell" data-shell>
    <div class="screen" data-screen>
        <div class="scroller" data-scroller>
            <header class="top">
                <div class="brand">
                    <?= bird_svg('brand__bird') ?>
                    <span class="brand__name"><b>Колибри</b><small>студия цветов</small></span>
                </div>
                <button class="icon-btn" type="button" data-go="orders" aria-label="Мои заказы"><?= $tgIcon('receipt') ?></button>
            </header>

            <section class="hero hero--<?= $skin ?>">
                <?php if ($skin === 'nochnoy'): ?>
                    <div class="hero__sky" aria-hidden="true"><?php for ($i = 0; $i < 18; $i++): ?><i style="--x:<?= ($i * 37) % 100 ?>%;--y:<?= ($i * 53) % 90 ?>%;--d:<?= ($i % 6) * .5 ?>s"></i><?php endfor; ?></div>
                    <?= bird_svg('hero__bird') ?>
                <?php elseif ($skin === 'pastel'): ?>
                    <span class="hero__blob hero__blob--1" aria-hidden="true"></span><span class="hero__blob hero__blob--2" aria-hidden="true"></span>
                <?php endif; ?>
                <p class="hero__hello" data-hello>Добро пожаловать</p>
                <h1 class="hero__title">
                    <?php foreach ($heroLines[$skin] as $n => $line): ?><span class="hl" style="--n:<?= $n ?>"><span><?= $line ?></span></span><?php endforeach; ?>
                </h1>
                <p class="hero__sub">Новокузнецк · круглосуточно · доставка и самовывоз</p>
                <?php if ($skin === 'pastel'): ?>
                    <div class="bento">
                        <div class="bento__tile bento__tile--mint" style="--n:0"><b>24/7</b><span>принимаем заказы</span></div>
                        <div class="bento__tile bento__tile--lilac" style="--n:1"><b><?= count($points) ?: 2 ?></b><span><?= plural(count($points) ?: 2, 'точка', 'точки', 'точек') ?> самовывоза</span></div>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($banners): ?>
                <div class="banners" data-banners>
                    <?php foreach ($banners as $banner): ?>
                        <div class="banner"><img src="<?= e($banner['image']) ?>" alt="<?= e($banner['name']) ?>" loading="lazy"></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!$config['active']): ?>
                <div class="note note--warn">Магазин временно не принимает заказы. Скоро вернёмся!</div>
            <?php elseif (setting('text_banner', '0') === '1' && trim((string) setting('text_banner_text', '')) !== ''): ?>
                <div class="note"><?= nl2br(e(setting('text_banner_text'))) ?></div>
            <?php endif; ?>

            <nav class="chips" data-chips>
                <div class="chips__track">
                    <?php foreach ($categories as $category): ?>
                        <button class="chip" type="button" data-chip="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></button>
                    <?php endforeach; ?>
                    <span class="chips__ink" data-ink></span>
                </div>
            </nav>

            <main class="catalog" data-catalog>
                <?php if (!$categories): ?>
                    <div class="empty"><?= bird_svg('empty__bird') ?><p>Каталог скоро появится</p></div>
                <?php endif; ?>
                <?php foreach ($categories as $category): ?>
                    <section class="cat" data-cat="<?= (int) $category['id'] ?>">
                        <h2 class="cat__title"><?= e($category['name']) ?></h2>
                        <div class="grid">
                            <?php foreach ($category['products'] as $n => $product):
                                $p = $products[(int) $product['id']]; ?>
                                <article class="card" data-product="<?= (int) $product['id'] ?>" style="--n:<?= $n % 6 ?>">
                                    <div class="card__img ph">
                                        <?php if ($p['image'] !== ''): ?><img src="<?= e($p['image']) ?>" alt="" loading="lazy"><?php endif; ?>
                                        <?php if ($p['old'] && $p['old'] > $p['price']): ?><span class="card__badge">−<?= (int) round(100 - $p['price'] / $p['old'] * 100) ?>%</span><?php endif; ?>
                                    </div>
                                    <div class="card__body">
                                        <h3 class="card__name"><?= e($p['name']) ?></h3>
                                        <?php if ($p['unit'] !== ''): ?><p class="card__unit"><?= e($p['unit']) ?></p><?php endif; ?>
                                        <div class="card__foot">
                                            <span class="price"><?= price_label($p['price']) ?><?php if ($p['old'] && $p['old'] > $p['price']): ?> <s><?= price_label($p['old']) ?></s><?php endif; ?></span>
                                            <div class="add" data-add>
                                                <button class="add__btn" type="button" data-inc aria-label="Добавить"><?= $icon('plus') ?></button>
                                                <span class="add__qty" data-qty>0</span>
                                                <button class="add__minus" type="button" data-dec aria-label="Убрать"><?= $icon('minus') ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </main>

            <footer class="foot">
                <?= bird_svg('foot__bird') ?>
                <p class="foot__name">«Колибри — студия цветов»</p>
                <?php foreach ($points as $point): ?>
                    <p class="foot__row"><?= $icon('pin') ?><?= e($point['address']) ?><?php if ($point['hours'] !== ''): ?> · <?= e($point['hours']) ?><?php endif; ?></p>
                <?php endforeach; ?>
                <?php if ($phone !== ''): ?>
                    <a class="foot__row" href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= $icon('phone') ?><?= e($phone) ?></a>
                <?php endif; ?>
            </footer>
        </div>
    </div>

    <!-- product -->
    <div class="sheet" data-view="product" aria-hidden="true">
        <div class="sheet__backdrop" data-back></div>
        <div class="sheet__panel" data-sheet-panel>
            <span class="sheet__grip"></span>
            <div class="sheet__scroll">
                <div class="sheet__img ph" data-p-img></div>
                <div class="sheet__body">
                    <h2 class="sheet__title" data-p-name></h2>
                    <p class="sheet__unit" data-p-unit></p>
                    <p class="sheet__price price" data-p-price></p>
                    <p class="sheet__desc" data-p-desc></p>
                    <div class="sheet__comp" data-p-comp></div>
                    <div class="stepper" data-p-stepper>
                        <button type="button" data-p-dec aria-label="Меньше"><?= $icon('minus') ?></button>
                        <b data-p-qty>1</b>
                        <button type="button" data-p-inc aria-label="Больше"><?= $icon('plus') ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- cart -->
    <section class="view" data-view="cart" aria-hidden="true">
        <header class="vhead"><button class="vhead__back" type="button" data-back aria-label="Назад"><?= $icon('chevron') ?></button><h2>Мой заказ</h2><button class="vhead__act" type="button" data-clear>Очистить</button></header>
        <div class="view__scroll">
            <div class="lines" data-lines></div>
            <div class="empty empty--cart" data-cart-empty hidden><?= bird_svg('empty__bird') ?><p>Здесь пока пусто.<br>Выберите букет в каталоге 🌷</p></div>
            <div class="box promo" data-promo-box>
                <input class="promo__input" data-promo placeholder="Промокод" maxlength="40" autocapitalize="characters" autocomplete="off">
                <button class="promo__btn" type="button" data-promo-apply>Применить</button>
            </div>
            <p class="promo__msg" data-promo-msg></p>
            <div class="box totals" data-totals></div>
        </div>
    </section>

    <!-- checkout -->
    <section class="view" data-view="checkout" aria-hidden="true">
        <header class="vhead"><button class="vhead__back" type="button" data-back aria-label="Назад"><?= $icon('chevron') ?></button><h2>Оформление</h2></header>
        <form class="view__scroll form" data-form novalidate>
            <input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">

            <div class="box">
                <h3 class="box__title">Как получить</h3>
                <div class="seg" data-seg="mode">
                    <?php foreach ($config['types'] as $key => $label): ?><button type="button" class="seg__btn" data-mode="<?= $key ?>"><?= e($label) ?></button><?php endforeach; ?>
                    <span class="seg__pill"></span>
                </div>
                <div class="pane" data-pane="delivery">
                    <label class="field"><span>Адрес доставки</span><input name="address" data-addr="address" placeholder="Улица, дом" autocomplete="street-address" maxlength="255"></label>
                    <div class="row3">
                        <label class="field"><span>Кв.</span><input name="apartment" data-addr="apartment" maxlength="20" inputmode="numeric"></label>
                        <label class="field"><span>Подъезд</span><input name="entrance" data-addr="entrance" maxlength="20" inputmode="numeric"></label>
                        <label class="field"><span>Этаж</span><input name="floor" data-addr="floor" maxlength="20" inputmode="numeric"></label>
                    </div>
                    <?php if ($config['deliveryInfo'] !== ''): ?><p class="hint"><?= nl2br(e($config['deliveryInfo'])) ?></p><?php endif; ?>
                </div>
                <div class="pane" data-pane="pickup">
                    <?php foreach ($points as $n => $point): ?>
                        <label class="opt"><input type="radio" name="pickup_point_id" value="<?= $point['id'] ?>"<?= $n === 0 ? ' checked' : '' ?>><span class="opt__dot"></span><span class="opt__text"><b><?= e($point['address']) ?></b><?php if ($point['hours'] !== ''): ?><small><?= e($point['hours']) ?></small><?php endif; ?></span></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="box">
                <h3 class="box__title">Когда</h3>
                <div class="seg" data-seg="when">
                    <button type="button" class="seg__btn" data-when="now">Как можно скорее</button>
                    <button type="button" class="seg__btn" data-when="later">Ко времени</button>
                    <span class="seg__pill"></span>
                </div>
                <p class="hint" data-closed hidden>Сейчас мы закрыты — выберите удобное время.</p>
                <div data-when-pane="now">
                    <label class="field" data-ready><span>Заберу через</span><select name="ready_in"><?php foreach (READY_IN_OPTIONS as $m): ?><option value="<?= $m ?>"<?= $m === 30 ? ' selected' : '' ?>><?= $m ?> мин</option><?php endforeach; ?></select></label>
                </div>
                <div data-when-pane="later" hidden>
                    <div class="slots slots--days" data-days></div>
                    <div class="slots slots--times" data-times></div>
                </div>
            </div>

            <div class="box">
                <h3 class="box__title">Контакты</h3>
                <label class="field"><span>Имя</span><input name="name" autocomplete="given-name" maxlength="120"></label>
                <label class="field field--phone"><span>Телефон</span><input name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="+7 ___ ___-__-__" maxlength="18" data-phone>
                    <button class="share-phone" type="button" data-share-phone hidden><?= $tgIcon('tg') ?>Из Telegram</button>
                </label>
            </div>

            <div class="box">
                <h3 class="box__title">Оплата при получении</h3>
                <?php $first = true; foreach ($config['payments'] as $key => $label): ?>
                    <label class="opt"><input type="radio" name="payment_method" value="<?= e($key) ?>"<?= $first ? ' checked' : '' ?>><span class="opt__dot"></span><span class="opt__text"><b><?= e($label) ?></b></span></label>
                <?php $first = false; endforeach; ?>
                <label class="field" data-change hidden><span>Сдача с суммы</span><input name="change_from" inputmode="numeric" maxlength="10" placeholder="Например, 5000"></label>
            </div>

            <div class="box">
                <h3 class="box__title">Пожелания</h3>
                <label class="field"><span>Комментарий или текст открытки</span><textarea name="comment" rows="3" maxlength="1000"></textarea></label>
            </div>

            <div class="box totals" data-totals></div>
            <p class="form__error" data-error role="alert"></p>
        </form>
    </section>

    <!-- success -->
    <section class="view view--done" data-view="done" aria-hidden="true">
        <div class="view__scroll done">
            <div class="done__bloom" aria-hidden="true"><?php for ($i = 0; $i < 8; $i++): ?><i style="--r:<?= $i * 45 ?>deg"></i><?php endfor; ?><span class="done__check"><?= $icon('check') ?></span></div>
            <h2 class="done__title">Заказ <span data-done-id></span> оформлен!</h2>
            <p class="done__msg" data-done-msg></p>
            <div class="box done__card">
                <p><?= $icon('pin') ?><span data-done-place></span></p>
                <p><?= $icon('clock') ?><span data-done-time></span></p>
                <p><?= $icon('wallet') ?><span data-done-total></span></p>
            </div>
            <p class="done__tg"><?= $tgIcon('tg') ?>О статусе заказа напишем в этот чат</p>
            <?php if ($config['payments']['transfer'] ?? false): ?><p class="done__pay" data-done-pay hidden></p><?php endif; ?>
        </div>
    </section>

    <!-- my orders -->
    <section class="view" data-view="orders" aria-hidden="true">
        <header class="vhead"><button class="vhead__back" type="button" data-back aria-label="Назад"><?= $icon('chevron') ?></button><h2>Мои заказы</h2></header>
        <div class="view__scroll" data-orders></div>
    </section>

    <button class="mainbtn" type="button" data-mainbtn hidden><span data-mainbtn-text></span></button>
    <div class="toast" data-toast role="status"></div>
</div>

<script type="application/json" id="kolibri-data"><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="app.js?v=<?= filemtime(__DIR__ . '/app.js') ?>"></script>
</body>
</html>
