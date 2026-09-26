<?php
declare(strict_types=1);

define('KOLIBRI', true);
define('ROOT_DIR', dirname(__DIR__, 2));
define('ADMIN_DIR', dirname(__DIR__));

$configFile = ROOT_DIR . '/config/config.php';
$GLOBALS['kolibri_config'] = require (is_file($configFile) ? $configFile : ROOT_DIR . '/config/config.sample.php');

date_default_timezone_set(config('timezone', 'Asia/Novokuznetsk'));
mb_internal_encoding('UTF-8');

require __DIR__ . '/schema.php';

if (PHP_SAPI !== 'cli') {
    session_name(defined('KOLIBRI_SITE') ? 'kolibri_site' : 'kolibri_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => is_https(),
    ]);
    session_start();
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

/* ---------- config / helpers ---------- */

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['kolibri_config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function asset(string $path): string
{
    $file = ADMIN_DIR . '/assets/' . $path;
    $version = is_file($file) ? filemtime($file) : 0;
    return 'assets/' . $path . '?v=' . $version;
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function money(float $amount): string
{
    return number_format($amount, 0, ',', ' ') . ' ' . config('currency', '₽');
}

/** "5 минут назад" style relative time. */
function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return 'только что';
    }
    $units = [[86400, 'день', 'дня', 'дней'], [3600, 'час', 'часа', 'часов'], [60, 'минуту', 'минуты', 'минут']];
    foreach ($units as [$seconds, $one, $few, $many]) {
        if ($diff >= $seconds) {
            $n = intdiv($diff, $seconds);
            return $n . ' ' . plural($n, $one, $few, $many) . ' назад';
        }
    }
    return '';
}

function plural(int $n, string $one, string $few, string $many): string
{
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) {
        return $many;
    }
    if ($n1 > 1 && $n1 < 5) {
        return $few;
    }
    return $n1 === 1 ? $one : $many;
}

/* ---------- database ---------- */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if (config('db.driver') === 'mysql') {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            config('db.host'),
            (int) config('db.port', 3306),
            config('db.name')
        );
        $pdo = new PDO($dsn, config('db.user'), config('db.password'), $options);
    } else {
        $path = config('db.path');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    migrate_if_needed($pdo);
    return $pdo;
}

/* ---------- auth ---------- */

function current_admin(): ?array
{
    static $admin = false;
    if ($admin !== false) {
        return $admin;
    }
    $admin = null;
    if (!empty($_SESSION['admin_id'])) {
        $stmt = db()->prepare('SELECT id, name, email FROM admins WHERE id = ?');
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch() ?: null;
    }
    return $admin;
}

function require_admin(bool $api = false): array
{
    $admin = current_admin();
    if ($admin === null) {
        if ($api) {
            json_response(['error' => 'unauthorized'], 401);
        }
        redirect('login.php');
    }
    return $admin;
}

function admins_exist(): bool
{
    return (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
}

function attempt_login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT id, password_hash FROM admins WHERE email = ?');
    $stmt->execute([mb_strtolower(trim($email))]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $row['id'];
    return true;
}

function create_admin(string $name, string $email, string $password): int
{
    $stmt = db()->prepare('INSERT INTO admins (name, email, password_hash, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([trim($name), mb_strtolower(trim($email)), password_hash($password, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
    return (int) db()->lastInsertId();
}

/* ---------- csrf ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($token) && hash_equals(csrf_token(), $token);
}

/* ---------- settings ---------- */

function setting(string $name, ?string $default = null): ?string
{
    static $all = null;
    if ($all === null) {
        $all = db()->query('SELECT name, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return array_key_exists($name, $all) ? $all[$name] : $default;
}

function save_setting(string $name, ?string $value): void
{
    upsert_setting(db(), $name, $value);
}

/* ---------- api helpers ---------- */

/** For state-changing API endpoints: admin session + POST + CSRF token. */
function api_guard(): array
{
    $admin = require_admin(api: true);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'method not allowed'], 405);
    }
    if (!csrf_valid()) {
        json_response(['error' => 'Сессия устарела. Обновите страницу.'], 419);
    }
    return $admin;
}

function input_string(string $key, int $maxLength = 255): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? mb_substr(trim($value), 0, $maxLength) : '';
}

function input_int(string $key, int $default = 0): int
{
    $value = $_POST[$key] ?? null;
    return is_numeric($value) ? (int) $value : $default;
}

function input_money(string $key): ?float
{
    $value = str_replace([' ', ','], ['', '.'], (string) (is_string($_POST[$key] ?? null) ? $_POST[$key] : ''));
    return is_numeric($value) ? round(max(0, (float) $value), 2) : null;
}

/* ---------- uploads ---------- */

/** An error whose message is safe to show to the admin. */
class UserError extends RuntimeException
{
}

/** Public URL of an uploaded file stored as "uploads/...". */
function upload_url(string $path): string
{
    return $path === '' ? '' : rtrim((string) config('site_url', '/'), '/') . '/' . $path;
}

/**
 * Saves an uploaded image into uploads/{dir}/ under a random name and
 * returns its stored path, or throws with a user-facing message.
 */
function store_image(array $file, string $dir): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new UserError($file['error'] === UPLOAD_ERR_INI_SIZE ? 'Файл слишком большой.' : 'Не удалось загрузить файл.');
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new UserError('Файл больше 10 МБ.');
    }
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $info  = @getimagesize($file['tmp_name']);
    $mime  = $info['mime'] ?? '';
    if (!isset($types[$mime])) {
        throw new UserError('Поддерживаются только JPG, PNG и WebP.');
    }

    $relative = 'uploads/' . $dir;
    $target   = ROOT_DIR . '/' . $relative;
    if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
        throw new UserError('Нет доступа к папке uploads.');
    }
    $name = bin2hex(random_bytes(12)) . '.' . $types[$mime];
    if (!move_uploaded_file($file['tmp_name'], $target . '/' . $name)) {
        throw new UserError('Не удалось сохранить файл.');
    }
    return $relative . '/' . $name;
}

function delete_upload(string $path): void
{
    if ($path !== '' && str_starts_with($path, 'uploads/') && !str_contains($path, '..')) {
        @unlink(ROOT_DIR . '/' . $path);
    }
}
