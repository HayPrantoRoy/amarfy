<?php
$PAGE_TITLE = 'Companies';
$view = $_GET['view'] ?? 'all';
$q = trim((string)($_GET['q'] ?? ''));
$where = ['1=1']; $p = [];
if ($view === 'approved') $where[] = 'co.ordering_approved=1';
if ($view === 'not_approved') $where[] = 'co.ordering_approved=0';
if ($q !== '') { $where[] = '(co.name LIKE ? OR co.external_id LIKE ?)'; array_push($p, "%$q%", "%$q%"); }
$rows = t_rows("SELECT co.*, c.first_name, c.last_name, c.email, (SELECT COUNT(*) FROM company_locations l WHERE l.company_id=co.id) locs
  FROM companies co LEFT JOIN customers c ON c.id=co.main_contact_id WHERE " . implode(' AND ', $where) . " ORDER BY co.name LIMIT 250", $p);
echo topbar('building', [['Companies', null]], '<a class="btn primary" href="' . h(url('companies/new')) . '">Add company</a>');
?>
<div class="page">
  <?php if (!setting('b2b_banner_dismissed')): ?>
  <div class="banner info" id="b2bBanner" style="align-items:flex-start"><?= icon('info') ?><div style="flex:1"><b>Selling to businesses</b><div style="margin-top:4px">Assign B2B orders to a company, not just an individual customer, so payment terms, locations and sales stay together. Create the order from the company page to link it automatically.</div></div><button class="icon-btn" type="button" id="b2bDismiss" aria-label="Dismiss"><?= icon('x') ?></button></div>
  <?php endif; ?>
  <?= filter_bar(['all' => 'All', 'approved' => 'Ordering approved', 'not_approved' => 'Not approved'], $view, $q, 'coTable') ?>
  <div class="bulk-bar" id="coBulk" hidden><b data-selcount></b><button class="btn" data-bulk-action="approve">Approve ordering</button><button class="btn" data-bulk-action="unapprove">Revoke approval</button><button class="btn danger" data-bulk-action="delete" data-confirm="Delete companies?">Delete</button></div>
  <table class="grid" id="coTable" data-bulk="coBulk" data-endpoint="companies/bulk">
    <thead><tr><th class="w-cb"><input type="checkbox" class="cb"></th><th>Company</th><th>Ordering</th><th>Locations</th><th>Main contact</th><th>Total orders</th><th class="num">Total sales</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr class="empty-row"><td colspan="7">No companies yet. <a class="link" href="<?= h(url('companies/new')) ?>">Add company</a></td></tr><?php endif; ?>
    <?php foreach ($rows as $co): $st = company_stats((int)$co['id']); ?>
      <tr data-href="<?= h(url('companies/' . $co['id'])) ?>">
        <td class="w-cb"><input type="checkbox" class="cb" value="<?= (int)$co['id'] ?>"></td>
        <td class="strong"><?= h($co['name']) ?></td>
        <td><?= $co['ordering_approved'] ? pill('Approved', 'green', true) : pill('Not approved') ?></td>
        <td><?= (int)$co['locs'] ?> location<?= (int)$co['locs'] === 1 ? '' : 's' ?></td>
        <td><?= $co['main_contact_id'] ? h(customer_name($co)) : '<span class="muted">No main contact</span>' ?></td>
        <td><?= $st['orders'] ?> order<?= $st['orders'] === 1 ? '' : 's' ?></td>
        <td class="num"><?= h(money($st['sales'], true)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?= learn_more('companies') ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const b = document.getElementById('b2bDismiss');
  if (b) b.onclick = async () => { await UI.api('home/setting', {key: 'b2b_banner_dismissed'}); document.getElementById('b2bBanner').remove(); };
});
</script>
