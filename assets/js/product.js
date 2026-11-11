/* Product editor */
document.addEventListener('DOMContentLoaded', () => {
  const {$, $$, esc, money, ic, api, toast, modal, confirmBox, popMenu, saveBar, tagsInput, rte, pickFiles, filesModal, url} = UI;
  const P = window.PRODUCT, root = $('#productApp');
  const SYM = window.AMARFY.sym;
  const firstLoc = P.locations[0] ? P.locations[0].id : 0;
  const shown = {compare: false, cost: false, unit: false, sku: false, barcode: false, origin: !!P.origin_country, hs: !!P.hs_code};
  const v0 = P.variants[0];
  shown.compare = v0.compare_at_price != null && v0.compare_at_price !== '';
  shown.cost = v0.cost != null && v0.cost !== '';
  shown.unit = v0.unit_price != null && v0.unit_price !== '';
  shown.sku = !!v0.sku; shown.barcode = !!v0.barcode;
  let seoOpen = false, editor = null;
  const payload = () => ({id: P.id, title: P.title, description_html: P.description_html, status: P.status, category: P.category, product_type: P.product_type, vendor: P.vendor,
    theme_template: P.theme_template, tags: P.tags, options: P.options, is_gift_card: P.is_gift_card, charge_tax: P.charge_tax, track_inventory: P.track_inventory, requires_shipping: P.requires_shipping,
    weight: P.weight, weight_unit: P.weight_unit, length: P.length, width: P.width, height: P.height, dim_unit: P.dim_unit, package: P.package, origin_country: P.origin_country, hs_code: P.hs_code,
    metafields: P.metafields, purchase_options: P.purchase_options, seo_title: P.seo_title, seo_description: P.seo_description, handle: P.handle, media: P.media, variants: P.variants, collections: P.collections});
  let snapshot = JSON.stringify(payload());
  const bar = saveBar({label: P.id ? 'Unsaved changes' : 'Unsaved product', onSave: save, onDiscard: () => location.reload()});
  const dirty = () => { JSON.stringify(payload()) !== snapshot ? bar.show() : bar.hide(); };
  async function save() {
    if (!P.title.trim()) { toast('Title can’t be blank', true); const t = $('[data-title]', root); if (t) t.focus(); throw new Error('title'); }
    const r = await api('products/save', payload());
    snapshot = JSON.stringify(payload()); bar.hide();
    if (!P.id) location.href = url('products/' + r.id); else { toast('Product saved'); setTimeout(() => location.reload(), 350); }
  }

  /* ---------------- variants from options ---------------- */
  function rebuildVariants() {
    const opts = P.options.filter(o => o.name && o.values.length);
    if (!opts.length) {
      const keep = P.variants.find(v => v.title === 'Default Title') || P.variants[0];
      P.variants = [Object.assign({}, keep, {title: 'Default Title', option1: null, option2: null, option3: null})];
      return;
    }
    let combos = [[]];
    opts.forEach(o => { const n = []; combos.forEach(c => o.values.forEach(v => n.push(c.concat(v)))); combos = n; });
    const old = P.variants;
    P.variants = combos.map(c => {
      const title = c.join(' / ');
      const ex = old.find(v => v.title === title);
      return ex || {id: 0, title, option1: c[0] || null, option2: c[1] || null, option3: c[2] || null, price: old[0] ? old[0].price : 0, compare_at_price: null, cost: null, unit_price: null, sku: '', barcode: '', sell_out_of_stock: 0, inventory: {}};
    });
  }
  function optionsModal() {
    let opts = JSON.parse(JSON.stringify(P.options));
    if (!opts.length) opts = [{name: 'Size', values: []}];
    const m = modal({title: 'Product options', size: 'wide', body: '<div data-opts></div><button class="chip-btn" type="button" data-addopt>' + ic('plus') + ' Add another option</button>',
      footer: '<button class="btn plain left" data-clear style="color:#c70a24">Remove all options</button><button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Done</button>'});
    const draw = () => {
      m.$('[data-opts]').innerHTML = opts.map((o, i) => `<div class="opt-box"><div class="row2" style="grid-template-columns:1fr auto"><label class="field"><span>Option name</span><input data-on="${i}" value="${esc(o.name)}" placeholder="Size, Color, Material"></label><button class="icon-btn" style="align-self:end" data-rmopt="${i}" type="button">${ic('trash')}</button></div>
        <label class="field" style="margin-top:10px"><span>Option values</span><input data-ov="${i}" placeholder="Type a value and press Enter"></label><div class="opt-vals">${o.values.map((v, j) => `<span class="tag-chip">${esc(v)}<button type="button" data-rmv="${i}:${j}">${ic('x')}</button></span>`).join('')}</div></div>`).join('');
      m.$$('[data-on]').forEach(inp => inp.oninput = () => opts[+inp.dataset.on].name = inp.value);
      m.$$('[data-ov]').forEach(inp => inp.onkeydown = e => { if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); const v = inp.value.trim(); const o = opts[+inp.dataset.ov]; if (v && !o.values.includes(v)) o.values.push(v); draw(); m.$(`[data-ov="${inp.dataset.ov}"]`).focus(); } });
      m.$$('[data-rmv]').forEach(b => b.onclick = () => { const [i, j] = b.dataset.rmv.split(':').map(Number); opts[i].values.splice(j, 1); draw(); });
      m.$$('[data-rmopt]').forEach(b => b.onclick = () => { opts.splice(+b.dataset.rmopt, 1); draw(); });
      m.$('[data-addopt]').disabled = opts.length >= 3;
    };
    draw();
    m.$('[data-addopt]').onclick = () => { if (opts.length < 3) { opts.push({name: '', values: []}); draw(); } };
    m.$('[data-clear]').onclick = () => { P.options = []; rebuildVariants(); m.close(); render(); };
    m.$('[data-ok]').onclick = () => {
      m.$$('[data-ov]').forEach(inp => { const v = inp.value.trim(); const o = opts[+inp.dataset.ov]; if (v && !o.values.includes(v)) o.values.push(v); });
      P.options = opts.filter(o => o.name.trim() && o.values.length).slice(0, 3); rebuildVariants(); m.close(); render();
    };
  }

  /* Decorative upload banner: original artwork — iridescent glass tubes, speckled terracotta
     block, blue glass vase and a mustard lounge chair, arranged around the upload prompt. */
  const HERO_ART = `<svg class="deco" viewBox="0 0 930 180" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
    <defs>
      <linearGradient id="hTubeA" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ffd166"/><stop offset=".3" stop-color="#ff9f68"/><stop offset=".55" stop-color="#7fb4ff"/><stop offset=".8" stop-color="#2f6fe0"/><stop offset="1" stop-color="#a5c8ff"/></linearGradient>
      <linearGradient id="hTubeB" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f4f4f6"/><stop offset=".5" stop-color="#c9ccd4"/><stop offset="1" stop-color="#eef0f4"/></linearGradient>
      <linearGradient id="hTubeC" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#9cc6ff"/><stop offset=".55" stop-color="#d9a7f0"/><stop offset="1" stop-color="#b24fc4"/></linearGradient>
      <linearGradient id="hShine" x1="0" x2="1"><stop offset="0" stop-color="#fff" stop-opacity="0"/><stop offset=".45" stop-color="#fff" stop-opacity=".85"/><stop offset=".6" stop-color="#fff" stop-opacity="0"/></linearGradient>
      <linearGradient id="hBrickTop" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f0876a"/><stop offset="1" stop-color="#d8563a"/></linearGradient>
      <linearGradient id="hBrickSide" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#c9452c"/><stop offset="1" stop-color="#a8361f"/></linearGradient>
      <pattern id="hSpeck" width="9" height="9" patternUnits="userSpaceOnUse"><circle cx="2" cy="3" r=".9" fill="#5a1d10" opacity=".55"/><circle cx="7" cy="7" r=".6" fill="#3a1208" opacity=".5"/></pattern>
      <linearGradient id="hVase" x1="0" x2="1"><stop offset="0" stop-color="#1d3fb8"/><stop offset=".35" stop-color="#4a7bf0"/><stop offset=".6" stop-color="#2a55d6"/><stop offset="1" stop-color="#16308f"/></linearGradient>
      <radialGradient id="hChair" cx=".35" cy=".3" r=".8"><stop offset="0" stop-color="#e3a64d"/><stop offset=".6" stop-color="#c9822a"/><stop offset="1" stop-color="#9c5f17"/></radialGradient>
      <filter id="hSoft"><feDropShadow dx="0" dy="6" stdDeviation="7" flood-opacity=".18"/></filter>
    </defs>
    <!-- glass tubes (left) -->
    <g filter="url(#hSoft)" stroke-linecap="round" fill="none">
      <path d="M40 186 C38 120 52 60 98 34 C130 18 160 28 170 60" stroke="url(#hTubeA)" stroke-width="30"/>
      <path d="M150 196 C146 140 150 90 176 54" stroke="url(#hTubeB)" stroke-width="30" opacity=".95"/>
      <path d="M198 160 C206 112 214 64 232 28" stroke="url(#hTubeC)" stroke-width="28" opacity=".95"/>
      <path d="M126 186 C126 150 128 118 138 90" stroke="url(#hTubeB)" stroke-width="12" opacity=".6"/>
      <path d="M52 170 C50 120 64 72 100 48" stroke="url(#hShine)" stroke-width="8" opacity=".9"/>
      <path d="M158 180 C156 140 160 100 178 70" stroke="#fff" stroke-width="5" opacity=".55"/>
    </g>
    <!-- speckled terracotta stepped block -->
    <g filter="url(#hSoft)" transform="translate(300 -8) rotate(-28)">
      <path d="M0 40 L70 10 L140 40 L70 70 Z" fill="url(#hBrickTop)"/><path d="M0 40 V78 L70 108 V70 Z" fill="url(#hBrickSide)"/><path d="M140 40 V78 L70 108 V70 Z" fill="#b8432a"/>
      <path d="M22 30 L70 10 L118 30 L70 50 Z" fill="#ee7a5d"/><path d="M22 30 V40 L70 60 V50 Z" fill="#c44a30"/><path d="M118 30 V40 L70 60 V50 Z" fill="#a83a22"/>
      <path d="M0 40 L70 10 L140 40 L70 70 Z M0 40 V78 L70 108 V70 Z M140 40 V78 L70 108 V70 Z" fill="url(#hSpeck)"/>
    </g>
    <!-- blue glass vase (top right) -->
    <g filter="url(#hSoft)" transform="translate(684 -16) rotate(14)">
      <path d="M8 0 H72 L80 14 V96 Q80 110 66 110 H14 Q0 110 0 96 V14 Z" fill="url(#hVase)" opacity=".92"/>
      <path d="M20 8 V100" stroke="#fff" stroke-opacity=".45" stroke-width="5" stroke-linecap="round"/>
      <path d="M40 4 V104" stroke="#0f2470" stroke-opacity=".35" stroke-width="3"/><path d="M60 6 V102" stroke="#0f2470" stroke-opacity=".3" stroke-width="3"/>
      <ellipse cx="40" cy="104" rx="34" ry="7" fill="#0d1f66" opacity=".35"/>
    </g>
    <!-- mustard lounge chair (bottom right) -->
    <g filter="url(#hSoft)" transform="translate(770 52)">
      <path d="M18 70 C10 30 30 4 70 2 C112 0 140 20 142 58 C144 88 140 112 130 128 L28 128 C18 112 22 92 18 70 Z" fill="url(#hChair)"/>
      <path d="M8 96 C6 74 22 66 40 74 L42 128 L14 128 C8 118 8 106 8 96 Z" fill="#b8741f"/>
      <path d="M150 92 C152 72 136 64 120 74 L118 128 L146 128 C152 118 150 104 150 92 Z" fill="#a9681a"/>
      <path d="M40 96 C60 86 100 86 120 96 L120 128 L40 128 Z" fill="#d3923a"/>
      <path d="M44 22 C66 10 100 12 120 28" stroke="#f2c27a" stroke-opacity=".6" stroke-width="6" fill="none" stroke-linecap="round"/>
    </g>
  </svg>`;

  /* ---------------- main column ---------------- */
  function renderMain() {
    const el = $('[data-main]', root), v = P.variants[0], multi = P.variants.length > 1 || v.title !== 'Default Title';
    let h = '';
    // media
    if (!P.media.length) {
      h += `<div class="media-hero" data-addmedia>${HERO_ART}<span class="mh-plus">+</span><span class="mh-text">Upload images, videos or 3D models</span></div>`;
    } else {
      h += `<div class="media-grid">${P.media.map((m, i) => `<div class="media-tile"><img src="${esc(url(m.path))}"><button class="rm" type="button" data-rmmedia="${i}">${ic('x')}</button></div>`).join('')}<div class="media-tile add" data-addmedia>+</div></div>`;
    }
    // title + description
    h += P.id ? `<label class="title-label">Product title</label><input class="title-input" data-title value="${esc(P.title)}" style="font-size:26px">`
              : `<input class="title-input" data-title value="${esc(P.title)}" placeholder="Add product title" style="border-color:${P.title ? 'transparent' : '#2c6ecb'}">`;
    h += `<div data-rte></div>`;
    // options strip + price/inventory/shipping card
    h += `<div style="margin-top:40px"><div class="opt-strip" data-options>${multi ? `<span style="flex:1">${P.options.map(o => `<b>${esc(o.name)}</b> <span class="muted">${o.values.map(esc).join(', ')}</span>`).join(' &nbsp;·&nbsp; ')}</span>${ic('pencil')}` : `${ic('plus')} Add options like size and color`}</div><div class="card">`;
    if (multi) {
      h += `<div class="card-sec"><div class="label-sm">Variants</div><div class="table-wrap"><table class="var-table"><thead><tr><th>Variant</th><th style="width:130px">Price</th><th style="width:130px">Compare-at</th>${P.track_inventory ? '<th style="width:110px">Available</th>' : ''}<th style="width:140px">SKU</th></tr></thead><tbody>
        ${P.variants.map((x, i) => `<tr><td>${esc(x.title)}</td><td><input type="number" min="0" step="0.01" data-vf="price:${i}" value="${x.price}"></td><td><input type="number" min="0" step="0.01" data-vf="compare_at_price:${i}" value="${x.compare_at_price ?? ''}"></td>${P.track_inventory ? `<td><input type="number" data-vinv="${i}" value="${(x.inventory || {})[firstLoc] ?? 0}"></td>` : ''}<td><input data-vf="sku:${i}" value="${esc(x.sku || '')}"></td></tr>`).join('')}</tbody></table></div>
        <div class="hint">Available quantities are for ${esc(P.locations[0] ? P.locations[0].name : '')}. Total inventory across all locations: ${P.variants.reduce((s, x) => s + Object.values(x.inventory || {}).reduce((a, b) => a + (+b || 0), 0), 0)}</div></div>`;
    } else {
      h += `<div class="card-sec"><div class="label-sm">Price</div><div class="input-affix" style="max-width:440px;height:38px;border-radius:10px"><span class="pre">${esc(SYM)}</span><input type="number" min="0" step="0.01" data-vf="price:0" value="${Number(v.price).toFixed(2)}"></div>
        ${shown.compare || shown.cost || shown.unit ? `<div class="row3" style="margin-top:12px">${shown.compare ? `<label class="field"><span>Compare-at price</span><div class="input-affix"><span class="pre">${esc(SYM)}</span><input type="number" min="0" step="0.01" data-vf="compare_at_price:0" value="${v.compare_at_price ?? ''}"></div></label>` : ''}${shown.cost ? `<label class="field"><span>Cost per item</span><div class="input-affix"><span class="pre">${esc(SYM)}</span><input type="number" min="0" step="0.01" data-vf="cost:0" value="${v.cost ?? ''}"></div>${v.cost && v.price ? `<small>Profit ${money(v.price - v.cost)} · Margin ${Math.round((v.price - v.cost) / v.price * 100)}%</small>` : ''}</label>` : ''}${shown.unit ? `<label class="field"><span>Unit price</span><div class="input-affix"><span class="pre">${esc(SYM)}</span><input type="number" min="0" step="0.01" data-vf="unit_price:0" value="${v.unit_price ?? ''}"></div></label>` : ''}</div>` : ''}
        <div class="addons">${shown.compare ? '' : `<button class="addon" type="button" data-show="compare">${ic('plus')} Compare-at</button>`}${shown.cost ? '' : `<button class="addon" type="button" data-show="cost">${ic('plus')} Cost per item</button>`}${shown.unit ? '' : `<button class="addon" type="button" data-show="unit">${ic('plus')} Unit price</button>`}<label class="addon on">Charge tax <input type="checkbox" class="toggle" data-flag="charge_tax" ${P.charge_tax ? 'checked' : ''}></label></div></div>`;
      h += `<div class="card-sec"><div style="display:flex;align-items:center;gap:10px"><span class="label-sm" style="margin:0">Inventory</span>${ic('pencil')}<input type="checkbox" class="toggle" style="margin-left:auto" data-flag="track_inventory" ${P.track_inventory ? 'checked' : ''}></div>
        ${P.track_inventory ? `<table class="inv-table"><thead><tr><th>Location</th><th>Quantity</th></tr></thead><tbody>${P.locations.map(l => `<tr><td>${esc(l.name)}</td><td><input type="number" data-inv="${l.id}" value="${(v.inventory || {})[l.id] ?? 0}"></td></tr>`).join('')}</tbody></table>` : '<p class="hint">Inventory is not tracked for this product.</p>'}
        ${shown.sku || shown.barcode ? `<div class="row2" style="margin-top:12px">${shown.sku ? `<label class="field"><span>SKU (Stock Keeping Unit)</span><input data-vf="sku:0" value="${esc(v.sku || '')}"></label>` : ''}${shown.barcode ? `<label class="field"><span>Barcode (ISBN, UPC, GTIN, etc.)</span><input data-vf="barcode:0" value="${esc(v.barcode || '')}"></label>` : ''}</div>` : ''}
        <div class="addons">${shown.sku ? '' : `<button class="addon" type="button" data-show="sku">${ic('plus')} SKU</button>`}${shown.barcode ? '' : `<button class="addon" type="button" data-show="barcode">${ic('plus')} Barcodes</button>`}<label class="addon on">Sell when out of stock <input type="checkbox" class="toggle" data-vflag="sell_out_of_stock" ${v.sell_out_of_stock ? 'checked' : ''}></label></div></div>`;
    }
    h += `<div class="card-sec"><div style="display:flex;align-items:center"><span class="label-sm" style="margin:0">Shipping</span><input type="checkbox" class="toggle" style="margin-left:auto" data-flag="requires_shipping" ${P.requires_shipping ? 'checked' : ''}></div>
      ${P.requires_shipping ? `<div class="row2" style="margin-top:14px"><label class="field"><span>Product weight</span><div class="input-affix" style="height:40px"><input type="number" min="0" step="0.01" data-pf="weight" value="${P.weight}"><span class="sep"></span><select data-pf="weight_unit">${['kg', 'g', 'lb', 'oz'].map(u => `<option ${u === P.weight_unit ? 'selected' : ''}>${u}</option>`).join('')}</select></div></label>
        <label class="field"><span>Packed dimensions</span><div class="input-affix" style="height:40px"><span class="pre">L</span><input type="number" min="0" step="0.1" data-pf="length" value="${P.length}"><span class="pre">×  W</span><input type="number" min="0" step="0.1" data-pf="width" value="${P.width}"><span class="pre">×  H</span><input type="number" min="0" step="0.1" data-pf="height" value="${P.height}"><span class="sep"></span><select data-pf="dim_unit">${['cm', 'in', 'm'].map(u => `<option ${u === P.dim_unit ? 'selected' : ''}>${u}</option>`).join('')}</select></div></label></div>
        <label class="field" style="margin-top:14px"><span>Package for one product</span><div class="select-wrap"><select class="input" data-pf="package" style="height:40px"><option value="">Store default · Sample box - 22 × 14 × 4 cm, 0 kg</option>${['Small box - 20 × 15 × 5 cm', 'Medium box - 30 × 25 × 10 cm', 'Large box - 45 × 35 × 20 cm', 'Envelope - 32 × 24 cm', 'Soft package / satchel'].map(x => `<option ${x === P.package ? 'selected' : ''}>${x}</option>`).join('')}</select>${ic('updown')}</div></label>
        ${shown.origin || shown.hs ? `<div class="row2" style="margin-top:12px">${shown.origin ? `<label class="field"><span>Country/Region of origin</span><input data-pf="origin_country" value="${esc(P.origin_country)}"></label>` : ''}${shown.hs ? `<label class="field"><span>HS (Harmonized System) code</span><input data-pf="hs_code" value="${esc(P.hs_code)}"></label>` : ''}</div>` : ''}
        <div class="addons">${shown.origin ? '' : `<button class="addon" type="button" data-show="origin">${ic('plus')} Origin</button>`}${shown.hs ? '' : `<button class="addon" type="button" data-show="hs">${ic('plus')} HS Code</button>`}</div>` : '<p class="hint" style="margin-top:8px">This is a digital product or service — no shipping required.</p>'}</div></div></div>`;
    // purchase options
    h += `<div class="sec-head" style="margin-top:44px"><h2>Purchase options</h2></div>
      ${P.purchase_options.length ? `<div class="card card-pad">${P.purchase_options.map((o, i) => `<div class="mf-row"><span class="k"><b>${esc(o.name)}</b></span><span style="flex:1">${esc(o.frequency)} · ${o.discount ? esc(o.discount) + '% off' : 'No discount'}</span><button class="icon-btn" type="button" data-rmpo="${i}">${ic('x')}</button></div>`).join('')}<div style="margin-top:10px"><button class="chip-btn" type="button" data-addpo>${ic('plus')} Purchase option</button></div></div>`
        : `<div class="card dashed"><div class="dz" style="border:0;padding:18px"><button class="chip-btn" type="button" data-addpo>${ic('plus')} Purchase option</button></div></div>`}`;
    // metafields
    h += `<div class="sec-head" style="margin-top:44px"><h2>Product metafields</h2><div class="acts"><span class="icon-btn">${ic('chev-up')}</span></div></div>
      <div class="card card-pad" style="padding-top:4px;padding-bottom:4px">${P.metafields.length ? P.metafields.map((m, i) => `<div class="mf-row"><span class="k">${esc(m.key)}</span><input data-mf="${i}" value="${esc(m.value)}" placeholder="Add value"><button class="icon-btn" type="button" data-rmmf="${i}">${ic('x')}</button></div>`).join('') : '<div class="mf-row muted">No metafields pinned</div>'}</div>
      <div class="addons" style="margin-top:8px"><button class="addon" type="button" data-addmf="Disclosures">${ic('plus')} Disclosures</button><button class="addon" type="button" data-addmf="">${ic('plus')} Add</button></div>`;
    // SEO
    const st = P.seo_title || P.title || 'Product title', sd = P.seo_description || (P.description_html || '').replace(/<[^>]+>/g, ' ').trim().slice(0, 160);
    h += `<div class="sec-head" style="margin-top:44px"><h2>Search engine listing</h2><div class="acts"><button class="icon-btn" type="button" data-seo>${ic('pencil')}</button></div></div>
      ${P.title || P.seo_title ? `<div class="seo-preview"><div><div class="t">${esc(st)}</div><div style="margin-bottom:4px">${esc(P.store_name)}</div><div class="u">${esc(P.store_url)}/products/${esc(P.handle || slug(P.title))}</div>${sd ? `<div class="d">${esc(sd)}</div>` : ''}</div>${P.media[0] ? `<img src="${esc(url(P.media[0].path))}">` : ''}</div>` : '<div class="seo-preview">Add a title and description to see how this product might appear in a search engine listing</div>'}
      ${seoOpen ? `<div class="card card-pad" style="margin-top:12px;display:flex;flex-direction:column;gap:12px"><label class="field"><span>Page title</span><input data-pf="seo_title" maxlength="70" value="${esc(P.seo_title)}" placeholder="${esc(P.title)}"><small>${(P.seo_title || '').length} of 70 characters used</small></label><label class="field"><span>Meta description</span><textarea data-pf="seo_description" maxlength="320">${esc(P.seo_description)}</textarea><small>${(P.seo_description || '').length} of 160 characters used</small></label><label class="field"><span>URL handle</span><div class="input-affix"><span class="pre">${esc(P.store_url)}/products/</span><input data-pf="handle" value="${esc(P.handle || slug(P.title))}"></div></label></div>` : ''}`;
    el.innerHTML = h;
    bindMain(el);
  }
  const slug = s => String(s || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

  function bindMain(el) {
    $$('[data-addmedia]', el).forEach(b => b.onclick = () => chooseMedia());
    const hero = $('.media-hero', el);
    if (hero) { hero.addEventListener('dragover', e => e.preventDefault()); hero.addEventListener('drop', async e => { e.preventDefault(); for (const f of e.dataTransfer.files) { const fd = new FormData(); fd.append('file', f); const r = await api('files/upload', fd); P.media.push({id: r.file.id, path: r.file.path}); } render(); }); }
    $$('[data-rmmedia]', el).forEach(b => b.onclick = e => { e.stopPropagation(); P.media.splice(+b.dataset.rmmedia, 1); render(); });
    const t = $('[data-title]', el); t.oninput = () => { P.title = t.value; t.style.borderColor = ''; $$('.crumb.cur span').forEach(s => s.textContent = t.value || 'Add product'); dirty(); };
    editor = rte($('[data-rte]', el), P.description_html, html => { P.description_html = html; dirty(); });
    $('[data-options]', el).onclick = optionsModal;
    $$('[data-vf]', el).forEach(inp => inp.oninput = () => { const [k, i] = inp.dataset.vf.split(':'); P.variants[+i][k] = ['sku', 'barcode'].includes(k) ? inp.value : (inp.value === '' ? null : parseFloat(inp.value)); dirty(); });
    $$('[data-vinv]', el).forEach(inp => inp.oninput = () => { const x = P.variants[+inp.dataset.vinv]; x.inventory = Object.assign({}, x.inventory, {[firstLoc]: parseInt(inp.value) || 0}); dirty(); });
    $$('[data-inv]', el).forEach(inp => inp.oninput = () => { const x = P.variants[0]; x.inventory = Object.assign({}, x.inventory, {[inp.dataset.inv]: parseInt(inp.value) || 0}); dirty(); });
    $$('[data-show]', el).forEach(b => b.onclick = () => { shown[b.dataset.show] = true; renderMain(); });
    $$('[data-flag]', el).forEach(c => c.onchange = () => { P[c.dataset.flag] = c.checked ? 1 : 0; renderMain(); dirty(); });
    $$('[data-vflag]', el).forEach(c => c.onchange = () => { P.variants.forEach(x => x[c.dataset.vflag] = c.checked ? 1 : 0); dirty(); });
    $$('[data-pf]', el).forEach(inp => { inp.oninput = inp.onchange = () => { P[inp.dataset.pf] = inp.type === 'number' ? (parseFloat(inp.value) || 0) : inp.value; dirty(); }; });
    $$('[data-addpo]', el).forEach(b => b.onclick = () => {
      const m = modal({title: 'Add purchase option', body: `<label class="field"><span>Name</span><input data-n placeholder="Subscribe & save"></label><div class="row2"><label class="field"><span>Delivery frequency</span><div class="select-wrap"><select class="input" data-fq><option>Every week</option><option>Every 2 weeks</option><option selected>Every month</option><option>Every 3 months</option></select>${ic('updown')}</div></label><label class="field"><span>Discount (%)</span><input type="number" min="0" max="100" data-d value="10"></label></div>`, footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Add</button>'});
      m.$('[data-ok]').onclick = () => { const n = m.$('[data-n]').value.trim(); if (!n) { toast('Enter a name', true); return; } P.purchase_options.push({name: n, frequency: m.$('[data-fq]').value, discount: m.$('[data-d]').value}); m.close(); render(); };
    });
    $$('[data-rmpo]', el).forEach(b => b.onclick = () => { P.purchase_options.splice(+b.dataset.rmpo, 1); render(); });
    $$('[data-mf]', el).forEach(inp => inp.oninput = () => { P.metafields[+inp.dataset.mf].value = inp.value; dirty(); });
    $$('[data-rmmf]', el).forEach(b => b.onclick = () => { P.metafields.splice(+b.dataset.rmmf, 1); render(); });
    $$('[data-addmf]', el).forEach(b => b.onclick = () => {
      const m = modal({title: 'Add metafield', size: 'sm', body: `<label class="field"><span>Name</span><input data-k value="${esc(b.dataset.addmf)}" placeholder="Material, Care instructions"></label><label class="field"><span>Value</span><input data-v></label>`, footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Add</button>'});
      m.$('[data-ok]').onclick = () => { const k = m.$('[data-k]').value.trim(); if (!k) { toast('Enter a name', true); return; } P.metafields.push({key: k, value: m.$('[data-v]').value}); m.close(); render(); };
    });
    $('[data-seo]', el).onclick = () => { seoOpen = !seoOpen; renderMain(); };
  }
  function chooseMedia() {
    const m = UI.popMenu($('[data-addmedia]', root), [
      {label: 'Upload new', action: async () => { const files = await pickFiles(true, 'image/*,video/mp4,video/webm'); files.forEach(f => P.media.push({id: f.id, path: f.path})); render(); }},
      {label: 'Select existing', action: () => filesModal({title: 'Select media', multiple: true, onDone: files => { files.forEach(f => { if (!P.media.find(x => x.path === f.path)) P.media.push({id: f.id, path: f.path}); }); render(); }})},
    ]);
    return m;
  }

  /* ---------------- side ---------------- */
  function renderSide() {
    const el = $('[data-side]', root);
    const sel = (k, opts) => `<span class="sel"><select data-sf="${k}">${opts.map(o => `<option value="${esc(o)}" ${o === P[k] ? 'selected' : ''}>${esc(o || 'None')}</option>`).join('')}</select>${ic('updown')}</span>`;
    const cats = ['', 'Uncategorized', 'Apparel & Accessories', 'Electronics', 'Health & Beauty', 'Home & Garden', 'Food & Beverages', 'Sporting Goods', 'Toys & Games', 'Books', 'Gift cards'];
    if (P.category && !cats.includes(P.category)) cats.push(P.category);
    el.innerHTML = `<div class="side-sec">
        <div class="status-line"><span class="sdot ${P.status}"></span><select data-sf="status"><option value="active" ${P.status === 'active' ? 'selected' : ''}>Active</option><option value="draft" ${P.status === 'draft' ? 'selected' : ''}>Draft</option><option value="archived" ${P.status === 'archived' ? 'selected' : ''}>Archived</option></select></div>
        <div class="status-line">${'<svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="5" cy="5" r="2"/><circle cx="11" cy="11" r="2"/><circle cx="4.5" cy="11.5" r="1.5"/><path d="M6.5 6.5l3 3M5 7v3"/></svg>'}${P.status === 'active' ? 'Online Store' : 'All channels'}</div>
        <div class="status-line">${ic('image')}No catalogs</div></div>
      <div class="side-sec side-props">
        <div class="kv"><span>Category</span>${sel('category', cats)}</div>
        <div class="kv"><span>Type</span><input class="kv-in" data-sf="product_type" list="dlTypes" value="${esc(P.product_type)}" placeholder="None"></div>
        <div class="kv"><span>Vendor</span><input class="kv-in" data-sf="vendor" list="dlVendors" value="${esc(P.vendor)}" placeholder="None"></div>
        <div class="kv"><span>Theme template</span>${sel('theme_template', ['Default product', 'Product - alternate', 'Gift card'])}</div></div>
      <div class="side-sec"><div class="side-head" style="font-weight:500;color:#303030">Collections ${P.collections.length ? `<button class="icon-btn" data-addcoll type="button">${ic('addc')}</button>` : ''}</div>
        <div class="tags-wrap">${P.collections.map(cid => { const c = P.all_collections.find(x => +x.id === cid); return c ? `<span class="tag-chip">${esc(c.title)}<button type="button" data-rmcoll="${cid}">${ic('x')}</button></span>` : ''; }).join('')}</div>
        ${P.collections.length ? '' : `<button class="chip-btn" type="button" data-addcoll style="height:26px;font-weight:500">${ic('addc')} Add collections</button>`}</div>
      <div class="side-sec"><div class="side-head" style="font-weight:500;color:#303030">Tags</div><div data-tags></div></div>`;
    $$('[data-sf]', el).forEach(i => { i.oninput = i.onchange = () => { P[i.dataset.sf] = i.value; if (i.dataset.sf === 'status') renderSide(); dirty(); }; });
    $$('[data-addcoll]', el).forEach(b => b.onclick = e => {
      e.stopPropagation();
      const manual = P.all_collections.filter(c => c.type === 'manual' && !P.collections.includes(+c.id));
      popMenu(b, manual.length ? manual.map(c => ({label: c.title, action: () => { P.collections.push(+c.id); renderSide(); dirty(); }})) : [{label: 'No manual collections — create one first', action: () => location.href = url('collections/new')}]);
    });
    $$('[data-rmcoll]', el).forEach(b => b.onclick = () => { P.collections = P.collections.filter(x => x !== +b.dataset.rmcoll); renderSide(); dirty(); });
    tagsInput($('[data-tags]', el), P.tags, t => { P.tags = t; dirty(); });
  }

  function render() { renderMain(); renderSide(); dirty(); }
  render();
  document.body.insertAdjacentHTML('beforeend', '<datalist id="dlTypes"></datalist><datalist id="dlVendors"></datalist>');
  fetchMeta();
  async function fetchMeta() {
    try {
      const r = await fetch(url('export/meta'), {headers: {'Accept': 'application/json'}}).then(x => x.json());
      $('#dlTypes').innerHTML = r.types.map(t => `<option value="${esc(t)}">`).join('');
      $('#dlVendors').innerHTML = r.vendors.map(t => `<option value="${esc(t)}">`).join('');
    } catch (e) {}
  }

  /* ---------------- top bar ---------------- */
  const more = $('#pMore');
  if (more) more.onclick = e => { e.stopPropagation(); popMenu(e.currentTarget, [
    {label: 'Duplicate', action: async () => { const r = await api('products/duplicate', {id: P.id}); location.href = url('products/' + r.id); }},
    {label: P.status === 'archived' ? 'Unarchive product' : 'Archive product', action: async () => { P.status = P.status === 'archived' ? 'draft' : 'archived'; await save(); }},
    '-',
    {label: 'Delete product', danger: true, action: async () => { if (await confirmBox(`Delete ${P.title}?`, 'Are you sure you want to delete this product? This can’t be undone.', 'Delete product', true)) { await api('products/delete', {id: P.id}); bar.hide(); location.href = url('products'); } }},
  ]); };
  const share = $('#pShare'); if (share) share.onclick = () => { navigator.clipboard && navigator.clipboard.writeText(`${P.store_url}/products/${P.handle || slug(P.title)}`); toast('Product link copied'); };
  const prev = $('#pPreview'); if (prev) prev.onclick = () => {
    const v = P.variants[0];
    modal({title: 'Preview', size: 'wide', footer: false, body: `<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;padding-bottom:10px">${P.media[0] ? `<img src="${esc(url(P.media[0].path))}" style="width:100%;border-radius:12px;background:#f7f7f7">` : `<div class="thumb-sm empty" style="width:100%;height:280px">${ic('image')}</div>`}<div><h2 style="font-size:22px;font-weight:600">${esc(P.title)}</h2><div style="font-size:18px;margin:10px 0">${money(v.price, true)} ${v.compare_at_price ? `<s class="muted">${money(v.compare_at_price)}</s>` : ''}</div>${P.options.map(o => `<div class="label-sm" style="margin-top:12px">${esc(o.name)}</div><div class="tags-wrap">${o.values.map(x => `<span class="tag-chip">${esc(x)}</span>`).join('')}</div>`).join('')}<button class="btn primary block lg" style="margin-top:18px">Add to cart</button><div style="margin-top:16px;line-height:1.6">${P.description_html}</div></div></div>`});
  };
  const va = $('#pViewAs'); if (va) va.onclick = e => { e.stopPropagation(); popMenu(e.currentTarget, [{label: 'Customer (Online Store)', action: () => prev && prev.click()}, {label: 'Copy product URL', action: () => share && share.click()}]); };
});
