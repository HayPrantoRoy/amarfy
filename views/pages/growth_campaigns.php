<?php
$PAGE_TITLE = 'Campaigns';
$view = $_GET['view'] ?? 'all';
$q = trim((string)($_GET['q'] ?? ''));
$where = ['1=1']; $p = [];
if (in_array($view, ['draft', 'active', 'paused', 'completed'], true)) { $where[] = 'c.status=?'; $p[] = $view; }
if ($q !== '') { $where[] = '(c.name LIKE ? OR c.utm_campaign LIKE ?)'; array_push($p, "%$q%", "%$q%"); }
$rows = t_rows("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.campaign_id=c.id AND o.cancelled_at IS NULL) n,
  (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.campaign_id=c.id AND o.cancelled_at IS NULL) sales FROM campaigns c WHERE " . implode(' AND ', $where) . " ORDER BY c.id DESC", $p);
echo topbar('growth', [['Campaigns', null]], '<button class="btn primary" type="button" data-new-campaign>Create campaign</button>');
?>
<div class="page">
  <?= filter_bar(['all' => 'All', 'active' => 'Active', 'draft' => 'Draft', 'paused' => 'Paused', 'completed' => 'Completed'], $view, $q, 'cmpTable') ?>
  <table class="grid" id="cmpTable">
    <thead><tr><th>Campaign</th><th>Channel</th><th>Status</th><th>Dates</th><th class="num">Budget</th><th class="num">Orders</th><th class="num">Attributed sales</th><th class="num">ROAS</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr class="empty-row"><td colspan="9">No campaigns yet. Create one to start tracking marketing results.</td></tr><?php endif; ?>
    <?php foreach ($rows as $c): ?>
      <tr id="c<?= (int)$c['id'] ?>" data-c='<?= h(json_encode($c)) ?>'>
        <td class="strong"><?= h($c['name']) ?><div class="sub-line">utm_campaign=<?= h($c['utm_campaign']) ?></div></td>
        <td><?= h(ucfirst($c['channel'])) ?></td>
        <td><?= pill(ucfirst($c['status']), ['active' => 'green', 'paused' => 'yellow', 'draft' => 'blue'][$c['status']] ?? 'neutral') ?></td>
        <td><?= $c['starts_on'] ? h(date('M j, Y', strtotime($c['starts_on']))) . ($c['ends_on'] ? ' – ' . h(date('M j, Y', strtotime($c['ends_on']))) : '') : '—' ?></td>
        <td class="num"><?= h(money($c['budget'])) ?></td><td class="num"><?= (int)$c['n'] ?></td><td class="num"><?= h(money($c['sales'])) ?></td>
        <td class="num"><?= $c['budget'] > 0 ? number_format($c['sales'] / $c['budget'], 1) . '×' : '—' ?></td>
        <td class="num"><button class="icon-btn" type="button" data-cmenu><?= icon('dots') ?></button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?= learn_more('marketing') ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-new-campaign]').forEach(b => b.onclick = () => campaignModal());
  document.querySelectorAll('[data-cmenu]').forEach(b => b.onclick = e => {
    e.stopPropagation(); const c = JSON.parse(b.closest('tr').dataset.c);
    const st = s => UI.api('growth/campaign_status', {id: c.id, status: s}).then(() => location.reload());
    UI.popMenu(b, [{label: 'Edit', action: () => campaignModal(c)},
      c.status === 'active' ? {label: 'Pause', action: () => st('paused')} : {label: 'Activate', action: () => st('active')},
      {label: 'Mark completed', action: () => st('completed')}, '-',
      {label: 'Delete', danger: true, action: async () => { if (await UI.confirmBox('Delete campaign?', 'Orders keep their data but lose the campaign link.', 'Delete', true)) { await UI.api('growth/campaign_delete', {id: c.id}); location.reload(); } }}]);
  });
  document.querySelectorAll('#cmpTable tbody tr[data-c]').forEach(tr => tr.addEventListener('click', e => { if (!e.target.closest('button')) campaignModal(JSON.parse(tr.dataset.c)); }));
});
</script>
<?php $GLOBALS['PAGE_SCRIPTS'][] = 'campaigns.js'; ?>
