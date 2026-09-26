<?php
defined('KOLIBRI') or exit;

/**
 * Filter chips above admin tables (clients, orders). A definition is
 * ['title' => ..., 'type' => text|date|range|select|multi, 'options' => [...]].
 * Values come from the query string: date -> key_from/key_to,
 * range -> key_min/key_max, multi -> key[] and the rest -> key.
 */
function filter_values(array $defs, array $query): array
{
    $values = [];
    foreach ($defs as $key => $def) {
        if ($def['type'] === 'multi') {
            $picked = array_values(array_intersect((array) ($query[$key] ?? []), array_map('strval', array_keys($def['options']))));
            if ($picked) {
                $values[$key] = $picked;
            }
            continue;
        }
        $fields = match ($def['type']) {
            'date'  => ["{$key}_from", "{$key}_to"],
            'range' => ["{$key}_min", "{$key}_max"],
            default => [$key],
        };
        foreach ($fields as $field) {
            $value = $query[$field] ?? '';
            if (!is_string($value) || trim($value) === '') {
                continue;
            }
            $value = trim($value);
            $valid = match ($def['type']) {
                'date'   => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value),
                'range'  => is_numeric($value) && abs((float) $value) < 1e12,
                'select' => isset($def['options'][$value]),
                default  => mb_strlen($value) <= 40,
            };
            if ($valid) {
                $values[$field] = $value;
            }
        }
    }
    return $values;
}

/** Short text shown on an active chip, e.g. "от 3" or "Женский". */
function filter_summary(string $key, array $def, array $values): string
{
    $fmt = static fn (string $date): string => date('d.m.y', strtotime($date));
    switch ($def['type']) {
        case 'date':
            $from = $values["{$key}_from"] ?? null;
            $to   = $values["{$key}_to"] ?? null;
            return trim(($from ? 'с ' . $fmt($from) : '') . ' ' . ($to ? 'по ' . $fmt($to) : ''));
        case 'range':
            $min = $values["{$key}_min"] ?? null;
            $max = $values["{$key}_max"] ?? null;
            return trim(($min !== null ? 'от ' . $min : '') . ' ' . ($max !== null ? 'до ' . $max : ''));
        case 'select':
            return isset($values[$key]) ? $def['options'][$values[$key]] : '';
        case 'multi':
            return implode(', ', array_map(static fn ($v) => $def['options'][$v], $values[$key] ?? []));
        default:
            return $values[$key] ?? '';
    }
}

/** SQL for a date filter on $column: [conditions, params]. */
function filter_date_sql(string $key, string $column, array $values): array
{
    $sql = [];
    $params = [];
    if (isset($values["{$key}_from"])) {
        $sql[] = "{$column} >= ?";
        $params[] = $values["{$key}_from"] . ' 00:00:00';
    }
    if (isset($values["{$key}_to"])) {
        $sql[] = "{$column} <= ?";
        $params[] = $values["{$key}_to"] . ' 23:59:59';
    }
    return [$sql, $params];
}

/**
 * SQL for a numeric range. Numbers are inlined (already validated as
 * numeric): PDO binds every value as a string, and SQLite never matches
 * COUNT() >= '3'.
 */
function filter_range_sql(string $key, string $expr, array $values): array
{
    $sql = [];
    if (isset($values["{$key}_min"])) {
        $sql[] = "{$expr} >= " . (float) $values["{$key}_min"];
    }
    if (isset($values["{$key}_max"])) {
        $sql[] = "{$expr} <= " . (float) $values["{$key}_max"];
    }
    return $sql;
}

/** SQL matching a phone column against the digits typed in a filter. */
function filter_phone_sql(string $column, string $typed): array
{
    $digits = preg_replace('/\D+/', '', $typed);
    return [
        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE ?",
        '%' . ($digits !== '' ? $digits : $typed) . '%',
    ];
}

