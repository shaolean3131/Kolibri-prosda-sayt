<?php
defined('KOLIBRI') or exit;

/** Tabs shown directly; the rest go under "Еще". */
const SETTINGS_MAIN_TABS = ['main', 'payment', 'preorders', 'delivery'];

const WEEKDAYS_FULL = [1 => 'Понедельник', 2 => 'Вторник', 3 => 'Среда', 4 => 'Четверг', 5 => 'Пятница', 6 => 'Суббота', 7 => 'Воскресенье'];

/**
 * Settings screen schema. Field types: switch (optionally with 'reveal'
 * field shown when on), textarea, code, text, number, select, schedule,
 * notice. Every value is stored in the settings table under its name.
 */
function settings_tabs(): array
{
    return [
        'main' => ['title' => 'Главные', 'fields' => [
            ['type' => 'switch', 'name' => 'shop_active', 'default' => '1', 'label' => 'Заведение активно',
             'hint' => 'Отключение скроет это заведение в приложении и на сайте'],
            ['type' => 'switch', 'name' => 'reviews_show_all', 'default' => '1', 'label' => 'Отображать все отзывы',
             'hint' => 'Если отключить, клиенты будут видеть только свои отзывы и ваши ответы на них.'],
            ['type' => 'switch', 'name' => 'text_banner', 'default' => '0', 'label' => 'Текстовый баннер',
             'hint' => 'Информируйте ваших клиентов об изменениях в условиях заказа.',
             'reveal' => ['type' => 'textarea', 'name' => 'text_banner_text', 'placeholder' => 'Например: 8 марта доставка работает с 6:00']],
            ['type' => 'textarea', 'name' => 'order_message', 'title' => 'Сообщение после заказа',
             'placeholder' => 'Вы получите уведомление, когда наш оператор его примет.',
             'hint' => "Опишите пользователю, когда будет принят его заказ:\nему перезвонят или он получит СМС-оповещение?"],
            ['type' => 'textarea', 'name' => 'review_message', 'title' => 'Сообщение в форме отзыва',
             'hint' => 'Сообщение отображается перед формой, в которой пользователь пишет отзыв.'],
        ]],
        'payment' => ['title' => 'Формы оплаты', 'fields' => [
            ['type' => 'switch', 'name' => 'pay_cash', 'default' => '1', 'label' => 'Наличными при получении',
             'hint' => 'Оплата курьеру или в точке самовывоза.'],
            ['type' => 'switch', 'name' => 'pay_card', 'default' => '1', 'label' => 'Картой при получении',
             'hint' => 'Курьер или продавец принимает карту через терминал.'],
            ['type' => 'switch', 'name' => 'pay_transfer', 'default' => '0', 'label' => 'Переводом по номеру телефона (СБП)',
             'hint' => 'Клиент увидит реквизиты после оформления заказа.',
             'reveal' => ['type' => 'textarea', 'name' => 'pay_transfer_details', 'placeholder' => 'Например: +7 900 000-00-00, Сбербанк, Анна К.']],
            ['type' => 'switch', 'name' => 'pay_change', 'default' => '1', 'label' => 'Спрашивать, с какой суммы нужна сдача',
             'hint' => 'В форме заказа появится поле для суммы, с которой подготовить сдачу.'],
        ]],
        'preorders' => ['title' => 'Предзаказы', 'fields' => [
            ['type' => 'switch', 'name' => 'preorders_enabled', 'default' => '1', 'label' => 'Принимать предзаказы',
             'hint' => 'Клиент сможет выбрать дату и время получения заказа.'],
            ['type' => 'switch', 'name' => 'asap_enabled', 'default' => '1', 'label' => 'Разрешить «Как можно скорее»',
             'hint' => 'Заказ без выбора времени — соберём и доставим сразу.'],
            ['type' => 'number', 'name' => 'preorder_min_minutes', 'default' => '60', 'label' => 'Минимальное время на сборку, мин',
             'hint' => 'Раньше этого времени заказ оформить нельзя.'],
            ['type' => 'number', 'name' => 'preorder_max_days', 'default' => '14', 'label' => 'На сколько дней вперёд',
             'hint' => 'Как далеко в будущее можно выбрать дату.'],
            ['type' => 'select', 'name' => 'preorder_step', 'default' => '30', 'label' => 'Шаг выбора времени',
             'hint' => 'Интервалы в списке времени получения.', 'options' => ['15' => '15 минут', '30' => '30 минут', '60' => '1 час']],
        ]],
        'delivery' => ['title' => 'Точки и зоны доставки', 'fields' => [
            ['type' => 'switch', 'name' => 'pickup_enabled', 'default' => '1', 'label' => 'Самовывоз',
             'hint' => 'Клиенты смогут забрать заказ сами из пункта самовывоза.'],
            ['type' => 'pickup_points'],
            ['type' => 'switch', 'name' => 'delivery_enabled', 'default' => '1', 'label' => 'Доставка',
             'hint' => 'Курьерская доставка по адресу клиента.'],
            ['type' => 'number', 'name' => 'delivery_price', 'default' => '0', 'label' => 'Стоимость доставки, ₽',
             'hint' => '0 — доставка бесплатная.'],
            ['type' => 'number', 'name' => 'delivery_free_from', 'default' => '0', 'label' => 'Бесплатная доставка от, ₽',
             'hint' => 'Сумма заказа, с которой доставка бесплатна. 0 — не использовать.'],
            ['type' => 'number', 'name' => 'delivery_min_order', 'default' => '0', 'label' => 'Минимальная сумма на доставку, ₽',
             'hint' => 'Меньше этой суммы доставку оформить нельзя. 0 — без ограничения.'],
            ['type' => 'text', 'name' => 'delivery_city', 'default' => 'Новокузнецк', 'label' => 'Город',
             'hint' => 'Подставляется в начало адреса доставки.'],
            ['type' => 'text', 'name' => 'map_center', 'default' => '53.7557, 87.1099', 'label' => 'Центр карты',
             'hint' => 'Широта и долгота центра города для карты доставки.'],
            ['type' => 'textarea', 'name' => 'delivery_info', 'title' => 'Условия доставки',
             'placeholder' => 'Например: доставляем по Новокузнецку за 1–2 часа.',
             'hint' => 'Показывается клиенту, когда он выбирает доставку.'],
        ]],
        'hours' => ['title' => 'Время работы', 'fields' => [
            ['type' => 'schedule', 'name' => 'schedule'],
        ]],
        'notifications' => ['title' => 'Уведомления', 'fields' => [
            ['type' => 'switch', 'name' => 'telegram_enabled', 'default' => '1', 'label' => 'Заказы в Telegram',
             'hint' => 'Каждый новый заказ сразу придёт сообщением в Telegram.'],
            ['type' => 'text', 'name' => 'telegram_token', 'label' => 'Токен бота',
             'hint' => 'Создайте бота в @BotFather и вставьте сюда его токен.'],
            ['type' => 'text', 'name' => 'telegram_chat_ids', 'label' => 'ID чатов',
             'hint' => 'Кому отправлять заказы, через запятую. Проще всего — кнопка «Найти чаты».'],
            ['type' => 'telegram'],
        ]],
        'legal' => ['title' => 'Юридическая информация', 'fields' => [
            ['type' => 'text', 'name' => 'legal_name', 'label' => 'Название организации или ИП'],
            ['type' => 'text', 'name' => 'legal_inn', 'label' => 'ИНН'],
            ['type' => 'text', 'name' => 'legal_ogrn', 'label' => 'ОГРН / ОГРНИП'],
            ['type' => 'text', 'name' => 'legal_address', 'label' => 'Юридический адрес'],
            ['type' => 'text', 'name' => 'contact_phone', 'label' => 'Телефон для клиентов'],
            ['type' => 'text', 'name' => 'contact_email', 'label' => 'E-mail для клиентов'],
            ['type' => 'textarea', 'name' => 'legal_privacy', 'title' => 'Политика конфиденциальности', 'rows' => 8],
            ['type' => 'textarea', 'name' => 'legal_terms', 'title' => 'Пользовательское соглашение', 'rows' => 8],
        ]],
        'snippets' => ['title' => 'Сниппеты', 'fields' => [
            ['type' => 'code', 'name' => 'snippet_head', 'title' => 'Код в <head>',
             'hint' => 'Например, счётчик Яндекс Метрики или код подтверждения сайта. Добавляется на все страницы сайта.'],
            ['type' => 'code', 'name' => 'snippet_body', 'title' => 'Код перед </body>',
             'hint' => 'Виджеты чатов и другие скрипты, которые нужно загрузить в конце страницы.'],
        ]],
    ];
}

