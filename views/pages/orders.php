<?php
$PAGE_TITLE = 'Orders';
$view = $_GET['view'] ?? 'all';
$q = trim((string)($_GET['q'] ?? ''));
$loc = (int)($_GET['location'] ?? 0);
$sort = in_array($_GET['sort'] ?? '', ['date', 'number', 'total', 'customer'], true) ? $_GET['sort'] : 'date';
$dir = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$where = ['1=1']; $p = [];
switch ($view) {
    case 'unfulfilled': $where[] = "o.fulfillment_status='unfulfilled' AND o.cancelled_at IS NULL AND o.archived=0"; break;
    case 'unpaid': $where[] = "o.payment_status='pending' AND o.cancelled_at IS NULL"; break;
    case 'open': $where[] = "o.archived=0 AND o.cancelled_at IS NULL"; break;
    case 'archived': $where[] = "o.archived=1"; break;
    default: $where[] = "o.archived=0";
}
if ($loc) { $where[] = 'o.location_id=?'; $p[] = $loc; }
if (!empty($_GET['company'])) {   // orders for a B2B company (linked directly or placed by one of its contacts)
    $cids = company_customer_ids((int)$_GET['company']);
    $where[] = '(o.company_id=? OR o.customer_id IN (' . ($cids ? implode(',', $cids) : '0') . '))'; $p[] = (int)$_GET['company'];
}
if ($q !== '') { $where[] = "(o.number LIKE ? OR CONCAT(c.first_name,' ',c.last_name) LIKE ? OR c.email LIKE ? OR o.tags LIKE ?)"; array_push($p, ltrim($q, '#') . '%', "%$q%", "%$q%", "%$q%"); }
$order = ['date' => 'o.created_at', 'number' => 'o.number', 'total' => 'o.total', 'customer' => 'c.first_name'][$sort];
$rows = t_rows("SELECT o.*, c.first_name, c.last_name, c.email, (SELECT COALESCE(SUM(quantity),0) FROM order_items i WHERE i.order_id=o.id) items
  FROM orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE " . implode(' AND ', $where) . " ORDER BY $order $dir LIMIT 250", $p);

$today = t_row("SELECT COUNT(*) n, COALESCE(SUM((SELECT SUM(quantity) FROM order_items i WHERE i.order_id=o.id)),0) items,
  COALESCE(SUM(CASE WHEN payment_status IN ('refunded','voided') THEN total ELSE 0 END),0) rev,
  COALESCE(SUM(fulfillment_status='fulfilled'),0) ful FROM orders o WHERE DATE(created_at)=CURDATE()");
$avgHours = t_val("SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, fulfilled_at))/60 FROM orders WHERE fulfilled_at IS NOT NULL AND DATE(created_at)=CURDATE()");
$spark = '<svg class="spark" viewBox="0 0 80 16"><path d="M0 14H80" stroke="#4b8fe2" stroke-width="1.5" fill="none"/></svg>';
$locs = locations();
$locSel = '<label class="top-select"><select onchange="location.href=\'?location=\'+this.value"><option value="0">All locations</option>';
foreach ($locs as $l) $locSel .= '<option value="' . $l['id'] . '"' . ($loc === (int)$l['id'] ? ' selected' : '') . '>' . h($l['name']) . '</option>';
$locSel .= '</select>' . icon('updown') . '</label>';
echo topbar('orders', [['Orders', null]], '<a class="btn" href="' . h(url('export/orders')) . '">Export</a><a class="btn primary" href="' . h(url('draft_orders/new')) . '">Create order</a>', null, $locSel);
?>
<div class="page">
  <div class="metrics-strip">
    <div class="range"><?= icon('calendar') ?>Today</div>
    <div class="metric"><div class="m-label"><span>Orders</span></div><div class="m-val"><?= (int)$today['n'] ?></div><?= $spark ?></div>
    <div class="metric"><div class="m-label"><span>Items ordered</span></div><div class="m-val"><?= (int)$today['items'] ?></div><?= $spark ?></div>
    <div class="metric"><div class="m-label"><span>Sales reversals</span></div><div class="m-val"><?= h(cur()) ?> <?= number_format((float)$today['rev'], 0) ?></div><?= $spark ?></div>
    <div class="metric"><div class="m-label"><span>Orders fulfilled</span></div><div class="m-val"><?= (int)$today['ful'] ?></div><?= $spark ?></div>
    <div class="metric"><div class="m-label"><span>Orders delivered</span></div><div class="m-val">0</div><?= $spark ?></div>
    <div class="metric"><div class="m-label"><span>Order to fulfill</span></div><div class="m-val"><?= $avgHours !== null ? number_format((float)$avgHours, 1) . ' hrs' : '—' ?></div></div>
  </div>

  <?= filter_bar(['all' => 'All', 'unfulfilled' => 'Unfulfilled', 'unpaid' => 'Unpaid', 'open' => 'Open', 'archived' => 'Archived'], $view, $q, 'ordersTable') ?>
  <div class="bulk-bar" id="ordersBulk" hidden><b data-selcount></b>
    <button class="btn" data-bulk-action="mark_paid">Mark as paid</button>
    <button class="btn" data-bulk-action="fulfill">Mark as fulfilled</button>
    <?php if ($view === 'archived'): ?><button class="btn" data-bulk-action="unarchive">Unarchive orders</button><?php else: ?><button class="btn" data-bulk-action="archive">Archive orders</button><?php endif; ?>
  </div>
  <div class="table-wrap">
  <table class="grid" id="ordersTable" data-bulk="ordersBulk" data-endpoint="orders/bulk">
    <thead><tr><th class="w-cb"><input type="checkbox" class="cb"></th><th><?= sort_link('Order', 'number', $sort, strtolower($dir)) ?></th><th><?= sort_link('Date', 'date', $sort, strtolower($dir)) ?></th><th><?= sort_link('Customer', 'customer', $sort, strtolower($dir)) ?></th><th>Fulfill by</th><th>Channel</th><th class="num"><?= sort_link('Total', 'total', $sort, strtolower($dir)) ?></th><th>Payment status</th><th>Fulfillment status</th><th>Items</th><th>Delivery status</th><th>Delivery method</th><th>Tags</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr class="empty-row"><td colspan="13"><?= $q !== '' || $view !== 'all' ? 'No orders found' : 'Your orders will show here. <a class="link" href="' . h(url('draft_orders/new')) . '">Create an order</a>' ?></td></tr><?php endif; ?>
    <?php foreach ($rows as $o):
        $fb = '';
        if ($o['fulfill_by'] && $o['fulfillment_status'] === 'unfulfilled') {
            $days = (int)floor((strtotime($o['fulfill_by']) - strtotime('today')) / 86400);
            $fb = '<span style="color:#8a4b00">' . ($days < 0 ? abs($days) . ' days ago' : ($days === 0 ? 'Today' : 'In ' . $days . ' days')) . '</span>';
        } ?>
      <tr data-href="<?= h(url('orders/' . $o['id'])) ?>">
        <td class="w-cb"><input type="checkbox" class="cb" value="<?= (int)$o['id'] ?>"></td>
        <td class="strong">#<?= (int)$o['number'] ?><?= $o['cancelled_at'] ? ' <span class="muted">(cancelled)</span>' : '' ?></td>
        <td><?= h(friendly_date($o['created_at'])) ?></td>
        <td><?= h(customer_name($o) ?: 'No customer') ?></td>
        <td><?= $fb ?></td>
        <td><?= h($o['channel']) ?></td>
        <td class="num"><?= h(money($o['total'])) ?></td>
        <td><?= status_pill($o['payment_status']) ?></td>
        <td><?= status_pill($o['fulfillment_status']) ?></td>
        <td><?= (int)$o['items'] ?> item<?= (int)$o['items'] === 1 ? '' : 's' ?></td>
        <td><?= h($o['delivery_status'] ?? '') ?></td>
        <td><?= h($o['delivery_method'] ?? '') ?></td>
        <td><?= h(str_replace(',', ', ', (string)$o['tags'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?= learn_more('orders') ?>
</div>
