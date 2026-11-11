<?php
if ($action === 'comment') {
    $in = input();
    $type = (string)($in['type'] ?? '');
    $id = (int)($in['id'] ?? 0);
    $msg = trim((string)($in['message'] ?? ''));
    if (!in_array($type, ['draft', 'order', 'customer', 'gift_card', 'purchase_order', 'transfer', 'product'], true) || !$id) json_err('Bad subject');
    if ($msg === '' || mb_strlen($msg) > 5000) json_err('Write a comment (max 5000 characters)');
    timeline_add($type, $id, $msg, 'comment');
    json_out(['ok' => true, 'html' => timeline_html($type, $id)]);
}
