<?php
$p = null;
if (is_int($id)) {
    $p = t_row("SELECT * FROM products WHERE id=?", [$id]);
    if (!$p) { echo topbar('products', [['Products', 'products']], '', 'products') . '<div class="page-pad">' . empty_state('', 'Product not found', 'It may have been deleted.', 'Back to products', 'products') . '</div>'; return; }
}
$PAGE_TITLE = $p ? $p['title'] : 'Add product';
$locs = locations();
$variants = [];
if ($p) {
    foreach (t_rows("SELECT * FROM variants WHERE product_id=? ORDER BY position, id", [$id]) as $v) {
        $inv = [];
        foreach (t_rows("SELECT * FROM inventory_levels WHERE variant_id=?", [(int)$v['id']]) as $l) $inv[$l['location_id']] = (int)$l['available'];
        $variants[] = ['id' => (int)$v['id'], 'title' => $v['title'], 'option1' => $v['option1'], 'option2' => $v['option2'], 'option3' => $v['option3'], 'price' => (float)$v['price'],
            'compare_at_price' => $v['compare_at_price'], 'cost' => $v['cost'], 'unit_price' => $v['unit_price'], 'sku' => $v['sku'], 'barcode' => $v['barcode'], 'sell_out_of_stock' => (int)$v['sell_out_of_stock'], 'inventory' => (object)$inv];
    }
}
$isGift = $p ? (int)$p['is_gift_card'] : (int)(($_GET['type'] ?? '') === 'gift_card');
$state = [
    'id' => $p ? (int)$p['id'] : 0,
    'title' => $p['title'] ?? ($isGift ? 'Gift Card' : ''),
    'description_html' => $p['description_html'] ?? '',
    'status' => $p['status'] ?? 'active',
    'category' => $p['category'] ?? '', 'product_type' => $p['product_type'] ?? ($isGift ? 'Gift card' : ''), 'vendor' => $p['vendor'] ?? '',
    'theme_template' => $p['theme_template'] ?? 'Default product',
    'tags' => $p ? array_values(array_filter(explode(',', (string)$p['tags']))) : [],
    'options' => $p ? (json_decode((string)$p['options_json'], true) ?: []) : [],
    'is_gift_card' => $isGift,
    'charge_tax' => (int)($p['charge_tax'] ?? 1), 'track_inventory' => (int)($p['track_inventory'] ?? ($isGift ? 0 : 1)), 'requires_shipping' => (int)($p['requires_shipping'] ?? ($isGift ? 0 : 1)),
    'weight' => (float)($p['weight'] ?? 0), 'weight_unit' => $p['weight_unit'] ?? 'kg', 'length' => (float)($p['length'] ?? 0), 'width' => (float)($p['width'] ?? 0), 'height' => (float)($p['height'] ?? 0), 'dim_unit' => $p['dim_unit'] ?? 'cm',
    'package' => $p['package'] ?? '', 'origin_country' => $p['origin_country'] ?? '', 'hs_code' => $p['hs_code'] ?? '',
    'metafields' => $p ? (json_decode((string)$p['metafields_json'], true) ?: []) : [],
    'purchase_options' => $p ? (json_decode((string)$p['purchase_options_json'], true) ?: []) : [],
    'seo_title' => $p['seo_title'] ?? '', 'seo_description' => $p['seo_description'] ?? '', 'handle' => $p['handle'] ?? '',
    'media' => $p ? array_map(fn($m) => ['id' => $m['file_id'], 'path' => $m['path']], t_rows("SELECT * FROM product_media WHERE product_id=? ORDER BY position, id", [$id])) : [],
    'variants' => $variants ?: [['id' => 0, 'title' => 'Default Title', 'price' => 0, 'compare_at_price' => null, 'cost' => null, 'unit_price' => null, 'sku' => '', 'barcode' => '', 'sell_out_of_stock' => 0, 'inventory' => (object)[]]],
    'collections' => $p ? array_map(fn($r) => (int)$r['collection_id'], t_rows("SELECT collection_id FROM collection_products WHERE product_id=?", [$id])) : [],
    'all_collections' => t_rows("SELECT id, title, type FROM collections ORDER BY title"),
    'locations' => array_map(fn($l) => ['id' => (int)$l['id'], 'name' => $l['name']], $locs),
    'from_inventory' => !empty($_GET['fromInventory']),
    'store_url' => 'https://' . slugify(setting('store_name', 'store')) . '.amarfy.com',
    'store_name' => setting('store_name', ''),
    'prev' => $p ? t_val("SELECT id FROM products WHERE id < ? ORDER BY id DESC LIMIT 1", [$id]) : null,
    'next' => $p ? t_val("SELECT id FROM products WHERE id > ? ORDER BY id LIMIT 1", [$id]) : null,
];
$actions = '';
if ($p) $actions = '<button class="btn" id="pViewAs" type="button">' . icon('eye') . 'View as</button><button class="btn" id="pPreview" type="button">Preview</button><button class="btn" id="pShare" type="button">Share</button><button class="btn" id="pMore" type="button">More actions ' . icon('chev-down') . '</button>'
    . '<span class="btn-group"><a class="btn' . ($state['prev'] ? '' : ' disabled') . '" href="' . h(url('products/' . $state['prev'])) . '">' . icon('chev-up') . '</a><a class="btn' . ($state['next'] ? '' : ' disabled') . '" href="' . h(url('products/' . $state['next'])) . '">' . icon('chev-down') . '</a></span>';
echo topbar('products', [['Products', 'products'], [$p ? $p['title'] : 'Add product', null]], $actions, $state['from_inventory'] ? 'products/inventory' : 'products');
?>
<div class="form-layout" id="productApp">
  <div data-main></div>
  <aside class="side" data-side></aside>
</div>
<script>window.PRODUCT = <?= json_encode($state, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php $GLOBALS['PAGE_SCRIPTS'][] = 'product.js'; ?>
