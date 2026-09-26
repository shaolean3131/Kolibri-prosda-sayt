<?php
defined('KOLIBRI') or exit;

require_once ADMIN_DIR . '/core/orders.php';

$tabs   = settings_tabs();
$active = is_string($_GET['tab'] ?? null) && isset($tabs[$_GET['tab']]) ? $_GET['tab'] : 'main';
$more   = array_diff(array_keys($tabs), SETTINGS_MAIN_TABS);
$value  = static fn (array $field): string => (string) setting($field['name'], $field['default'] ?? '');

$renderInput = static function (array $field, string $current): string {
    $name = e($field['name']);
    return match ($field['type']) {
        'textarea', 'code' => '<textarea class="set-input' . ($field['type'] === 'code' ? ' set-input--code' : '') . '" name="' . $name . '" rows="' . ($field['rows'] ?? ($field['type'] === 'code' ? 6 : 3)) . '"'
            . ' placeholder="' . e($field['placeholder'] ?? '') . '"' . ($field['type'] === 'code' ? ' spellcheck="false"' : '') . '>' . e($current) . '</textarea>',
        'select' => '<select class="set-input set-input--short" name="' . $name . '">'
            . implode('', array_map(static fn ($v, $l) => '<option value="' . e($v) . '"' . ((string) $v === $current ? ' selected' : '') . '>' . e($l) . '</option>', array_keys($field['options']), $field['options']))
            . '</select>',
        'number' => '<input class="set-input set-input--short" type="number" min="0" name="' . $name . '" value="' . e($current) . '" inputmode="numeric">',
        default  => '<input class="set-input" type="text" name="' . $name . '" value="' . e($current) . '">',
    };
};
?>
<div class="page-head reveal">
    <h1 class="page-title"><?= e($tabs[$active]['title']) ?></h1>
</div>

