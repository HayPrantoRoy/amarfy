<?php
function product_delete(int $id): void {
    foreach (t_rows("SELECT id FROM variants WHERE product_id=?", [$id]) as $v) t_exec("DELETE FROM inventory_levels WHERE variant_id=?", [(int)$v['id']]);
    t_exec("DELETE FROM variants WHERE product_id=?", [$id]);
    t_exec("DELETE FROM product_media WHERE product_id=?", [$id]);
    t_exec("DELETE FROM collection_products WHERE product_id=?", [$id]);
    t_exec("DELETE FROM products WHERE id=?", [$id]);
}
function dec_or_null($v) { return ($v === '' || $v === null) ? null : max(0, (float)$v); }

switch ($action) {
    case 'search':
        $loc = isset($_GET['location']) ? (int)$_GET['location'] : null;
        json_out(['ok' => true, 'products' => product_search(trim((string)($_GET['q'] ?? '')), (string)($_GET['by'] ?? 'all'), $loc ?: null)]);

    case 'save':
        $in = input();
        $id = (int)($in['id'] ?? 0);
        $title = trim((string)($in['title'] ?? ''));
        if ($title === '') json_err('Title can’t be blank');
        if (mb_strlen($title) > 255) json_err('Title is too long');
        if ($id && !t_val("SELECT id FROM products WHERE id=?", [$id])) json_err('Product not found', 404);
        $status = in_array($in['status'] ?? '', ['active', 'draft', 'archived'], true) ? $in['status'] : 'active';
        $tags = implode(',', array_filter(array_map('trim', (array)($in['tags'] ?? []))));
        $options = array_values(array_filter((array)($in['options'] ?? []), fn($o) => trim((string)($o['name'] ?? '')) !== '' && !empty($o['values'])));
        $handle = slugify((string)($in['handle'] ?? '') ?: $title);
        $f = [
            $title, $handle, (string)($in['description_html'] ?? ''), $status, trim((string)($in['category'] ?? '')), trim((string)($in['product_type'] ?? '')), trim((string)($in['vendor'] ?? '')),
            (string)($in['theme_template'] ?? 'Default product'), $tags, json_encode($options, JSON_UNESCAPED_UNICODE), !empty($in['is_gift_card']) ? 1 : 0,
            !empty($in['charge_tax']) ? 1 : 0, !empty($in['track_inventory']) ? 1 : 0, !empty($in['requires_shipping']) ? 1 : 0,
            (float)($in['weight'] ?? 0), in_array($in['weight_unit'] ?? '', ['kg', 'g', 'lb', 'oz'], true) ? $in['weight_unit'] : 'kg',
            (float)($in['length'] ?? 0), (float)($in['width'] ?? 0), (float)($in['height'] ?? 0), in_array($in['dim_unit'] ?? '', ['cm', 'in', 'm'], true) ? $in['dim_unit'] : 'cm',
            trim((string)($in['package'] ?? '')), trim((string)($in['origin_country'] ?? '')), trim((string)($in['hs_code'] ?? '')),
            json_encode(array_values((array)($in['metafields'] ?? [])), JSON_UNESCAPED_UNICODE), json_encode(array_values((array)($in['purchase_options'] ?? [])), JSON_UNESCAPED_UNICODE),
            trim((string)($in['seo_title'] ?? '')), trim((string)($in['seo_description'] ?? '')),
        ];
        $cols = 'title=?, handle=?, description_html=?, status=?, category=?, product_type=?, vendor=?, theme_template=?, tags=?, options_json=?, is_gift_card=?, charge_tax=?, track_inventory=?, requires_shipping=?, weight=?, weight_unit=?, length=?, width=?, height=?, dim_unit=?, package=?, origin_country=?, hs_code=?, metafields_json=?, purchase_options_json=?, seo_title=?, seo_description=?';
        $isNew = !$id;
        if ($id) t_exec("UPDATE products SET $cols WHERE id=?", [...$f, $id]);
        else $id = t_exec("INSERT INTO products SET $cols", $f);

        // media
        t_exec("DELETE FROM product_media WHERE product_id=?", [$id]);
        foreach (array_values((array)($in['media'] ?? [])) as $pos => $m) {
            $path = (string)($m['path'] ?? '');
            if (!preg_match('#^uploads/[a-z0-9_]+/[A-Za-z0-9_.-]+$#', $path)) continue;
            t_exec("INSERT INTO product_media (product_id, file_id, path, position) VALUES (?,?,?,?)", [$id, !empty($m['id']) ? (int)$m['id'] : null, $path, $pos]);
        }

        // variants: keep ids that still exist, delete removed ones
        $variants = array_values((array)($in['variants'] ?? []));
        if (!$variants) $variants = [['title' => 'Default Title', 'price' => 0]];
        $keep = [];
        foreach ($variants as $pos => $v) {
            $vid = (int)($v['id'] ?? 0);
            if ($vid && !t_val("SELECT id FROM variants WHERE id=? AND product_id=?", [$vid, $id])) $vid = 0;
            $vf = [trim((string)($v['title'] ?? 'Default Title')) ?: 'Default Title', $v['option1'] ?? null, $v['option2'] ?? null, $v['option3'] ?? null,
                max(0, (float)($v['price'] ?? 0)), dec_or_null($v['compare_at_price'] ?? null), dec_or_null($v['cost'] ?? null), dec_or_null($v['unit_price'] ?? null),
                trim((string)($v['sku'] ?? '')), trim((string)($v['barcode'] ?? '')), !empty($v['sell_out_of_stock']) ? 1 : 0, $pos];
            if ($vid) t_exec("UPDATE variants SET title=?, option1=?, option2=?, option3=?, price=?, compare_at_price=?, cost=?, unit_price=?, sku=?, barcode=?, sell_out_of_stock=?, position=? WHERE id=?", [...$vf, $vid]);
            else $vid = t_exec("INSERT INTO variants (title, option1, option2, option3, price, compare_at_price, cost, unit_price, sku, barcode, sell_out_of_stock, position, product_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)", [...$vf, $id]);
            $keep[] = $vid;
            foreach ((array)($v['inventory'] ?? []) as $locId => $qty) {
                if ($qty === '' || $qty === null) continue;
                inv_set($vid, (int)$locId, 'available', (int)$qty);
            }
        }
        foreach (t_rows("SELECT id FROM variants WHERE product_id=?", [$id]) as $r) {
            if (!in_array((int)$r['id'], $keep, true)) { t_exec("DELETE FROM inventory_levels WHERE variant_id=?", [(int)$r['id']]); t_exec("DELETE FROM variants WHERE id=?", [(int)$r['id']]); }
        }

        // manual collections
        if (isset($in['collections'])) {
            $manual = array_map(fn($r) => (int)$r['id'], t_rows("SELECT id FROM collections WHERE type='manual'"));
            foreach ($manual as $cid) t_exec("DELETE FROM collection_products WHERE collection_id=? AND product_id=?", [$cid, $id]);
            foreach (array_map('intval', (array)$in['collections']) as $cid) if (in_array($cid, $manual, true)) t_exec("INSERT IGNORE INTO collection_products (collection_id, product_id) VALUES (?,?)", [$cid, $id]);
        }
        timeline_add('product', $id, $isNew ? 'You created this product.' : 'You updated this product.');
        json_out(['ok' => true, 'id' => $id]);

    case 'delete':
        product_delete((int)(input()['id'] ?? 0));
        json_out(['ok' => true]);

    case 'duplicate':
        $id = (int)(input()['id'] ?? 0);
        $p = t_row("SELECT * FROM products WHERE id=?", [$id]);
        if (!$p) json_err('Product not found', 404);
        unset($p['id'], $p['created_at'], $p['updated_at']);
        $p['title'] = 'Copy of ' . $p['title']; $p['status'] = 'draft'; $p['handle'] = slugify($p['title']);
        $cols = implode(',', array_map(fn($k) => "`$k`=?", array_keys($p)));
        $new = t_exec("INSERT INTO products SET $cols", array_values($p));
        foreach (t_rows("SELECT * FROM variants WHERE product_id=?", [$id]) as $v) {
            t_exec("INSERT INTO variants (product_id, title, option1, option2, option3, price, compare_at_price, cost, unit_price, sku, barcode, sell_out_of_stock, position) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$new, $v['title'], $v['option1'], $v['option2'], $v['option3'], $v['price'], $v['compare_at_price'], $v['cost'], $v['unit_price'], $v['sku'], $v['barcode'], $v['sell_out_of_stock'], $v['position']]);
        }
        t_exec("INSERT INTO product_media (product_id, file_id, path, position) SELECT ?, file_id, path, position FROM product_media WHERE product_id=?", [$new, $id]);
        json_out(['ok' => true, 'id' => $new]);

    case 'bulk':
        $in = input();
        foreach (array_map('intval', (array)($in['ids'] ?? [])) as $i) match ($in['action'] ?? '') {
            'active', 'draft', 'archived' => t_exec("UPDATE products SET status=? WHERE id=?", [$in['action'], $i]),
            'delete' => product_delete($i),
            default => null,
        };
        json_out(['ok' => true]);

    case 'import':
        $f = $_FILES['file'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) json_err('Choose a CSV file');
        $h = fopen($f['tmp_name'], 'r');
        $head = array_map(fn($x) => strtolower(trim((string)$x)), fgetcsv($h) ?: []);
        $col = fn($row, $name) => ($i = array_search($name, $head, true)) !== false ? trim((string)($row[$i] ?? '')) : '';
        $n = 0; $loc = default_location_id();
        while (($row = fgetcsv($h)) !== false) {
            $title = $col($row, 'title');
            if ($title === '') continue;
            $status = in_array($s = strtolower($col($row, 'status')), ['active', 'draft', 'archived'], true) ? $s : 'active';
            $pid = t_exec("INSERT INTO products (title, handle, description_html, status, vendor, product_type, tags) VALUES (?,?,?,?,?,?,?)",
                [$title, slugify($title), $col($row, 'description'), $status, $col($row, 'vendor'), $col($row, 'type'), $col($row, 'tags')]);
            $vid = t_exec("INSERT INTO variants (product_id, title, price, compare_at_price, sku, barcode) VALUES (?,?,?,?,?,?)",
                [$pid, 'Default Title', (float)$col($row, 'price'), dec_or_null($col($row, 'compare at price')), $col($row, 'sku'), $col($row, 'barcode')]);
            inv_set($vid, $loc, 'available', (int)$col($row, 'quantity'));
            $n++;
        }
        fclose($h);
        json_out(['ok' => true, 'count' => $n]);
}
