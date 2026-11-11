<?php
$PAGE_TITLE = 'Settings';
$u = current_user();
$locs = t_rows("SELECT l.*, (SELECT COALESCE(SUM(available+committed),0) FROM inventory_levels il WHERE il.location_id=l.id) units FROM locations l ORDER BY is_default DESC, id");
$master = m_row("SELECT email, mobile_number, db_name, created_at FROM users WHERE id=?", [(int)$_SESSION['uid']]);
echo topbar('settings', [['Settings', null]]);
?>
<div class="form-layout single" style="padding-left:200px;padding-right:200px">
  <div>
    <div class="sec-head"><h2>Store details</h2></div>
    <form class="card card-pad" id="storeForm" style="display:flex;flex-direction:column;gap:14px">
      <div class="row2"><label class="field"><span>Store name</span><input name="store_name" value="<?= h(setting('store_name', '')) ?>" maxlength="120" required></label>
        <label class="field"><span>Store email</span><input name="store_email" type="email" value="<?= h(setting('store_email', $master['email'] ?? '')) ?>"></label></div>
      <div class="row2"><label class="field"><span>Phone</span><input name="store_phone" value="<?= h(setting('store_phone', $master['mobile_number'] ?? '')) ?>"></label>
        <label class="field"><span>Currency</span><input value="Bangladeshi Taka (<?= h(cur()) ?> <?= h(sym()) ?>)" disabled></label></div>
      <label class="field"><span>Business address</span><input name="store_address" value="<?= h(setting('store_address', '')) ?>"></label>
      <div style="display:flex;justify-content:space-between;align-items:center"><span class="hint" style="margin:0">Store ID: <?= h($master['db_name'] ?? '') ?> · Created <?= h(date('M j, Y', strtotime($master['created_at'] ?? 'now'))) ?></span><button class="btn primary" type="submit">Save</button></div>
    </form>

    <div class="sec-head" style="margin-top:32px"><h2>Locations</h2><div class="acts"><button class="btn" type="button" id="addLoc"><?= icon('plus') ?>Add location</button></div></div>
    <div class="card">
      <table class="grid" id="locTable"><thead><tr><th>Location</th><th>Address</th><th class="num">Units</th><th></th></tr></thead><tbody>
      <?php foreach ($locs as $l): ?>
        <tr data-id="<?= (int)$l['id'] ?>" data-name="<?= h($l['name']) ?>" data-address="<?= h($l['address']) ?>">
          <td class="strong"><?= h($l['name']) ?> <?= $l['is_default'] ? pill('Default', 'green') : '' ?></td>
          <td><?= h($l['address'] ?: '—') ?></td><td class="num"><?= (int)$l['units'] ?></td>
          <td class="num"><button class="icon-btn" type="button" data-locmenu aria-label="Location actions"><?= icon('dots') ?></button></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>

    <div class="sec-head" style="margin-top:32px"><h2>Account</h2></div>
    <div class="card">
      <form class="card-sec" id="accForm" style="display:flex;gap:14px;align-items:flex-end">
        <label class="field" style="flex:1"><span>Your name</span><input name="name" value="<?= h($u['name'] ?? '') ?>" required></label>
        <label class="field" style="flex:1"><span>Login email</span><input value="<?= h($master['email'] ?? '') ?>" disabled></label>
        <button class="btn" type="submit">Save</button>
      </form>
      <form class="card-sec" id="pwForm" style="display:flex;flex-direction:column;gap:14px">
        <div class="label-sm" style="margin:0">Change password</div>
        <div class="row3"><label class="field"><span>Current password</span><input type="password" name="current" autocomplete="current-password" required></label>
          <label class="field"><span>New password</span><input type="password" name="new" autocomplete="new-password" minlength="8" required></label>
          <label class="field"><span>Confirm new password</span><input type="password" name="confirm" autocomplete="new-password" required></label></div>
        <div style="text-align:right"><button class="btn" type="submit">Update password</button></div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const {api, toast, modal, popMenu, confirmBox, esc} = UI;
  const data = f => Object.fromEntries(new FormData(f));
  document.getElementById('storeForm').onsubmit = async e => { e.preventDefault(); await api('settings/store', data(e.target)); toast('Store details saved'); setTimeout(() => location.reload(), 400); };
  document.getElementById('accForm').onsubmit = async e => { e.preventDefault(); await api('settings/account', data(e.target)); toast('Account saved'); setTimeout(() => location.reload(), 400); };
  document.getElementById('pwForm').onsubmit = async e => { e.preventDefault(); await api('settings/password', data(e.target)); e.target.reset(); toast('Password updated'); };
  const locModal = (l = {}) => {
    const m = modal({title: l.id ? 'Edit location' : 'Add location', body: `<label class="field"><span>Name</span><input data-n value="${esc(l.name || '')}"></label><label class="field"><span>Address</span><input data-a value="${esc(l.address || '')}"></label>`, footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Save</button>'});
    m.$('[data-ok]').onclick = async () => { await api('settings/location_save', {id: l.id || 0, name: m.$('[data-n]').value, address: m.$('[data-a]').value}); location.reload(); };
  };
  document.getElementById('addLoc').onclick = () => locModal();
  document.querySelectorAll('[data-locmenu]').forEach(b => b.onclick = e => {
    e.stopPropagation();
    const tr = b.closest('tr'), l = {id: +tr.dataset.id, name: tr.dataset.name, address: tr.dataset.address};
    popMenu(b, [
      {label: 'Edit', action: () => locModal(l)},
      {label: 'Make default', action: async () => { await api('settings/location_default', {id: l.id}); location.reload(); }},
      '-',
      {label: 'Delete', danger: true, action: async () => { if (await confirmBox(`Delete ${l.name}?`, 'Only empty locations can be deleted.', 'Delete', true)) { await api('settings/location_delete', {id: l.id}); location.reload(); } }},
    ]);
  });
});
</script>
