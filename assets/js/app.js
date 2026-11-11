/* Amarfy admin — shared client helpers */
(function () {
  const A = window.AMARFY;
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const money = (v, code) => A.sym + Number(v || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + (code ? ' ' + A.cur : '');
  const url = p => A.base + '/' + String(p).replace(/^\//, '');
  const IC = {
    x: '<path d="m4 4 8 8M12 4l-8 8"/>', plus: '<path d="M8 3v10M3 8h10"/>', search: '<circle cx="7" cy="7" r="4.6"/><path d="m10.5 10.5 3.2 3.2"/>',
    image: '<rect x="2.5" y="2.5" width="11" height="11" rx="2"/><circle cx="6" cy="6" r="1"/><path d="m3 12 3.5-3.5 2.5 2.5 1.5-1.5L13 12"/>',
    updown: '<path d="m5.5 6 2.5-2.5L10.5 6M5.5 10l2.5 2.5 2.5-2.5"/>', chev: '<path d="m4.5 6.5 3.5 3.5 3.5-3.5"/>',
    addc: '<circle cx="8" cy="8" r="6"/><path d="M8 5v6M5 8h6"/>', dots: '<circle cx="3.5" cy="8" r=".9" fill="currentColor"/><circle cx="8" cy="8" r=".9" fill="currentColor"/><circle cx="12.5" cy="8" r=".9" fill="currentColor"/>',
    chevr: '<path d="m6.5 4 4 4-4 4"/>', 'chev-up': '<path d="m4.5 9.5 3.5-3.5 3.5 3.5"/>',
    pencil: '<path d="M10.5 2.8l2.7 2.7-7.4 7.4-3.3.6.6-3.3z"/><path d="m9.3 4 2.7 2.7"/>',
    info: '<circle cx="8" cy="8" r="6"/><path d="M8 7.3v3.7"/><circle cx="8" cy="5.2" r=".5" fill="currentColor"/>',
    pin: '<path d="M8 14s4.5-4 4.5-7.5a4.5 4.5 0 0 0-9 0C3.5 10 8 14 8 14z"/><circle cx="8" cy="6.5" r="1.6"/>',
    arrow: '<path d="M3 8h10M9.5 4.5 13 8l-3.5 3.5"/>', truck: '<path d="M1.5 4h8v7h-8zM9.5 6.5h2.5l2 2.5V11h-4.5"/><circle cx="4" cy="11.8" r="1.2"/><circle cx="11.5" cy="11.8" r="1.2"/>',
    building: '<rect x="3" y="2" width="10" height="12" rx="1.5"/><path d="M6 5h1M9 5h1M6 7.5h1M9 7.5h1M6 10h1M9 10h1M7 14v-2h2v2"/>',
    calendar: '<rect x="2.5" y="3.5" width="11" height="10" rx="2"/><path d="M2.5 6.5h11M5.5 2v3M10.5 2v3"/>',
    download: '<path d="M8 2.5v8M4.5 7 8 10.5 11.5 7M3 13.5h10"/>', barcode: '<path d="M2.5 5V3.5h2M11.5 3.5h2V5M13.5 11v1.5h-2M4.5 12.5h-2V11M5.5 6v4M7.5 6v4M9 6v4M10.5 6v4"/>',
    products: '<path d="M8.6 2.5H13a.5.5 0 0 1 .5.5v4.4L7.3 13.6a1 1 0 0 1-1.4 0L2.4 10.1a1 1 0 0 1 0-1.4z"/><circle cx="10.6" cy="5.4" r=".9"/>',
    upload: '<path d="M8 13V5M4.5 8.5 8 5l3.5 3.5M3.5 2.5h9"/>', channels: '<circle cx="5" cy="5" r="2"/><circle cx="11" cy="11" r="2"/><circle cx="4.5" cy="11.5" r="1.5"/><path d="M6.5 6.5l3 3M5 7v3"/>', trash: '<path d="M3 4.5h10M6.5 4.5V3h3v1.5M4.5 4.5l.6 8.5a1 1 0 0 0 1 1h3.8a1 1 0 0 0 1-1l.6-8.5"/>'
  };
  const ic = (n, cls = '') => `<svg class="ic ${cls}" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">${IC[n] || ''}</svg>`;

  async function api(path, data, opts = {}) {
    const isGet = data === undefined && !opts.method;
    const init = {method: opts.method || (isGet ? 'GET' : 'POST'), headers: {'X-CSRF-Token': A.csrf, 'Accept': 'application/json'}};
    if (data instanceof FormData) init.body = data;
    else if (data !== undefined) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(data); }
    let res, json;
    try { res = await fetch(url('api/' + path), init); json = await res.json(); }
    catch (e) { toast('Network error. Check your connection.', true); throw e; }
    if (res.status === 401) { location.href = url('login'); throw new Error('auth'); }
    if (!json.ok) {
      toast(json.error || 'Request failed', true);
      const err = new Error(json.error); err.shown = true;   // already shown to the user as a toast
      throw err;
    }
    return json;
  }

  function toast(msg, err) {
    const t = document.createElement('div');
    t.className = 'toast' + (err ? ' err' : '');
    t.textContent = msg;
    $('#toasts').appendChild(t);
    setTimeout(() => t.remove(), 3200);
  }

  /* ---------- modal ---------- */
  function modal({title, body, footer, size = '', onOpen}) {
    const back = document.createElement('div');
    back.className = 'modal-back';
    back.innerHTML = `<div class="modal ${size}" role="dialog" aria-modal="true"><div class="modal-h"><h3>${esc(title)}</h3><button class="close" data-close aria-label="Close">${ic('x')}</button></div><div class="modal-b">${body}</div>${footer !== false ? `<div class="modal-f">${footer || '<button class="btn" data-close>Cancel</button>'}</div>` : ''}</div>`;
    document.body.appendChild(back);
    const close = () => { back.remove(); document.removeEventListener('keydown', onKey); };
    const onKey = e => { if (e.key === 'Escape') close(); };
    document.addEventListener('keydown', onKey);
    back.addEventListener('mousedown', e => { if (e.target === back) close(); });
    $$('[data-close]', back).forEach(b => b.addEventListener('click', close));
    const m = {el: back, close, $: s => $(s, back), $$: s => $$(s, back)};
    if (onOpen) onOpen(m);
    const f = $('input:not([type=checkbox]):not([type=hidden]),textarea,select', back);
    if (f) setTimeout(() => f.focus(), 30);
    return m;
  }
  function confirmBox(title, text, okLabel = 'Confirm', danger = false) {
    return new Promise(resolve => {
      const m = modal({title, size: 'sm', body: `<p>${esc(text)}</p>`, footer: `<button class="btn" data-close>Cancel</button><button class="btn ${danger ? 'danger' : 'primary'}" data-ok>${esc(okLabel)}</button>`});
      m.$('[data-ok]').onclick = () => { m.close(); resolve(true); };
      $$('[data-close]', m.el).forEach(b => b.addEventListener('click', () => resolve(false)));
    });
  }

  /* ---------- popover menu ---------- */
  function popMenu(anchor, items) {
    closeMenus();
    const m = document.createElement('div');
    m.className = 'menu';
    m.dataset.pop = '1';
    m._born = performance.now();
    items.forEach(it => {
      if (it === '-') { m.appendChild(document.createElement('hr')); return; }
      const b = document.createElement('button');
      b.type = 'button';
      b.innerHTML = esc(it.label);
      if (it.danger) b.className = 'danger';
      if (it.disabled) { b.disabled = true; b.style.opacity = .45; }
      b.onclick = e => { e.stopPropagation(); closeMenus(); it.action && it.action(); };
      m.appendChild(b);
    });
    document.body.appendChild(m);
    const r = anchor.getBoundingClientRect();
    let left = r.right - m.offsetWidth; if (left < 8) left = r.left;
    let top = r.bottom + 6 + window.scrollY;
    if (r.bottom + m.offsetHeight + 10 > innerHeight) top = r.top - m.offsetHeight - 6 + window.scrollY;
    m.style.left = Math.max(8, left) + 'px'; m.style.top = top + 'px';
    return m;
  }
  function closeMenus() { $$('[data-pop]').forEach(m => m.remove()); }
  // Close menus on outside clicks — but never on the same click that opened them.
  document.addEventListener('click', e => {
    if (e.target.closest('[data-pop]')) return;
    $$('[data-pop]').forEach(m => { if (!m._born || e.timeStamp > m._born) m.remove(); });
  });

  /* ---------- save bar (unsaved changes) ---------- */
  function saveBar({label = 'Unsaved changes', onSave, onDiscard}) {
    let el = null;
    const api_ = {
      show() {
        if (el) return;
        el = document.createElement('div');
        el.className = 'save-bar';
        el.innerHTML = `<span>${esc(label)}</span><button class="sb-discard" type="button">Discard</button><button class="sb-save" type="button">Save</button>`;
        document.body.appendChild(el);
        el.querySelector('.sb-discard').onclick = async () => {
          if (await confirmBox('Discard all unsaved changes', 'If you discard changes, you’ll delete any edits you made since you last saved.', 'Discard changes', true)) onDiscard();
        };
        el.querySelector('.sb-save').onclick = async () => {
          const b = el.querySelector('.sb-save'); b.disabled = true;
          try { await onSave(); } finally { if (el) b.disabled = false; }
        };
      },
      hide() { if (el) { el.remove(); el = null; } },
      get visible() { return !!el; }
    };
    window.addEventListener('beforeunload', e => { if (el) { e.preventDefault(); e.returnValue = ''; } });
    return api_;
  }

  /* ---------- tags input ---------- */
  function tagsInput(container, initial, onChange) {
    let tags = (initial || []).filter(Boolean);
    const render = () => {
      container.innerHTML = `<input class="input" placeholder="Add tags, separated by commas"><div class="tags-wrap" style="margin-top:8px">${tags.map((t, i) => `<span class="tag-chip">${esc(t)}<button type="button" data-i="${i}">${ic('x')}</button></span>`).join('')}</div>`;
      const inp = container.querySelector('input');
      const commit = () => {
        const parts = inp.value.split(',').map(s => s.trim()).filter(Boolean);
        if (!parts.length) return;
        parts.forEach(p => { if (!tags.includes(p)) tags.push(p); });
        inp.value = ''; render(); onChange(tags); container.querySelector('input').focus();
      };
      inp.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); commit(); } });
      inp.addEventListener('blur', commit);
      container.querySelectorAll('[data-i]').forEach(b => b.onclick = () => { tags.splice(+b.dataset.i, 1); render(); onChange(tags); });
    };
    render();
    return {set(t) { tags = t.slice(); render(); }, get: () => tags.slice()};
  }

  /* ---------- product / variant picker ---------- */
  function productPicker({title = 'Select products', mode = 'variants', selected = [], locationId = null, onAdd}) {
    // mode: 'variants' (select variants) | 'products' (select whole products)
    const sel = new Set(selected.map(String));
    const isV = mode === 'variants';
    let data = [], filters = {active: false, stock: false};
    const m = modal({
      title, size: 'wide',
      body: `<div class="pk-tools"><label class="input-affix">${ic('search')}<input placeholder="Search products" data-q autocomplete="off"></label>
          <div class="select-wrap"><select class="input" data-by><option value="all">Search by All</option><option value="title">Product title</option><option value="sku">SKU</option><option value="vendor">Vendor</option><option value="type">Product type</option></select>${ic('updown')}</div></div>
        <div class="pk-chips"><button class="pk-chip" type="button" data-filter>Add filter ${ic('plus')}</button><span data-active-filters></span></div>
        <div class="pk-wrap" data-list><table class="pk"><tbody><tr class="empty-pk"><td>Loading…</td></tr></tbody></table></div>`,
      footer: `<span class="sel-count left" data-count></span><button class="btn" data-close>Cancel</button><button class="btn primary" data-add>Add</button>`
    });
    const count = () => {
      m.$('[data-count]').textContent = isV ? `${sel.size}/500 variants selected` : `${sel.size} product${sel.size === 1 ? '' : 's'} selected`;
    };
    const visible = () => data.filter(p => (!filters.active || p.status === 'active') && (!filters.stock || p.variants.some(v => v.available > 0)));
    const thumb = p => p.image ? `<span class="thumb-sm"><img src="${esc(url(p.image))}" alt=""></span>` : `<span class="thumb-sm empty">${ic('image')}</span>`;
    const avail = (p, v) => p.track ? `<span class="${v.available <= 0 ? 'red' : ''}">${v.available}</span>` : '<span class="muted">—</span>';
    const render = () => {
      const rows = visible();
      const head = `<thead><tr><th>Product</th>${isV ? '<th class="num c-av">Available</th><th class="num c-pr">Price</th>' : '<th class="num c-av">Status</th>'}</tr></thead>`;
      let h = '';
      if (!rows.length) h = `<tr class="empty-pk"><td colspan="3">No products found</td></tr>`;
      rows.forEach(p => {
        if (!isV) {
          h += `<tr><td><span class="pk-cell"><input type="checkbox" class="cb" data-pid="${p.id}">${thumb(p)}<span class="t">${esc(p.title)}</span></span></td><td class="num c-av">${esc(p.status.charAt(0).toUpperCase() + p.status.slice(1))}</td></tr>`;
          return;
        }
        const multi = p.variants.length > 1 || (p.variants[0] && p.variants[0].title !== 'Default Title');
        if (!multi && p.variants[0]) {
          const v = p.variants[0];
          h += `<tr><td><span class="pk-cell"><input type="checkbox" class="cb" data-vid="${v.id}">${thumb(p)}<span class="t">${esc(p.title)}</span></span></td><td class="num c-av">${avail(p, v)}</td><td class="num c-pr">${money(v.price, true)}</td></tr>`;
        } else {
          h += `<tr><td colspan="3"><span class="pk-cell"><input type="checkbox" class="cb" data-parent="${p.id}">${thumb(p)}<span class="t">${esc(p.title)}</span></span></td></tr>`;
          p.variants.forEach(v => h += `<tr class="var"><td><span class="pk-cell"><input type="checkbox" class="cb" data-vid="${v.id}"><span class="t">${esc(v.title)}</span></span></td><td class="num c-av">${avail(p, v)}</td><td class="num c-pr">${money(v.price, true)}</td></tr>`);
        }
      });
      m.$('[data-list]').innerHTML = `<table class="pk">${head}<tbody>${h}</tbody></table>`;
      sync();
    };
    // Update ticks in place so the list keeps its scroll position.
    const sync = () => {
      m.$$('[data-vid]').forEach(b => { b.checked = sel.has(b.dataset.vid); b.closest('tr').classList.toggle('on', b.checked); });
      m.$$('[data-pid]').forEach(b => { b.checked = sel.has(b.dataset.pid); b.closest('tr').classList.toggle('on', b.checked); });
      m.$$('[data-parent]').forEach(b => {
        const p = data.find(x => String(x.id) === b.dataset.parent);
        const all = p.variants.length > 0 && p.variants.every(v => sel.has(String(v.id))), some = p.variants.some(v => sel.has(String(v.id)));
        b.checked = all; b.classList.toggle('indeterminate', some && !all);
      });
      count();
    };
    const toggle = box => {
      const on = box.checked;
      if (box.dataset.vid) on ? sel.add(box.dataset.vid) : sel.delete(box.dataset.vid);
      if (box.dataset.pid) on ? sel.add(box.dataset.pid) : sel.delete(box.dataset.pid);
      if (box.dataset.parent) {
        const p = data.find(x => String(x.id) === box.dataset.parent);
        const all = p.variants.every(v => sel.has(String(v.id)));
        p.variants.forEach(v => all ? sel.delete(String(v.id)) : sel.add(String(v.id)));
      }
      sync();
    };
    m.$('[data-list]').addEventListener('change', e => { if (e.target.matches('.cb')) toggle(e.target); });
    // clicking anywhere on a row toggles its checkbox
    m.$('[data-list]').addEventListener('click', e => {
      if (e.target.matches('.cb')) return;
      const tr = e.target.closest('tbody tr'); if (!tr) return;
      const box = tr.querySelector('.cb'); if (!box) return;
      box.checked = !box.checked; toggle(box);
    });
    const drawChips = () => {
      m.$('[data-active-filters]').innerHTML =
        (filters.active ? `<button class="pk-chip on" type="button" data-rmf="active">Active products ${ic('x')}</button> ` : '') +
        (filters.stock ? `<button class="pk-chip on" type="button" data-rmf="stock">In stock ${ic('x')}</button>` : '');
      m.$$('[data-rmf]').forEach(b => b.onclick = () => { filters[b.dataset.rmf] = false; drawChips(); render(); });
    };
    let timer;
    const load = () => api(`products/search?q=${encodeURIComponent(m.$('[data-q]').value)}&by=${m.$('[data-by]').value}${locationId ? '&location=' + locationId : ''}`).then(r => { data = r.products; render(); });
    m.$('[data-q]').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 220); });
    m.$('[data-q]').addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });
    m.$('[data-by]').addEventListener('change', load);
    m.$('[data-filter]').onclick = e => { e.stopPropagation(); popMenu(e.currentTarget, [
      {label: 'Active products', action: () => { filters.active = true; drawChips(); render(); }},
      {label: 'In stock', action: () => { filters.stock = true; drawChips(); render(); }},
    ]); };
    m.$('[data-add]').onclick = () => {
      const out = [];
      if (!isV) data.forEach(p => { if (sel.has(String(p.id))) out.push(p); });
      else data.forEach(p => p.variants.forEach(v => { if (sel.has(String(v.id))) out.push({product: p, variant: v}); }));
      onAdd(out, [...sel]);
      m.close();
    };
    load();
    count();
    return m;
  }

  /* ---------- customer search dropdown ---------- */
  function customerSearch(input, onPick, {allowCreate = true} = {}) {
    const wrap = input.closest('.cs-wrap') || input.parentElement;
    wrap.style.position = 'relative';
    let box = null, timer;
    const close = () => { if (box) { box.remove(); box = null; } };
    const open = async () => {
      const r = await api('customers/search?q=' + encodeURIComponent(input.value));
      close();
      box = document.createElement('div');
      box.className = 'dropdown-list';
      box.innerHTML = (allowCreate ? `<div class="opt create" data-new>${ic('addc')}Create a new customer</div>` : '') +
        (r.customers.length ? r.customers.map(c => `<div class="opt" data-id="${c.id}"><span>${esc(c.name)}</span><small>${esc(c.email || c.phone || '')}</small></div>`).join('') : '<div class="opt"><small>No customers found</small></div>');
      wrap.appendChild(box);
      box.addEventListener('mousedown', e => e.preventDefault());
      box.querySelectorAll('[data-id]').forEach(o => o.onclick = () => { close(); input.value = ''; onPick(r.customers.find(c => String(c.id) === o.dataset.id)); });
      const n = box.querySelector('[data-new]');
      if (n) n.onclick = () => { close(); customerModal(c => onPick(c), input.value); };
    };
    input.addEventListener('focus', open);
    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(open, 200); });
    input.addEventListener('blur', () => setTimeout(close, 150));
  }
  function customerModal(onCreated, prefill = '') {
    const m = modal({
      title: 'Create a new customer', size: 'wide',
      body: `<div class="row2"><label class="field"><span>First name</span><input data-f="first_name" value="${esc(prefill.includes('@') ? '' : prefill)}"></label><label class="field"><span>Last name</span><input data-f="last_name"></label></div>
        <label class="field"><span>Email</span><input type="email" data-f="email" value="${esc(prefill.includes('@') ? prefill : '')}"></label>
        <label class="field"><span>Phone number</span><input data-f="phone"></label>
        <label class="check"><input type="checkbox" class="cb" data-f="accepts_marketing">Customer agreed to receive marketing emails.</label>
        <div class="label-sm" style="margin:6px 0 -6px">Shipping address</div>
        <label class="field"><span>Country/region</span><input data-f="country" value="Bangladesh"></label>
        <label class="field"><span>Company</span><input data-f="company"></label>
        <label class="field"><span>Address</span><input data-f="address1"></label>
        <label class="field"><span>Apartment, suite, etc.</span><input data-f="address2"></label>
        <div class="row2"><label class="field"><span>City</span><input data-f="city"></label><label class="field"><span>Postal code</span><input data-f="zip"></label></div>`,
      footer: `<button class="btn" data-close>Cancel</button><button class="btn primary" data-save>Save</button>`
    });
    m.$('[data-save]').onclick = async () => {
      const d = {};
      m.$$('[data-f]').forEach(i => d[i.dataset.f] = i.type === 'checkbox' ? (i.checked ? 1 : 0) : i.value.trim());
      const r = await api('customers/save', d);
      m.close(); toast('Customer created'); onCreated(r.customer);
    };
  }

  /* ---------- tables: select all + bulk bar + column chooser + row links ---------- */
  function initTables() {
    $$('table.grid[data-bulk]').forEach(tbl => {
      const all = $('thead .cb', tbl), bar = document.getElementById(tbl.dataset.bulk);
      const update = () => {
        const boxes = $$('tbody .cb', tbl), on = boxes.filter(b => b.checked);
        if (all) { all.checked = on.length && on.length === boxes.length; all.classList.toggle('indeterminate', on.length > 0 && on.length < boxes.length); }
        boxes.forEach(b => b.closest('tr').classList.toggle('selected', b.checked));
        if (bar) { bar.hidden = !on.length; const c = bar.querySelector('[data-selcount]'); if (c) c.textContent = on.length + ' selected'; }
      };
      if (all) all.addEventListener('change', () => { $$('tbody .cb', tbl).forEach(b => b.checked = all.checked); update(); });
      tbl.addEventListener('change', e => { if (e.target.matches('tbody .cb')) update(); });
      if (bar) $$('[data-bulk-action]', bar).forEach(b => b.onclick = async () => {
        const ids = $$('tbody .cb:checked', tbl).map(c => c.value);
        const act = b.dataset.bulkAction;
        if (b.dataset.confirm && !(await confirmBox(b.dataset.confirm, `This affects ${ids.length} item(s).`, b.textContent.trim(), b.classList.contains('danger')))) return;
        await api(tbl.dataset.endpoint, {action: act, ids});
        toast('Updated'); location.reload();
      });
    });
    $$('tr[data-href]').forEach(tr => {
      tr.classList.add('clickable');
      tr.addEventListener('click', e => { if (e.target.closest('input,button,a,label')) return; location.href = tr.dataset.href; });
    });
    $$('.col-chooser').forEach(btn => {
      const tbl = document.getElementById(btn.dataset.table);
      if (!tbl) return;
      const key = 'cols:' + location.pathname + ':' + btn.dataset.table;
      let hidden = [];
      try { hidden = JSON.parse(localStorage.getItem(key) || '[]'); } catch (e) {}
      const apply = () => $$('tr', tbl).forEach(tr => Array.from(tr.children).forEach((c, i) => c.style.display = hidden.includes(i) ? 'none' : ''));
      apply();
      btn.onclick = e => {
        e.stopPropagation(); closeMenus();
        const ths = $$('thead th', tbl);
        const m = document.createElement('div'); m.className = 'menu'; m.dataset.pop = 1;
        ths.forEach((th, i) => {
          const name = th.textContent.trim(); if (!name) return;
          const l = document.createElement('label'); l.className = 'mi check';
          l.innerHTML = `<input type="checkbox" class="cb" ${hidden.includes(i) ? '' : 'checked'}> ${esc(name)}`;
          l.querySelector('input').onchange = ev => { hidden = ev.target.checked ? hidden.filter(x => x !== i) : [...hidden, i]; try { localStorage.setItem(key, JSON.stringify(hidden)); } catch (er) {} apply(); };
          m.appendChild(l);
        });
        m.addEventListener('click', ev => ev.stopPropagation());
        document.body.appendChild(m);
        const r = btn.getBoundingClientRect();
        m.style.top = (r.bottom + 6 + scrollY) + 'px'; m.style.left = (r.right - m.offsetWidth) + 'px';
      };
    });
  }

  /* ---------- rich text editor ---------- */
  function rte(root, html, onChange) {
    root.classList.add('rte');
    root.innerHTML = `<div class="rte-bar">
      <button type="button" class="icon-btn" data-cmd="removeFormat" title="Clear formatting"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 4h10M3 8h7M3 12h4M11 10l3 3M14 10l-3 3"/></svg></button><span class="vsep"></span>
      <select data-block><option value="p">Paragraph</option><option value="h2">Heading 2</option><option value="h3">Heading 3</option><option value="h4">Heading 4</option><option value="blockquote">Blockquote</option></select><span class="vsep"></span>
      <button type="button" class="icon-btn" data-cmd="bold"><b>B</b></button><button type="button" class="icon-btn" data-cmd="italic"><i style="font-family:serif;font-size:15px">I</i></button><button type="button" class="icon-btn" data-cmd="underline"><u>U</u></button>
      <label class="icon-btn" title="Text color" style="position:relative"><b style="border-bottom:2px solid #1a1a1a">A</b><input type="color" data-color style="position:absolute;inset:0;opacity:0;cursor:pointer"></label><span class="vsep"></span>
      <button type="button" class="icon-btn" data-cmd="justifyLeft" title="Align left">≡</button><button type="button" class="icon-btn" data-cmd="justifyCenter" title="Center">☰</button><span class="vsep"></span>
      <button type="button" class="icon-btn" data-link title="Link"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 9a3 3 0 0 0 4.2 0l2-2A3 3 0 0 0 9 2.8l-.8.8M9 7a3 3 0 0 0-4.2 0l-2 2A3 3 0 0 0 7 13.2l.8-.8"/></svg></button>
      <button type="button" class="icon-btn" data-img title="Insert image">${ic('image')}</button>
      <button type="button" class="icon-btn" data-video title="Insert video"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="6"/><path d="M6.8 5.8 10.2 8l-3.4 2.2z"/></svg></button>
      <button type="button" class="icon-btn" data-table title="Insert table"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2.5" y="2.5" width="11" height="11" rx="2"/><path d="M2.5 6.5h11M2.5 10h11M7 2.5v11"/></svg></button><span class="vsep"></span>
      <button type="button" class="icon-btn" data-cmd="insertUnorderedList" title="Bulleted list">•≡</button><button type="button" class="icon-btn" data-cmd="insertOrderedList" title="Numbered list">1≡</button>
      <button type="button" class="icon-btn" data-cmd="outdent" title="Outdent">⇤</button><button type="button" class="icon-btn" data-cmd="indent" title="Indent">⇥</button><span class="vsep"></span>
      <button type="button" class="icon-btn" data-cmd="unlink" title="Remove link"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="6"/><path d="m4 4 8 8"/></svg></button>
      <button type="button" class="icon-btn" data-src title="Show HTML" style="margin-left:auto">&lt;/&gt;</button></div>
      <div class="rte-area" contenteditable="true" data-placeholder="Add a product description"></div><textarea class="rte-src" hidden></textarea>`;
    const area = root.querySelector('.rte-area'), src = root.querySelector('.rte-src');
    area.innerHTML = html || '';
    const changed = () => onChange(area.innerHTML);
    area.addEventListener('input', changed);
    src.addEventListener('input', () => { area.innerHTML = src.value; changed(); });
    root.querySelectorAll('[data-cmd]').forEach(b => b.onclick = () => { area.focus(); document.execCommand(b.dataset.cmd); changed(); });
    root.querySelector('[data-block]').onchange = e => { area.focus(); document.execCommand('formatBlock', false, e.target.value); changed(); };
    root.querySelector('[data-color]').oninput = e => { area.focus(); document.execCommand('foreColor', false, e.target.value); changed(); };
    root.querySelector('[data-link]').onclick = () => { const u = prompt('Link URL'); if (u) { area.focus(); document.execCommand('createLink', false, u); changed(); } };
    root.querySelector('[data-img]').onclick = () => { const u = prompt('Image URL'); if (u) { area.focus(); document.execCommand('insertImage', false, u); changed(); } };
    root.querySelector('[data-video]').onclick = () => { const u = prompt('Video URL'); if (u) { area.focus(); document.execCommand('insertHTML', false, `<p><a href="${esc(u)}">${esc(u)}</a></p>`); changed(); } };
    root.querySelector('[data-table]').onclick = () => { area.focus(); document.execCommand('insertHTML', false, '<table border="1" style="border-collapse:collapse;width:100%"><tr><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td></tr></table><p></p>'); changed(); };
    root.querySelector('[data-src]').onclick = () => { const showSrc = src.hidden; src.hidden = !showSrc; area.hidden = showSrc; if (showSrc) src.value = area.innerHTML; };
    return {get: () => area.innerHTML, set: v => { area.innerHTML = v || ''; }};
  }

  /* ---------- file upload helper ---------- */
  function pickFiles(multiple = true, accept = 'image/*') {
    return new Promise(resolve => {
      document.querySelectorAll('input[data-picker]').forEach(x => x.remove());
      const i = document.createElement('input');
      i.type = 'file'; i.accept = accept; i.multiple = multiple;
      i.dataset.picker = '1'; i.style.cssText = 'position:fixed;left:-9999px;top:0;opacity:0';
      document.body.appendChild(i);
      i.onchange = async () => {
        i.remove();
        const out = [];
        for (const f of i.files) {
          const fd = new FormData(); fd.append('file', f);
          try { const r = await api('files/upload', fd); out.push(r.file); } catch (e) {}
        }
        resolve(out);
      };
      i.click();
    });
  }
  function filesModal({title = 'Select image', multiple = false, onDone}) {
    let files = [], sel = new Set();
    const m = modal({
      title, size: 'xwide',
      body: `<div style="display:flex;gap:10px;align-items:center"><label class="input-affix" style="flex:0 1 640px">${ic('search')}<input placeholder="Search files" data-q></label><span style="flex:1"></span><button class="chip-btn" data-sort type="button">⇅ Sort</button></div>
        <div class="dz" style="padding:40px 20px;flex-direction:column;align-items:center"><div style="display:flex;gap:10px"><button class="chip-btn" type="button" data-up>${ic('plus')} Add files</button></div><span style="color:#303030">Drag and drop images</span></div>
        <div class="files-grid" data-grid></div>`,
      footer: `<button class="btn" data-close>Cancel</button><button class="btn primary" data-done disabled>Done</button>`
    });
    let asc = false;
    const render = () => {
      const q = m.$('[data-q]').value.toLowerCase();
      const list = files.filter(f => f.name.toLowerCase().includes(q)).sort((a, b) => asc ? a.id - b.id : b.id - a.id);
      m.$('[data-grid]').innerHTML = list.map(f => `<div class="file-tile ${sel.has(f.id) ? 'sel' : ''}" data-id="${f.id}"><div class="fi"><input type="checkbox" class="cb" ${sel.has(f.id) ? 'checked' : ''}><img src="${esc(url(f.path))}" loading="lazy"></div><div class="fn">${esc(f.name)}</div><div class="ft">${esc((f.name.split('.').pop() || '').toUpperCase())}</div></div>`).join('') || '<div class="muted">No files yet</div>';
      m.$('[data-done]').disabled = !sel.size;
    };
    m.$('[data-grid]').addEventListener('click', e => {
      const t = e.target.closest('.file-tile'); if (!t) return;
      const id = +t.dataset.id;
      if (!multiple) sel.clear();
      sel.has(id) ? sel.delete(id) : sel.add(id); render();
    });
    m.$('[data-q]').oninput = render;
    m.$('[data-sort]').onclick = () => { asc = !asc; render(); };
    m.$('[data-up]').onclick = async () => { const up = await pickFiles(multiple); files = up.concat(files); up.forEach(f => sel.add(f.id)); render(); };
    const dz = m.$('.dz');
    dz.addEventListener('dragover', e => e.preventDefault());
    dz.addEventListener('drop', async e => {
      e.preventDefault();
      for (const f of e.dataTransfer.files) { const fd = new FormData(); fd.append('file', f); const r = await api('files/upload', fd); files.unshift(r.file); sel.add(r.file.id); }
      render();
    });
    m.$('[data-done]').onclick = () => { onDone(files.filter(f => sel.has(f.id))); m.close(); };
    api('files/list').then(r => { files = r.files; render(); });
  }

  /* ---------- global search (⌘K) ---------- */
  function initSearch() {
    const ov = $('#searchOverlay'), inp = $('#globalSearch'), res = $('#globalResults');
    if (!ov) return;
    const open = () => { ov.hidden = false; inp.value = ''; res.innerHTML = ''; inp.focus(); };
    $('#openSearch').onclick = open;
    document.addEventListener('keydown', e => { if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); open(); } if (e.key === 'Escape') ov.hidden = true; });
    ov.addEventListener('mousedown', e => { if (e.target === ov) ov.hidden = true; });
    let t;
    inp.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(async () => {
        if (!inp.value.trim()) { res.innerHTML = ''; return; }
        const r = await api('search/all?q=' + encodeURIComponent(inp.value));
        res.innerHTML = r.groups.map(g => `<div class="grp">${esc(g.label)}</div>` + g.items.map(i => `<a href="${esc(url(i.href))}">${esc(i.title)}<span class="muted" style="margin-left:auto">${esc(i.sub || '')}</span></a>`).join('')).join('') || '<div class="muted" style="padding:16px">No results</div>';
      }, 200);
    });
  }

  /* ---------- shell ---------- */
  function initShell() {
    const app = $('#app'), tog = $('#sbToggle');
    const toggleSb = () => app.classList.toggle(innerWidth < 760 ? 'sb-open' : 'sb-collapsed');
    if (tog) tog.onclick = toggleSb;
    const reopen = $('#sbOpen');
    if (reopen) reopen.onclick = toggleSb;
    const sm = $('#storeMenuBtn'), menu = $('#storeMenu');
    if (sm) sm.addEventListener('click', e => { e.stopPropagation(); menu.hidden = !menu.hidden; });
    document.addEventListener('click', e => { if (menu && !e.target.closest('#storeMenu')) menu.hidden = true; });
    const main = $('#main'), tb = $('.topbar');
    if (main && tb) main.addEventListener('scroll', () => tb.classList.toggle('scrolled', main.scrollTop > 4));
    const ai = $('#aiInput');
    if (ai) ai.addEventListener('keydown', e => { if (e.key === 'Enter' && ai.value.trim()) { toast('Amarfy AI is coming soon'); ai.value = ''; } });
  }

  // Validation errors are already shown as a toast — don't also report them as uncaught errors.
  window.addEventListener('unhandledrejection', e => { if (e.reason && e.reason.shown) e.preventDefault(); });

  window.UI = {$, $$, esc, money, url, ic, api, toast, modal, confirmBox, popMenu, closeMenus, saveBar, tagsInput, productPicker, customerSearch, customerModal, rte, pickFiles, filesModal};
  document.addEventListener('DOMContentLoaded', () => { initShell(); initTables(); initSearch(); });
})();
