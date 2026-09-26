<?php
require dirname(__DIR__) . '/core/bootstrap.php';
require dirname(__DIR__) . '/core/stats.php';

require_admin(api: true);

$period = is_string($_GET['period'] ?? null) ? $_GET['period'] : 'week';
if (!in_array($period, STAT_PERIODS, true)) {
    json_response(['error' => 'unknown period'], 422);
}

json_response(dashboard_stats($period));
