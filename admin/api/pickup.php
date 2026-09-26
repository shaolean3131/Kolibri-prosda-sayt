<?php
require dirname(__DIR__) . '/core/bootstrap.php';

api_guard();

$id = input_int('id');

function find_point(int $id): array
{
    $stmt = db()->prepare('SELECT * FROM pickup_points WHERE id = ?');
    $stmt->execute([$id]);
    $point = $stmt->fetch();
    if (!$point) {
        json_response(['error' => 'Пункт не найден. Обновите страницу.'], 404);
    }
    return $point;
}

switch (input_string('action', 20)) {
    case 'create':
        $sort = (int) db()->query('SELECT COALESCE(MAX(sort), 0) + 1 FROM pickup_points')->fetchColumn();
        db()->prepare("INSERT INTO pickup_points (address, hours, sort, is_active, created_at) VALUES ('', 'круглосуточно', ?, 1, ?)")
            ->execute([$sort, date('Y-m-d H:i:s')]);
        json_response(['ok' => true, 'id' => (int) db()->lastInsertId()]);

    case 'save':
        find_point($id);
        $lat = '';
        $lng = '';
        if (preg_match('/^\s*(-?\d{1,2}(?:[.,]\d+)?)\s*[,; ]\s*(-?\d{1,3}(?:[.,]\d+)?)\s*$/', input_string('coords', 60), $m)) {
            $lat = str_replace(',', '.', $m[1]);
            $lng = str_replace(',', '.', $m[2]);
        }
        db()->prepare('UPDATE pickup_points SET address = ?, hours = ?, lat = ?, lng = ?, is_active = ? WHERE id = ?')
            ->execute([input_string('address', 255), input_string('hours', 120), $lat, $lng, input_int('is_active') ? 1 : 0, $id]);
        json_response(['ok' => true]);

    case 'delete':
        find_point($id);
        db()->prepare('DELETE FROM pickup_points WHERE id = ?')->execute([$id]);
        json_response(['ok' => true]);
}

json_response(['error' => 'unknown action'], 400);
