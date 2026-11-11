<?php
$PAGE_TITLE = 'Abandoned checkouts';
$view = ($_GET['status'] ?? 'incomplete') === 'complete' ? 'complete' : 'incomplete';
$rows = t_rows("SELECT ch.*, c.first_name, c.last_name FROM checkouts ch LEFT JOIN customers c ON c.id=ch.customer_id WHERE ch.completed=? ORDER BY ch.created_at DESC LIMIT 250", [$view === 'complete' ? 1 : 0]);
$sel = '<label class="top-select"><select onchange="location.href=\'?status=\'+this.value"><option value="incomplete"' . ($view === 'incomplete' ? ' selected' : '') . '>Incomplete</option><option value="complete"' . ($view === 'complete' ? ' selected' : '') . '>Complete</option></select>' . icon('chev-down') . '</label>';
echo topbar('cart', [['Abandoned checkouts', null]], '', null, $sel);
?>
<div class="page">
  <div class="banner info" id="autoBanner"><?= icon('info') ?><span><b>Upgrade to the new abandoned checkout automation</b>&nbsp; A new abandoned checkout automation is available. The new version features improved email editing and workflow customization.</span><button class="chip-btn b-act" style="background:#cbe9f7" type="button" onclick="UI.toast('Abandoned checkout emails are enabled'); document.getElementById('autoBanner').remove()">View automation</button></div>
  <?php if (!$rows): ?>
  <div class="card" style="display:flex;align-items:center;justify-content:space-between;padding:60px 140px 60px 200px;gap:30px;flex-wrap:wrap">
    <div style="max-width:640px"><h2 style="font-size:24px;font-weight:550;letter-spacing:-.3px;margin-bottom:12px">Abandoned checkouts will show here</h2><p style="font-size:14px;line-height:1.7">See when customers put an item in their cart but don’t check out. You can also email customers a link to their cart.</p></div>
    <svg viewBox="0 0 150 140" width="150"><circle cx="78" cy="72" r="58" fill="#e6e6e6"/><path d="M10 26h18l18 66h70l12-44H40" fill="none" stroke="#3c7a5a" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/><rect x="48" y="40" width="50" height="34" rx="4" fill="#bdbdbd"/><rect x="62" y="30" width="28" height="18" rx="3" fill="#9e9e9e"/><circle cx="56" cy="114" r="8" fill="#3c7a5a"/><circle cx="104" cy="114" r="8" fill="#3c7a5a"/><circle cx="120" cy="34" r="27" fill="#e07a5f"/><path d="M111 25l18 18M129 25l-18 18" stroke="#fff" stroke-width="5" stroke-linecap="round"/></svg>
  </div>
  <?php else: ?>
  <table class="grid"><thead><tr><th>Checkout</th><th>Date</th><th>Placed by</th><th>Email status</th><th>Recovery status</th><th class="num">Total</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td class="strong">#<?= (int)$r['id'] ?></td><td><?= h(friendly_date($r['created_at'])) ?></td><td><?= h(customer_name($r) ?: $r['email']) ?></td><td><?= h($r['email_status']) ?></td><td><?= $r['recovery_status'] === 'recovered' ? pill('Recovered', 'green') : pill('Not recovered', 'neutral') ?></td><td class="num"><?= h(money($r['total'])) ?></td></tr><?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>
  <?= learn_more('abandoned checkouts') ?>
</div>
