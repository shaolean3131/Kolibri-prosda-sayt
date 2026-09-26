<?php
defined('KOLIBRI') or exit;

/** Bump when the schema below changes; migrate() then runs once. */
const SCHEMA_VERSION = 3;

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
 * Table definitions: columns (name => SQL type), constraints and indexes.
 * {id}, {fk} and {bool} are replaced per database driver.
 */
function schema_tables(): array
{
    return [
        'settings' => [
            'columns' => [
                'name'  => 'VARCHAR(100) NOT NULL PRIMARY KEY',
                'value' => 'TEXT NULL',
            ],
        ],
        'admins' => [
            'columns' => [
                'id'            => '{id}',
                'name'          => 'VARCHAR(120) NOT NULL',
                'email'         => 'VARCHAR(190) NOT NULL UNIQUE',
                'password_hash' => 'VARCHAR(255) NOT NULL',
                'created_at'    => 'DATETIME NOT NULL',
            ],
        ],
        'clients' => [
            'columns' => [
                'id'            => '{id}',
                'name'          => "VARCHAR(120) NOT NULL DEFAULT ''",
                'phone'         => "VARCHAR(32) NOT NULL DEFAULT ''",
                'email'         => "VARCHAR(190) NOT NULL DEFAULT ''",
                'birthday'      => 'DATE NULL',
                'gender'        => "VARCHAR(1) NOT NULL DEFAULT ''",
                'platform'      => "VARCHAR(10) NOT NULL DEFAULT ''",
                'points'        => 'INT NOT NULL DEFAULT 0',
                'discount'      => 'INT NOT NULL DEFAULT 0',
                'last_visit_at' => 'DATETIME NULL',
                'created_at'    => 'DATETIME NOT NULL',
            ],
            'indexes' => ['idx_clients_created' => 'created_at', 'idx_clients_phone' => 'phone'],
        ],
        'pickup_points' => [
            'columns' => [
                'id'         => '{id}',
                'address'    => 'VARCHAR(255) NOT NULL',
                'hours'      => "VARCHAR(120) NOT NULL DEFAULT ''",
                'lat'        => "VARCHAR(20) NOT NULL DEFAULT ''",
                'lng'        => "VARCHAR(20) NOT NULL DEFAULT ''",
                'sort'       => 'INT NOT NULL DEFAULT 0',
                'is_active'  => '{bool} NOT NULL DEFAULT 1',
                'created_at' => 'DATETIME NOT NULL',
            ],
        ],
        'orders' => [
            'columns' => [
                'id'              => '{id}',
                'client_id'       => '{fk} NULL',
                'client_name'     => "VARCHAR(120) NOT NULL DEFAULT ''",
                'phone'           => "VARCHAR(32) NOT NULL DEFAULT ''",
                'delivery_type'   => "VARCHAR(10) NOT NULL DEFAULT 'pickup'",
                'pickup_point_id' => '{fk} NULL',
                'address'         => "VARCHAR(255) NOT NULL DEFAULT ''",
                'apartment'       => "VARCHAR(20) NOT NULL DEFAULT ''",
                'entrance'        => "VARCHAR(20) NOT NULL DEFAULT ''",
                'floor'           => "VARCHAR(20) NOT NULL DEFAULT ''",
                'scheduled_at'    => 'DATETIME NULL',
                'ready_in'        => 'INT NOT NULL DEFAULT 0',
                'payment_method'  => "VARCHAR(20) NOT NULL DEFAULT 'cash'",
                'change_from'     => "VARCHAR(20) NOT NULL DEFAULT ''",
                'comment'         => 'TEXT NULL',
                'subtotal'        => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'discount'        => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'delivery_price'  => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'total'           => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'promo_code'      => "VARCHAR(40) NOT NULL DEFAULT ''",
                'promotion_id'    => '{fk} NULL',
                'source'          => "VARCHAR(10) NOT NULL DEFAULT 'site'",
                'status'          => "VARCHAR(20) NOT NULL DEFAULT 'new'",
                'cancel_reason'   => "VARCHAR(120) NOT NULL DEFAULT ''",
                'ip'              => "VARCHAR(45) NOT NULL DEFAULT ''",
                'created_at'      => 'DATETIME NOT NULL',
                'updated_at'      => 'DATETIME NULL',
            ],
            'constraints' => ['FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL'],
            'indexes'     => ['idx_orders_created' => 'created_at', 'idx_orders_status' => 'status', 'idx_orders_client' => 'client_id', 'idx_orders_phone' => 'phone'],
        ],
        'order_items' => [
            'columns' => [
                'id'         => '{id}',
                'order_id'   => '{fk} NOT NULL',
                'product_id' => '{fk} NULL',
                'name'       => 'VARCHAR(190) NOT NULL',
                'unit'       => "VARCHAR(60) NOT NULL DEFAULT ''",
                'price'      => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'quantity'   => 'INT NOT NULL DEFAULT 1',
                'total'      => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'is_gift'    => '{bool} NOT NULL DEFAULT 0',
            ],
            'constraints' => ['FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE'],
            'indexes'     => ['idx_order_items_order' => 'order_id'],
        ],
        'categories' => [
            'columns' => [
                'id'         => '{id}',
                'name'       => 'VARCHAR(120) NOT NULL',
                'image'      => "VARCHAR(255) NOT NULL DEFAULT ''",
                'sort'       => 'INT NOT NULL DEFAULT 0',
                'is_active'  => '{bool} NOT NULL DEFAULT 1',
                'created_at' => 'DATETIME NOT NULL',
            ],
        ],
        'products' => [
            'columns' => [
                'id'          => '{id}',
                'category_id' => '{fk} NOT NULL',
                'name'        => 'VARCHAR(190) NOT NULL',
                'description' => 'TEXT NULL',
                'price'       => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'old_price'   => 'DECIMAL(12,2) NULL',
                'unit_amount' => "VARCHAR(20) NOT NULL DEFAULT '1'",
                'unit_name'   => "VARCHAR(30) NOT NULL DEFAULT 'шт'",
                'image'       => "VARCHAR(255) NOT NULL DEFAULT ''",
                'labels'      => "VARCHAR(255) NOT NULL DEFAULT ''",
                'extra'       => 'TEXT NULL',
                'sort'        => 'INT NOT NULL DEFAULT 0',
                'is_active'   => '{bool} NOT NULL DEFAULT 1',
                'created_at'  => 'DATETIME NOT NULL',
            ],
            'constraints' => ['FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE'],
            'indexes'     => ['idx_products_category' => 'category_id'],
        ],
        'promotions' => [
            'columns' => [
                'id'             => '{id}',
                'name'           => 'VARCHAR(190) NOT NULL',
                'description'    => 'TEXT NULL',
                'banner_app'     => "VARCHAR(255) NOT NULL DEFAULT ''",
                'banner_mobile'  => "VARCHAR(255) NOT NULL DEFAULT ''",
                'banner_desktop' => "VARCHAR(255) NOT NULL DEFAULT ''",
                'starts_at'      => 'DATETIME NULL',
                'ends_at'        => 'DATETIME NULL',
                'type'           => "VARCHAR(20) NOT NULL DEFAULT 'percent'",
                'value'          => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'min_order'      => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'applies_to'     => "VARCHAR(20) NOT NULL DEFAULT 'all'",
                'category_ids'   => "VARCHAR(255) NOT NULL DEFAULT ''",
                'promo_codes'    => 'TEXT NULL',
                'options'        => 'TEXT NULL',
                'sort'           => 'INT NOT NULL DEFAULT 0',
                'is_active'      => '{bool} NOT NULL DEFAULT 1',
                'created_at'     => 'DATETIME NOT NULL',
            ],
        ],
    ];
}

/**
 * Creates missing tables, columns and indexes. Every step is idempotent,
 * so it is safe on a fresh database and on one from an older version.
 */
function migrate(PDO $pdo): void
{
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $types = [
        '{id}'   => $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT',
        '{fk}'   => $mysql ? 'INT UNSIGNED' : 'INTEGER',
        '{bool}' => $mysql ? 'TINYINT(1)' : 'INTEGER',
    ];
    $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

    foreach (schema_tables() as $name => $table) {
        $columns = array_map(static fn ($type) => strtr($type, $types), $table['columns']);
        $parts   = [];
        foreach ($columns as $column => $type) {
            $parts[] = "{$column} {$type}";
        }
        $parts = array_merge($parts, $table['constraints'] ?? []);
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$name} (" . implode(', ', $parts) . "){$tail}");

        // tables created by an older version: add the columns they lack
        $existing = table_columns($pdo, $name);
        foreach ($columns as $column => $type) {
            if (!in_array($column, $existing, true)) {
                $pdo->exec("ALTER TABLE {$name} ADD COLUMN {$column} {$type}");
            }
        }

        foreach ($table['indexes'] ?? [] as $index => $column) {
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
