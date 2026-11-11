<?php
$PAGE_TITLE = 'Home';

/* ---------- headline metrics: current period vs previous period, per day ---------- */
$range = in_array((int)($_GET['range'] ?? 0), [7, 30, 90], true) ? (int)$_GET['range'] : (int)setting('home_range', '30');
if (!in_array($range, [7, 30, 90], true)) $range = 30;
if (isset($_GET['range'])) set_setting('home_range', $range);
$today = new DateTimeImmutable('today');
$curStart = $today->modify('-' . ($range - 1) . ' days');
$prevStart = $curStart->modify("-$range days");
$daily = [];
foreach (t_rows("SELECT DATE(created_at) d, COUNT(*) n, COALESCE(SUM(total),0) s FROM orders WHERE cancelled_at IS NULL AND created_at >= ? GROUP BY DATE(created_at)", [$prevStart->format('Y-m-d')]) as $r)
    $daily[$r['d']] = ['n' => (int)$r['n'], 's' => (float)$r['s']];
$series = function (DateTimeImmutable $start) use ($daily, $range) {
    $o = ['dates' => [], 'sales' => [], 'orders' => [], 'aov' => [], 'sessions' => []];
    for ($i = 0; $i < $range; $i++) {
        $d = $start->modify("+$i days")->format('Y-m-d');
        $n = $daily[$d]['n'] ?? 0; $sv = $daily[$d]['s'] ?? 0.0;
        $o['dates'][] = $d; $o['sales'][] = round($sv, 2); $o['orders'][] = $n; $o['aov'][] = $n ? round($sv / $n, 2) : 0; $o['sessions'][] = 0;
    }
    return $o;
};
$cur = $series($curStart); $prev = $series($prevStart);
$s = ['n' => array_sum($cur['orders']), 'sales' => array_sum($cur['sales'])];
$aov = $s['n'] ? $s['sales'] / $s['n'] : 0;
$sessions = $range === 30 ? (int)setting('sessions_30d', '0') : 0;
$fmtRange = fn(DateTimeImmutable $a, DateTimeImmutable $b) => $a->format('M j') . ' – ' . $b->format('M j, Y');
$chartData = [
    'cur' => $cur, 'prev' => $prev, 'currency' => cur(), 'range' => $range,
    'curLabel' => $fmtRange($curStart, $today), 'prevLabel' => $fmtRange($prevStart, $curStart->modify('-1 day')),
    'totals' => ['sessions' => $sessions, 'sales' => round($s['sales'], 2), 'orders' => $s['n'], 'aov' => round($aov, 2)],
    'prevTotals' => ['sales' => array_sum($prev['sales']), 'orders' => array_sum($prev['orders'])],
];
/** Tiny sparkline from a daily series (26×12). */
function mini_spark(array $v): string {
    // bucket into ~6 points so a 26px-wide line reads as a shape, not noise
    $buckets = 6; $size = (int)ceil(count($v) / $buckets); $b = [];
    foreach (array_chunk($v, max(1, $size)) as $chunk) $b[] = array_sum($chunk);
    $v = $b;
    $max = max($v) ?: 1; $n = count($v); $pts = [];
    foreach ($v as $i => $y) $pts[] = round(1 + 24 * $i / max(1, $n - 1), 2) . ',' . round(11 - 10 * $y / $max, 2);
    return '<svg class="spk" viewBox="0 0 26 12" aria-hidden="true"><polyline points="' . implode(' ', $pts) . '"/></svg>';
}
$toFulfill = (int)t_val("SELECT COUNT(*) FROM orders WHERE fulfillment_status='unfulfilled' AND cancelled_at IS NULL AND archived=0");
$toCapture = (int)t_val("SELECT COUNT(*) FROM orders WHERE payment_status='pending' AND cancelled_at IS NULL");

