<?php
defined('KOLIBRI') or exit;

/** Renders a page view inside the admin shell (top bar + sidebar). */
function render_page(string $page, string $title, string $view, array $vars = []): void
{
    $admin = current_admin();
    [, $activeGroup] = menu_find($page);

    $content = (function (string $__view, array $__vars): string {
        extract($__vars);
        ob_start();
        require ADMIN_DIR . '/pages/' . $__view . '.php';
        return (string) ob_get_clean();
    })($view, $vars + ['page' => $page, 'title' => $title]);

    $stmt = db()->prepare("SELECT id, total, created_at FROM orders WHERE status = 'new' ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $notifications = $stmt->fetchAll();
    ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title) ?> — <?= e(config('app_name')) ?></title>
    <link rel="icon" href="<?= e(asset('img/kolibri-mark.svg')) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body>
<header class="topbar">
    <button class="icon-btn topbar__burger" type="button" data-sidebar-open aria-label="Меню"><?= icon('burger') ?></button>
    <a class="wordmark" href="index.php" aria-label="<?= e(config('app_name')) ?>">
        <span class="wordmark__name">КОЛИБРИ</span>
        <span class="wordmark__sub">СТУДИЯ&nbsp;ЦВЕТОВ</span>
    </a>
    <div class="topbar__actions">
        <div class="dropdown" data-dropdown>
            <button class="icon-btn bell" type="button" data-dropdown-toggle aria-label="Уведомления">
                <?= icon('bell') ?>
                <?php if ($notifications): ?><span class="bell__dot"></span><?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu--right notifications">
                <div class="dropdown-menu__title">Уведомления</div>
                <?php if (!$notifications): ?>
                    <div class="notifications__empty">Нет новых уведомлений</div>
                <?php endif; ?>
                <?php foreach ($notifications as $n): ?>
                    <a class="notification" href="index.php?p=orders">
                        <span class="notification__icon"><?= icon('box') ?></span>
                        <span class="notification__body">
                            <strong>Новый заказ #<?= (int) $n['id'] ?> · <?= e(money((float) $n['total'])) ?></strong>
                            <small><?= e(time_ago($n['created_at'])) ?></small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="dropdown" data-dropdown>
            <button class="avatar-btn" type="button" data-dropdown-toggle aria-label="Профиль"><?= icon('user') ?></button>
            <div class="dropdown-menu dropdown-menu--right">
                <div class="profile">
                    <strong><?= e($admin['name']) ?></strong>
                    <small><?= e($admin['email']) ?></small>
                </div>
                <a class="dropdown-item" href="index.php?p=settings"><?= icon('settings', 'icon icon--sm') ?>Настройки</a>
                <form method="post" action="logout.php">
                    <?= csrf_field() ?>
                    <button class="dropdown-item dropdown-item--danger" type="submit"><?= icon('logout', 'icon icon--sm') ?>Выйти</button>
                </form>
            </div>
        </div>
    </div>
</header>

<div class="shell">
    <aside class="sidebar" data-sidebar>
        <div class="sidebar__inner">
            <div class="sidebar__head">
                <div class="dropdown store" data-dropdown>
                    <button class="store__btn" type="button" data-dropdown-toggle>
                        <img class="store__logo" src="<?= e(asset('img/kolibri-mark.svg')) ?>" alt="" width="44" height="44">
                        <span class="store__name"><?= e(config('app_name')) ?></span>
                        <?= icon('chevron', 'icon store__chevron') ?>
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="<?= e(config('site_url')) ?>" target="_blank" rel="noopener"><?= icon('external', 'icon icon--sm') ?>Открыть сайт</a>
                    </div>
                </div>
                <button class="icon-btn sidebar__close" type="button" data-sidebar-close aria-label="Закрыть меню"><?= icon('close') ?></button>
            </div>

            <nav class="nav">
                <?php foreach (admin_menu() as $item): ?>
                    <?php if (isset($item['children'])):
                        $isActive = $activeGroup !== null && $activeGroup['key'] === $item['key']; ?>
                        <div class="nav-group<?= $isActive ? ' is-open is-active' : '' ?>" data-nav-group>
                            <button class="nav-link" type="button" data-nav-toggle aria-expanded="<?= $isActive ? 'true' : 'false' ?>">
                                <?= icon($item['icon'], 'icon nav-link__icon') ?>
                                <span class="nav-link__label"><?= e($item['title']) ?></span>
                            </button>
                            <div class="nav-sub">
                                <div class="nav-sub__inner">
                                    <?php foreach ($item['children'] as $child): ?>
                                        <a class="nav-sublink<?= $child['page'] === $page ? ' is-active' : '' ?>" href="index.php?p=<?= e($child['page']) ?>">
                                            <?= e($child['title']) ?><?= render_badge($child['badge'] ?? null) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <a class="nav-link<?= $item['page'] === $page ? ' is-active' : '' ?>" href="index.php?p=<?= e($item['page']) ?>">
                            <?= icon($item['icon'], 'icon nav-link__icon') ?>
                            <span class="nav-link__label"><?= e($item['title']) ?></span>
                            <?= render_badge($item['badge'] ?? null) ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        </div>
    </aside>

    <main class="content" id="content">
        <?= $content ?>
    </main>
</div>
<div class="backdrop" data-sidebar-close></div>

<script src="<?= e(asset('js/charts.js')) ?>" defer></script>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
    <?php
}
