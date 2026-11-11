<?php
switch ($action) {
    case 'search':
        $q = trim((string)($_GET['q'] ?? ''));
        $like = '%' . $q . '%';
        $rows = t_rows("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id=c.id) orders_count FROM customers c
            WHERE ? = '' OR CONCAT(first_name,' ',last_name) LIKE ? OR email LIKE ? OR phone LIKE ? ORDER BY first_name, last_name LIMIT 20", [$q, $like, $like, $like]);
        json_out(['ok' => true, 'customers' => array_map('customer_json', $rows)]);

    case 'get':
        $c = t_row("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id=c.id) orders_count FROM customers c WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$c) json_err('Customer not found', 404);
        json_out(['ok' => true, 'customer' => customer_json($c)]);

    case 'save':
        $in = input();
        $id = (int)($in['id'] ?? 0);
        $f = [];
        foreach (['first_name', 'last_name', 'email', 'phone', 'company', 'address1', 'address2', 'city', 'zip', 'country', 'tags', 'note'] as $k) $f[$k] = trim((string)($in[$k] ?? ''));
        if ($f['first_name'] === '' && $f['last_name'] === '' && $f['email'] === '' && $f['phone'] === '') json_err('Enter a name, email or phone number');
        if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) json_err('Enter a valid email');
        if ($f['email'] !== '' && t_val("SELECT id FROM customers WHERE email=? AND id<>?", [$f['email'], $id])) json_err('A customer with this email already exists');
        $mk = !empty($in['accepts_marketing']) ? 1 : 0;
        $vals = array_values($f);
        if ($id) {
            t_exec("UPDATE customers SET first_name=?, last_name=?, email=?, phone=?, company=?, address1=?, address2=?, city=?, zip=?, country=?, tags=?, note=?, accepts_marketing=? WHERE id=?", [...$vals, $mk, $id]);
        } else {
            $id = t_exec("INSERT INTO customers (first_name, last_name, email, phone, company, address1, address2, city, zip, country, tags, note, accepts_marketing) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)", [...$vals, $mk]);
            timeline_add('customer', $id, 'You created this customer.');
        }
        $c = t_row("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id=c.id) orders_count FROM customers c WHERE id=?", [$id]);
        json_out(['ok' => true, 'customer' => customer_json($c)]);

    case 'bulk':
        $in = input();
        $ids = array_map('intval', (array)($in['ids'] ?? []));
        if ($ids && ($in['action'] ?? '') === 'delete') {
            foreach ($ids as $i) t_exec("DELETE FROM customers WHERE id=?", [$i]);
        }
        json_out(['ok' => true]);
}

function customer_json(array $c): array
{
    return ['id' => (int)$c['id'], 'name' => customer_name($c), 'first_name' => $c['first_name'], 'last_name' => $c['last_name'], 'email' => $c['email'], 'phone' => $c['phone'],
        'company' => $c['company'], 'address1' => $c['address1'], 'address2' => $c['address2'], 'city' => $c['city'], 'zip' => $c['zip'], 'country' => $c['country'], 'orders_count' => (int)($c['orders_count'] ?? 0)];
}
