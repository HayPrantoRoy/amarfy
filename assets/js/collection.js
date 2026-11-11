/* Collection editor */
document.addEventListener('DOMContentLoaded', () => {
  const {$, $$, esc, money, ic, api, toast, modal, confirmBox, popMenu, saveBar, productPicker, filesModal, pickFiles, url} = UI;
  const C = window.COLLECTION, root = $('#collApp');
  const FIELDS = {title: 'Title', type: 'Product type', vendor: 'Vendor', tag: 'Tag', category: 'Category', price: 'Price', compare_at: 'Compare-at price', stock: 'Inventory stock', weight: 'Weight'};
  const RELS = {equals: 'is equal to', not_equals: 'is not equal to', gt: 'is greater than', lt: 'is less than', starts: 'starts with', ends: 'ends with', contains: 'contains', not_contains: 'does not contain'};
  let items = [], seoOpen = false, titleError = false;
  const payload = () => ({id: C.id, title: C.title, description_html: C.description_html, image_path: C.image_path, type: C.type, match_all: C.match_all, conditions: C.conditions, excluded: C.excluded,
    products: C.products, theme_template: C.theme_template, channels: C.channels, seo_title: C.seo_title, seo_description: C.seo_description, handle: C.handle});
  let snapshot = JSON.stringify(payload());
  const bar = saveBar({label: C.id ? 'Unsaved changes' : 'Unsaved collection', onSave: save, onDiscard: () => location.reload()});
  const dirty = () => JSON.stringify(payload()) !== snapshot ? bar.show() : bar.hide();
  async function save() {
    if (!C.title.trim()) { titleError = true; renderMain(); toast('Add a title', true); throw new Error('title'); }
    const r = await api('collections/save', payload());
    snapshot = JSON.stringify(payload()); bar.hide();
    if (!C.id) location.href = url('collections/' + r.id); else toast('Collection saved');
  }
  async function refreshItems() {
    const r = await api('collections/preview', {type: C.type, products: C.products, conditions: C.conditions.filter(c => String(c.value).trim() !== ''), match_all: C.match_all, excluded: C.excluded});
    items = r.items; renderItems();
  }

  function renderMain() {
    const el = $('[data-main]', root);
    const slug = s => String(s || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    el.innerHTML = `<div class="card"><div class="coll-head">
        <div class="coll-img" data-img>${C.image_path ? `<img src="${esc(url(C.image_path))}">` : `<span style="font-size:20px">${ic('upload')}</span>`}</div>
        <div style="flex:1;display:flex;flex-direction:column;min-height:180px"><input class="coll-title" data-title placeholder="Add title" value="${esc(C.title)}">
          ${titleError && !C.title ? `<div style="color:#8e1f0b;font-size:12.5px;margin-top:6px;display:flex;gap:6px;align-items:center">${ic('info')}Add a title</div>` : ''}
          <div class="coll-desc" contenteditable="true" data-desc>${C.description_html}</div>
          <div style="margin-top:auto;align-self:flex-end;position:relative"><button class="chip-btn ghost" type="button" data-channels>${ic('channels')} ${C.channels} channel${C.channels === 1 ? '' : 's'} ${ic('updown')}</button></div></div></div></div>
      <div class="sec-head" style="margin-top:36px"><h2>Collection items</h2><span class="count-chip" data-count>0</span></div>
      <div class="card" data-items></div>
      <div class="sec-head" style="margin-top:36px"><h2>Theme template</h2></div>
      <div class="card card-pad"><div class="select-wrap"><select class="input" data-theme style="height:40px">${['Default collection', 'Collection - grid', 'Collection - list'].map(t => `<option ${t === C.theme_template ? 'selected' : ''}>${t}</option>`).join('')}</select>${ic('updown')}</div></div>
      <div class="sec-head" style="margin-top:36px"><h2>Search engine listing</h2><div class="acts"><button class="chip-btn" type="button" data-seo>Edit</button></div></div>
      <div class="card card-pad"><div>${esc(C.seo_title || C.title || C.store_name)}</div><div class="muted" style="margin-top:6px">${esc(C.store_url)} › collections › ${esc(C.handle || slug(C.title))}</div>${C.seo_description ? `<div class="muted" style="margin-top:6px">${esc(C.seo_description)}</div>` : ''}
        ${seoOpen ? `<div style="display:flex;flex-direction:column;gap:12px;margin-top:16px"><label class="field"><span>Page title</span><input data-cf="seo_title" value="${esc(C.seo_title)}" maxlength="70"></label><label class="field"><span>Meta description</span><textarea data-cf="seo_description" maxlength="320">${esc(C.seo_description)}</textarea></label><label class="field"><span>URL handle</span><input data-cf="handle" value="${esc(C.handle || slug(C.title))}"></label></div>` : ''}</div>`;
    $('[data-img]', el).onclick = e => popMenu(e.currentTarget, [
      {label: 'Select existing', action: () => filesModal({onDone: f => { if (f[0]) { C.image_path = f[0].path; renderMain(); dirty(); } }})},
      {label: 'Upload new', action: async () => { const f = await pickFiles(false); if (f[0]) { C.image_path = f[0].path; renderMain(); dirty(); } }},
      ...(C.image_path ? [{label: 'Remove image', danger: true, action: () => { C.image_path = ''; renderMain(); dirty(); }}] : [])]);
    const t = $('[data-title]', el); t.oninput = () => { C.title = t.value; titleError = false; dirty(); };
    const d = $('[data-desc]', el); d.oninput = () => { C.description_html = d.innerHTML; dirty(); };
    $('[data-channels]', el).onclick = e => { e.stopPropagation(); popMenu(e.currentTarget, [0, 1, 2, 3].map(n => ({label: `${n} channel${n === 1 ? '' : 's'}${n === 3 ? ' (Online Store, Agentic, Point of Sale)' : ''}`, action: () => { C.channels = n; renderMain(); dirty(); }}))); };
    $('[data-theme]', el).onchange = e => { C.theme_template = e.target.value; dirty(); };
    $('[data-seo]', el).onclick = () => { seoOpen = !seoOpen; renderMain(); };
    $$('[data-cf]', el).forEach(i => i.oninput = () => { C[i.dataset.cf] = i.value; dirty(); });
    renderItems();
  }
  function renderItems() {
    const el = $('[data-items]', root); if (!el) return;
    $('[data-count]', root).textContent = items.length;
    if (!items.length) { el.innerHTML = `<div class="items-grid">${Array(8).fill('<div class="item-card sk"><div class="im"></div><div class="tx"><i></i><i style="width:60%"></i></div></div>').join('')}</div>`; return; }
    el.innerHTML = `<div class="items-grid">${items.map(p => `<div class="item-card"><div class="im">${p.image ? `<img src="${esc(url(p.image))}">` : ic('image')}</div><div class="tx"><a href="${esc(url('products/' + p.id))}" class="strong">${esc(p.title)}</a><div class="muted">${money(p.price)}</div>
      ${C.type === 'manual' ? `<button class="btn plain" style="margin-top:6px;padding:0 4px" type="button" data-rmp="${p.id}">Remove</button>` : `<button class="btn plain" style="margin-top:6px;padding:0 4px" type="button" data-exc="${p.id}">Exclude</button>`}</div></div>`).join('')}</div>`;
    $$('[data-rmp]', el).forEach(b => b.onclick = () => { C.products = C.products.filter(x => x !== +b.dataset.rmp); refreshItems(); dirty(); });
    $$('[data-exc]', el).forEach(b => b.onclick = () => { C.excluded.push(+b.dataset.exc); refreshItems(); renderSide(); dirty(); });
  }

  function renderSide() {
    const el = $('[data-side]', root);
    const conds = C.type === 'smart' ? `<div style="margin-top:10px"><div style="display:flex;gap:14px;margin-bottom:6px"><label class="check"><input type="radio" name="match" value="1" ${C.match_all ? 'checked' : ''}> All conditions</label><label class="check"><input type="radio" name="match" value="0" ${C.match_all ? '' : 'checked'}> Any condition</label></div>
        ${C.conditions.map((c, i) => `<div class="cond-row"><select class="input" data-cd="field:${i}">${Object.entries(FIELDS).map(([k, v]) => `<option value="${k}" ${k === c.field ? 'selected' : ''}>${v}</option>`).join('')}</select><select class="input" data-cd="rel:${i}">${Object.entries(RELS).map(([k, v]) => `<option value="${k}" ${k === c.rel ? 'selected' : ''}>${v}</option>`).join('')}</select><input class="input" data-cd="value:${i}" value="${esc(c.value)}"><button class="icon-btn" type="button" data-rmc="${i}">${ic('trash')}</button></div>`).join('')}</div>` : '';
    el.innerHTML = `<div class="card" style="padding:8px">
        <div style="position:relative;padding:6px 8px"><button class="chip-btn ghost" data-type type="button">${ic('products')} ${C.type === 'smart' ? 'Smart (automated)' : 'Products'} ${ic('updown')}</button></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;padding:4px 8px 8px"><button class="chip-btn" type="button" data-addcond>${ic('plus')} Add condition</button>${C.type === 'manual' ? `<button class="chip-btn" type="button" data-addprod>${ic('search')} Add products</button>` : ''}</div>
        ${conds ? `<div style="padding:0 8px 8px">${conds}</div>` : ''}</div>
      <div style="margin:14px 0 0 6px"><button class="chip-btn ghost" type="button" data-exclude>${ic('plus')} Exclude</button></div>
      ${C.excluded.length ? `<div class="tags-wrap" style="margin:8px 6px">${C.excluded.map(x => `<span class="tag-chip">Excluded #${x}<button type="button" data-unexc="${x}">${ic('x')}</button></span>`).join('')}</div>` : ''}
      <div class="card dashed" style="margin-top:20px"><button class="dz" style="border:0;width:100%" type="button" data-addprod2>+</button></div>`;
    $('[data-type]', el).onclick = e => { e.stopPropagation(); popMenu(e.currentTarget, [
      {label: 'Manual — choose products one by one', action: () => { C.type = 'manual'; renderSide(); refreshItems(); renderItems(); dirty(); }},
      {label: 'Smart — products that match conditions', action: () => { C.type = 'smart'; if (!C.conditions.length) C.conditions.push({field: 'price', rel: 'lt', value: ''}); renderSide(); refreshItems(); dirty(); }}]); };
    $('[data-addcond]', el).onclick = () => { C.type = 'smart'; C.conditions.push({field: 'title', rel: 'contains', value: ''}); renderSide(); dirty(); };
    const addProducts = () => productPicker({title: 'Add products', mode: 'products', selected: C.products, onAdd: (list, ids) => { C.type = 'manual'; C.products = ids.map(Number); renderSide(); refreshItems(); dirty(); }});
    const ap = $('[data-addprod]', el); if (ap) ap.onclick = addProducts;
    $('[data-addprod2]', el).onclick = () => C.type === 'manual' ? addProducts() : $('[data-addcond]', el).click();
    $('[data-exclude]', el).onclick = () => productPicker({title: 'Exclude products', mode: 'products', selected: C.excluded, onAdd: (list, ids) => { C.excluded = ids.map(Number); C.products = C.products.filter(p => !C.excluded.includes(p)); renderSide(); refreshItems(); dirty(); }});
    $$('[data-unexc]', el).forEach(b => b.onclick = () => { C.excluded = C.excluded.filter(x => x !== +b.dataset.unexc); renderSide(); refreshItems(); dirty(); });
    $$('input[name=match]', el).forEach(r => r.onchange = () => { C.match_all = +r.value; refreshItems(); dirty(); });
    let t;
    $$('[data-cd]', el).forEach(i => { i.oninput = i.onchange = () => { const [k, n] = i.dataset.cd.split(':'); C.conditions[+n][k] = i.value; clearTimeout(t); t = setTimeout(refreshItems, 300); dirty(); }; });
    $$('[data-rmc]', el).forEach(b => b.onclick = () => { C.conditions.splice(+b.dataset.rmc, 1); renderSide(); refreshItems(); dirty(); });
  }

  renderMain(); renderSide(); refreshItems();
  const more = $('#cMore');
  if (more) more.onclick = e => { e.stopPropagation(); popMenu(e.currentTarget, [{label: 'Delete collection', danger: true, action: async () => { if (await confirmBox(`Delete ${C.title}?`, 'Products in this collection won’t be deleted.', 'Delete', true)) { await api('collections/delete', {id: C.id}); bar.hide(); location.href = url('collections'); } }}]); };
  const view = $('#cView'); if (view) view.onclick = () => { navigator.clipboard && navigator.clipboard.writeText(`${C.store_url}/collections/${C.handle}`); toast('Collection link copied'); };
});
