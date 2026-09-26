<?php
/* Prices the cart on the server (promo codes, delivery, limits). */
define('KOLIBRI_SITE', true);
require dirname(__DIR__) . '/admin/core/bootstrap.php';
require_once dirname(__DIR__) . '/admin/core/orders.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    json_response(['error' => 'Обновите страницу и попробуйте снова.'], 419);
}

$items = json_decode(is_string($_POST['items'] ?? null) ? $_POST['items'] : '[]', true);
$priced = price_cart(is_array($items) ? array_slice($items, 0, 100) : [], [
    'delivery_type' => input_string('delivery_type', 10),
    'promo_code'    => input_string('promo_code', 40),
    'phone'         => input_string('phone', 40),
    'source'        => input_string('source', 10),
]);

json_response([
    'lines' => array_map(static fn ($l) => [
        'id'      => $l['product_id'],
        'qty'     => $l['quantity'],
        'gift'    => $l['is_gift'],
        'name'    => $l['name'],
        'unit'    => $l['unit'],
        'image'   => $l['image'],
        'total'   => price_label($l['total']),
    ], $priced['lines']),
    'count'    => $priced['count'],
    'subtotal' => price_label($priced['subtotal']),
    'discount' => $priced['discount'] > 0 ? '−' . price_label($priced['discount']) : '',
    'delivery' => input_string('delivery_type', 10) === 'delivery' ? ($priced['delivery_price'] > 0 ? price_label($priced['delivery_price']) : 'бесплатно') : '',
    'total'    => price_label($priced['total']),
    'totalRaw' => $priced['total'],
    'promo'    => $priced['promo'],
    'promoError' => $priced['promo_error'],
    'errors'   => $priced['errors'],
]);