<nav class="tabs reveal" style="--i: 1">
    <?php foreach (SETTINGS_MAIN_TABS as $key): ?>
        <a class="tab<?= $key === $active ? ' is-active' : '' ?>" href="index.php?p=settings&amp;tab=<?= $key ?>"><?= e($tabs[$key]['title']) ?></a>
    <?php endforeach; ?>
    <div class="dropdown" data-dropdown>
        <button class="tab<?= in_array($active, $more, true) ? ' is-active' : '' ?>" type="button" data-dropdown-toggle>
            <?= in_array($active, $more, true) ? e($tabs[$active]['title']) : 'Еще' ?><?= icon('chevron', 'icon tab__chevron') ?>
        </button>
        <div class="dropdown-menu">
            <?php foreach ($more as $key): ?>
                <a class="dropdown-item<?= $key === $active ? ' is-active' : '' ?>" href="index.php?p=settings&amp;tab=<?= $key ?>"><?= e($tabs[$key]['title']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>

<form class="settings reveal" style="--i: 2" data-settings onsubmit="return false">
    <?php foreach ($tabs[$active]['fields'] as $field):
        $current = isset($field['name']) ? $value($field) : ''; ?>

        <?php if ($field['type'] === 'switch'): ?>
            <div class="set-row<?= isset($field['reveal']) && $current === '1' ? ' is-open' : '' ?>">
                <div class="set-row__main">
                    <div class="set-row__text">
                        <div class="set-row__label"><?= e($field['label']) ?></div>
                        <?php if (!empty($field['hint'])): ?><div class="set-row__hint"><?= e($field['hint']) ?></div><?php endif; ?>
                    </div>
                    <label class="switch switch--md">
                        <input type="checkbox" name="<?= e($field['name']) ?>"<?= $current === '1' ? ' checked' : '' ?><?= isset($field['reveal']) ? ' data-reveal' : '' ?>>
                        <span class="switch__track"></span>
                    </label>
                </div>
                <?php if (isset($field['reveal'])): ?>
                    <div class="set-row__reveal"><div class="set-row__reveal-inner">
                        <?= $renderInput($field['reveal'], $value($field['reveal'])) ?>
                    </div></div>
                <?php endif; ?>
            </div>

        <?php elseif ($field['type'] === 'number' || $field['type'] === 'select' || $field['type'] === 'text'): ?>
            <div class="set-row">
                <div class="set-row__main">
                    <div class="set-row__text">
                        <div class="set-row__label"><?= e($field['label']) ?></div>
                        <?php if (!empty($field['hint'])): ?><div class="set-row__hint"><?= e($field['hint']) ?></div><?php endif; ?>
                    </div>
                    <?= $renderInput($field, $current) ?>
                </div>
            </div>

        <?php elseif ($field['type'] === 'textarea' || $field['type'] === 'code'): ?>
            <div class="set-block">
                <h2 class="set-block__title"><?= e($field['title']) ?></h2>
                <?= $renderInput($field, $current) ?>
                <?php if (!empty($field['hint'])): ?><p class="set-block__hint"><?= nl2br(e($field['hint'])) ?></p><?php endif; ?>
            </div>

        <?php elseif ($field['type'] === 'schedule'):
            $schedule = schedule_value($current ?: null); ?>
            <div class="schedule" data-schedule="<?= e($field['name']) ?>">
                <?php foreach (WEEKDAYS_FULL as $day => $dayName):
                    $d = $schedule[$day]; ?>
                    <div class="schedule__row<?= $d['open'] ? '' : ' is-closed' ?><?= $d['allday'] ? ' is-allday' : '' ?>" data-day="<?= $day ?>">
                        <label class="switch switch--md">
                            <input type="checkbox" data-k="open"<?= $d['open'] ? ' checked' : '' ?>>
                            <span class="switch__track"></span>
                            <span class="switch__label schedule__day"><?= e($dayName) ?></span>
                        </label>
                        <div class="schedule__times">
                            <input class="set-input set-input--time" type="time" data-k="from" value="<?= e($d['from']) ?>" aria-label="Открытие">
                            <span class="schedule__dash">—</span>
                            <input class="set-input set-input--time" type="time" data-k="to" value="<?= e($d['to']) ?>" aria-label="Закрытие">
                        </div>
                        <label class="check"><input type="checkbox" data-k="allday"<?= $d['allday'] ? ' checked' : '' ?>><span></span>Круглосуточно</label>
                        <span class="schedule__closed">Выходной</span>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($field['type'] === 'pickup_points'): ?>
            <div class="set-block set-block--points<?= setting('pickup_enabled', '1') === '1' ? '' : ' is-muted' ?>" data-points-block>
                <h2 class="set-block__title set-block__title--sm">Пункты самовывоза</h2>
                <div class="points" data-points>
                    <?php foreach (pickup_points(false) as $point): ?>
                        <?= render_pickup_point($point) ?>
                    <?php endforeach; ?>
                </div>
                <template data-point-template><?= render_pickup_point(['id' => 0, 'address' => '', 'hours' => 'круглосуточно', 'lat' => '', 'lng' => '', 'is_active' => 1]) ?></template>
                <button class="add-btn add-btn--inline" type="button" data-point-add><?= icon('plus', 'icon icon--sm') ?>Добавить пункт самовывоза</button>
                <p class="set-block__hint">Координаты нужны для карты. Откройте точку в Яндекс Картах — широта и долгота указаны в карточке места.</p>
            </div>

        <?php elseif ($field['type'] === 'telegram'): ?>
            <div class="set-block">
                <div class="tg">
                    <div class="tg__actions">
                        <button class="btn btn--light btn--sm" type="button" data-tg="find"><?= icon('search', 'icon icon--sm') ?>Найти чаты</button>
                        <button class="btn btn--primary btn--sm" type="button" data-tg="test">Отправить тестовое сообщение</button>
                    </div>
                    <div class="tg__chats" data-tg-chats></div>
                    <ol class="tg__help">
                        <li>Откройте в Telegram <b>@BotFather</b>, отправьте <code>/newbot</code> и придумайте имя боту.</li>
                        <li>Скопируйте токен, который пришлёт BotFather, в поле «Токен бота».</li>
                        <li>Напишите своему боту <code>/start</code> (или добавьте его в рабочую группу).</li>
                        <li>Нажмите «Найти чаты» и выберите чат — заказы начнут приходить туда.</li>
                    </ol>
                </div>
            </div>

        <?php elseif ($field['type'] === 'notice'): ?>
            <div class="notice"><?= icon('box') ?><span><?= e($field['text']) ?></span></div>
        <?php endif; ?>
    <?php endforeach; ?>
</form>
