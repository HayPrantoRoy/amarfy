<?php
$draft = null; $items = []; $customer = null;
if (is_int($id)) {
    $draft = t_row("SELECT * FROM draft_orders WHERE id=?", [$id]);
    if (!$draft) { echo topbar('drafts', [['Drafts', 'draft_orders']], '', 'draft_orders') . '<div class="page-pad">' . empty_state('', 'Draft order not found', 'It may have been deleted.', 'Back to drafts', 'draft_orders') . '</div>'; return; }
    $items = t_rows("SELECT * FROM draft_order_items WHERE draft_order_id=? ORDER BY id", [$id]);
    if ($draft['customer_id']) {
        $c = t_row("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id=c.id) orders_count FROM customers c WHERE id=?", [(int)$draft['customer_id']]);
        if ($c) $customer = ['id' => (int)$c['id'], 'name' => customer_name($c), 'first_name' => $c['first_name'], 'last_name' => $c['last_name'], 'email' => $c['email'], 'phone' => $c['phone'], 'company' => $c['company'], 'address1' => $c['address1'], 'address2' => $c['address2'], 'city' => $c['city'], 'zip' => $c['zip'], 'country' => $c['country'], 'orders_count' => (int)$c['orders_count']];
    }
}
// "Create order" from a company or customer page pre-selects the customer and links the company
if (!$draft && !empty($_GET['customer'])) {
    $c = t_row("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id=c.id) orders_count FROM customers c WHERE id=?", [(int)$_GET['customer']]);
    if ($c) $customer = ['id' => (int)$c['id'], 'name' => customer_name($c), 'first_name' => $c['first_name'], 'last_name' => $c['last_name'], 'email' => $c['email'], 'phone' => $c['phone'], 'company' => $c['company'], 'address1' => $c['address1'], 'address2' => $c['address2'], 'city' => $c['city'], 'zip' => $c['zip'], 'country' => $c['country'], 'orders_count' => (int)$c['orders_count']];
}
$companyId = $draft['company_id'] ?? (!empty($_GET['company']) && t_val("SELECT id FROM companies WHERE id=?", [(int)$_GET['company']]) ? (int)$_GET['company'] : null);
$PAGE_TITLE = $draft ? '#D' . $draft['number'] : 'Create order';
$state = [
    'id' => $draft ? (int)$draft['id'] : 0,
    'number' => $draft ? (int)$draft['number'] : null,
    'status' => $draft['status'] ?? 'open',
    'order_id' => $draft['order_id'] ?? null,
    'order_number' => !empty($draft['order_id']) ? (int)t_val("SELECT number FROM orders WHERE id=?", [(int)$draft['order_id']]) : null,
    'updated' => $draft ? 'Updated by ' . ($draft['updated_by'] ?: staff_name()) . ' ' . lcfirst(friendly_date($draft['updated_at'])) : '',
    'items' => array_map(fn($i) => ['variant_id' => $i['variant_id'] ? (int)$i['variant_id'] : null, 'product_id' => $i['product_id'], 'title' => $i['title'], 'variant_title' => $i['variant_title'], 'image' => $i['image'], 'price' => (float)$i['price'], 'quantity' => (int)$i['quantity'], 'taxable' => (int)$i['taxable'], 'requires_shipping' => (int)$i['requires_shipping']], $items),
    'customer' => $customer,
    'company_id' => $companyId ? (int)$companyId : null,
    'shipping_address' => $draft && $draft['shipping_address_json'] ? json_decode($draft['shipping_address_json'], true) : null,
    'tags' => $draft ? array_values(array_filter(explode(',', (string)$draft['tags']))) : [],
    'note' => $draft['note'] ?? '',
    'po_number' => $draft['po_number'] ?? '',
    'discount_type' => $draft['discount_type'] ?? null,
    'discount_value' => (float)($draft['discount_value'] ?? 0),
    'discount_reason' => $draft['discount_reason'] ?? '',
    'shipping_title' => $draft['shipping_title'] ?? '',
    'shipping_amount' => (float)($draft['shipping_amount'] ?? 0),
    'payment_due_later' => (int)($draft['payment_due_later'] ?? 0),
    'payment_terms' => $draft['payment_terms'] ?? 'receipt',
    'terms' => payment_terms_options(),
    'market' => setting('market', 'Bangladesh'),
    'prev' => $draft ? t_val("SELECT id FROM draft_orders WHERE id < ? ORDER BY id DESC LIMIT 1", [$id]) : null,
    'next' => $draft ? t_val("SELECT id FROM draft_orders WHERE id > ? ORDER BY id LIMIT 1", [$id]) : null,
];
$actions = '';
if ($draft) {
    $actions = '<button class="btn" id="dDuplicate" type="button">Duplicate</button><button class="btn" id="dShare" type="button">Share</button><button class="btn" id="dMore" type="button">More actions ' . icon('chev-down') . '</button>'
        . prev_next('draft_orders', $state['prev'], $state['next']);
}
echo topbar('drafts', $draft ? [['Drafts', 'draft_orders'], ['#D' . $draft['number'], null]] : [['Drafts', 'draft_orders']], $actions, 'draft_orders');
?>
<div class="form-layout" id="draftApp">
  <div>
    <h1 class="page-h1" style="margin-bottom:<?= $draft ? '4px' : '24px' ?>"><?= $draft ? '#D' . (int)$draft['number'] : 'Create order' ?> <?= $draft && $draft['status'] !== 'open' ? status_pill($draft['status']) : '' ?></h1>
    <?php if ($draft): ?><p class="muted" style="font-size:14px;margin-bottom:26px"><?= h($state['updated']) ?></p><?php endif; ?>
    <?php if ($state['order_id']): ?><div class="banner info"><?= icon('info') ?>This draft order was completed. <a class="link" href="<?= h(url('orders/' . $state['order_id'])) ?>">View order #<?= (int)$state['order_number'] ?></a></div><?php endif; ?>
    <div data-products></div>
    <div data-payment></div>
    <?php if ($draft): ?><div class="sec-head" style="margin-top:36px"><h2>Timeline</h2></div><?= timeline_html('draft', (int)$draft['id']) ?><?php endif; ?>
  </div>
  <aside class="side" data-side></aside>
</div>
<script>window.DRAFT = <?= json_encode($state, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php $GLOBALS['PAGE_SCRIPTS'][] = 'timeline.js'; $GLOBALS['PAGE_SCRIPTS'][] = 'draft.js'; ?>
