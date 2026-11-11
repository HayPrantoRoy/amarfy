<?php
$PAGE_TITLE = 'Gift cards';
$q = trim((string)($_GET['q'] ?? ''));
$view = $_GET['view'] ?? 'all';
$where = ['1=1']; $p = [];
if ($q !== '') { $where[] = "(g.code LIKE ? OR CONCAT(c.first_name,' ',c.last_name) LIKE ? OR g.recipient LIKE ?)"; array_push($p, "%$q%", "%$q%", "%$q%"); }
if ($view === 'active') $where[] = "g.status='active' AND (g.expires_on IS NULL OR g.expires_on >= CURDATE())";
if ($view === 'disabled') $where[] = "g.status='disabled'";
if ($view === 'expired') $where[] = "g.expires_on < CURDATE()";
$rows = t_rows("SELECT g.*, c.first_name, c.last_name, c.email FROM gift_cards g LEFT JOIN customers c ON c.id=g.customer_id WHERE " . implode(' AND ', $where) . " ORDER BY g.id DESC LIMIT 250", $p);
$bad = (int)t_val("SELECT COUNT(*) FROM gift_cards WHERE currency<>? AND status='active'", [cur()]);
echo topbar('gift', [['Gift cards', null]],
  '<a class="btn" href="' . h(url('products/new?type=gift_card')) . '">Add gift card product</a><a class="btn" href="' . h(url('products?q=gift')) . '">View gift card products</a><a class="btn" href="' . h(url('export/gift_cards')) . '">Export</a><a class="btn primary" href="' . h(url('gift_cards/new')) . '">Create gift card</a>');
?>
<div class="page">
  <?php if ($bad): ?><div class="banner warn"><?= icon('warn') ?><span><b><?= $bad ?> gift card<?= $bad > 1 ? 's' : '' ?> can't be redeemed</b>&nbsp; Gift cards affected by currency changes can't be redeemed. Create new gift cards to replace them.</span></div><?php endif; ?>
  <?= filter_bar(['all' => 'All', 'active' => 'Active', 'disabled' => 'Disabled', 'expired' => 'Expired'], $view, $q, 'gcTable') ?>
  <div class="bulk-bar" id="gcBulk" hidden><b data-selcount></b><button class="btn" data-bulk-action="disable">Disable</button><button class="btn" data-bulk-action="enable">Enable</button></div>
  <table class="grid" id="gcTable" data-bulk="gcBulk" data-endpoint="gift_cards/bulk">
    <thead><tr><th class="w-cb"><input type="checkbox" class="cb"></th><th>Code ending</th><th style="width:70px"></th><th>Status</th><th>Customer</th><th>Recipient</th><th>Created</th><th class="num">Current / Initial</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr class="empty-row"><td colspan="8">No gift cards found. <a class="link" href="<?= h(url('gift_cards/new')) ?>">Create gift card</a></td></tr><?php endif; ?>
    <?php foreach ($rows as $g): $expired = $g['expires_on'] && $g['expires_on'] < date('Y-m-d'); ?>
      <tr data-href="<?= h(url('gift_cards/' . $g['id'])) ?>">
        <td class="w-cb"><input type="checkbox" class="cb" value="<?= (int)$g['id'] ?>"></td>
        <td class="strong"><?= h(strtoupper(substr($g['code'], -4))) ?></td>
        <td><span style="display:inline-flex;gap:8px"><?= $g['currency'] !== cur() ? '<span style="color:#e3721c" title="Currency mismatch">' . icon('warn') . '</span>' : '' ?><?= $g['note'] ? '<span title="Has notes">' . icon('note') . '</span>' : '' ?></span></td>
        <td><?= $expired ? pill('Expired') : status_pill($g['status']) ?></td>
        <td><?= h(customer_name($g)) ?></td>
        <td><?= h($g['recipient'] ?: 'No recipient') ?></td>
        <td><?= h(friendly_date($g['created_at'])) ?></td>
        <td class="num"><?= h(money($g['balance']) . ' ' . $g['currency'] . ' / ' . money($g['initial_value']) . ' ' . $g['currency']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?= learn_more('gift cards') ?>
</div>
