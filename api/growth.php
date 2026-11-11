<?php
$in = input();
$channels = ['email', 'social', 'search', 'sms', 'affiliate', 'other'];
switch ($action) {
    case 'dismiss_banner':
        set_setting('growth_banner_dismissed', '1');
        json_out(['ok' => true]);

    case 'campaign_save':
        $id = (int)($in['id'] ?? 0);
        $name = trim((string)($in['name'] ?? ''));
        if ($name === '') json_err('Give the campaign a name');
        $ch = in_array($in['channel'] ?? '', $channels, true) ? $in['channel'] : 'other';
        $start = ($in['starts_on'] ?? '') ?: null; $end = ($in['ends_on'] ?? '') ?: null;
        if ($start && $end && $end < $start) json_err('End date must be after the start date');
        $status = in_array($in['status'] ?? '', ['draft', 'active', 'paused', 'completed'], true) ? $in['status'] : 'draft';
        $f = [$name, $ch, $status, trim((string)($in['goal'] ?? '')), max(0, (float)($in['budget'] ?? 0)), slugify((string)($in['utm_campaign'] ?? '') ?: $name), $start, $end, !empty($in['autopilot']) ? 1 : 0];
        $cols = 'name=?, channel=?, status=?, goal=?, budget=?, utm_campaign=?, starts_on=?, ends_on=?, autopilot=?';
        if ($id) t_exec("UPDATE campaigns SET $cols WHERE id=?", [...$f, $id]);
        else $id = t_exec("INSERT INTO campaigns SET $cols", $f);
        json_out(['ok' => true, 'id' => $id]);

    case 'campaign_status':
        $id = (int)($in['id'] ?? 0);
        $st = in_array($in['status'] ?? '', ['draft', 'active', 'paused', 'completed'], true) ? $in['status'] : null;
        if (!$st) json_err('Unknown status');
        t_exec("UPDATE campaigns SET status=? WHERE id=?", [$st, $id]);
        json_out(['ok' => true]);

    case 'campaign_delete':
        $id = (int)($in['id'] ?? 0);
        t_exec("UPDATE orders SET campaign_id=NULL WHERE campaign_id=?", [$id]);
        t_exec("DELETE FROM campaigns WHERE id=?", [$id]);
        json_out(['ok' => true]);

    case 'autopilot':
        $ch = (string)($in['channel'] ?? '');
        if (!in_array($ch, $channels, true)) json_err('Unknown channel');
        set_setting('autopilot_' . $ch, !empty($in['on']) ? '1' : '0');
        json_out(['ok' => true]);
}
