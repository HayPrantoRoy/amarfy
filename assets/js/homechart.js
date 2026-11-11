/* Home metric popovers: "Total sales over time" style line chart (current vs previous period). */
document.addEventListener('DOMContentLoaded', () => {
  const D = window.HOME_CHART;
  const strip = document.getElementById('homeMetrics'), pop = document.getElementById('metricPop');
  if (!D || !strip || !pop) return;
  const {esc, popMenu} = UI;
  const CUR = D.currency;
  const META = {
    sessions: {title: 'Sessions over time', desc: 'Number of sessions on your online store', money: false},
    sales: {title: 'Total sales over time', desc: 'Amount spent (subtotal, taxes, shipping, sales reversals, discounts, fees, etc.)', money: true},
    orders: {title: 'Orders over time', desc: 'Number of orders placed', money: false},
    aov: {title: 'Average order value over time', desc: 'Total sales divided by number of orders', money: true},
  };
  const fmtFull = (v, money) => money ? `${CUR} ${Number(v).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}` : Number(v).toLocaleString('en-US');
  const fmtAxis = (v, money) => {
    const n = Math.abs(v) >= 1e6 ? (v / 1e6).toFixed(v % 1e6 ? 1 : 0) + 'M' : Math.abs(v) >= 1e3 ? +(v / 1e3).toFixed(1) + 'K' : String(+v.toFixed(0));
    return money ? `${CUR} ${n}` : n;
  };
  const day = s => new Date(s + 'T00:00:00');
  const short = s => day(s).toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
  const niceMax = m => {
    if (m <= 0) return 4;
    const exp = Math.pow(10, Math.floor(Math.log10(m))), f = m / exp;
    const step = [1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10].find(s => s >= f) * exp;
    return step;
  };

  function chart(key) {
    const meta = META[key], cur = D.cur[key], prev = D.prev[key], n = cur.length;
    const W = 780, H = 340, L = 76, R = 10, T = 10, B = 40, pw = W - L - R, ph = H - T - B;
    const max = niceMax(Math.max(...cur, ...prev) * 1.05);
    const ticks = [0, 1, 2, 3, 4].map(i => max * i / 4);
    const x = i => L + (n === 1 ? pw / 2 : pw * i / (n - 1));
    const y = v => T + ph - ph * (v / max);
    // smooth monotone-ish curve (Catmull-Rom → Bézier, clamped so it never dips below 0)
    const base = T + ph;
    const path = arr => arr.map((v, i) => {
      if (i === 0) return `M${x(0)},${y(v)}`;
      const a = Math.max(0, i - 2), b = i - 1, c = i, d = Math.min(n - 1, i + 1);
      const c1x = x(b) + (x(c) - x(a)) / 6, c1y = Math.min(base, y(arr[b]) + (y(arr[c]) - y(arr[a])) / 6);
      const c2x = x(c) - (x(d) - x(b)) / 6, c2y = Math.min(base, y(arr[c]) - (y(arr[d]) - y(arr[b])) / 6);
      return `C${c1x},${c1y} ${c2x},${c2y} ${x(c)},${y(v)}`;
    }).join(' ');
    const every = n <= 7 ? 1 : n <= 31 ? 4 : 14;
    const xl = D.cur.dates.map((d, i) => i % every === 0 ? `<text class="xlab" x="${x(i)}" y="${H - 12}" text-anchor="middle">${esc(short(d))}</text>` : '').join('');
    const yl = ticks.map(t => `<text class="ylab" x="${L - 14}" y="${y(t) + 4}" text-anchor="end">${esc(fmtAxis(t, meta.money))}</text>`).join('');
    const grid = ticks.map(t => `<line x1="${L}" x2="${W - R}" y1="${y(t)}" y2="${y(t)}"/>`).join('');
    return `<svg viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(meta.title)}: ${esc(D.curLabel)} compared with ${esc(D.prevLabel)}">
        <g class="grid">${grid}</g>${yl}${xl}
        <path class="prev" d="${path(prev)}"/><path class="cur" d="${path(cur)}"/>
        <g class="hover" visibility="hidden"><line class="xhair" y1="${T}" y2="${T + ph}"/><circle class="dot-prev" r="5"/><circle class="dot-cur" r="5"/></g>
        <rect class="hit" x="${L}" y="${T}" width="${pw}" height="${ph}" fill="transparent"/></svg>
      <div class="mp-tip" hidden></div>`;
  }

  function render(key) {
    const meta = META[key], total = D.totals[key];
    const noData = key === 'sessions' && D.cur.sessions.every(v => !v);
    pop.innerHTML = `<div class="mp-head"><div><div class="mp-val">${esc(fmtFull(total, meta.money))}</div>
        <div class="mp-title">${esc(meta.title)}</div><div class="mp-desc">${esc(meta.desc)}</div></div>
        <button class="icon-btn mp-edit" type="button" data-edit aria-label="Change date range"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.5 2.8l2.7 2.7-7.4 7.4-3.3.6.6-3.3z"/><path d="m9.3 4 2.7 2.7"/></svg></button></div>
      ${noData ? `<div class="mp-empty">Daily session data appears here once your online store is live and receiving visitors.</div>` : `<div class="mp-chart">${chart(key)}</div>`}
      <div class="mp-legend"><span><i style="background:#3b82d6"></i>${esc(D.curLabel)}</span><span><i style="background:#9cc3ec"></i>${esc(D.prevLabel)}</span></div>
      <details class="mp-table"><summary>View as table</summary><div class="t"><table class="grid"><thead><tr><th>Date</th><th class="num">${esc(D.curLabel)}</th><th class="num">${esc(D.prevLabel)}</th></tr></thead><tbody>
        ${D.cur.dates.map((d, i) => `<tr><td>${esc(short(d))}</td><td class="num">${esc(fmtFull(D.cur[key][i], meta.money))}</td><td class="num">${esc(fmtFull(D.prev[key][i], meta.money))}</td></tr>`).join('')}</tbody></table></div></details>`;
    pop.querySelector('[data-edit]').onclick = e => { e.stopPropagation(); rangeMenu(e.currentTarget); };
    bindHover(key);
  }

  function bindHover(key) {
    const svg = pop.querySelector('.mp-chart svg'); if (!svg) return;
    const tip = pop.querySelector('.mp-tip'), g = svg.querySelector('.hover'), hit = svg.querySelector('.hit');
    const meta = META[key], n = D.cur[key].length;
    const vb = svg.viewBox.baseVal, L = 76, R = 10, pw = vb.width - L - R;
    hit.addEventListener('mousemove', ev => {
      const r = svg.getBoundingClientRect(), sx = (ev.clientX - r.left) * vb.width / r.width;
      const i = Math.max(0, Math.min(n - 1, Math.round((sx - L) / pw * (n - 1))));
      const cx = L + (n === 1 ? pw / 2 : pw * i / (n - 1));
      const curPath = svg.querySelector('.cur'), prevPath = svg.querySelector('.prev');
      const pointAt = (pathEl) => { // nearest y on the path at this x
        const len = pathEl.getTotalLength(); let lo = 0, hi = len;
        for (let k = 0; k < 24; k++) { const mid = (lo + hi) / 2; pathEl.getPointAtLength(mid).x < cx ? lo = mid : hi = mid; }
        return pathEl.getPointAtLength(lo).y;
      };
      g.setAttribute('visibility', 'visible');
      g.querySelector('.xhair').setAttribute('x1', cx); g.querySelector('.xhair').setAttribute('x2', cx);
      const yc = pointAt(curPath), yp = pointAt(prevPath);
      g.querySelector('.dot-cur').setAttribute('cx', cx); g.querySelector('.dot-cur').setAttribute('cy', yc);
      g.querySelector('.dot-prev').setAttribute('cx', cx); g.querySelector('.dot-prev').setAttribute('cy', yp);
      tip.hidden = false;
      tip.innerHTML = `<div class="r"><i style="background:#3b82d6"></i>${esc(short(D.cur.dates[i]))}<b style="margin-left:auto;padding-left:14px">${esc(fmtFull(D.cur[key][i], meta.money))}</b></div>
        <div class="r"><i style="background:#9cc3ec"></i>${esc(short(D.prev.dates[i]))}<b style="margin-left:auto;padding-left:14px">${esc(fmtFull(D.prev[key][i], meta.money))}</b></div>`;
      tip.style.left = (cx * r.width / vb.width) + 'px';
      tip.style.top = (Math.min(yc, yp) * r.height / vb.height) + 'px';
    });
    hit.addEventListener('mouseleave', () => { g.setAttribute('visibility', 'hidden'); tip.hidden = true; });
  }

  function rangeMenu(anchor) {
    popMenu(anchor, [7, 30, 90].map(n => ({label: `Last ${n} days${n === D.range ? ' ✓' : ''}`, action: () => location.href = UI.url('home?range=' + n)})));
  }
  document.getElementById('rangeBtn').onclick = e => { e.stopPropagation(); rangeMenu(e.currentTarget); };

  /* open on hover (desktop) or click (touch / pin); close when leaving both */
  let active = null, pinned = false, closeT;
  const open = (btn) => {
    clearTimeout(closeT);
    if (active === btn) return;
    strip.querySelectorAll('.home-metric').forEach(b => b.classList.toggle('active', b === btn));
    strip.classList.add('has-active');
    active = btn; pop.hidden = false; render(btn.dataset.metric);
  };
  const close = () => { active = null; pinned = false; pop.hidden = true; strip.classList.remove('has-active'); strip.querySelectorAll('.home-metric').forEach(b => b.classList.remove('active')); };
  const later = () => { if (!pinned) closeT = setTimeout(close, 220); };
  strip.querySelectorAll('.home-metric').forEach(btn => {
    btn.addEventListener('mouseenter', () => open(btn));
    btn.addEventListener('mouseleave', later);
    btn.addEventListener('click', e => { e.stopPropagation(); if (active === btn && pinned) { close(); return; } open(btn); pinned = true; });
    btn.addEventListener('focus', () => open(btn));
  });
  pop.addEventListener('mouseenter', () => clearTimeout(closeT));
  pop.addEventListener('mouseleave', later);
  pop.addEventListener('click', e => e.stopPropagation());
  document.addEventListener('click', e => { if (active && !e.target.closest('[data-pop]')) close(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && active) close(); });
});
