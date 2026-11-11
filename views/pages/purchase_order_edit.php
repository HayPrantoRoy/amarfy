<?php
$po = null;
if (is_int($id)) {
    $po = t_row("SELECT * FROM purchase_orders WHERE id=?", [$id]);
    if (!$po) { echo topbar('po', [['Purchase orders', 'purchase_orders']], '', 'purchase_orders') . '<div class="page-pad">' . empty_state('', 'Purchase order not found', 'It may have been deleted.', 'Back', 'purchase_orders') . '</div>'; return; }
}
$PAGE_TITLE = $po ? '#PO' . $po['number'] : 'Create purchase order';
$items = $po ? t_rows("SELECT i.*, v.title vtitle, v.sku, p.title, p.id pid, (SELECT path FROM product_media m WHERE m.product_id=p.id ORDER BY position, id LIMIT 1) image
  FROM purchase_order_items i JOIN variants v ON v.id=i.variant_id JOIN products p ON p.id=v.product_id WHERE i.purchase_order_id=? ORDER BY i.id", [$id]) : [];
$state = [
    'kind' => 'po', 'id' => $po ? (int)$po['id'] : 0, 'number' => $po['number'] ?? null, 'status' => $po['status'] ?? 'draft',
    'supplier_id' => $po['supplier_id'] ?? null, 'destination_id' => $po['destination_id'] ?? null,
    'reference' => $po['reference'] ?? '', 'note' => $po['note'] ?? '', 'terms' => $po['terms'] ?? '', 'expected_arrival' => $po['expected_arrival'] ?? '',
    'carrier' => $po['carrier'] ?? '', 'tracking' => $po['tracking'] ?? '', 'shipping' => (float)($po['shipping'] ?? 0),
    'tags' => $po ? array_values(array_filter(explode(',', (string)$po['tags']))) : [],
    'items' => array_map(fn($i) => ['line_id' => (int)$i['id'], 'variant_id' => (int)$i['variant_id'], 'product_id' => (int)$i['pid'], 'title' => $i['title'], 'variant_title' => $i['vtitle'] === 'Default Title' ? '' : $i['vtitle'], 'image' => $i['image'], 'sku' => $i['sku'], 'supplier_sku' => $i['supplier_sku'], 'quantity' => (int)$i['quantity'], 'received' => (int)$i['received'], 'cost' => (float)$i['cost'], 'tax_rate' => (float)$i['tax_rate']], $items),
    'suppliers' => t_rows("SELECT id, name FROM suppliers ORDER BY name"),
    'locations' => array_map(fn($l) => ['id' => (int)$l['id'], 'name' => $l['name']], locations()),
];
$actions = $po ? '<button class="btn" id="docMore" type="button">More actions ' . icon('chev-down') . '</button>' : '';
echo topbar('po', $po ? [['Purchase orders', 'purchase_orders'], ['#PO' . $po['number'], null]] : [['Purchase orders', 'purchase_orders']], $actions, 'purchase_orders');
?>
<div class="form-layout mid" id="docApp" style="padding-left:240px">
  <div style="grid-column:1/3"><h1 class="page-h1"><?= $po ? '#PO' . (int)$po['number'] . ' ' . status_pill($po['status']) : 'Create purchase order' ?></h1></div>
  <div data-main></div>
  <aside data-side></aside>
  <?php if ($po): ?><div style="grid-column:1/2"><div class="sec-head" style="margin-top:20px"><h2>Timeline</h2></div><?= timeline_html('purchase_order', (int)$po['id']) ?></div><?php endif; ?>
</div>
<script>window.DOC = <?= json_encode($state, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php $GLOBALS['PAGE_SCRIPTS'][] = 'timeline.js'; $GLOBALS['PAGE_SCRIPTS'][] = 'stockdoc.js'; ?>
