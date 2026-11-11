<?php
$in = input();
$id = (int)($in['id'] ?? 0);
$company = fn() => $id ? t_row("SELECT * FROM companies WHERE id=?", [$id]) : null;
switch ($action) {
    case 'save':
        $name = trim((string)($in['name'] ?? ''));
        if ($name === '') json_err('Company name is required');
        if (t_val("SELECT id FROM companies WHERE name=? AND id<>?", [$name, $id])) json_err('A company with this name already exists');
        $contact = !empty($in['main_contact_id']) ? (int)$in['main_contact_id'] : null;
        if ($contact && !t_val("SELECT id FROM customers WHERE id=?", [$contact])) json_err('Customer not found');
        $f = [$name, trim((string)($in['external_id'] ?? '')), !empty($in['ordering_approved']) ? 1 : 0, $contact, trim((string)($in['note'] ?? ''))];
        if ($id) {
            if (!$company()) json_err('Company not found', 404);
            t_exec("UPDATE companies SET name=?, external_id=?, ordering_approved=?, main_contact_id=?, note=? WHERE id=?", [...$f, $id]);
        } else {
            $id = t_exec("INSERT INTO companies (name, external_id, ordering_approved, main_contact_id, note) VALUES (?,?,?,?,?)", $f);
            $loc = trim((string)($in['location_name'] ?? '')) ?: $name;
            t_exec("INSERT INTO company_locations (company_id, name, address, city, payment_terms, checkout_mode) VALUES (?,?,?,?,?,?)",
                [$id, $loc, trim((string)($in['address'] ?? '')), trim((string)($in['city'] ?? '')), ($in['payment_terms'] ?? '') ?: null, ($in['checkout_mode'] ?? 'auto') === 'review' ? 'review' : 'auto']);
            timeline_add('company', $id, 'You created this company.');
        }
        if ($contact) {
            t_exec("INSERT IGNORE INTO company_contacts (company_id, customer_id) VALUES (?,?)", [$id, $contact]);
            t_exec("UPDATE customers SET company=? WHERE id=?", [$name, $contact]);
        }
        json_out(['ok' => true, 'id' => $id]);

    case 'update':   // single side-panel fields
        if (!$company()) json_err('Company not found', 404);
        $field = (string)($in['field'] ?? '');
        $val = $in['value'] ?? null;
        match ($field) {
            'note' => t_exec("UPDATE companies SET note=? WHERE id=?", [trim((string)$val), $id]),
            'external_id' => t_exec("UPDATE companies SET external_id=? WHERE id=?", [trim((string)$val), $id]),
            'staff_id' => t_exec("UPDATE companies SET staff_id=? WHERE id=?", [$val ? (int)$val : null, $id]),
            'ordering_approved' => t_exec("UPDATE companies SET ordering_approved=? WHERE id=?", [$val ? 1 : 0, $id]),
            'payment_terms' => t_exec("UPDATE company_locations SET payment_terms=? WHERE company_id=?", [$val ?: null, $id]),
            'checkout_mode' => t_exec("UPDATE company_locations SET checkout_mode=? WHERE company_id=?", [$val === 'review' ? 'review' : 'auto', $id]),
            default => json_err('Unknown field'),
        };
        json_out(['ok' => true]);

    case 'contact_add':
        $c = t_row("SELECT * FROM customers WHERE id=?", [(int)($in['customer_id'] ?? 0)]);
        $co = $company();
        if (!$c || !$co) json_err('Not found', 404);
        t_exec("INSERT IGNORE INTO company_contacts (company_id, customer_id) VALUES (?,?)", [$id, (int)$c['id']]);
        t_exec("UPDATE customers SET company=? WHERE id=?", [$co['name'], (int)$c['id']]);
        if (!$co['main_contact_id']) t_exec("UPDATE companies SET main_contact_id=? WHERE id=?", [(int)$c['id'], $id]);
        json_out(['ok' => true]);

    case 'contact_remove':
        $cid = (int)($in['customer_id'] ?? 0);
        t_exec("DELETE FROM company_contacts WHERE company_id=? AND customer_id=?", [$id, $cid]);
        t_exec("UPDATE companies SET main_contact_id=NULL WHERE id=? AND main_contact_id=?", [$id, $cid]);
        json_out(['ok' => true]);

    case 'location_save':
        if (!$company()) json_err('Company not found', 404);
        $lid = (int)($in['location_id'] ?? 0);
        $name = trim((string)($in['name'] ?? ''));
        if ($name === '') json_err('Location name is required');
        $f = [$name, trim((string)($in['address'] ?? '')), trim((string)($in['city'] ?? '')), ($in['payment_terms'] ?? '') ?: null, ($in['checkout_mode'] ?? 'auto') === 'review' ? 'review' : 'auto'];
        if ($lid) t_exec("UPDATE company_locations SET name=?, address=?, city=?, payment_terms=?, checkout_mode=? WHERE id=? AND company_id=?", [...$f, $lid, $id]);
        else t_exec("INSERT INTO company_locations (name, address, city, payment_terms, checkout_mode, company_id) VALUES (?,?,?,?,?,?)", [...$f, $id]);
        json_out(['ok' => true]);

    case 'location_delete':
        if ((int)t_val("SELECT COUNT(*) FROM company_locations WHERE company_id=?", [$id]) <= 1) json_err('A company needs at least one location');
        t_exec("DELETE FROM company_locations WHERE id=? AND company_id=?", [(int)($in['location_id'] ?? 0), $id]);
        json_out(['ok' => true]);

    case 'delete':
        t_exec("DELETE FROM company_contacts WHERE company_id=?", [$id]);
        t_exec("DELETE FROM company_locations WHERE company_id=?", [$id]);
        t_exec("DELETE FROM companies WHERE id=?", [$id]);
        json_out(['ok' => true]);

    case 'bulk':
        foreach (array_map('intval', (array)($in['ids'] ?? [])) as $i) match ($in['action'] ?? '') {
            'approve' => t_exec("UPDATE companies SET ordering_approved=1 WHERE id=?", [$i]),
            'unapprove' => t_exec("UPDATE companies SET ordering_approved=0 WHERE id=?", [$i]),
            'delete' => [t_exec("DELETE FROM company_contacts WHERE company_id=?", [$i]), t_exec("DELETE FROM company_locations WHERE company_id=?", [$i]), t_exec("DELETE FROM companies WHERE id=?", [$i])],
            default => null,
        };
        json_out(['ok' => true]);
}
