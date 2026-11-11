/* Amarfy AI assistant (store search + intents), quick create, voice input, recents, notifications, help. */
(function () {
  const {$, $$, esc, api, toast, modal, popMenu, url} = window.UI;
  const RKEY = 'amarfy:ai-recents';
  const recents = {
    get() { try { return JSON.parse(localStorage.getItem(RKEY) || '[]'); } catch (e) { return []; } },
    add(q) { try { const r = [q, ...this.get().filter(x => x !== q)].slice(0, 8); localStorage.setItem(RKEY, JSON.stringify(r)); } catch (e) {} },
    clear() { try { localStorage.removeItem(RKEY); } catch (e) {} },
  };

  // Plain-language requests → store pages
  const INTENTS = [
    [/(create|new|make).*(order)|^order$/i, 'Create an order', 'draft_orders/new'],
    [/(add|create|new).*(product)/i, 'Add a product', 'products/new'],
    [/(add|create|new).*(customer)/i, 'Add a customer', 'customers/new'],
    [/(add|create|new).*(collection)/i, 'Create a collection', 'collections/new'],
    [/(gift ?card)/i, 'Create a gift card', 'gift_cards/new'],
    [/(purchase order|supplier|restock)/i, 'Create a purchase order', 'purchase_orders/new'],
    [/(transfer|move stock|move inventory)/i, 'Create a transfer', 'transfers/new'],
    [/(unfulfilled|to fulfil|to fulfill|ship|pending shipment)/i, 'Orders to fulfill', 'orders?view=unfulfilled'],
    [/(unpaid|payment pending|capture|collect payment)/i, 'Orders with payment pending', 'orders?view=unpaid'],
    [/(out of stock|low stock|sold out)/i, 'Out-of-stock inventory', 'products/inventory?view=out_of_stock'],
    [/(inventory|stock)/i, 'Inventory', 'products/inventory'],
    [/(draft)/i, 'Draft orders', 'draft_orders'],
    [/(abandon|cart)/i, 'Abandoned checkouts', 'checkouts'],
    [/(export).*(order)/i, 'Export orders (CSV)', 'export/orders'],
    [/(export).*(product)/i, 'Export products (CSV)', 'export/products'],
    [/(sales|revenue|report)/i, 'Sales over time (Home)', 'home'],
  ];

  async function ask(q, panelEl) {
    q = q.trim(); if (!q) return;
    recents.add(q);
    const m = panelEl || modal({title: 'Amarfy AI', size: 'wide', footer: false, body: '<div data-ai-out></div>'});
    const out = (m.$ ? m.$('[data-ai-out]') : m.querySelector('[data-ai-out]'));
    out.innerHTML = `<div class="ai-q">${esc(q)}</div><div class="muted">Looking through your store…</div>`;
    const intents = INTENTS.filter(([re]) => re.test(q)).slice(0, 3);
    let groups = [];
    try { groups = (await api('search/all?q=' + encodeURIComponent(q.replace(/^(find|show|search( for)?|open)\s+/i, '')))).groups; } catch (e) {}
    let h = `<div class="ai-q">${esc(q)}</div>`;
    if (intents.length) h += `<div class="ai-sec">Suggested actions</div>` + intents.map(([, l, href]) => `<a class="ai-row" href="${esc(url(href))}"><b>${esc(l)}</b><span class="muted">→</span></a>`).join('');
    if (groups.length) h += groups.map(g => `<div class="ai-sec">${esc(g.label)}</div>` + g.items.map(i => `<a class="ai-row" href="${esc(url(i.href))}">${esc(i.title)}<span class="muted">${esc(i.sub || '')}</span></a>`).join('')).join('');
    if (!intents.length && !groups.length) h += `<div class="ai-empty">I couldn’t find anything for “${esc(q)}”. Try a product name, an order number like <b>#1001</b>, a customer, or a request such as <b>create order</b>, <b>out of stock</b> or <b>unpaid orders</b>.</div>`;
    out.innerHTML = h;
  }

  function quickCreate(anchor) {
    popMenu(anchor, [
      {label: 'Create order', action: () => location.href = url('draft_orders/new')},
      {label: 'Add product', action: () => location.href = url('products/new')},
      {label: 'Add customer', action: () => location.href = url('customers/new')},
      {label: 'Create collection', action: () => location.href = url('collections/new')},
      {label: 'Create gift card', action: () => location.href = url('gift_cards/new')},
      {label: 'Create purchase order', action: () => location.href = url('purchase_orders/new')},
      {label: 'Create transfer', action: () => location.href = url('transfers/new')},
    ]);
  }

  function voice(input, btn) {
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) { toast('Voice input isn’t supported in this browser. Try Chrome.', true); return; }
    const r = new SR(); r.lang = 'en-US'; r.interimResults = true;
    btn.classList.add('listening'); toast('Listening… speak now');
    r.onresult = e => { input.value = [...e.results].map(x => x[0].transcript).join(''); input.dispatchEvent(new Event('input')); };
    r.onerror = e => toast(e.error === 'not-allowed' ? 'Microphone permission was denied' : 'Voice input stopped', true);
    r.onend = () => { btn.classList.remove('listening'); if (input.value.trim()) ask(input.value); };
    r.start();
  }

  function showRecents(anchor, input) {
    const list = recents.get();
    popMenu(anchor, list.length
      ? [...list.map(q => ({label: q, action: () => { input.value = q; ask(q); }})), '-', {label: 'Clear recents', action: () => { recents.clear(); toast('Recents cleared'); }}]
      : [{label: 'No recent questions yet', disabled: true}]);
  }

  function openPanel(prefill = '') {
    const m = modal({title: 'Amarfy AI', size: 'wide', footer: false,
      body: `<label class="input-affix" style="height:40px">${UI.ic('search')}<input data-ai-q placeholder="Ask about orders, products, customers… e.g. “unpaid orders”" value="${esc(prefill)}"></label><div data-ai-out><div class="muted">Recent: ${recents.get().slice(0, 4).map(q => `<button type="button" class="link-btn" data-rq="${esc(q)}">${esc(q)}</button>`).join(' · ') || 'none yet'}</div></div>`});
    const inp = m.$('[data-ai-q]');
    inp.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); ask(inp.value, m.el); } });
    m.$$('[data-rq]').forEach(b => b.onclick = () => { inp.value = b.dataset.rq; ask(b.dataset.rq, m.el); });
    if (prefill) ask(prefill, m.el);
  }

  async function bell(anchor) {
    const r = await api('notifications/list');
    popMenu(anchor, r.items.length
      ? r.items.map(i => ({label: `${i.count} ${i.text}`, action: () => location.href = url(i.href)}))
      : [{label: 'You’re all caught up 🎉', disabled: true}]);
  }

  const HELP = {
    'orders': 'Orders are created when customers check out or when you complete a draft order. Use the filters to find unfulfilled or unpaid orders, select several to mark them paid or fulfilled in bulk, and export them to CSV from the top bar.',
    'creating draft orders': 'Draft orders let you create orders for customers yourself — for phone, chat or wholesale sales. Add products or custom items, apply a discount or shipping, then send an invoice or collect payment to turn the draft into an order.',
    'buying shipping labels': 'Use the rate calculator to estimate delivery costs inside and outside Dhaka. Fulfill orders from the order page once the parcel is packed so stock and delivery status stay accurate.',
    'abandoned checkouts': 'An abandoned checkout is a cart where the customer entered details but didn’t finish paying. Follow up by email to recover the sale; recovered checkouts are marked automatically.',
    'products': 'Products hold the title, images, description, price and stock you sell. Add options like size and colour to create variants, track inventory per location, and group products into collections.',
    'collections': 'Collections group products so customers can browse them. Manual collections contain the products you pick; smart collections update automatically from conditions like price, type, vendor or tag.',
    'purchase orders': 'Purchase orders track stock you buy from suppliers. Mark a purchase order as ordered to show incoming stock, then receive items to add them to inventory at the destination.',
    'transfers': 'Transfers move stock between your locations. Marking a transfer in transit removes the stock from the origin; receiving it adds the stock at the destination.',
    'gift cards': 'Gift cards are store credit with a unique code. Issue them to customers, set an expiry date if needed, adjust the balance, or disable a card that should no longer be used.',
    'customers': 'Customer profiles keep contact details, addresses, notes and order history together. Customers are created automatically from orders, or you can add them yourself.',
  };
  function help(topic) {
    modal({title: 'About ' + topic, size: 'sm', body: `<p style="line-height:1.6">${esc(HELP[topic] || 'This section helps you manage ' + topic + ' for your store.')}</p>`, footer: '<button class="btn primary" data-close>Got it</button>'});
  }

  document.addEventListener('DOMContentLoaded', () => {
    $$('[data-ai-input]').forEach(inp => inp.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); const q = inp.value; if (q.trim()) { ask(q); inp.value = ''; } }
    }));
    const firstInput = () => $('[data-ai-input]');
    $$('[data-ai-plus]').forEach(b => b.addEventListener('click', e => { e.stopPropagation(); quickCreate(b); }));
    $$('[data-ai-mic]').forEach(b => b.addEventListener('click', () => voice(b.closest('.prompt, .ai-inner').querySelector('[data-ai-input]') || firstInput(), b)));
    $$('[data-ai-recents]').forEach(b => b.addEventListener('click', e => { e.stopPropagation(); showRecents(b, firstInput()); }));
    $$('[data-ai-panel]').forEach(b => b.addEventListener('click', () => openPanel(($('#aiInput') || {}).value || '')));
    const bb = $('#bellBtn'); if (bb) bb.addEventListener('click', e => { e.stopPropagation(); bell(bb); });
    document.addEventListener('click', e => { const h = e.target.closest('[data-help]'); if (h) { e.preventDefault(); help(h.dataset.help); } });
  });
  window.AmarfyAI = {ask, openPanel};
})();
