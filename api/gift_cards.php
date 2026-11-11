<?php
switch ($action) {
    case 'save':
        $in = input();
        $id = (int)($in['id'] ?? 0);
        $customer = !empty($in['customer_id']) ? (int)$in['customer_id'] : null;
        $expires = ($in['expires_on'] ?? '') ?: null;
        if ($expires && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires)) json_err('Invalid expiry date');
        if ($id) {
            if (!t_val("SELECT id FROM gift_cards WHERE id=?", [$id])) json_err('Gift card not found', 404);
            t_exec("UPDATE gift_cards SET expires_on=?, customer_id=?, recipient=?, note=? WHERE id=?", [$expires, $customer, trim((string)($in['recipient'] ?? '')), trim((string)($in['note'] ?? '')), $id]);
            timeline_add('gift_card', $id, 'You updated this gift card.');
        } else {
            $code = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)($in['code'] ?? '')));
            if (strlen($code) < 8 || strlen($code) > 20) json_err('Gift card code must be 8–20 letters or numbers');
            if (t_val("SELECT id FROM gift_cards WHERE code=?", [$code])) json_err('This code is already in use');
            $value = (float)($in['initial_value'] ?? 0);
            if ($value <= 0) json_err('Initial value must be greater than 0');
            $id = t_exec("INSERT INTO gift_cards (code, initial_value, balance, currency, expires_on, customer_id, recipient, note) VALUES (?,?,?,?,?,?,?,?)",
                [$code, $value, $value, cur(), $expires, $customer, trim((string)($in['recipient'] ?? '')), trim((string)($in['note'] ?? ''))]);
            timeline_add('gift_card', $id, 'You issued this gift card.');
        }
        json_out(['ok' => true, 'id' => $id]);

    case 'toggle':
        $id = (int)(input()['id'] ?? 0);
        t_exec("UPDATE gift_cards SET status=IF(status='active','disabled','active') WHERE id=?", [$id]);
        timeline_add('gift_card', $id, t_val("SELECT status FROM gift_cards WHERE id=?", [$id]) === 'active' ? 'You enabled this gift card.' : 'You disabled this gift card.');
        json_out(['ok' => true]);

    case 'adjust':
        $in = input();
        $id = (int)($in['id'] ?? 0);
        $bal = max(0, (float)($in['balance'] ?? 0));
        t_exec("UPDATE gift_cards SET balance=? WHERE id=?", [$bal, $id]);
        timeline_add('gift_card', $id, 'You adjusted the balance to ' . money($bal) . '.');
        json_out(['ok' => true]);

    case 'bulk':
        $in = input();
        foreach (array_map('intval', (array)($in['ids'] ?? [])) as $i) match ($in['action'] ?? '') {
            'disable' => t_exec("UPDATE gift_cards SET status='disabled' WHERE id=?", [$i]),
            'enable' => t_exec("UPDATE gift_cards SET status='active' WHERE id=?", [$i]),
            default => null,
        };
        json_out(['ok' => true]);

    case 'code':
        $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
        do { $c = ''; for ($i = 0; $i < 16; $i++) $c .= $chars[random_int(0, strlen($chars) - 1)]; } while (t_val("SELECT id FROM gift_cards WHERE code=?", [$c]));
        json_out(['ok' => true, 'code' => $c]);
}
