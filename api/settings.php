<?php
$in = input();
switch ($action) {
    case 'store':
        $name = trim((string)($in['store_name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) json_err('Enter a store name (max 120 characters)');
        $email = trim((string)($in['store_email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) json_err('Enter a valid store email');
        set_setting('store_name', $name);
        foreach (['store_email', 'store_phone', 'store_address', 'order_prefix'] as $k) set_setting($k, trim((string)($in[$k] ?? '')));
        m_exec("UPDATE users SET store_name=? WHERE id=?", [$name, (int)$_SESSION['uid']]);
        $_SESSION['user']['store_name'] = $name;
        json_out(['ok' => true]);

    case 'location_save':
        $id = (int)($in['id'] ?? 0);
        $name = trim((string)($in['name'] ?? ''));
        if ($name === '') json_err('Enter a location name');
        if (t_val("SELECT id FROM locations WHERE name=? AND id<>?", [$name, $id])) json_err('A location with this name already exists');
        $addr = trim((string)($in['address'] ?? ''));
        if ($id) t_exec("UPDATE locations SET name=?, address=? WHERE id=?", [$name, $addr, $id]);
        else $id = t_exec("INSERT INTO locations (name, address) VALUES (?,?)", [$name, $addr]);
        json_out(['ok' => true, 'id' => $id]);

    case 'location_default':
        $id = (int)($in['id'] ?? 0);
        if (!t_val("SELECT id FROM locations WHERE id=?", [$id])) json_err('Location not found');
        t_exec("UPDATE locations SET is_default = (id = ?)", [$id]);
        json_out(['ok' => true]);

    case 'location_delete':
        $id = (int)($in['id'] ?? 0);
        if ((int)t_val("SELECT COUNT(*) FROM locations") <= 1) json_err('You need at least one location');
        if ((int)t_val("SELECT is_default FROM locations WHERE id=?", [$id])) json_err('Make another location the default before deleting this one');
        $stock = (int)t_val("SELECT COALESCE(SUM(available+committed+incoming),0) FROM inventory_levels WHERE location_id=?", [$id]);
        if ($stock > 0) json_err("This location still holds $stock units. Transfer the stock out first.");
        if ((int)t_val("SELECT COUNT(*) FROM transfers WHERE status<>'received' AND (origin_id=? OR destination_id=?)", [$id, $id])) json_err('This location has open transfers');
        t_exec("DELETE FROM inventory_levels WHERE location_id=?", [$id]);
        t_exec("DELETE FROM locations WHERE id=?", [$id]);
        json_out(['ok' => true]);

    case 'account':
        $name = trim((string)($in['name'] ?? ''));
        if ($name === '') json_err('Enter your name');
        m_exec("UPDATE users SET name=? WHERE id=?", [$name, (int)$_SESSION['uid']]);
        t_exec("UPDATE staff SET name=? WHERE master_user_id=?", [$name, (int)$_SESSION['uid']]);
        $_SESSION['user']['name'] = $name;
        json_out(['ok' => true]);

    case 'password':
        $u = m_row("SELECT * FROM users WHERE id=?", [(int)$_SESSION['uid']]);
        if (!$u || !password_verify((string)($in['current'] ?? ''), $u['password'])) json_err('Your current password is incorrect');
        $new = (string)($in['new'] ?? '');
        if (strlen($new) < 8) json_err('New password must be at least 8 characters');
        if ($new !== (string)($in['confirm'] ?? '')) json_err('New passwords do not match');
        m_exec("UPDATE users SET password=? WHERE id=?", [password_hash($new, PASSWORD_DEFAULT), (int)$u['id']]);
        json_out(['ok' => true]);
}
