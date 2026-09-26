<?php
/*
 * Fills the database with demo clients and orders so the dashboard charts
 * can be previewed. Run from the command line only:
 *
 *   php admin/tools/seed-demo.php
 *
 * Do not run it on the live shop database.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/core/bootstrap.php';

$pdo = db();
$pdo->beginTransaction();

$names = ['Анна', 'Мария', 'Екатерина', 'Ольга', 'Дмитрий', 'Алексей', 'Ирина', 'Светлана', 'Сергей', 'Наталья'];
$clientIds = [];
$insertClient = $pdo->prepare('INSERT INTO clients (name, phone, birthday, gender, platform, points, discount, last_visit_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
for ($i = 0; $i < 160; $i++) {
    $created = time() - random_int(0, 365 * 86400);
    $name    = random_int(0, 4) ? $names[array_rand($names)] : '';
    $insertClient->execute([
        $name,
        '+7 9' . random_int(100000000, 999999999),
        random_int(0, 2) ? null : date('Y-m-d', strtotime('-' . random_int(18, 60) . ' years -' . random_int(0, 364) . ' days')),
        ['', 'm', 'f', 'f'][random_int(0, 3)],
        ['ios', 'android', 'web', 'web'][random_int(0, 3)],
        random_int(0, 3) ? 0 : random_int(50, 900),
        random_int(0, 6) ? 0 : 5,
        random_int(0, 2) ? date('Y-m-d H:i:s', random_int($created, time())) : null,
        date('Y-m-d H:i:s', $created),
    ]);
    $clientIds[] = (int) $pdo->lastInsertId();
}

$catalog = [
    'Монобукеты' => [
        ['Букет «Фиалковый рассвет»', 'Сборный букет из 5 веток белой кустовой хризантемы с акцентами из мягкого розово-лилового лагуруса 11шт., оформленный в двухслойную сиренево-лавандовую упаковку с бантом.', 2500, '1', 'букет'],
        ['Букет из 25 красных роз', 'Классический букет из 25 красных роз 50 см в крафтовой упаковке.', 4900, '1', 'букет'],
    ],
    'Авторские букеты' => [
        ['Букет «Лавандовое поле»', 'Авторский букет в сиреневых тонах: эустома, лизиантус, лагурус, эвкалипт.', 3600, '1', 'букет'],
    ],
    'Композиции' => [
        ['Композиция «Розовая сказка»', 'Корзина в розово-белой гамме: 2 кустовые хризантемы, гвоздики, гипсофила.', 2420, '1', 'шт'],
        ['Композиция «Морская акварель»', 'Компактная коробочка-сумочка с 2 голубыми хризантемами и гвоздиками.', 2520, '1', 'шт'],
        ['Композиция «Голубая мечта»', 'Коробочка в нежно-голубой гамме: 3 синих ириса, 3 кустовые хризантемы, лагурус.', 2150, '1', 'шт'],
    ],
    'Открытки и сувениры' => [
        ['Открытка «С днём рождения»', 'Открытка ручной работы с конвертом.', 250, '1', 'шт'],
    ],
];
$insertCategory = $pdo->prepare('INSERT INTO categories (name, sort, is_active, created_at) VALUES (?, ?, 1, ?)');
$insertProduct  = $pdo->prepare('INSERT INTO products (category_id, name, description, price, unit_amount, unit_name, labels, sort, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
$sort = 0;
foreach ($catalog as $category => $products) {
    $insertCategory->execute([$category, ++$sort, date('Y-m-d H:i:s')]);
    $categoryId = (int) $pdo->lastInsertId();
    foreach ($products as $n => [$name, $description, $price, $amount, $unit]) {
        $insertProduct->execute([$categoryId, $name, $description, $price, $amount, $unit, $n === 0 ? 'hit' : '', $n + 1, date('Y-m-d H:i:s')]);
    }
}

$insertOrder = $pdo->prepare('INSERT INTO orders (client_id, total, status, created_at) VALUES (?, ?, ?, ?)');
$statuses = ['done', 'done', 'done', 'done', 'cancelled'];
for ($day = 365; $day >= 0; $day--) {
    $count = random_int(0, 6) + ($day < 30 ? 2 : 0);
    for ($j = 0; $j < $count; $j++) {
        $ts = strtotime("-{$day} days") - random_int(0, 10 * 3600);
        if ($ts > time()) {
            $ts = time() - random_int(60, 3600);
        }
        $status = $day === 0 && $j < 3 ? 'new' : $statuses[array_rand($statuses)];
        $insertOrder->execute([$clientIds[array_rand($clientIds)], random_int(15, 120) * 100, $status, date('Y-m-d H:i:s', $ts)]);
    }
}

$pdo->commit();
echo "Demo data added.\n";
