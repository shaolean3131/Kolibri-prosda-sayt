<?php
defined('KOLIBRI') or exit;

/*
 * Telegram Mini App: the storefront opened from the bot's menu button.
 * The customer is identified by Telegram's signed initData, so no
 * cookies or SMS are needed; the bot also tells them about status changes.
 */

require_once __DIR__ . '/orders.php';

const TG_SKINS = TG_SKINS_LABELS;

/** Messages the customer gets in the bot chat when the shop changes the status. */
const TG_STATUS_TEXT = [
    'accepted'   => "✅ Заказ №{id} принят! Мы уже собираем ваш букет.",
    'ready'      => "💐 Заказ №{id} готов.",
    'delivering' => "🚗 Заказ №{id} в пути — курьер скоро будет у вас.",
    'done'       => "🌸 Заказ №{id} доставлен. Спасибо, что выбрали «Колибри»!",
    'cancelled'  => "😔 Заказ №{id} отменён{reason}. Если это ошибка — просто ответьте на это сообщение или позвоните нам.",
];

function tg_token(): string
{
    $token = (string) setting('telegram_token', '');
    return preg_match('/^\d+:[\w-]+$/', $token) ? $token : '';
}

function tg_skin(): string
{
    $skin = is_string($_GET['skin'] ?? null) ? $_GET['skin'] : (string) setting('tg_skin', 'nochnoy');
    return isset(TG_SKINS[$skin]) ? $skin : 'nochnoy';
}

/**
 * Validates Telegram WebApp initData (HMAC-SHA256 with the bot token) and
 * returns the user, or null. Data older than a day is rejected.
 */
function tg_verify_init_data(string $initData, ?string $token = null): ?array
{
    $token = $token ?? tg_token();
    if ($token === '' || $initData === '' || strlen($initData) > 4096) {
        return null;
    }
    parse_str($initData, $fields);
    $hash = is_string($fields['hash'] ?? null) ? $fields['hash'] : '';
    unset($fields['hash']);
    if ($hash === '') {
        return null;
    }
    ksort($fields);
    $check = [];
    foreach ($fields as $key => $value) {
        if (!is_string($value)) {
            return null;
        }
        $check[] = $key . '=' . $value;
    }
    $secret = hash_hmac('sha256', $token, 'WebAppData', true);
    if (!hash_equals(hash_hmac('sha256', implode("\n", $check), $secret), $hash)) {
        return null;
    }
    if ((int) ($fields['auth_date'] ?? 0) < time() - 86400) {
        return null;
    }
    $user = json_decode((string) ($fields['user'] ?? ''), true);
    if (!is_array($user) || !isset($user['id']) || !preg_match('/^\d{1,20}$/', (string) $user['id'])) {
        return null;
    }
    return [
        'id'         => (string) $user['id'],
        'first_name' => mb_substr((string) ($user['first_name'] ?? ''), 0, 60),
        'last_name'  => mb_substr((string) ($user['last_name'] ?? ''), 0, 60),
        'username'   => mb_substr((string) ($user['username'] ?? ''), 0, 60),
    ];
}

/** The Telegram user behind this request (header or POST field), if any. */
function tg_request_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $raw  = $_SERVER['HTTP_X_TG_INIT_DATA'] ?? $_POST['tg_init'] ?? '';
        $user = is_string($raw) ? tg_verify_init_data($raw) : null;
    }
    return $user;
}

/**
 * Storefront API guard: the session CSRF token, or signed Telegram data.
 * Telegram Web shows the app in a cross-site iframe where our session
 * cookie is not sent, so initData stands in for it there.
 */
function storefront_request_valid(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST' && (csrf_valid() || tg_request_user() !== null);
}

/** Public https address of the Mini App, or '' when the site has no https. */
function tg_app_url(): string
{
    $base = trim((string) setting('tg_site_url', ''));
    if ($base === '') {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '' || !is_https()) {
            return '';
        }
        $base = 'https://' . $host . rtrim((string) config('site_url', '/'), '/');
    }
    $base = rtrim($base, '/');
    return str_starts_with($base, 'https://') ? $base . '/tg/' : '';
}

function tg_send_to(string $chatId, string $html, array $extra = []): bool
{
    if (tg_token() === '' || !preg_match('/^-?\d{1,20}$/', $chatId)) {
        return false;
    }
    $res = telegram_api('sendMessage', array_merge([
        'chat_id' => $chatId, 'text' => $html, 'parse_mode' => 'HTML', 'disable_web_page_preview' => 'true',
    ], $extra));
    return !empty($res['ok']);
}

/** Inline keyboard with one button that opens the Mini App. */
function tg_open_button(string $text): array
{
    $url = tg_app_url();
    return $url === '' ? [] : ['reply_markup' => json_encode(['inline_keyboard' => [[['text' => $text, 'web_app' => ['url' => $url]]]]], JSON_UNESCAPED_UNICODE)];
}

