<?php
defined('KOLIBRI') or exit;

require_once __DIR__ . '/filters.php';

const CLIENT_PER_PAGE = [25, 50, 100];

function client_filters(): array
{
    return [
        'phone'      => ['title' => 'Номер телефона',         'type' => 'text', 'placeholder' => 'Например, 912 345', 'inputmode' => 'tel'],
        'visit'      => ['title' => 'Дата последнего визита', 'type' => 'date'],
        'order'      => ['title' => 'Дата последнего заказа', 'type' => 'date'],
        'registered' => ['title' => 'Дата регистрации',       'type' => 'date'],
        'birthday'   => ['title' => 'День рождения',          'type' => 'select', 'options' => [
            'today' => 'Сегодня', 'week' => 'В ближайшие 7 дней', 'month' => 'В ближайшие 30 дней',
        ]],
        'orders'     => ['title' => 'Количество заказов',     'type' => 'range'],
        'check'      => ['title' => 'Средний чек',            'type' => 'range'],
        'discount'   => ['title' => 'Персональная скидка',    'type' => 'select', 'options' => ['yes' => 'Есть', 'no' => 'Нет']],
        'gender'     => ['title' => 'Пол',                    'type' => 'select', 'options' => ['m' => 'Мужской', 'f' => 'Женский', 'none' => 'Не указан']],
        'platform'   => ['title' => 'Платформа',              'type' => 'select', 'options' => ['ios' => 'iOS', 'android' => 'Android', 'web' => 'Сайт']],
    ];
}

/**
 * Builds the clients query: [sql without LIMIT, params]. Aggregates come
 * from non-cancelled orders.
 */
function clients_query(array $values): array
{
    $where  = [];
    $params = [];

    if (isset($values['phone'])) {
        [$sql, $param] = filter_phone_sql('c.phone', $values['phone']);
        $where[] = $sql;
        $params[] = $param;
    }
    foreach (['visit' => 'c.last_visit_at', 'registered' => 'c.created_at'] as $key => $column) {
        [$sql, $p] = filter_date_sql($key, $column, $values);
        array_push($where, ...$sql);
        array_push($params, ...$p);
    }

    if (isset($values['birthday'])) {
        $days  = ['today' => 0, 'week' => 6, 'month' => 29][$values['birthday']];
        $dates = [];
        for ($d = 0; $d <= $days; $d++) {
            $dates[] = date('m-d', strtotime("+{$d} days"));
        }
        $where[] = 'SUBSTR(c.birthday, 6, 5) IN (' . implode(', ', array_fill(0, count($dates), '?')) . ')';
        array_push($params, ...$dates);
    }
    if (isset($values['discount'])) {
        $where[] = $values['discount'] === 'yes' ? 'c.discount > 0' : 'c.discount = 0';
    }
    if (isset($values['gender'])) {
        $where[] = 'c.gender = ?';
        $params[] = $values['gender'] === 'none' ? '' : $values['gender'];
    }
    if (isset($values['platform'])) {
        $where[] = 'c.platform = ?';
        $params[] = $values['platform'];
    }

    [$having, $hparams] = filter_date_sql('order', 'MAX(o.created_at)', $values);
    array_push($having, ...filter_range_sql('orders', 'COUNT(o.id)', $values));
    array_push($having, ...filter_range_sql('check', 'COALESCE(AVG(o.total), 0)', $values));

    $sql = "SELECT c.id, c.name, c.phone, c.birthday, c.gender, c.platform, c.points, c.discount,
                   c.last_visit_at, c.created_at,
                   COUNT(o.id) AS orders_count, MAX(o.created_at) AS last_order_at, AVG(o.total) AS avg_check
            FROM clients c
            LEFT JOIN orders o ON o.client_id = c.id AND o.status <> 'cancelled'"
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' GROUP BY c.id, c.name, c.phone, c.birthday, c.gender, c.platform, c.points, c.discount, c.last_visit_at, c.created_at'
        . ($having ? ' HAVING ' . implode(' AND ', $having) : '')
        . ' ORDER BY COALESCE(MAX(o.created_at), c.created_at) DESC, c.id DESC';

    return [$sql, array_merge($params, $hparams)];
}

function clients_count(array $values): int
{
    [$sql, $params] = clients_query($values);
    $stmt = db()->prepare("SELECT COUNT(*) FROM ({$sql}) t");
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function clients_page(array $values, int $limit, int $offset): array
{
    [$sql, $params] = clients_query($values);
    $stmt = db()->prepare($sql . ' LIMIT ' . $limit . ' OFFSET ' . $offset);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function ru_date(?string $datetime): string
{
    return $datetime ? date('d.m.Y', strtotime($datetime)) : '';
}
