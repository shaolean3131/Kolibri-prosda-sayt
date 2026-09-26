<?php
require dirname(__DIR__) . '/core/bootstrap.php';
require_once dirname(__DIR__) . '/core/orders.php';

api_guard();

$token = (string) setting('telegram_token', '');
if (!preg_match('/^\d+:[\w-]+$/', $token)) {
    json_response(['error' => 'Сначала вставьте токен бота из @BotFather.'], 422);
}

switch (input_string('action', 20)) {
    case 'find':
        $res = telegram_api('getUpdates', ['limit' => 100]);
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
}

json_response(['error' => 'unknown action'], 400);