/** Links the client record to the Telegram account it ordered from. */
function tg_link_client(int $clientId, array $user): void
{
    db()->prepare('UPDATE clients SET tg_id = ?, tg_username = ? WHERE id = ?')
        ->execute([$user['id'], $user['username'], $clientId]);
}

/** Receipt sent to the customer's chat right after they order. */
function tg_notify_order_placed(int $orderId): void
{
    $order = order_with_items($orderId);
    if (!$order || $order['tg_chat_id'] === '' || setting('tg_notify_status', '1') !== '1') {
        return;
    }
    $h = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $lines = ['🌸 <b>Заказ №' . $order['id'] . ' оформлен</b>', ''];
    foreach ($order['items'] as $item) {
        $lines[] = '• ' . $h($item['name']) . ' × ' . $item['quantity'];
    }
    $lines[] = '';
    $lines[] = ($order['delivery_type'] === 'pickup' ? '🏪 ' : '🚗 ') . $h(order_place_label($order));
    $lines[] = '🕒 ' . $h(order_time_label($order));
    $lines[] = '<b>Итого: ' . price_label((float) $order['total']) . '</b>';
    $lines[] = '';
    $lines[] = 'Мы напишем сюда, когда заказ будет принят.';
    tg_send_to($order['tg_chat_id'], implode("\n", $lines));
}

/** Tells the customer about a status change, if they ordered from Telegram. */
function tg_notify_status(array $order): void
{
    $text = TG_STATUS_TEXT[$order['status']] ?? null;
    if ($text === null || ($order['tg_chat_id'] ?? '') === '' || setting('tg_notify_status', '1') !== '1') {
        return;
    }
    $reason = $order['cancel_reason'] !== '' ? ' (' . htmlspecialchars($order['cancel_reason'], ENT_QUOTES, 'UTF-8') . ')' : '';
    tg_send_to($order['tg_chat_id'], strtr($text, ['{id}' => (string) $order['id'], '{reason}' => $reason]));
}

/** Remembers chats that wrote to the bot, for "Найти чаты" while a webhook is set. */
function tg_remember_chat(array $chat): void
{
    $chats = json_decode((string) setting('tg_recent_chats', '[]'), true) ?: [];
    $id = (string) $chat['id'];
    unset($chats[$id]);
    $chats[$id] = [
        'id'    => $id,
        'title' => $chat['title'] ?? trim(($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? '')) ?: ('@' . ($chat['username'] ?? $id)),
        'type'  => ($chat['type'] ?? '') === 'private' ? 'Личный чат' : 'Группа',
    ];
    save_setting('tg_recent_chats', json_encode(array_slice($chats, -20, null, true), JSON_UNESCAPED_UNICODE));
}

/** Handles one update from the bot webhook. */
function tg_handle_update(array $update): void
{
    $message = $update['message'] ?? $update['my_chat_member'] ?? null;
    $chat = $message['chat'] ?? null;
    if (!is_array($chat) || !isset($chat['id'])) {
        return;
    }
    tg_remember_chat($chat);
    if (($chat['type'] ?? '') !== 'private' || !isset($update['message'])) {
        return;
    }
    $text = trim((string) ($message['text'] ?? ''));
    if (str_starts_with($text, '/start') || $text === '/menu') {
        $welcome = trim((string) setting('tg_welcome', '')) ?: "Здравствуйте! 🌸\nЭто «Колибри — студия цветов».\n\nСоберите букет в приложении — доставим по Новокузнецку или подготовим к самовывозу. О статусе заказа напишем сюда.";
        tg_send_to((string) $chat['id'], htmlspecialchars($welcome, ENT_QUOTES, 'UTF-8'), tg_open_button('💐 ' . (setting('tg_menu_text', '') ?: 'Заказать букет')));
    }
}

/** Orders placed from this Telegram account, newest first. */
function tg_user_orders(string $tgId, int $limit = 10): array
{
    $stmt = db()->prepare('SELECT o.*, p.address AS pickup_address FROM orders o LEFT JOIN pickup_points p ON p.id = o.pickup_point_id WHERE o.tg_chat_id = ? ORDER BY o.id DESC LIMIT ' . max(1, min(50, $limit)));
    $stmt->execute([$tgId]);
    $items = db()->prepare('SELECT name, quantity FROM order_items WHERE order_id = ? ORDER BY id');
    $out = [];
    foreach ($stmt->fetchAll() as $order) {
        $items->execute([$order['id']]);
        $out[] = [
            'id'     => (int) $order['id'],
            'date'   => date('d.m.Y H:i', strtotime($order['created_at'])),
            'status' => $order['status'],
            'label'  => ORDER_STATUSES[$order['status']]['label'] ?? $order['status'],
            'place'  => order_place_label($order),
            'total'  => price_label((float) $order['total']),
            'items'  => array_map(static fn ($i) => $i['name'] . ' × ' . $i['quantity'], $items->fetchAll()),
        ];
    }
    return $out;
}
