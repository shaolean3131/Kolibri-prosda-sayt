<?php
defined('KOLIBRI') or exit;

/** Bump when the schema below changes; migrate() then runs once. */
const SCHEMA_VERSION = 2;

function migrate_if_needed(PDO $pdo): void
{
    try {
        $version = (int) $pdo->query("SELECT value FROM settings WHERE name = 'schema_version'")->fetchColumn();
    } catch (PDOException) {
        $version = 0;
    }
    if ($version === SCHEMA_VERSION) {
        return;
    }
    migrate($pdo);
    upsert_setting($pdo, 'schema_version', (string) SCHEMA_VERSION);
}

/**
 * Creates missing tables and columns. Every step is idempotent, so it is
 * safe on a fresh database and on one created by an older version.
 */
function migrate(PDO $pdo): void
{
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id    = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $fk    = $mysql ? 'INT UNSIGNED' : 'INTEGER';
    $bool  = $mysql ? 'TINYINT(1)' : 'INTEGER';
    $tail  = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

    $tables = [
        'settings' => [
            'columns' => "
                name VARCHAR(100) NOT NULL PRIMARY KEY,
                value TEXT NULL",
            'indexes' => [],
        ],
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
                birthday DATE NULL,
                gender VARCHAR(1) NOT NULL DEFAULT '',
                platform VARCHAR(10) NOT NULL DEFAULT '',
                points INT NOT NULL DEFAULT 0,
                discount INT NOT NULL DEFAULT 0,
                last_visit_at DATETIME NULL,
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
            'indexes' => ['idx_orders_created' => 'created_at', 'idx_orders_status' => 'status', 'idx_orders_client' => 'client_id'],
        ],
        'categories' => [
            'columns' => "
                id {$id},
                name VARCHAR(120) NOT NULL,
                image VARCHAR(255) NOT NULL DEFAULT '',
                sort INT NOT NULL DEFAULT 0,
                is_active {$bool} NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL",
            'indexes' => [],
        ],
        'products' => [
            'columns' => "
                id {$id},
                category_id {$fk} NOT NULL,
                name VARCHAR(190) NOT NULL,
                description TEXT NULL,
                price DECIMAL(12,2) NOT NULL DEFAULT 0,
                old_price DECIMAL(12,2) NULL,
                unit_amount VARCHAR(20) NOT NULL DEFAULT '1',
                unit_name VARCHAR(30) NOT NULL DEFAULT 'шт',
                image VARCHAR(255) NOT NULL DEFAULT '',
                labels VARCHAR(255) NOT NULL DEFAULT '',
                extra TEXT NULL,
                sort INT NOT NULL DEFAULT 0,
                is_active {$bool} NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE",
            'indexes' => ['idx_products_category' => 'category_id'],
        ],
        'promotions' => [
            'columns' => "
                id {$id},
                name VARCHAR(190) NOT NULL,
                description TEXT NULL,
                banner_app VARCHAR(255) NOT NULL DEFAULT '',
                banner_mobile VARCHAR(255) NOT NULL DEFAULT '',
                banner_desktop VARCHAR(255) NOT NULL DEFAULT '',
                starts_at DATETIME NULL,
                ends_at DATETIME NULL,
                type VARCHAR(20) NOT NULL DEFAULT 'percent',
                value DECIMAL(12,2) NOT NULL DEFAULT 0,
                min_order DECIMAL(12,2) NOT NULL DEFAULT 0,
                applies_to VARCHAR(20) NOT NULL DEFAULT 'all',
                category_ids VARCHAR(255) NOT NULL DEFAULT '',
                promo_codes TEXT NULL,
                options TEXT NULL,
                sort INT NOT NULL DEFAULT 0,
                is_active {$bool} NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL",
            'indexes' => [],
        ],
    ];

    foreach ($tables as $name => $table) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$name} ({$table['columns']}){$tail}");
    }

    // Columns added after version 1.
    $added = [
        'clients' => [
            'birthday'      => 'DATE NULL',
            'gender'        => "VARCHAR(1) NOT NULL DEFAULT ''",
            'platform'      => "VARCHAR(10) NOT NULL DEFAULT ''",
            'points'        => 'INT NOT NULL DEFAULT 0',
            'discount'      => 'INT NOT NULL DEFAULT 0',
            'last_visit_at' => 'DATETIME NULL',
        ],
    ];
    foreach ($added as $table => $columns) {
        $existing = table_columns($pdo, $table);
        foreach ($columns as $column => $definition) {
            if (!in_array($column, $existing, true)) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        }
    }

    foreach ($tables as $name => $table) {
        foreach ($table['indexes'] as $index => $column) {
            if ($mysql) {
                $exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
                $exists->execute([$name, $index]);
                if ((int) $exists->fetchColumn() === 0) {
                    $pdo->exec("CREATE INDEX {$index} ON {$name} ({$column})");
                }
            } else {
                $pdo->exec("CREATE INDEX IF NOT EXISTS {$index} ON {$name} ({$column})");
            }
        }
    }
}

function table_columns(PDO $pdo, string $table): array
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $stmt = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    return array_column($pdo->query("PRAGMA table_info({$table})")->fetchAll(), 'name');
}

function upsert_setting(PDO $pdo, string $name, ?string $value): void
{
    $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
        ? 'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)'
        : 'INSERT INTO settings (name, value) VALUES (?, ?) ON CONFLICT(name) DO UPDATE SET value = excluded.value';
    $pdo->prepare($sql)->execute([$name, $value]);
}
