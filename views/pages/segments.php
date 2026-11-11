<?php
$PAGE_TITLE = 'Segments';
$q = trim((string)($_GET['q'] ?? ''));
$dir = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$rows = t_rows("SELECT * FROM segments WHERE ? = '' OR name LIKE ? ORDER BY updated_at $dir, id $dir", [$q, "%$q%"]);
$total = (int)t_val("SELECT COUNT(*) FROM customers");
echo topbar('customers', [['Segments', null]], '<a class="btn primary" href="' . h(url('segments/new')) . '">Create segment</a>');
$logo = '<span class="logo" style="width:18px;height:20px;font-size:11px;border-radius:4px;display:inline-grid">a</span>';
?>
<div class="page">
  <form class="filter-bar" method="get">
    <label class="fsearch"><?= icon('search') ?><input name="q" value="<?= h($q) ?>" placeholder="Search segments" autocomplete="off"></label>
    <a class="icon-btn" title="Sort by last activity" href="?<?= h(http_build_query(['q' => $q, 'dir' => $dir === 'DESC' ? 'asc' : 'desc'])) ?>"><?= icon('sort') ?></a>
  </form>
  <div class="bulk-bar" id="segBulk" hidden><b data-selcount></b><button class="btn danger" data-bulk-action="delete" data-confirm="Delete segments?">Delete segments</button></div>
  <table class="grid" id="segTable" data-bulk="segBulk" data-endpoint="segments/bulk">
    <thead><tr><th class="w-cb"><input type="checkbox" class="cb"></th><th>Name</th><th class="num">% of customers</th><th><?= sort_link('Last activity', 'activity', 'activity', strtolower($dir)) ?></th><th>Created by</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr class="empty-row"><td colspan="5">No segments found</td></tr><?php endif; ?>
    <?php foreach ($rows as $s): $n = count(segment_customer_ids($s)); ?>
      <tr data-href="<?= h(url('segments/' . $s['id'])) ?>">
        <td class="w-cb"><input type="checkbox" class="cb" value="<?= (int)$s['id'] ?>"></td>
        <td class="strong"><?= h($s['name']) ?></td>
        <td class="num"><?= $total ? round($n / $total * 100) : 0 ?>%</td>
        <td><?= $s['updated_at'] > $s['created_at'] ? 'Edited on ' : 'Created on ' ?><?= h(date('M j, Y', strtotime($s['updated_at']))) ?></td>
        <td><span style="display:inline-flex;align-items:center;gap:8px"><?= $s['rule_key'] ? $logo . 'Amarfy' : '<span class="tl-avatar" style="width:20px;height:20px;flex-basis:20px;font-size:9px;border-radius:5px">' . h(initials($s['created_by'])) . '</span>' . h($s['created_by']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?= learn_more('segments') ?>
</div>
