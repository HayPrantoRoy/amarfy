<?php
if ($action === 'all') {
    $q = trim((string)($_GET['q'] ?? ''));
    $like = '%' . $q . '%';
    $num = ltrim(preg_replace('/^#?d?/i', '', $q), '#');
    $groups = [];
    $o = t_rows("SELECT o.id, o.number, o.total, c.first_name, c.last_name, c.email FROM orders o LEFT JOIN customers c ON c.id=o.customer_id
        WHERE o.number LIKE ? OR CONCAT(c.first_name,' ',c.last_name) LIKE ? ORDER BY o.id DESC LIMIT 5", [$num . '%', $like]);
    if ($o) $groups[] = ['label' => 'Orders', 'items' => array_map(fn($r) => ['title' => '#' . $r['number'], 'sub' => customer_name($r), 'href' => 'orders/' . $r['id']], $o)];
    $p = t_rows("SELECT id, title, status FROM products WHERE title LIKE ? ORDER BY title LIMIT 6", [$like]);
    if ($p) $groups[] = ['label' => 'Products', 'items' => array_map(fn($r) => ['title' => $r['title'], 'sub' => ucfirst($r['status']), 'href' => 'products/' . $r['id']], $p)];
    $c = t_rows("SELECT * FROM customers WHERE CONCAT(first_name,' ',last_name) LIKE ? OR email LIKE ? LIMIT 5", [$like, $like]);
    if ($c) $groups[] = ['label' => 'Customers', 'items' => array_map(fn($r) => ['title' => customer_name($r), 'sub' => $r['email'], 'href' => 'customers/' . $r['id']], $c)];
    $d = t_rows("SELECT id, number FROM draft_orders WHERE number LIKE ? ORDER BY id DESC LIMIT 4", [$num . '%']);
    if ($d) $groups[] = ['label' => 'Draft orders', 'items' => array_map(fn($r) => ['title' => '#D' . $r['number'], 'href' => 'draft_orders/' . $r['id']], $d)];
    $col = t_rows("SELECT id, title FROM collections WHERE title LIKE ? LIMIT 4", [$like]);
    if ($col) $groups[] = ['label' => 'Collections', 'items' => array_map(fn($r) => ['title' => $r['title'], 'href' => 'collections/' . $r['id']], $col)];
    json_out(['ok' => true, 'groups' => $groups]);
}
