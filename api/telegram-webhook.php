<?php
/* Bot webhook: /start greets the customer with the Mini App button. */
define('KOLIBRI_SITE', true);
require dirname(__DIR__) . '/admin/core/bootstrap.php';
require_once dirname(__DIR__) . '/admin/core/telegram_app.php';

$secret = (string) setting('tg_webhook_secret', '');
$given  = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $secret === '' || !is_string($given) || !hash_equals($secret, $given)) {
    http_response_code(403);
    exit;
}

$update = json_decode((string) file_get_contents('php://input', false, null, 0, 1 << 20), true);
if (is_array($update)) {
    tg_handle_update($update);
}
json_response(['ok' => true]);