/** Every savable field (including "reveal" sub-fields), keyed by name. */
function settings_fields(): array
{
    $fields = [];
    foreach (settings_tabs() as $tab) {
        foreach ($tab['fields'] as $field) {
            if (isset($field['name'])) {
                $fields[$field['name']] = $field;
            }
            if (isset($field['reveal'])) {
                $fields[$field['reveal']['name']] = $field['reveal'];
            }
        }
    }
    return $fields;
}

function default_schedule(): array
{
    return array_fill_keys(range(1, 7), ['open' => true, 'from' => '09:00', 'to' => '21:00', 'allday' => false]);
}

function schedule_value(?string $json): array
{
    $input    = json_decode((string) $json, true);
    $schedule = default_schedule();
    foreach ($schedule as $day => $defaults) {
        $given = is_array($input[$day] ?? null) ? $input[$day] : [];
        $time  = static fn ($v, $d) => is_string($v) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : $d;
        $schedule[$day] = [
            'open'   => (bool) ($given['open'] ?? $defaults['open']),
            'from'   => $time($given['from'] ?? null, $defaults['from']),
            'to'     => $time($given['to'] ?? null, $defaults['to']),
            'allday' => (bool) ($given['allday'] ?? $defaults['allday']),
        ];
    }
    return $schedule;
}

