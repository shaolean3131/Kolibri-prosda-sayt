<?php
defined('KOLIBRI') or exit;

const PROMO_TYPES = [
    'percent' => 'Скидка в %',
    'fixed'   => 'Скидка в рублях',
    'gift'    => 'Подарок к заказу',
    'banner'  => 'Только баннер (без скидки)',
];

const PROMO_BANNERS = [
    'banner_app'     => ['title' => 'Приложение',          'size' => '750x320'],
    'banner_mobile'  => ['title' => 'Сайт · моб. версия',  'size' => '750x320'],
    'banner_desktop' => ['title' => 'Сайт · десктоп',      'size' => '2360x710'],
];

/**
 * Checkbox settings of a promotion. 'select' adds an inline choice to the
 * label; 'hint' shows a "?" tooltip.
 */
function promo_option_list(): array
{
    return [
        'once_per'      => ['label' => 'Только один раз в', 'select' => ['day' => 'день', 'week' => 'неделю', 'month' => 'месяц']],
        'nth_order'     => ['label' => 'Только на', 'select' => ['1' => 'первый заказ', '2' => 'второй заказ', '3' => 'третий заказ'], 'after' => 'каждому'],
        'auto'          => ['label' => 'Применять автоматически', 'hint' => 'Скидка применится в корзине без ввода промокода.'],
        'once_each'     => ['label' => 'Только один раз каждому'],
        'pickup_only'   => ['label' => 'Только самовывоз'],
        'delivery_only' => ['label' => 'Только на доставку'],
        'app_only'      => ['label' => 'Только через приложение'],
        'site_only'     => ['label' => 'Только через сайт'],
        'auth'          => ['label' => 'Требовать авторизацию'],
        'no_stack'      => ['label' => 'Не увеличивать установленную скидку'],
        'no_crossed'    => ['label' => 'Не действует при заказе товаров с зачёркнутой ценой'],
        'free_delivery' => ['label' => 'Установить бесплатную доставку', 'hint' => 'Доставка по этой акции будет бесплатной.'],
        'active_only'   => ['label' => 'Отображать только, когда акция активна', 'hint' => 'Баннер скрывается вне сроков акции.'],
    ];
}

/** Normalised options from JSON or form input: [key => bool|string]. */
function promo_options(array $input): array
{
    $options = [];
    foreach (promo_option_list() as $key => $option) {
        $options[$key] = !empty($input[$key]);
        if (isset($option['select'])) {
            $choice = (string) ($input[$key . '_value'] ?? '');
            $options[$key . '_value'] = isset($option['select'][$choice]) ? $choice : array_key_first($option['select']);
        }
    }
    $options['gift_product_id'] = (int) ($input['gift_product_id'] ?? 0);
    return $options;
}

/** "SPRING10, ROSES" -> ['SPRING10', 'ROSES'] */
function promo_codes(string $raw): array
{
    $codes = preg_split('/[\s,;]+/u', mb_strtoupper($raw), -1, PREG_SPLIT_NO_EMPTY);
    $codes = array_map(static fn ($c) => mb_substr(preg_replace('/[^\p{L}\p{N}_-]/u', '', $c), 0, 40), $codes);
    return array_values(array_unique(array_filter($codes)));
}

function promo_period_label(array $promo): string
{
    if (!$promo['starts_at'] && !$promo['ends_at']) {
        return 'Бессрочный';
    }
    $fmt = static fn (?string $d): string => $d ? date('d.m.Y', strtotime($d)) : '…';
    return $fmt($promo['starts_at']) . ' – ' . $fmt($promo['ends_at']);
}

function promo_badge(array $promo): string
{
    return match ($promo['type']) {
        'percent' => '−' . format_number((float) $promo['value']) . '%',
        'fixed'   => '−' . format_number((float) $promo['value']) . ' ' . config('currency', '₽'),
        'gift'    => 'Подарок',
        default   => 'Баннер',
    };
}

function format_number(float $value): string
{
    return number_format($value, fmod($value, 1.0) === 0.0 ? 0 : 2, ',', ' ');
}

/** Promotion state for the edit modal. */
function promo_json(array $promo): string
{
    $data = [
        'id'           => (int) $promo['id'],
        'name'         => $promo['name'],
        'description'  => (string) $promo['description'],
        'starts_at'    => $promo['starts_at'] ? date('Y-m-d\TH:i', strtotime($promo['starts_at'])) : '',
        'ends_at'      => $promo['ends_at'] ? date('Y-m-d\TH:i', strtotime($promo['ends_at'])) : '',
        'type'         => $promo['type'],
        'value'        => (float) $promo['value'] ? format_number((float) $promo['value']) : '',
        'min_order'    => (float) $promo['min_order'] ? format_number((float) $promo['min_order']) : '',
        'applies_to'   => $promo['applies_to'],
        'category_ids' => array_map('intval', array_filter(explode(',', $promo['category_ids']))),
        'promo_codes'  => implode(', ', json_decode((string) $promo['promo_codes'], true) ?: []),
        'options'      => promo_options(json_decode((string) $promo['options'], true) ?: []),
    ];
    foreach (array_keys(PROMO_BANNERS) as $banner) {
        $data[$banner] = upload_url($promo[$banner]);
    }
    return json_encode($data, JSON_UNESCAPED_UNICODE);
}
