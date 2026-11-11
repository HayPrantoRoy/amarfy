<?php
$PAGE_TITLE = 'Drafts';
$view = $_GET['view'] ?? 'all';
$q = trim((string)($_GET['q'] ?? ''));
$sort = in_array($_GET['sort'] ?? '', ['date', 'number', 'total'], true) ? $_GET['sort'] : 'date';
$dir = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$where = ['1=1']; $p = [];
if (in_array($view, ['open', 'invoice_sent', 'completed'], true)) { $where[] = 'd.status=?'; $p[] = $view; }
if ($q !== '') { $where[] = "(d.number LIKE ? OR CONCAT(c.first_name,' ',c.last_name) LIKE ? OR d.po_number LIKE ?)"; array_push($p, preg_replace('/^#?d/i', '', $q) . '%', "%$q%", "%$q%"); }
$ob = ['date' => 'd.created_at', 'number' => 'd.number', 'total' => 'd.id'][$sort];
$rows = t_rows("SELECT d.*, c.first_name, c.last_name, c.email, c.company FROM draft_orders d LEFT JOIN customers c ON c.id=d.customer_id WHERE " . implode(' AND ', $where) . " ORDER BY $ob $dir LIMIT 250", $p);
echo topbar('drafts', [['Drafts', null]], '<a class="btn" href="' . h(url('export/drafts')) . '">Export</a><a class="btn primary" href="' . h(url('draft_orders/new')) . '">Create order</a>');
?>
<div class="page">
  <?= filter_bar(['all' => 'All', 'open' => 'Open', 'invoice_sent' => 'Invoice sent', 'completed' => 'Completed'], $view, $q, 'draftsTable') ?>
  <div class="bulk-bar" id="draftsBulk" hidden><b data-selcount></b><button class="btn danger" data-bulk-action="delete" data-confirm="Delete draft orders?">Delete drafts</button></div>
  <div class="table-wrap">
  <table class="grid" id="draftsTable" data-bulk="draftsBulk" data-endpoint="drafts/bulk">
    <thead><tr><th class="w-cb"><input type="checkbox" class="cb"></th><th><?= sort_link('Draft order', 'number', $sort, strtolower($dir)) ?></th><th>PO number</th><th><?= sort_link('Date', 'date', $sort, strtolower($dir)) ?></th><th>Customer</th><th>Status</th><th class="num">Total</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr class="empty-row"><td colspan="7">No draft orders found. <a class="link" href="<?= h(url('draft_orders/new')) ?>">Create order</a></td></tr><?php endif; ?>
    <?php foreach ($rows as $d):
        $items = t_rows("SELECT price, quantity FROM draft_order_items WHERE draft_order_id=?", [(int)$d['id']]);
        $t = calc_totals($items, $d['discount_type'], (float)$d['discount_value'], (float)$d['shipping_amount']); ?>
      <tr data-href="<?= h(url('draft_orders/' . $d['id'])) ?>">
        <td class="w-cb"><input type="checkbox" class="cb" value="<?= (int)$d['id'] ?>"></td>
        <td class="strong">#D<?= (int)$d['number'] ?></td>
        <td><?= h($d['po_number']) ?></td>
        <td><?= h(friendly_date($d['created_at'])) ?></td>
        <td><?php if ($d['customer_id']): ?><?= h(customer_name($d)) ?><?php if ($d['company']): ?><div class="sub-line"><?= h($d['company']) ?></div><?php endif; ?><?php else: ?><span class="muted">No customer</span><?php endif; ?></td>
        <td><?= status_pill($d['status']) ?></td>
        <td class="num"><?= h(money($t['total'], true)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?= learn_more('creating draft orders') ?>
</div>
