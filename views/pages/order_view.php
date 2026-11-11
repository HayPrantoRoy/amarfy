<?php
$o = is_int($id) ? t_row("SELECT * FROM orders WHERE id=?", [$id]) : null;
if (!$o) { $PAGE_TITLE = 'Not found'; echo topbar('orders', [['Orders', 'orders']], '', 'orders') . '<div class="page-pad">' . empty_state('', 'Order not found', 'This order may have been deleted.', 'Back to orders', 'orders') . '</div>'; return; }
$PAGE_TITLE = 'Order #' . $o['number'];
$items = t_rows("SELECT * FROM order_items WHERE order_id=?", [$id]);
$c = $o['customer_id'] ? t_row("SELECT * FROM customers WHERE id=?", [(int)$o['customer_id']]) : null;
$addr = json_decode((string)$o['shipping_address_json'], true) ?: ($c ? $c : null);
$count = array_sum(array_map(fn($i) => (int)$i['quantity'], $items));
$prev = t_val("SELECT id FROM orders WHERE id < ? ORDER BY id DESC LIMIT 1", [$id]);
$next = t_val("SELECT id FROM orders WHERE id > ? ORDER BY id LIMIT 1", [$id]);
$nav = prev_next('orders', $prev, $next);
echo topbar('orders', [['Orders', 'orders'], ['#' . $o['number'], null]],
    '<button class="btn" id="oPrint" type="button">Print</button><button class="btn" id="oMore" type="button">More actions ' . icon('chev-down') . '</button>' . $nav, 'orders');