/** The chip row (a GET form). $hidden: extra query params to keep. */
function render_filters(array $defs, array $values, array $hidden, string $exportUrl, string $clearUrl): string
{
    $active = 0;
    ob_start();
    foreach ($defs as $key => $def):
        $summary = filter_summary($key, $def, $values);
        $active += $summary !== '' ? 1 : 0; ?>
        <div class="dropdown filter" data-dropdown>
            <button class="filter__btn<?= $summary !== '' ? ' is-active' : '' ?>" type="button" data-dropdown-toggle>
                <span><?= e($def['title']) ?><?php if ($summary !== ''): ?>: <b><?= e($summary) ?></b><?php endif; ?></span>
                <?= icon('chevron', 'icon filter__chevron') ?>
            </button>
            <div class="dropdown-menu filter__menu">
                <div class="filter__title"><?= e($def['title']) ?></div>
                <?php if ($def['type'] === 'text'): ?>
                    <input class="mini-input" type="search" name="<?= $key ?>" value="<?= e($values[$key] ?? '') ?>" placeholder="<?= e($def['placeholder'] ?? '') ?>" inputmode="<?= e($def['inputmode'] ?? 'text') ?>">
                <?php elseif ($def['type'] === 'date' || $def['type'] === 'range'):
                    [$a, $b, $la, $lb, $type] = $def['type'] === 'date' ? ['from', 'to', 'С', 'По', 'date'] : ['min', 'max', 'От', 'До', 'number']; ?>
                    <div class="filter__pair">
                        <label class="mini-field"><span><?= $la ?></span><input class="mini-input" type="<?= $type ?>" min="0" name="<?= $key ?>_<?= $a ?>" value="<?= e($values["{$key}_{$a}"] ?? '') ?>"></label>
                        <label class="mini-field"><span><?= $lb ?></span><input class="mini-input" type="<?= $type ?>" min="0" name="<?= $key ?>_<?= $b ?>" value="<?= e($values["{$key}_{$b}"] ?? '') ?>"></label>
                    </div>
                <?php else: ?>
                    <div class="filter__options">
                        <?php foreach ($def['options'] as $value => $label):
                            $checked = $def['type'] === 'multi' ? in_array((string) $value, $values[$key] ?? [], true) : ($values[$key] ?? '') === (string) $value; ?>
                            <?php if ($def['type'] === 'multi'): ?>
                                <label class="check"><input type="checkbox" name="<?= $key ?>[]" value="<?= e($value) ?>"<?= $checked ? ' checked' : '' ?>><span></span><?= e($label) ?></label>
                            <?php else: ?>
                                <label class="radio"><input type="radio" name="<?= $key ?>" value="<?= e($value) ?>"<?= $checked ? ' checked' : '' ?>><span></span><?= e($label) ?></label>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="filter__actions">
                    <button class="btn btn--light btn--sm" type="button" data-filter-reset>Сбросить</button>
                    <button class="btn btn--primary btn--sm" type="submit">Применить</button>
                </div>
            </div>
        </div>
    <?php endforeach;
    $chips = (string) ob_get_clean();

    $out = '<form class="filters reveal" style="--i: 1" method="get" action="index.php" data-filters>';
    foreach ($hidden as $name => $value) {
        $out .= '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
    }
    $out .= '<button class="btn btn--light btn--sm filters__toggle" type="button" data-filters-toggle>Фильтры'
        . ($active ? '<span class="badge badge--blue">' . $active . '</span>' : '') . icon('chevron', 'icon filter__chevron') . '</button>';
    $out .= $chips;
    $out .= '<a class="btn btn--light btn--sm filters__download" href="' . e($exportUrl) . '" download>' . icon('download', 'icon icon--sm') . 'Скачать весь список</a>';
    if ($values) {
        $out .= '<a class="btn btn--light btn--sm filters__clear" href="' . e($clearUrl) . '">' . icon('trash', 'icon icon--sm') . 'Очистить фильтры</a>';
    }
    return $out . '</form>';
}
