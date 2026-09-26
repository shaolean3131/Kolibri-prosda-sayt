<?php
defined('KOLIBRI') or exit;

/** Line-art hummingbird (the brand mark). */
function bird_svg(string $class = ''): string
{
    return '<svg class="' . e($class) . '" viewBox="0 0 520 440" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<g class="bird__body">'
        . '<path d="M14 18 124 98" stroke-width="10"/>'
        . '<path d="M122 96c24-24 66-16 72 22 6 48-26 88-14 136 12 46 58 76 110 94" stroke-width="16"/>'
        . '<circle cx="152" cy="112" r="9" fill="currentColor" stroke="none"/>'
        . '<path d="M262 340l18 84M292 344l34 76M322 342l46 62" stroke-width="14"/>'
        . '<path d="M196 248l6 50M218 252l16 46M240 254l24 38" stroke-width="13"/>'
        . '</g>'
        . '<g class="bird__wing">'
        . '<path d="M208 206c70-54 160-76 270-66" stroke-width="16"/>'
        . '<path d="M222 226c72-40 160-50 280-34" stroke-width="16"/>'
        . '<path d="M232 246c70-26 150-28 250-4" stroke-width="16"/>'
        . '<path d="M240 266c56-10 116-4 180 22" stroke-width="16"/>'
        . '</g>'
        . '</svg>';
}

function site_icon(string $name): string
{
    $icons = [
        'grid'    => '<g fill="currentColor"><circle cx="5" cy="5" r="1.9"/><circle cx="12" cy="5" r="1.9"/><circle cx="19" cy="5" r="1.9"/><circle cx="5" cy="12" r="1.9"/><circle cx="12" cy="12" r="1.9"/><circle cx="19" cy="12" r="1.9"/><circle cx="5" cy="19" r="1.9"/><circle cx="12" cy="19" r="1.9"/><circle cx="19" cy="19" r="1.9"/></g>',
        'search'  => '<circle cx="10.8" cy="10.8" r="6.3"/><path d="M15.6 15.6l4.6 4.6"/>',
        'basket'  => '<path d="M3.5 9.5h17l-1.6 9.1a2 2 0 0 1-2 1.7H7.1a2 2 0 0 1-2-1.7z"/><path d="M8 9.5l3-5.5M16 9.5l-3-5.5M3 9.5h18"/>',
        'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4.5 20.5c1.2-3.8 4-5.8 7.5-5.8s6.3 2 7.5 5.8"/>',
        'phone'   => '<path d="M7 3.5h2.6l1.3 4-2 1.5a11 11 0 0 0 6.1 6.1l1.5-2 4 1.3V17a3.5 3.5 0 0 1-3.8 3.5C9.7 19.9 4.1 14.3 3.5 7.3A3.5 3.5 0 0 1 7 3.5z"/>',
        'pin'     => '<path d="M12 21s-6.5-6.3-6.5-11.2a6.5 6.5 0 0 1 13 0C18.5 14.7 12 21 12 21z" fill="currentColor" stroke="none"/><circle cx="12" cy="9.8" r="2.4" fill="#fff" stroke="none"/>',
        'chevron' => '<path d="M9 6l6 6-6 6"/>',
        'down'    => '<path d="M6 9l6 6 6-6"/>',
        'close'   => '<path d="M6 6l12 12M18 6L6 18"/>',
        'minus'   => '<path d="M5 12h14"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'trash'   => '<path d="M4.5 6.5h15M9.5 6.5V4.8c0-.4.4-.8.8-.8h3.4c.4 0 .8.4.8.8v1.7M6.5 6.5l.8 12.2c.1.8.7 1.3 1.4 1.3h6.6c.7 0 1.3-.5 1.4-1.3l.8-12.2"/>',
        'arrow'   => '<path d="M4 12h15M14 6.5l5.5 5.5-5.5 5.5"/>',
        'up'      => '<path d="M12 19V5M6 11l6-6 6 6"/>',
        'comment' => '<path d="M5 4.5h14a1.5 1.5 0 0 1 1.5 1.5v9.5a1.5 1.5 0 0 1-1.5 1.5H10l-4.5 3.5V17H5a1.5 1.5 0 0 1-1.5-1.5V6A1.5 1.5 0 0 1 5 4.5z"/>',
        'wallet'  => '<rect x="3.5" y="6" width="17" height="13" rx="2.5"/><path d="M3.5 10h17M15.5 14.5h2"/>',
        'clock'   => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'check'   => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'share'   => '<path d="M12 15V3.5M8 7.5l4-4 4 4M6 11H5v9.5h14V11h-1"/>',
    ];
    return '<svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icons[$name] ?? '') . '</svg>';
}

function site_head(string $title, string $description = ''): void
{
    $name = (string) config('app_name', 'Колибри');
    ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description ?: $name . ' — студия цветов. Букеты и композиции с доставкой и самовывозом.') ?>">
    <meta name="theme-color" content="#ffffff">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <script>document.documentElement.classList.add('js');</script>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?= e($name) ?>">
    <meta property="og:title" content="<?= e($title) ?>">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="icon" href="admin/assets/img/kolibri-mark.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Unbounded:wght@700;800&display=swap">
    <link rel="stylesheet" href="<?= e(site_asset('site.css')) ?>">
    <?= setting('snippet_head', '') ?>
