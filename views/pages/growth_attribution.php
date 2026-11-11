<?php
$PAGE_TITLE = 'Attribution';
$range = in_array((int)($_GET['range'] ?? 0), [7, 30, 90], true) ? (int)$_GET['range'] : 30;
$g = growth_data($range);
$labels = ['direct' => 'Direct', 'draft' => 'Draft orders', 'email' => 'Email', 'social' => 'Social', 'search' => 'Search', 'sms' => 'SMS', 'affiliate' => 'Affiliate', 'paid' => 'Paid ads', 'organic' => 'Organic search', 'referral' => 'Referral'];
$total = array_sum(array_column($g['bySource'], 'sales'));
uasort($g['bySource'], fn($a, $b) => $b['sales'] <=> $a['sales']);
$byCampaign = t_rows("SELECT c.name, c.channel, COUNT(o.id) n, COALESCE(SUM(o.total),0) s FROM campaigns c LEFT JOIN orders o ON o.campaign_id=c.id AND o.cancelled_at IS NULL AND o.created_at >= ? GROUP BY c.id ORDER BY s DESC", [$g['from']]);
$sel = '<label class="top-select"><select onchange="location.href=\'?range=\'+this.value">' . implode('', array_map(fn($r) => '<option value="' . $r . '"' . ($r === $range ? ' selected' : '') . '>Last ' . $r . ' days</option>', [7, 30, 90])) . '</select>' . icon('updown') . '</label>';
echo topbar('growth', [['Attribution', null]], '', null, $sel);
?>
<div class="page">
  <div class="card card-pad" style="margin-bottom:14px"><div style="font-weight:550">Sales by source</div><div class="muted" style="margin:4px 0 10px"><?= h($g['curLabel']) ?> · <?= h(money($total, true)) ?> total</div>
    <table class="grid"><thead><tr><th>Source</th><th class="num">Orders</th><th class="num">Sales</th><th style="width:40%">Share</th></tr></thead><tbody>
    <?php if (!$g['bySource']): ?><tr class="empty-row"><td colspan="4">No orders in this period</td></tr><?php endif; ?>
    <?php foreach ($g['bySource'] as $src => $r): $pct = $total ? $r['sales'] / $total * 100 : 0; ?>
      <tr><td class="strong"><?= h($labels[$src] ?? ucfirst($src)) ?><?= in_array($src, MARKETING_SOURCES, true) ? ' ' . pill('Marketing', 'blue') : '' ?></td><td class="num"><?= $r['orders'] ?></td><td class="num"><?= h(money($r['sales'])) ?></td>
        <td><div style="display:flex;align-items:center;gap:10px"><div style="flex:1;height:8px;background:#f1f1f1;border-radius:4px"><div style="width:<?= round($pct, 1) ?>%;height:8px;background:#3b82d6;border-radius:4px"></div></div><span style="width:44px;text-align:right"><?= number_format($pct, 1) ?>%</span></div></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <div class="card card-pad"><div style="font-weight:550;margin-bottom:10px">Sales by campaign</div>
    <table class="grid"><thead><tr><th>Campaign</th><th>Channel</th><th class="num">Orders</th><th class="num">Sales</th></tr></thead><tbody>
    <?php if (!$byCampaign): ?><tr class="empty-row"><td colspan="4">No campaigns yet. <a class="link" href="<?= h(url('growth/campaigns')) ?>">Create a campaign</a></td></tr><?php endif; ?>
    <?php foreach ($byCampaign as $c): ?><tr><td class="strong"><?= h($c['name']) ?></td><td><?= h(ucfirst($c['channel'])) ?></td><td class="num"><?= (int)$c['n'] ?></td><td class="num"><?= h(money($c['s'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