?>
<div class="form-layout">
  <div>
    <h1 class="page-h1" style="display:flex;align-items:center;gap:10px">#<?= (int)$o['number'] ?> <?= status_pill($o['payment_status']) ?> <?= status_pill($o['fulfillment_status']) ?> <?= $o['archived'] ? pill('Archived') : '' ?></h1>
    <p class="page-sub"><?= h(date('F j, Y \a\t g:i a', strtotime($o['created_at']))) ?> from <?= h($o['channel']) ?><?= $o['cancelled_at'] ? ' · Cancelled ' . h(friendly_date($o['cancelled_at'])) : '' ?></p>

    <div class="card">
      <div class="card-sec" style="display:flex;align-items:center;gap:10px"><?= status_pill($o['fulfillment_status']) ?><span class="muted"><?= h(t_val("SELECT name FROM locations WHERE id=?", [(int)$o['location_id']]) ?? '') ?></span></div>
      <div class="card-sec" style="padding-top:0">
        <?php foreach ($items as $i): ?>
        <div class="line-item">
          <?= thumb($i['image']) ?>
          <div class="li-title"><?php if ($i['product_id']): ?><a class="link" href="<?= h(url('products/' . $i['product_id'])) ?>"><?= h($i['title']) ?></a><?php else: ?><?= h($i['title']) ?><?php endif; ?><?php if ($i['variant_title']): ?><br><span class="vchip"><?= h($i['variant_title']) ?></span><?php endif; ?></div>
          <span><?= h(money($i['price'])) ?> × <?= (int)$i['quantity'] ?></span>
          <span class="li-total"><?= h(money($i['price'] * $i['quantity'])) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if ($o['fulfillment_status'] === 'unfulfilled' && !$o['cancelled_at']): ?>
      <div class="card-sec" style="display:flex;justify-content:flex-end"><button class="btn primary" data-act="fulfill">Mark as fulfilled</button></div>
      <?php endif; ?>
    </div>

    <div class="card" style="margin-top:14px">
      <div class="card-sec"><?= status_pill($o['payment_status']) ?></div>
      <div class="card-sec" style="padding-top:4px">
        <table class="pay-table">
          <tr><td>Subtotal</td><td class="mid"><?= $count ?> item<?= $count === 1 ? '' : 's' ?></td><td><?= h(money($o['subtotal'])) ?></td></tr>
          <tr><td>Discount</td><td class="mid"></td><td>-<?= h(money($o['discount'])) ?></td></tr>
          <tr><td>Shipping</td><td class="mid"><?= h($o['delivery_method']) ?></td><td><?= h(money($o['shipping'])) ?></td></tr>
          <tr class="total"><td>Total</td><td></td><td><?= h(money($o['total'], true)) ?></td></tr>
          <tr><td style="padding-top:12px">Paid</td><td></td><td style="padding-top:12px"><?= h(money($o['payment_status'] === 'paid' ? $o['total'] : 0)) ?></td></tr>
          <tr><td>Balance</td><td></td><td><?= h(money($o['payment_status'] === 'paid' ? 0 : $o['total'])) ?></td></tr>
        </table>
      </div>
      <?php if ($o['payment_status'] === 'pending' && !$o['cancelled_at']): ?>
      <div class="card-sec" style="display:flex;justify-content:flex-end;gap:8px"><button class="btn" data-act="mark_paid">Mark as paid</button></div>
      <?php endif; ?>
    </div>

    <div class="sec-head"><h2>Timeline</h2></div>
    <?= timeline_html('order', $id) ?>
  </div>

  <aside class="side">
    <div class="side-sec"><div class="side-head">Notes <button class="icon-btn" id="editNote" type="button"><?= icon('pencil') ?></button></div><div class="muted" id="noteText"><?= $o['note'] ? nl2br(h($o['note'])) : 'No notes from customer' ?></div></div>
    <div class="side-sec"><div class="side-head">Customer</div>
      <?php if ($c): ?>
        <a class="link" href="<?= h(url('customers/' . $c['id'])) ?>"><?= h(customer_name($c)) ?></a>
        <div class="muted" style="margin-top:4px"><?= (int)t_val("SELECT COUNT(*) FROM orders WHERE customer_id=?", [(int)$c['id']]) ?> order(s)</div>
        <div class="label-sm" style="margin-top:14px">Contact information</div><div class="link"><?= h($c['email']) ?></div><div><?= h($c['phone']) ?></div>
      <?php else: ?><div class="muted">No customer</div><?php endif; ?>
      <?php if ($addr): ?><div class="label-sm" style="margin-top:14px">Shipping address</div>
        <div><?= h(customer_name($addr)) ?><br><?= h($addr['address1'] ?? '') ?><br><?= h($addr['city'] ?? '') ?> <?= h($addr['zip'] ?? '') ?><br><?= h($addr['country'] ?? '') ?></div><?php endif; ?>
    </div>
    <div class="side-sec"><div class="side-head">Tags</div><div id="oTags"></div></div>
  </aside>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const id = <?= (int)$id ?>, {api, toast, popMenu, confirmBox, modal, esc, tagsInput} = UI;
  document.querySelectorAll('[data-act]').forEach(b => b.onclick = async () => { await api('orders/' + b.dataset.act, {id}); location.reload(); });
  document.getElementById('oPrint').onclick = () => window.print();
  document.getElementById('oMore').onclick = e => { e.stopPropagation(); popMenu(e.currentTarget, [
    {label: <?= json_encode($o['archived'] ? 'Unarchive' : 'Archive') ?>, action: async () => { await api('orders/archive', {id}); location.reload(); }},
    {label: 'Duplicate as draft', action: async () => { const r = await api('orders/duplicate_draft', {id}); location.href = UI.url('draft_orders/' + r.id); }},
    '-',
    {label: 'Cancel order', danger: true, disabled: <?= $o['cancelled_at'] ? 'true' : 'false' ?>, action: async () => { if (await confirmBox('Cancel order #<?= (int)$o['number'] ?>?', 'Unfulfilled items will be restocked and the order will be archived.', 'Cancel order', true)) { await api('orders/cancel', {id}); location.reload(); } }},
  ]); };
  let tags = <?= json_encode(array_values(array_filter(explode(',', (string)$o['tags'])))) ?>, note = <?= json_encode((string)$o['note']) ?>;
  const save = () => api('orders/update', {id, tags, note}).then(() => toast('Saved'));
  tagsInput(document.getElementById('oTags'), tags, t => { tags = t; save(); });
  document.getElementById('editNote').onclick = () => {
    const m = modal({title: 'Edit note', body: `<textarea class="input ta" data-n>${esc(note)}</textarea>`, footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-s>Save</button>'});
    m.$('[data-s]').onclick = async () => { note = m.$('[data-n]').value; await save(); document.getElementById('noteText').textContent = note || 'No notes from customer'; m.close(); };
  };
});
</script>
<?php $GLOBALS['PAGE_SCRIPTS'][] = 'timeline.js'; ?>
