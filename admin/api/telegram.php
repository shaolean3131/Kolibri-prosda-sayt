<?php
require dirname(__DIR__) . '/core/bootstrap.php';
require_once dirname(__DIR__) . '/core/telegram_app.php';

api_guard();

$token = (string) setting('telegram_token', '');
if (!preg_match('/^\d+:[\w-]+$/', $token)) {
    json_response(['error' => 'Сначала вставьте токен бота из @BotFather.'], 422);
}

switch (input_string('action', 20)) {
    case 'find':
        $res = telegram_api('getUpdates', ['limit' => 100]);
        if (($res['error_code'] ?? 0) === 409) {
            // a webhook is set (Mini App connected): use the chats it has seen
            json_response(['chats' => array_values(array_reverse(json_decode((string) setting('tg_recent_chats', '[]'), true) ?: []))]);
        }
        if (!$res || empty($res['ok'])) {
            json_response(['error' => 'Telegram не ответил. Проверьте токен и доступ сервера в интернет.'], 502);
        }
        $chats = [];
        foreach ($res['result'] as $update) {
            $message = $update['message'] ?? $update['my_chat_member'] ?? $update['channel_post'] ?? null;
            $chat = $message['chat'] ?? null;
            if ($chat) {
                $chats[(string) $chat['id']] = [
                    'id'    => (string) $chat['id'],
                    'title' => $chat['title'] ?? trim(($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? '')) ?: ('@' . ($chat['username'] ?? $chat['id'])),
                    'type'  => $chat['type'] === 'private' ? 'Личный чат' : 'Группа',
                ];
            }
        }
        json_response(['chats' => array_values($chats)]);

    case 'test':
        if (!telegram_chat_ids()) {
            json_response(['error' => 'Добавьте хотя бы один ID чата.'], 422);
        }
        $sent = telegram_send("🌸 <b>Колибри</b>\nТестовое сообщение: уведомления о заказах работают.", telegram_chat_ids());
        if ($sent === 0) {
            json_response(['error' => 'Не удалось отправить. Проверьте токен и ID чатов.'], 502);
        }
        json_response(['ok' => true, 'sent' => $sent]);

    case 'connect':
        // bot menu button → Mini App, and a webhook for /start and chat discovery
        $appUrl = tg_app_url();
        if ($appUrl === '') {
            json_response(['error' => 'Telegram открывает приложения только по https. Укажите адрес сайта с https:// в поле «Адрес сайта».'], 422);
        }
        $secret = (string) setting('tg_webhook_secret', '');
        if ($secret === '') {
            $secret = bin2hex(random_bytes(24));
            save_setting('tg_webhook_secret', $secret);
        }
        $hook = telegram_api('setWebhook', [
            'url'             => substr($appUrl, 0, -3) . 'api/telegram-webhook.php',
            'secret_token'    => $secret,
            'allowed_updates' => json_encode(['message', 'my_chat_member']),
        ]);
        $menu = telegram_api('setChatMenuButton', ['menu_button' => json_encode([
            'type' => 'web_app', 'text' => mb_substr((string) setting('tg_menu_text', '') ?: 'Заказать букет', 0, 30), 'web_app' => ['url' => $appUrl],
        ], JSON_UNESCAPED_UNICODE)]);
        if (empty($hook['ok']) || empty($menu['ok'])) {
            json_response(['error' => 'Telegram не принял настройки: ' . ($hook['description'] ?? $menu['description'] ?? 'нет ответа')], 502);
        }
        $me = telegram_api('getMe', []);
        save_setting('tg_bot_username', (string) ($me['result']['username'] ?? ''));
        json_response(['ok' => true, 'url' => $appUrl, 'bot' => (string) ($me['result']['username'] ?? '')]);
}

json_response(['error' => 'unknown action'], 400);
