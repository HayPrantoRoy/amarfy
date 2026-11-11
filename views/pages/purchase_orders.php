<?php
$PAGE_TITLE = 'Purchase orders';
$view = $_GET['view'] ?? 'all';
$q = trim((string)($_GET['q'] ?? ''));
$where = ['1=1']; $p = [];
if (in_array($view, ['draft', 'ordered', 'partial', 'received', 'closed'], true)) { $where[] = 'po.status=?'; $p[] = $view; }
if ($q !== '') { $where[] = '(po.number LIKE ? OR s.name LIKE ? OR po.reference LIKE ?)'; array_push($p, ltrim($q, '#PO') . '%', "%$q%", "%$q%"); }
$rows = t_rows("SELECT po.*, s.name supplier, l.name dest,
  (SELECT COALESCE(SUM(quantity*cost*(1+tax_rate/100)),0) FROM purchase_order_items i WHERE i.purchase_order_id=po.id) items_total,
  (SELECT COALESCE(SUM(received),0) FROM purchase_order_items i WHERE i.purchase_order_id=po.id) rec,
  (SELECT COALESCE(SUM(quantity),0) FROM purchase_order_items i WHERE i.purchase_order_id=po.id) qty
  FROM purchase_orders po LEFT JOIN suppliers s ON s.id=po.supplier_id LEFT JOIN locations l ON l.id=po.destination_id WHERE " . implode(' AND ', $where) . " ORDER BY po.id DESC LIMIT 250", $p);
$any = (int)t_val("SELECT COUNT(*) FROM purchase_orders");
echo topbar('po', [['Purchase orders', null]], $any ? '<a class="btn primary" href="' . h(url('purchase_orders/new')) . '">Create purchase order</a>' : '');
?>
<div class="page">
  <?= filter_bar(['all' => 'All', 'draft' => 'Draft', 'ordered' => 'Ordered', 'partial' => 'Partially received', 'received' => 'Received', 'closed' => 'Closed'], $view, $q, 'poTable') ?>
  <?php if (!$any): ?>
    <?= empty_state('<svg viewBox="0 0 200 190"><circle cx="118" cy="95" r="90" fill="#d9a352"/><path d="M118 5a90 90 0 0 1 0 180z" fill="#c78c3a"/><rect x="132" y="40" width="14" height="14" fill="#e8c27e"/><rect x="150" y="110" width="16" height="16" fill="#fff"/><path d="M18 24h88l20 20v130H18z" fill="#fff" stroke="#e3e3e3"/><path d="M106 24v20h20" fill="#e6e6e6"/><rect x="30" y="40" width="34" height="30" rx="4" fill="#f1f1f1"/><path d="M36 60h22l-4-8h-14z" fill="#3c6e71"/><rect x="30" y="82" width="34" height="30" rx="4" fill="#f1f1f1"/><path d="M38 88h18l4 6-4 2v12H40V96l-4-2z" fill="#5b9ea0"/><rect x="30" y="124" width="34" height="30" rx="4" fill="#f1f1f1"/><path d="M47 132a6 6 0 0 1 6 6v8H41v-8a6 6 0 0 1 6-6z" fill="#3c6e71"/><path d="M74 50h36M74 58h26M74 92h36M74 100h26M74 134h36M74 142h26" stroke="#e1e1e1" stroke-width="4" stroke-linecap="round"/></svg>',
        'Manage your purchase orders', 'Track and receive inventory ordered from suppliers.', 'Create purchase order', 'purchase_orders/new') ?>
  <?php else: ?>
  <table class="grid" id="poTable">
    <thead><tr><th>Purchase order</th><th>Supplier</th><th>Destination</th><th>Status</th><th>Received</th><th>Expected arrival</th><th class="num">Total</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr class="empty-row"><td colspan="7">No purchase orders found</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr data-href="<?= h(url('purchase_orders/' . $r['id'])) ?>">
        <td class="strong">#PO<?= (int)$r['number'] ?></td><td><?= h($r['supplier']) ?></td><td><?= h($r['dest']) ?></td><td><?= status_pill($r['status']) ?></td>
        <td><?= (int)$r['rec'] ?> of <?= (int)$r['qty'] ?></td><td><?= $r['expected_arrival'] ? h(date('M j, Y', strtotime($r['expected_arrival']))) : '' ?></td>
        <td class="num"><?= h(money($r['items_total'] + $r['shipping'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <?= learn_more('purchase orders') ?>
</div>
