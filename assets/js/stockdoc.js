/* Purchase order + transfer editor */
document.addEventListener('DOMContentLoaded', () => {
  const {$, $$, esc, money, ic, api, toast, modal, confirmBox, popMenu, saveBar, tagsInput, productPicker, url} = UI;
  const D = window.DOC, root = $('#docApp'), isPO = D.kind === 'po';
  const editable = D.status === 'draft';
  const base = isPO ? 'purchase_orders' : 'transfers';
  const TERMS = ['', 'Cash on delivery', 'Payment on receipt', 'Payment in advance', 'Net 7', 'Net 15', 'Net 30', 'Net 45', 'Net 60'];
  const payload = () => isPO
    ? {id: D.id, supplier_id: D.supplier_id, destination_id: D.destination_id, reference: D.reference, note: D.note, terms: D.terms, expected_arrival: D.expected_arrival, carrier: D.carrier, tracking: D.tracking, shipping: D.shipping, tags: D.tags, items: D.items}
    : {id: D.id, origin_id: D.origin_id, destination_id: D.destination_id, transfer_date: D.transfer_date, reference: D.reference, note: D.note, tags: D.tags, purchase_order_id: D.purchase_order_id, items: D.items};
  let snapshot = JSON.stringify(payload());
  const bar = saveBar({label: D.id ? 'Unsaved changes' : (isPO ? 'Unsaved purchase order' : 'Unsaved transfer'), onSave: save, onDiscard: () => location.reload()});
  const dirty = () => { if (editable) JSON.stringify(payload()) !== snapshot ? bar.show() : bar.hide(); };
  async function save() {
    const r = await api(base + '/save', payload());
    snapshot = JSON.stringify(payload()); bar.hide();
    location.href = url(base + '/' + r.id);
  }
  const locName = id => (D.locations.find(l => l.id === +id) || {}).name;
  const supName = id => (D.suppliers || []).find(s => +s.id === +id)?.name;

  function routeBtn(kind, label, icon, value) {
    return `<div class="route-pick"><button type="button" data-route="${kind}" ${editable ? '' : 'disabled'}>${ic(icon)} ${esc(value || label)} ${editable ? ic('chev') : ''}</button></div>`;
  }
  function pickRoute(btn) {
    const kind = btn.dataset.route;
    if (kind === 'supplier') {
      popMenu(btn, [...D.suppliers.map(s => ({label: s.name, action: () => { D.supplier_id = +s.id; render(); }})), '-', {label: '+ Create new supplier', action: supplierModal}]);
    } else {
      popMenu(btn, D.locations.map(l => ({label: l.name, action: () => { D[kind + '_id'] = l.id; render(); }})));
    }
  }
  function supplierModal() {
    const m = modal({title: 'Create supplier', body: `<label class="field"><span>Company</span><input data-f="name"></label><div class="row2"><label class="field"><span>Contact name</span><input data-f="contact_name"></label><label class="field"><span>Phone</span><input data-f="phone"></label></div><label class="field"><span>Email</span><input data-f="email" type="email"></label><label class="field"><span>Address</span><input data-f="address"></label><label class="field"><span>Country/region</span><input data-f="country" value="Bangladesh"></label>`, footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Save</button>'});
    m.$('[data-ok]').onclick = async () => { const d = {}; m.$$('[data-f]').forEach(i => d[i.dataset.f] = i.value.trim()); const r = await api('purchase_orders/save_supplier', d); D.suppliers.push(r.supplier); D.supplier_id = +r.supplier.id; m.close(); render(); };
  }
  function addProducts(q = '') {
    productPicker({selected: D.items.map(i => i.variant_id), locationId: isPO ? D.destination_id : D.origin_id, onAdd: list => {
      list.forEach(({product, variant}) => {
        if (D.items.find(i => i.variant_id === variant.id)) return;
        D.items.push({variant_id: variant.id, product_id: product.id, title: product.title, variant_title: variant.title === 'Default Title' ? '' : variant.title, image: product.image, sku: variant.sku, supplier_sku: '', quantity: 1, received: 0, cost: variant.cost || 0, tax_rate: 0, available: variant.available});
      });
      render();
    }});
    setTimeout(() => { const i = document.querySelector('.modal [data-q]'); if (i && q) { i.value = q; i.dispatchEvent(new Event('input')); } }, 50);
  }

  function renderMain() {
    const el = $('[data-main]', root);
    const route = isPO
      ? `${routeBtn('supplier', 'Select supplier', 'building', supName(D.supplier_id))}<span class="route-arrow">${ic('arrow')}</span>${routeBtn('destination', 'Select destination', 'pin', locName(D.destination_id))}`
      : `${routeBtn('origin', 'Select origin', 'pin', locName(D.origin_id))}<span class="route-arrow">${ic('arrow')}</span><span style="display:inline-flex;gap:8px;align-items:center;color:#4a4a4a">${ic('truck')} In transit</span><span class="route-arrow">${ic('arrow')}</span>${routeBtn('destination', 'Select destination', 'pin', locName(D.destination_id))}`;
    let rows = '';
    if (D.items.length) {
      const showSku = isPO && (editable || D.items.some(i => i.supplier_sku));
      rows = `<div class="table-wrap"><table class="grid compact"><thead><tr><th>Product</th>${showSku ? '<th>Supplier SKU</th>' : ''}<th class="num" style="width:110px">Quantity</th>${isPO ? '<th class="num" style="width:130px">Cost</th><th class="num" style="width:90px">Tax %</th><th class="num">Total</th>' : ''}${D.status !== 'draft' ? '<th class="num">Received</th>' : ''}${editable ? '<th></th>' : ''}</tr></thead><tbody>
        ${D.items.map((i, n) => `<tr><td><div class="prod-cell">${i.image ? `<span class="thumb-sm thumb-xs"><img src="${esc(url(i.image))}"></span>` : `<span class="thumb-sm thumb-xs empty">${ic('image')}</span>`}<div><a href="${esc(url('products/' + i.product_id))}" class="strong">${esc(i.title)}</a>${i.variant_title ? `<br><span class="vchip">${esc(i.variant_title)}</span>` : ''}<div class="sub-line">${esc(i.sku || 'No SKU')}</div></div></div></td>
          ${showSku ? `<td>${editable ? `<input class="input" style="height:32px;width:120px" data-li="supplier_sku:${n}" value="${esc(i.supplier_sku || '')}">` : esc(i.supplier_sku || '')}</td>` : ''}
          <td class="num">${editable ? `<input class="input" type="number" min="1" style="height:32px;width:90px;text-align:right" data-li="quantity:${n}" value="${i.quantity}">` : i.quantity}</td>
          ${isPO ? `<td class="num">${editable ? `<div class="input-affix" style="height:32px;width:120px;margin-left:auto"><span class="pre">${esc(window.AMARFY.sym)}</span><input type="number" min="0" step="0.01" data-li="cost:${n}" value="${i.cost}"></div>` : money(i.cost)}</td>
            <td class="num">${editable ? `<input class="input" type="number" min="0" max="100" style="height:32px;width:70px;text-align:right" data-li="tax_rate:${n}" value="${i.tax_rate}">` : i.tax_rate + '%'}</td><td class="num">${money(i.quantity * i.cost * (1 + i.tax_rate / 100))}</td>` : ''}
          ${D.status !== 'draft' ? `<td class="num">${i.received} of ${i.quantity}</td>` : ''}
          ${editable ? `<td><button class="icon-btn" data-rm="${n}" type="button">${ic('x')}</button></td>` : ''}</tr>`).join('')}</tbody></table></div>`;
    }
    let details = '';
    if (!isPO) {
      const po = (D.purchase_orders || []).find(p => +p.id === +D.purchase_order_id);
      details = `<div class="sec-head" style="margin-top:36px"><h2>Transfer details</h2></div>
        <div class="card card-pad detail-list">
          <div class="dl"><span>${ic('calendar')}</span><label class="date-chip" style="position:relative">${esc(new Date(D.transfer_date + 'T00:00').toLocaleDateString('en-US', {month: 'long', day: 'numeric', year: 'numeric'}))}<input type="date" data-df="transfer_date" value="${esc(D.transfer_date)}" style="position:absolute;inset:0;opacity:0" ${editable ? '' : 'disabled'}></label></div>
          <div class="dl" data-edit="reference"><span>${ic('pencil')}</span><span class="dl-t">${D.reference ? esc(D.reference) : 'Add reference name'}</span></div>
          <div class="dl" data-edit="note"><span>${ic('info')}</span><span class="dl-t">${D.note ? esc(D.note) : 'Add note'}</span></div>
          <div class="dl" data-edit="tags"><span style="font-weight:700">#</span><span class="dl-t">${D.tags.length ? D.tags.map(t => `<span class="tag-chip">${esc(t)}</span>`).join(' ') : 'Add tags'}</span></div>
          <div class="dl" data-edit="po"><span>${ic('products')}</span><span class="dl-t">${po ? 'Linked to #PO' + po.number : 'Link purchase order'}</span></div></div>`;
    }
    el.innerHTML = `<div class="card"><div class="route-bar">${route}</div>
      ${editable ? `<div class="prod-search">${ic('search')}<input placeholder="Search products to add" data-psearch><button class="icon-btn" type="button" data-browse title="Browse">${ic('download')}</button><button class="icon-btn" type="button" data-scan title="Scan barcode">${ic('barcode')}</button></div>` : ''}
      ${rows}</div>${details}${actionsHtml()}`;
    $$('[data-route]', el).forEach(b => b.onclick = e => { e.stopPropagation(); pickRoute(b); });
    const ps = $('[data-psearch]', el);
    if (ps) { ps.onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); addProducts(ps.value); ps.value = ''; } }; ps.onfocus = () => { addProducts(ps.value); ps.blur(); }; }
    const br = $('[data-browse]', el); if (br) br.onclick = () => addProducts();
    const sc = $('[data-scan]', el); if (sc) sc.onclick = () => { const code = prompt('Scan or type a barcode / SKU'); if (code) addProducts(code); };
    $$('[data-li]', el).forEach(i => i.onchange = () => { const [k, n] = i.dataset.li.split(':'); D.items[+n][k] = k === 'supplier_sku' ? i.value : Math.max(k === 'quantity' ? 1 : 0, parseFloat(i.value) || 0); render(); });
    $$('[data-rm]', el).forEach(b => b.onclick = () => { D.items.splice(+b.dataset.rm, 1); render(); });
    $$('[data-df]', el).forEach(i => i.onchange = () => { D[i.dataset.df] = i.value; render(); });
    if (editable) $$('[data-edit]', el).forEach(d => d.onclick = () => editDetail(d.dataset.edit));
    bindActions(el);
  }
  function editDetail(k) {
    if (k === 'tags') {
      const m = modal({title: 'Tags', body: '<div data-t></div>', footer: '<button class="btn primary" data-close>Done</button>'});
      tagsInput(m.$('[data-t]'), D.tags, t => { D.tags = t; render(); });
      return;
    }
    if (k === 'po') { popMenu($('[data-edit=po]', root), [{label: 'None', action: () => { D.purchase_order_id = null; render(); }}, ...(D.purchase_orders || []).map(p => ({label: '#PO' + p.number + ' · ' + p.status, action: () => { D.purchase_order_id = +p.id; render(); }}))]); return; }
    const m = modal({title: k === 'note' ? 'Note' : 'Reference name', size: 'sm', body: k === 'note' ? `<textarea class="input ta" data-v>${esc(D.note)}</textarea>` : `<input class="input" data-v value="${esc(D.reference)}">`, footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Done</button>'});
    m.$('[data-ok]').onclick = () => { D[k] = m.$('[data-v]').value.trim(); m.close(); render(); };
  }
  function actionsHtml() {
    if (!D.id) return '';
    const b = [];
    if (D.status === 'draft') b.push(isPO ? '<button class="btn primary" data-act="mark_ordered">Mark as ordered</button>' : '<button class="btn primary" data-act="ship">Mark as in transit</button>');
    if (isPO && ['ordered', 'partial'].includes(D.status)) b.push('<button class="btn primary" data-receive>Receive inventory</button>');
    if (!isPO && D.status === 'in_transit') b.push('<button class="btn primary" data-act="receive">Receive inventory</button>');
    return b.length ? `<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">${b.join('')}</div>` : '';
  }
  function bindActions(el) {
    $$('[data-act]', el).forEach(b => b.onclick = async () => {
      if (bar.visible) { toast('Save your changes first', true); return; }
      await api(base + '/' + b.dataset.act, {id: D.id}); location.reload();
    });
    const rc = $('[data-receive]', el);
    if (rc) rc.onclick = () => {
      const m = modal({title: 'Receive inventory', size: 'wide', body: `<table class="grid"><thead><tr><th>Product</th><th class="num">Received</th><th class="num" style="width:140px">Accept now</th></tr></thead><tbody>${D.items.map(i => `<tr><td>${esc(i.title)} ${i.variant_title ? `<span class="vchip">${esc(i.variant_title)}</span>` : ''}</td><td class="num">${i.received} of ${i.quantity}</td><td class="num"><input class="input" type="number" min="0" max="${i.quantity - i.received}" value="${i.quantity - i.received}" data-line="${i.line_id}"></td></tr>`).join('')}</tbody></table>`,
        footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Save</button>'});
      m.$('[data-ok]').onclick = async () => { const lines = {}; m.$$('[data-line]').forEach(i => lines[i.dataset.line] = parseInt(i.value) || 0); await api('purchase_orders/receive', {id: D.id, lines}); location.reload(); };
    };
  }

  function renderSide() {
    const el = $('[data-side]', root); if (!el) return;
    let sub = 0, tax = 0, cnt = 0;
    D.items.forEach(i => { sub += i.quantity * i.cost; tax += i.quantity * i.cost * i.tax_rate / 100; cnt += +i.quantity; });
    const dis = editable ? '' : 'disabled';
    el.innerHTML = `<div class="sec-head" style="margin-top:0"><h2>Cost summary</h2><div class="acts"><button class="icon-btn" type="button" data-shipedit ${dis}>${ic('pencil')}</button></div></div>
      <div class="card card-pad cost-card"><div class="label-sm" style="font-weight:500">Order details</div>
        <div class="kv"><span>${D.items.length} variants (${cnt} items)</span><span>${money(sub)}</span></div>
        <div class="kv"><span>Taxes (Included)</span><span>${money(tax)}</span></div>
        ${D.shipping ? `<div class="kv"><span>Shipping</span><span>${money(D.shipping)}</span></div>` : ''}
        <div class="kv" style="border-top:1px solid var(--line);margin-top:10px;padding-top:16px;font-weight:650;font-size:15px"><span>Total</span><span>${money(sub + tax + (+D.shipping || 0))}</span></div></div>
      <div class="sec-head" style="margin-top:36px"><h2>Purchase order details</h2></div>
      <div class="card"><div class="card-sec" style="display:flex;flex-direction:column;gap:14px">
        <label class="field"><span>Reference number</span><div class="counter-in"><input class="input" maxlength="255" data-sf="reference" value="${esc(D.reference)}" ${dis}><span class="cnt">${D.reference.length}/255</span></div></label>
        <label class="field"><span>Note to supplier</span><div class="counter-in"><textarea class="input ta" maxlength="5000" data-sf="note" ${dis}>${esc(D.note)}</textarea><span class="cnt">${D.note.length}/5000</span></div></label>
        <label class="field"><span>Expected arrival</span><input class="input" type="date" data-sf="expected_arrival" value="${esc(D.expected_arrival || '')}" ${dis}></label>
        <div class="row2"><label class="field"><span>Shipping carrier</span><input class="input" data-sf="carrier" value="${esc(D.carrier)}" ${dis}></label><label class="field"><span>Tracking number</span><input class="input" data-sf="tracking" value="${esc(D.tracking)}" ${dis}></label></div></div>
        <div class="card-sec" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><span class="term-chip">Terms <b>${esc(D.terms || 'None')}</b><select data-sf="terms" ${dis}>${TERMS.map(t => `<option value="${t}" ${t === D.terms ? 'selected' : ''}>${t || 'None'}</option>`).join('')}</select></span><span class="term-chip">Currency <b>${esc(window.AMARFY.cur)} ${esc(window.AMARFY.sym)}</b></span></div></div>
      <div class="sec-head" style="margin-top:36px"><h2>Tags</h2></div><div class="card card-pad" data-tags></div>`;
    $$('[data-sf]', el).forEach(i => { i.oninput = () => { D[i.dataset.sf] = i.value; const c = i.parentElement.querySelector('.cnt'); if (c) c.textContent = i.value.length + '/' + i.maxLength; dirty(); }; i.onchange = () => { D[i.dataset.sf] = i.value; if (i.tagName === 'SELECT') renderSide(); dirty(); }; });
    const se = $('[data-shipedit]', el);
    if (se) se.onclick = () => { const m = modal({title: 'Edit cost summary', size: 'sm', body: `<label class="field"><span>Shipping</span><div class="input-affix"><span class="pre">${esc(window.AMARFY.sym)}</span><input type="number" min="0" step="0.01" data-v value="${D.shipping}"></div></label>`, footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Done</button>'}); m.$('[data-ok]').onclick = () => { D.shipping = Math.max(0, parseFloat(m.$('[data-v]').value) || 0); m.close(); render(); }; };
    if (editable) tagsInput($('[data-tags]', el), D.tags, t => { D.tags = t; dirty(); });
    else $('[data-tags]', el).innerHTML = D.tags.map(t => `<span class="tag-chip">${esc(t)}</span>`).join(' ') || '<span class="muted">No tags</span>';
  }

  function render() { renderMain(); if (isPO) renderSide(); dirty(); }
  render();
  const more = $('#docMore');
  if (more) more.onclick = e => { e.stopPropagation(); const items = [{label: 'Print', action: () => print()}];
    if (isPO && ['ordered', 'partial'].includes(D.status)) items.push({label: 'Close purchase order', action: async () => { await api('purchase_orders/close', {id: D.id}); location.reload(); }});
    if (D.status === 'draft') items.push('-', {label: 'Delete', danger: true, action: async () => { if (await confirmBox('Delete this ' + (isPO ? 'purchase order' : 'transfer') + '?', 'This can’t be undone.', 'Delete', true)) { await api(base + '/delete', {id: D.id}); bar.hide(); location.href = url(base); } }});
    popMenu(e.currentTarget, items); };
});
