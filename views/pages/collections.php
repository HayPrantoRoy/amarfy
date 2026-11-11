<?php
$PAGE_TITLE = 'Collections';
$view = $_GET['view'] ?? 'all';
$q = trim((string)($_GET['q'] ?? ''));
$where = ['1=1']; $p = [];
if (in_array($view, ['manual', 'smart'], true)) { $where[] = 'type=?'; $p[] = $view; }
if ($q !== '') { $where[] = 'title LIKE ?'; $p[] = "%$q%"; }
$rows = t_rows("SELECT * FROM collections WHERE " . implode(' AND ', $where) . " ORDER BY title LIMIT 250", $p);
$fieldLabel = ['title' => 'Title', 'type' => 'Product type', 'vendor' => 'Vendor', 'tag' => 'Tag', 'category' => 'Category', 'price' => 'Price', 'compare_at' => 'Compare-at price', 'stock' => 'Inventory stock', 'weight' => 'Weight'];
$relLabel = ['equals' => 'is equal to', 'not_equals' => 'is not equal to', 'gt' => 'is greater than', 'lt' => 'is less than', 'starts' => 'starts with', 'ends' => 'ends with', 'contains' => 'contains', 'not_contains' => 'does not contain'];
echo topbar('collections', [['Collections', null]], '<a class="btn primary" href="' . h(url('collections/new')) . '">Add collection</a>');
?>
<div class="page">
  <?= filter_bar(['all' => 'All', 'manual' => 'Manual', 'smart' => 'Smart'], $view, $q, 'collTable') ?>
  <div class="bulk-bar" id="collBulk" hidden><b data-selcount></b><button class="btn danger" data-bulk-action="delete" data-confirm="Delete collections?">Delete collections</button></div>
  <div class="table-wrap">
  <table class="grid" id="collTable" data-bulk="collBulk" data-endpoint="collections/bulk">
    <thead><tr><th class="w-cb"><input type="checkbox" class="cb"></th><th style="width:52px"></th><th>Title</th><th class="num">Products</th><th>Product conditions</th><th class="num">Sales channels</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr class="empty-row"><td colspan="6">No collections yet. <a class="link" href="<?= h(url('collections/new')) ?>">Add collection</a></td></tr><?php endif; ?>
    <?php foreach ($rows as $c):
        $n = count(collection_product_ids($c));
        $conds = $c['type'] === 'smart' ? (json_decode((string)$c['conditions_json'], true) ?: []) : []; ?>
      <tr data-href="<?= h(url('collections/' . $c['id'])) ?>">
        <td class="w-cb"><input type="checkbox" class="cb" value="<?= (int)$c['id'] ?>"></td>
        <td><?= thumb($c['image_path']) ?></td>
        <td class="strong"><?= h($c['title']) ?></td>
        <td class="num"><?= $n ?></td>
        <td><?php foreach ($conds as $cd): ?><div><?= h(($fieldLabel[$cd['field']] ?? $cd['field']) . ' ' . ($relLabel[$cd['rel']] ?? $cd['rel']) . ' ' . (in_array($cd['field'], ['price', 'compare_at'], true) ? money($cd['value']) : $cd['value'])) ?></div><?php endforeach; ?></td>
        <td class="num"><?= (int)$c['channels'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?= learn_more('collections') ?>
</div>
