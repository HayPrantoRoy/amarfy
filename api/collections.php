<?php
function coll_items_json(array $ids): array {
    if (!$ids) return [];
    $in = implode(',', array_map('intval', $ids));
    $rows = t_rows("SELECT p.id, p.title, p.status, (SELECT MIN(price) FROM variants v WHERE v.product_id=p.id) price,
        (SELECT path FROM product_media m WHERE m.product_id=p.id ORDER BY position, id LIMIT 1) image FROM products p WHERE p.id IN ($in)");
    $by = []; foreach ($rows as $r) $by[$r['id']] = $r;
    return array_values(array_filter(array_map(fn($i) => $by[$i] ?? null, $ids)));
}

switch ($action) {
    case 'preview':
        $in = input();
        $ids = ($in['type'] ?? 'manual') === 'smart'
            ? smart_collection_product_ids((array)($in['conditions'] ?? []), !empty($in['match_all']), (array)($in['excluded'] ?? []))
            : array_map('intval', (array)($in['products'] ?? []));
        json_out(['ok' => true, 'items' => coll_items_json($ids)]);

    case 'save':
        $in = input();
        $id = (int)($in['id'] ?? 0);
        $title = trim((string)($in['title'] ?? ''));
        if ($title === '') json_err('Add a title');
        $type = ($in['type'] ?? 'manual') === 'smart' ? 'smart' : 'manual';
        $img = (string)($in['image_path'] ?? '');
        if ($img !== '' && !preg_match('#^uploads/[a-z0-9_]+/[A-Za-z0-9_.-]+$#', $img)) $img = '';
        $conds = array_values(array_filter((array)($in['conditions'] ?? []), fn($c) => trim((string)($c['value'] ?? '')) !== ''));
        if ($type === 'smart' && !$conds) json_err('Add at least one condition');
        $f = [$title, slugify((string)($in['handle'] ?? '') ?: $title), (string)($in['description_html'] ?? ''), $img ?: null, $type, !empty($in['match_all']) ? 1 : 0,
              json_encode($conds, JSON_UNESCAPED_UNICODE), json_encode(array_map('intval', (array)($in['excluded'] ?? []))),
              (string)($in['theme_template'] ?? 'Default collection'), max(0, min(3, (int)($in['channels'] ?? 3))), trim((string)($in['seo_title'] ?? '')), trim((string)($in['seo_description'] ?? ''))];
        $cols = 'title=?, handle=?, description_html=?, image_path=?, type=?, match_all=?, conditions_json=?, excluded_json=?, theme_template=?, channels=?, seo_title=?, seo_description=?';
        if ($id) t_exec("UPDATE collections SET $cols WHERE id=?", [...$f, $id]);
        else $id = t_exec("INSERT INTO collections SET $cols", $f);
        t_exec("DELETE FROM collection_products WHERE collection_id=?", [$id]);
        if ($type === 'manual') foreach (array_values(array_map('intval', (array)($in['products'] ?? []))) as $pos => $pid)
            t_exec("INSERT IGNORE INTO collection_products (collection_id, product_id, position) VALUES (?,?,?)", [$id, $pid, $pos]);
        json_out(['ok' => true, 'id' => $id]);

    case 'delete':
        $id = (int)(input()['id'] ?? 0);
        t_exec("DELETE FROM collection_products WHERE collection_id=?", [$id]);
        t_exec("DELETE FROM collections WHERE id=?", [$id]);
        json_out(['ok' => true]);

    case 'bulk':
        $in = input();
        if (($in['action'] ?? '') === 'delete') foreach (array_map('intval', (array)($in['ids'] ?? [])) as $i) {
            t_exec("DELETE FROM collection_products WHERE collection_id=?", [$i]);
            t_exec("DELETE FROM collections WHERE id=?", [$i]);
        }
        json_out(['ok' => true]);

    case 'list':
        json_out(['ok' => true, 'collections' => t_rows("SELECT id, title, type FROM collections ORDER BY title")]);
}
