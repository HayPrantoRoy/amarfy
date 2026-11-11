<?php
if ($action === 'set') {
    $in = input();
    $vid = (int)($in['variant_id'] ?? 0);
    $loc = (int)($in['location_id'] ?? 0);
    $field = (string)($in['field'] ?? 'available');
    $value = (int)($in['value'] ?? 0);
    if (!t_val("SELECT id FROM variants WHERE id=?", [$vid]) || !t_val("SELECT id FROM locations WHERE id=?", [$loc])) json_err('Unknown variant or location');
    $row = t_row("SELECT * FROM inventory_levels WHERE variant_id=? AND location_id=?", [$vid, $loc]) ?: ['available' => 0, 'committed' => 0, 'unavailable' => 0, 'incoming' => 0];
    if ($field === 'on_hand') {
        // on hand = available + committed + unavailable → adjust available to reach the new on hand
        $value = $value - (int)$row['committed'] - (int)$row['unavailable'];
        $field = 'available';
    }
    if (!in_array($field, ['available', 'unavailable'], true)) json_err('Field can’t be edited');
    inv_set($vid, $loc, $field, $value);
    $r = t_row("SELECT * FROM inventory_levels WHERE variant_id=? AND location_id=?", [$vid, $loc]);
    json_out(['ok' => true, 'level' => $r + ['on_hand' => (int)$r['available'] + (int)$r['committed'] + (int)$r['unavailable']]]);
}
if ($action === 'locations') json_out(['ok' => true, 'locations' => locations()]);
if ($action === 'add_location') {
    $name = trim((string)(input()['name'] ?? ''));
    if ($name === '') json_err('Enter a location name');
    $id = t_exec("INSERT INTO locations (name) VALUES (?)", [$name]);
    json_out(['ok' => true, 'location' => ['id' => $id, 'name' => $name]]);
}
