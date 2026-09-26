<?php
require dirname(__DIR__) . '/core/bootstrap.php';
require_once dirname(__DIR__) . '/core/promotions.php';

api_guard();

$action = input_string('action', 40);
$id     = input_int('id');
$pdo    = db();

function find_promo(int $id): array
{
    $stmt = db()->prepare('SELECT * FROM promotions WHERE id = ?');
    $stmt->execute([$id]);
    $promo = $stmt->fetch();
    if (!$promo) {
        json_response(['error' => 'Акция не найдена. Обновите страницу.'], 404);
    }
    return $promo;
}

function input_datetime(string $key): ?string
{
    $value = input_string($key, 20);
    $time  = $value !== '' ? strtotime($value) : false;
    return $time ? date('Y-m-d H:i:s', $time) : null;
}

try {
    switch ($action) {
        case 'save':
            $promo = $id > 0 ? find_promo($id) : null;
            $type  = input_string('type', 20);
            $data  = [
                'name'         => input_string('name', 190),
                'description'  => input_string('description', 3000),
                'type'         => array_key_exists($type, PROMO_TYPES) ? $type : 'percent',
                'value'        => input_money('value') ?? 0,
                'min_order'    => input_money('min_order') ?? 0,
                'applies_to'   => input_string('applies_to', 20) === 'categories' ? 'categories' : 'all',
                'category_ids' => implode(',', array_filter(array_map('intval', (array) ($_POST['category_ids'] ?? [])))),
                'promo_codes'  => json_encode(promo_codes(input_string('promo_codes', 2000)), JSON_UNESCAPED_UNICODE),
                'options'      => json_encode(promo_options(is_array($_POST['options'] ?? null) ? $_POST['options'] : []), JSON_UNESCAPED_UNICODE),
                'starts_at'    => input_int('unlimited') ? null : input_datetime('starts_at'),
                'ends_at'      => input_int('unlimited') ? null : input_datetime('ends_at'),
            ];

            if ($data['name'] === '') {
                json_response(['error' => 'Введите название акции.'], 422);
            }
            if ($data['type'] === 'percent' && $data['value'] > 100) {
                json_response(['error' => 'Скидка не может быть больше 100%.'], 422);
            }
            if ($data['starts_at'] && $data['ends_at'] && $data['ends_at'] < $data['starts_at']) {
                json_response(['error' => 'Окончание акции раньше начала.'], 422);
            }

            $replaced = [];
            foreach (array_keys(PROMO_BANNERS) as $banner) {
                if (!empty($_FILES[$banner]['name'])) {
                    $data[$banner] = store_image($_FILES[$banner], 'promo');
                } elseif (input_int($banner . '_remove')) {
                    $data[$banner] = '';
                } else {
                    continue;
                }
                if ($promo) {
                    $replaced[] = $promo[$banner];
                }
            }

            if ($promo) {
                $sets = implode(', ', array_map(static fn ($col) => "{$col} = ?", array_keys($data)));
                $pdo->prepare("UPDATE promotions SET {$sets} WHERE id = ?")->execute([...array_values($data), $id]);
                array_map('delete_upload', $replaced);
            } else {
                $data += ['sort' => 0, 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')];
                $columns = implode(', ', array_keys($data));
                $marks   = implode(', ', array_fill(0, count($data), '?'));
                $pdo->prepare("INSERT INTO promotions ({$columns}) VALUES ({$marks})")->execute(array_values($data));
                $id = (int) $pdo->lastInsertId();
            }
            json_response(['ok' => true, 'id' => $id]);

        case 'toggle':
            find_promo($id);
            $pdo->prepare('UPDATE promotions SET is_active = ? WHERE id = ?')->execute([input_int('active') ? 1 : 0, $id]);
            json_response(['ok' => true]);

        case 'delete':
            $promo = find_promo($id);
            $pdo->prepare('DELETE FROM promotions WHERE id = ?')->execute([$id]);
            foreach (array_keys(PROMO_BANNERS) as $banner) {
                delete_upload($promo[$banner]);
            }
            json_response(['ok' => true]);
    }
} catch (UserError $e) {
    json_response(['error' => $e->getMessage()], 422);
}

json_response(['error' => 'unknown action'], 400);
