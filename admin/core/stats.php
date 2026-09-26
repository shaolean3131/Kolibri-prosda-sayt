<?php
defined('KOLIBRI') or exit;

const STAT_PERIODS = ['day', 'week', 'month', 'year'];

/**
 * Dashboard series for a period: revenue, order count, average check and
 * new clients per bucket (hour / day / month). Cancelled orders are ignored.
 */
function dashboard_stats(string $period): array
{
    $now     = new DateTimeImmutable('now');
    $today   = $now->setTime(0, 0);
    $months  = ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
    $weekday = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
    $buckets = [];

    switch ($period) {
        case 'day':
            $from = $today;
            for ($h = 0; $h < 24; $h++) {
                $buckets[sprintf('%02d', $h)] = sprintf('%02d:00', $h);
            }
            $keyOf = static fn (string $dt): string => substr($dt, 11, 2);
            break;

        case 'year':
            $from = $today->modify('first day of this month')->modify('-11 months');
            for ($d = $from; $d <= $now; $d = $d->modify('+1 month')) {
                $buckets[$d->format('Y-m')] = $months[(int) $d->format('n') - 1] . ' ' . $d->format('y');
            }
            $keyOf = static fn (string $dt): string => substr($dt, 0, 7);
            break;

        case 'month':
        case 'week':
        default:
            $days = $period === 'month' ? 30 : 7;
            $from = $today->modify('-' . ($days - 1) . ' days');
            for ($d = $from; $d <= $now; $d = $d->modify('+1 day')) {
                $buckets[$d->format('Y-m-d')] = $days === 7
                    ? $weekday[(int) $d->format('w')] . ' ' . $d->format('j')
                    : $d->format('d.m');
            }
            $keyOf = static fn (string $dt): string => substr($dt, 0, 10);
            break;
    }

    $zero       = array_fill_keys(array_keys($buckets), 0);
    $revenue    = $zero;
    $orders     = $zero;
    $newClients = $zero;
    $fromSql    = $from->format('Y-m-d H:i:s');

    $stmt = db()->prepare("SELECT total, created_at FROM orders WHERE created_at >= ? AND status <> 'cancelled'");
    $stmt->execute([$fromSql]);
    foreach ($stmt as $row) {
        $key = $keyOf($row['created_at']);
        if (isset($revenue[$key])) {
            $revenue[$key] += (float) $row['total'];
            $orders[$key]++;
        }
    }

    $stmt = db()->prepare('SELECT created_at FROM clients WHERE created_at >= ?');
    $stmt->execute([$fromSql]);
    foreach ($stmt as $row) {
        $key = $keyOf($row['created_at']);
        if (isset($newClients[$key])) {
            $newClients[$key]++;
        }
    }

    $avgCheck = [];
    foreach ($revenue as $key => $sum) {
        $avgCheck[$key] = $orders[$key] > 0 ? round($sum / $orders[$key]) : 0;
    }

    $totalRevenue = array_sum($revenue);
    $totalOrders  = array_sum($orders);

    return [
        'period'      => $period,
        'labels'      => array_values($buckets),
        'revenue'     => array_map('round', array_values($revenue)),
        'orders'      => array_values($orders),
        'avg_check'   => array_values($avgCheck),
        'new_clients' => array_values($newClients),
        'totals'      => [
            'revenue'     => round($totalRevenue),
            'orders'      => $totalOrders,
            'avg_check'   => $totalOrders > 0 ? round($totalRevenue / $totalOrders) : 0,
            'new_clients' => array_sum($newClients),
        ],
    ];
}
