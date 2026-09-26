<?php
require dirname(__DIR__) . '/core/bootstrap.php';
require dirname(__DIR__) . '/core/settings.php';

api_guard();

$fields = settings_fields();
$name   = input_string('name', 100);
$value  = is_string($_POST['value'] ?? null) ? $_POST['value'] : '';

if (!isset($fields[$name])) {
    json_response(['error' => 'Неизвестная настройка'], 422);
}
$clean = settings_clean($fields[$name], $value);
if ($clean === null) {
    json_response(['error' => 'Некорректное значение'], 422);
}

save_setting($name, $clean);
json_response(['ok' => true]);
