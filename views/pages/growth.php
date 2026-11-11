<?php
$PAGE_TITLE = 'Growth';
$range = in_array((int)($_GET['range'] ?? 0), [7, 30, 90], true) ? (int)$_GET['range'] : 30;
$g = growth_data($range);
$total = array_sum($g['cur']['sales']); $mkt = array_sum($g['cur']['marketing']);
$sessions = array_sum($g['traffic']);
$storeOrders = (int)t_val("SELECT COUNT(*) FROM orders WHERE cancelled_at IS NULL AND created_at >= ?", [$g['from']]);
$direct = $g['bySource']['direct'] ?? ['orders' => 0, 'sales' => 0];
$campaigns = t_rows("SELECT c.*, (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.campaign_id=c.id AND o.cancelled_at IS NULL) sales FROM campaigns c ORDER BY c.id DESC LIMIT 5");
$anyAutopilot = (bool)t_val("SELECT COUNT(*) FROM settings WHERE k LIKE 'autopilot\_%' AND v='1'");
echo topbar('growth', [['Growth', null]]);
?>
<div class="page" style="padding-top:8px">
  <?php if (!setting('growth_banner_dismissed')): ?>
  <section class="gr-hero" id="grHero">
    <div style="flex:1">
      <h1>Run your marketing on autopilot</h1>
      <p>Campaign Autopilot promotes your products across your connected channels and keeps refining the plan while you stay in control.</p>
      <div style="display:flex;gap:8px;margin-top:18px"><a class="btn primary" style="height:34px;border-radius:12px" href="<?= h(url('growth/autopilot')) ?>">Get started</a><button class="chip-btn" type="button" data-help="marketing">Learn more</button></div>
    </div>
    <svg viewBox="0 0 260 170" width="260" aria-hidden="true"><rect x="10" y="60" width="130" height="90" rx="10" fill="#2b3035"/><text x="22" y="82" fill="#fff" font-size="10" font-family="Inter">Sales by channel</text><text x="22" y="104" fill="#fff" font-size="14" font-weight="700" font-family="Inter"><?= h(number_format($mkt)) ?></text><polyline points="22,135 45,128 66,131 88,118 108,122 128,108" fill="none" stroke="#5ad19a" stroke-width="2"/><g transform="translate(150 20) rotate(18)"><rect width="90" height="34" rx="16" fill="#1f3d2c"/><rect x="6" y="6" width="34" height="22" rx="10" fill="#0f2418"/><rect x="50" y="6" width="34" height="22" rx="10" fill="#0f2418"/></g></svg>
    <button class="chip-btn dismiss" type="button" id="grDismiss"><?= icon('x') ?> Dismiss</button>
  </section>
  <?php endif; ?>

  <div class="sec-head"><h2 style="font-size:15px">Marketing performance</h2><div class="acts">
    <label class="top-select" style="background:#f1f1f1;border:0"><?= icon('calendar') ?><select onchange="location.href='?range='+this.value" style="padding-left:6px"><?php foreach ([7, 30, 90] as $r): ?><option value="<?= $r ?>" <?= $r === $range ? 'selected' : '' ?>>Last <?= $r ?> days</option><?php endforeach; ?></select><?= icon('chev-down') ?></label>
    <a class="btn plain" href="<?= h(url('growth/attribution?range=' . $range)) ?>">View details</a></div></div>

  <div class="card card-pad">
    <div style="font-weight:550;font-size:14px">Sales attributed to marketing</div>
    <div style="margin:6px 0 14px"><span style="font-size:26px;font-weight:600"><?= h(cur()) ?> <?= number_format($mkt, 2) ?></span> <span>of <b><?= h(cur()) ?> <?= number_format($total, 2) ?></b> total store sales</span></div>
    <div id="grChart"></div>
  </div>

  <div class="gr-cards">
    <div class="card card-pad gr-card">
      <div style="font-weight:550">Sessions by traffic type</div>
      <?php if ($sessions): ?>
        <div class="gr-legend" style="margin-top:12px"><?php foreach (TRAFFIC_TYPES as $k => [$l, $c]): if (empty($g['traffic'][$k])) continue; ?><span><span><i style="background:<?= $c ?>"></i><?= h(strtolower($l)) ?></span><b><?= number_format($g['traffic'][$k]) ?></b></span><?php endforeach; ?></div>
        <div class="gr-stack" role="img" aria-label="Sessions by traffic type"><?php foreach (TRAFFIC_TYPES as $k => [$l, $c]): if (empty($g['traffic'][$k])) continue; ?><div style="flex:<?= $g['traffic'][$k] ?>;background:<?= $c ?>" title="<?= h($l . ': ' . number_format($g['traffic'][$k])) ?>"></div><?php endforeach; ?></div>
      <?php else: ?><p class="muted" style="margin-top:auto">Session data appears once your online store receives visitors.</p><?php endif; ?>
    </div>
    <div class="card card-pad gr-card">
      <div style="display:flex;justify-content:space-between"><span style="display:flex;gap:8px;align-items:center;font-weight:550"><span class="logo" style="width:18px;height:20px;font-size:11px;border-radius:4px">a</span>Online Store</span><span class="muted"><?= number_format($sessions) ?> sessions</span></div>
      <div class="big"><?= h(cur()) ?> <?= number_format($total, 2) ?></div>
      <div class="muted">Order value for <b><?= $storeOrders ?> orders</b><?= $sessions ? ' at <b>' . number_format(min(100, $storeOrders / $sessions * 100), 1) . '%</b> conversion rate' : '' ?>.</div>
    </div>
    <div class="card card-pad gr-card">
      <div style="display:flex;justify-content:space-between"><span style="display:flex;gap:8px;align-items:center;font-weight:550"><?= icon('store') ?>Direct</span><span class="muted"><?= number_format($g['traffic']['direct'] ?? 0) ?> sessions</span></div>
      <div class="big"><?= h(cur()) ?> <?= number_format($direct['sales'], 2) ?></div>
      <div class="muted"><?= $direct['orders'] ?> direct order<?= $direct['orders'] === 1 ? '' : 's' ?>.</div>
    </div>
  </div>

  <div class="sec-head" style="margin-top:26px"><h2 style="font-size:15px">Campaign Autopilot</h2></div>
  <div class="card card-pad" style="display:flex;align-items:center;gap:12px"><span><?= $anyAutopilot ? 'Autopilot is on for some channels. Review which channels it manages.' : 'Connect channels to automatically promote your products with managed campaigns.' ?></span><a class="chip-btn" style="margin-left:auto" href="<?= h(url('growth/autopilot')) ?>"><?= $anyAutopilot ? 'Manage' : 'Get started' ?></a></div>

  <div class="sec-head" style="margin-top:26px"><h2 style="font-size:15px">Recent campaigns</h2><?php if ($campaigns): ?><div class="acts"><a class="btn plain" href="<?= h(url('growth/campaigns')) ?>">View all</a></div><?php endif; ?></div>
  <?php if (!$campaigns): ?>
    <div class="card card-pad" style="display:flex;align-items:center;gap:12px"><span>Create campaigns to evaluate how marketing initiatives drive business goals.</span><button class="chip-btn" style="margin-left:auto" type="button" data-new-campaign>Create campaign</button></div>
  <?php else: ?>
    <div class="card"><table class="grid"><thead><tr><th>Campaign</th><th>Channel</th><th>Status</th><th>Dates</th><th class="num">Attributed sales</th></tr></thead><tbody>
      <?php foreach ($campaigns as $c): ?><tr data-href="<?= h(url('growth/campaigns#c' . $c['id'])) ?>"><td class="strong"><?= h($c['name']) ?></td><td><?= h(ucfirst($c['channel'])) ?></td><td><?= pill(ucfirst($c['status']), ['active' => 'green', 'paused' => 'yellow', 'draft' => 'blue'][$c['status']] ?? 'neutral') ?></td><td><?= $c['starts_on'] ? h(date('M j', strtotime($c['starts_on']))) . ($c['ends_on'] ? ' – ' . h(date('M j', strtotime($c['ends_on']))) : '') : '—' ?></td><td class="num"><?= h(money($c['sales'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const G = <?= json_encode(['cur' => $g['cur']['marketing'], 'prev' => $g['prev']['marketing'], 'dates' => $g['cur']['dates'], 'prevDates' => $g['prev']['dates'], 'curLabel' => $g['curLabel'], 'prevLabel' => $g['prevLabel']]) ?>;
  LineChart.render(document.getElementById('grChart'), Object.assign(G, {money: true, symbol: <?= json_encode(sym()) ?>, label: 'Sales attributed to marketing', height: 300}));
  const d = document.getElementById('grDismiss');
  if (d) d.onclick = async () => { await UI.api('home/setting', {key: 'growth_banner_dismissed'}); document.getElementById('grHero').remove(); };
  document.querySelectorAll('[data-new-campaign]').forEach(b => b.onclick = () => window.campaignModal());
});
</script>
<?php $GLOBALS['PAGE_SCRIPTS'][] = 'linechart.js'; $GLOBALS['PAGE_SCRIPTS'][] = 'campaigns.js'; ?>
