<?php
defined('KOLIBRI') or exit;

$filters = client_filters();
$values  = client_filter_values($_GET);
$perPage = in_array((int) ($_GET['per_page'] ?? 100), CLIENT_PER_PAGE, true) ? (int) ($_GET['per_page'] ?? 100) : 100;
$total   = clients_count($values);
$pages   = max(1, (int) ceil($total / $perPage));
$current = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$clients = clients_page($values, $perPage, ($current - 1) * $perPage);
$newCount = badge_new_clients();

$query = static fn (array $change): string => 'index.php?' . http_build_query(array_merge(['p' => 'clients'], $values, ['per_page' => $perPage], $change));
$exportUrl = 'api/clients.php?' . http_build_query(array_merge(['action' => 'export'], $values));
?>
<div class="page-head reveal">
    <h1 class="page-title">
        <?= e($title) ?><?php if ($newCount): ?><sup class="title-count" title="Новых за неделю"><?= $newCount ?></sup><?php endif; ?>
    </h1>
</div>

<?php $activeFilters = count(array_filter(array_keys($filters), static fn ($key) => client_filter_summary($key, $filters[$key], $values) !== '')); ?>
<form class="filters reveal" style="--i: 1" method="get" action="index.php" data-filters>
    <input type="hidden" name="p" value="clients">
    <input type="hidden" name="per_page" value="<?= $perPage ?>">
    <button class="btn btn--light btn--sm filters__toggle" type="button" data-filters-toggle>
        Фильтры<?php if ($activeFilters): ?><span class="badge badge--blue"><?= $activeFilters ?></span><?php endif; ?>
        <?= icon('chevron', 'icon filter__chevron') ?>
    </button>
    <?php foreach ($filters as $key => $filter):
        $summary = client_filter_summary($key, $filter, $values); ?>
        <div class="dropdown filter" data-dropdown>
            <button class="filter__btn<?= $summary !== '' ? ' is-active' : '' ?>" type="button" data-dropdown-toggle>
                <span><?= e($filter['title']) ?><?php if ($summary !== ''): ?>: <b><?= e($summary) ?></b><?php endif; ?></span>
                <?= icon('chevron', 'icon filter__chevron') ?>
            </button>
            <div class="dropdown-menu filter__menu">
                <div class="filter__title"><?= e($filter['title']) ?></div>
                <?php if ($filter['type'] === 'text'): ?>
                    <input class="mini-input" type="search" name="<?= $key ?>" value="<?= e($values[$key] ?? '') ?>" placeholder="Например, 912 345" inputmode="tel">
                <?php elseif ($filter['type'] === 'date'): ?>
                    <div class="filter__pair">
                        <label class="mini-field"><span>С</span><input class="mini-input" type="date" name="<?= $key ?>_from" value="<?= e($values["{$key}_from"] ?? '') ?>"></label>
                        <label class="mini-field"><span>По</span><input class="mini-input" type="date" name="<?= $key ?>_to" value="<?= e($values["{$key}_to"] ?? '') ?>"></label>
                    </div>
                <?php elseif ($filter['type'] === 'range'): ?>
                    <div class="filter__pair">
                        <label class="mini-field"><span>От</span><input class="mini-input" type="number" min="0" name="<?= $key ?>_min" value="<?= e($values["{$key}_min"] ?? '') ?>"></label>
                        <label class="mini-field"><span>До</span><input class="mini-input" type="number" min="0" name="<?= $key ?>_max" value="<?= e($values["{$key}_max"] ?? '') ?>"></label>
                    </div>
                <?php else: ?>
                    <div class="filter__options">
                        <?php foreach ($filter['options'] as $value => $label): ?>
                            <label class="radio">
                                <input type="radio" name="<?= $key ?>" value="<?= e($value) ?>"<?= ($values[$key] ?? '') === $value ? ' checked' : '' ?>>
                                <span></span><?= e($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="filter__actions">
                    <button class="btn btn--light btn--sm" type="button" data-filter-reset>Сбросить</button>
                    <button class="btn btn--primary btn--sm" type="submit">Применить</button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <a class="btn btn--light btn--sm filters__download" href="<?= e($exportUrl) ?>" download><?= icon('download', 'icon icon--sm') ?>Скачать весь список</a>
    <?php if ($values): ?>
        <a class="filters__clear" href="index.php?p=clients">Сбросить все</a>
    <?php endif; ?>
</form>

<div class="table-meta reveal" style="--i: 2">
    <span class="table-meta__count"><?= $total ?> <?= plural($total, 'клиент', 'клиента', 'клиентов') ?></span>
    <div class="dropdown" data-dropdown>
        <button class="link-dashed" type="button" data-dropdown-toggle>Отображать по <?= $perPage ?></button>
        <div class="dropdown-menu dropdown-menu--right">
            <?php foreach (CLIENT_PER_PAGE as $n): ?>
                <a class="dropdown-item<?= $n === $perPage ? ' is-active' : '' ?>" href="<?= e($query(['per_page' => $n, 'page' => 1])) ?>">По <?= $n ?><?= icon('check', 'icon icon--sm dropdown-item__check') ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="table-wrap reveal" style="--i: 3">
    <table class="table">
        <thead>
            <tr>
                <th class="table__name">Имя</th>
                <th>Телефон</th>
                <th class="c">Визит</th>
                <th class="c">Заказ</th>
                <th class="c">Кол</th>
                <th class="c">Баллы</th>
                <th class="c">Пол</th>
                <th class="c">ДР</th>
                <th class="c">Моб</th>
                <th class="r">Чек</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$clients): ?>
                <tr><td class="table__empty" colspan="10">Клиенты не найдены</td></tr>
            <?php endif; ?>
            <?php foreach ($clients as $n => $client): ?>
                <tr style="--row: <?= min($n, 20) ?>">
                    <td class="table__name"><?= e($client['name']) ?></td>
                    <td><button class="phone" type="button" data-phone="<?= (int) $client['id'] ?>">скрыт</button></td>
                    <td class="c"><?= e(ru_date($client['last_visit_at'])) ?: '—' ?></td>
                    <td class="c"><?= e(ru_date($client['last_order_at'])) ?: '—' ?></td>
                    <td class="c"><?php if ($client['orders_count'] > 0): ?><a class="table__link" href="index.php?p=orders&amp;client=<?= (int) $client['id'] ?>"><?= (int) $client['orders_count'] ?></a><?php else: ?>—<?php endif; ?></td>
                    <td class="c"><?= $client['points'] > 0 ? (int) $client['points'] : '—' ?></td>
                    <td class="c"><?php if ($client['gender'] !== ''): ?><span class="gender gender--<?= e($client['gender']) ?>" title="<?= $client['gender'] === 'm' ? 'Мужской' : 'Женский' ?>"></span><?php endif; ?></td>
                    <td class="c"><?= e(ru_date($client['birthday'])) ?: '—' ?></td>
                    <td class="c">
                        <?php if ($client['platform'] === 'ios'): ?><span class="platform" title="iOS"><?= icon('apple') ?></span>
                        <?php elseif ($client['platform'] === 'android'): ?><span class="platform" title="Android"><?= icon('play') ?></span><?php endif; ?>
                    </td>
                    <td class="r"><?= $client['avg_check'] !== null ? e(number_format((float) $client['avg_check'], 0, ',', ' ')) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
    <nav class="pager reveal" style="--i: 4">
        <?php for ($n = 1; $n <= $pages; $n++):
            if ($n !== 1 && $n !== $pages && abs($n - $current) > 2) {
                if ($n === 2 || $n === $pages - 1) {
                    echo '<span class="pager__gap">…</span>';
                }
                continue;
            } ?>
            <a class="pager__item<?= $n === $current ? ' is-active' : '' ?>" href="<?= e($query(['page' => $n])) ?>"><?= $n ?></a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>
