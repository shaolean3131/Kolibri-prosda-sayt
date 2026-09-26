<?php
defined('KOLIBRI') or exit;

/**
 * Sidebar menu. An item is either a link ('page') or a group ('children').
 * 'badge' => ['type' => 'blue'|'red'|'green', 'count' => callable returning int|string].
 */
function admin_menu(): array
{
    return [
        [
            'key'      => 'analytics',
            'title'    => 'Аналитика',
            'icon'     => 'analytics',
            'children' => [
                ['page' => 'dashboard',           'title' => 'Дашборд'],
                ['page' => 'analytics-products',  'title' => 'Товары'],
                ['page' => 'analytics-qr',        'title' => 'QR-меню'],
                ['page' => 'analytics-couriers',  'title' => 'Курьеры'],
                ['page' => 'analytics-timings',   'title' => 'Тайминги'],
                ['page' => 'analytics-promos',    'title' => 'Акции'],
                ['page' => 'analytics-campaigns', 'title' => 'Рекламные кампании'],
            ],
        ],
        [
            'key'      => 'catalog',
            'title'    => 'Каталог',
            'icon'     => 'catalog',
            'children' => [
                ['page' => 'stop-lists',           'title' => 'Стоп-листы'],
                ['page' => 'catalog',              'title' => 'Основное'],
                ['page' => 'qr-menu',              'title' => 'QR-меню'],
                ['page' => 'cart-addons',          'title' => 'Допы в корзине'],
                ['page' => 'cart-recommendations', 'title' => 'Smart рекомендации в корзине'],
            ],
        ],
        ['page' => 'clients',    'title' => 'Ваши клиенты',         'icon' => 'clients',   'badge' => ['type' => 'blue', 'count' => 'badge_new_clients']],
        ['page' => 'push',       'title' => 'Пуш-рассылка',         'icon' => 'push'],
        ['page' => 'orders',     'title' => 'Заказы',               'icon' => 'orders',    'badge' => ['type' => 'blue', 'count' => 'badge_new_orders']],
        ['page' => 'promotions', 'title' => 'Акции и скидки',       'icon' => 'percent'],
        ['page' => 'reviews',    'title' => 'Отзывы',               'icon' => 'reviews'],
        ['page' => 'app-site',   'title' => 'Приложение и сайт',    'icon' => 'app'],
        ['page' => 'loyalty',    'title' => 'Программа лояльности', 'icon' => 'loyalty'],
        ['page' => 'marketing',  'title' => 'Маркетинг',            'icon' => 'marketing'],
        ['page' => 'settings',   'title' => 'Настройки',            'icon' => 'settings'],
        ['page' => 'api',        'title' => 'API',                  'icon' => 'api'],
    ];
}

/** Finds the menu entry for a page slug; returns [item, parentGroup|null]. */
function menu_find(string $page): array
{
    foreach (admin_menu() as $item) {
        if (($item['page'] ?? null) === $page) {
            return [$item, null];
        }
        foreach ($item['children'] ?? [] as $child) {
            if ($child['page'] === $page) {
                return [$child, $item];
            }
        }
    }
    return [null, null];
}

function badge_new_orders(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM orders WHERE status = 'new'")->fetchColumn();
}

function badge_new_clients(): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM clients WHERE created_at >= ?');
    $stmt->execute([date('Y-m-d H:i:s', strtotime('-7 days'))]);
    return (int) $stmt->fetchColumn();
}

function render_badge(?array $badge): string
{
    if ($badge === null) {
        return '';
    }
    $count = ($badge['count'])();
    if ($count === 0 || $count === '') {
        return '';
    }
    return '<span class="badge badge--' . e($badge['type']) . '">' . e($count) . '</span>';
}
