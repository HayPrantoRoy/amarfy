<?php
$PAGE_TITLE = 'Customers';
$q = trim((string)($_GET['q'] ?? ''));
$where = ['1=1']; $p = [];
if ($q !== '') { $where[] = "(CONCAT(c.first_name,' ',c.last_name) LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.tags LIKE ?)"; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%"); }
$rows = t_rows("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id=c.id AND o.cancelled_at IS NULL) n, (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.customer_id=c.id AND o.cancelled_at IS NULL) spent
  FROM customers c WHERE " . implode(' AND ', $where) . " ORDER BY c.id DESC LIMIT 250", $p);
$total = (int)t_val("SELECT COUNT(*) FROM customers");
echo topbar('customers', [['Customers', null]], '<a class="btn" href="' . h(url('export/customers')) . '">Export</a><a class="btn primary" href="' . h(url('customers/new')) . '">Add customer</a>');
?>
<div class="page">
  <div class="metrics-strip"><div class="range" style="padding-left:6px"><b><?= $total ?></b>&nbsp;customers</div><div class="metric"><div class="m-label"><span>100% of your customer base</span></div></div></div>
  <?= filter_bar([], '', $q, 'custTable') ?>
  <div class="bulk-bar" id="custBulk" hidden><b data-selcount></b><button class="btn danger" data-bulk-action="delete" data-confirm="Delete customers?">Delete customers</button></div>
  <table class="grid" id="custTable" data-bulk="custBulk" data-endpoint="customers/bulk">
    <thead><tr><th class="w-cb"><input type="checkbox" class="cb"></th><th>Customer name</th><th>Email subscription</th><th>Location</th><th>Orders</th><th class="num">Amount spent</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr class="empty-row"><td colspan="6">No customers found. <a class="link" href="<?= h(url('customers/new')) ?>">Add customer</a></td></tr><?php endif; ?>
    <?php foreach ($rows as $c): ?>
      <tr data-href="<?= h(url('customers/' . $c['id'])) ?>"><td class="w-cb"><input type="checkbox" class="cb" value="<?= (int)$c['id'] ?>"></td><td class="strong"><?= h(customer_name($c)) ?></td>
        <td><?= $c['accepts_marketing'] ? pill('Subscribed', 'green') : pill('Not subscribed') ?></td><td><?= h(trim(($c['city'] ?? '') . ', ' . ($c['country'] ?? ''), ', ')) ?></td><td><?= (int)$c['n'] ?> order<?= (int)$c['n'] === 1 ? '' : 's' ?></td><td class="num"><?= h(money($c['spent'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?= learn_more('customers') ?>
</div>
