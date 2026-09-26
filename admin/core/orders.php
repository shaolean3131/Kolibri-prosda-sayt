<?php
defined('KOLIBRI') or exit;

require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/filters.php';
require_once __DIR__ . '/promotions.php';
require_once __DIR__ . '/settings.php';

const ORDER_STATUSES = [
    'new'        => ['label' => 'Новый',     'color' => 'blue'],
    'accepted'   => ['label' => 'Принят',    'color' => 'orange'],
    'ready'      => ['label' => 'Готов',     'color' => 'purple'],
    'delivering' => ['label' => 'В пути',    'color' => 'purple'],
    'done'       => ['label' => 'Доставлен', 'color' => 'green'],
    'cancelled'  => ['label' => 'Отмена',    'color' => 'red'],
];

const PAYMENT_METHODS = [
    'cash'     => 'Наличными',
    'card'     => 'Картой при получении',
    'transfer' => 'Переводом (СБП)',
];

const CANCEL_REASONS = ['Клиент отказался', 'Нет в наличии', 'Не удалось связаться', 'Дубль заказа', 'Другое'];

const READY_IN_OPTIONS = [20, 30, 45, 60, 90, 120];

/* ---------- formatting ---------- */

/** "2500 ₽" — the storefront style (no thousands separator). */
function price_label(float $amount): string
{
    return number_format($amount, fmod($amount, 1.0) === 0.0 ? 0 : 2, ',', '') . ' ' . config('currency', '₽');
}

/** Keeps digits only and returns "+7 900 000-00-00", or '' when invalid. */
function normalize_phone(string $raw): string
{
    $digits = preg_replace('/\D+/', '', $raw);
    if (strlen($digits) === 10) {
        $digits = '7' . $digits;
    }
    if (strlen($digits) !== 11 || !in_array($digits[0], ['7', '8'], true)) {
        return '';
    }
    $d = '7' . substr($digits, 1);
    return sprintf('+%s %s %s-%s-%s', $d[0], substr($d, 1, 3), substr($d, 4, 3), substr($d, 7, 2), substr($d, 9, 2));
}

/* ---------- store settings ---------- */

function enabled_payment_methods(): array
{
    $defaults = ['cash' => '1', 'card' => '1', 'transfer' => '0'];
    return array_filter(PAYMENT_METHODS, static fn ($key) => setting('pay_' . $key, $defaults[$key]) === '1', ARRAY_FILTER_USE_KEY);
}

function delivery_types(): array
{
    $types = [];
    if (setting('delivery_enabled', '1') === '1') {
        $types['delivery'] = 'Доставка';
    }
    if (setting('pickup_enabled', '1') === '1') {
        $types['pickup'] = 'Самовывоз';
    }
    return $types;
}

