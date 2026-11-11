<?php
$g = null; $cust = null;
if (is_int($id)) {
    $g = t_row("SELECT * FROM gift_cards WHERE id=?", [$id]);
    if (!$g) { echo topbar('gift', [['Gift cards', 'gift_cards']], '', 'gift_cards') . '<div class="page-pad">' . empty_state('', 'Gift card not found', 'It may have been deleted.', 'Back', 'gift_cards') . '</div>'; return; }
    if ($g['customer_id']) { $c = t_row("SELECT * FROM customers WHERE id=?", [(int)$g['customer_id']]); if ($c) $cust = ['id' => (int)$c['id'], 'name' => customer_name($c), 'email' => $c['email']]; }
}
$PAGE_TITLE = $g ? 'Gift card ' . strtoupper(substr($g['code'], -4)) : 'Create gift card';
$state = ['id' => $g ? (int)$g['id'] : 0, 'code' => $g['code'] ?? '', 'initial_value' => (float)($g['initial_value'] ?? 10), 'balance' => (float)($g['balance'] ?? 0),
    'expires_on' => $g['expires_on'] ?? '', 'customer' => $cust, 'recipient' => $g['recipient'] ?? '', 'note' => $g['note'] ?? '', 'status' => $g['status'] ?? 'active', 'currency' => $g['currency'] ?? cur()];