</head>
<body class="site">
    <?php
}

function site_asset(string $path): string
{
    $file = ROOT_DIR . '/assets/' . $path;
    return 'assets/' . $path . '?v=' . (is_file($file) ? filemtime($file) : 0);
}

/** Header. $catsNav: category chips shown in the sticky header on scroll. */
function site_header(array $categories = []): void
{
    ?>
<header class="hdr" data-hdr>
    <div class="hdr__main container">
        <div class="hdr__side">
            <div class="dd-wrap" data-dd>
                <button class="hbtn hbtn--icon" type="button" data-dd-toggle aria-label="Меню"><?= site_icon('grid') ?></button>
                <div class="dd">
                    <a class="dd__item" href="page.php?p=promotions">Акции</a>
                    <button class="dd__item" type="button" data-open="contacts">Контакты</button>
                    <a class="dd__item" href="page.php?p=privacy">Политика конфиденциальности</a>
                </div>
            </div>
            <button class="hbtn hbtn--icon" type="button" data-open="search" aria-label="Поиск"><?= site_icon('search') ?></button>
            <button class="hbtn hbtn--text hide-sm" type="button" data-open="contacts">Контакты</button>
        </div>
        <a class="logo" href="./" aria-label="<?= e(config('app_name', 'Колибри')) ?>">
            <?= bird_svg('logo__bird') ?>
            <span class="logo__text"><b>КОЛИБРИ</b><small>студия цветов</small></span>
        </a>
        <div class="hdr__side hdr__side--end">
            <button class="hbtn cart-btn" type="button" data-cart-open aria-label="Корзина"><?= site_icon('basket') ?><span data-cart-total>0 ₽</span></button>
        </div>
    </div>
    <?php if ($categories): ?>
        <div class="hdr__cats container" aria-hidden="true">
            <nav class="chips chips--hdr" data-chips-hdr>
                <?php foreach ($categories as $category): ?>
                    <a class="chip" href="#c-<?= (int) $category['id'] ?>" data-chip="<?= (int) $category['id'] ?>" tabindex="-1"><?= e($category['name']) ?></a>
                <?php endforeach; ?>
            </nav>
            <button class="hbtn cart-btn" type="button" data-cart-open tabindex="-1"><?= site_icon('basket') ?><span data-cart-total>0 ₽</span></button>
        </div>
    <?php endif; ?>
</header>
    <?php
}

function site_footer(): void
{
    $legal = array_filter([
        setting('legal_name', ''),
        setting('legal_inn', '') !== '' ? 'ИНН ' . setting('legal_inn') : '',
        setting('legal_ogrn', '') !== '' ? 'ОГРН ' . setting('legal_ogrn') : '',
    ]);
    ?>
<footer class="ftr container">
    <div class="ftr__brand">
        <?= bird_svg('ftr__bird') ?>
        <div>
            <b><?= e(config('app_name', 'Колибри')) ?></b> — студия цветов
            <?php if ($legal): ?><div class="ftr__legal"><?= e(implode(' · ', $legal)) ?></div><?php endif; ?>
        </div>
    </div>
    <nav class="ftr__links">
        <a href="page.php?p=promotions">Акции</a>
        <button type="button" data-open="contacts">Контакты</button>
        <a href="page.php?p=privacy">Политика конфиденциальности</a>
        <a href="page.php?p=terms">Пользовательское соглашение</a>
    </nav>
</footer>
    <?php
}

/** Contacts window (pickup points, phone, hours). */
function site_contacts_modal(array $points): void
{
    $schedule = schedule_value(setting('schedule'));
    $phone = (string) setting('contact_phone', '');
    $email = (string) setting('contact_email', '');
    ?>
<div class="smodal" data-smodal="contacts" aria-hidden="true">
    <div class="smodal__backdrop" data-close></div>
    <div class="smodal__dialog smodal__dialog--sm" role="dialog" aria-label="Контакты">
        <button class="round-btn smodal__close" type="button" data-close aria-label="Закрыть"><?= site_icon('close') ?></button>
        <h2 class="smodal__title">Контакты</h2>
        <?php if ($phone !== ''): ?>
            <a class="contact-row" href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= site_icon('phone') ?><span><?= e($phone) ?></span></a>
        <?php endif; ?>
        <?php if ($email !== ''): ?>
            <a class="contact-row" href="mailto:<?= e($email) ?>"><?= site_icon('comment') ?><span><?= e($email) ?></span></a>
        <?php endif; ?>
        <?php foreach ($points as $point): ?>
            <div class="contact-row"><?= site_icon('pin') ?><span><?= e($point['address']) ?><?php if ($point['hours'] !== ''): ?><small><?= e($point['hours']) ?></small><?php endif; ?></span></div>
        <?php endforeach; ?>
        <div class="hours">
            <div class="hours__title"><?= site_icon('clock') ?>Время работы</div>
            <?php foreach (WEEKDAYS_FULL as $n => $day):
                $d = $schedule[$n]; ?>
                <div class="hours__row<?= (int) date('N') === $n ? ' is-today' : '' ?>">
                    <span><?= e($day) ?></span>
                    <span><?= !$d['open'] ? 'выходной' : ($d['allday'] ? 'круглосуточно' : e($d['from'] . ' – ' . $d['to'])) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
    <?php
}
