<?php
/** Things that need the merchant's attention (bell menu). */
if ($action === 'list') {
    $items = [];
    $add = function (int $n, string $text, string $href, string $tone = 'info') use (&$items) {
        if ($n > 0) $items[] = ['count' => $n, 'text' => $text, 'href' => $href, 'tone' => $tone];
    };
    $add((int)t_val("SELECT COUNT(*) FROM orders WHERE fulfillment_status='unfulfilled' AND cancelled_at IS NULL AND archived=0"), 'orders to fulfill', 'orders?view=unfulfilled', 'warn');
    $add((int)t_val("SELECT COUNT(*) FROM orders WHERE payment_status='pending' AND cancelled_at IS NULL"), 'orders with payment pending', 'orders?view=unpaid', 'warn');
    $add((int)t_val("SELECT COUNT(*) FROM products p WHERE p.status='active' AND p.track_inventory=1 AND (SELECT COALESCE(SUM(il.available),0) FROM inventory_levels il JOIN variants v ON v.id=il.variant_id WHERE v.product_id=p.id) <= 0"), 'active products out of stock', 'products/inventory?view=out_of_stock', 'critical');
    $add((int)t_val("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('ordered','partial')"), 'purchase orders waiting to be received', 'purchase_orders?view=ordered');
    $add((int)t_val("SELECT COUNT(*) FROM transfers WHERE status='in_transit'"), 'transfers in transit', 'transfers?view=in_transit');
    $add((int)t_val("SELECT COUNT(*) FROM draft_orders WHERE status='invoice_sent'"), 'invoices sent and awaiting payment', 'draft_orders?view=invoice_sent');
    $add((int)t_val("SELECT COUNT(*) FROM checkouts WHERE completed=0 AND recovery_status='not_recovered' AND created_at >= NOW() - INTERVAL 7 DAY"), 'abandoned checkouts this week', 'checkouts');
    json_out(['ok' => true, 'items' => $items]);
}
