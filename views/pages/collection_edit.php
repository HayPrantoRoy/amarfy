<?php
$c = null;
if (is_int($id)) {
    $c = t_row("SELECT * FROM collections WHERE id=?", [$id]);
    if (!$c) { echo topbar('collections', [['Collections', 'collections']], '', 'collections') . '<div class="page-pad">' . empty_state('', 'Collection not found', 'It may have been deleted.', 'Back to collections', 'collections') . '</div>'; return; }
}
$PAGE_TITLE = $c ? $c['title'] : 'Add collection';
$state = [
    'id' => $c ? (int)$c['id'] : 0,
    'title' => $c['title'] ?? '', 'description_html' => $c['description_html'] ?? '', 'image_path' => $c['image_path'] ?? '',
    'type' => $c['type'] ?? 'manual', 'match_all' => (int)($c['match_all'] ?? 1),
    'conditions' => $c ? (json_decode((string)$c['conditions_json'], true) ?: []) : [],
    'excluded' => $c ? (json_decode((string)$c['excluded_json'], true) ?: []) : [],
    'products' => $c && $c['type'] === 'manual' ? collection_product_ids($c) : [],
    'theme_template' => $c['theme_template'] ?? 'Default collection', 'channels' => (int)($c['channels'] ?? 3),
    'seo_title' => $c['seo_title'] ?? '', 'seo_description' => $c['seo_description'] ?? '', 'handle' => $c['handle'] ?? '',
    'store_url' => 'https://' . slugify(setting('store_name', 'store')) . '.amarfy.com', 'store_name' => setting('store_name', ''),
];
$actions = $c ? '<button class="btn" id="cView" type="button">' . icon('eye') . 'View</button><button class="btn" id="cMore" type="button">More actions ' . icon('chev-down') . '</button>' : '';
echo topbar('collections', [['Collections', 'collections'], [$c ? $c['title'] : 'Add collection', null]], $actions, 'collections');
?>
<div class="form-layout" id="collApp"><div data-main></div><aside class="side" data-side></aside></div>
<script>window.COLLECTION = <?= json_encode($state, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php $GLOBALS['PAGE_SCRIPTS'][] = 'collection.js'; ?>