function pickup_points(bool $activeOnly = true): array
{
    return db()->query('SELECT * FROM pickup_points' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort, id')->fetchAll();
}

/** Is the shop open at $time according to the weekly schedule? */
function schedule_allows(DateTimeImmutable $time): bool
{
    $schedule = schedule_value(setting('schedule'));
    $day = $schedule[(int) $time->format('N')];
    if (!$day['open']) {
        return false;
    }
    if ($day['allday']) {
        return true;
    }
    $now = $time->format('H:i');
    return $day['from'] <= $day['to']
        ? $now >= $day['from'] && $now < $day['to']
        : $now >= $day['from'] || $now < $day['to']; // past midnight, e.g. 20:00–02:00
}

/* ---------- catalog for the storefront ---------- */

/** Can the product be ordered right now from the given source ("site" or "app")? */
function product_available(array $product, array $extra, DateTimeImmutable $now, string $source): bool
{
    if (!$product['is_active'] || $extra['service']['on']) {
        return false;
    }
    if ($extra['stock']['on'] && $extra['stock']['qty'] <= 0) {
        return false;
    }
    $display = $extra['display'];
    if ($display['on']) {
        if (!in_array((int) $now->format('N'), $display['days'], true)) {
            return false;
        }
        $time = $now->format('H:i');
        if ($display['from'] !== '' && $time < $display['from']) {
            return false;
        }
        if ($display['to'] !== '' && $time > $display['to']) {
            return false;
        }
        if (!$display[$source === 'app' ? 'app' : 'site']) {
            return false;
        }
    }
    return true;
}

/** Active categories with the products a customer may order now. */
function storefront_catalog(string $source = 'site'): array
{
    $now = new DateTimeImmutable();
    $categories = [];
    foreach (catalog_tree() as $category) {
        if (!$category['is_active']) {
            continue;
        }
        $products = [];
        foreach ($category['products'] as $product) {
            $extra = product_extra($product['extra']);
            if (product_available($product, $extra, $now, $source)) {
                $product['extra_data'] = $extra;
                $products[] = $product;
            }
        }
        if ($products) {
            $category['products'] = $products;
            $categories[] = $category;
        }
    }
    return $categories;
}

/* ---------- pricing ---------- */

/**
 * Prices a cart from the server-side catalog. $items: [[id, qty], ...].
 * $context: delivery_type, promo_code, phone, source.
 */
function price_cart(array $items, array $context): array
{
    $source = ($context['source'] ?? 'site') === 'app' ? 'app' : 'site';
    $available = [];
    foreach (storefront_catalog($source) as $category) {
        foreach ($category['products'] as $product) {
            $available[(int) $product['id']] = $product;
        }
    }

    $lines = [];
    $errors = [];
    foreach ($items as $item) {
        $id  = (int) ($item['id'] ?? 0);
        $qty = (int) ($item['qty'] ?? 0);
        if ($qty <= 0) {
            continue;
        }
        if (!isset($available[$id])) {
            $errors[] = 'Некоторые товары закончились и были убраны из корзины.';
            continue;
        }
        $product = $available[$id];
        $extra   = $product['extra_data'];
        if ($extra['limits']['on']) {
            $qty = max($qty, max(1, (int) $extra['limits']['min']));
            if ((int) $extra['limits']['max'] > 0) {
                $qty = min($qty, (int) $extra['limits']['max']);
            }
        }
        if ($extra['stock']['on']) {
            $qty = min($qty, (int) $extra['stock']['qty']);
        }
        $qty   = min($qty, 99);
        $price = (float) $product['price'];
        $lines[$id] = [
            'product_id'  => $id,
            'category_id' => (int) $product['category_id'],
            'name'        => $product['name'],
            'unit'        => trim($product['unit_amount'] . ' ' . $product['unit_name']),
            'image'       => upload_url($product['image']),
            'price'       => $price,
            'old_price'   => $product['old_price'] !== null ? (float) $product['old_price'] : null,
            'quantity'    => $qty,
            'total'       => round($price * $qty, 2),
            'is_gift'     => false,
        ];
    }
    $lines = array_values($lines);

    $subtotal = array_sum(array_column($lines, 'total'));
    $type     = ($context['delivery_type'] ?? '') === 'delivery' ? 'delivery' : 'pickup';
    $cart     = ['lines' => $lines, 'subtotal' => $subtotal];

    [$promo, $promoError] = find_promotion($cart, array_merge($context, ['delivery_type' => $type, 'source' => $source]));
    $discount = 0.0;
    $freeDelivery = false;
    $gift = null;
    if ($promo) {
        [$discount, $gift, $freeDelivery] = promotion_effect($promo, $lines);
    }

    $deliveryPrice = 0.0;
    if ($type === 'delivery') {
        $deliveryPrice = (float) setting('delivery_price', '0');
        $freeFrom = (float) setting('delivery_free_from', '0');
        if ($freeDelivery || ($freeFrom > 0 && $subtotal - $discount >= $freeFrom)) {
            $deliveryPrice = 0.0;
        }
        $minOrder = (float) setting('delivery_min_order', '0');
        if ($lines && $minOrder > 0 && $subtotal < $minOrder) {
            $errors[] = 'Минимальная сумма заказа на доставку — ' . price_label($minOrder) . '.';
        }
    }

    if ($gift) {
        $lines[] = $gift;
    }

    return [
        'lines'          => $lines,
        'count'          => array_sum(array_map(static fn ($l) => $l['is_gift'] ? 0 : $l['quantity'], $lines)),
        'subtotal'       => $subtotal,
        'discount'       => $discount,
        'delivery_price' => $deliveryPrice,
        'total'          => max(0, round($subtotal - $discount + $deliveryPrice, 2)),
        'promo'          => $promo ? ['id' => (int) $promo['id'], 'name' => $promo['name'], 'code' => $promo['_code']] : null,
        'promo_error'    => $promoError,
        'errors'         => array_values(array_unique($errors)),
    ];
}

/**
 * The promotion to apply: the one matching the entered code, otherwise the
 * best automatic one. Returns [promotion|null, error message].
 */
function find_promotion(array $cart, array $context): array
{
    $code = mb_strtoupper(trim((string) ($context['promo_code'] ?? '')));
    $now  = date('Y-m-d H:i:s');
    $stmt = db()->prepare("SELECT * FROM promotions WHERE is_active = 1 AND type <> 'banner'
        AND (starts_at IS NULL OR starts_at <= ?) AND (ends_at IS NULL OR ends_at >= ?) ORDER BY sort, id DESC");
    $stmt->execute([$now, $now]);
    $promos = $stmt->fetchAll();

    if ($code !== '') {
        foreach ($promos as $promo) {
            $codes = json_decode((string) $promo['promo_codes'], true) ?: [];
            if (in_array($code, $codes, true)) {
                $error = promotion_error($promo, $cart, $context);
                $promo['_code'] = $code;
                return $error === null ? [$promo, ''] : [null, $error];
            }
        }
        return [null, 'Промокод не найден или больше не действует.'];
    }

    $best = null;
    $bestValue = 0.0;
    foreach ($promos as $promo) {
        $options = promo_options(json_decode((string) $promo['options'], true) ?: []);
        if (!$options['auto'] || promotion_error($promo, $cart, $context) !== null) {
            continue;
        }
        [$value, $gift, $free] = promotion_effect($promo, $cart['lines']);
        $score = $value + ($gift ? 1 : 0) + ($free ? 1 : 0);
        if ($score > $bestValue) {
            $best = $promo + ['_code' => ''];
            $bestValue = $score;
        }
    }
    return [$best, ''];
}

/** Why a promotion can't be used for this cart (null when it can). */
function promotion_error(array $promo, array $cart, array $context): ?string
{
    $options = promo_options(json_decode((string) $promo['options'], true) ?: []);
    $eligible = array_sum(array_column(promotion_lines($promo, $options, $cart['lines']), 'total'));

    if ((float) $promo['min_order'] > 0 && $eligible < (float) $promo['min_order']) {
        return 'Промокод действует при заказе от ' . price_label((float) $promo['min_order']) . '.';
    }
    if ($options['pickup_only'] && $context['delivery_type'] !== 'pickup') {
        return 'Промокод действует только на самовывоз.';
    }
    if ($options['delivery_only'] && $context['delivery_type'] !== 'delivery') {
        return 'Промокод действует только на доставку.';
    }
    if ($options['site_only'] && $context['source'] !== 'site') {
        return 'Промокод действует только на сайте.';
    }
    if ($options['app_only'] && $context['source'] !== 'app') {
        return 'Промокод действует только в приложении.';
    }

    $phone = normalize_phone((string) ($context['phone'] ?? ''));
    if ($phone !== '') {
        $count = static function (string $where, array $params) use ($phone): int {
            $stmt = db()->prepare("SELECT COUNT(*) FROM orders WHERE phone = ? AND status <> 'cancelled' AND {$where}");
            $stmt->execute(array_merge([$phone], $params));
            return (int) $stmt->fetchColumn();
        };
        if ($options['nth_order']) {
            $nth = (int) $options['nth_order_value'];
            if ($count('1 = 1', []) + 1 !== $nth) {
                return 'Промокод действует только на ' . ['1' => 'первый', '2' => 'второй', '3' => 'третий'][(string) $nth] . ' заказ.';
            }
        }
        if ($options['once_each'] && $count('promotion_id = ?', [$promo['id']]) > 0) {
            return 'Вы уже воспользовались этой акцией.';
        }
        if ($options['once_per']) {
            $period = ['day' => '-1 day', 'week' => '-7 days', 'month' => '-1 month'][$options['once_per_value']];
            if ($count('promotion_id = ? AND created_at >= ?', [$promo['id'], date('Y-m-d H:i:s', strtotime($period))]) > 0) {
                return 'Эту акцию можно использовать один раз в ' . promo_option_list()['once_per']['select'][$options['once_per_value']] . '.';
            }
        }
    }
    return null;
}

/** Cart lines the promotion applies to. */
function promotion_lines(array $promo, array $options, array $lines): array
{
    $categories = array_map('intval', array_filter(explode(',', $promo['category_ids'])));
    return array_values(array_filter($lines, static function (array $line) use ($promo, $options, $categories): bool {
        if ($line['is_gift']) {
            return false;
        }
        if ($promo['applies_to'] === 'categories' && !in_array($line['category_id'], $categories, true)) {
            return false;
        }
        return !($options['no_crossed'] && $line['old_price'] !== null);
    }));
}

/** [discount amount, gift line|null, free delivery] */
function promotion_effect(array $promo, array $lines): array
{
    $options = promo_options(json_decode((string) $promo['options'], true) ?: []);
    $base = array_sum(array_column(promotion_lines($promo, $options, $lines), 'total'));
    $value = (float) $promo['value'];

    $discount = match ($promo['type']) {
        'percent' => round($base * min(100, $value) / 100),
        'fixed'   => min($value, $base),
        default   => 0.0,
    };

    $gift = null;
    if ($promo['type'] === 'gift' && $options['gift_product_id'] > 0) {
        $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$options['gift_product_id']]);
        if ($product = $stmt->fetch()) {
            $gift = [
                'product_id'  => (int) $product['id'],
                'category_id' => (int) $product['category_id'],
                'name'        => $product['name'],
                'unit'        => 'подарок',
                'image'       => upload_url($product['image']),
                'price'       => 0.0,
                'old_price'   => null,
                'quantity'    => 1,
                'total'       => 0.0,
                'is_gift'     => true,
            ];
        }
    }
    return [$discount, $gift, $options['free_delivery']];
}

