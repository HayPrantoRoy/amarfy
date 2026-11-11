<?php
$t = null;
if (is_int($id)) {
    $t = t_row("SELECT * FROM transfers WHERE id=?", [$id]);
    if (!$t) { echo topbar('transfer', [['Transfers', 'transfers']], '', 'transfers') . '<div class="page-pad">' . empty_state('', 'Transfer not found', 'It may have been deleted.', 'Back', 'transfers') . '</div>'; return; }
}
$PAGE_TITLE = $t ? '#T' . $t['number'] : 'Create transfer';
$items = $t ? t_rows("SELECT i.*, v.title vtitle, v.sku, p.title, p.id pid, (SELECT path FROM product_media m WHERE m.product_id=p.id ORDER BY position, id LIMIT 1) image
  FROM transfer_items i JOIN variants v ON v.id=i.variant_id JOIN products p ON p.id=v.product_id WHERE i.transfer_id=? ORDER BY i.id", [$id]) : [];
$state = [
    'kind' => 'transfer', 'id' => $t ? (int)$t['id'] : 0, 'number' => $t['number'] ?? null, 'status' => $t['status'] ?? 'draft',
    'origin_id' => $t['origin_id'] ?? null, 'destination_id' => $t['destination_id'] ?? null,
    'transfer_date' => $t['transfer_date'] ?? date('Y-m-d'), 'reference' => $t['reference'] ?? '', 'note' => $t['note'] ?? '',
    'tags' => $t ? array_values(array_filter(explode(',', (string)$t['tags']))) : [], 'purchase_order_id' => $t['purchase_order_id'] ?? null,
    'items' => array_map(fn($i) => ['line_id' => (int)$i['id'], 'variant_id' => (int)$i['variant_id'], 'product_id' => (int)$i['pid'], 'title' => $i['title'], 'variant_title' => $i['vtitle'] === 'Default Title' ? '' : $i['vtitle'], 'image' => $i['image'], 'sku' => $i['sku'], 'quantity' => (int)$i['quantity'], 'received' => (int)$i['received']], $items),
    'locations' => array_map(fn($l) => ['id' => (int)$l['id'], 'name' => $l['name']], locations()),
    'purchase_orders' => t_rows("SELECT id, number, status FROM purchase_orders ORDER BY id DESC"),
];
$actions = $t ? '<button class="btn" id="docMore" type="button">More actions ' . icon('chev-down') . '</button>' : '';
echo topbar('transfer', $t ? [['Transfers', 'transfers'], ['#T' . $t['number'], null]] : [['Transfers', 'transfers']], $actions, 'transfers');
?>
<div class="form-layout single" id="docApp">
  <div><h1 class="page-h1"><?= $t ? '#T' . (int)$t['number'] . ' ' . status_pill($t['status']) : 'Create transfer' ?></h1></div>
  <div data-main></div>
  <?php if ($t): ?><div><div class="sec-head" style="margin-top:20px"><h2>Timeline</h2></div><?= timeline_html('transfer', (int)$t['id']) ?></div><?php endif; ?>
</div>
<script>window.DOC = <?= json_encode($state, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php $GLOBALS['PAGE_SCRIPTS'][] = 'timeline.js'; $GLOBALS['PAGE_SCRIPTS'][] = 'stockdoc.js'; ?>
