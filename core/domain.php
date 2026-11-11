<?php
/** Business logic shared by pages and API handlers. */

function locations(): array { return t_rows("SELECT * FROM locations ORDER BY is_default DESC, id"); }
function default_location_id(): int { return (int)(t_val("SELECT id FROM locations ORDER BY is_default DESC, id LIMIT 1") ?? 0); }

/** Add $delta to one inventory column for a variant at a location (creates the row if missing). */
function inv_adjust(int $variantId, int $locationId, string $field, int $delta): void
{
    if (!in_array($field, ['available', 'committed', 'unavailable', 'incoming'], true) || !$locationId) return;
    t_exec("INSERT INTO inventory_levels (variant_id, location_id, `$field`) VALUES (?,?,?) ON DUPLICATE KEY UPDATE `$field` = `$field` + VALUES(`$field`)", [$variantId, $locationId, $delta]);
}
function inv_set(int $variantId, int $locationId, string $field, int $value): void
{
    if (!in_array($field, ['available', 'committed', 'unavailable', 'incoming'], true)) return;
    t_exec("INSERT INTO inventory_levels (variant_id, location_id, `$field`) VALUES (?,?,?) ON DUPLICATE KEY UPDATE `$field` = VALUES(`$field`)", [$variantId, $locationId, $value]);
}
function variant_available(int $variantId, ?int $locationId = null): int
{
    return $locationId
        ? (int)(t_val("SELECT available FROM inventory_levels WHERE variant_id=? AND location_id=?", [$variantId, $locationId]) ?? 0)
        : (int)(t_val("SELECT COALESCE(SUM(available),0) FROM inventory_levels WHERE variant_id=?", [$variantId]) ?? 0);
}