$actions = $g ? '<button class="btn" id="gToggle" type="button">' . ($g['status'] === 'active' ? 'Disable' : 'Enable') . '</button>' : '';
echo topbar('gift', $g ? [['Gift cards', 'gift_cards'], ['•••• ' . strtoupper(substr($g['code'], -4)), null]] : [['Gift cards', 'gift_cards']], $actions, 'gift_cards');
?>
<div class="form-layout" id="gcApp">
  <div>
    <h1 class="page-h1"><?= $g ? '•••• •••• •••• ' . h(strtoupper(substr($g['code'], -4))) . ' ' . status_pill($g['status']) : 'Create gift card' ?></h1>
    <?php if ($g && $g['currency'] !== cur()): ?><div class="banner warn"><?= icon('warn') ?>This gift card is in <?= h($g['currency']) ?> and can’t be redeemed in your store currency (<?= h(cur()) ?>). Create a new gift card to replace it.</div><?php endif; ?>
    <div class="sec-head"><h2>Gift card details</h2></div>
    <div class="card card-pad" style="display:flex;flex-direction:column;gap:16px" data-main></div>
    <?php if ($g): ?><div class="sec-head" style="margin-top:36px"><h2>Timeline</h2></div><?= timeline_html('gift_card', (int)$g['id']) ?><?php endif; ?>
  </div>
  <aside class="side" data-side></aside>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const {$, esc, ic, api, toast, modal, saveBar, customerSearch, money, url} = UI;
  const G = <?= json_encode($state, JSON_UNESCAPED_UNICODE) ?>, root = $('#gcApp');
  const payload = () => ({id: G.id, code: G.code, initial_value: G.initial_value, expires_on: G.expires_on, customer_id: G.customer ? G.customer.id : null, recipient: G.recipient, note: G.note});
  let snap = JSON.stringify(payload());
  const bar = saveBar({onSave: async () => { const r = await api('gift_cards/save', payload()); snap = JSON.stringify(payload()); bar.hide(); location.href = url('gift_cards/' + r.id); }, onDiscard: () => location.reload()});
  const dirty = () => JSON.stringify(payload()) !== snap ? bar.show() : bar.hide();
  function renderMain() {
    const el = $('[data-main]', root);
    el.innerHTML = G.id
      ? `<div class="row2"><div><div class="label-sm">Current balance</div><div style="font-size:20px;font-weight:600">${money(G.balance)} <span class="muted" style="font-size:13px">${esc(G.currency)}</span></div><button class="btn" style="margin-top:8px" type="button" data-adjust>Adjust balance</button></div><div><div class="label-sm">Initial value</div><div style="font-size:20px">${money(G.initial_value)}</div></div></div>
         <div><div class="label-sm">Expiry date</div><label class="chip-btn" style="position:relative">${ic('calendar')} ${G.expires_on ? esc(G.expires_on) : 'Doesn’t expire'}<input type="date" data-exp value="${esc(G.expires_on)}" style="position:absolute;inset:0;opacity:0"></label>${G.expires_on ? ' <button class="btn plain" type="button" data-noexp>Remove expiry</button>' : ''}</div>`
      : `<label class="field"><span>Gift card code</span><input data-code value="${esc(G.code)}" maxlength="20"></label>
         <div class="row2"><label class="field"><span>Initial value</span><div class="input-affix"><span class="pre">${esc(window.AMARFY.sym)}</span><input type="number" min="0.01" step="0.01" data-val value="${G.initial_value.toFixed(2)}"></div></label>
         <div class="field"><span>Expiry date</span><div><label class="chip-btn" style="position:relative;width:max-content">${ic('calendar')} ${G.expires_on ? esc(G.expires_on) : 'Doesn’t expire'}<input type="date" data-exp value="${esc(G.expires_on)}" style="position:absolute;inset:0;opacity:0"></label></div><small>Gift card expiration laws can vary by country</small></div></div>`;
    const c = $('[data-code]', el); if (c) c.oninput = () => { G.code = c.value; dirty(); };
    const v = $('[data-val]', el); if (v) v.oninput = () => { G.initial_value = parseFloat(v.value) || 0; dirty(); };
    const e = $('[data-exp]', el); e.onchange = () => { G.expires_on = e.value; renderMain(); dirty(); };
    const ne = $('[data-noexp]', el); if (ne) ne.onclick = () => { G.expires_on = ''; renderMain(); dirty(); };
    const ad = $('[data-adjust]', el); if (ad) ad.onclick = () => { const m = modal({title: 'Adjust balance', size: 'sm', body: `<label class="field"><span>New balance</span><div class="input-affix"><span class="pre">${esc(window.AMARFY.sym)}</span><input type="number" min="0" step="0.01" data-b value="${G.balance}"></div></label>`, footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Save</button>'}); m.$('[data-ok]').onclick = async () => { await api('gift_cards/adjust', {id: G.id, balance: parseFloat(m.$('[data-b]').value) || 0}); location.reload(); }; };
  }
  function renderSide() {
    const el = $('[data-side]', root);
    el.innerHTML = `<div class="side-sec"><div class="side-head">Customer ${G.customer ? `<button class="icon-btn" type="button" data-rmc>${ic('x')}</button>` : ''}</div>
        ${G.customer ? `<a class="link" href="${esc(url('customers/' + G.customer.id))}">${esc(G.customer.name)}</a><div class="muted">${esc(G.customer.email || '')}</div>` : `<div class="cs-wrap"><label class="input-affix">${ic('search')}<input placeholder="Search or create customer" data-cs></label></div>`}</div>
      <div class="side-sec"><div class="side-head">Recipient</div><input class="input" data-rec placeholder="Name or email" value="${esc(G.recipient)}"></div>
      <div class="side-sec"><div class="side-head">Notes <button class="icon-btn" type="button" data-note>${ic('pencil')}</button></div><div class="muted" style="white-space:pre-wrap">${G.note ? esc(G.note) : 'No notes'}</div></div>`;
    const cs = $('[data-cs]', el); if (cs) customerSearch(cs, c => { G.customer = c; renderSide(); dirty(); });
    const rm = $('[data-rmc]', el); if (rm) rm.onclick = () => { G.customer = null; renderSide(); dirty(); };
    $('[data-rec]', el).oninput = e => { G.recipient = e.target.value; dirty(); };
    $('[data-note]', el).onclick = () => { const m = modal({title: 'Notes', body: `<textarea class="input ta" data-n>${esc(G.note)}</textarea>`, footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Done</button>'}); m.$('[data-ok]').onclick = () => { G.note = m.$('[data-n]').value; m.close(); renderSide(); dirty(); }; };
  }
  renderMain(); renderSide();
  if (!G.id) { api('gift_cards/code').then(r => { G.code = r.code; snap = '{}'; renderMain(); dirty(); }); }
  const tg = $('#gToggle'); if (tg) tg.onclick = async () => { await api('gift_cards/toggle', {id: G.id}); location.reload(); };
});
</script>
<?php $GLOBALS['PAGE_SCRIPTS'][] = 'timeline.js'; ?>
