<?php
/* "Мои заказы" in the Telegram Mini App: orders of the signed-in Telegram user. */
define('KOLIBRI_SITE', true);
require dirname(__DIR__) . '/admin/core/bootstrap.php';
require_once dirname(__DIR__) . '/admin/core/telegram_app.php';

$user = $_SERVER['REQUEST_METHOD'] === 'POST' ? tg_request_user() : null;
if ($user === null) {
    json_response(['error' => 'Откройте приложение из Telegram, чтобы увидеть свои заказы.'], 401);
}

json_response(['orders' => tg_user_orders($user['id'])]);
