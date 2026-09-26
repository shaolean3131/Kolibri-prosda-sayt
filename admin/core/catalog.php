<?php
defined('KOLIBRI') or exit;

const PRODUCT_LABELS = [
    'new'  => 'Новинка',
    'hit'  => 'Хит',
    'top'  => 'Топ',
    'sale' => 'Акция',
    'gift' => 'Подарок',
];

const PRODUCT_SECTIONS = [
    'composition' => 'Состав и характеристики',
    'display'     => 'Время и место отображения',
    'weighted'    => 'Весовой товар',
    'stock'       => 'Остатки',
    'limits'      => 'Имеются ограничения',
    'service'     => 'Сервисный товар',
];

const WEEKDAYS = [1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб', 7 => 'Вс'];

/** Categories (by sort order) with their products. */
function catalog_tree(): array
{
    $categories = db()->query('SELECT * FROM categories ORDER BY sort, id')->fetchAll();
    $products   = db()->query('SELECT * FROM products ORDER BY sort, id')->fetchAll();

    $byCategory = [];
    foreach ($products as $product) {
        $byCategory[$product['category_id']][] = $product;
    }
    foreach ($categories as &$category) {
        $category['products'] = $byCategory[$category['id']] ?? [];
    }
    return $categories;
}

function product_extra_defaults(): array
{
    return [
        'composition' => ['on' => false, 'text' => '', 'height' => '', 'width' => ''],
        'display'     => ['on' => false, 'days' => [1, 2, 3, 4, 5, 6, 7], 'from' => '', 'to' => '', 'site' => true, 'app' => true, 'qr' => true],
        'weighted'    => ['on' => false, 'step' => '100', 'unit' => 'г'],
        'stock'       => ['on' => false, 'qty' => 0],
        'limits'      => ['on' => false, 'min' => 1, 'max' => ''],
        'service'     => ['on' => false],
    ];
}

/** Decodes and normalises the product "extra" JSON, keeping only known keys. */
function product_extra(?string $json): array
{
    $input = json_decode((string) $json, true);
    $input = is_array($input) ? $input : [];
    $extra = product_extra_defaults();

    foreach ($extra as $section => $fields) {
        $given = is_array($input[$section] ?? null) ? $input[$section] : [];
        foreach ($fields as $field => $default) {
            if (!array_key_exists($field, $given)) {
                continue;
            }
            $value = $given[$field];
            $extra[$section][$field] = match (true) {
                is_bool($default)  => (bool) $value,
                is_int($default)   => (int) $value,
                is_array($default) => array_values(array_intersect(array_map('intval', (array) $value), array_keys(WEEKDAYS))),
                default            => mb_substr(trim((string) $value), 0, $field === 'text' ? 2000 : 20),
            };
        }
    }
    return $extra;
}

function product_labels(string $stored): array
{
    return array_values(array_intersect(explode(',', $stored), array_keys(PRODUCT_LABELS)));
}

function format_price(?string $price): string
{
    if ($price === null || $price === '') {
        return '';
    }
    $value = (float) $price;
    return number_format($value, fmod($value, 1.0) === 0.0 ? 0 : 2, ',', ' ');
}

/** Data the product modal needs to edit a product. */
function product_json(array $product): string
{
    return json_encode([
        'id'          => (int) $product['id'],
        'category_id' => (int) $product['category_id'],
        'name'        => $product['name'],
        'description' => (string) $product['description'],
        'price'       => format_price($product['price']),
        'old_price'   => format_price($product['old_price']),
        'unit_amount' => $product['unit_amount'],
        'unit_name'   => $product['unit_name'],
        'image'       => upload_url($product['image']),
    ], JSON_UNESCAPED_UNICODE);
}

function next_sort(string $table, string $where = '1 = 1', array $params = []): int
{
    $stmt = db()->prepare("SELECT COALESCE(MAX(sort), 0) + 1 FROM {$table} WHERE {$where}");
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/** Renders the fields shown when a product section switch is on. */
function render_section_fields(string $section, array $values): string
{
    $input = static function (string $field, string $label, string $type = 'text', string $suffix = '') use ($section, $values): string {
        $value = $values[$field];
        return '<label class="mini-field"><span>' . e($label) . '</span>'
            . '<input class="mini-input" type="' . $type . '" data-extra="' . $section . '.' . $field . '" value="' . e($value) . '"'
            . ($type === 'number' ? ' min="0" inputmode="numeric"' : '') . '>'
            . ($suffix ? '<em>' . e($suffix) . '</em>' : '') . '</label>';
    };
    $check = static function (string $field, string $label) use ($section, $values): string {
        return '<label class="check"><input type="checkbox" data-extra="' . $section . '.' . $field . '"' . ($values[$field] ? ' checked' : '') . '><span></span>' . e($label) . '</label>';
    };

    switch ($section) {
        case 'composition':
            return '<label class="mini-field mini-field--wide"><span>Состав</span>'
                . '<textarea class="mini-input" rows="2" data-extra="composition.text" placeholder="Например: 5 веток хризантемы, лагурус, упаковка">' . e($values['text']) . '</textarea></label>'
                . $input('height', 'Высота', 'number', 'см') . $input('width', 'Ширина', 'number', 'см');

        case 'display':
            $days = '';
            foreach (WEEKDAYS as $n => $day) {
                $days .= '<label class="day-chip"><input type="checkbox" data-extra="display.days" value="' . $n . '"'
                    . (in_array($n, $values['days'], true) ? ' checked' : '') . '><span>' . $day . '</span></label>';
            }
            return '<div class="mini-field mini-field--wide"><span>Дни</span><div class="day-chips">' . $days . '</div></div>'
                . $input('from', 'С', 'time') . $input('to', 'До', 'time')
                . '<div class="mini-field mini-field--wide"><span>Где показывать</span><div class="checks">'
                . $check('site', 'Сайт') . $check('app', 'Приложение') . $check('qr', 'QR-меню') . '</div></div>';

        case 'weighted':
            $options = '';
            foreach (['г', 'кг', 'шт'] as $unit) {
                $options .= '<option' . ($values['unit'] === $unit ? ' selected' : '') . '>' . $unit . '</option>';
            }
            return $input('step', 'Шаг', 'number')
                . '<label class="mini-field"><span>Единица</span><select class="mini-input" data-extra="weighted.unit">' . $options . '</select></label>';

        case 'stock':
            return $input('qty', 'В наличии', 'number', 'шт')
                . '<p class="mini-hint">Когда остаток закончится, товар автоматически скроется с сайта.</p>';

        case 'limits':
            return $input('min', 'Минимум в заказе', 'number') . $input('max', 'Максимум в заказе', 'number');

        case 'service':
            return '<p class="mini-hint">Не показывается в каталоге. Используйте для упаковки, открытки или доставки, которые добавляются к заказу.</p>';
    }
    return '';
}
