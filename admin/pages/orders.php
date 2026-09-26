<?php
defined('KOLIBRI') or exit;

$filters = order_filters();
$values  = filter_values($filters, $_GET);
$perPage = in_array((int) ($_GET['per_page'] ?? 100), ORDER_PER_PAGE, true) ? (int) ($_GET['per_page'] ?? 100) : 100;

[$sql, $params] = orders_query($values);
$count = db()->prepare("SELECT COUNT(*) FROM ({$sql}) t");
$count->execute($params);
$total   = (int) $count->fetchColumn();
$pages   = max(1, (int) ceil($total / $perPage));
$current = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$stmt = db()->prepare($sql . ' LIMIT ' . $perPage . ' OFFSET ' . (($current - 1) * $perPage));
$stmt->execute($params);
$orders = $stmt->fetchAll();
$newCount = badge_new_orders();
$openId = (int) ($_GET['order'] ?? 0);

$query = static fn (array $change): string => 'index.php?' . http_build_query(array_merge(['p' => 'orders'], $values, ['per_page' => $perPage], $change));
$exportUrl = 'api/orders.php?' . http_build_query(array_merge(['action' => 'export'], $values));
?>
<div class="page-head reveal">
    <h1 class="page-title">
        <?= e($title) ?><?php if ($newCount): ?><sup class="title-count" title="Новых заказов"><?= $newCount ?></sup><?php endif; ?>
    </h1>
</div>

<?= render_filters($filters, $values, ['p' => 'orders', 'per_page' => $perPage], $exportUrl, 'index.php?p=orders') ?>

<div class="table-meta reveal" style="--i: 2">
    <span class="table-meta__count"><?= $total ?> <?= plural($total, 'заказ', 'заказа', 'заказов') ?></span>
    <div class="dropdown" data-dropdown>
        <button class="link-dashed" type="button" data-dropdown-toggle>Отображать по <?= $perPage ?></button>
        <div class="dropdown-menu dropdown-menu--right">
            <?php foreach (ORDER_PER_PAGE as $n): ?>
                <a class="dropdown-item<?= $n === $perPage ? ' is-active' : '' ?>" href="<?= e($query(['per_page' => $n, 'page' => 1])) ?>">По <?= $n ?><?= icon('check', 'icon icon--sm dropdown-item__check') ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="table-wrap reveal" style="--i: 3">
    <table class="table table--orders">
        <thead>
            <tr>
                <th class="c">#</th>
                <th>Дата</th>
                <th>Клиент</th>
                <th>Телефон</th>
                <th class="table__name">Заказ</th>
                <th>Итого</th>
                <th>Статус</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$orders): ?>
                <tr><td class="table__empty" colspan="7"><?= $values ? 'Заказы не найдены' : 'Заказов пока нет. Они появятся здесь, как только клиент оформит заказ на сайте.' ?></td></tr>
            <?php endif; ?>
            <?php foreach ($orders as $n => $order):
                $status = ORDER_STATUSES[$order['status']] ?? ['label' => $order['status'], 'color' => 'blue']; ?>
                <tr class="table__row<?= $order['status'] === 'new' ? ' is-new' : '' ?>" style="--row: <?= min($n, 20) ?>" data-order="<?= (int) $order['id'] ?>" tabindex="0">
                    <td class="c muted"><?= ($current - 1) * $perPage + $n + 1 ?></td>
                    <td><?= e(date('d.m.Y H:i', strtotime($order['created_at']))) ?></td>
                    <td><a class="table__link" href="index.php?p=clients&amp;phone=<?= e(urlencode(preg_replace('/\D+/', '', $order['phone']))) ?>" data-stop><?= e($order['client_name']) ?></a></td>
                    <td><button class="phone" type="button" data-order-phone="<?= (int) $order['id'] ?>">скрыт</button></td>
                    <td class="table__name">
                        <div class="order-place"><?= e($order['delivery_type'] === 'pickup' ? 'Самовывоз' : order_place_label($order)) ?></div>
                        <small class="muted">#<?= (int) $order['id'] ?><?= $order['scheduled_at'] ? ' · предзаказ на ' . e(date('d.m H:i', strtotime($order['scheduled_at']))) : '' ?></small>
                    </td>
                    <td><?= e(number_format((float) $order['total'], 0, ',', '')) ?></td>
                    <td><span class="status status--<?= e($status['color']) ?>" data-status-cell><?= e($status['label']) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
    <nav class="pager reveal" style="--i: 4">
        <?php for ($n = 1; $n <= $pages; $n++):
            if ($n !== 1 && $n !== $pages && abs($n - $current) > 2) {
                if ($n === 2 || $n === $pages - 1) {
                    echo '<span class="pager__gap">…</span>';
                }
                continue;
            } ?>
            <a class="pager__item<?= $n === $current ? ' is-active' : '' ?>" href="<?= e($query(['page' => $n])) ?>"><?= $n ?></a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

<!-- order window -->
<div class="modal modal--order" data-modal="order" aria-hidden="true" data-open-order="<?= $openId ?>"
     data-statuses="<?= e(json_encode(ORDER_STATUSES, JSON_UNESCAPED_UNICODE)) ?>"
     data-reasons="<?= e(json_encode(CANCEL_REASONS, JSON_UNESCAPED_UNICODE)) ?>">
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog">
        <header class="modal__head">
            <div>
                <h2 class="modal__title" data-o="title">Заказ</h2>
                <div class="order-sub" data-o="sub"></div>
            </div>
            <button class="tool" type="button" data-modal-close aria-label="Закрыть"><?= icon('close') ?></button>
        </header>
        <div class="modal__body" data-order-body>
            <div class="skeleton skeleton--order"></div>
        </div>
    </div>
</div>