/* ---------- AI suggestions, built from real store data ---------- */
$suggest = [];
$noun = fn(?string $type, string $fallback) => strtolower(trim((string)$type)) ?: $fallback;
// 1. product with several variants → make choices easy to compare
if ($p = t_row("SELECT p.id, p.title, p.product_type, (SELECT COUNT(*) FROM variants v WHERE v.product_id=p.id) vc FROM products p WHERE p.status<>'archived' HAVING vc > 1 ORDER BY vc DESC LIMIT 1")) {
    $n = $noun($p['product_type'], 'product');
    $suggest[] = ['title' => 'Make ' . $n . ' choices easy to compare', 'text' => $p['title'] . ' has ' . $p['vc'] . ' variants with similar names. Clarify each option before shoppers choose.', 'btn' => 'Clarify ' . $n . ' options', 'href' => 'products/' . $p['id']];
}
// 2. active product with no stock → hide it
if ($p = t_row("SELECT p.id, p.title, p.product_type FROM products p WHERE p.status='active' AND p.track_inventory=1 AND (SELECT COALESCE(SUM(il.available),0) FROM inventory_levels il JOIN variants v ON v.id=il.variant_id WHERE v.product_id=p.id) <= 0 AND NOT EXISTS (SELECT 1 FROM variants v WHERE v.product_id=p.id AND v.sell_out_of_stock=1) LIMIT 1")) {
    $n = $noun($p['product_type'], 'product');
    $suggest[] = ['title' => 'Keep sold-out ' . $n . 's out of view', 'text' => $p['title'] . ' is published to the Online Store with no sellable quantity, so it cannot accept orders. Hide it until stock returns.', 'btn' => 'Hide sold-out ' . $n, 'action' => 'hide', 'id' => (int)$p['id']];
}
// 3. product with a missing/short description
if ($p = t_row("SELECT id, title, product_type FROM products WHERE status<>'archived' AND CHAR_LENGTH(COALESCE(description_html,'')) < 40 ORDER BY id LIMIT 1")) {
    $n = $noun($p['product_type'], 'product');
    $suggest[] = ['title' => 'Help shoppers choose the right ' . $n, 'text' => $p['title'] . ' has little or no description. Add concrete product details so buyers can compare.', 'btn' => 'Add ' . $n . ' details', 'href' => 'products/' . $p['id']];
}
// fallbacks so the grid is always complete
$fallback = [
    ['title' => 'Add your first product', 'text' => 'Products are what you sell. Add a title, images, price and stock to start taking orders.', 'btn' => 'Add product', 'href' => 'products/new'],
    ['title' => 'Group products into collections', 'text' => 'Collections make it easier for customers to browse. Create one by hand or with automatic conditions.', 'btn' => 'Create collection', 'href' => 'collections/new'],
    ['title' => 'Create your first order', 'text' => 'Take orders by phone, chat or in person and send customers an invoice to pay.', 'btn' => 'Create order', 'href' => 'draft_orders/new'],
];
foreach ($fallback as $f) if (count($suggest) < 3) $suggest[] = $f;

/* ---------- image generation cards: products without photos (fallback: any products) ---------- */
$imgProducts = t_rows("SELECT p.id, p.title FROM products p WHERE NOT EXISTS (SELECT 1 FROM product_media m WHERE m.product_id=p.id) ORDER BY p.id LIMIT 3");

/* ---------- setup guides ---------- */
$has = fn(string $sql) => (int)t_val($sql) > 0;
$bdTasks = [$has("SELECT COUNT(*) FROM products"), $has("SELECT COUNT(*) FROM orders"), $has("SELECT COUNT(*) FROM customers WHERE address1<>''")];
$bdDone = count(array_filter($bdTasks));
$b2bTasks = [
    $has("SELECT COUNT(*) FROM products"), $has("SELECT COUNT(*) FROM customers"), $has("SELECT COUNT(*) FROM customers WHERE company<>''"),
    $has("SELECT COUNT(*) FROM collections"), $has("SELECT COUNT(*) FROM draft_orders"), $has("SELECT COUNT(*) FROM orders WHERE payment_terms IS NOT NULL"),
    $has("SELECT COUNT(*) FROM purchase_orders"), $has("SELECT COUNT(*) FROM gift_cards"),
];
$b2bDone = count(array_filter($b2bTasks));
$b2bNext = ['Add your first product', 'Add a customer', 'Add a company to a customer', 'Create a collection', 'Create a draft order', 'Create an order with payment terms', 'Create a purchase order', 'Issue a gift card'];
// array_search returns false when every task is done (index 0 is a valid "next task")
$nextIdx = array_search(false, $b2bTasks, true);
$b2bLinks = ['products/new', 'customers/new', 'customers', 'collections/new', 'draft_orders/new', 'draft_orders/new', 'purchase_orders/new', 'gift_cards/new'];
$nextTask = $nextIdx === false ? null : $b2bNext[$nextIdx];
$nextHref = $nextIdx === false ? 'customers' : $b2bLinks[$nextIdx];