/** Validates a submitted value for a field; returns null when invalid. */
function settings_clean(array $field, string $value): ?string
{
    return match ($field['type']) {
        'switch'   => $value === '1' ? '1' : '0',
        'number'   => is_numeric($value) ? (string) max(0, (int) $value) : null,
        'select'   => isset($field['options'][$value]) ? $value : null,
        'schedule' => json_encode(schedule_value($value)),
        'code'     => mb_substr($value, 0, 20000),
        'textarea' => mb_substr($value, 0, 20000),
        default    => mb_substr(trim($value), 0, 500),
    };
}

function render_pickup_point(array $point): string
{
    $coords = $point['lat'] !== '' ? $point['lat'] . ', ' . $point['lng'] : '';
    return '<div class="point' . ($point['is_active'] ? '' : ' is-off') . '" data-point="' . (int) $point['id'] . '">'
        . '<input class="set-input point__address" data-f="address" value="' . e($point['address']) . '" placeholder="Адрес, например: Новокузнецк, Тореза 42а/1">'
        . '<input class="set-input point__hours" data-f="hours" value="' . e($point['hours']) . '" placeholder="Часы работы">'
        . '<input class="set-input point__coords" data-f="coords" value="' . e($coords) . '" placeholder="Координаты" inputmode="decimal">'
        . '<label class="switch switch--md" title="Показывать клиентам"><input type="checkbox" data-f="is_active"' . ($point['is_active'] ? ' checked' : '') . '><span class="switch__track"></span></label>'
        . '<button class="tool tool--danger" type="button" title="Удалить" data-point-delete>' . icon('trash') . '</button>'
        . '</div>';
}
