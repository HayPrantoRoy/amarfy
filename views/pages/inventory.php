<?php
$PAGE_TITLE = 'Inventory';
$locs = locations();
$loc = (int)($_GET['location_id'] ?? ($locs[0]['id'] ?? 0));
$view = $_GET['view'] ?? 'all';
$q = trim((string)($_GET['q'] ?? ''));
$where = ['p.track_inventory=1']; $p = [$loc];
if ($q !== '') { $where[] = '(p.title LIKE ? OR v.sku LIKE ? OR v.title LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%"); }
if ($view === 'in_stock') $where[] = 'COALESCE(il.available,0) > 0';
if ($view === 'out_of_stock') $where[] = 'COALESCE(il.available,0) <= 0';
if ($view === 'incoming') $where[] = 'COALESCE(il.incoming,0) > 0';
$rows = t_rows("SELECT v.id vid, v.title vtitle, v.sku, p.id pid, p.title, (SELECT path FROM product_media m WHERE m.product_id=p.id ORDER BY position, id LIMIT 1) image,
   COALESCE(il.available,0) available, COALESCE(il.committed,0) committed, COALESCE(il.unavailable,0) unavailable, COALESCE(il.incoming,0) incoming
   FROM variants v JOIN products p ON p.id=v.product_id LEFT JOIN inventory_levels il ON il.variant_id=v.id AND il.location_id=?
   WHERE " . implode(' AND ', $where) . " ORDER BY p.title, v.position, v.id LIMIT 500", $p);
$sel = '<label class="top-select"><select onchange="location.href=\'?location_id=\'+this.value">';
foreach ($locs as $l) $sel .= '<option value="' . $l['id'] . '"' . ($loc === (int)$l['id'] ? ' selected' : '') . '>' . h($l['name']) . '</option>';
$sel .= '</select>' . icon('updown') . '</label>';
echo topbar('inventory', [['Inventory', null]], '<button class="btn" type="button" id="stockCounts">Stock counts</button><a class="btn" href="' . h(url('export/inventory')) . '">Export</a><button class="btn" type="button" id="invImport">Import</button>', null, $sel);
?>
<div class="page">
  <?= filter_bar(['all' => 'All', 'in_stock' => 'In stock', 'out_of_stock' => 'Out of stock', 'incoming' => 'Incoming'], $view, $q, 'invTable') ?>
  <div class="table-wrap">
  <table class="grid" id="invTable">
    <thead><tr><th class="w-cb"><input type="checkbox" class="cb"></th><th style="width:52px"></th><th>Product</th><th>SKU</th><th class="num"><span class="dotted">Unavailable</span></th><th class="num"><span class="dotted">Committed</span></th><th class="num" style="width:150px"><span class="dotted">Available</span></th><th class="num" style="width:150px"><span class="dotted">On hand</span></th><th class="num"><span class="dotted">Incoming</span></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr class="empty-row"><td colspan="9">No tracked inventory found at this location</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): $onHand = $r['available'] + $r['committed'] + $r['unavailable']; ?>
      <tr data-vid="<?= (int)$r['vid'] ?>">
        <td class="w-cb"><input type="checkbox" class="cb"></td>
        <td><?= thumb($r['image']) ?></td>
        <td><a class="strong" href="<?= h(url('products/' . $r['pid'] . '?fromInventory=true')) ?>"><?= h($r['title']) ?></a><?php if ($r['vtitle'] !== 'Default Title'): ?><br><span class="vchip"><?= h($r['vtitle']) ?></span><?php endif; ?></td>
        <td><?= $r['sku'] ? h($r['sku']) : '<span class="muted">No SKU</span>' ?></td>
        <td class="num"><?= (int)$r['unavailable'] ?></td>
        <td class="num"><?= (int)$r['committed'] ?></td>
        <td class="num"><input class="inv-edit" type="number" data-field="available" value="<?= (int)$r['available'] ?>"></td>
        <td class="num"><input class="inv-edit" type="number" data-field="on_hand" value="<?= (int)$onHand ?>"></td>
        <td class="num"><?= (int)$r['incoming'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<style>
.inv-edit{width:84px;height:32px;border:1px solid transparent;border-radius:8px;text-align:right;padding:0 8px;background:transparent;outline:0}
tr:hover .inv-edit{border-color:#cfcfcf;background:#fff}
.inv-edit:focus{border-color:#2c6ecb!important;box-shadow:0 0 0 1px #2c6ecb;background:#fff}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const loc = <?= $loc ?>;
  document.querySelectorAll('.inv-edit').forEach(inp => {
    inp.dataset.orig = inp.value;
    const commit = async () => {
      if (inp.value === inp.dataset.orig) return;
      const tr = inp.closest('tr');
      try {
        const r = await UI.api('inventory/set', {variant_id: +tr.dataset.vid, location_id: loc, field: inp.dataset.field, value: parseInt(inp.value) || 0});
        tr.querySelector('[data-field=available]').value = tr.querySelector('[data-field=available]').dataset.orig = r.level.available;
        tr.querySelector('[data-field=on_hand]').value = tr.querySelector('[data-field=on_hand]').dataset.orig = r.level.on_hand;
        UI.toast('Inventory updated');
      } catch (e) { inp.value = inp.dataset.orig; }
    };
    inp.addEventListener('blur', commit);
    inp.addEventListener('keydown', e => { if (e.key === 'Enter') inp.blur(); if (e.key === 'Escape') { inp.value = inp.dataset.orig; inp.blur(); } });
  });
  document.getElementById('stockCounts').onclick = () => {
    const rows = [...document.querySelectorAll('#invTable tbody tr[data-vid]')];
    const m = UI.modal({title: 'Stock count', size: 'wide', body: `<p class="muted">Count what is physically on the shelf. Differences will update “On hand” at this location.</p><div class="picker-scroll" style="margin:0"><table class="grid"><thead><tr><th>Product</th><th class="num">Expected</th><th class="num" style="width:120px">Counted</th></tr></thead><tbody>${rows.map(r => `<tr><td>${UI.esc(r.children[2].innerText.replace(/\n/g, ' · '))}</td><td class="num">${r.querySelector('[data-field=on_hand]').value}</td><td class="num"><input class="input" type="number" data-count="${r.dataset.vid}" value="${r.querySelector('[data-field=on_hand]').value}"></td></tr>`).join('')}</tbody></table></div>`,
      footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-ok>Apply count</button>'});
    m.$('[data-ok]').onclick = async () => {
      for (const i of m.$$('[data-count]')) {
        const tr = document.querySelector(`tr[data-vid="${i.dataset.count}"]`);
        if (i.value !== tr.querySelector('[data-field=on_hand]').value) await UI.api('inventory/set', {variant_id: +i.dataset.count, location_id: loc, field: 'on_hand', value: parseInt(i.value) || 0});
      }
      location.reload();
    };
  };
  document.getElementById('invImport').onclick = () => {
    const m = UI.modal({title: 'Import inventory', body: `<p>Upload a CSV with columns <b>SKU</b> and <b>Available</b> to update quantities at this location.</p><input type="file" accept=".csv" data-f>`, footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-go>Import</button>'});
    m.$('[data-go]').onclick = async () => {
      const f = m.$('[data-f]').files[0]; if (!f) return UI.toast('Choose a CSV file', true);
      const text = await f.text(), lines = text.trim().split(/\r?\n/), head = lines.shift().toLowerCase().split(',').map(s => s.trim().replace(/"/g, ''));
      const si = head.indexOf('sku'), ai = head.indexOf('available'); if (si < 0 || ai < 0) return UI.toast('CSV needs SKU and Available columns', true);
      const bySku = {}; document.querySelectorAll('#invTable tbody tr[data-vid]').forEach(tr => { const s = tr.children[3].innerText.trim(); if (s && s !== 'No SKU') bySku[s] = +tr.dataset.vid; });
      let n = 0;
      for (const l of lines) { const c = l.split(',').map(s => s.trim().replace(/"/g, '')); if (bySku[c[si]]) { await UI.api('inventory/set', {variant_id: bySku[c[si]], location_id: loc, field: 'available', value: parseInt(c[ai]) || 0}); n++; } }
      UI.toast(n + ' rows updated'); setTimeout(() => location.reload(), 500);
    };
  };
});
</script>
