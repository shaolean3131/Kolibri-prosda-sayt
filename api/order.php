<?php
/* Places an order from the storefront and notifies the shop in Telegram. */
define('KOLIBRI_SITE', true);
require dirname(__DIR__) . '/admin/core/bootstrap.php';
require_once dirname(__DIR__) . '/admin/core/orders.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    json_response(['error' => 'Сессия устарела. Обновите страницу и попробуйте снова.'], 419);
}

$input = $_POST;
$items = json_decode(is_string($_POST['items'] ?? null) ? $_POST['items'] : '[]', true);
$input['items'] = is_array($items) ? array_slice($items, 0, 100) : [];
if (($input['when'] ?? '') === 'later') {
    $input['scheduled_at'] = trim((string) ($_POST['date'] ?? '')) . ' ' . trim((string) ($_POST['time'] ?? ''));
}

try {
    $id = place_order($input, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
} catch (UserError $e) {
    json_response(['error' => $e->getMessage()], 422);
}

$order = order_with_items($id);
$body = json_encode([
    'ok'      => true,
    'id'      => $id,
    'message' => (string) setting('order_message', '') ?: 'Вы получите уведомление, когда наш оператор его примет.',
    'place'   => $order['delivery_type'] === 'pickup' ? (string) $order['pickup_address'] : order_place_label($order),
    'time'    => order_time_label($order),
    'total'   => price_label((float) $order['total']),
], JSON_UNESCAPED_UNICODE);

// answer the customer first, then talk to Telegram
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Content-Length: ' . strlen($body));
header('Connection: close');
echo $body;
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    @ob_end_flush();
    flush();
}
ignore_user_abort(true);
notify_new_order($id);
