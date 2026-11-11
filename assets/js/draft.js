/* Draft order editor */
document.addEventListener('DOMContentLoaded', () => {
  const {$, esc, money, ic, api, toast, modal, confirmBox, popMenu, saveBar, tagsInput, productPicker, customerSearch, customerModal, url} = UI;
  const S = window.DRAFT;
  const root = $('#draftApp');
  const readOnly = S.status === 'completed';
  let snapshot = JSON.stringify(pick());
  const bar = saveBar({onSave: save, onDiscard: () => location.reload()});

  function pick() {
    return {items: S.items, customer_id: S.customer ? S.customer.id : null, shipping_address: S.shipping_address, tags: S.tags, note: S.note, po_number: S.po_number,
      discount_type: S.discount_type, discount_value: S.discount_value, discount_reason: S.discount_reason, shipping_title: S.shipping_title, shipping_amount: S.shipping_amount,
      payment_due_later: S.payment_due_later, payment_terms: S.payment_terms};
  }
  function dirty() {
    if (readOnly) return;
    const changed = JSON.stringify(pick()) !== snapshot;
    changed ? bar.show() : bar.hide();
  }
  async function save() {
    if (!S.items.length) { toast('Add a product before saving', true); throw new Error('empty'); }
    const r = await api('drafts/save', Object.assign({id: S.id}, pick()));
    snapshot = JSON.stringify(pick()); bar.hide();
    if (!S.id) { location.href = url('draft_orders/' + r.id); return r.id; }
    toast('Draft order saved'); setTimeout(() => location.reload(), 400);
    return r.id;
  }
  function totals() {
    let sub = 0, cnt = 0;
    S.items.forEach(i => { sub += i.price * i.quantity; cnt += +i.quantity; });
    let disc = 0;
    if (S.discount_type === 'percentage') disc = Math.round(sub * Math.min(100, S.discount_value)) / 100;
    if (S.discount_type === 'fixed') disc = Math.min(sub, S.discount_value);
    return {sub, cnt, disc, ship: +S.shipping_amount || 0, total: Math.max(0, sub - disc + (+S.shipping_amount || 0))};
  }

  /* ---------------- products ---------------- */
  function renderProducts() {
    const el = $('[data-products]', root);
    const btns = readOnly ? '' : `<button class="chip-btn" data-add-product type="button">${ic('plus')} Product</button><button class="chip-btn" data-add-custom type="button">${ic('plus')} Custom item</button>`;
    if (!S.items.length) {
      el.innerHTML = `<div class="sec-head"><h2>Products</h2></div><div class="card dashed"><div class="dz" style="border:0">${btns}</div></div>`;
    } else {
      el.innerHTML = `<div class="sec-head"><h2>Products</h2><div class="acts">${readOnly ? '' : `<button class="icon-btn" data-pmenu type="button">${ic('dots')}</button>`}${btns}</div></div>
        <div class="card card-pad" style="padding-top:6px;padding-bottom:6px">${S.items.map((i, n) => `
          <div class="line-item">
            ${i.image ? `<span class="thumb-sm"><img src="${esc(url(i.image))}"></span>` : `<span class="thumb-sm empty">${ic('image')}</span>`}
            <div class="li-title">${i.product_id ? `<a href="${esc(url('products/' + i.product_id))}">${esc(i.title)}</a>` : esc(i.title)}${i.variant_title ? `<br><span class="vchip">${esc(i.variant_title)}</span>` : ''}</div>
            <span class="li-price" data-price="${n}">${money(i.price)}</span>
            <span class="li-qty"><input type="number" min="1" value="${i.quantity}" data-qty="${n}" ${readOnly ? 'disabled' : ''}></span>
            <span class="li-total">${money(i.price * i.quantity)}</span>
            ${readOnly ? '' : `<button class="icon-btn" data-rm="${n}" type="button" aria-label="Remove">${ic('x')}</button>`}
          </div>`).join('')}</div>`;
    }
    const ap = $('[data-add-product]', el), ac = $('[data-add-custom]', el), pm = $('[data-pmenu]', el);
    if (ap) ap.onclick = () => productPicker({selected: S.items.filter(i => i.variant_id).map(i => i.variant_id), onAdd: list => {
      list.forEach(({product, variant}) => {
        const ex = S.items.find(i => i.variant_id === variant.id);
        if (ex) return;
        S.items.push({variant_id: variant.id, product_id: product.id, title: product.title, variant_title: variant.title === 'Default Title' ? null : variant.title, image: product.image, price: variant.price, quantity: 1, taxable: 1, requires_shipping: 1});
      });
      render();
    }});
    if (ac) ac.onclick = customItem;
    if (pm) pm.onclick = e => { e.stopPropagation(); popMenu(e.currentTarget, [{label: 'Remove all items', danger: true, action: () => { S.items = []; render(); }}]); };
    el.querySelectorAll('[data-qty]').forEach(inp => inp.onchange = () => { S.items[+inp.dataset.qty].quantity = Math.max(1, parseInt(inp.value) || 1); render(); });
    el.querySelectorAll('[data-rm]').forEach(b => b.onclick = () => { S.items.splice(+b.dataset.rm, 1); render(); });
    if (!readOnly) el.querySelectorAll('[data-price]').forEach(p => p.onclick = () => editPrice(+p.dataset.price));
  }
  function customItem() {
    const m = modal({title: 'Add custom item', body: `<div class="row3" style="grid-template-columns:2fr 1fr 1fr"><label class="field"><span>Item name</span><input data-n></label><label class="field"><span>Price</span><div class="input-affix"><span class="pre">${esc(window.AMARFY.sym)}</span><input type="number" min="0" step="0.01" value="0.00" data-p></div></label><label class="field"><span>Quantity</span><input type="number" min="1" value="1" data-q></label></div>
      <label class="check"><input type="checkbox" class="cb" data-t checked>Item is taxable</label><label class="check"><input type="checkbox" class="cb" data-s checked>Item is a physical product</label>`,
      footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Add item</button>'});
    m.$('[data-ok]').onclick = () => {
      const t = m.$('[data-n]').value.trim(); if (!t) { toast('Enter an item name', true); return; }
      S.items.push({variant_id: null, product_id: null, title: t, variant_title: null, image: null, price: Math.max(0, parseFloat(m.$('[data-p]').value) || 0), quantity: Math.max(1, parseInt(m.$('[data-q]').value) || 1), taxable: m.$('[data-t]').checked ? 1 : 0, requires_shipping: m.$('[data-s]').checked ? 1 : 0});
      m.close(); render();
    };
  }
  function editPrice(n) {
    const it = S.items[n];
    const m = modal({title: 'Edit price', size: 'sm', body: `<label class="field"><span>Price</span><div class="input-affix"><span class="pre">${esc(window.AMARFY.sym)}</span><input type="number" min="0" step="0.01" value="${it.price}" data-p></div></label>`, footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Done</button>'});
    m.$('[data-ok]').onclick = () => { it.price = Math.max(0, parseFloat(m.$('[data-p]').value) || 0); m.close(); render(); };
  }

  /* ---------------- payment ---------------- */
  function renderPayment() {
    const el = $('[data-payment]', root), t = totals(), has = S.items.length > 0;
    const lnk = (cls, txt) => `<span class="lnk ${has && !readOnly ? '' : 'dim'}" data-${cls}>${txt}</span>`;
    const termsTxt = {receipt: 'Payment due when invoice is sent.', fulfillment: 'Payment due when the order is fulfilled.'}[S.payment_terms] || `Payment due ${String(S.terms[S.payment_terms] || '').toLowerCase()} of the invoice being sent.`;
    let actions = '';
    if (has && !readOnly) actions = `<div class="acts"><button class="chip-btn" data-invoice type="button">Send invoice</button>${S.payment_due_later || S.customer ? `<button class="btn primary" data-create-order type="button" style="height:32px;border-radius:12px">Create order</button>` : `<button class="btn primary" data-collect type="button" style="height:32px;border-radius:12px">Collect payment ${ic('chev')}</button>`}</div>`;
    el.innerHTML = `<div class="sec-head" style="margin-top:36px"><h2>Payment</h2>${actions}</div>
      <div class="card"><div class="card-sec"><table class="pay-table">
        <tr><td>Subtotal</td><td class="mid">${has ? `${t.cnt} item${t.cnt === 1 ? '' : 's'}` : ''}</td><td>${money(t.sub)}</td></tr>
        <tr><td>${lnk('disc', 'Add discount')}</td><td class="mid">${S.discount_type ? esc(S.discount_reason || (S.discount_type === 'percentage' ? S.discount_value + '%' : 'Custom discount')) : ''}</td><td>${t.disc ? '-' : ''}${money(t.disc)}</td></tr>
        <tr><td>${lnk('ship', 'Add shipping or delivery')}</td><td class="mid">${esc(S.shipping_title || '')}</td><td>${money(t.ship)}</td></tr>
        <tr><td><span class="${has ? 'lnk' : 'muted'}" style="${has ? '' : 'color:#8a8a8a'}">Estimated tax ${ic('info')}</span></td><td class="mid">Not calculated</td><td></td></tr>
        <tr class="total"><td>Total</td><td></td><td>${money(t.total, true)}</td></tr></table>
        ${has ? `<div style="border-top:1px solid var(--line);margin-top:14px;padding-top:16px;display:flex;align-items:center;justify-content:space-between">Payment due later <input type="checkbox" class="toggle" data-due ${S.payment_due_later ? 'checked' : ''} ${readOnly ? 'disabled' : ''}></div>
          ${S.payment_due_later ? `<div style="margin-top:14px"><div class="label-sm">Payment terms</div><div class="select-wrap" style="max-width:440px"><select class="input" data-terms ${readOnly ? 'disabled' : ''}>${Object.entries(S.terms).map(([k, v]) => `<option value="${k}" ${k === S.payment_terms ? 'selected' : ''}>${esc(v)}</option>`).join('')}</select>${ic('updown')}</div>
            <p style="margin-top:14px"><b>${esc(termsTxt.split('.')[0])}.</b> You’ll be able to collect the balance from the order page.</p>
            <div class="banner info" style="margin-top:14px;flex-direction:column;align-items:flex-start;gap:10px"><span style="display:flex;gap:10px">${ic('info')}Customers can receive automatic reminders for their orders when payment is due at a later date</span><button class="chip-btn" type="button" style="background:#cbe9f7" data-reminders>Set up payment reminders</button></div></div>` : ''}` : ''}
      </div>${has ? '' : '<div class="card-foot">Add a product to calculate total and view payment options</div>'}</div>`;
    const q = s => $(s, el);
    if (has && !readOnly) {
      q('[data-disc]').onclick = discountModal;
      q('[data-ship]').onclick = shippingModal;
    }
    const due = q('[data-due]'); if (due) due.onchange = () => { S.payment_due_later = due.checked ? 1 : 0; render(); };
    const terms = q('[data-terms]'); if (terms) terms.onchange = () => { S.payment_terms = terms.value; render(); };
    const rem = q('[data-reminders]'); if (rem) rem.onclick = () => toast('Payment reminders are sent automatically for orders with payment terms');
    const inv = q('[data-invoice]'); if (inv) inv.onclick = invoiceModal;
    const co = q('[data-create-order]'); if (co) co.onclick = () => complete('pending');
    const col = q('[data-collect]'); if (col) col.onclick = e => { e.stopPropagation(); popMenu(e.currentTarget, [
      {label: 'Mark as paid', action: () => complete('paid')},
      {label: 'Create order with payment pending', action: () => complete('pending')},
    ]); };
  }
  function discountModal() {
    const m = modal({title: 'Add discount', body: `<div class="row2"><label class="field"><span>Discount type</span><div class="select-wrap"><select class="input" data-t><option value="fixed">Amount</option><option value="percentage">Percentage</option></select>${ic('updown')}</div></label>
      <label class="field"><span>Value</span><div class="input-affix"><span class="pre" data-pre>${esc(window.AMARFY.sym)}</span><input type="number" min="0" step="0.01" data-v value="${S.discount_value || ''}"></div></label></div>
      <label class="field"><span>Reason for discount</span><input data-r value="${esc(S.discount_reason)}"><small>Your customers can see this reason</small></label>`,
      footer: `${S.discount_type ? '<button class="btn plain left" data-rm style="color:#c70a24">Remove discount</button>' : ''}<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Apply</button>`});
    const t = m.$('[data-t]'); t.value = S.discount_type || 'fixed';
    const upd = () => m.$('[data-pre]').textContent = t.value === 'percentage' ? '%' : window.AMARFY.sym; upd(); t.onchange = upd;
    m.$('[data-ok]').onclick = () => { const v = parseFloat(m.$('[data-v]').value) || 0; S.discount_type = v > 0 ? t.value : null; S.discount_value = v; S.discount_reason = m.$('[data-r]').value.trim(); m.close(); render(); };
    const rm = m.$('[data-rm]'); if (rm) rm.onclick = () => { S.discount_type = null; S.discount_value = 0; S.discount_reason = ''; m.close(); render(); };
  }
  function shippingModal() {
    const free = !S.shipping_title || (S.shipping_title === 'Free shipping');
    const m = modal({title: 'Shipping and delivery options', body: `<label class="check"><input type="radio" name="sh" value="free" ${free ? 'checked' : ''}> Free shipping</label><label class="check"><input type="radio" name="sh" value="custom" ${free ? '' : 'checked'}> Custom</label>
      <div class="row2" data-custom><label class="field"><span>Shipping rate name</span><input data-n value="${esc(free ? '' : S.shipping_title)}" placeholder="All Bangladesh Shipping"></label><label class="field"><span>Price</span><div class="input-affix"><span class="pre">${esc(window.AMARFY.sym)}</span><input type="number" min="0" step="0.01" data-p value="${S.shipping_amount || ''}"></div></label></div>`,
      footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Done</button>'});
    m.$('[data-ok]').onclick = () => {
      if (m.$('input[name=sh]:checked').value === 'free') { S.shipping_title = 'Free shipping'; S.shipping_amount = 0; }
      else { S.shipping_title = m.$('[data-n]').value.trim() || 'Custom shipping'; S.shipping_amount = Math.max(0, parseFloat(m.$('[data-p]').value) || 0); }
      m.close(); render();
    };
  }
  async function ensureSaved() {
    if (!S.id || bar.visible) {
      const r = await api('drafts/save', Object.assign({id: S.id}, pick()));
      S.id = r.id; snapshot = JSON.stringify(pick()); bar.hide();
    }
    return S.id;
  }
  async function complete(payment) {
    const label = payment === 'paid' ? 'Mark as paid' : 'Create order';
    const total = money(totals().total, true);
    if (!(await confirmBox(payment === 'paid' ? 'Mark as paid' : 'Create order', payment === 'paid' ? `Mark ${total} as received? An order will be created and inventory will be committed.` : `An order will be created with a balance of ${total} due.`, label))) return;
    const id = await ensureSaved();
    const r = await api('drafts/complete', {id, payment});
    location.href = url('orders/' + r.order_id);
  }
  function invoiceModal() {
    const to = S.customer ? (S.customer.email || '') : '';
    const m = modal({title: 'Send invoice', size: 'wide', body: `<div class="row2"><label class="field"><span>To</span><input type="email" data-to value="${esc(to)}"></label><label class="field"><span>From</span><input value="${esc(window.AMARFY.user)}" disabled></label></div>
      <label class="field"><span>Subject</span><input data-sub value="Invoice for your order"></label>
      <label class="field"><span>Custom message (optional)</span><textarea data-msg></textarea></label>`,
      footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Review invoice</button>'});
    m.$('[data-ok]').onclick = async () => {
      const id = await ensureSaved();
      await api('drafts/invoice', {id, to: m.$('[data-to]').value.trim()});
      m.close(); toast('Invoice sent'); setTimeout(() => location.href = url('draft_orders/' + id), 500);
    };
  }

  /* ---------------- side ---------------- */
  function renderSide() {
    const el = $('[data-side]', root), c = S.customer, a = S.shipping_address || c;
    let cust;
    if (c) {
      cust = `<div class="side-sec"><div class="side-head">Customer ${readOnly ? '' : `<button class="icon-btn" data-cmenu type="button">${ic('dots')}</button>`}</div>
        <a class="link" href="${esc(url('customers/' + c.id))}">${esc(c.name)}</a><div class="link" style="margin-top:2px">${c.orders_count} order${c.orders_count === 1 ? '' : 's'}</div>
        <div style="margin-top:14px" class="link">${esc(c.email || '')}</div><div>${esc(c.phone || '')}</div></div>
        <div class="side-sec"><div class="side-head">Shipping address ${readOnly ? '' : `<button class="icon-btn" data-amenu type="button">${ic('dots')}</button>`}</div>
          ${a && (a.address1 || a.city) ? `<div style="line-height:1.75">${esc(a.first_name || '')} ${esc(a.last_name || '')}<br>${esc(a.address1 || '')}${a.address2 ? '<br>' + esc(a.address2) : ''}<br>${esc(a.city || '')} ${esc(a.zip || '')}<br>${esc(a.country || '')}</div><a class="link" target="_blank" rel="noopener" href="https://maps.google.com/?q=${encodeURIComponent([a.address1, a.city, a.country].filter(Boolean).join(', '))}">View map</a>` : '<div class="muted">No shipping address provided</div>'}</div>
        <div class="side-sec"><div class="side-head">Billing address</div><div class="muted">Same as shipping address</div></div>`;
    } else {
      cust = `<div class="side-sec"><div class="side-head">Customer</div><div class="cs-wrap"><label class="input-affix">${ic('search')}<input placeholder="Search or create a customer" data-csearch ${readOnly ? 'disabled' : ''}></label></div></div>`;
    }
    el.innerHTML = `<div class="side-sec"><div class="side-head">Notes ${readOnly ? '' : `<button class="icon-btn" data-note type="button">${S.note ? ic('dots') : ic('plus')}</button>`}</div>${S.note ? `<div style="white-space:pre-wrap">${esc(S.note)}</div>` : ''}</div>
      ${cust}
      <div class="side-sec"><div class="side-head">Markets <span class="icon-btn">${ic('chevr')}</span></div><span class="tag-chip" style="padding:6px 10px">${esc(S.market)}</span>
        <div class="label-sm" style="margin-top:14px;font-weight:500">Currency</div><div class="select-wrap"><select class="input" disabled><option>Bangladeshi Taka (${esc(window.AMARFY.cur)} ${esc(window.AMARFY.sym)})</option></select>${ic('updown')}</div></div>
      <div class="side-sec"><div class="side-head">Tags</div><div data-tags></div></div>`;
    const cs = $('[data-csearch]', el);
    if (cs && !readOnly) customerSearch(cs, cust => { S.customer = cust; S.shipping_address = null; render(); });
    const cm = $('[data-cmenu]', el);
    if (cm) cm.onclick = e => { e.stopPropagation(); popMenu(e.currentTarget, [{label: 'Edit contact information', action: editContact}, {label: 'Remove customer', danger: true, action: () => { S.customer = null; S.shipping_address = null; render(); }}]); };
    const am = $('[data-amenu]', el);
    if (am) am.onclick = e => { e.stopPropagation(); popMenu(e.currentTarget, [{label: 'Edit shipping address', action: editAddress}]); };
    const nb = $('[data-note]', el);
    if (nb) nb.onclick = () => {
      const m = modal({title: 'Notes', body: `<textarea class="input ta" data-n>${esc(S.note)}</textarea><label class="field"><span>PO number</span><input data-po value="${esc(S.po_number)}"></label>`, footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Done</button>'});
      m.$('[data-ok]').onclick = () => { S.note = m.$('[data-n]').value.trim(); S.po_number = m.$('[data-po]').value.trim(); m.close(); render(); };
    };
    const tw = $('[data-tags]', el);
    if (readOnly) tw.innerHTML = `<div class="tags-wrap">${S.tags.map(t => `<span class="tag-chip">${esc(t)}</span>`).join('') || '<span class="muted">No tags</span>'}</div>`;
    else tagsInput(tw, S.tags, t => { S.tags = t; dirty(); });
  }
  function editContact() {
    const c = S.customer;
    const m = modal({title: 'Edit contact information', body: `<div class="row2"><label class="field"><span>First name</span><input data-f="first_name" value="${esc(c.first_name || '')}"></label><label class="field"><span>Last name</span><input data-f="last_name" value="${esc(c.last_name || '')}"></label></div><label class="field"><span>Email</span><input data-f="email" value="${esc(c.email || '')}"></label><label class="field"><span>Phone</span><input data-f="phone" value="${esc(c.phone || '')}"></label>`, footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Save</button>'});
    m.$('[data-ok]').onclick = async () => {
      const d = Object.assign({}, c, {id: c.id}); m.el.querySelectorAll('[data-f]').forEach(i => d[i.dataset.f] = i.value.trim());
      const r = await api('customers/save', d); S.customer = r.customer; m.close(); render();
    };
  }
  function editAddress() {
    const a = Object.assign({first_name: S.customer.first_name, last_name: S.customer.last_name, country: 'Bangladesh'}, S.shipping_address || S.customer);
    const f = (k, l) => `<label class="field"><span>${l}</span><input data-f="${k}" value="${esc(a[k] || '')}"></label>`;
    const m = modal({title: 'Edit shipping address', body: `${f('country', 'Country/region')}<div class="row2">${f('first_name', 'First name')}${f('last_name', 'Last name')}</div>${f('company', 'Company')}${f('address1', 'Address')}${f('address2', 'Apartment, suite, etc.')}<div class="row2">${f('city', 'City')}${f('zip', 'Postal code')}</div>${f('phone', 'Phone')}`, footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Save</button>'});
    m.$('[data-ok]').onclick = () => { const d = {}; m.el.querySelectorAll('[data-f]').forEach(i => d[i.dataset.f] = i.value.trim()); S.shipping_address = d; m.close(); render(); };
  }

  function render() { renderProducts(); renderPayment(); renderSide(); dirty(); }
  render();

  /* top bar actions */
  const dup = $('#dDuplicate'), share = $('#dShare'), more = $('#dMore');
  if (dup) dup.onclick = async () => { const r = await api('drafts/duplicate', {id: S.id}); location.href = url('draft_orders/' + r.id); };
  if (share) share.onclick = () => { navigator.clipboard && navigator.clipboard.writeText(location.href); toast('Link copied'); };
  if (more) more.onclick = e => { e.stopPropagation(); popMenu(e.currentTarget, [
    {label: 'Print', action: () => print()},
    {label: 'Delete draft order', danger: true, action: async () => { if (await confirmBox(`Delete #D${S.number}?`, 'This can’t be undone.', 'Delete', true)) { await api('drafts/delete', {id: S.id}); bar.hide(); location.href = url('draft_orders'); } }},
  ]); };
});
