<?php
require dirname(__DIR__) . '/core/bootstrap.php';
require_once dirname(__DIR__) . '/core/orders.php';

$action = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';

// read-only GET actions
if ($action === 'poll') {
    require_admin(api: true);
    $after = max(0, (int) ($_GET['after'] ?? 0));
    $stmt = db()->prepare('SELECT id, client_name, total FROM orders WHERE id > ? ORDER BY id');
    $stmt->execute([$after]);
    $orders = array_map(static fn ($o) => ['id' => (int) $o['id'], 'name' => $o['client_name'], 'total' => price_label((float) $o['total'])], $stmt->fetchAll());
    json_response([
        'new'    => (int) db()->query("SELECT COUNT(*) FROM orders WHERE status = 'new'")->fetchColumn(),
        'orders' => $orders,
    ]);
}

if ($action === 'export') {
    require_admin();
    [$sql, $params] = orders_query(filter_values(order_filters(), $_GET));
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="orders-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Номер', 'Дата', 'Клиент', 'Телефон', 'Получение', 'Время', 'Товары', 'Скидка', 'Доставка', 'Итого', 'Оплата', 'Промокод', 'Статус', 'Причина отмены', 'Комментарий', 'Источник'], ';', '"', '');
    $items = db()->prepare('SELECT name, quantity FROM order_items WHERE order_id = ?');
    foreach ($stmt as $o) {
        $items->execute([$o['id']]);
        fputcsv($out, [
            $o['id'],
            date('d.m.Y H:i', strtotime($o['created_at'])),
            $o['client_name'],
            $o['phone'],
            order_place_label($o),
            order_time_label($o),
            implode('; ', array_map(static fn ($i) => $i['name'] . ' × ' . $i['quantity'], $items->fetchAll())),
            $o['discount'],
            $o['delivery_price'],
            $o['total'],
            PAYMENT_METHODS[$o['payment_method']] ?? $o['payment_method'],
            $o['promo_code'],
            ORDER_STATUSES[$o['status']]['label'] ?? $o['status'],
            $o['cancel_reason'],
            $o['comment'],
            $o['source'] === 'app' ? 'Приложение' : 'Сайт',
        ], ';', '"', '');
    }
    exit;
}

api_guard();

$id = input_int('id');
$order = $id > 0 ? order_with_items($id) : null;
if ($order === null) {
    json_response(['error' => 'Заказ не найден'], 404);
}

switch (input_string('action', 20)) {
    case 'get':
        json_response(['order' => order_payload($order)]);

    case 'phone':
        json_response(['phone' => $order['phone']]);

    case 'status':
        $status = input_string('status', 20);
        if (!isset(ORDER_STATUSES[$status])) {
            json_response(['error' => 'Неизвестный статус'], 422);
        }
        $reason = $status === 'cancelled' ? input_string('reason', 120) : '';
        db()->prepare('UPDATE orders SET status = ?, cancel_reason = ?, updated_at = ? WHERE id = ?')
            ->execute([$status, $reason, date('Y-m-d H:i:s'), $id]);
        $order['status'] = $status;
        $order['cancel_reason'] = $reason;
        json_response(['ok' => true, 'order' => order_payload($order)]);
}

json_response(['error' => 'unknown action'], 400);
