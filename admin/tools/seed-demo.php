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
$insertClient = $pdo->prepare('INSERT INTO clients (name, phone, created_at) VALUES (?, ?, ?)');
for ($i = 0; $i < 160; $i++) {
    $created = date('Y-m-d H:i:s', time() - random_int(0, 365 * 86400));
    $insertClient->execute([$names[array_rand($names)], '+7 9' . random_int(100000000, 999999999), $created]);
    $clientIds[] = (int) $pdo->lastInsertId();
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
