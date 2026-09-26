<?php
defined('KOLIBRI') or exit;

/**
 * Creates missing tables. Safe to run on every request: every statement is
 * "IF NOT EXISTS". Works for both SQLite and MySQL.
 */
function migrate(PDO $pdo): void
{
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id    = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $fk    = $mysql ? 'INT UNSIGNED' : 'INTEGER';
    $tail  = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

    $tables = [
        'admins' => [
            'columns' => "
                id {$id},
                name VARCHAR(120) NOT NULL,
                email VARCHAR(190) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL",
            'indexes' => [],
        ],
        'clients' => [
            'columns' => "
                id {$id},
                name VARCHAR(120) NOT NULL DEFAULT '',
                phone VARCHAR(32) NOT NULL DEFAULT '',
                email VARCHAR(190) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL",
            'indexes' => ['idx_clients_created' => 'created_at'],
        ],
        'orders' => [
            'columns' => "
                id {$id},
                client_id {$fk} NULL,
                total DECIMAL(12,2) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'new',
                created_at DATETIME NOT NULL,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL",
            'indexes' => ['idx_orders_created' => 'created_at', 'idx_orders_status' => 'status'],
        ],
    ];

    foreach ($tables as $name => $table) {
        $columns = $table['columns'];
        if ($mysql) {
            foreach ($table['indexes'] as $index => $column) {
                $columns .= ", INDEX {$index} ({$column})";
            }
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$name} ({$columns}){$tail}");

        if (!$mysql) {
            foreach ($table['indexes'] as $index => $column) {
                $pdo->exec("CREATE INDEX IF NOT EXISTS {$index} ON {$name} ({$column})");
            }
        }
    }
}
