<?php
require __DIR__ . '/core/bootstrap.php';
require __DIR__ . '/core/icons.php';
require __DIR__ . '/core/menu.php';
require __DIR__ . '/core/layout.php';

require_admin();

// Pages that already have their own screen; the rest show a placeholder.
$views = [
    'dashboard'  => 'dashboard',
    'catalog'    => 'catalog',
    'clients'    => 'clients',
    'promotions' => 'promotions',
    'settings'   => 'settings',
];
// Page headings that differ from the menu label.
$titles = [
    'catalog' => 'Каталог',
    'clients' => 'Клиенты',
];

$page = is_string($_GET['p'] ?? null) ? $_GET['p'] : 'dashboard';
[$item] = menu_find($page);

if ($item === null) {
    http_response_code(404);
    render_page($page, 'Страница не найдена', 'not-found');
    exit;
}

$view = $views[$page] ?? 'placeholder';
if (is_file(__DIR__ . '/core/' . $view . '.php')) {
    require __DIR__ . '/core/' . $view . '.php';
}

render_page($page, $titles[$page] ?? $item['title'], $view);
