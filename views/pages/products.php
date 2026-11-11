<?php
$PAGE_TITLE = 'Products';
$view = $_GET['view'] ?? 'all';
$q = trim((string)($_GET['q'] ?? ''));
$sort = in_array($_GET['sort'] ?? '', ['title', 'inventory', 'type', 'vendor', 'created'], true) ? $_GET['sort'] : 'created';
$dir = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$where = ['1=1']; $p = [];
if (in_array($view, ['active', 'draft', 'archived'], true)) { $where[] = 'p.status=?'; $p[] = $view; }
if ($q !== '') { $where[] = '(p.title LIKE ? OR p.vendor LIKE ? OR p.product_type LIKE ? OR p.tags LIKE ? OR EXISTS (SELECT 1 FROM variants v WHERE v.product_id=p.id AND v.sku LIKE ?))'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%"); }
$ob = ['title' => 'p.title', 'inventory' => 'stock', 'type' => 'p.product_type', 'vendor' => 'p.vendor', 'created' => 'p.id'][$sort];
$rows = t_rows("SELECT p.*, (SELECT COUNT(*) FROM variants v WHERE v.product_id=p.id) vcount,
  (SELECT COALESCE(SUM(il.available),0) FROM inventory_levels il JOIN variants v ON v.id=il.variant_id WHERE v.product_id=p.id) stock,
  (SELECT path FROM product_media m WHERE m.product_id=p.id ORDER BY position, id LIMIT 1) image
  FROM products p WHERE " . implode(' AND ', $where) . " ORDER BY $ob $dir LIMIT 250", $p);

// 30-day analytics
$sold = (int)t_val("SELECT COALESCE(SUM(i.quantity),0) FROM order_items i JOIN orders o ON o.id=i.order_id WHERE o.created_at >= NOW() - INTERVAL 30 DAY AND o.cancelled_at IS NULL");
$onHand = (int)t_val("SELECT COALESCE(SUM(available+committed),0) FROM inventory_levels");
$sellThrough = ($sold + $onHand) > 0 ? $sold / ($sold + $onHand) * 100 : 0;
$daysLeft = $sold > 0 ? round($onHand / ($sold / 30)) : null;
$abc = t_rows("SELECT i.product_id, SUM(i.price*i.quantity) rev FROM order_items i JOIN orders o ON o.id=i.order_id WHERE o.created_at >= NOW() - INTERVAL 30 DAY AND o.cancelled_at IS NULL AND i.product_id IS NOT NULL GROUP BY i.product_id ORDER BY rev DESC");
$totalRev = array_sum(array_column($abc, 'rev')); $run = 0; $a = 0; $c = 0;
foreach ($abc as $r) { $run += $r['rev']; if ($totalRev && $run / $totalRev <= 0.8) $a += $r['rev']; elseif ($totalRev && $run / $totalRev > 0.95) $c += $r['rev']; }
echo topbar('products', [['Products', null]],
  '<button class="btn" type="button" id="viewAs">' . icon('eye') . 'View as</button><button class="btn" type="button" id="importBtn">Import</button><a class="btn" href="' . h(url('export/products')) . '">Export</a><a class="btn primary" href="' . h(url('products/new')) . '">Add product</a>');
?>
<div class="page">
  <div class="metrics-strip">
    <div class="range"><?= icon('calendar') ?>30 days</div>
    <div class="metric" style="flex:1.4"><div class="m-label"><span>Average sell-through rate</span></div><div class="m-val"><?= number_format($sellThrough, 2) ?>%</div></div>
    <div class="metric" style="flex:1.4"><div class="m-label"><span>Products by days of inventory remaining</span></div><div class="m-val" style="font-weight:450"><?= $daysLeft !== null ? $daysLeft . ' days' : 'No data' ?></div></div>
    <div class="metric"><div class="m-label"><span>ABC product analysis</span></div><div class="m-val"><span style="border-bottom:2px solid #4b8fe2"><?= h(cur()) ?> <?= number_format($a, 2) ?> A</span> &nbsp; <span style="border-bottom:2px solid #7b5cf5"><?= h(cur()) ?> <?= number_format($c, 2) ?> C</span></div></div>
  </div>
  <?= filter_bar(['all' => 'All', 'active' => 'Active', 'draft' => 'Draft', 'archived' => 'Archived'], $view, $q, 'productsTable') ?>
  <div class="bulk-bar" id="prodBulk" hidden><b data-selcount></b>
    <button class="btn" data-bulk-action="active">Set as active</button><button class="btn" data-bulk-action="draft">Set as draft</button><button class="btn" data-bulk-action="archived">Archive products</button>
    <button class="btn danger" data-bulk-action="delete" data-confirm="Delete products?">Delete products</button>
  </div>
  <div class="table-wrap">
  <table class="grid" id="productsTable" data-bulk="prodBulk" data-endpoint="products/bulk">
    <thead><tr><th class="w-cb"><input type="checkbox" class="cb"></th><th style="width:52px"></th><th><?= sort_link('Product', 'title', $sort, strtolower($dir)) ?></th><th>Status</th><th><?= sort_link('Inventory', 'inventory', $sort, strtolower($dir)) ?></th><th>Category</th><th class="num">Channels</th><th><?= sort_link('Product type', 'type', $sort, strtolower($dir)) ?></th><th><?= sort_link('Vendor', 'vendor', $sort, strtolower($dir)) ?></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr class="empty-row"><td colspan="9"><?= $q !== '' || $view !== 'all' ? 'No products found' : 'Add your first product. <a class="link" href="' . h(url('products/new')) . '">Add product</a>' ?></td></tr><?php endif; ?>
    <?php foreach ($rows as $r):
        $inv = !$r['track_inventory'] ? '<span class="muted">Inventory not tracked</span>'
            : ((int)$r['stock'] <= 0 ? '<span class="red">' . (int)$r['stock'] . ' in stock</span>' . ($r['vcount'] > 1 ? ' for ' . $r['vcount'] . ' variants' : '')
            : (int)$r['stock'] . ' in stock' . ($r['vcount'] > 1 ? ' for ' . $r['vcount'] . ' variants' : '')); ?>
      <tr data-href="<?= h(url('products/' . $r['id'])) ?>" style="<?= $r['status'] === 'draft' ? 'opacity:.75' : '' ?>">
        <td class="w-cb"><input type="checkbox" class="cb" value="<?= (int)$r['id'] ?>"></td>
        <td><?= thumb($r['image']) ?></td>
        <td class="strong"><?= h($r['title']) ?></td>
        <td><?= status_pill($r['status']) ?></td>
        <td><?= $inv ?></td>
        <td><?= h($r['category']) ?></td>
        <td class="num"><?= $r['status'] === 'active' ? 1 : 0 ?></td>
        <td><?= h($r['product_type']) ?></td>
        <td><?= h($r['vendor']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?= learn_more('products') ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('viewAs').onclick = e => { e.stopPropagation(); UI.popMenu(e.currentTarget, [
    {label: 'All products', action: () => location.href = '?'}, {label: 'Active only', action: () => location.href = '?view=active'},
    {label: 'Draft only', action: () => location.href = '?view=draft'}, {label: 'Archived only', action: () => location.href = '?view=archived'}]); };
  document.getElementById('importBtn').onclick = () => {
    const m = UI.modal({title: 'Import products by CSV', body: `<div class="dz" style="padding:34px;flex-direction:column;align-items:center"><input type="file" accept=".csv,text/csv" data-f></div>
      <p class="hint">Columns: <b>Title</b>, Description, Status, Vendor, Type, Tags, Price, Compare at price, SKU, Barcode, Quantity. <a class="link" href="${UI.url('export/products_template')}">Download a sample CSV template</a></p>`,
      footer: '<button class="btn" data-close>Cancel</button><button class="btn primary" data-go>Upload and continue</button>'});
    m.$('[data-go]').onclick = async () => {
      const f = m.$('[data-f]').files[0]; if (!f) { UI.toast('Choose a CSV file', true); return; }
      const fd = new FormData(); fd.append('file', f);
      const r = await UI.api('products/import', fd); UI.toast(r.count + ' products imported'); setTimeout(() => location.reload(), 600);
    };
  };
});
</script>
