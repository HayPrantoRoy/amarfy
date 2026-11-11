<?php
/** Home page card preferences. */
if ($action === 'dismiss') {
    $key = preg_replace('/[^a-z0-9_]/', '', (string)(input()['card'] ?? ''));
    if ($key === '') json_err('Unknown card');
    $list = json_decode((string)setting('dismissed_cards', '[]'), true) ?: [];
    if (!in_array($key, $list, true)) $list[] = $key;
    set_setting('dismissed_cards', json_encode(array_values($list)));
    json_out(['ok' => true]);
}
if ($action === 'setting') {   // dismissible banners
    $key = (string)(input()['key'] ?? '');
    if (!in_array($key, ['b2b_banner_dismissed', 'growth_banner_dismissed'], true)) json_err('Unknown setting');
    set_setting($key, '1');
    json_out(['ok' => true]);
}
if ($action === 'restore') {
    set_setting('dismissed_cards', '[]');
    json_out(['ok' => true]);
}
