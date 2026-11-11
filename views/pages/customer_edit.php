<?php
$c = null;
if (is_int($id)) {
    $c = t_row("SELECT * FROM customers WHERE id=?", [$id]);
    if (!$c) { echo topbar('customers', [['Customers', 'customers']], '', 'customers') . '<div class="page-pad">' . empty_state('', 'Customer not found', 'It may have been deleted.', 'Back', 'customers') . '</div>'; return; }
}
$PAGE_TITLE = $c ? customer_name($c) : 'New customer';
$orders = $c ? t_rows("SELECT * FROM orders WHERE customer_id=? ORDER BY id DESC LIMIT 20", [$id]) : [];
$f = fn($k) => h($c[$k] ?? ($k === 'country' ? 'Bangladesh' : ''));
echo topbar('customers', [['Customers', 'customers'], [$c ? customer_name($c) : 'New customer', null]], '', 'customers');
?>
<form class="form-layout" id="custForm" autocomplete="off">
  <div>
    <h1 class="page-h1"><?= $c ? h(customer_name($c)) : 'New customer' ?></h1>
    <div class="sec-head"><h2>Customer overview</h2></div>
    <div class="card card-pad" style="display:flex;flex-direction:column;gap:14px">
      <div class="row2"><label class="field"><span>First name</span><input name="first_name" value="<?= $f('first_name') ?>"></label><label class="field"><span>Last name</span><input name="last_name" value="<?= $f('last_name') ?>"></label></div>
      <label class="field"><span>Email</span><input name="email" type="email" value="<?= $f('email') ?>"></label>
      <label class="field"><span>Phone number</span><input name="phone" value="<?= $f('phone') ?>"></label>
      <label class="check"><input type="checkbox" class="cb" name="accepts_marketing" <?= !empty($c['accepts_marketing']) ? 'checked' : '' ?>>Customer agreed to receive marketing emails.</label>
    </div>
    <div class="sec-head" style="margin-top:30px"><h2>Default address</h2></div>
    <div class="card card-pad" style="display:flex;flex-direction:column;gap:14px">
      <label class="field"><span>Country/region</span><input name="country" value="<?= $f('country') ?>"></label>
      <label class="field"><span>Company</span><input name="company" value="<?= $f('company') ?>"></label>
      <label class="field"><span>Address</span><input name="address1" value="<?= $f('address1') ?>"></label>
      <label class="field"><span>Apartment, suite, etc.</span><input name="address2" value="<?= $f('address2') ?>"></label>
      <div class="row2"><label class="field"><span>City</span><input name="city" value="<?= $f('city') ?>"></label><label class="field"><span>Postal code</span><input name="zip" value="<?= $f('zip') ?>"></label></div>
    </div>
    <?php if ($c): ?>
    <div class="sec-head" style="margin-top:30px"><h2>Orders</h2></div>
    <div class="card"><?php if (!$orders): ?><div class="card-pad muted">This customer hasn’t placed any orders yet.</div><?php else: ?>
      <table class="grid"><tbody><?php foreach ($orders as $o): ?><tr data-href="<?= h(url('orders/' . $o['id'])) ?>"><td class="strong">#<?= (int)$o['number'] ?></td><td><?= h(friendly_date($o['created_at'])) ?></td><td><?= status_pill($o['payment_status']) ?></td><td><?= status_pill($o['fulfillment_status']) ?></td><td class="num"><?= h(money($o['total'])) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
    <div class="sec-head" style="margin-top:30px"><h2>Timeline</h2></div><?= timeline_html('customer', (int)$c['id']) ?>
    <?php endif; ?>
  </div>
  <aside class="side">
    <div class="side-sec"><div class="side-head">Notes</div><textarea class="input ta" name="note" placeholder="Add a note about this customer"><?= $f('note') ?></textarea></div>
    <div class="side-sec"><div class="side-head">Tags</div><input class="input" name="tags" value="<?= $f('tags') ?>" placeholder="VIP, wholesale"></div>
  </aside>
</form>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('custForm'), id = <?= $c ? (int)$c['id'] : 0 ?>;
  const data = () => { const d = Object.fromEntries(new FormData(form)); d.accepts_marketing = form.accepts_marketing.checked ? 1 : 0; d.id = id; return d; };
  let snap = JSON.stringify(data());
  const bar = UI.saveBar({label: id ? 'Unsaved changes' : 'Unsaved customer', onSave: async () => { const r = await UI.api('customers/save', data()); snap = JSON.stringify(data()); bar.hide(); location.href = UI.url('customers/' + r.customer.id); }, onDiscard: () => location.reload()});
  form.addEventListener('input', () => JSON.stringify(data()) !== snap ? bar.show() : bar.hide());
  form.addEventListener('change', () => JSON.stringify(data()) !== snap ? bar.show() : bar.hide());
  form.addEventListener('submit', e => e.preventDefault());
});
</script>
<?php $GLOBALS['PAGE_SCRIPTS'][] = 'timeline.js'; ?>
