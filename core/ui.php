<?php
/** Inline SVG icon set (stroke icons, 16x16 grid). */
function icon(string $n, string $cls = ''): string
{
    static $p = [
        'home' => '<path d="M3 5.5A3 3 0 0 1 6 2.5h4a3 3 0 0 1 3 3v5a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3z"/><path d="M6.2 10.2h3.6"/>',
        'orders' => '<path d="M2.5 9 4 3.5h8L13.5 9v3a1.5 1.5 0 0 1-1.5 1.5H4A1.5 1.5 0 0 1 2.5 12z"/><path d="M2.5 9h3l.8 1.5h3.4l.8-1.5h3"/>',
        'products' => '<path d="M8.6 2.5H13a.5.5 0 0 1 .5.5v4.4L7.3 13.6a1 1 0 0 1-1.4 0L2.4 10.1a1 1 0 0 1 0-1.4z"/><circle cx="10.6" cy="5.4" r=".9"/>',
        'customers' => '<circle cx="8" cy="5.3" r="2.6"/><path d="M3.2 13.2c0-2.1 2.1-3.6 4.8-3.6s4.8 1.5 4.8 3.6c0 .3-.2.5-.5.5H3.7c-.3 0-.5-.2-.5-.5z"/>',
        'growth' => '<path d="M3.2 12.5a5.5 5.5 0 1 1 9.6 0"/><path d="m8.4 10.4 1.8-3.8"/><circle cx="8" cy="11" r=".8"/>',
        'discounts' => '<path d="M8 1.8l1.5 1.1 1.8-.1.6 1.7 1.5 1-.5 1.8.5 1.8-1.5 1-.6 1.7-1.8-.1L8 14.2l-1.5-1.1-1.8.1-.6-1.7-1.5-1 .5-1.8-.5-1.8 1.5-1 .6-1.7 1.8.1z"/><path d="m6 10 4-4"/><circle cx="6.2" cy="6.2" r=".6"/><circle cx="9.8" cy="9.8" r=".6"/>',
        'content' => '<rect x="2" y="2.5" width="12" height="9" rx="2"/><path d="m2.5 10 3.2-3 2.6 2.4 1.7-1.4 3.5 3"/><circle cx="10.5" cy="5.5" r=".9"/><path d="M5 14h6"/>',
        'markets' => '<path d="M13.7 7.2A5.8 5.8 0 1 0 8 13.8"/><path d="M3 6.5h3l1 2-1.5 2 .5 2.6M9.5 2.6 9 4.5l2 1.2 2.4-.3"/><path d="M12.3 9.3v5M13.6 10.3c-.3-.4-.8-.6-1.3-.6-.7 0-1.2.4-1.2 1s.5.9 1.2 1 1.3.4 1.3 1-.6 1-1.3 1c-.5 0-1-.2-1.3-.6"/>',
        'analytics' => '<rect x="2.3" y="8" width="2.6" height="5.5" rx="1.3"/><rect x="6.7" y="3" width="2.6" height="10.5" rx="1.3"/><rect x="11.1" y="5.5" width="2.6" height="8" rx="1.3"/>',
        'store' => '<path d="M3.5 6.5 4.3 3.5h7.4l.8 3a1.5 1.5 0 0 1-3 0 1.5 1.5 0 0 1-3 0 1.5 1.5 0 0 1-3 0z"/><path d="M4.3 8v4.5h7.4V8M7 12.5V10h2v2.5"/>',
        'agentic' => '<circle cx="8" cy="8" r="3.2"/><circle cx="4" cy="4" r="1.3"/><circle cx="12" cy="4" r="1.3"/><circle cx="4" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/>',
        'add-circle' => '<circle cx="8" cy="8" r="6"/><path d="M8 5v6M5 8h6"/>',
        'settings' => '<circle cx="8" cy="8" r="2"/><path d="M8 1.8l1.2 1.4 1.8-.3.5 1.8 1.7.7-.4 1.8 1.1 1.4-1.4 1.2.3 1.8-1.8.5-.7 1.7-1.8-.4L8 14.2l-1.2-1.4-1.8.3-.5-1.8-1.7-.7.4-1.8L2.1 7.4l1.4-1.2-.3-1.8 1.8-.5.7-1.7 1.8.4z"/>',
        'bell' => '<path d="M4 11V7a4 4 0 0 1 8 0v4l1 1.5H3z"/><path d="M6.5 14h3"/>',
        'search' => '<circle cx="7" cy="7" r="4.6"/><path d="m10.5 10.5 3.2 3.2"/>',
        'sidebar' => '<rect x="2" y="2.5" width="12" height="11" rx="2.5"/><path d="M6 3v10"/>',
        'columns' => '<rect x="2" y="2.5" width="12" height="11" rx="2.5"/><path d="M6.5 2.5v11M9.5 2.5v11"/>',
        'updown' => '<path d="m5.5 6 2.5-2.5L10.5 6M5.5 10l2.5 2.5 2.5-2.5"/>',
        'chev-down' => '<path d="m4.5 6.5 3.5 3.5 3.5-3.5"/>',
        'chev-up' => '<path d="m4.5 9.5 3.5-3.5 3.5 3.5"/>',
        'chev-left' => '<path d="M9.5 4 5.5 8l4 4"/>',
        'chev-right' => '<path d="m6.5 4 4 4-4 4"/>',
        'arrow-down' => '<path d="M8 3v10M4.5 9.5 8 13l3.5-3.5"/>',
        'arrow-up' => '<path d="M8 13V3M4.5 6.5 8 3l3.5 3.5"/>',
        'arrow-right' => '<path d="M3 8h10M9.5 4.5 13 8l-3.5 3.5"/>',
        'plus' => '<path d="M8 3v10M3 8h10"/>',
        'x' => '<path d="m4 4 8 8M12 4l-8 8"/>',
        'dots' => '<circle cx="3.5" cy="8" r=".9" fill="currentColor"/><circle cx="8" cy="8" r=".9" fill="currentColor"/><circle cx="12.5" cy="8" r=".9" fill="currentColor"/>',
        'pencil' => '<path d="M10.5 2.8l2.7 2.7-7.4 7.4-3.3.6.6-3.3z"/><path d="m9.3 4 2.7 2.7"/>',
        'eye' => '<path d="M1.5 8S4 3.5 8 3.5 14.5 8 14.5 8 12 12.5 8 12.5 1.5 8 1.5 8z"/><circle cx="8" cy="8" r="2"/>',
        'calendar' => '<rect x="2.5" y="3.5" width="11" height="10" rx="2"/><path d="M2.5 6.5h11M5.5 2v3M10.5 2v3"/>',
        'info' => '<circle cx="8" cy="8" r="6"/><path d="M8 7.3v3.7"/><circle cx="8" cy="5.2" r=".5" fill="currentColor"/>',
        'warn' => '<path d="M8 2.2 14.2 13H1.8z"/><path d="M8 6.5v3"/><circle cx="8" cy="11.2" r=".5" fill="currentColor"/>',
        'drafts' => '<path d="M10 2.5H4.5a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2V8"/><path d="m11.8 2.3 1.9 1.9L8.5 9.4l-2.4.5.5-2.4z"/>',
        'label' => '<rect x="2.5" y="3" width="11" height="10.5" rx="2"/><path d="M2.5 6.5h11M6.5 3v3.5M9.5 3v3.5"/>',
        'cart' => '<path d="M1.5 2.5h2l1.6 7.5h7.4l1.4-5.5H4.3"/><circle cx="6" cy="12.8" r=".9"/><circle cx="11.5" cy="12.8" r=".9"/><path d="M8 5.5h3"/>',
        'collections' => '<path d="M9.6 2.5H13a.5.5 0 0 1 .5.5v3.4L8.3 11.6a1 1 0 0 1-1.4 0L4.4 9.1a1 1 0 0 1 0-1.4z"/><path d="M2.5 9.2 7 13.7"/>',
        'inventory' => '<path d="M2.5 6 8 2.5 13.5 6v7.5h-11z"/><path d="M5 13.5V8.5h6v5M5 11h6"/>',
        'po' => '<rect x="2.5" y="2" width="9" height="12" rx="1.5"/><path d="M5 5h4M5 7.5h4M5 10h2"/><path d="M13.5 9.5v4M12.3 10.5c.3-.5 1.9-.6 2 .3.1.8-2 .6-2 1.5 0 .8 1.7.9 2.1.3"/>',
        'transfer' => '<path d="M2.5 3v10M13.5 3v10M5 8h6.5M9 5.5 11.5 8 9 10.5"/>',
        'gift' => '<rect x="2" y="4" width="12" height="9" rx="1.5"/><path d="M2 8h12M8 4v9"/><path d="M8 4c-1-2-3.5-2-3.5-.5S7 4 8 4c1 0 3.5-.5 3.5-2S9 2 8 4z"/>',
        'image' => '<rect x="2.5" y="2.5" width="11" height="11" rx="2"/><circle cx="6" cy="6" r="1"/><path d="m3 12 3.5-3.5 2.5 2.5 1.5-1.5L13 12"/>',
        'upload' => '<path d="M8 13V5M4.5 8.5 8 5l3.5 3.5M3.5 2.5h9"/>',
        'download' => '<path d="M8 2.5v8M4.5 7 8 10.5 11.5 7M3 13.5h10"/>',
        'barcode' => '<path d="M2.5 5V3.5h2M11.5 3.5h2V5M13.5 11v1.5h-2M4.5 12.5h-2V11M5.5 6v4M7.5 6v4M9 6v4M10.5 6v4"/>',
        'pin' => '<path d="M8 14s4.5-4 4.5-7.5a4.5 4.5 0 0 0-9 0C3.5 10 8 14 8 14z"/><circle cx="8" cy="6.5" r="1.6"/>',
        'truck' => '<path d="M1.5 4h8v7h-8zM9.5 6.5h2.5l2 2.5V11h-4.5"/><circle cx="4" cy="11.8" r="1.2"/><circle cx="11.5" cy="11.8" r="1.2"/>',
        'building' => '<rect x="3" y="2" width="10" height="12" rx="1.5"/><path d="M6 5h1M9 5h1M6 7.5h1M9 7.5h1M6 10h1M9 10h1M7 14v-2h2v2"/>',
        'note' => '<rect x="2.5" y="2.5" width="11" height="11" rx="2"/><path d="M5 5.5h6M5 8h6M5 10.5h3"/>',
        'doc-ref' => '<path d="M9.5 2H4.5a1.5 1.5 0 0 0-1.5 1.5v9A1.5 1.5 0 0 0 4.5 14H7"/><path d="M9.5 2 13 5.5H9.5zM10.5 9.5l2.5 2.5-2.5 2.5"/>',
        'hash' => '<path d="M6 2.5 5 13.5M11 2.5l-1 11M3 6h10.5M2.5 10H13"/>',
        'emoji' => '<circle cx="8" cy="8" r="6"/><path d="M5.5 9.5c.6 1 1.4 1.5 2.5 1.5s1.9-.5 2.5-1.5"/><circle cx="6" cy="6.5" r=".6" fill="currentColor"/><circle cx="10" cy="6.5" r=".6" fill="currentColor"/>',
        'at' => '<circle cx="8" cy="8" r="2.5"/><path d="M10.5 8v1a1.8 1.8 0 0 0 3.5 0V8a6 6 0 1 0-2.4 4.8"/>',
        'link' => '<path d="M7 9a3 3 0 0 0 4.2 0l2-2A3 3 0 0 0 9 2.8l-.8.8"/><path d="M9 7a3 3 0 0 0-4.2 0l-2 2A3 3 0 0 0 7 13.2l.8-.8"/>',
        'globe' => '<circle cx="8" cy="8" r="6"/><path d="M2 8h12M8 2c-2.5 2.8-2.5 9.2 0 12M8 2c2.5 2.8 2.5 9.2 0 12"/>',
        'trash' => '<path d="M3 4.5h10M6.5 4.5V3h3v1.5M4.5 4.5l.6 8.5a1 1 0 0 0 1 1h3.8a1 1 0 0 0 1-1l.6-8.5"/>',
        'channels' => '<circle cx="5" cy="5" r="2"/><circle cx="11" cy="11" r="2"/><circle cx="4.5" cy="11.5" r="1.5"/><path d="M6.5 6.5l3 3M5 7v3"/>',
        'catalog' => '<rect x="2.5" y="2.5" width="9" height="9" rx="1.5"/><path d="M5 13.5h6.5a2 2 0 0 0 2-2V5"/><path d="m5 7 1.5 1.5L9.5 5"/>',
        'route' => '<circle cx="4" cy="12" r="1.5"/><circle cx="12" cy="4" r="1.5"/><path d="M5.5 12H10a2 2 0 0 0 0-4H6a2 2 0 0 1 0-4h4.5"/>',
        'sort' => '<path d="M5 13V3M2.5 5.5 5 3l2.5 2.5M11 3v10M8.5 10.5 11 13l2.5-2.5"/>',
        'grid' => '<rect x="2.5" y="2.5" width="4.5" height="4.5" rx="1"/><rect x="9" y="2.5" width="4.5" height="4.5" rx="1"/><rect x="2.5" y="9" width="4.5" height="4.5" rx="1"/><rect x="9" y="9" width="4.5" height="4.5" rx="1"/>',
        'mic' => '<rect x="5.5" y="1.8" width="5" height="8" rx="2.5"/><path d="M3.5 7.5a4.5 4.5 0 0 0 9 0M8 12v2.3"/>',
        'panel' => '<rect x="2.5" y="2.5" width="11" height="11" rx="2.5"/><path d="M10 2.5v11"/>',
        'check' => '<path d="m3.5 8.5 3 3 6-7"/>',
        'circle-dash' => '<circle cx="8" cy="8" r="5" stroke-dasharray="2 1.6"/>',
        'clock' => '<circle cx="8" cy="8" r="6"/><path d="M8 4.5V8l2.5 1.5"/>',
        'filter' => '<path d="M2.5 4h11M4.5 8h7M6.5 12h3"/>',
        'logout' => '<path d="M6 2.5H4a1.5 1.5 0 0 0-1.5 1.5v8A1.5 1.5 0 0 0 4 13.5h2M10 5l3 3-3 3M13 8H6"/>',
        'adjust' => '<path d="M3 5h6M12 5h1M3 11h1M7 11h6"/><circle cx="10.5" cy="5" r="1.5"/><circle cx="5.5" cy="11" r="1.5"/>',
        'box' => '<path d="M2.5 5 8 2.5 13.5 5v6L8 13.5 2.5 11z"/><path d="M2.5 5 8 7.5 13.5 5M8 7.5v6"/>',
    ];
    $body = $p[$n] ?? $p['circle-dash'];
    return '<svg class="ic ' . $cls . '" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}

/** Standard page top bar. $crumbs: [[label, href|null], ...]; first crumb gets the icon. */
function topbar(string $ic, array $crumbs, string $actions = '', ?string $back = null, string $extra = ''): string
{
    $o = '<header class="topbar">';
    if ($back) $o .= '<a class="back-btn" href="' . h(url($back)) . '" aria-label="Back">' . icon('chev-left') . '</a>';
    $o .= '<div class="crumbs">';
    foreach ($crumbs as $i => $c) {
        if ($i) $o .= '<span class="crumb-sep">/</span>';
        $label = ($i === 0 ? icon($ic) : '') . '<span>' . h($c[0]) . '</span>';
        $cls = $i === count($crumbs) - 1 && count($crumbs) > 1 ? 'crumb cur' : 'crumb';
        $o .= !empty($c[1]) ? '<a class="' . $cls . '" href="' . h(url($c[1])) . '">' . $label . '</a>' : '<span class="' . $cls . '">' . $label . '</span>';
    }
    $o .= $extra . '</div><div class="top-actions">' . $actions . '</div></header>';
    return $o;
}

function pill(string $text, string $tone = 'neutral', bool $dot = false): string
{
    return '<span class="pill ' . $tone . '">' . ($dot ? '<i class="pdot"></i>' : '') . h($text) . '</span>';
}

function status_pill(string $s): string
{
    return match ($s) {
        'pending' => pill('Payment pending', 'orange', true),
        'paid' => pill('Paid', 'neutral', true),
        'refunded' => pill('Refunded', 'neutral', true),
        'voided' => pill('Voided', 'neutral', true),
        'unfulfilled' => pill('Unfulfilled', 'yellow', true),
        'fulfilled' => pill('Fulfilled', 'neutral', true),
        'open' => pill('Open', 'yellow', true),
        'invoice_sent' => pill('Invoice sent', 'blue', true),
        'completed' => pill('Completed', 'neutral', true),
        'active' => pill('Active', 'green'),
        'draft' => pill('Draft', 'blue'),
        'archived' => pill('Archived', 'neutral'),
        'disabled' => pill('Disabled', 'neutral'),
        'ordered' => pill('Ordered', 'yellow', true),
        'partial' => pill('Partially received', 'orange', true),
        'received' => pill('Received', 'green', true),
        'closed' => pill('Closed', 'neutral', true),
        'in_transit' => pill('In transit', 'yellow', true),
        default => pill(ucfirst($s)),
    };
}

/** Filter bar with view selector, search box and column chooser. */
function filter_bar(array $views, string $current, string $q, string $tableId): string
{
    $o = '<form class="filter-bar" method="get"><label class="view-select">';
    if ($views) {
        $o .= '<select name="view" onchange="this.form.submit()">';
        foreach ($views as $k => $v) $o .= '<option value="' . h($k) . '"' . ($k === $current ? ' selected' : '') . '>' . h($v) . '</option>';
        $o .= '</select>' . icon('updown');
    }
    $o .= '</label><label class="fsearch">' . icon('search') . '<input name="q" value="' . h($q) . '" placeholder="Search and filter" autocomplete="off"></label>';
    foreach ($_GET as $k => $v) if (!in_array($k, ['view', 'q'], true) && is_string($v)) $o .= '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    $o .= '<button type="button" class="icon-btn col-chooser" data-table="' . h($tableId) . '" title="Show/hide columns">' . icon('columns') . '</button></form>';
    return $o;
}

function thumb(?string $path, string $cls = 'thumb-sm'): string
{
    if ($path) return '<span class="' . $cls . '"><img src="' . h(url($path)) . '" alt="" loading="lazy"></span>';
    return '<span class="' . $cls . ' empty">' . icon('image') . '</span>';
}

function sort_link(string $label, string $key, string $curSort, string $curDir): string
{
    $dir = ($curSort === $key && $curDir === 'desc') ? 'asc' : 'desc';
    $q = array_merge($_GET, ['sort' => $key, 'dir' => $dir]);
    $arrow = $curSort === $key ? icon($curDir === 'desc' ? 'arrow-down' : 'arrow-up', 'sort-ic') : '';
    return '<a class="th-sort" href="?' . h(http_build_query($q)) . '">' . h($label) . $arrow . '</a>';
}

function first_image(int $productId): ?string
{
    return t_val("SELECT path FROM product_media WHERE product_id = ? ORDER BY position, id LIMIT 1", [$productId]);
}
function customer_name(?array $c): string
{
    if (!$c) return '';
    $n = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
    return $n !== '' ? $n : (string)($c['email'] ?? '');
}
function empty_state(string $svg, string $title, string $text, string $btn = '', string $href = ''): string
{
    return '<div class="empty-state">' . $svg . '<h3>' . h($title) . '</h3><p>' . h($text) . '</p>' .
        ($btn ? '<a class="btn primary" href="' . h(url($href)) . '">' . h($btn) . '</a>' : '') . '</div>';
}
function learn_more(string $t): string { return '<div class="learn-more"><button type="button" class="link-btn" data-help="' . h($t) . '">Learn more about ' . h($t) . '</button></div>'; }
