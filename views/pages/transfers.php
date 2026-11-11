<?php
$PAGE_TITLE = 'Transfers';
$view = $_GET['view'] ?? 'all';
$q = trim((string)($_GET['q'] ?? ''));
$where = ['1=1']; $p = [];
if (in_array($view, ['draft', 'in_transit', 'received'], true)) { $where[] = 't.status=?'; $p[] = $view; }
if ($q !== '') { $where[] = '(t.number LIKE ? OR t.reference LIKE ?)'; array_push($p, ltrim($q, '#T') . '%', "%$q%"); }
$rows = t_rows("SELECT t.*, o.name origin, d.name dest, (SELECT COALESCE(SUM(quantity),0) FROM transfer_items i WHERE i.transfer_id=t.id) qty
  FROM transfers t LEFT JOIN locations o ON o.id=t.origin_id LEFT JOIN locations d ON d.id=t.destination_id WHERE " . implode(' AND ', $where) . " ORDER BY t.id DESC LIMIT 250", $p);
$any = (int)t_val("SELECT COUNT(*) FROM transfers");
echo topbar('transfer', [['Transfers', null]], $any ? '<a class="btn primary" href="' . h(url('transfers/new')) . '">Create transfer</a>' : '');
?>
<div class="page">
  <?php if (!$any): ?>
  <div class="card" style="margin-top:4px">
    <?= empty_state('<svg viewBox="0 0 220 190"><circle cx="110" cy="95" r="90" fill="#f1f1f1"/><rect x="30" y="30" width="72" height="135" rx="4" fill="#fff" stroke="#e3e3e3"/><rect x="118" y="30" width="72" height="135" rx="4" fill="#fff" stroke="#e3e3e3"/><path d="M42 48h20M130 48h20" stroke="#bdbdbd" stroke-width="6" stroke-linecap="round"/><path d="M84 40c0-6 10-6 10 0 0 5-5 10-5 10s-5-5-5-10z" fill="#5b9ea0"/><path d="M172 40c0-6 10-6 10 0 0 5-5 10-5 10s-5-5-5-10z" fill="#2f7a5b"/><path d="M45 72h40l-4 50H49z" fill="#a8a8a8"/><path d="M133 72h40l-4 50h-32z" fill="#5b9ea0"/><rect x="50" y="135" width="18" height="16" rx="3" fill="#ececec"/><rect x="146" y="135" width="18" height="16" rx="3" fill="#ececec"/><text x="59" y="147" font-size="10" text-anchor="middle" fill="#555" font-family="Inter">6</text><text x="155" y="147" font-size="10" text-anchor="middle" fill="#555" font-family="Inter">4</text><circle cx="110" cy="97" r="24" fill="#2f7a5b"/><path d="M98 97h22M112 88l9 9-9 9" stroke="#fff" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'Move inventory between locations', 'Move and track inventory between your business locations.', 'Create transfer', 'transfers/new') ?>
  </div>
  <?php else: ?>
  <?= filter_bar(['all' => 'All', 'draft' => 'Draft', 'in_transit' => 'In transit', 'received' => 'Received'], $view, $q, 'trTable') ?>
  <table class="grid" id="trTable">
    <thead><tr><th>Transfer</th><th>Origin</th><th>Destination</th><th>Status</th><th>Date</th><th>Reference</th><th class="num">Quantity</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr class="empty-row"><td colspan="7">No transfers found</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr data-href="<?= h(url('transfers/' . $r['id'])) ?>"><td class="strong">#T<?= (int)$r['number'] ?></td><td><?= h($r['origin']) ?></td><td><?= h($r['dest']) ?></td><td><?= status_pill($r['status']) ?></td><td><?= $r['transfer_date'] ? h(date('M j, Y', strtotime($r['transfer_date']))) : '' ?></td><td><?= h($r['reference']) ?></td><td class="num"><?= (int)$r['qty'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <?= learn_more('transfers') ?>
</div>
