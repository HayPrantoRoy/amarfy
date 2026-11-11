<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
$GLOBALS['CFG'] = require APP_ROOT . '/config/config.php';
date_default_timezone_set($GLOBALS['CFG']['timezone']);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('serialize_precision', '-1'); // 699.95 stays 699.95 in JSON

// Base URL path, e.g. "/amarfy" — derived so the app works in any folder.
$__base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
define('BASE', $__base === '/' ? '' : $__base);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('AMARFYSESSID');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => BASE . '/']);
    session_start();
}

/* ------------------------------------------------------------------ */
/* Database                                                           */
/* ------------------------------------------------------------------ */
final class DB
{
    private static ?mysqli $master = null;
    private static ?mysqli $tenant = null;

    public static function connect(?string $db = null): mysqli
    {
        $c = $GLOBALS['CFG']['db'];
        $m = new mysqli($c['host'], $c['username'], $c['password'], $db ?? '');
        $m->set_charset($c['charset']);
        $m->query("SET time_zone = '" . (new DateTime())->format('P') . "'");
        return $m;
    }

    /** Master DB — created automatically (idempotent) on first use. */
    public static function master(): mysqli
    {
        if (self::$master) return self::$master;
        $name = $GLOBALS['CFG']['master_db'];
        try {
            self::$master = self::connect($name);
        } catch (mysqli_sql_exception $e) {
            $raw = self::connect();
            $raw->query("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $raw->close();
            self::$master = self::connect($name);
            self::runSqlFile(self::$master, APP_ROOT . '/database/master_schema.sql');
        }
        return self::$master;
    }

    public static function tenant(): mysqli
    {
        if (self::$tenant) return self::$tenant;
        $db = $_SESSION['db_name'] ?? '';
        if (!preg_match('/^[a-z0-9_]+$/', $db)) throw new RuntimeException('No tenant database in session');
        self::$tenant = self::connect($db);
        return self::$tenant;
    }

    public static function runSqlFile(mysqli $conn, string $path): void
    {
        $sql = file_get_contents($path);
        if ($sql === false) throw new RuntimeException("Cannot read $path");
        $conn->multi_query($sql);
        do { if ($r = $conn->store_result()) $r->free(); } while ($conn->more_results() && $conn->next_result());
    }

    /** First unused tenant number at or after the configured start (amarfy_100001, ...). */
    public static function nextTenantNumber(): int
    {
        $prefix = $GLOBALS['CFG']['tenant_prefix'];
        $n = (int)$GLOBALS['CFG']['tenant_start'];
        $m = self::master();
        $max = $m->query("SELECT MAX(tenant_number) m FROM users")->fetch_assoc()['m'];
        if ($max && (int)$max >= $n) $n = (int)$max + 1;
        while (true) {
            $name = $prefix . $n;
            $st = $m->prepare("SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?");
            $st->bind_param('s', $name);
            $st->execute();
            if ($st->get_result()->num_rows === 0) return $n;
            $n++;
        }
    }
}

/** Run a prepared query against a connection. Returns mysqli_result|bool. */
function dbq(mysqli $c, string $sql, array $params = [])
{
    $st = $c->prepare($sql);
    if ($params) {
        $types = '';
        foreach ($params as $p) $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
        $st->bind_param($types, ...$params);
    }
    $st->execute();
    $res = $st->get_result();
    if ($res === false) { $GLOBALS['__last_id'] = $st->insert_id; $GLOBALS['__affected'] = $st->affected_rows; $st->close(); return true; }
    $st->close();
    return $res;
}
function t_rows(string $sql, array $p = []): array { $r = dbq(DB::tenant(), $sql, $p); return $r instanceof mysqli_result ? $r->fetch_all(MYSQLI_ASSOC) : []; }
function t_row(string $sql, array $p = []): ?array { $r = dbq(DB::tenant(), $sql, $p); return $r instanceof mysqli_result ? ($r->fetch_assoc() ?: null) : null; }
function t_val(string $sql, array $p = []) { $r = t_row($sql, $p); return $r ? array_values($r)[0] : null; }
function t_exec(string $sql, array $p = []): int { dbq(DB::tenant(), $sql, $p); return (int)($GLOBALS['__last_id'] ?? 0); }
function m_row(string $sql, array $p = []): ?array { $r = dbq(DB::master(), $sql, $p); return $r instanceof mysqli_result ? ($r->fetch_assoc() ?: null) : null; }
function m_exec(string $sql, array $p = []): int { dbq(DB::master(), $sql, $p); return (int)($GLOBALS['__last_id'] ?? 0); }

/* ------------------------------------------------------------------ */
/* Helpers                                                            */
/* ------------------------------------------------------------------ */
function h($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function url(string $p = ''): string { return BASE . '/' . ltrim($p, '/'); }
function asset(string $p): string { $f = APP_ROOT . '/assets/' . $p; return url('assets/' . $p) . (is_file($f) ? '?v=' . filemtime($f) : ''); }
function redirect(string $p): never { header('Location: ' . url($p)); exit; }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_ok(?string $t): bool { return is_string($t) && hash_equals(csrf_token(), $t); }
function setting(string $k, $default = null) {
    if (!isset($GLOBALS['__settings'])) { $GLOBALS['__settings'] = []; foreach (t_rows("SELECT k, v FROM settings") as $r) $GLOBALS['__settings'][$r['k']] = $r['v']; }
    return $GLOBALS['__settings'][$k] ?? $default;
}
function set_setting(string $k, $v): void {
    t_exec("INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)", [$k, (string)$v]);
    if (isset($GLOBALS['__settings'])) $GLOBALS['__settings'][$k] = (string)$v;   // keep the cache in sync
}
function sym(): string { return setting('currency_symbol', '৳'); }
function cur(): string { return setting('currency', 'BDT'); }
function money($v, bool $code = false): string { return sym() . number_format((float)$v, 2) . ($code ? ' ' . cur() : ''); }
function initials(string $s): string {
    $parts = preg_split('/\s+/', trim($s)) ?: [];
    $i = '';
    foreach (array_slice($parts, 0, 2) as $p) $i .= mb_strtoupper(mb_substr($p, 0, 1));
    return $i ?: 'A';
}
function friendly_date(?string $dt): string {
    if (!$dt) return '';
    $t = strtotime($dt); $now = time(); $diff = $now - $t;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) { $m = intdiv($diff, 60); return $m . ' minute' . ($m > 1 ? 's' : '') . ' ago'; }
    $time = strtolower(date('g:i a', $t));
    if (date('Y-m-d', $t) === date('Y-m-d')) return 'Today at ' . $time;
    if (date('Y-m-d', $t) === date('Y-m-d', strtotime('-1 day'))) return 'Yesterday at ' . $time;
    if ($diff < 6 * 86400) return date('l', $t) . ' at ' . $time;
    return date('M j', $t) . (date('Y', $t) !== date('Y') ? ', ' . date('Y', $t) : '') . ' at ' . $time;
}
function json_out($data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function json_err(string $msg, int $code = 422): never { json_out(['ok' => false, 'error' => $msg], $code); }
function input(): array {
    static $in = null;
    if ($in !== null) return $in;
    $raw = file_get_contents('php://input');
    $j = $raw ? json_decode($raw, true) : null;
    return $in = is_array($j) ? $j : $_POST;
}
function slugify(string $s): string { $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-')); return $s ?: 'item'; }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function staff_name(): string { return current_user()['name'] ?? 'Staff'; }
function upload_dir(): string {
    $f = $_SESSION['upload_folder'] ?? '';
    if (!preg_match('/^[a-z0-9_]+$/', $f)) throw new RuntimeException('Bad upload folder');
    $d = APP_ROOT . '/uploads/' . $f;
    if (!is_dir($d)) {
        if (!@mkdir($d, 0777, true) && !is_dir($d))
            throw new RuntimeException('The uploads folder is not writable by the web server. Run: chmod 777 ' . APP_ROOT . '/uploads');
        @chmod($d, 0777); // umask strips group/other write; keep it writable for web + CLI tools
    }
    if (!is_writable($d)) throw new RuntimeException('Upload folder ' . $f . ' is not writable by the web server.');
    return $d;
}
function timeline_add(string $type, int $id, string $msg, string $kind = 'event'): void {
    t_exec("INSERT INTO timeline (subject_type, subject_id, kind, message, author) VALUES (?,?,?,?,?)", [$type, $id, $kind, $msg, staff_name()]);
}

/* ------------------------------------------------------------------ */
/* Tenant provisioning (registration)                                 */
/* ------------------------------------------------------------------ */
function provision_tenant(int $userId, string $name, string $email, string $storeName): string
{
    $cfg = $GLOBALS['CFG'];
    $n = DB::nextTenantNumber();
    $db = $cfg['tenant_prefix'] . $n;
    $raw = DB::connect();
    $raw->query("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $raw->close();
    m_exec("UPDATE users SET db_name = ?, tenant_number = ?, upload_folder = ? WHERE id = ?", [$db, $n, $db, $userId]);

    $t = DB::connect($db);
    DB::runSqlFile($t, APP_ROOT . '/database/tenant_schema.sql');
    $seed = [
        'store_name' => $storeName, 'currency' => $cfg['currency'], 'currency_symbol' => $cfg['currency_symbol'],
        'market' => 'Bangladesh', 'next_order_number' => '1001', 'next_draft_number' => '1',
        'next_po_number' => '1', 'next_transfer_number' => '1', 'tenant_number' => (string)$n,
    ];
    foreach ($seed as $k => $v) dbq($t, "INSERT IGNORE INTO settings (k, v) VALUES (?, ?)", [$k, $v]);
    dbq($t, "INSERT INTO locations (name, is_default) VALUES ('Shop location', 1), ('My Custom Location', 0)");
    dbq($t, "INSERT INTO staff (master_user_id, name, email, role) VALUES (?, ?, ?, 'owner')", [$userId, $name, $email]);
    $t->close();
    if (@mkdir(APP_ROOT . '/uploads/' . $db, 0777, true)) @chmod(APP_ROOT . '/uploads/' . $db, 0777);
    return $db;
}

function login_user(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['db_name'] = $u['db_name'];
    $_SESSION['upload_folder'] = $u['upload_folder'] ?: $u['db_name'];
    $_SESSION['user'] = ['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email'], 'store_name' => $u['store_name'], 'tenant_number' => $u['tenant_number']];
    m_exec("UPDATE users SET last_login_at = NOW() WHERE id = ?", [(int)$u['id']]);
}