/** Products with variants in the shape the client-side picker expects. */
function product_search(string $q, string $by = 'all', ?int $locationId = null, int $limit = 60): array
{
    $where = '1=1'; $p = [];
    if ($q !== '') {
        $like = '%' . $q . '%';
        switch ($by) {
            case 'title': $where = 'p.title LIKE ?'; $p = [$like]; break;
            case 'sku': $where = 'EXISTS (SELECT 1 FROM variants v2 WHERE v2.product_id=p.id AND v2.sku LIKE ?)'; $p = [$like]; break;
            case 'vendor': $where = 'p.vendor LIKE ?'; $p = [$like]; break;
            case 'type': $where = 'p.product_type LIKE ?'; $p = [$like]; break;
            default: $where = '(p.title LIKE ? OR p.vendor LIKE ? OR p.product_type LIKE ? OR p.tags LIKE ? OR EXISTS (SELECT 1 FROM variants v2 WHERE v2.product_id=p.id AND (v2.sku LIKE ? OR v2.barcode LIKE ?)))'; $p = array_fill(0, 6, $like);
        }
    }
    $products = t_rows("SELECT p.id, p.title, p.status, p.track_inventory, p.vendor, p.product_type,
        (SELECT path FROM product_media m WHERE m.product_id=p.id ORDER BY position, id LIMIT 1) image
        FROM products p WHERE $where ORDER BY p.title LIMIT " . (int)$limit, $p);
    if (!$products) return [];
    $ids = implode(',', array_map(fn($r) => (int)$r['id'], $products));
    $locSql = $locationId ? ' AND il.location_id=' . (int)$locationId : '';
    $vars = t_rows("SELECT v.*, COALESCE((SELECT SUM(il.available) FROM inventory_levels il WHERE il.variant_id=v.id $locSql),0) available
        FROM variants v WHERE v.product_id IN ($ids) ORDER BY v.position, v.id");
    $byP = [];
    foreach ($vars as $v) $byP[$v['product_id']][] = ['id' => (int)$v['id'], 'title' => $v['title'], 'price' => (float)$v['price'], 'cost' => (float)($v['cost'] ?? 0), 'sku' => $v['sku'], 'available' => (int)$v['available']];
    return array_map(fn($r) => ['id' => (int)$r['id'], 'title' => $r['title'], 'status' => $r['status'], 'track' => (int)$r['track_inventory'], 'image' => $r['image'], 'variants' => $byP[$r['id']] ?? []], $products);
}

/** Totals for a draft or order line list. */
function calc_totals(array $items, ?string $discType, float $discValue, float $shipping): array
{
    $subtotal = 0; $count = 0;
    foreach ($items as $i) { $subtotal += (float)$i['price'] * (int)$i['quantity']; $count += (int)$i['quantity']; }
    $discount = 0.0;
    if ($discType === 'percentage') $discount = round($subtotal * min(100, max(0, $discValue)) / 100, 2);
    elseif ($discType === 'fixed') $discount = min($subtotal, max(0, $discValue));
    $total = max(0, $subtotal - $discount + $shipping);
    return ['subtotal' => round($subtotal, 2), 'discount' => round($discount, 2), 'shipping' => round($shipping, 2), 'total' => round($total, 2), 'count' => $count];
}

/** Atomically take the next sequence number (safe across concurrent requests). */
function next_number(string $key, int $default): int
{
    t_exec("INSERT IGNORE INTO settings (k, v) VALUES (?, ?)", [$key, (string)$default]);
    t_exec("UPDATE settings SET v = LAST_INSERT_ID(CAST(v AS UNSIGNED) + 1) WHERE k = ?", [$key]);
    $next = (int)t_val("SELECT LAST_INSERT_ID()");
    if (isset($GLOBALS['__settings'])) $GLOBALS['__settings'][$key] = (string)$next;
    return $next - 1;
}

/** Convert a saved draft into a real order. Commits inventory at the default location. */
function draft_to_order(int $draftId, string $paymentStatus): int
{
    $d = t_row("SELECT * FROM draft_orders WHERE id=?", [$draftId]);
    if (!$d) throw new RuntimeException('Draft not found');
    if ($d['status'] === 'completed') throw new RuntimeException('This draft order is already completed');
    $items = t_rows("SELECT * FROM draft_order_items WHERE draft_order_id=?", [$draftId]);
    if (!$items) throw new RuntimeException('Add a product before creating the order');
    $t = calc_totals($items, $d['discount_type'], (float)$d['discount_value'], (float)$d['shipping_amount']);
    $loc = default_location_id();
    $num = next_number('next_order_number', 1001);
    $fulfillBy = null;
    if ($d['payment_terms'] && preg_match('/^net_(\d+)$/', $d['payment_terms'], $mm)) $fulfillBy = date('Y-m-d', strtotime('+' . $mm[1] . ' days'));
    $oid = t_exec("INSERT INTO orders (number, customer_id, draft_order_id, location_id, channel, currency, subtotal, discount, shipping, total, payment_status, delivery_method, payment_terms, fulfill_by, tags, note, shipping_address_json)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
        [$num, $d['customer_id'], $draftId, $loc, 'Draft Orders', $d['currency'], $t['subtotal'], $t['discount'], $t['shipping'], $t['total'], $paymentStatus,
         $d['shipping_title'] ?: 'Standard shipping', $d['payment_terms'], $fulfillBy, $d['tags'], $d['note'], $d['shipping_address_json']]);
    foreach ($items as $i) {
        t_exec("INSERT INTO order_items (order_id, variant_id, product_id, title, variant_title, image, price, quantity) VALUES (?,?,?,?,?,?,?,?)",
            [$oid, $i['variant_id'], $i['product_id'], $i['title'], $i['variant_title'], $i['image'], $i['price'], $i['quantity']]);
        if ($i['variant_id']) { inv_adjust((int)$i['variant_id'], $loc, 'available', -(int)$i['quantity']); inv_adjust((int)$i['variant_id'], $loc, 'committed', (int)$i['quantity']); }
    }
    t_exec("UPDATE draft_orders SET status='completed', order_id=? WHERE id=?", [$oid, $draftId]);
    timeline_add('draft', $draftId, 'You created order #' . $num . ' from this draft order.');
    timeline_add('order', $oid, 'Order was created from draft order #D' . $d['number'] . '.');
    if ($paymentStatus === 'paid') timeline_add('order', $oid, 'A ' . money($t['total']) . ' ' . cur() . ' payment was processed manually.');
    return $oid;
}

function timeline_html(string $type, int $id): string
{
    $ev = t_rows("SELECT * FROM timeline WHERE subject_type=? AND subject_id=? ORDER BY created_at DESC, id DESC", [$type, $id]);
    $u = current_user();
    $o = '<div class="timeline" data-tl-type="' . h($type) . '" data-tl-id="' . $id . '"><div class="tl-comment"><div class="row"><span class="tl-avatar">' . h(initials($u['name'] ?? 'A')) . '</span><textarea placeholder="Leave a comment..." data-tl-text></textarea></div>
      <div class="tl-tools"><button type="button" class="icon-btn" data-tl-ins="🙂">' . icon('emoji') . '</button><button type="button" class="icon-btn" data-tl-ins="@">' . icon('at') . '</button><button type="button" class="icon-btn" data-tl-ins="#">' . icon('hash') . '</button><button type="button" class="icon-btn" data-tl-ins="https://">' . icon('link') . '</button><button type="button" class="btn tonal post" data-tl-post disabled>Post</button></div></div>
      <div class="tl-note">Only you and other staff can see comments</div><div class="tl-list">';
    $lastDay = null;
    foreach ($ev as $e) {
        $day = date('Y-m-d', strtotime($e['created_at']));
        if ($day !== $lastDay) {
            $label = $day === date('Y-m-d') ? 'Today' : ($day === date('Y-m-d', strtotime('-1 day')) ? 'Yesterday' : date('F j', strtotime($day)));
            $o .= '<div class="tl-day">' . h($label) . '</div>';
            $lastDay = $day;
        }
        $when = (time() - strtotime($e['created_at']) < 60) ? 'Just now' : strtolower(date('g:i a', strtotime($e['created_at'])));
        $msg = $e['kind'] === 'comment' ? '<b>' . h($e['author']) . '</b><div>' . nl2br(h($e['message'])) . '</div>' : h($e['message']);
        $o .= '<div class="tl-ev ' . h($e['kind']) . '"><div class="msg">' . $msg . '</div><span class="when">' . h($when) . '</span></div>';
    }
    return $o . '</div></div>';
}

/** Products matched by a smart collection's conditions. */
function smart_collection_product_ids(array $conds, bool $matchAll, array $excluded = []): array
{
    if (!$conds) return [];
    $parts = []; $params = [];
    foreach ($conds as $c) {
        $field = $c['field'] ?? 'title'; $rel = $c['rel'] ?? 'equals'; $val = (string)($c['value'] ?? '');
        $col = match ($field) {
            'title' => 'p.title', 'type' => 'p.product_type', 'vendor' => 'p.vendor', 'tag' => 'p.tags', 'category' => 'p.category',
            'price' => '(SELECT MIN(v.price) FROM variants v WHERE v.product_id=p.id)',
            'compare_at' => '(SELECT MAX(v.compare_at_price) FROM variants v WHERE v.product_id=p.id)',
            'stock' => '(SELECT COALESCE(SUM(il.available),0) FROM inventory_levels il JOIN variants v ON v.id=il.variant_id WHERE v.product_id=p.id)',
            'weight' => 'p.weight', default => 'p.title',
        };
        [$op, $v] = match ($rel) {
            'not_equals' => ['<> ?', $val], 'gt' => ['> ?', (float)$val], 'lt' => ['< ?', (float)$val],
            'starts' => ['LIKE ?', $val . '%'], 'ends' => ['LIKE ?', '%' . $val], 'contains' => ['LIKE ?', '%' . $val . '%'],
            'not_contains' => ['NOT LIKE ?', '%' . $val . '%'], default => ['= ?', $val],
        };
        if ($field === 'tag' && $rel === 'equals') { $op = 'LIKE ?'; $v = '%' . $val . '%'; }
        $parts[] = "$col $op"; $params[] = $v;
    }
    $where = implode($matchAll ? ' AND ' : ' OR ', $parts);
    $ids = array_map(fn($r) => (int)$r['id'], t_rows("SELECT p.id FROM products p WHERE ($where)", $params));
    return array_values(array_diff($ids, array_map('intval', $excluded)));
}

function collection_product_ids(array $c): array
{
    if ($c['type'] === 'smart') return smart_collection_product_ids(json_decode($c['conditions_json'] ?: '[]', true) ?: [], (bool)$c['match_all'], json_decode($c['excluded_json'] ?: '[]', true) ?: []);
    return array_map(fn($r) => (int)$r['product_id'], t_rows("SELECT product_id FROM collection_products WHERE collection_id=? ORDER BY position", [(int)$c['id']]));
}

function payment_terms_options(): array
{
    return ['receipt' => 'Due on receipt', 'fulfillment' => 'Due on fulfillment', 'net_7' => 'Within 7 days', 'net_15' => 'Within 15 days', 'net_30' => 'Within 30 days', 'net_45' => 'Within 45 days', 'net_60' => 'Within 60 days', 'net_90' => 'Within 90 days'];
}
