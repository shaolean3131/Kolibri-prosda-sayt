<?php
defined('KOLIBRI') or exit;

const CLIENT_PER_PAGE = [25, 50, 100];

/**
 * Filter chips shown above the clients table. Each filter reads its own GET
 * parameters; 'type' decides both the dropdown fields and the SQL condition.
 */
function client_filters(): array
{
    return [
        'phone'      => ['title' => 'Номер телефона',        'type' => 'text'],
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

/** Current filter values from the query string (only non-empty ones). */
function client_filter_values(array $query): array
{
    $values = [];
    foreach (client_filters() as $key => $filter) {
        $fields = match ($filter['type']) {
            'date'  => ["{$key}_from", "{$key}_to"],
            'range' => ["{$key}_min", "{$key}_max"],
            default => [$key],
        };
        foreach ($fields as $field) {
            $value = $query[$field] ?? '';
            if (!is_string($value) || trim($value) === '') {
                continue;
            }
            $value = trim($value);
            $valid = match ($filter['type']) {
                'date'   => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value),
                'range'  => is_numeric($value) && abs((float) $value) < 1e12,
                'select' => isset($filter['options'][$value]),
                default  => mb_strlen($value) <= 40,
            };
            if ($valid) {
                $values[$field] = $value;
            }
        }
    }
    return $values;
}

/** Short text shown on an active filter chip, e.g. "от 3" or "Женский". */
function client_filter_summary(string $key, array $filter, array $values): string
{
    $fmt = static fn (string $date): string => date('d.m.y', strtotime($date));
    switch ($filter['type']) {
        case 'date':
            $from = $values["{$key}_from"] ?? null;
            $to   = $values["{$key}_to"] ?? null;
            return trim(($from ? 'с ' . $fmt($from) : '') . ' ' . ($to ? 'по ' . $fmt($to) : ''));
        case 'range':
            $min = $values["{$key}_min"] ?? null;
            $max = $values["{$key}_max"] ?? null;
            return trim(($min !== null ? 'от ' . $min : '') . ' ' . ($max !== null ? 'до ' . $max : ''));
        case 'select':
            return isset($values[$key]) ? $filter['options'][$values[$key]] : '';
        default:
            return $values[$key] ?? '';
    }
}

/**
 * Builds the clients query: [sql without LIMIT, params]. Aggregates come
 * from non-cancelled orders.
 */
function clients_query(array $values): array
{
    $where  = [];
    $having = [];
    $params = [];
    $hparams = [];

    if (isset($values['phone'])) {
        $digits = preg_replace('/\D+/', '', $values['phone']);
        $where[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(c.phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE ?";
        $params[] = '%' . ($digits !== '' ? $digits : $values['phone']) . '%';
    }

    $dateColumns = ['visit' => 'c.last_visit_at', 'registered' => 'c.created_at'];
    foreach ($dateColumns as $key => $column) {
        if (isset($values["{$key}_from"])) {
            $where[] = "{$column} >= ?";
            $params[] = $values["{$key}_from"] . ' 00:00:00';
        }
        if (isset($values["{$key}_to"])) {
            $where[] = "{$column} <= ?";
            $params[] = $values["{$key}_to"] . ' 23:59:59';
        }
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

    if (isset($values['order_from'])) {
        $having[] = 'MAX(o.created_at) >= ?';
        $hparams[] = $values['order_from'] . ' 00:00:00';
    }
    if (isset($values['order_to'])) {
        $having[] = 'MAX(o.created_at) <= ?';
        $hparams[] = $values['order_to'] . ' 23:59:59';
    }
    // Numbers are inlined (already validated as numeric): PDO binds every
    // value as a string, and SQLite never matches COUNT() >= '3'.
    $ranges = ['orders' => 'COUNT(o.id)', 'check' => 'COALESCE(AVG(o.total), 0)'];
    foreach ($ranges as $key => $expr) {
        if (isset($values["{$key}_min"])) {
            $having[] = "{$expr} >= " . (float) $values["{$key}_min"];
        }
        if (isset($values["{$key}_max"])) {
            $having[] = "{$expr} <= " . (float) $values["{$key}_max"];
        }
    }

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
