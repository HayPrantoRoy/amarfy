<?php
switch ($action) {
    case 'suppliers':
        json_out(['ok' => true, 'suppliers' => t_rows("SELECT * FROM suppliers ORDER BY name")]);

    case 'save_supplier':
        $in = input();
        $name = trim((string)($in['name'] ?? ''));
        if ($name === '') json_err('Enter the supplier’s company name');
        $id = t_exec("INSERT INTO suppliers (name, contact_name, email, phone, address, country) VALUES (?,?,?,?,?,?)",
            [$name, trim((string)($in['contact_name'] ?? '')), trim((string)($in['email'] ?? '')), trim((string)($in['phone'] ?? '')), trim((string)($in['address'] ?? '')), trim((string)($in['country'] ?? ''))]);
        json_out(['ok' => true, 'supplier' => t_row("SELECT * FROM suppliers WHERE id=?", [$id])]);

    case 'save':
        $in = input();
        $id = (int)($in['id'] ?? 0);
        $po = $id ? t_row("SELECT * FROM purchase_orders WHERE id=?", [$id]) : null;
        if ($id && !$po) json_err('Purchase order not found', 404);
        if ($po && $po['status'] !== 'draft') json_err('Only draft purchase orders can be edited');
        $sup = !empty($in['supplier_id']) ? (int)$in['supplier_id'] : null;
        $dest = !empty($in['destination_id']) ? (int)$in['destination_id'] : null;
        if (!$sup) json_err('Select a supplier');
        if (!$dest) json_err('Select a destination');
        $items = array_values(array_filter((array)($in['items'] ?? []), fn($i) => !empty($i['variant_id'])));
        if (!$items) json_err('Add at least one product');
        $f = [$sup, $dest, trim((string)($in['reference'] ?? '')), trim((string)($in['note'] ?? '')), trim((string)($in['terms'] ?? '')), cur(),
              ($in['expected_arrival'] ?? '') ?: null, trim((string)($in['carrier'] ?? '')), trim((string)($in['tracking'] ?? '')), max(0, (float)($in['shipping'] ?? 0)),
              implode(',', array_filter(array_map('trim', (array)($in['tags'] ?? []))))];
        $cols = 'supplier_id=?, destination_id=?, reference=?, note=?, terms=?, currency=?, expected_arrival=?, carrier=?, tracking=?, shipping=?, tags=?';
        if ($id) t_exec("UPDATE purchase_orders SET $cols WHERE id=?", [...$f, $id]);
        else { $id = t_exec("INSERT INTO purchase_orders SET $cols, number=?", [...$f, next_number('next_po_number', 1)]); timeline_add('purchase_order', $id, 'You created this purchase order.'); }
        t_exec("DELETE FROM purchase_order_items WHERE purchase_order_id=?", [$id]);
        foreach ($items as $i) t_exec("INSERT INTO purchase_order_items (purchase_order_id, variant_id, supplier_sku, quantity, cost, tax_rate) VALUES (?,?,?,?,?,?)",
            [$id, (int)$i['variant_id'], trim((string)($i['supplier_sku'] ?? '')), max(1, (int)($i['quantity'] ?? 1)), max(0, (float)($i['cost'] ?? 0)), max(0, min(100, (float)($i['tax_rate'] ?? 0)))]);
        json_out(['ok' => true, 'id' => $id]);

    case 'mark_ordered':
        $id = (int)(input()['id'] ?? 0);
        $po = t_row("SELECT * FROM purchase_orders WHERE id=? AND status='draft'", [$id]);
        if (!$po) json_err('Only draft purchase orders can be marked as ordered');
        foreach (t_rows("SELECT * FROM purchase_order_items WHERE purchase_order_id=?", [$id]) as $i) inv_adjust((int)$i['variant_id'], (int)$po['destination_id'], 'incoming', (int)$i['quantity']);
        t_exec("UPDATE purchase_orders SET status='ordered' WHERE id=?", [$id]);
        timeline_add('purchase_order', $id, 'You marked this purchase order as ordered.');
        json_out(['ok' => true]);

    case 'receive':
        $in = input();
        $id = (int)($in['id'] ?? 0);
        $po = t_row("SELECT * FROM purchase_orders WHERE id=? AND status IN ('ordered','partial')", [$id]);
        if (!$po) json_err('Mark the purchase order as ordered before receiving');
        $total = 0;
        foreach ((array)($in['lines'] ?? []) as $lineId => $qty) {
            $i = t_row("SELECT * FROM purchase_order_items WHERE id=? AND purchase_order_id=?", [(int)$lineId, $id]);
            if (!$i) continue;
            $qty = max(0, min((int)$qty, (int)$i['quantity'] - (int)$i['received']));
            if (!$qty) continue;
            t_exec("UPDATE purchase_order_items SET received=received+? WHERE id=?", [$qty, (int)$i['id']]);
            inv_adjust((int)$i['variant_id'], (int)$po['destination_id'], 'incoming', -$qty);
            inv_adjust((int)$i['variant_id'], (int)$po['destination_id'], 'available', $qty);
            $total += $qty;
        }
        $left = (int)t_val("SELECT COALESCE(SUM(quantity-received),0) FROM purchase_order_items WHERE purchase_order_id=?", [$id]);
        t_exec("UPDATE purchase_orders SET status=? WHERE id=?", [$left > 0 ? 'partial' : 'received', $id]);
        if ($total) timeline_add('purchase_order', $id, "You received $total item(s).");
        json_out(['ok' => true]);

    case 'close':
        $id = (int)(input()['id'] ?? 0);
        $po = t_row("SELECT * FROM purchase_orders WHERE id=?", [$id]);
        if ($po && in_array($po['status'], ['ordered', 'partial'], true))
            foreach (t_rows("SELECT * FROM purchase_order_items WHERE purchase_order_id=?", [$id]) as $i) inv_adjust((int)$i['variant_id'], (int)$po['destination_id'], 'incoming', -((int)$i['quantity'] - (int)$i['received']));
        t_exec("UPDATE purchase_orders SET status='closed' WHERE id=?", [$id]);
        timeline_add('purchase_order', $id, 'You closed this purchase order.');
        json_out(['ok' => true]);

    case 'delete':
        $id = (int)(input()['id'] ?? 0);
        if (t_val("SELECT status FROM purchase_orders WHERE id=?", [$id]) !== 'draft') json_err('Only draft purchase orders can be deleted');
        t_exec("DELETE FROM purchase_order_items WHERE purchase_order_id=?", [$id]);
        t_exec("DELETE FROM purchase_orders WHERE id=?", [$id]);
        json_out(['ok' => true]);

    case 'list':
        json_out(['ok' => true, 'purchase_orders' => t_rows("SELECT id, number, status FROM purchase_orders ORDER BY id DESC")]);
}
