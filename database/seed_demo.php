<?php
/**
 * Fill one store with realistic demo data (products + images, customers, collections,
 * orders, drafts, purchase orders, transfers, gift cards, abandoned checkouts).
 *
 *   php database/seed_demo.php amarfy_100001
 *
 * CLI only. Adds data; never deletes. Refuses to run twice on the same store.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
$db = $argv[1] ?? '';
if (!preg_match('/^amarfy_\d+$/', $db)) exit("Usage: php database/seed_demo.php amarfy_100001\n");

$_SERVER['SCRIPT_NAME'] = '/amarfy/index.php';
require __DIR__ . '/../core/bootstrap.php';
require __DIR__ . '/../core/ui.php';
require __DIR__ . '/../core/domain.php';

$owner = m_row("SELECT * FROM users WHERE db_name = ?", [$db]);
if (!$owner) exit("No master user owns $db\n");
$_SESSION['db_name'] = $db;
$_SESSION['upload_folder'] = $owner['upload_folder'] ?: $db;
$_SESSION['user'] = ['id' => (int)$owner['id'], 'name' => $owner['name'], 'email' => $owner['email'], 'store_name' => $owner['store_name'], 'tenant_number' => $owner['tenant_number']];
if (setting('demo_seeded')) exit("$db already has demo data.\n");

$dir = upload_dir();
@chmod($dir, 0777);
$img = function (string $name) use ($dir): array {
    $src = __DIR__ . '/demo_images/' . $name . '.png';
    $file = $name . '_' . bin2hex(random_bytes(3)) . '.png';
    copy($src, $dir . '/' . $file);
    @chmod($dir . '/' . $file, 0666);
    $rel = 'uploads/' . $_SESSION['upload_folder'] . '/' . $file;
    $id = t_exec("INSERT INTO files (path, name, mime, size) VALUES (?,?,?,?)", [$rel, $file, 'image/png', filesize($src)]);
    return ['id' => $id, 'path' => $rel];
};
$ago = fn(int $days, int $h = 11) => date('Y-m-d H:i:s', strtotime("-$days days") - (date('G') - $h) * 3600 + random_int(0, 3000));
$L1 = default_location_id();
$L2 = (int)t_val("SELECT id FROM locations WHERE id <> ? ORDER BY id LIMIT 1", [$L1]);
t_exec("UPDATE locations SET address='House 12, Road 7, Dhanmondi, Dhaka' WHERE id=?", [$L1]);
t_exec("UPDATE locations SET name='Chattogram Warehouse', address='Agrabad C/A, Chattogram' WHERE id=?", [$L2]);

/* ---------------- products ---------------- */
$P = [];   // key => ['id'=>, 'variants'=>[title=>id]]
$products = [
    ['panjabi', 'Classic Cotton Panjabi', 'Apparel & Accessories', 'Panjabi', 'Dhaka Loom', 'eid,cotton,men', 1850, 2200, 950,
        '<p>Breathable 100% cotton panjabi with a mandarin collar and hand-finished button placket. Ideal for Eid, weddings and everyday comfort.</p><ul><li>Fabric: combed cotton</li><li>Regular fit</li><li>Machine wash cold</li></ul>',
        ['Color' => ['White', 'Navy', 'Maroon'], 'Size' => ['M', 'L', 'XL']], ['panjabi_white', 'panjabi_navy', 'panjabi_maroon'], 0.4],
    ['saree', 'Handwoven Jamdani Saree', 'Apparel & Accessories', 'Saree', 'Nokshi House', 'handloom,women,festive', 6500, 7800, 3900,
        '<p>Traditional Dhakai Jamdani woven on a handloom by artisans in Narayanganj. Lightweight muslin with a contrast zari border.</p><p>Length 6 yards, blouse piece included.</p>',
        ['Color' => ['Red', 'Teal']], ['saree_red', 'saree_teal'], 0.6],
    ['kantha', 'Nakshi Kantha Throw', 'Home & Garden', 'Home decor', 'Nokshi House', 'handmade,home', 3200, null, 1700,
        '<p>Hand-stitched nakshi kantha throw made from layered cotton with running-stitch motifs. Each piece is one of a kind.</p>', [], ['kantha'], 1.1],
    ['wallet', 'Leather Bifold Wallet', 'Apparel & Accessories', 'Wallet', 'Sundarban Leather', 'leather,men,gift', 1450, 1800, 620,
        '<p>Full-grain leather bifold with 6 card slots, a cash compartment and RFID-blocking lining.</p>', ['Color' => ['Brown', 'Black']], ['wallet_brown', 'wallet_black'], 0.15],
    ['mug', 'Ceramic Coffee Mug', 'Home & Garden', 'Kitchen', 'Padma Ceramics', 'kitchen,gift', 450, null, 180,
        '<p>Glazed stoneware mug, 350 ml. Microwave and dishwasher safe.</p>', [], ['mug'], 0.35],
    ['tote', 'Canvas Tote Bag', 'Apparel & Accessories', 'Bag', 'Dhaka Loom', 'eco,bag', 650, null, 260,
        '<p>Heavy 12 oz cotton canvas tote with reinforced handles and an inside pocket.</p>', [], ['tote'], 0.3],
    ['sneaker', 'Everyday Sneakers', 'Apparel & Accessories', 'Shoes', 'Urban Step', 'shoes,men,women', 3800, 4500, 2100,
        '<p>Lightweight knit sneakers with a cushioned EVA sole for all-day walking.</p>', ['Size' => ['40', '41', '42', '43']], ['sneaker'], 0.8],
    ['watch', 'Minimal Wrist Watch', 'Apparel & Accessories', 'Watch', 'Urban Step', 'watch,gift', 5200, 6000, 2800,
        '<p>Japanese quartz movement, sapphire-coated glass and a genuine leather strap. Water resistant to 30 m.</p>', [], ['watch'], 0.12],
    ['earbuds', 'Wireless Earbuds Pro', 'Electronics', 'Electronics', 'Tech Bazar', 'gadget,audio', 2900, 3500, 1650,
        '<p>Bluetooth 5.3 earbuds.</p>', [], ['earbuds'], 0.1],
    ['bottle', 'Stainless Water Bottle 1L', 'Sporting Goods', 'Bottle', 'Tech Bazar', 'eco,sports', 890, null, 420,
        '<p>Double-wall vacuum insulated bottle — keeps drinks cold for 24 hours or hot for 12.</p>', [], ['bottle'], 0.45],
    ['sunglasses', 'Polarized Sunglasses', 'Apparel & Accessories', 'Eyewear', 'Urban Step', 'summer,eyewear', 1600, 2000, 700,
        '<p>UV400 polarized lenses with a lightweight metal frame.</p>', [], ['sunglasses'], 0.05],
    ['backpack', 'Laptop Backpack', 'Apparel & Accessories', 'Bag', 'Sundarban Leather', 'bag,office,student', 2750, 3200, 1400,
        '<p>Water-resistant backpack with a padded 15.6" laptop sleeve, USB charging port and anti-theft back pocket.</p>', [], ['backpack'], 0.9],
];
foreach ($products as [$key, $title, $cat, $type, $vendor, $tags, $price, $compare, $cost, $desc, $opts, $imgs, $weight]) {
    $optJson = [];
    foreach ($opts as $n => $vals) $optJson[] = ['name' => $n, 'values' => $vals];
    $pid = t_exec("INSERT INTO products (title, handle, description_html, status, category, product_type, vendor, tags, options_json, weight, weight_unit, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
        [$title, slugify($title), $desc, 'active', $cat, $type, $vendor, $tags, json_encode($optJson), $weight, 'kg', $ago(random_int(20, 40))]);
    $media = [];
    foreach ($imgs as $i => $im) { $f = $img($im); $media[$im] = $f; t_exec("INSERT INTO product_media (product_id, file_id, path, position) VALUES (?,?,?,?)", [$pid, $f['id'], $f['path'], $i]); }
    $combos = [[]];
    foreach ($opts as $vals) { $n = []; foreach ($combos as $c) foreach ($vals as $v) $n[] = array_merge($c, [$v]); $combos = $n; }
    $vmap = [];
    foreach ($combos as $pos => $c) {
        $vt = $c ? implode(' / ', $c) : 'Default Title';
        $sku = strtoupper(substr(preg_replace('/[^a-z]/i', '', $key), 0, 3)) . '-' . str_pad((string)($pid * 100 + $pos), 5, '0', STR_PAD_LEFT);
        $vid = t_exec("INSERT INTO variants (product_id, title, option1, option2, price, compare_at_price, cost, sku, barcode, position) VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$pid, $vt, $c[0] ?? null, $c[1] ?? null, $price, $compare, $cost, $sku, '8941' . str_pad((string)random_int(0, 99999999), 9, '0', STR_PAD_LEFT), $pos]);
        $qty = $key === 'earbuds' ? 0 : random_int(8, 45);
        inv_set($vid, $L1, 'available', $qty);
        inv_set($vid, $L2, 'available', $key === 'earbuds' ? 0 : random_int(0, 20));
        $vmap[$vt] = $vid;
    }
    $P[$key] = ['id' => $pid, 'variants' => $vmap, 'title' => $title, 'price' => $price, 'image' => array_values($media)[0]['path']];
}
// gift card product (not tracked, no shipping)
$gid = t_exec("INSERT INTO products (title, handle, description_html, status, category, product_type, vendor, tags, options_json, is_gift_card, track_inventory, requires_shipping, theme_template) VALUES (?,?,?,?,?,?,?,?,?,1,0,0,'Gift card')",
    ['Pranto Shop Gift Card', 'gift-card', '<p>Give the gift of choice. Delivered by email and never expires.</p>', 'active', 'Gift cards', 'Gift card', setting('store_name', 'Store'), 'gift', json_encode([['name' => 'Denomination', 'values' => ['৳500', '৳1000', '৳2000']]])]);
$gf = $img('giftcard'); t_exec("INSERT INTO product_media (product_id, file_id, path, position) VALUES (?,?,?,0)", [$gid, $gf['id'], $gf['path']]);
foreach ([500, 1000, 2000] as $i => $amt) t_exec("INSERT INTO variants (product_id, title, option1, price, position) VALUES (?,?,?,?,?)", [$gid, '৳' . $amt, '৳' . $amt, $amt, $i]);
// one draft product
t_exec("INSERT INTO products (title, handle, description_html, status, product_type, vendor, tags) VALUES ('Bamboo Desk Organizer','bamboo-desk-organizer','<p>Coming soon.</p>','draft','Office','Padma Ceramics','office')");
$did = (int)t_val("SELECT LAST_INSERT_ID()");
$v = t_exec("INSERT INTO variants (product_id, title, price) VALUES (?,?,?)", [$did, 'Default Title', 1100]);
inv_set($v, $L1, 'available', 15);

/* ---------------- customers ---------------- */
$customers = [
    ['Naiem', 'Hossain', 'naiem.hossain@example.com', '01711234567', '', 'House 22, Road 11, Banani', 'Dhaka', '1213', 'vip', 1],
    ['Sakib', 'Uddin', 'sakib.uddin@example.com', '01812345678', '', 'Flat 4B, Lake Circus, Kalabagan', 'Dhaka', '1205', '', 0],
    ['Nusrat', 'Jahan', 'nusrat.jahan@example.com', '01913456789', '', '15 Zindabazar', 'Sylhet', '3100', 'vip,repeat', 1],
    ['Tanvir', 'Ahmed', 'tanvir.ahmed@example.com', '01614567890', 'Ahmed Traders', '45 Agrabad Commercial Area', 'Chattogram', '4100', 'wholesale', 0],
    ['Farhana', 'Akter', 'farhana.akter@example.com', '01515678901', '', 'Sector 7, Road 3, Uttara', 'Dhaka', '1230', '', 1],
    ['Rafiq', 'Islam', 'rafiq.islam@example.com', '01716789012', '', '88 Shaheb Bazar Road', 'Rajshahi', '6100', '', 0],
    ['Mitu', 'Rahman', 'mitu.rahman@example.com', '01817890123', '', 'KDA Avenue, Sonadanga', 'Khulna', '9100', 'newsletter', 1],
    ['Arif', 'Chowdhury', 'arif.chowdhury@example.com', '01918901234', 'Chowdhury Fashion House', 'Plot 9, Mirpur DOHS', 'Dhaka', '1216', 'wholesale,b2b', 0],
    ['Sumaiya', 'Khan', 'sumaiya.khan@example.com', '01619012345', '', 'Kazir Dewri, Kotwali', 'Chattogram', '4000', '', 1],
    ['Imran', 'Kabir', 'imran.kabir@example.com', '01520123456', '', 'Station Road, Comilla Sadar', 'Cumilla', '3500', '', 0],
];
$C = [];
foreach ($customers as $i => [$f, $l, $e, $ph, $co, $a, $city, $zip, $tags, $mk]) {
    $C[] = t_exec("INSERT INTO customers (first_name, last_name, email, phone, company, address1, city, zip, country, tags, accepts_marketing, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
        [$f, $l, $e, $ph, $co, $a, $city, $zip, 'Bangladesh', $tags, $mk, $ago(35 - $i * 2)]);
}

/* ---------------- collections ---------------- */
$col = function (string $title, string $type, array $products = [], array $conds = [], ?string $image = null) {
    $id = t_exec("INSERT INTO collections (title, handle, description_html, image_path, type, match_all, conditions_json) VALUES (?,?,?,?,?,1,?)",
        [$title, slugify($title), '<p>' . $title . ' picked for you.</p>', $image, $type, json_encode($conds)]);
    foreach (array_values($products) as $pos => $pid) t_exec("INSERT INTO collection_products (collection_id, product_id, position) VALUES (?,?,?)", [$id, $pid, $pos]);
};
$col('New Arrivals', 'manual', [$P['saree']['id'], $P['sneaker']['id'], $P['watch']['id'], $P['backpack']['id']], [], $P['sneaker']['image']);
$col("Men's Fashion", 'manual', [$P['panjabi']['id'], $P['wallet']['id'], $P['watch']['id'], $P['sunglasses']['id']], [], $P['panjabi']['image']);
$col('Eid Collection', 'manual', [$P['panjabi']['id'], $P['saree']['id'], $P['kantha']['id']], [], $P['saree']['image']);
$col('Under ৳1000', 'smart', [], [['field' => 'price', 'rel' => 'lt', 'value' => '1000']], $P['mug']['image']);
$col('Gadgets', 'smart', [], [['field' => 'type', 'rel' => 'equals', 'value' => 'Electronics']], $P['earbuds']['image']);

/* ---------------- orders (via real draft → order flow) ---------------- */
$v = fn(string $key, ?string $title = null) => $title ? $P[$key]['variants'][$title] : array_values($P[$key]['variants'])[0];
$makeDraft = function (?int $cid, array $lines, array $opt = []) use ($P) {
    $num = next_number('next_draft_number', 1);
    $c = $cid ? t_row("SELECT * FROM customers WHERE id=?", [$cid]) : null;
    $addr = $c ? json_encode(['first_name' => $c['first_name'], 'last_name' => $c['last_name'], 'address1' => $c['address1'], 'city' => $c['city'], 'zip' => $c['zip'], 'country' => 'Bangladesh']) : null;
    $id = t_exec("INSERT INTO draft_orders (number, customer_id, currency, tags, note, discount_type, discount_value, discount_reason, shipping_title, shipping_amount, payment_due_later, payment_terms, shipping_address_json, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
        [$num, $cid, 'BDT', $opt['tags'] ?? '', $opt['note'] ?? '', $opt['dtype'] ?? null, $opt['dval'] ?? 0, $opt['dreason'] ?? '', $opt['ship'] ?? 'Inside Dhaka', $opt['shipamt'] ?? 60, !empty($opt['terms']) ? 1 : 0, $opt['terms'] ?? null, $addr, staff_name()]);
    foreach ($lines as [$vid, $qty]) {
        $vr = t_row("SELECT v.*, p.title pt, p.id pid FROM variants v JOIN products p ON p.id=v.product_id WHERE v.id=?", [$vid]);
        t_exec("INSERT INTO draft_order_items (draft_order_id, variant_id, product_id, title, variant_title, image, price, quantity) VALUES (?,?,?,?,?,?,?,?)",
            [$id, $vid, $vr['pid'], $vr['pt'], $vr['title'] === 'Default Title' ? null : $vr['title'], first_image((int)$vr['pid']), $vr['price'], $qty]);
    }
    timeline_add('draft', $id, 'You created this draft order.');
    return $id;
};
$orders = [
    // [customer idx, lines, days ago, paid, fulfilled, opts]
    [0, [[$v('panjabi', 'White / L'), 1], [$v('wallet', 'Brown'), 1]], 28, 1, 1, ['tags' => 'eid']],
    [2, [[$v('saree', 'Red'), 1]], 25, 1, 1, ['ship' => 'Outside Dhaka', 'shipamt' => 120]],
    [1, [[$v('mug'), 2], [$v('tote'), 1]], 22, 1, 1, []],
    [3, [[$v('panjabi', 'Navy / M'), 5], [$v('panjabi', 'Navy / L'), 5]], 20, 1, 1, ['dtype' => 'percentage', 'dval' => 10, 'dreason' => 'Wholesale', 'terms' => 'net_15', 'tags' => 'wholesale', 'ship' => 'Outside Dhaka', 'shipamt' => 250]],
    [4, [[$v('sneaker', '41'), 1]], 17, 1, 1, []],
    [6, [[$v('bottle'), 1], [$v('sunglasses'), 1]], 14, 1, 1, ['ship' => 'Outside Dhaka', 'shipamt' => 120]],
    [0, [[$v('watch'), 1]], 11, 1, 1, ['tags' => 'vip']],
    [7, [[$v('backpack'), 3], [$v('wallet', 'Black'), 3]], 9, 0, 1, ['terms' => 'net_30', 'tags' => 'b2b', 'note' => 'Invoice to accounts team']],
    [8, [[$v('kantha'), 1]], 6, 1, 0, ['ship' => 'Outside Dhaka', 'shipamt' => 120]],
    [5, [[$v('panjabi', 'Maroon / XL'), 1], [$v('mug'), 1]], 4, 0, 0, ['ship' => 'Outside Dhaka', 'shipamt' => 120, 'note' => 'Cash on delivery']],
    [2, [[$v('saree', 'Teal'), 1], [$v('tote'), 1]], 2, 1, 0, []],
    [9, [[$v('sneaker', '42'), 1], [$v('bottle'), 2]], 1, 0, 0, ['note' => 'Call before delivery']],
    [4, [[$v('sunglasses'), 1]], 0, 0, 0, []],
];
foreach ($orders as [$ci, $lines, $days, $paid, $ful, $opt]) {
    $did = $makeDraft($C[$ci], $lines, $opt);
    $oid = draft_to_order($did, $paid ? 'paid' : 'pending');
    $when = $ago($days, 10 + $days % 8);
    t_exec("UPDATE orders SET created_at=?, channel='Online Store' WHERE id=?", [$when, $oid]);
    t_exec("UPDATE draft_orders SET created_at=?, updated_at=? WHERE id=?", [$when, $when, $did]);
    t_exec("UPDATE timeline SET created_at=? WHERE (subject_type='order' AND subject_id=?) OR (subject_type='draft' AND subject_id=?)", [$when, $oid, $did]);
    if ($ful) {
        $o = t_row("SELECT * FROM orders WHERE id=?", [$oid]);
        foreach (t_rows("SELECT * FROM order_items WHERE order_id=?", [$oid]) as $it) if ($it['variant_id']) inv_adjust((int)$it['variant_id'], (int)$o['location_id'], 'committed', -(int)$it['quantity']);
        $fd = date('Y-m-d H:i:s', strtotime($when) + 3600 * random_int(3, 30));
        t_exec("UPDATE orders SET fulfillment_status='fulfilled', fulfilled_at=?, delivery_status='Delivered' WHERE id=?", [$fd, $oid]);
        t_exec("INSERT INTO timeline (subject_type, subject_id, kind, message, author, created_at) VALUES ('order',?,'event','You fulfilled all items.',?,?)", [$oid, staff_name(), $fd]);
    }
}
// open drafts
$d1 = $makeDraft($C[1], [[$v('watch'), 1], [$v('wallet', 'Brown'), 1]], ['dtype' => 'fixed', 'dval' => 300, 'dreason' => 'Gift combo']);
$d2 = $makeDraft($C[7], [[$v('tote'), 20]], ['terms' => 'net_30', 'tags' => 'b2b', 'ship' => 'Outside Dhaka', 'shipamt' => 400]);
t_exec("UPDATE draft_orders SET status='invoice_sent' WHERE id=?", [$d2]);
timeline_add('draft', $d2, 'You sent an invoice to arif.chowdhury@example.com.');
$makeDraft(null, [[$v('mug'), 4]], ['note' => 'Office order — waiting for confirmation']);

/* ---------------- suppliers, purchase orders, transfers ---------------- */
$s1 = t_exec("INSERT INTO suppliers (name, contact_name, email, phone, address, country) VALUES ('Narayanganj Weavers Co-op','Abdul Karim','orders@nweavers.example','01710000001','Rupganj, Narayanganj','Bangladesh')");
$s2 = t_exec("INSERT INTO suppliers (name, contact_name, email, phone, address, country) VALUES ('Bengal Leather Works','Shirin Sultana','sales@bengalleather.example','01810000002','Hazaribagh, Dhaka','Bangladesh')");
$s3 = t_exec("INSERT INTO suppliers (name, contact_name, email, phone, address, country) VALUES ('Shenzhen Audio Ltd','Li Wei','export@szaudio.example','+8675500000','Bao''an, Shenzhen','China')");
$po = function (int $sup, string $status, array $lines, string $ref, int $days) use ($L1) {
    $id = t_exec("INSERT INTO purchase_orders (number, supplier_id, destination_id, status, reference, terms, currency, expected_arrival, shipping, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)",
        [next_number('next_po_number', 1), $sup, $L1, 'draft', $ref, 'Net 30', 'BDT', date('Y-m-d', strtotime('+' . (10 - $days) . ' days')), 500, date('Y-m-d H:i:s', strtotime("-$days days"))]);
    foreach ($lines as [$vid, $q]) t_exec("INSERT INTO purchase_order_items (purchase_order_id, variant_id, quantity, cost) VALUES (?,?,?,?)", [$id, $vid, $q, (float)t_val("SELECT cost FROM variants WHERE id=?", [$vid])]);
    timeline_add('purchase_order', $id, 'You created this purchase order.');
    if ($status !== 'draft') {
        foreach ($lines as [$vid, $q]) inv_adjust($vid, $L1, 'incoming', $q);
        t_exec("UPDATE purchase_orders SET status='ordered' WHERE id=?", [$id]);
        timeline_add('purchase_order', $id, 'You marked this purchase order as ordered.');
    }
    if ($status === 'received') {
        foreach (t_rows("SELECT * FROM purchase_order_items WHERE purchase_order_id=?", [$id]) as $i) {
            inv_adjust((int)$i['variant_id'], $L1, 'incoming', -(int)$i['quantity']); inv_adjust((int)$i['variant_id'], $L1, 'available', (int)$i['quantity']);
        }
        t_exec("UPDATE purchase_order_items SET received=quantity WHERE purchase_order_id=?", [$id]);
        t_exec("UPDATE purchase_orders SET status='received' WHERE id=?", [$id]);
        timeline_add('purchase_order', $id, 'You received all items.');
    }
};
$po($s1, 'received', [[$v('saree', 'Red'), 10], [$v('saree', 'Teal'), 10], [$v('kantha'), 8]], 'NW-2026-118', 18);
$po($s2, 'ordered', [[$v('wallet', 'Brown'), 30], [$v('wallet', 'Black'), 30], [$v('backpack'), 15]], 'BLW-5521', 5);
$po($s3, 'draft', [[$v('earbuds'), 50]], 'SZA-Q4', 1);
$tr = function (string $status, array $lines, string $ref, int $days) use ($L1, $L2) {
    $id = t_exec("INSERT INTO transfers (number, origin_id, destination_id, status, transfer_date, reference, created_at) VALUES (?,?,?,'draft',?,?,?)",
        [next_number('next_transfer_number', 1), $L1, $L2, date('Y-m-d', strtotime("-$days days")), $ref, date('Y-m-d H:i:s', strtotime("-$days days"))]);
    foreach ($lines as [$vid, $q]) t_exec("INSERT INTO transfer_items (transfer_id, variant_id, quantity) VALUES (?,?,?)", [$id, $vid, $q]);
    timeline_add('transfer', $id, 'You created this transfer.');
    foreach ($lines as [$vid, $q]) { inv_adjust($vid, $L1, 'available', -$q); inv_adjust($vid, $L2, 'incoming', $q); }
    t_exec("UPDATE transfers SET status='in_transit' WHERE id=?", [$id]);
    if ($status === 'received') {
        foreach ($lines as [$vid, $q]) { inv_adjust($vid, $L2, 'incoming', -$q); inv_adjust($vid, $L2, 'available', $q); }
        t_exec("UPDATE transfer_items SET received=quantity WHERE transfer_id=?", [$id]);
        t_exec("UPDATE transfers SET status='received' WHERE id=?", [$id]);
    }
};
$tr('received', [[$v('mug'), 6], [$v('tote'), 5]], 'Restock Chattogram', 12);
$tr('in_transit', [[$v('panjabi', 'White / M'), 4], [$v('bottle'), 6]], 'Eid stock move', 2);

/* ---------------- gift cards ---------------- */
$gc = function (string $code, float $init, float $bal, ?int $cid, ?string $exp, string $note = '') {
    $id = t_exec("INSERT INTO gift_cards (code, initial_value, balance, currency, expires_on, customer_id, note) VALUES (?,?,?,?,?,?,?)", [$code, $init, $bal, 'BDT', $exp, $cid, $note]);
    timeline_add('gift_card', $id, 'You issued this gift card.');
};
$gc('eidgift2026nusr', 2000, 2000, $C[2], null, 'Eid loyalty reward');
$gc('welcome500farha', 500, 120, $C[4], date('Y-m-d', strtotime('+6 months')));
$gc('corpgift2026arif', 5000, 5000, $C[7], date('Y-m-d', strtotime('+1 year')), 'Corporate bulk gift');

/* ---------------- abandoned checkouts ---------------- */
foreach ([[5, 4350, 3], [9, 1850, 1], [null, 6500, 0]] as [$ci, $tot, $days])
    t_exec("INSERT INTO checkouts (customer_id, email, total, email_status, created_at) VALUES (?,?,?,?,?)",
        [$ci !== null ? $C[$ci] : null, $ci !== null ? null : 'guest.shopper@example.com', $tot, $days ? 'Sent' : 'Scheduled', date('Y-m-d H:i:s', strtotime("-$days days -3 hours"))]);

set_setting('sessions_30d', '1248');
set_setting('demo_seeded', date('c'));
$n = fn($t) => t_val("SELECT COUNT(*) FROM $t");
echo "Seeded $db: {$n('products')} products, {$n('customers')} customers, {$n('orders')} orders, {$n('draft_orders')} drafts, {$n('collections')} collections, {$n('purchase_orders')} POs, {$n('transfers')} transfers, {$n('gift_cards')} gift cards\n";
