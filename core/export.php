<?php
/** CSV exports: /export/{type} */
$type = preg_replace('/[^a-z_]/', '', $seg[1] ?? '');

if ($type === 'meta') {
    $types = array_column(t_rows("SELECT DISTINCT product_type v FROM products WHERE product_type<>'' ORDER BY v"), 'v');
    $vendors = array_column(t_rows("SELECT DISTINCT vendor v FROM products WHERE vendor<>'' ORDER BY v"), 'v');
    json_out(['ok' => true, 'types' => $types, 'vendors' => $vendors]);
}

$sets = [
    'orders' => [['Order', 'Date', 'Customer', 'Email', 'Channel', 'Subtotal', 'Discount', 'Shipping', 'Total', 'Payment status', 'Fulfillment status', 'Items', 'Delivery method', 'Tags'],
        fn() => array_map(fn($r) => ['#' . $r['number'], $r['created_at'], customer_name($r), $r['email'], $r['channel'], $r['subtotal'], $r['discount'], $r['shipping'], $r['total'], $r['payment_status'], $r['fulfillment_status'], $r['items'], $r['delivery_method'], $r['tags']],
            t_rows("SELECT o.*, c.first_name, c.last_name, c.email, (SELECT SUM(quantity) FROM order_items i WHERE i.order_id=o.id) items FROM orders o LEFT JOIN customers c ON c.id=o.customer_id ORDER BY o.id DESC"))],
    'drafts' => [['Draft order', 'Date', 'Customer', 'Status', 'Tags'],
        fn() => array_map(fn($r) => ['#D' . $r['number'], $r['created_at'], customer_name($r), $r['status'], $r['tags']],
            t_rows("SELECT d.*, c.first_name, c.last_name, c.email FROM draft_orders d LEFT JOIN customers c ON c.id=d.customer_id ORDER BY d.id DESC"))],
    'products' => [['Title', 'Handle', 'Description', 'Status', 'Vendor', 'Type', 'Tags', 'Variant', 'Price', 'Compare at price', 'Cost', 'SKU', 'Barcode', 'Quantity'],
        fn() => array_map(fn($r) => [$r['title'], $r['handle'], strip_tags((string)$r['description_html']), $r['status'], $r['vendor'], $r['product_type'], $r['tags'], $r['vtitle'], $r['price'], $r['compare_at_price'], $r['cost'], $r['sku'], $r['barcode'], $r['qty']],
            t_rows("SELECT p.*, v.title vtitle, v.price, v.compare_at_price, v.cost, v.sku, v.barcode, (SELECT COALESCE(SUM(available),0) FROM inventory_levels il WHERE il.variant_id=v.id) qty FROM products p JOIN variants v ON v.product_id=p.id ORDER BY p.id, v.position"))],
    'products_template' => [['Title', 'Description', 'Status', 'Vendor', 'Type', 'Tags', 'Price', 'Compare at price', 'SKU', 'Barcode', 'Quantity'],
        fn() => [['Sample T-shirt', 'Soft cotton tee', 'active', 'My brand', 'Apparel', 'summer,cotton', '550', '650', 'TS-001', '', '25']]],
    'inventory' => [['Product', 'Variant', 'SKU', 'Location', 'Unavailable', 'Committed', 'Available', 'On hand', 'Incoming'],
        fn() => array_map(fn($r) => [$r['title'], $r['vtitle'], $r['sku'], $r['loc'], $r['unavailable'], $r['committed'], $r['available'], $r['available'] + $r['committed'] + $r['unavailable'], $r['incoming']],
            t_rows("SELECT p.title, v.title vtitle, v.sku, l.name loc, COALESCE(il.unavailable,0) unavailable, COALESCE(il.committed,0) committed, COALESCE(il.available,0) available, COALESCE(il.incoming,0) incoming
                FROM variants v JOIN products p ON p.id=v.product_id CROSS JOIN locations l LEFT JOIN inventory_levels il ON il.variant_id=v.id AND il.location_id=l.id ORDER BY p.title, v.position, l.id"))],
    'gift_cards' => [['Code ending', 'Status', 'Customer', 'Created', 'Expires', 'Initial value', 'Balance', 'Currency'],
        fn() => array_map(fn($r) => [substr($r['code'], -4), $r['status'], customer_name($r), $r['created_at'], $r['expires_on'], $r['initial_value'], $r['balance'], $r['currency']],
            t_rows("SELECT g.*, c.first_name, c.last_name, c.email FROM gift_cards g LEFT JOIN customers c ON c.id=g.customer_id ORDER BY g.id DESC"))],
    'customers' => [['First name', 'Last name', 'Email', 'Phone', 'Address', 'City', 'Country', 'Orders', 'Tags'],
        fn() => array_map(fn($r) => [$r['first_name'], $r['last_name'], $r['email'], $r['phone'], $r['address1'], $r['city'], $r['country'], $r['n'], $r['tags']],
            t_rows("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id=c.id) n FROM customers c ORDER BY c.id DESC"))],
];
if (!isset($sets[$type])) { http_response_code(404); exit('Unknown export'); }
[$head, $rows] = $sets[$type];
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $type . '_export_' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, $head);
foreach ($rows() as $r) {
    // Neutralise spreadsheet formula injection
    fputcsv($out, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $r));
}
fclose($out);
