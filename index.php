<?php
declare(strict_types=1);
require __DIR__ . '/core/bootstrap.php';
require __DIR__ . '/core/ui.php';
require __DIR__ . '/core/domain.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (BASE !== '' && str_starts_with($path, BASE)) $path = substr($path, strlen(BASE));
$path = trim($path, '/');
if ($path === 'index.php') $path = '';
$seg = $path === '' ? [] : explode('/', $path);

/* ---------- public routes ---------- */
if (in_array($path, ['login', 'register', 'logout'], true)) {
    if ($path === 'logout') { $_SESSION = []; session_destroy(); redirect('login'); }
    require __DIR__ . "/views/auth/$path.php";
    exit;
}

/* ---------- everything else needs a logged-in owner ---------- */
if (empty($_SESSION['uid'])) {
    if (($seg[0] ?? '') === 'api') json_err('Session expired. Please log in again.', 401);
    redirect('login');
}

/* ---------- JSON API: /api/{module}/{action} ---------- */
if (($seg[0] ?? '') === 'api') {
    $module = preg_replace('/[^a-z_]/', '', $seg[1] ?? '');
    $action = preg_replace('/[^a-z_]/', '', $seg[2] ?? '');
    $file = __DIR__ . "/api/$module.php";
    if (!$module || !is_file($file)) json_err('Unknown endpoint', 404);
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !csrf_ok($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? null))) json_err('Invalid security token. Refresh the page.', 419);
    try {
        require $file;
        json_err('Unknown action', 404);
    } catch (Throwable $e) {
        error_log('[amarfy api] ' . $e->getMessage());
        json_err('Something went wrong: ' . $e->getMessage(), 500);
    }
}

/* ---------- CSV exports ---------- */
if (($seg[0] ?? '') === 'export') { require __DIR__ . '/core/export.php'; exit; }

/* ---------- pages ---------- */
$id = null;
$routes = [
    ''                => ['home', 'home', null],
    'home'            => ['home', 'home', null],
    'orders'          => ['orders', 'orders', null],
    'orders/*'        => ['order_view', 'orders', null],
    'draft_orders'    => ['drafts', 'orders', 'drafts'],
    'draft_orders/*'  => ['draft_edit', 'orders', 'drafts'],
    'shipping_labels' => ['shipping_labels', 'orders', 'shipping'],
    'checkouts'       => ['checkouts', 'orders', 'checkouts'],
    'products'        => ['products', 'products', null],
    'products/inventory' => ['inventory', 'products', 'inventory'],
    'products/*'      => ['product_edit', 'products', null],
    'collections'     => ['collections', 'products', 'collections'],
    'collections/*'   => ['collection_edit', 'products', 'collections'],
    'purchase_orders' => ['purchase_orders', 'products', 'purchase_orders'],
    'purchase_orders/*' => ['purchase_order_edit', 'products', 'purchase_orders'],
    'transfers'       => ['transfers', 'products', 'transfers'],
    'transfers/*'     => ['transfer_edit', 'products', 'transfers'],
    'gift_cards'      => ['gift_cards', 'products', 'gift_cards'],
    'gift_cards/*'    => ['gift_card_edit', 'products', 'gift_cards'],
    'settings'        => ['settings', 'settings', null],
    'customers'       => ['customers', 'customers', null],
    'customers/*'     => ['customer_edit', 'customers', null],
];
$match = $routes[$path] ?? null;
if (!$match && count($seg) === 2 && isset($routes[$seg[0] . '/*'])) {
    $match = $routes[$seg[0] . '/*'];
    $id = $seg[1] === 'new' ? 'new' : (ctype_digit($seg[1]) ? (int)$seg[1] : null);
    if ($id === null) $match = null;
}
if (!$match) { $match = ['placeholder', $seg[0] ?? 'home', $seg[1] ?? null]; }

[$view, $NAV, $SUB] = $match;
$PAGE_TITLE = 'Amarfy';
try {
    ob_start();
    require __DIR__ . "/views/pages/$view.php";
    $CONTENT = ob_get_clean();
} catch (Throwable $e) {
    ob_end_clean();
    error_log('[amarfy page] ' . $e->getMessage());
    http_response_code(500);
    $CONTENT = '<div class="page-pad"><div class="banner warn"><b>Something went wrong.</b> ' . h($e->getMessage()) . '</div></div>';
}
require __DIR__ . '/views/layout.php';
