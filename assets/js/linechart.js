/* Reusable current-vs-previous line chart with crosshair tooltip (single y-axis). */
window.LineChart = (function () {
  const C1 = '#3b82d6', C2 = '#9cc3ec';
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
  const short = s => new Date(s + 'T00:00:00').toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
  const niceMax = m => { if (m <= 0) return 4; const e = Math.pow(10, Math.floor(Math.log10(m))), f = m / e; return [1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10].find(s => s >= f) * e; };

  function render(el, o) {
    const cur = o.cur, prev = o.prev, n = cur.length, money = !!o.money, sym = o.symbol || o.currency || '';
    const fmtAxis = v => { const a = Math.abs(v); const t = a >= 1e6 ? +(v / 1e6).toFixed(1) + 'M' : a >= 1e3 ? +(v / 1e3).toFixed(1) + 'K' : String(Math.round(v)); return money ? sym + t : t; };
    const fmt = v => money ? `${sym}${Number(v).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}` : Number(v).toLocaleString('en-US');
    const W = o.width || 1100, H = o.height || 320, L = 70, R = 12, T = 12, B = 38, pw = W - L - R, ph = H - T - B, base = T + ph;
    const max = niceMax(Math.max(...cur, ...prev, 0) * 1.05);
    const ticks = [0, 1, 2, 3, 4].map(i => max * i / 4);
    const x = i => L + (n === 1 ? pw / 2 : pw * i / (n - 1));
    const y = v => T + ph - ph * (v / max);
    const path = arr => arr.map((v, i) => {
      if (i === 0) return `M${x(0)},${y(v)}`;
      const a = Math.max(0, i - 2), b = i - 1, c = i, d = Math.min(n - 1, i + 1);
      return `C${x(b) + (x(c) - x(a)) / 6},${Math.min(base, y(arr[b]) + (y(arr[c]) - y(arr[a])) / 6)} ${x(c) - (x(d) - x(b)) / 6},${Math.min(base, y(arr[c]) - (y(arr[d]) - y(arr[b])) / 6)} ${x(c)},${y(v)}`;
    }).join(' ');
    const every = n <= 7 ? 1 : n <= 31 ? 2 : 10;
    el.innerHTML = `<div class="lc"><svg viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(o.label || 'Chart')}">
        <g class="lc-grid">${ticks.map(t => `<line x1="${L}" x2="${W - R}" y1="${y(t)}" y2="${y(t)}"/>`).join('')}</g>
        ${ticks.map(t => `<text class="lc-y" x="${L - 12}" y="${y(t) + 4}" text-anchor="end">${esc(fmtAxis(t))}</text>`).join('')}
        ${o.dates.map((d, i) => i % every === 0 ? `<text class="lc-x" x="${x(i)}" y="${H - 10}" text-anchor="middle">${esc(short(d))}</text>` : '').join('')}
        <path d="${path(prev)}" fill="none" stroke="${C2}" stroke-width="2" stroke-dasharray="3 4" stroke-linecap="round"/>
        <path class="lc-cur" d="${path(cur)}" fill="none" stroke="${C1}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
        <g class="lc-hover" visibility="hidden"><line y1="${T}" y2="${base}" stroke="#8a8a8a"/><circle r="5" fill="${C1}" stroke="#fff" stroke-width="2"/></g>
        <rect class="lc-hit" x="${L}" y="${T}" width="${pw}" height="${ph}" fill="transparent"/></svg>
      <div class="lc-tip" hidden></div>
      <div class="lc-legend"><span><i style="background:${C1}"></i>${esc(o.curLabel)}</span><span><i style="background:${C2}"></i>${esc(o.prevLabel)}</span></div></div>`;
    const svg = el.querySelector('svg'), tip = el.querySelector('.lc-tip'), g = el.querySelector('.lc-hover'), curPath = el.querySelector('.lc-cur');
    el.querySelector('.lc-hit').addEventListener('mousemove', ev => {
      const r = svg.getBoundingClientRect(), sx = (ev.clientX - r.left) * W / r.width;
      const i = Math.max(0, Math.min(n - 1, Math.round((sx - L) / pw * (n - 1)))), cx = x(i);
      let lo = 0, hi = curPath.getTotalLength();
      for (let k = 0; k < 22; k++) { const mid = (lo + hi) / 2; curPath.getPointAtLength(mid).x < cx ? lo = mid : hi = mid; }
      const cy = curPath.getPointAtLength(lo).y;
      g.setAttribute('visibility', 'visible'); g.querySelector('line').setAttribute('x1', cx); g.querySelector('line').setAttribute('x2', cx);
      g.querySelector('circle').setAttribute('cx', cx); g.querySelector('circle').setAttribute('cy', cy);
      tip.hidden = false;
      tip.innerHTML = `<div class="r"><i style="background:${C1}"></i>${esc(short(o.dates[i]))}<b>${esc(fmt(cur[i]))}</b></div><div class="r"><i style="background:${C2}"></i>${esc(short(o.prevDates[i]))}<b>${esc(fmt(prev[i]))}</b></div>`;
      tip.style.left = (cx * r.width / W) + 'px'; tip.style.top = (cy * r.height / H) + 'px';
    });
    el.querySelector('.lc-hit').addEventListener('mouseleave', () => { g.setAttribute('visibility', 'hidden'); tip.hidden = true; });
  }
  return {render};
})();
