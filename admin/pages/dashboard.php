<?php
defined('KOLIBRI') or exit;

$periods = ['day' => 'Сегодня', 'week' => 'Неделя', 'month' => 'Месяц', 'year' => 'Год'];

$periodSelect = static function (string $class = '') use ($periods): string {
    $items = '';
    foreach ($periods as $value => $label) {
        $active = $value === 'week' ? ' is-active' : '';
        $items .= '<button class="dropdown-item' . $active . '" type="button" data-value="' . $value . '">'
            . e($label) . icon('check', 'icon icon--sm dropdown-item__check') . '</button>';
    }
    return '<div class="dropdown ' . $class . '" data-dropdown data-period-select>'
        . '<button class="select-btn" type="button" data-dropdown-toggle><span data-period-label>Неделя</span>' . icon('chevron', 'icon select-btn__chevron') . '</button>'
        . '<div class="dropdown-menu dropdown-menu--right">' . $items . '</div></div>';
};

$cards = [
    ['metric' => 'revenue_orders', 'title' => 'Выручка и заказы', 'wide' => true,
     'hint' => 'Выручка (линия) и количество заказов (столбцы) без учёта отменённых заказов.'],
    ['metric' => 'revenue',     'title' => 'Выручка'],
    ['metric' => 'orders',      'title' => 'Заказы'],
    ['metric' => 'avg_check',   'title' => 'Средний чек'],
    ['metric' => 'new_clients', 'title' => 'Новые клиенты'],
];
?>
<div class="page-head reveal">
    <h1 class="page-title"><?= e($title) ?></h1>
    <?= $periodSelect('page-period select--lg') ?>
</div>

<div class="dash-grid" data-currency="<?= e(config('currency', '₽')) ?>">
    <?php foreach ($cards as $i => $card): ?>
        <section class="card chart-card<?= !empty($card['wide']) ? ' chart-card--wide' : '' ?> reveal" style="--i: <?= $i + 1 ?>" data-chart="<?= e($card['metric']) ?>">
            <header class="card__head">
                <h2 class="card__title">
                    <?= e($card['title']) ?>
                    <?php if (!empty($card['hint'])): ?>
                        <span class="hint" tabindex="0" data-tip="<?= e($card['hint']) ?>">?</span>
                    <?php endif; ?>
                </h2>
                <div class="card__tools">
                    <?= $periodSelect() ?>
                    <div class="dropdown" data-dropdown>
                        <button class="square-btn" type="button" data-dropdown-toggle aria-label="Действия"><?= icon('dots') ?></button>
                        <div class="dropdown-menu dropdown-menu--right">
                            <button class="dropdown-item" type="button" data-chart-action="refresh"><?= icon('refresh', 'icon icon--sm') ?>Обновить</button>
                            <button class="dropdown-item" type="button" data-chart-action="csv"><?= icon('download', 'icon icon--sm') ?>Скачать CSV</button>
                        </div>
                    </div>
                </div>
            </header>
            <div class="chart-card__summary" data-chart-summary></div>
            <div class="chart-card__body" data-chart-body>
                <div class="skeleton"></div>
            </div>
        </section>
    <?php endforeach; ?>
</div>