$spark = '<svg class="spk" viewBox="0 0 26 12"><path d="M1 11h11l3.5-9 3.5 9"/></svg>';
$aiTag = '<div class="ai-tag"><svg viewBox="0 0 14 14" width="15" height="15"><circle cx="7" cy="7" r="6" fill="none" stroke="#7b5cf5" stroke-width="1.4"/><circle cx="7" cy="7" r="3" fill="none" stroke="#7b5cf5" stroke-width="1.4"/></svg>Amarfy AI suggestion</div>';
$ring = fn(int $done, int $total) => $done === 0 ? '<i></i>'
    : '<svg width="16" height="16" viewBox="0 0 16 16"><circle cx="8" cy="8" r="6.5" fill="none" stroke="rgba(255,255,255,.35)" stroke-width="2"/><circle cx="8" cy="8" r="6.5" fill="none" stroke="#fff" stroke-width="2" stroke-dasharray="' . round(40.8 * $done / max(1, $total), 1) . ' 40.8" transform="rotate(-90 8 8)" stroke-linecap="round"/></svg>';
function suggestion_card(array $s, string $aiTag): string {
    $btn = !empty($s['action'])
        ? '<button class="btn hbtn" type="button" data-hide="' . (int)$s['id'] . '">' . h($s['btn']) . '</button>'
        : '<a class="btn hbtn" href="' . h(url($s['href'])) . '">' . h($s['btn']) . '</a>';
    return '<article class="hcard text"><div class="body">' . $aiTag . '<h3>' . h($s['title']) . '</h3><p>' . h($s['text']) . '</p><div class="cta">' . $btn . '</div></div></article>';
}
?>
<style>
.page-home{position:relative;overflow:hidden;min-height:100%}
.home-top{padding:20px 24px 0}
.home-metric .val{gap:4px}
.home-top{position:relative;z-index:20}
.home-metrics{position:relative}
button.home-metric{background:none;border:0;padding:4px 6px;border-radius:10px;cursor:pointer;transition:opacity .15s;font:inherit;color:inherit}
.home-metrics.has-active .home-metric{opacity:.42}
.home-metrics.has-active .home-metric.active{opacity:1}
.spk polyline{fill:none;stroke:#8a8a8a;stroke-width:1.3;stroke-linejoin:round;stroke-linecap:round}
.range-btn{background:none;border:0;font:inherit;color:inherit;cursor:pointer;padding:2px 4px;margin:-2px -4px;border-radius:6px}
.range-btn:hover{background:#f1f1f1}
.metric-pop{position:absolute;top:calc(100% + 14px);left:50%;transform:translateX(-50%);width:min(845px,calc(100vw - 280px));z-index:30;background:#fff;border:1px solid #ebebeb;border-radius:18px;box-shadow:0 2px 4px rgba(0,0,0,.04),0 18px 40px rgba(0,0,0,.12);padding:26px 26px 18px;text-align:left;animation:fade .14s}
.mp-head{display:flex;align-items:flex-start;gap:12px}
.mp-val{font-size:30px;font-weight:600;letter-spacing:-.5px;line-height:1.1}
.mp-title{font-weight:550;margin-top:12px;font-size:14px}
.mp-desc{color:#4a4a4a;margin-top:4px;font-size:14px}
.mp-edit{margin-left:auto}
.mp-chart{position:relative;margin-top:18px}
.mp-chart svg{display:block;width:100%;height:auto;overflow:visible}
.mp-chart .grid line{stroke:#ececec;stroke-width:1}
.mp-chart .ylab,.mp-chart .xlab{fill:#303030;font-size:12.5px;font-family:Inter,sans-serif}
.mp-chart .cur{fill:none;stroke:#3b82d6;stroke-width:2;stroke-linejoin:round;stroke-linecap:round}
.mp-chart .prev{fill:none;stroke:#9cc3ec;stroke-width:2;stroke-dasharray:3 4;stroke-linecap:round}
.mp-chart .xhair{stroke:#8a8a8a;stroke-width:1}
.mp-chart .dot-cur{fill:#3b82d6;stroke:#fff;stroke-width:2}
.mp-chart .dot-prev{fill:#9cc3ec;stroke:#fff;stroke-width:2}
.mp-tip{position:absolute;pointer-events:none;background:#fff;border:1px solid #e3e3e3;border-radius:10px;box-shadow:0 8px 20px rgba(0,0,0,.12);padding:8px 10px;font-size:12.5px;white-space:nowrap;transform:translate(-50%,-110%)}
.mp-tip .r{display:flex;align-items:center;gap:8px;margin-top:3px}
.mp-tip i,.mp-legend i{width:9px;height:9px;border-radius:50%;display:inline-block}
.mp-legend{display:flex;justify-content:center;gap:24px;margin-top:14px;font-size:13px;color:#303030}
.mp-legend span{display:inline-flex;align-items:center;gap:8px}
.mp-empty{color:#4a4a4a;margin-top:16px;padding:14px;background:#f7f7f7;border-radius:12px}
.mp-table{margin-top:10px;font-size:12.5px}
.mp-table summary{cursor:pointer;color:#4a4a4a}
.mp-table .t{max-height:180px;overflow:auto;margin-top:6px}
.spk{width:26px;height:12px;stroke:#8a8a8a;fill:none;stroke-width:1.3;stroke-linejoin:round}
.live .dot{width:10px;height:10px;border:1.6px solid #29a86b}
.live .dot::after{display:none}
.worldmap{position:absolute;top:-40px;right:-150px;width:860px;height:760px;pointer-events:none;z-index:0}
.main.is-home .app-footer{padding-bottom:22px}
.hero{padding-top:104px}
.hero h1{font-size:29px;line-height:1.18;letter-spacing:-.5px}
.prompt{width:min(620px,100%);border-radius:18px;padding:15px 16px 12px;margin-top:30px}
.prompt textarea{height:46px;font-size:13.5px}
.chips{margin-top:16px}
.hgrid{width:min(932px,calc(100% - 48px));margin-top:72px;gap:14px}
.hcard{min-height:342px;border-radius:16px;padding:7px;position:relative}
.hcard .dismiss{position:absolute;top:14px;right:14px;z-index:3;width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.92);border:1px solid #e3e3e3;display:grid;place-items:center;opacity:0;transition:opacity .12s;color:#303030}
.hcard:hover .dismiss,.hcard .dismiss:focus{opacity:1}
.hcard .dismiss .ic{width:14px;height:14px}
.hcard.text{padding:7px}
.hcard .body{padding:12px 11px 12px}
.hcard.text .body{padding-top:13px}
.hthumb{height:140px;border-radius:11px}
.hcard h3{font-size:15.5px}
.hcard p{font-size:13px;color:#616161}
.hbtn{height:28px;border-radius:12px;font-size:12.5px;padding:0 11px}
.progress{background:rgba(48,48,48,.78);border-radius:9px;padding:5px 10px 5px 7px;font-size:12.5px}
.progress i{width:14px;height:14px}
.hthumb .progress svg{position:static;width:16px;height:16px;flex:0 0 16px}
.worldmap{opacity:.6;top:30px;right:-170px}
.hgrid .center{grid-column:2}
@media (max-width:980px){.hgrid .center{grid-column:auto}}
</style>
<div class="page-home">
  <svg class="worldmap" viewBox="0 0 860 760" aria-hidden="true">
    <defs>
      <pattern id="wmDots" width="6" height="6" patternUnits="userSpaceOnUse"><circle cx="3" cy="3" r=".95" fill="#a8a8a8"/></pattern>
      <radialGradient id="wmFade" cx=".42" cy=".42" r=".6"><stop offset="0" stop-color="#fff"/><stop offset=".72" stop-color="#fff" stop-opacity=".9"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>
      <mask id="wmMask"><circle cx="400" cy="380" r="370" fill="url(#wmFade)"/></mask>
      <radialGradient id="wmGlobe" cx=".4" cy=".38" r=".62"><stop offset="0" stop-color="#fff" stop-opacity="0"/><stop offset=".8" stop-color="#f3f3f3" stop-opacity=".7"/><stop offset="1" stop-color="#ececec" stop-opacity=".9"/></radialGradient>
    </defs>
    <circle cx="400" cy="380" r="370" fill="url(#wmGlobe)"/>
    <g mask="url(#wmMask)" fill="url(#wmDots)">
      <!-- North America -->
      <path d="M150 70c40-18 95-30 150-22 40 6 70 22 80 48 6 18-6 30-24 36-20 6-30 22-28 40 2 22-16 34-36 44-22 10-30 30-26 52 4 18-8 28-24 26-16-2-26-14-36-28-10-14-26-20-42-24-26-6-46-26-54-52-10-32-6-70 12-96 8-12 16-20 28-24z"/>
      <path d="M300 30c22-8 52-6 66 6 10 10 2 24-14 28-16 4-34 0-46-8-10-6-14-20-6-26z"/>
      <!-- Central America -->
      <path d="M268 300c14 6 24 18 28 32 4 12 14 20 24 28 6 6 2 14-6 12-14-4-26-14-34-26-8-12-14-26-16-40-2-8 0-10 4-6z"/>
      <!-- South America -->
      <path d="M330 372c30-10 66-4 92 14 26 18 46 44 48 74 2 26-14 48-32 66-18 18-24 42-34 66-10 26-26 50-44 68-8 8-18 4-18-6 0-24 6-48 6-72 0-22-12-40-24-58-14-20-20-44-14-68 4-24 14-46 40-60z"/>
      <!-- Greenland -->
      <path d="M430 34c26-10 58-8 76 6 12 10 6 28-10 36-20 10-46 8-62-4-12-10-16-30-4-38z"/>
      <!-- Europe / Africa edge -->
      <path d="M640 120c24-8 52-4 70 10 16 12 18 32 8 48-10 16-30 22-48 20-20-2-36-14-40-32-4-18-6-38 10-46z"/>
      <path d="M660 230c30-8 64 0 86 22 22 22 30 54 26 86-4 32-20 62-42 86-16 18-40 22-56 6-16-16-20-40-22-64-2-26-12-50-14-76-2-26 0-52 22-60z"/>
    </g>
  </svg>

  <div class="home-top">
    <div class="home-range"><button type="button" class="range-btn" id="rangeBtn">Last <?= $range ?> days</button> · All channels</div>
    <div class="home-metrics" id="homeMetrics">
      <button type="button" class="home-metric" data-metric="sessions"><div class="label">Sessions</div><div class="val"><?= number_format($sessions) ?> <?= mini_spark($cur['sessions']) ?></div></button>
      <button type="button" class="home-metric" data-metric="sales"><div class="label">Total sales</div><div class="val"><?= h(cur()) ?> <?= number_format((float)$s['sales'], 0) ?> <?= mini_spark($cur['sales']) ?></div></button>
      <button type="button" class="home-metric" data-metric="orders"><div class="label">Orders</div><div class="val"><?= (int)$s['n'] ?> <?= mini_spark($cur['orders']) ?></div></button>
      <button type="button" class="home-metric" data-metric="aov"><div class="label">Average order value</div><div class="val"><?= h(cur()) ?> <?= number_format($aov, 0) ?> <?= mini_spark($cur['aov']) ?></div></button>
      <div class="metric-pop" id="metricPop" hidden></div>
    </div>
    <div class="live">Live visitors <b>0</b> <span class="dot"></span></div>
  </div>

  <section class="hero">
    <h1>Hey there!<br>Let’s keep growing your business</h1>
    <div class="prompt">
      <textarea placeholder="Work with Amarfy AI" id="homePrompt" data-ai-input></textarea>
      <div class="prompt-row">
        <svg class="bot" viewBox="0 0 20 20" style="width:24px;height:24px"><circle cx="10" cy="10.5" r="8.5" fill="#2a2f8f"/><path d="M2.5 8.5c1.5-4 4.5-6 7.5-6s6 2 7.5 6c-2-1-4.5-1.4-7.5-1.4S4.5 7.5 2.5 8.5z" fill="#1b1f5e"/><rect x="3.6" y="9" width="5.6" height="4.4" rx="2" fill="#fff" stroke="#7b5cf5"/><rect x="10.8" y="9" width="5.6" height="4.4" rx="2" fill="#fff" stroke="#7b5cf5"/><circle cx="6.6" cy="11.2" r="1" fill="#1a1a1a"/><circle cx="13.6" cy="11.2" r="1" fill="#1a1a1a"/></svg>
        <span class="spacer"></span>
        <button class="p-btn" type="button" data-ai-recents><?= icon('clock') ?>Recents</button><span class="sep"></span>
        <button class="p-btn" type="button" data-ai-plus title="Quick create"><?= icon('plus') ?></button><button class="p-btn" type="button" data-ai-mic title="Voice input"><?= icon('mic') ?></button>
      </div>
    </div>
    <div class="chips">
      <a class="chip" href="<?= h(url('orders?view=unfulfilled')) ?>">Fulfill orders <span class="n"><?= $toFulfill ?></span></a>
      <a class="chip" href="<?= h(url('orders?view=unpaid')) ?>">Capture payments <span class="n"><?= $toCapture ?></span></a>
    </div>
  </section>

  <?php
  $dismissed = json_decode((string)setting('dismissed_cards', '[]'), true) ?: [];
  $show = fn(string $k) => !in_array($k, $dismissed, true);
  $x = fn(string $k) => '<button class="dismiss" type="button" data-dismiss="' . h($k) . '" aria-label="Dismiss">' . icon('x') . '</button>';
  ?>
  <section class="hgrid">
    <!-- Row 1 -->
    <?php if ($show('optimize')): ?>
    <article class="hcard"><?= $x('optimize') ?>
      <div class="hthumb" style="background:#ececec"><span class="progress"><?= $ring($bdDone, 3) ?><?= $bdDone ?> task<?= $bdDone === 1 ? '' : 's' ?> completed</span>
        <svg viewBox="0 0 400 200" preserveAspectRatio="xMidYMid slice"><defs><linearGradient id="mp" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fafafa"/><stop offset="1" stop-color="#dedede"/></linearGradient></defs>
          <path d="M120 112l60-22 50 16 60-20 34 50-58 24-52-14-60 22z" fill="url(#mp)" stroke="#d2d2d2"/><path d="M180 90l12 64M230 106l8 62" stroke="#d6d6d6"/>
          <path d="M140 128c20-14 38 6 58-2s36-18 56-8 30 2 44-8" fill="none" stroke="#a9a9a9" stroke-width="1.6" stroke-dasharray="4 4"/>
          <g transform="translate(118 58)"><rect width="56" height="26" rx="8" fill="#e9e9e9" stroke="#c9c9c9"/><rect x="8" y="-10" width="36" height="14" rx="5" fill="#f2f2f2" stroke="#c9c9c9"/><circle cx="12" cy="27" r="6" fill="#bdbdbd"/><circle cx="44" cy="27" r="6" fill="#bdbdbd"/></g>
          <path d="M282 64c0-10 18-10 18 0 0 9-9 18-9 18s-9-9-9-18z" fill="#3dbb74"/><circle cx="291" cy="64" r="3.2" fill="#d8f5e4"/>
          <g transform="translate(270 150)"><rect width="30" height="26" rx="5" fill="#d9d9d9" stroke="#bdbdbd"/><path d="M7 0v-8a8 8 0 0 1 16 0v8" fill="none" stroke="#c4c4c4" stroke-width="4"/><circle cx="15" cy="13" r="3" fill="#9e9e9e"/></g></svg></div>
      <div class="body"><h3>Optimize your store in <?= h(setting('market', 'Bangladesh')) ?></h3><p>Get set up to sell with Amarfy AI's step-by-step guidance, customized for your region.</p><div class="cta"><a class="btn hbtn" href="<?= h(url($bdTasks[0] ? ($bdTasks[1] ? 'customers' : 'draft_orders/new') : 'products/new')) ?>">Get started</a></div></div>
    </article>
    <?php endif; ?>
    <?= suggestion_card($suggest[0], $aiTag) ?>
    <?php if ($show('launch')): ?>
    <article class="hcard"><?= $x('launch') ?>
      <div class="hthumb" style="background:linear-gradient(180deg,#a4bfd8 0%,#c3d6e6 55%,#d8e4ee 100%)">
        <svg viewBox="0 0 400 200" preserveAspectRatio="xMidYMid slice"><defs><radialGradient id="cl" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#fff"/><stop offset=".7" stop-color="#eef2f5"/><stop offset="1" stop-color="#e3e9ee" stop-opacity="0"/></radialGradient><linearGradient id="rkb" x1="0" x2="1"><stop offset="0" stop-color="#f2f5f2"/><stop offset=".45" stop-color="#b7c4ba"/><stop offset="1" stop-color="#5f7564"/></linearGradient></defs>
          <rect x="120" y="44" width="180" height="150" rx="10" fill="#eef2f6" opacity=".95"/><rect x="120" y="44" width="180" height="20" rx="10" fill="#f7f9fb"/>
          <circle cx="134" cy="54" r="3" fill="#e5534b"/><circle cx="144" cy="54" r="3" fill="#f0a531"/><circle cx="154" cy="54" r="3" fill="#29a86b"/>
          <circle cx="150" cy="178" r="60" fill="url(#cl)"/><circle cx="110" cy="190" r="48" fill="url(#cl)"/><circle cx="200" cy="186" r="44" fill="url(#cl)"/><circle cx="175" cy="150" r="34" fill="url(#cl)"/>
          <path d="M190 160c14-20 32-42 52-58" stroke="#f4f6f8" stroke-width="18" stroke-linecap="round" opacity=".9"/>
          <g transform="translate(272 84) rotate(40)"><path d="M0-56C16-38 18-6 14 24H-14C-18-6-16-38 0-56z" fill="url(#rkb)" stroke="#55695a" stroke-width="1"/><circle cy="-16" r="7" fill="#2f6b4a" stroke="#e5ece7" stroke-width="2.2"/><path d="M-14 8-28 30-13 24zM14 8l14 22-15-6z" fill="#4d7a5d"/><path d="M-8 24 0 40 8 24z" fill="#f6b04a"/></g></svg></div>
      <div class="body"><h3>Launch your online store</h3><p>Ready to open for business? Take your store public and start welcoming customers.</p><div class="cta"><a class="btn hbtn" href="<?= h(url('online_store')) ?>">Launch store</a></div></div>
    </article>
    <?php endif; ?>

    <!-- Row 2 -->
    <?php if ($show('domain')): ?>
    <article class="hcard"><?= $x('domain') ?>
      <div class="hthumb" style="background:#efefef">
        <svg viewBox="0 0 400 200" preserveAspectRatio="xMidYMid slice"><rect x="56" y="58" width="290" height="170" rx="38" fill="#f6f6f6" stroke="#dcdcdc"/><rect x="68" y="72" width="268" height="54" rx="27" fill="#fff" style="filter:drop-shadow(0 6px 10px rgba(0,0,0,.12))"/><rect x="88" y="92" width="182" height="14" rx="7" fill="#dcdcdc"/><text x="278" y="106" font-family="Inter,sans-serif" font-size="19" fill="#1a1a1a">.com</text><path d="M306 112l7 50 11-14 18 13 4-6-16-12 18-6z" fill="#1f1f1f" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/></svg></div>
      <div class="body"><h3>Get a custom domain</h3><p>Give your store a branded URL that's easy to find, trust, and remember.</p><div class="cta"><a class="btn hbtn" href="<?= h(url('settings')) ?>">Set up domain</a></div></div>
    </article>
    <?php endif; ?>
    <?= suggestion_card($suggest[1], $aiTag) ?>
    <?= suggestion_card($suggest[2], $aiTag) ?>

    <!-- Row 3: image generation -->
    <?php
    // Always three cards: products without photos first, then other products, then a generic prompt.
    $imgCards = $imgProducts;
    if (count($imgCards) < 3) {
        $have = array_column($imgCards, 'id') ?: [0];
        $more = t_rows("SELECT id, title FROM products WHERE id NOT IN (" . implode(',', array_map('intval', $have)) . ") ORDER BY id LIMIT " . (3 - count($imgCards)));
        $imgCards = array_merge($imgCards, $more);
    }
    while (count($imgCards) < 3) $imgCards[] = ['id' => 0, 'title' => count($imgCards) === 0 ? 'your first product' : (count($imgCards) === 1 ? 'your next product' : 'a gift card')];
    foreach ($imgCards as $i => $p): if (!$show('img_' . $p['id'] . '_' . $i)) continue; ?>
    <article class="hcard"><?= $x('img_' . $p['id'] . '_' . $i) ?>
      <div class="hthumb" style="background:linear-gradient(105deg,#0f0b24 0%,#1b1448 45%,#3b26b0 100%)">
        <svg viewBox="0 0 400 200" preserveAspectRatio="xMidYMid slice"><defs><pattern id="ck<?= $i ?>" width="16" height="16" patternUnits="userSpaceOnUse"><rect width="16" height="16" fill="#fff"/><rect width="8" height="8" fill="#ececec"/><rect x="8" y="8" width="8" height="8" fill="#ececec"/></pattern>
          <linearGradient id="lp<?= $i ?>" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#8fd8f6"/><stop offset=".65" stop-color="#3497c9"/><stop offset="1" stop-color="#1d6d99"/></linearGradient>
          <linearGradient id="st<?= $i ?>" x1="0" x2="1"><stop offset="0" stop-color="#1f6f95"/><stop offset=".5" stop-color="#9ad9f0"/><stop offset="1" stop-color="#1f6f95"/></linearGradient></defs>
          <rect x="44" y="28" width="226" height="200" rx="16" fill="url(#ck<?= $i ?>)"/>
          <path d="M152 104h14l6 100h-26z" fill="url(#st<?= $i ?>)"/>
          <path d="M100 100c0-44 116-44 116 0 0 8-116 8-116 0z" fill="url(#lp<?= $i ?>)"/><ellipse cx="158" cy="102" rx="58" ry="7" fill="#c3ecfa"/>
          <path d="M122 80c8-12 26-17 40-16" stroke="#fff" stroke-opacity=".75" stroke-width="4" fill="none" stroke-linecap="round"/>
          <path d="M30 60l4 11 11 4-11 4-4 11-4-11-11-4 11-4zM346 140l4 12 12 4-12 4-4 12-4-12-12-4 12-4z" fill="#d6bdff"/></svg></div>
      <div class="body"><h3>Create stunning images for<br>your products</h3><p>Try out Amarfy AI's image generation for <?= h($p['title']) ?>.</p><div class="cta"><a class="btn hbtn" href="<?= h(url($p['id'] ? 'products/' . $p['id'] : 'products/new')) ?>">Generate image</a></div></div>
    </article>
    <?php endforeach; ?>

    <!-- Row 4: B2B guide -->
    <?php if ($show('b2b')): ?>
    <article class="hcard center"><?= $x('b2b') ?>
      <div class="hthumb" style="background:#a3e5d2"><span class="progress"><?= $ring($b2bDone, 8) ?><?= $b2bDone ?> of 8 tasks completed</span>
        <svg viewBox="0 0 400 200" preserveAspectRatio="xMidYMid slice"><path d="M110 60l70-14 60 20 70-16 14 128-72 14-58-18-72 16z" fill="#c8f2e5"/><path d="M180 46l10 132M240 66l6 126" stroke="#a5ddcb"/>
          <path d="M150 64c30 26 60 6 80 30s-10 40 20 54 50-20 70-40" fill="none" stroke="#5cbfa2" stroke-width="3" stroke-dasharray="7 7"/>
          <path d="M120 150c30-10 50 10 80-6s40-40 70-50 40-30 60-50" fill="none" stroke="#2f8f73" stroke-width="3"/>
          <rect x="40" y="62" width="96" height="96" rx="14" fill="#4fae8c"/><path d="M64 92h48l-4 14H68z" fill="#111"/><rect x="70" y="106" width="36" height="28" fill="#111"/><rect x="82" y="116" width="12" height="18" fill="#4fae8c"/>
          <circle cx="324" cy="58" r="42" fill="#4fae8c"/><rect x="304" y="50" width="40" height="28" rx="6" fill="#111"/><path d="M315 50v-6h18v6" fill="none" stroke="#111" stroke-width="4"/><path d="M304 62h40" stroke="#4fae8c" stroke-width="2"/>
          <path d="M228 98l32-14-14 32-4-14z" fill="#111"/></svg></div>
      <div class="body"><h3>Sell B2B and DTC, in one place</h3><p><?= $nextTask === null ? 'All setup tasks are complete. Manage your B2B customers and companies.' : 'Next task: ' . h($nextTask) ?></p><div class="cta"><a class="btn hbtn" href="<?= h(url($nextHref)) ?>"><?= $nextTask === null ? 'View customers' : ($b2bDone ? 'Resume' : 'Get started') ?></a></div></div>
    </article>
    <?php endif; ?>
  </section>
  <?php if ($dismissed): ?><div style="text-align:center;margin:-16px 0 30px"><button class="btn plain" type="button" id="restoreCards">Show <?= count($dismissed) ?> hidden card<?= count($dismissed) === 1 ? '' : 's' ?></button></div><?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-hide]').forEach(b => b.onclick = async () => {
    await UI.api('products/bulk', {action: 'draft', ids: [b.dataset.hide]});
    UI.toast('Product hidden from your store'); setTimeout(() => location.reload(), 500);
  });
  document.querySelectorAll('[data-dismiss]').forEach(b => b.onclick = async e => {
    e.preventDefault(); e.stopPropagation();
    const card = b.closest('.hcard');
    card.style.transition = 'opacity .18s'; card.style.opacity = '0';
    await UI.api('home/dismiss', {card: b.dataset.dismiss});
    setTimeout(() => location.reload(), 200);
  });
  const rc = document.getElementById('restoreCards');
  if (rc) rc.onclick = async () => { await UI.api('home/restore', {}); location.reload(); };
});
</script>
<script>window.HOME_CHART = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php $GLOBALS['PAGE_SCRIPTS'][] = 'homechart.js'; ?>
