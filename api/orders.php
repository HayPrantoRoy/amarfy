<?php
function order_mark_paid(int $id): void {
    $o = t_row("SELECT * FROM orders WHERE id=?", [$id]);
    if (!$o || $o['payment_status'] === 'paid') return;
    t_exec("UPDATE orders SET payment_status='paid' WHERE id=?", [$id]);
    timeline_add('order', $id, 'A ' . money($o['total']) . ' ' . $o['currency'] . ' payment was processed manually.');
}
function order_fulfill(int $id): void {
    $o = t_row("SELECT * FROM orders WHERE id=?", [$id]);
    if (!$o || $o['fulfillment_status'] === 'fulfilled') return;
    foreach (t_rows("SELECT * FROM order_items WHERE order_id=?", [$id]) as $i)
        if ($i['variant_id']) inv_adjust((int)$i['variant_id'], (int)$o['location_id'], 'committed', -(int)$i['quantity']);
    t_exec("UPDATE orders SET fulfillment_status='fulfilled', fulfilled_at=NOW() WHERE id=?", [$id]);
    timeline_add('order', $id, 'You fulfilled all items.');
}
function order_cancel(int $id): void {
    $o = t_row("SELECT * FROM orders WHERE id=?", [$id]);
    if (!$o || $o['cancelled_at']) return;
    if ($o['fulfillment_status'] === 'unfulfilled') {
        foreach (t_rows("SELECT * FROM order_items WHERE order_id=?", [$id]) as $i) if ($i['variant_id']) {
            inv_adjust((int)$i['variant_id'], (int)$o['location_id'], 'committed', -(int)$i['quantity']);
            inv_adjust((int)$i['variant_id'], (int)$o['location_id'], 'available', (int)$i['quantity']);
        }
    }
    t_exec("UPDATE orders SET cancelled_at=NOW(), payment_status=IF(payment_status='paid','refunded','voided'), archived=1 WHERE id=?", [$id]);
    timeline_add('order', $id, 'You cancelled this order. Items were restocked.');
}

$in = input();
$id = (int)($in['id'] ?? 0);
switch ($action) {
    case 'mark_paid': order_mark_paid($id); json_out(['ok' => true]);
    case 'fulfill': order_fulfill($id); json_out(['ok' => true]);
    case 'cancel': order_cancel($id); json_out(['ok' => true]);
    case 'duplicate_draft':
        $o = t_row("SELECT * FROM orders WHERE id=?", [$id]);
        if (!$o) json_err('Order not found', 404);
        $num = next_number('next_draft_number', 1);
        $did = t_exec("INSERT INTO draft_orders (number, customer_id, currency, tags, note, shipping_title, shipping_amount, shipping_address_json, updated_by) VALUES (?,?,?,?,?,?,?,?,?)",
            [$num, $o['customer_id'], $o['currency'], $o['tags'], $o['note'], $o['delivery_method'], $o['shipping'], $o['shipping_address_json'], staff_name()]);
        foreach (t_rows("SELECT * FROM order_items WHERE order_id=?", [$id]) as $i) {
            // use the current price for products that still exist
            $price = $i['variant_id'] ? (t_val("SELECT price FROM variants WHERE id=?", [(int)$i['variant_id']]) ?? $i['price']) : $i['price'];
            t_exec("INSERT INTO draft_order_items (draft_order_id, variant_id, product_id, title, variant_title, image, price, quantity) VALUES (?,?,?,?,?,?,?,?)",
                [$did, $i['variant_id'], $i['product_id'], $i['title'], $i['variant_title'], $i['image'], $price, $i['quantity']]);
        }
        timeline_add('draft', $did, 'You created this draft from order #' . $o['number'] . '.');
        json_out(['ok' => true, 'id' => $did]);
    case 'archive': t_exec("UPDATE orders SET archived=1-archived WHERE id=?", [$id]); json_out(['ok' => true]);
    case 'update':
        $tags = implode(',', array_filter(array_map('trim', (array)($in['tags'] ?? []))));
        t_exec("UPDATE orders SET note=?, tags=? WHERE id=?", [trim((string)($in['note'] ?? '')), $tags, $id]);
        json_out(['ok' => true]);
    case 'bulk':
        $ids = array_map('intval', (array)($in['ids'] ?? []));
        foreach ($ids as $i) match ($in['action'] ?? '') {
            'mark_paid' => order_mark_paid($i), 'fulfill' => order_fulfill($i),
            'archive' => t_exec("UPDATE orders SET archived=1 WHERE id=?", [$i]),
            'unarchive' => t_exec("UPDATE orders SET archived=0 WHERE id=?", [$i]),
            default => null,
        };
        json_out(['ok' => true]);
}
