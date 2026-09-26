<?php
require dirname(__DIR__) . '/core/bootstrap.php';
require dirname(__DIR__) . '/core/catalog.php';

api_guard();

$action = input_string('action', 40);
$id     = input_int('id');
$now    = date('Y-m-d H:i:s');
$pdo    = db();

function find_row(string $table, int $id): array
{
    $stmt = db()->prepare("SELECT * FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        json_response(['error' => 'Запись не найдена. Обновите страницу.'], 404);
    }
    return $row;
}

/** Saves the new order of the given ids (only rows matching $scope are touched). */
function save_order(string $table, array $ids, string $scope = '1 = 1', array $params = []): void
{
    $stmt = db()->prepare("UPDATE {$table} SET sort = ? WHERE id = ? AND {$scope}");
    foreach (array_values($ids) as $position => $rowId) {
        $stmt->execute(array_merge([$position + 1, (int) $rowId], $params));
    }
}

try {
    switch ($action) {
        /* ---------- categories ---------- */

        case 'category.save':
            $name = input_string('name', 120);
            if ($name === '') {
                json_response(['error' => 'Введите название категории.'], 422);
            }
            if ($id > 0) {
                find_row('categories', $id);
                $pdo->prepare('UPDATE categories SET name = ? WHERE id = ?')->execute([$name, $id]);
            } else {
                $pdo->prepare('INSERT INTO categories (name, sort, is_active, created_at) VALUES (?, ?, 1, ?)')
                    ->execute([$name, next_sort('categories'), $now]);
                $id = (int) $pdo->lastInsertId();
            }
            json_response(['ok' => true, 'id' => $id]);

        case 'category.toggle':
            find_row('categories', $id);
            $pdo->prepare('UPDATE categories SET is_active = ? WHERE id = ?')->execute([input_int('active') ? 1 : 0, $id]);
            json_response(['ok' => true]);

        case 'category.image':
            $category = find_row('categories', $id);
            $path = store_image($_FILES['image'] ?? [], 'catalog');
            $pdo->prepare('UPDATE categories SET image = ? WHERE id = ?')->execute([$path, $id]);
            delete_upload($category['image']);
            json_response(['ok' => true, 'image' => upload_url($path)]);

        case 'category.delete':
            $category = find_row('categories', $id);
            $images = $pdo->prepare('SELECT image FROM products WHERE category_id = ?');
            $images->execute([$id]);
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM products WHERE category_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            $pdo->commit();
            foreach ($images->fetchAll(PDO::FETCH_COLUMN) as $image) {
                delete_upload($image);
            }
            delete_upload($category['image']);
            json_response(['ok' => true]);

        case 'category.reorder':
            save_order('categories', (array) ($_POST['ids'] ?? []));
            json_response(['ok' => true]);

        /* ---------- products ---------- */

        case 'product.save':
            $categoryId = input_int('category_id');
            find_row('categories', $categoryId);
            $data = [
                'category_id' => $categoryId,
                'name'        => input_string('name', 190),
                'description' => input_string('description', 3000),
                'price'       => input_money('price'),
                'old_price'   => input_money('old_price'),
                'unit_amount' => input_string('unit_amount', 20) ?: '1',
                'unit_name'   => input_string('unit_name', 30) ?: 'шт',
            ];
            if ($data['name'] === '') {
                json_response(['error' => 'Введите название товара.'], 422);
            }
            if ($data['price'] === null) {
                json_response(['error' => 'Укажите цену.'], 422);
            }

            $newImage = null;
            if (!empty($_FILES['image']['name'])) {
                $newImage = store_image($_FILES['image'], 'catalog');
                $data['image'] = $newImage;
            }

            if ($id > 0) {
                $product = find_row('products', $id);
                if ($newImage === null && input_int('remove_image')) {
                    $data['image'] = '';
                }
                if ((int) $product['category_id'] !== $categoryId) {
                    $data['sort'] = next_sort('products', 'category_id = ?', [$categoryId]);
                }
                $sets = implode(', ', array_map(static fn ($col) => "{$col} = ?", array_keys($data)));
                $pdo->prepare("UPDATE products SET {$sets} WHERE id = ?")->execute([...array_values($data), $id]);
                if (array_key_exists('image', $data)) {
                    delete_upload($product['image']);
                }
            } else {
                $data += [
                    'image'      => '',
                    'labels'     => '',
                    'extra'      => json_encode(product_extra_defaults(), JSON_UNESCAPED_UNICODE),
                    'sort'       => next_sort('products', 'category_id = ?', [$categoryId]),
                    'is_active'  => 1,
                    'created_at' => $now,
                ];
                $columns = implode(', ', array_keys($data));
                $marks   = implode(', ', array_fill(0, count($data), '?'));
                $pdo->prepare("INSERT INTO products ({$columns}) VALUES ({$marks})")->execute(array_values($data));
                $id = (int) $pdo->lastInsertId();
            }
            json_response(['ok' => true, 'id' => $id]);

        case 'product.toggle':
            find_row('products', $id);
            $pdo->prepare('UPDATE products SET is_active = ? WHERE id = ?')->execute([input_int('active') ? 1 : 0, $id]);
            json_response(['ok' => true]);

        case 'product.labels':
            find_row('products', $id);
            $labels = array_intersect((array) ($_POST['labels'] ?? []), array_keys(PRODUCT_LABELS));
            $pdo->prepare('UPDATE products SET labels = ? WHERE id = ?')->execute([implode(',', $labels), $id]);
            json_response(['ok' => true]);

        case 'product.extra':
            find_row('products', $id);
            $extra = product_extra(input_string('extra', 20000));
            $pdo->prepare('UPDATE products SET extra = ? WHERE id = ?')->execute([json_encode($extra, JSON_UNESCAPED_UNICODE), $id]);
            json_response(['ok' => true]);

        case 'product.delete':
            $product = find_row('products', $id);
            $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            delete_upload($product['image']);
            json_response(['ok' => true]);

        case 'product.reorder':
            $categoryId = input_int('category_id');
            save_order('products', (array) ($_POST['ids'] ?? []), 'category_id = ?', [$categoryId]);
            json_response(['ok' => true]);
    }
} catch (UserError $e) {
    json_response(['error' => $e->getMessage()], 422);
}

json_response(['error' => 'unknown action'], 400);
