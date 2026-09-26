<?php
require dirname(__DIR__) . '/core/bootstrap.php';
require dirname(__DIR__) . '/core/clients.php';

$action = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';

if ($action === 'export') {
    require_admin();
    $values = client_filter_values($_GET);
    [$sql, $params] = clients_query($values);
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clients-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel opens it as UTF-8
    fputcsv($out, ['Имя', 'Телефон', 'Последний визит', 'Последний заказ', 'Заказов', 'Баллы', 'Пол', 'День рождения', 'Платформа', 'Средний чек', 'Скидка, %', 'Дата регистрации'], ';', '"', '');
    $genders   = ['m' => 'М', 'f' => 'Ж'];
    $platforms = ['ios' => 'iOS', 'android' => 'Android', 'web' => 'Сайт'];
    foreach ($stmt as $c) {
        fputcsv($out, [
            $c['name'],
            $c['phone'],
            ru_date($c['last_visit_at']),
            ru_date($c['last_order_at']),
            $c['orders_count'],
            $c['points'],
            $genders[$c['gender']] ?? '',
            ru_date($c['birthday']),
            $platforms[$c['platform']] ?? '',
            $c['avg_check'] !== null ? round((float) $c['avg_check']) : '',
            $c['discount'],
            ru_date($c['created_at']),
        ], ';', '"', '');
    }
    exit;
}

api_guard();

if (input_string('action', 20) === 'phone') {
    $stmt = db()->prepare('SELECT phone FROM clients WHERE id = ?');
    $stmt->execute([input_int('id')]);
    $phone = $stmt->fetchColumn();
    if ($phone === false) {
        json_response(['error' => 'Клиент не найден'], 404);
    }
    json_response(['phone' => $phone]);
}

json_response(['error' => 'unknown action'], 400);
