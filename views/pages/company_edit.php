<?php
$terms = payment_terms_options();
if ($id === 'new') {
    $PAGE_TITLE = 'Add company';
    echo topbar('building', [['Companies', 'customers/companies'], ['Add company', null]], '', 'customers/companies');
    ?>
    <form class="form-layout" id="coNew" autocomplete="off">
      <div>
        <div class="sec-head"><h2>Company</h2></div>
        <div class="card card-pad" style="display:flex;flex-direction:column;gap:14px">
          <div class="row2"><label class="field"><span>Company name</span><input name="name" required></label><label class="field"><span>Company ID <small>(optional)</small></span><input name="external_id"></label></div>
          <label class="check"><input type="checkbox" class="cb" name="ordering_approved" checked> Approve this company to place orders</label>
        </div>
        <div class="sec-head" style="margin-top:26px"><h2>Main contact</h2></div>
        <div class="card card-pad"><div class="cs-wrap"><label class="input-affix"><?= icon('search') ?><input id="coContactSearch" placeholder="Search or create a customer"></label></div><div id="coContact" class="muted" style="margin-top:10px">No main contact</div></div>
        <div class="sec-head" style="margin-top:26px"><h2>Location</h2></div>
        <div class="card card-pad" style="display:flex;flex-direction:column;gap:14px">
          <label class="field"><span>Location name</span><input name="location_name" placeholder="Head office"></label>
          <div class="row2"><label class="field"><span>Address</span><input name="address"></label><label class="field"><span>City</span><input name="city"></label></div>
          <div class="row2"><label class="field"><span>Payment terms</span><div class="select-wrap"><select class="input" name="payment_terms"><option value="">No payment terms</option><?php foreach ($terms as $k => $v): ?><option value="<?= h($k) ?>"><?= h($v) ?></option><?php endforeach; ?></select><?= icon('updown') ?></div></label>
            <label class="field"><span>Checkout</span><div class="select-wrap"><select class="input" name="checkout_mode"><option value="auto">Automatically submit orders</option><option value="review">Submit all orders as drafts for review</option></select><?= icon('updown') ?></div></label></div>
        </div>
      </div>
      <aside class="side"><div class="side-sec"><div class="side-head">Notes</div><textarea class="input ta" name="note" placeholder="Add a note about this company"></textarea></div></aside>
    </form>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
      const f = document.getElementById('coNew'); let contact = null;
      UI.customerSearch(document.getElementById('coContactSearch'), c => { contact = c; document.getElementById('coContact').innerHTML = `<b>${UI.esc(c.name)}</b> · ${UI.esc(c.email || '')}`; bar.show(); });
      const bar = UI.saveBar({label: 'Unsaved company', onSave: async () => {
        const d = Object.fromEntries(new FormData(f)); d.ordering_approved = f.ordering_approved.checked ? 1 : 0; d.main_contact_id = contact ? contact.id : null;
        const r = await UI.api('companies/save', d); bar.hide(); location.href = UI.url('companies/' + r.id);
      }, onDiscard: () => location.href = UI.url('customers/companies')});
      f.addEventListener('input', () => bar.show()); f.addEventListener('submit', e => e.preventDefault());
    });
    </script>
    <?php return;
}
$co = is_int($id) ? t_row("SELECT * FROM companies WHERE id=?", [$id]) : null;
if (!$co) { echo topbar('building', [['Companies', 'customers/companies']], '', 'customers/companies') . '<div class="page-pad">' . empty_state('', 'Company not found', 'It may have been deleted.', 'Back to companies', 'customers/companies') . '</div>'; return; }
$PAGE_TITLE = $co['name'];
$st = company_stats((int)$co['id']);
$contacts = $st['customer_ids'] ? t_rows("SELECT * FROM customers WHERE id IN (" . implode(',', $st['customer_ids']) . ") ORDER BY first_name") : [];
$locs = t_rows("SELECT * FROM company_locations WHERE company_id=? ORDER BY id", [$id]);
$staff = t_rows("SELECT id, name FROM staff ORDER BY name");
$staffName = $co['staff_id'] ? t_val("SELECT name FROM staff WHERE id=?", [(int)$co['staff_id']]) : null;
$loc0 = $locs[0] ?? null;
$since = (new DateTime($co['created_at']))->diff(new DateTime());
$sinceTxt = $since->y ? 'Customer for over ' . $since->y . ' year' . ($since->y > 1 ? 's' : '') : ($since->m ? 'Customer for ' . $since->m . ' month' . ($since->m > 1 ? 's' : '') : 'Customer since ' . date('M j, Y', strtotime($co['created_at'])));
$prev = t_val("SELECT id FROM companies WHERE name < ? ORDER BY name DESC LIMIT 1", [$co['name']]);
$next = t_val("SELECT id FROM companies WHERE name > ? ORDER BY name LIMIT 1", [$co['name']]);
$nav = prev_next('companies', $prev, $next);
echo topbar('building', [['Companies', 'customers/companies']], '<button class="btn" type="button" id="coMore">More actions ' . icon('chev-down') . '</button>' . $nav, 'customers/companies');
$locSales = function (array $l) use ($st) { return $st; };
$contactParam = $co['main_contact_id'] ? '&customer=' . (int)$co['main_contact_id'] : '';
?>
<div class="form-layout" id="coApp">
  <div>
    <h1 class="page-h1" style="margin-bottom:4px"><?= h($co['name']) ?></h1>
    <p class="muted" style="font-size:14px;margin-bottom:28px"><?= h($sinceTxt) ?></p>

    <div class="sec-head"><h2>Orders</h2><div class="acts"><a class="btn primary" style="height:32px;border-radius:12px" href="<?= h(url('draft_orders/new?company=' . $co['id'] . $contactParam)) ?>"><?= icon('plus') ?> Create order</a></div></div>
    <?php if ($st['drafts'] || $st['orders']): ?>
    <div class="card">
      <?php if ($st['drafts']): ?><a class="card-sec" style="display:flex;align-items:center;gap:14px" href="<?= h(url('draft_orders?company=' . $co['id'])) ?>"><?= icon('drafts') ?><span><?= $st['drafts'] ?> draft order<?= $st['drafts'] === 1 ? '' : 's' ?> for review</span><span style="margin-left:auto"><?= icon('chev-right') ?></span></a><?php endif; ?>
      <?php if ($st['orders']): ?><a class="card-sec" style="display:flex;align-items:center;gap:14px" href="<?= h(url('orders?company=' . $co['id'])) ?>"><?= icon('orders') ?><span><?= $st['orders'] ?> order<?= $st['orders'] === 1 ? '' : 's' ?> · <?= h(money($st['sales'], true)) ?></span><span style="margin-left:auto"><?= icon('chev-right') ?></span></a><?php endif; ?>
    </div>
    <?php else: ?><div class="card card-pad muted">No orders yet. Create the first order for this company.</div><?php endif; ?>

    <div class="sec-head" style="margin-top:32px"><h2>Locations</h2><div class="acts"><button class="chip-btn" type="button" id="addLoc"><?= icon('plus') ?> Add location</button></div></div>
    <div class="card">
      <table class="grid compact"><thead><tr><th>Location</th><th class="num">Markets</th><th class="num">Catalogs</th><th class="num">Sales</th><th class="num">Orders</th><th></th></tr></thead><tbody>
      <?php foreach ($locs as $i => $l): ?>
        <tr data-loc='<?= h(json_encode($l)) ?>'>
          <td style="white-space:normal"><b><?= h($l['name']) ?></b><div class="sub-line"><?= h($l['payment_terms'] ? $terms[$l['payment_terms']] ?? $l['payment_terms'] : 'No payment terms') ?> · <?= $l['checkout_mode'] === 'auto' ? 'Automatically submit orders' : 'Submit orders for review' ?><?= $l['address'] ? ' · ' . h($l['address'] . ($l['city'] ? ', ' . $l['city'] : '')) : '' ?></div></td>
          <td class="num">1</td><td class="num">0</td>
          <td class="num"><?= $i === 0 ? h(money($st['sales'], true)) : h(money(0, true)) ?></td><td class="num"><?= $i === 0 ? $st['orders'] : 0 ?></td>
          <td class="num"><button class="icon-btn" type="button" data-locmenu><?= icon('dots') ?></button></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
  </div>

  <aside class="side">
    <div class="side-sec">
      <div class="side-head" style="justify-content:flex-start;gap:10px"><?= h($co['name']) ?> <?= $co['ordering_approved'] ? pill('Approved', 'green', true) : pill('Not approved') ?><button class="icon-btn" type="button" id="coDots" style="margin-left:auto"><?= icon('dots') ?></button></div>
      <div class="muted"><?= h($co['external_id'] ?: 'No company ID') ?></div>
      <div class="label-sm" style="margin-top:16px">Customers</div>
      <div class="tags-wrap"><?php foreach ($contacts as $c): ?><span class="tag-chip" style="padding:6px 10px"><a href="<?= h(url('customers/' . $c['id'])) ?>"><?= h(customer_name($c)) ?></a><?= (int)$c['id'] === (int)$co['main_contact_id'] ? ' <small class="muted">· main</small>' : '' ?><button type="button" data-rmc="<?= (int)$c['id'] ?>" aria-label="Remove"><?= icon('x') ?></button></span><?php endforeach; ?></div>
      <div class="cs-wrap" style="margin-top:8px"><label class="input-affix"><?= icon('search') ?><input id="addContact" placeholder="Add customer"></label></div>
      <div class="label-sm" style="margin-top:16px">Assigned staff</div>
      <div class="select-wrap"><select class="input" id="coStaff"><option value="">None</option><?php foreach ($staff as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$co['staff_id'] === (int)$s['id'] ? 'selected' : '' ?>><?= h($s['name']) ?></option><?php endforeach; ?></select><?= icon('updown') ?></div>
    </div>
    <div class="side-sec">
      <div class="side-head">Customizations</div>
      <div class="label-sm" style="font-weight:500">Markets</div><span class="tag-chip" style="padding:6px 10px"><?= h(setting('market', 'Bangladesh')) ?></span>
      <div class="label-sm" style="font-weight:500;margin-top:14px">Catalogs</div><div class="muted">No catalogs</div>
      <div class="side-head" style="margin:14px 0 6px;font-weight:500">Payment terms <button class="icon-btn" type="button" id="editTerms"><?= icon('pencil') ?></button></div><div><?= h($loc0 && $loc0['payment_terms'] ? $terms[$loc0['payment_terms']] ?? '' : 'None') ?></div>
      <div class="side-head" style="margin:14px 0 6px;font-weight:500">Checkout <button class="icon-btn" type="button" id="editCheckout"><?= icon('pencil') ?></button></div>
      <div>Ship to location address</div><div><?= $loc0 && $loc0['checkout_mode'] === 'review' ? 'Submit all orders as drafts for review' : 'Automatically submit orders' ?></div>
    </div>
    <div class="side-sec"><div class="side-head">Notes <button class="icon-btn" type="button" id="editNote"><?= icon('pencil') ?></button></div><div class="muted" style="white-space:pre-wrap"><?= $co['note'] ? h($co['note']) : 'No notes' ?></div></div>
  </aside>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const id = <?= (int)$co['id'] ?>, {api, toast, modal, popMenu, confirmBox, esc, url} = UI;
  const TERMS = <?= json_encode($terms) ?>, reload = () => location.reload();
  const upd = (field, value) => api('companies/update', {id, field, value}).then(reload);
  const editText = (title, field, cur, ta) => { const m = modal({title, body: ta ? `<textarea class="input ta" data-v>${esc(cur)}</textarea>` : `<input class="input" data-v value="${esc(cur)}">`, footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Save</button>'}); m.$('[data-ok]').onclick = () => upd(field, m.$('[data-v]').value); };
  document.getElementById('editNote').onclick = () => editText('Notes', 'note', <?= json_encode((string)$co['note']) ?>, true);
  document.getElementById('editTerms').onclick = () => { const m = modal({title: 'Payment terms', body: `<select class="input" data-v><option value="">No payment terms</option>${Object.entries(TERMS).map(([k, v]) => `<option value="${k}" ${k === <?= json_encode($loc0['payment_terms'] ?? '') ?> ? 'selected' : ''}>${v}</option>`).join('')}</select>`, footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Save</button>'}); m.$('[data-ok]').onclick = () => upd('payment_terms', m.$('[data-v]').value); };
  document.getElementById('editCheckout').onclick = () => { const m = modal({title: 'Checkout', body: `<label class="check"><input type="radio" name="cm" value="auto" ${<?= json_encode(($loc0['checkout_mode'] ?? 'auto') === 'auto') ?> ? 'checked' : ''}> Automatically submit orders</label><label class="check"><input type="radio" name="cm" value="review" ${<?= json_encode(($loc0['checkout_mode'] ?? '') === 'review') ?> ? 'checked' : ''}> Submit all orders as drafts for review</label>`, footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Save</button>'}); m.$('[data-ok]').onclick = () => upd('checkout_mode', m.$('input[name=cm]:checked').value); };
  document.getElementById('coStaff').onchange = e => upd('staff_id', e.target.value);
  UI.customerSearch(document.getElementById('addContact'), c => api('companies/contact_add', {id, customer_id: c.id}).then(reload));
  document.querySelectorAll('[data-rmc]').forEach(b => b.onclick = () => api('companies/contact_remove', {id, customer_id: +b.dataset.rmc}).then(reload));
  const locModal = (l = {}) => {
    const m = modal({title: l.id ? 'Edit location' : 'Add location', body: `<label class="field"><span>Location name</span><input data-f="name" value="${esc(l.name || '')}"></label><div class="row2"><label class="field"><span>Address</span><input data-f="address" value="${esc(l.address || '')}"></label><label class="field"><span>City</span><input data-f="city" value="${esc(l.city || '')}"></label></div>
      <label class="field"><span>Payment terms</span><select class="input" data-f="payment_terms"><option value="">No payment terms</option>${Object.entries(TERMS).map(([k, v]) => `<option value="${k}" ${k === l.payment_terms ? 'selected' : ''}>${v}</option>`).join('')}</select></label>
      <label class="field"><span>Checkout</span><select class="input" data-f="checkout_mode"><option value="auto">Automatically submit orders</option><option value="review" ${l.checkout_mode === 'review' ? 'selected' : ''}>Submit all orders as drafts for review</option></select></label>`,
      footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Save</button>'});
    m.$('[data-ok]').onclick = async () => { const d = {id, location_id: l.id || 0}; m.$$('[data-f]').forEach(i => d[i.dataset.f] = i.value); await api('companies/location_save', d); reload(); };
  };
  document.getElementById('addLoc').onclick = () => locModal();
  document.querySelectorAll('[data-locmenu]').forEach(b => b.onclick = e => { e.stopPropagation(); const l = JSON.parse(b.closest('tr').dataset.loc);
    popMenu(b, [{label: 'Edit location', action: () => locModal(l)}, {label: 'Delete location', danger: true, action: async () => { if (await confirmBox('Delete location?', l.name, 'Delete', true)) { await api('companies/location_delete', {id, location_id: l.id}); reload(); } }}]); });
  const actions = e => { e.stopPropagation(); popMenu(e.currentTarget, [
    {label: 'Edit company ID', action: () => editText('Company ID', 'external_id', <?= json_encode((string)$co['external_id']) ?>)},
    {label: <?= json_encode($co['ordering_approved'] ? 'Revoke ordering approval' : 'Approve ordering') ?>, action: () => upd('ordering_approved', <?= $co['ordering_approved'] ? 0 : 1 ?>)},
    '-',
    {label: 'Delete company', danger: true, action: async () => { if (await confirmBox('Delete company?', 'Customers and orders won’t be deleted.', 'Delete', true)) { await api('companies/delete', {id}); location.href = url('customers/companies'); } }},
  ]); };
  document.getElementById('coMore').onclick = actions; document.getElementById('coDots').onclick = actions;
});
</script>
