<?php
$u = current_user();
$store = setting('store_name', $u['store_name'] ?? 'My store');
$openOrders = (int)t_val("SELECT COUNT(*) FROM orders WHERE fulfillment_status='unfulfilled' AND archived=0 AND cancelled_at IS NULL");
// bell badge = number of attention groups (same rules as api/notifications)
$alerts = (int)($openOrders > 0)
    + (int)((int)t_val("SELECT COUNT(*) FROM orders WHERE payment_status='pending' AND cancelled_at IS NULL") > 0)
    + (int)((int)t_val("SELECT COUNT(*) FROM products p WHERE p.status='active' AND p.track_inventory=1 AND (SELECT COALESCE(SUM(il.available),0) FROM inventory_levels il JOIN variants v ON v.id=il.variant_id WHERE v.product_id=p.id) <= 0") > 0)
    + (int)((int)t_val("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('ordered','partial')") > 0)
    + (int)((int)t_val("SELECT COUNT(*) FROM transfers WHERE status='in_transit'") > 0);
$menu = [
    ['home', 'Home', 'home', []],
    ['orders', 'Orders', 'orders', [['drafts', 'Drafts', 'draft_orders'], ['shipping', 'Shipping labels', 'shipping_labels'], ['checkouts', 'Abandoned checkouts', 'checkouts']]],
    ['products', 'Products', 'products', [['collections', 'Collections', 'collections'], ['inventory', 'Inventory', 'products/inventory'], ['purchase_orders', 'Purchase orders', 'purchase_orders'], ['transfers', 'Transfers', 'transfers'], ['gift_cards', 'Gift cards', 'gift_cards']]],
    ['customers', 'Customers', 'customers', [['segments', 'Segments', 'customers/segments'], ['companies', 'Companies', 'customers/companies']]],
    ['growth', 'Growth', 'growth', [['attribution', 'Attribution', 'growth/attribution'], ['autopilot', 'Autopilot', 'growth/autopilot'], ['campaigns', 'Campaigns', 'growth/campaigns']]],
    ['discounts', 'Discounts', 'discounts', []],
    ['content', 'Content', 'content', [['metaobjects', 'Metaobjects', 'content/metaobjects'], ['files', 'Files', 'content/files'], ['menus', 'Menus', 'content/menus'], ['blog_posts', 'Blog posts', 'content/blog_posts']]],
    ['markets', 'Markets', 'markets', [['catalogs', 'Catalogs', 'markets/catalogs'], ['rollouts', 'Rollouts', 'markets/rollouts']]],
    ['analytics', 'Analytics', 'analytics', [['reports', 'Reports', 'analytics/reports'], ['live_view', 'Live View', 'analytics/live_view']]],
];
$channels = [
    ['online_store', 'Online Store', 'online_store', 'store', [['pages', 'Pages', 'online_store/pages'], ['preferences', 'Preferences', 'online_store/preferences']]],
    ['agentic', 'Agentic', 'agentic', 'agentic', []],
];
function nav_item($key, $label, $href, $ic, $subs, $NAV, $SUB, $badge = '', $app = false) {
    $isParent = $NAV === $key;
    $active = $isParent && !$SUB;
    $o = '<li class="' . ($isParent && $subs ? 'open' : '') . '"><a href="' . h(url($href)) . '" class="' . ($active ? 'active' : '') . '">';
    $o .= $app ? '<span class="app-ico">' . icon($ic) . '</span>' : icon($ic);
    $o .= '<span class="lbl">' . h($label) . '</span>' . $badge;
    if ($key === 'online_store') $o .= '<span class="actions">' . icon('pencil') . icon('eye') . '</span>';
    $o .= '</a>';
    if ($subs) {
        $o .= '<ul class="sub">';
        foreach ($subs as [$sk, $sl, $sh]) $o .= '<li><a href="' . h(url($sh)) . '" class="' . ($isParent && $SUB === $sk ? 'active' : '') . '">' . h($sl) . '</a></li>';
        $o .= '</ul>';
    }
    return $o . '</li>';
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($PAGE_TITLE) ?> · <?= h($store) ?> · Amarfy</title>
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;450;500;550;600;650&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('app.css') ?>">
<script>window.AMARFY = {base: <?= json_encode(BASE) ?>, csrf: <?= json_encode(csrf_token()) ?>, sym: <?= json_encode(sym()) ?>, cur: <?= json_encode(cur()) ?>, user: <?= json_encode($u['name'] ?? '') ?>};</script>
</head>
<body>
<div class="app" id="app">
  <aside class="sidebar">
    <div class="sb-top">
      <a href="<?= h(url('home')) ?>" class="logo" title="Amarfy">a</a>
      <button class="icon-btn" id="sbToggle" aria-label="Toggle sidebar"><?= icon('sidebar') ?></button>
    </div>
    <button class="search" id="openSearch" type="button"><?= icon('search') ?><span>Search</span><kbd>⌘K</kbd></button>
    <div class="sb-scroll">
      <ul class="nav">
        <?php foreach ($menu as [$k, $l, $href, $subs]) {
            $badge = $k === 'orders' && $openOrders ? '<span class="badge">' . $openOrders . '</span>' : '';
            echo nav_item($k, $l, $href, $k, $subs, $NAV, $SUB, $badge);
        } ?>
      </ul>
      <div class="sb-section">Sales channels <?= icon('chev-right') ?></div>
      <ul class="nav">
        <?php foreach ($channels as [$k, $l, $href, $ic, $subs]) echo nav_item($k, $l, $href, $ic, $subs, $NAV, $SUB, '', true); ?>
      </ul>
      <div class="sb-section">Apps <?= icon('chev-right') ?></div>
      <ul class="nav"><?= nav_item('apps', 'Add', 'apps', 'add-circle', [], $NAV, $SUB) ?></ul>
    </div>
    <div class="sb-bottom">
      <ul class="nav"><?= nav_item('settings', 'Settings', 'settings', 'settings', [], $NAV, $SUB) ?></ul>
      <div class="store" id="storeMenuBtn">
        <div class="avatar"><?= h(strtoupper(mb_substr(preg_replace('/\s+/', '', $store), 0, 3))) ?></div>
        <span class="store-name"><?= h($store) ?></span>
        <span class="tag">dev</span>
        <button class="bell" id="bellBtn" aria-label="Notifications" type="button"><?= icon('bell') ?><?= $alerts ? '<i>' . $alerts . '</i>' : '' ?></button>
      </div>
      <div class="store-menu" id="storeMenu" hidden>
        <div class="sm-head"><b><?= h($u['name'] ?? '') ?></b><span><?= h($u['email'] ?? '') ?></span><span class="muted">Store ID: <?= h($_SESSION['db_name'] ?? '') ?></span></div>
        <a href="<?= h(url('settings')) ?>"><?= icon('settings') ?>Settings</a>
        <a href="<?= h(url('logout')) ?>"><?= icon('logout') ?>Log out</a>
      </div>
    </div>
  </aside>

  <button class="sb-open" id="sbOpen" type="button" aria-label="Open sidebar"><?= icon('sidebar') ?></button>
  <main class="main<?= $NAV === 'home' ? ' is-home' : '' ?>" id="main">
    <?= $CONTENT ?>
    <footer class="app-footer">
      <span>© 2024 - 2026 BizScale Global Limited | All rights reserved</span>
      <span>Powered By: Amarfy v<?= h($GLOBALS['CFG']['version']) ?></span>
    </footer>
    <?php if ($NAV !== 'home'): /* Home has its own AI prompt in the hero */ ?>
    <div class="ai-bar">
      <div class="ai-inner">
        <svg class="bot" viewBox="0 0 20 20"><circle cx="10" cy="10.5" r="8.5" fill="#2a2f8f"/><path d="M2.5 8.5c1.5-4 4.5-6 7.5-6s6 2 7.5 6c-2-1-4.5-1.4-7.5-1.4S4.5 7.5 2.5 8.5z" fill="#1b1f5e"/><rect x="3.6" y="9" width="5.6" height="4.4" rx="2" fill="#fff" stroke="#7b5cf5"/><rect x="10.8" y="9" width="5.6" height="4.4" rx="2" fill="#fff" stroke="#7b5cf5"/><circle cx="6.6" cy="11.2" r="1" fill="#1a1a1a"/><circle cx="13.6" cy="11.2" r="1" fill="#1a1a1a"/></svg>
        <input placeholder="Work with Amarfy AI" id="aiInput" data-ai-input>
        <button class="icon-btn" type="button" data-ai-plus title="Quick create"><?= icon('plus') ?></button>
        <button class="icon-btn" type="button" data-ai-mic title="Voice input"><?= icon('mic') ?></button>
        <span class="sep"></span>
        <button class="icon-btn" type="button" data-ai-panel title="Open Amarfy AI"><?= icon('panel') ?></button>
      </div>
    </div>
    <?php endif; ?>
  </main>
</div>

<div class="search-overlay" id="searchOverlay" hidden>
  <div class="search-box">
    <label><?= icon('search') ?><input id="globalSearch" placeholder="Search orders, products, customers…" autocomplete="off"></label>
    <div id="globalResults" class="search-results"></div>
  </div>
</div>
<div id="toasts"></div>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/assistant.js') ?>"></script>
<?php foreach ($GLOBALS['PAGE_SCRIPTS'] ?? [] as $s): ?><script src="<?= asset('js/' . $s) ?>"></script><?php endforeach; ?>
</body>
</html>
