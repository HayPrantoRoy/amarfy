<?php
$in = input();
switch ($action) {
    case 'preview':
        $ids = segment_customer_ids(['rules_json' => json_encode($in['rules'] ?? []), 'match_all' => !empty($in['match_all'])]);
        $total = (int)t_val("SELECT COUNT(*) FROM customers");
        $rows = $ids ? t_rows("SELECT id, first_name, last_name, email, city FROM customers WHERE id IN (" . implode(',', $ids) . ") ORDER BY first_name LIMIT 50") : [];
        json_out(['ok' => true, 'count' => count($ids), 'percent' => $total ? round(count($ids) / $total * 100) : 0,
            'customers' => array_map(fn($c) => ['id' => (int)$c['id'], 'name' => customer_name($c), 'email' => $c['email'], 'city' => $c['city']], $rows)]);

    case 'save':
        $id = (int)($in['id'] ?? 0);
        $name = trim((string)($in['name'] ?? ''));
        if ($name === '') json_err('Give the segment a name');
        $fields = segment_fields();
        $rules = array_values(array_filter((array)($in['rules'] ?? []), fn($r) => isset($fields[$r['field'] ?? '']) && trim((string)($r['value'] ?? '')) !== ''));
        if ($id) {
            $s = t_row("SELECT * FROM segments WHERE id=?", [$id]);
            if (!$s) json_err('Segment not found', 404);
            if ($s['rule_key']) { t_exec("UPDATE segments SET name=? WHERE id=?", [$name, $id]); json_out(['ok' => true, 'id' => $id]); }
        }
        if (!$rules) json_err('Add at least one condition');
        $f = [$name, json_encode($rules, JSON_UNESCAPED_UNICODE), !empty($in['match_all']) ? 1 : 0];
        if ($id) t_exec("UPDATE segments SET name=?, rules_json=?, match_all=? WHERE id=?", [...$f, $id]);
        else $id = t_exec("INSERT INTO segments (name, rules_json, match_all, created_by) VALUES (?,?,?,?)", [...$f, staff_name()]);
        json_out(['ok' => true, 'id' => $id]);

    case 'delete':
        $id = (int)($in['id'] ?? 0);
        t_exec("DELETE FROM segments WHERE id=?", [$id]);
        json_out(['ok' => true]);

    case 'bulk':
        if (($in['action'] ?? '') === 'delete') foreach (array_map('intval', (array)($in['ids'] ?? [])) as $i) t_exec("DELETE FROM segments WHERE id=?", [$i]);
        json_out(['ok' => true]);
}
