<?php
switch ($action) {
    case 'save':
        $in = input();
        $id = (int)($in['id'] ?? 0);
        $t = $id ? t_row("SELECT * FROM transfers WHERE id=?", [$id]) : null;
        if ($id && !$t) json_err('Transfer not found', 404);
        if ($t && $t['status'] !== 'draft') json_err('Only draft transfers can be edited');
        $o = (int)($in['origin_id'] ?? 0); $d = (int)($in['destination_id'] ?? 0);
        if (!$o) json_err('Select an origin');
        if (!$d) json_err('Select a destination');
        if ($o === $d) json_err('Origin and destination must be different');
        $items = array_values(array_filter((array)($in['items'] ?? []), fn($i) => !empty($i['variant_id'])));
        if (!$items) json_err('Add at least one product');
        $f = [$o, $d, ($in['transfer_date'] ?? '') ?: date('Y-m-d'), trim((string)($in['reference'] ?? '')), trim((string)($in['note'] ?? '')),
              implode(',', array_filter(array_map('trim', (array)($in['tags'] ?? [])))), !empty($in['purchase_order_id']) ? (int)$in['purchase_order_id'] : null];
        $cols = 'origin_id=?, destination_id=?, transfer_date=?, reference=?, note=?, tags=?, purchase_order_id=?';
        if ($id) t_exec("UPDATE transfers SET $cols WHERE id=?", [...$f, $id]);
        else { $id = t_exec("INSERT INTO transfers SET $cols, number=?", [...$f, next_number('next_transfer_number', 1)]); timeline_add('transfer', $id, 'You created this transfer.'); }
        t_exec("DELETE FROM transfer_items WHERE transfer_id=?", [$id]);
        foreach ($items as $i) t_exec("INSERT INTO transfer_items (transfer_id, variant_id, quantity) VALUES (?,?,?)", [$id, (int)$i['variant_id'], max(1, (int)($i['quantity'] ?? 1))]);
        json_out(['ok' => true, 'id' => $id]);

    case 'ship':
        $id = (int)(input()['id'] ?? 0);
        $t = t_row("SELECT * FROM transfers WHERE id=? AND status='draft'", [$id]);
        if (!$t) json_err('Only draft transfers can be shipped');
        foreach (t_rows("SELECT * FROM transfer_items WHERE transfer_id=?", [$id]) as $i) {
            inv_adjust((int)$i['variant_id'], (int)$t['origin_id'], 'available', -(int)$i['quantity']);
            inv_adjust((int)$i['variant_id'], (int)$t['destination_id'], 'incoming', (int)$i['quantity']);
        }
        t_exec("UPDATE transfers SET status='in_transit' WHERE id=?", [$id]);
        timeline_add('transfer', $id, 'You marked this transfer as in transit.');
        json_out(['ok' => true]);

    case 'receive':
        $id = (int)(input()['id'] ?? 0);
        $t = t_row("SELECT * FROM transfers WHERE id=? AND status='in_transit'", [$id]);
        if (!$t) json_err('Only in-transit transfers can be received');
        foreach (t_rows("SELECT * FROM transfer_items WHERE transfer_id=?", [$id]) as $i) {
            $left = (int)$i['quantity'] - (int)$i['received'];
            inv_adjust((int)$i['variant_id'], (int)$t['destination_id'], 'incoming', -$left);
            inv_adjust((int)$i['variant_id'], (int)$t['destination_id'], 'available', $left);
            t_exec("UPDATE transfer_items SET received=quantity WHERE id=?", [(int)$i['id']]);
        }
        t_exec("UPDATE transfers SET status='received' WHERE id=?", [$id]);
        timeline_add('transfer', $id, 'You received all items.');
        json_out(['ok' => true]);

    case 'delete':
        $id = (int)(input()['id'] ?? 0);
        if (t_val("SELECT status FROM transfers WHERE id=?", [$id]) !== 'draft') json_err('Only draft transfers can be deleted');
        t_exec("DELETE FROM transfer_items WHERE transfer_id=?", [$id]);
        t_exec("DELETE FROM transfers WHERE id=?", [$id]);
        json_out(['ok' => true]);
}