/* ---------- placing an order ---------- */

/**
 * Validates and saves an order from the storefront. Throws UserError with
 * a message for the customer. Returns the new order id.
 */
function place_order(array $in, string $ip): int
{
    if (setting('shop_active', '1') !== '1') {
        throw new UserError('Магазин временно не принимает заказы.');
    }
    if (trim((string) ($in['website'] ?? '')) !== '') {
        throw new UserError('Не удалось оформить заказ.'); // honeypot filled by a bot
    }

    $str = static fn (string $key, int $max): string => mb_substr(trim((string) ($in[$key] ?? '')), 0, $max);

    $name = $str('name', 120);
    if ($name === '') {
        throw new UserError('Укажите ваше имя.');
    }
    $phone = normalize_phone($str('phone', 40));
    if ($phone === '') {
        throw new UserError('Проверьте номер телефона.');
    }

    // flood protection: per phone, and a looser limit per IP (customers
    // behind one proxy share an address)
    $since  = date('Y-m-d H:i:s', strtotime('-10 minutes'));
    $recent = db()->prepare('SELECT SUM(CASE WHEN phone = ? THEN 1 ELSE 0 END), SUM(CASE WHEN ip = ? THEN 1 ELSE 0 END) FROM orders WHERE created_at >= ?');
    $recent->execute([$phone, $ip, $since]);
    [$byPhone, $byIp] = array_map('intval', $recent->fetch(PDO::FETCH_NUM));
    if ($byPhone >= 5 || $byIp >= 20) {
        throw new UserError('Слишком много заказов подряд. Попробуйте через несколько минут или позвоните нам.');
    }

    $types = delivery_types();
    $type  = $str('delivery_type', 10);
    if (!isset($types[$type])) {
        throw new UserError('Выберите способ получения.');
    }

    $pointId = null;
    $address = '';
    if ($type === 'pickup') {
        $pointId = (int) ($in['pickup_point_id'] ?? 0);
        $point = array_values(array_filter(pickup_points(), static fn ($p) => (int) $p['id'] === $pointId));
        if (!$point) {
            throw new UserError('Выберите пункт самовывоза.');
        }
    } else {
        $address = $str('address', 255);
        if (mb_strlen($address) < 5) {
            throw new UserError('Укажите адрес доставки.');
        }
    }

    // time: as soon as possible or a pre-order
    $scheduledAt = null;
    $readyIn = 0;
    $now = new DateTimeImmutable();
    if ($str('when', 10) === 'later') {
        if (setting('preorders_enabled', '1') !== '1') {
            throw new UserError('Предзаказы сейчас не принимаются.');
        }
        $time = DateTimeImmutable::createFromFormat('Y-m-d H:i', $str('scheduled_at', 16));
        $min  = $now->modify('+' . max(0, (int) setting('preorder_min_minutes', '60') - 10) . ' minutes');
        $max  = $now->modify('+' . max(1, (int) setting('preorder_max_days', '14')) . ' days');
        if (!$time || $time < $min || $time > $max) {
            throw new UserError('Выберите другое время получения.');
        }
        if (!schedule_allows($time)) {
            throw new UserError('В это время мы не работаем. Выберите другое время.');
        }
        $scheduledAt = $time->format('Y-m-d H:i:s');
    } else {
        if (setting('asap_enabled', '1') !== '1') {
            throw new UserError('Выберите время получения заказа.');
        }
        if (!schedule_allows($now)) {
            throw new UserError('Сейчас мы закрыты — оформите предзаказ на удобное время.');
        }
        if ($type === 'pickup') {
            $readyIn = in_array((int) ($in['ready_in'] ?? 0), READY_IN_OPTIONS, true) ? (int) $in['ready_in'] : READY_IN_OPTIONS[1];
        }
    }

    $payment = $str('payment_method', 20);
    if (!isset(enabled_payment_methods()[$payment])) {
        throw new UserError('Выберите способ оплаты.');
    }

    $items = is_array($in['items'] ?? null) ? $in['items'] : [];
    $source = ($in['source'] ?? '') === 'app' ? 'app' : 'site';
    $priced = price_cart($items, ['delivery_type' => $type, 'promo_code' => $str('promo_code', 40), 'phone' => $phone, 'source' => $source]);
    if (!$priced['lines'] || $priced['count'] === 0) {
        throw new UserError('Корзина пуста.');
    }
    if ($priced['errors']) {
        throw new UserError($priced['errors'][0]);
    }
    if ($str('promo_code', 40) !== '' && $priced['promo_error'] !== '') {
        throw new UserError($priced['promo_error']);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $clientId = remember_client($name, $phone, $source);
        $stamp = $now->format('Y-m-d H:i:s');
        $order = [
            'client_id'       => $clientId,
            'client_name'     => $name,
            'phone'           => $phone,
            'delivery_type'   => $type,
            'pickup_point_id' => $pointId,
            'address'         => $address,
            'apartment'       => $str('apartment', 20),
            'entrance'        => $str('entrance', 20),
            'floor'           => $str('floor', 20),
            'scheduled_at'    => $scheduledAt,
            'ready_in'        => $readyIn,
            'payment_method'  => $payment,
            'change_from'     => $payment === 'cash' ? preg_replace('/\D+/', '', $str('change_from', 20)) : '',
            'comment'         => $str('comment', 1000),
            'subtotal'        => $priced['subtotal'],
            'discount'        => $priced['discount'],
            'delivery_price'  => $priced['delivery_price'],
            'total'           => $priced['total'],
            'promo_code'      => $priced['promo']['code'] ?? '',
            'promotion_id'    => $priced['promo']['id'] ?? null,
            'source'          => $source,
            'status'          => 'new',
            'ip'              => $ip,
            'created_at'      => $stamp,
            'updated_at'      => $stamp,
        ];
        $columns = implode(', ', array_keys($order));
        $marks   = implode(', ', array_fill(0, count($order), '?'));
        $pdo->prepare("INSERT INTO orders ({$columns}) VALUES ({$marks})")->execute(array_values($order));
        $orderId = (int) $pdo->lastInsertId();

        $insert = $pdo->prepare('INSERT INTO order_items (order_id, product_id, name, unit, price, quantity, total, is_gift) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stock  = $pdo->prepare('SELECT extra FROM products WHERE id = ?');
        $update = $pdo->prepare('UPDATE products SET extra = ? WHERE id = ?');
        foreach ($priced['lines'] as $line) {
            $insert->execute([$orderId, $line['product_id'], $line['name'], $line['unit'], $line['price'], $line['quantity'], $line['total'], $line['is_gift'] ? 1 : 0]);
            // keep "Остатки" in sync
            $stock->execute([$line['product_id']]);
            $extra = product_extra($stock->fetchColumn() ?: null);
            if ($extra['stock']['on']) {
                $extra['stock']['qty'] = max(0, $extra['stock']['qty'] - $line['quantity']);
                $update->execute([json_encode($extra, JSON_UNESCAPED_UNICODE), $line['product_id']]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $orderId;
}

/** Finds the client by phone (or creates one) and returns the id. */
function remember_client(string $name, string $phone, string $source): int
{
    $now = date('Y-m-d H:i:s');
    $stmt = db()->prepare('SELECT id, name FROM clients WHERE phone = ? ORDER BY id LIMIT 1');
    $stmt->execute([$phone]);
    if ($client = $stmt->fetch()) {
        db()->prepare('UPDATE clients SET name = ?, last_visit_at = ? WHERE id = ?')
            ->execute([$client['name'] !== '' ? $client['name'] : $name, $now, $client['id']]);
        return (int) $client['id'];
    }
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $platform = $source === 'app' ? (preg_match('/iPhone|iPad|iPod/i', $ua) ? 'ios' : (stripos($ua, 'Android') !== false ? 'android' : 'web')) : 'web';
    db()->prepare('INSERT INTO clients (name, phone, platform, last_visit_at, created_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([$name, $phone, $platform, $now, $now]);
    return (int) db()->lastInsertId();
}

/* ---------- reading orders ---------- */

function order_with_items(int $id): ?array
{
    $stmt = db()->prepare('SELECT o.*, p.address AS pickup_address FROM orders o LEFT JOIN pickup_points p ON p.id = o.pickup_point_id WHERE o.id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }
    $items = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $items->execute([$id]);
    $order['items'] = $items->fetchAll();
    return $order;
}

/** "Самовывоз · Тореза 42а/1" or the delivery address. */
function order_place_label(array $order): string
{
    if ($order['delivery_type'] === 'pickup') {
        return 'Самовывоз' . (!empty($order['pickup_address']) ? ' · ' . $order['pickup_address'] : '');
    }
    $parts = [$order['address']];
    foreach (['apartment' => 'кв.', 'entrance' => 'подъезд', 'floor' => 'этаж'] as $key => $label) {
        if ($order[$key] !== '') {
            $parts[] = $label . ' ' . $order[$key];
        }
    }
    return implode(', ', $parts);
}

function order_time_label(array $order): string
{
    if ($order['scheduled_at']) {
        return 'Предзаказ на ' . date('d.m.Y в H:i', strtotime($order['scheduled_at']));
    }
    if ($order['delivery_type'] === 'pickup' && $order['ready_in'] > 0) {
        return 'Заберут через ' . $order['ready_in'] . ' мин';
    }
    return 'Как можно скорее';
}

/* ---------- telegram ---------- */

function telegram_chat_ids(): array
{
    return array_values(array_filter(preg_split('/[\s,;]+/', (string) setting('telegram_chat_ids', '')), static fn ($id) => preg_match('/^-?\d+$/', $id)));
}

/** Calls a Telegram Bot API method; returns the decoded response or null. */
function telegram_api(string $method, array $params, ?string $token = null): ?array
{
    $token = $token ?? (string) setting('telegram_token', '');
    if (!preg_match('/^\d+:[\w-]+$/', $token)) {
        return null;
    }
    $url  = 'https://api.telegram.org/bot' . $token . '/' . $method;
    $body = http_build_query($params);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $raw = @file_get_contents($url, false, stream_context_create(['http' => [
            'method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $body, 'timeout' => 8, 'ignore_errors' => true,
        ]]));
    }
    $json = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($json) || empty($json['ok'])) {
        @file_put_contents(ROOT_DIR . '/storage/telegram.log', date('c') . ' ' . $method . ' ' . (is_string($raw) ? mb_substr($raw, 0, 300) : 'no response') . "\n", FILE_APPEND);
        return is_array($json) ? $json : null;
    }
    return $json;
}

function telegram_send(string $html, ?array $chatIds = null): int
{
    if (setting('telegram_enabled', '1') !== '1' && $chatIds === null) {
        return 0;
    }
    $sent = 0;
    foreach ($chatIds ?? telegram_chat_ids() as $chatId) {
        $res = telegram_api('sendMessage', ['chat_id' => $chatId, 'text' => $html, 'parse_mode' => 'HTML', 'disable_web_page_preview' => 'true']);
        $sent += !empty($res['ok']) ? 1 : 0;
    }
    return $sent;
}

function order_telegram_text(array $order, string $adminUrl = ''): string
{
    $h = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $lines = [];
    $lines[] = '🌸 <b>Новый заказ #' . $order['id'] . '</b>' . ($order['source'] === 'app' ? ' (приложение)' : '');
    $lines[] = ($order['delivery_type'] === 'pickup' ? '🏪 ' : '🚗 ') . $h(order_place_label($order));
    $lines[] = '🕒 ' . $h(order_time_label($order));
    $lines[] = '';
    $lines[] = '👤 ' . $h($order['client_name']) . ', ' . $h($order['phone']);
    $lines[] = '';
    foreach ($order['items'] as $item) {
        $lines[] = '• ' . $h($item['name']) . ' × ' . $item['quantity'] . ($item['is_gift'] ? ' — 🎁 подарок' : ' — ' . price_label((float) $item['total']));
    }
    if ((float) $order['discount'] > 0) {
        $lines[] = 'Скидка' . ($order['promo_code'] ? ' (' . $h($order['promo_code']) . ')' : '') . ': −' . price_label((float) $order['discount']);
    }
    if ($order['delivery_type'] === 'delivery') {
        $lines[] = 'Доставка: ' . ((float) $order['delivery_price'] > 0 ? price_label((float) $order['delivery_price']) : 'бесплатно');
    }
    $lines[] = '<b>Итого: ' . price_label((float) $order['total']) . '</b>';
    $lines[] = '💳 ' . $h(PAYMENT_METHODS[$order['payment_method']] ?? $order['payment_method'])
        . ($order['change_from'] !== '' ? ', сдача с ' . $h($order['change_from']) . ' ₽' : '');
    if (trim((string) $order['comment']) !== '') {
        $lines[] = '💬 ' . $h($order['comment']);
    }
    if ($adminUrl !== '') {
        $lines[] = '';
        $lines[] = '<a href="' . $h($adminUrl) . '">Открыть в панели</a>';
    }
    return implode("\n", $lines);
}

function notify_new_order(int $orderId): void
{
    $order = order_with_items($orderId);
    if (!$order || !telegram_chat_ids()) {
        return;
    }
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $adminUrl = $host !== '' ? (is_https() ? 'https://' : 'http://') . $host . rtrim((string) config('site_url', '/'), '/') . '/admin/index.php?p=orders&order=' . $orderId : '';
    telegram_send(order_telegram_text($order, $adminUrl));
}

/* ---------- admin list ---------- */

const ORDER_PER_PAGE = [25, 50, 100];

function order_filters(): array
{
    $promos = db()->query('SELECT id, name FROM promotions ORDER BY id DESC')->fetchAll(PDO::FETCH_KEY_PAIR);
    $points = db()->query('SELECT id, address FROM pickup_points ORDER BY sort, id')->fetchAll(PDO::FETCH_KEY_PAIR);

    $filters = [
        'phone'   => ['title' => 'Поиск по телефону', 'type' => 'text', 'placeholder' => 'Например, 912 345', 'inputmode' => 'tel'],
        'date'    => ['title' => 'Дата заказа', 'type' => 'date'],
        'total'   => ['title' => 'Итого', 'type' => 'range'],
        'promo'   => ['title' => 'Акция', 'type' => 'select', 'options' => $promos],
        'source'  => ['title' => 'Источник', 'type' => 'select', 'options' => ['site' => 'Сайт', 'app' => 'Приложение']],
        'type'    => ['title' => 'Тип доставки', 'type' => 'select', 'options' => ['delivery' => 'Доставка', 'pickup' => 'Самовывоз']],
        'point'   => ['title' => 'Пункт самовывоза', 'type' => 'select', 'options' => $points],
        'payment' => ['title' => 'Метод оплаты', 'type' => 'select', 'options' => PAYMENT_METHODS],
        'status'  => ['title' => 'Статус', 'type' => 'multi', 'options' => array_map(static fn ($s) => $s['label'], ORDER_STATUSES)],
        'reason'  => ['title' => 'Причина отмены', 'type' => 'select', 'options' => array_combine(CANCEL_REASONS, CANCEL_REASONS)],
    ];
    // hide filters that have nothing to choose from yet
    return array_filter($filters, static fn ($f) => $f['type'] !== 'select' || $f['options']);
}

/** [sql without LIMIT, params] for the admin orders table. */
function orders_query(array $values): array
{
    $where = [];
    $params = [];
    if (isset($values['phone'])) {
        [$sql, $param] = filter_phone_sql('o.phone', $values['phone']);
        $where[] = $sql;
        $params[] = $param;
    }
    [$sql, $p] = filter_date_sql('date', 'o.created_at', $values);
    array_push($where, ...$sql);
    array_push($params, ...$p);
    array_push($where, ...filter_range_sql('total', 'o.total', $values));

    $equals = ['promo' => 'o.promotion_id', 'source' => 'o.source', 'type' => 'o.delivery_type', 'point' => 'o.pickup_point_id', 'payment' => 'o.payment_method', 'reason' => 'o.cancel_reason'];
    foreach ($equals as $key => $column) {
        if (isset($values[$key])) {
            $where[] = "{$column} = ?";
            $params[] = $values[$key];
        }
    }
    if (isset($values['status'])) {
        $where[] = 'o.status IN (' . implode(', ', array_fill(0, count($values['status']), '?')) . ')';
        array_push($params, ...$values['status']);
    }

    $sql = 'SELECT o.*, p.address AS pickup_address FROM orders o LEFT JOIN pickup_points p ON p.id = o.pickup_point_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY o.id DESC';
    return [$sql, $params];
}

/** Order data for the admin order window. */
function order_payload(array $order): array
{
    return [
        'id'         => (int) $order['id'],
        'date'       => date('d.m.Y H:i', strtotime($order['created_at'])),
        'status'     => $order['status'],
        'reason'     => $order['cancel_reason'],
        'name'       => $order['client_name'],
        'phone'      => $order['phone'],
        'client_id'  => $order['client_id'] ? (int) $order['client_id'] : null,
        'type'       => $order['delivery_type'],
        'place'      => $order['delivery_type'] === 'pickup' ? (string) ($order['pickup_address'] ?? '') : order_place_label($order),
        'time'       => order_time_label($order),
        'payment'    => (PAYMENT_METHODS[$order['payment_method']] ?? $order['payment_method']) . ($order['change_from'] !== '' ? ', сдача с ' . $order['change_from'] . ' ₽' : ''),
        'comment'    => (string) $order['comment'],
        'source'     => $order['source'] === 'app' ? 'Приложение' : 'Сайт',
        'promo'      => $order['promo_code'],
        'items'      => array_map(static fn ($i) => [
            'name'  => $i['name'],
            'unit'  => $i['unit'],
            'qty'   => (int) $i['quantity'],
            'total' => $i['is_gift'] ? 'подарок' : price_label((float) $i['total']),
        ], $order['items'] ?? []),
        'subtotal'   => price_label((float) $order['subtotal']),
        'discount'   => (float) $order['discount'] > 0 ? '−' . price_label((float) $order['discount']) : '',
        'delivery'   => $order['delivery_type'] === 'delivery' ? ((float) $order['delivery_price'] > 0 ? price_label((float) $order['delivery_price']) : 'бесплатно') : '',
        'total'      => price_label((float) $order['total']),
    ];
}
