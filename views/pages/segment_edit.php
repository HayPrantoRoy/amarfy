<?php
$seg = null;
if (is_int($id)) {
    $seg = t_row("SELECT * FROM segments WHERE id=?", [$id]);
    if (!$seg) { echo topbar('customers', [['Segments', 'customers/segments']], '', 'customers/segments') . '<div class="page-pad">' . empty_state('', 'Segment not found', 'It may have been deleted.', 'Back to segments', 'customers/segments') . '</div>'; return; }
}
$PAGE_TITLE = $seg ? $seg['name'] : 'Create segment';
$builtIn = $seg && $seg['rule_key'];
$ids = $seg ? segment_customer_ids($seg) : [];
$total = (int)t_val("SELECT COUNT(*) FROM customers");
$customers = $ids ? t_rows("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id=c.id AND o.cancelled_at IS NULL) n, (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.customer_id=c.id AND o.cancelled_at IS NULL) spent FROM customers c WHERE c.id IN (" . implode(',', $ids) . ") ORDER BY c.first_name") : [];
$state = ['id' => $seg ? (int)$seg['id'] : 0, 'name' => $seg['name'] ?? '', 'builtIn' => (bool)$builtIn, 'match_all' => (int)($seg['match_all'] ?? 1),
    'rules' => $seg && !$builtIn ? (json_decode((string)$seg['rules_json'], true) ?: []) : [['field' => 'orders_count', 'op' => 'gte', 'value' => '1']],
    'fields' => segment_fields()];
