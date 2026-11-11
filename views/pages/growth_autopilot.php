<?php
$PAGE_TITLE = 'Autopilot';
$channels = ['email' => ['Email', 'Send win-back and new-arrival emails to subscribers automatically.'], 'social' => ['Social media', 'Promote best sellers to followers with rotating posts.'],
    'search' => ['Search ads', 'Keep top products visible for high-intent searches.'], 'sms' => ['SMS', 'Remind customers about abandoned carts by text.'],
    'affiliate' => ['Affiliate', 'Track sales referred by partners and influencers.']];
$subs = (int)t_val("SELECT COUNT(*) FROM customers WHERE accepts_marketing=1");
echo topbar('growth', [['Autopilot', null]]);
?>
<div class="form-layout single" style="padding-left:180px;padding-right:180px">
  <div>
    <h1 class="page-h1">Campaign Autopilot</h1>
    <p class="page-sub">Choose the channels Autopilot manages. Each active channel creates and tracks a campaign so its sales appear under Growth → Attribution.</p>
    <div class="card">
      <?php foreach ($channels as $k => [$label, $desc]): $on = setting('autopilot_' . $k) === '1'; ?>
      <div class="card-sec" style="display:flex;align-items:center;gap:16px">
        <div style="flex:1"><b><?= h($label) ?></b><div class="muted" style="margin-top:2px"><?= h($desc) ?><?= $k === 'email' ? ' · ' . $subs . ' subscriber' . ($subs === 1 ? '' : 's') : '' ?></div></div>
        <?= $on ? pill('On', 'green') : pill('Off') ?>
        <input type="checkbox" class="toggle" data-ch="<?= h($k) ?>" <?= $on ? 'checked' : '' ?> aria-label="Autopilot for <?= h($label) ?>">
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-ch]').forEach(t => t.onchange = async () => {
    await UI.api('growth/autopilot', {channel: t.dataset.ch, on: t.checked ? 1 : 0});
    if (t.checked) await UI.api('growth/campaign_save', {name: 'Autopilot – ' + t.closest('.card-sec').querySelector('b').textContent, channel: t.dataset.ch, status: 'active', autopilot: 1, starts_on: new Date().toISOString().slice(0, 10)});
    UI.toast(t.checked ? 'Autopilot turned on' : 'Autopilot turned off'); setTimeout(() => location.reload(), 300);
  });
});
</script>
