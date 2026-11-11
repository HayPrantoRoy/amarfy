<?php
switch ($action) {
    case 'save':
        $in = input();
        $id = (int)($in['id'] ?? 0);
        $items = (array)($in['items'] ?? []);
        if (!$items) json_err('Add at least one product or custom item');
        if ($id) {
            $d = t_row("SELECT * FROM draft_orders WHERE id=?", [$id]);
            if (!$d) json_err('Draft not found', 404);
            if ($d['status'] === 'completed') json_err('Completed draft orders can’t be edited');
        }
        $customerId = !empty($in['customer_id']) ? (int)$in['customer_id'] : null;
        if ($customerId && !t_val("SELECT id FROM customers WHERE id=?", [$customerId])) json_err('Customer not found');
        $discType = in_array($in['discount_type'] ?? '', ['fixed', 'percentage'], true) ? $in['discount_type'] : null;
        $terms = array_key_exists($in['payment_terms'] ?? '', payment_terms_options()) ? $in['payment_terms'] : null;
        $tags = implode(',', array_filter(array_map('trim', (array)($in['tags'] ?? []))));
        $addr = isset($in['shipping_address']) && is_array($in['shipping_address']) ? json_encode($in['shipping_address'], JSON_UNESCAPED_UNICODE) : null;
        $vals = [$customerId, cur(), $tags, trim((string)($in['note'] ?? '')), $discType, (float)($in['discount_value'] ?? 0), trim((string)($in['discount_reason'] ?? '')),
                 trim((string)($in['shipping_title'] ?? '')), max(0, (float)($in['shipping_amount'] ?? 0)), !empty($in['payment_due_later']) ? 1 : 0, $terms, $addr, staff_name(), trim((string)($in['po_number'] ?? ''))];
        if ($id) {
            t_exec("UPDATE draft_orders SET customer_id=?, currency=?, tags=?, note=?, discount_type=?, discount_value=?, discount_reason=?, shipping_title=?, shipping_amount=?, payment_due_later=?, payment_terms=?, shipping_address_json=?, updated_by=?, po_number=? WHERE id=?", [...$vals, $id]);
            t_exec("DELETE FROM draft_order_items WHERE draft_order_id=?", [$id]);
        } else {
            $num = next_number('next_draft_number', 1);
            $id = t_exec("INSERT INTO draft_orders (customer_id, currency, tags, note, discount_type, discount_value, discount_reason, shipping_title, shipping_amount, payment_due_later, payment_terms, shipping_address_json, updated_by, po_number, number) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [...$vals, $num]);
            timeline_add('draft', $id, 'You created this draft order.');
        }
        $companyId = !empty($in['company_id']) ? (int)$in['company_id'] : null;
        if ($companyId && !t_val("SELECT id FROM companies WHERE id=?", [$companyId])) $companyId = null;
        t_exec("UPDATE draft_orders SET company_id=? WHERE id=?", [$companyId, $id]);
        foreach ($items as $it) {
            $qty = max(1, (int)($it['quantity'] ?? 1));
            $price = max(0, (float)($it['price'] ?? 0));
            $vid = !empty($it['variant_id']) ? (int)$it['variant_id'] : null;
            $title = trim((string)($it['title'] ?? ''));
            if ($vid) {
                $v = t_row("SELECT v.*, p.title ptitle, p.id pid FROM variants v JOIN products p ON p.id=v.product_id WHERE v.id=?", [$vid]);
                if (!$v) continue;
                $title = $v['ptitle'];
                t_exec("INSERT INTO draft_order_items (draft_order_id, variant_id, product_id, title, variant_title, image, price, quantity) VALUES (?,?,?,?,?,?,?,?)",
                    [$id, $vid, (int)$v['pid'], $title, $v['title'] === 'Default Title' ? null : $v['title'], first_image((int)$v['pid']), $price, $qty]);
            } elseif ($title !== '') {
                t_exec("INSERT INTO draft_order_items (draft_order_id, title, price, quantity, taxable, requires_shipping) VALUES (?,?,?,?,?,?)",
                    [$id, $title, $price, $qty, !empty($it['taxable']) ? 1 : 0, !empty($it['requires_shipping']) ? 1 : 0]);
            }
        }
        json_out(['ok' => true, 'id' => $id]);

    case 'complete':
        $in = input();
        $oid = draft_to_order((int)($in['id'] ?? 0), ($in['payment'] ?? '') === 'paid' ? 'paid' : 'pending');
        json_out(['ok' => true, 'order_id' => $oid]);

    case 'invoice':
        $in = input();
        $id = (int)($in['id'] ?? 0);
        $d = t_row("SELECT * FROM draft_orders WHERE id=?", [$id]);
        if (!$d) json_err('Draft not found', 404);
        $to = trim((string)($in['to'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) json_err('Enter a valid recipient email');
        t_exec("UPDATE draft_orders SET status='invoice_sent' WHERE id=? AND status='open'", [$id]);
        timeline_add('draft', $id, 'You sent an invoice to ' . $to . '.');
        json_out(['ok' => true]);

    case 'duplicate':
        $id = (int)(input()['id'] ?? 0);
        $d = t_row("SELECT * FROM draft_orders WHERE id=?", [$id]);
        if (!$d) json_err('Draft not found', 404);
        $num = next_number('next_draft_number', 1);
        $new = t_exec("INSERT INTO draft_orders (number, customer_id, currency, tags, note, discount_type, discount_value, discount_reason, shipping_title, shipping_amount, payment_due_later, payment_terms, shipping_address_json, updated_by)
            SELECT ?, customer_id, currency, tags, note, discount_type, discount_value, discount_reason, shipping_title, shipping_amount, payment_due_later, payment_terms, shipping_address_json, ? FROM draft_orders WHERE id=?", [$num, staff_name(), $id]);
        t_exec("INSERT INTO draft_order_items (draft_order_id, variant_id, product_id, title, variant_title, image, price, quantity, taxable, requires_shipping)
            SELECT ?, variant_id, product_id, title, variant_title, image, price, quantity, taxable, requires_shipping FROM draft_order_items WHERE draft_order_id=?", [$new, $id]);
        timeline_add('draft', $new, 'You duplicated draft order #D' . $d['number'] . '.');
        json_out(['ok' => true, 'id' => $new]);

    case 'delete':
        $id = (int)(input()['id'] ?? 0);
        t_exec("DELETE FROM draft_order_items WHERE draft_order_id=?", [$id]);
        t_exec("DELETE FROM draft_orders WHERE id=?", [$id]);
        json_out(['ok' => true]);

    case 'bulk':
        $in = input();
        foreach (array_map('intval', (array)($in['ids'] ?? [])) as $i) {
            if (($in['action'] ?? '') === 'delete') { t_exec("DELETE FROM draft_order_items WHERE draft_order_id=?", [$i]); t_exec("DELETE FROM draft_orders WHERE id=?", [$i]); }
        }
        json_out(['ok' => true]);
}