$actions = $seg && !$builtIn ? '<button class="btn" type="button" id="segDelete">Delete</button>' : '';
echo topbar('customers', [['Segments', 'customers/segments'], [$seg ? $seg['name'] : 'Create segment', null]], $actions, 'customers/segments');
?>
<div class="form-layout single" style="padding-left:120px;padding-right:120px" id="segApp">
  <div>
    <input class="title-input" id="segName" value="<?= h($state['name']) ?>" placeholder="Segment name">
    <?php if ($builtIn): ?>
      <div class="banner info"><?= icon('info') ?>This is a default segment maintained by Amarfy. You can rename it; its conditions update automatically.</div>
    <?php else: ?>
      <div class="card card-pad" id="ruleBox">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:6px"><b>Customers who match</b>
          <label class="check"><input type="radio" name="match" value="1" <?= $state['match_all'] ? 'checked' : '' ?>> all conditions</label>
          <label class="check"><input type="radio" name="match" value="0" <?= $state['match_all'] ? '' : 'checked' ?>> any condition</label></div>
        <div data-rules></div>
        <div style="display:flex;gap:8px;margin-top:12px"><button class="chip-btn" type="button" id="addRule"><?= icon('plus') ?> Add condition</button><span class="muted" id="segCount" style="margin-left:auto;align-self:center"></span></div>
      </div>
    <?php endif; ?>
    <div class="sec-head" style="margin-top:28px"><h2>Customers</h2><span class="count-chip" id="custCount"><?= count($ids) ?></span><span class="muted"><?= $total ? round(count($ids) / $total * 100) : 0 ?>% of your customer base</span></div>
    <div class="card"><table class="grid" id="segCustomers"><thead><tr><th>Customer name</th><th>Email subscription</th><th>Location</th><th>Orders</th><th class="num">Amount spent</th></tr></thead><tbody>
      <?php if (!$customers): ?><tr class="empty-row"><td colspan="5">No customers match this segment yet</td></tr><?php endif; ?>
      <?php foreach ($customers as $c): ?><tr data-href="<?= h(url('customers/' . $c['id'])) ?>"><td class="strong"><?= h(customer_name($c)) ?></td><td><?= $c['accepts_marketing'] ? pill('Subscribed', 'green') : pill('Not subscribed') ?></td><td><?= h(trim($c['city'] . ', ' . $c['country'], ', ')) ?></td><td><?= (int)$c['n'] ?> order<?= (int)$c['n'] === 1 ? '' : 's' ?></td><td class="num"><?= h(money($c['spent'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const S = <?= json_encode($state, JSON_UNESCAPED_UNICODE) ?>, {api, toast, esc, saveBar, confirmBox, url, ic} = UI;
  const OPS = {number: [['gte', 'is at least'], ['lte', 'is at most'], ['gt', 'is greater than'], ['lt', 'is less than'], ['eq', 'is equal to']], text: [['eq', 'is'], ['neq', 'is not'], ['contains', 'contains']], bool: [['eq', 'is']]};
  const payload = () => ({id: S.id, name: document.getElementById('segName').value, rules: S.rules, match_all: S.match_all});
  let snap = JSON.stringify(payload());
  const bar = saveBar({label: S.id ? 'Unsaved changes' : 'Unsaved segment', onSave: async () => { const r = await api('segments/save', payload()); snap = JSON.stringify(payload()); bar.hide(); location.href = url('segments/' + r.id); }, onDiscard: () => location.reload()});
  const dirty = () => JSON.stringify(payload()) !== snap ? bar.show() : bar.hide();
  document.getElementById('segName').addEventListener('input', dirty);
  let t;
  const preview = () => { clearTimeout(t); t = setTimeout(async () => {
    const r = await api('segments/preview', {rules: S.rules.filter(x => String(x.value).trim() !== ''), match_all: S.match_all});
    document.getElementById('segCount').textContent = `${r.count} customers · ${r.percent}% of your customer base`;
    document.getElementById('custCount').textContent = r.count;
    document.querySelector('#segCustomers tbody').innerHTML = r.customers.length ? r.customers.map(c => `<tr onclick="location.href='${esc(url('customers/' + c.id))}'" class="clickable"><td class="strong">${esc(c.name)}</td><td></td><td>${esc(c.city || '')}</td><td></td><td></td></tr>`).join('') : '<tr class="empty-row"><td colspan="5">No customers match these conditions</td></tr>';
  }, 250); };
  const draw = () => {
    const box = document.querySelector('[data-rules]'); if (!box) return;
    box.innerHTML = S.rules.map((r, i) => {
      const type = (S.fields[r.field] || ['', 'text'])[1];
      const val = type === 'bool' ? `<select class="input" data-r="value:${i}"><option value="1" ${r.value === '1' ? 'selected' : ''}>Subscribed</option><option value="0" ${r.value === '0' ? 'selected' : ''}>Not subscribed</option></select>` : `<input class="input" data-r="value:${i}" value="${esc(r.value)}" ${type === 'number' ? 'type="number"' : ''} placeholder="Value">`;
      return `<div class="cond-row"><select class="input" data-r="field:${i}">${Object.entries(S.fields).map(([k, v]) => `<option value="${k}" ${k === r.field ? 'selected' : ''}>${esc(v[0])}</option>`).join('')}</select>
        <select class="input" data-r="op:${i}">${OPS[type].map(([k, l]) => `<option value="${k}" ${k === r.op ? 'selected' : ''}>${l}</option>`).join('')}</select>${val}<button class="icon-btn" type="button" data-rm="${i}">${ic('trash')}</button></div>`;
    }).join('');
    box.querySelectorAll('[data-r]').forEach(el => { el.oninput = el.onchange = () => { const [k, i] = el.dataset.r.split(':'); S.rules[+i][k] = el.value; if (k === 'field') { const ty = S.fields[el.value][1]; S.rules[+i].op = OPS[ty][0][0]; S.rules[+i].value = ty === 'bool' ? '1' : ''; draw(); } dirty(); preview(); }; });
    box.querySelectorAll('[data-rm]').forEach(b => b.onclick = () => { S.rules.splice(+b.dataset.rm, 1); draw(); dirty(); preview(); });
  };
  const add = document.getElementById('addRule');
  if (add) add.onclick = () => { S.rules.push({field: 'city', op: 'eq', value: ''}); draw(); dirty(); };
  document.querySelectorAll('input[name=match]').forEach(r => r.onchange = () => { S.match_all = +r.value; dirty(); preview(); });
  const del = document.getElementById('segDelete');
  if (del) del.onclick = async () => { if (await confirmBox('Delete segment?', 'Customers in it won’t be deleted.', 'Delete', true)) { await api('segments/delete', {id: S.id}); bar.hide(); location.href = url('customers/segments'); } };
  draw(); if (!S.builtIn) preview();
});
</script>
