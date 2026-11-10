<?php
/* Storefront data shared by the website (index.php) and the Telegram Mini App (tg/). */
defined('KOLIBRI') or exit;

/** Hero banners from "Акции и скидки" that are running now. */
function storefront_banners(): array
{
    $now = date('Y-m-d H:i:s');
    $banners = [];
    foreach (db()->query('SELECT * FROM promotions WHERE is_active = 1 ORDER BY sort, id DESC')->fetchAll() as $promo) {
        if ($promo['banner_desktop'] === '' && $promo['banner_mobile'] === '') {
            continue;
        }
        $options = promo_options(json_decode((string) $promo['options'], true) ?: []);
        $ended   = $promo['ends_at'] && $promo['ends_at'] < $now;
        $started = !$promo['starts_at'] || $promo['starts_at'] <= $now;
        if ($ended || ($options['active_only'] && !$started)) {
            continue;
        }
        $banners[] = $promo;
    }
    return $banners;
}

/** Products keyed by id, in the shape the storefront scripts use. */
function storefront_products(array $categories): array
{
    $products = [];
    foreach ($categories as $category) {
        foreach ($category['products'] as $product) {
            $extra = $product['extra_data'];
            $products[(int) $product['id']] = [
                'name'  => $product['name'],
                'unit'  => trim($product['unit_amount'] . ' ' . $product['unit_name']),
                'price' => (float) $product['price'],
                'old'   => $product['old_price'] !== null ? (float) $product['old_price'] : null,
                'image' => upload_url($product['image']),
                'desc'  => (string) $product['description'],
                'comp'  => $extra['composition']['on'] ? trim($extra['composition']['text']) : '',
                'size'  => $extra['composition']['on'] ? array_filter(['Высота' => $extra['composition']['height'], 'Ширина' => $extra['composition']['width']]) : [],
                'min'   => $extra['limits']['on'] ? max(1, (int) $extra['limits']['min']) : 1,
                'max'   => min(99, $extra['limits']['on'] && (int) $extra['limits']['max'] > 0 ? (int) $extra['limits']['max'] : 99, $extra['stock']['on'] ? (int) $extra['stock']['qty'] : 99),
            ];
        }
    }
    return $products;
}

/** Ordering rules for the storefront scripts. */
function storefront_config(string $source): array
{
    return [
        'currency'  => config('currency', '₽'),
        'source'    => $source,
        'types'     => delivery_types(),
        'points'    => array_map(static fn ($p) => ['id' => (int) $p['id'], 'address' => $p['address'], 'hours' => $p['hours'], 'lat' => $p['lat'], 'lng' => $p['lng']], pickup_points()),
        'payments'  => enabled_payment_methods(),
        'askChange' => setting('pay_change', '1') === '1',
        'asap'      => setting('asap_enabled', '1') === '1',
        'preorders' => setting('preorders_enabled', '1') === '1',
        'minMinutes' => (int) setting('preorder_min_minutes', '60'),
        'maxDays'   => (int) setting('preorder_max_days', '14'),
        'step'      => (int) setting('preorder_step', '30'),
        'readyIn'   => READY_IN_OPTIONS,
        'schedule'  => schedule_value(setting('schedule')),
        'openNow'   => schedule_allows(new DateTimeImmutable()),
        'now'       => date('Y-m-d\TH:i:s'), // the shop's clock; pre-order slots use it, not the device clock
        'active'    => setting('shop_active', '1') === '1',
        'city'      => (string) setting('delivery_city', 'Новокузнецк'),
        'center'    => (string) setting('map_center', '53.7557, 87.1099'),
        'deliveryInfo' => (string) setting('delivery_info', ''),
        'orderMessage' => (string) setting('order_message', '') ?: 'Вы получите уведомление, когда наш оператор его примет.',
    ];
}
