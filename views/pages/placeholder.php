<?php
$labels = [
    'customers/segments' => ['customers', 'Segments'], 'customers/companies' => ['building', 'Companies'],
    'growth' => ['growth', 'Growth'], 'growth/attribution' => ['growth', 'Attribution'], 'growth/autopilot' => ['growth', 'Autopilot'], 'growth/campaigns' => ['growth', 'Campaigns'],
    'discounts' => ['discounts', 'Discounts'], 'content' => ['content', 'Content'], 'content/metaobjects' => ['content', 'Metaobjects'], 'content/files' => ['image', 'Files'],
    'content/menus' => ['content', 'Menus'], 'content/blog_posts' => ['content', 'Blog posts'], 'markets' => ['markets', 'Markets'], 'markets/catalogs' => ['catalog', 'Catalogs'],
    'markets/rollouts' => ['route', 'Rollouts'], 'analytics' => ['analytics', 'Analytics'], 'analytics/reports' => ['analytics', 'Reports'], 'analytics/live_view' => ['analytics', 'Live View'],
    'online_store' => ['store', 'Online Store'], 'online_store/pages' => ['store', 'Pages'], 'online_store/preferences' => ['store', 'Preferences'],
    'agentic' => ['agentic', 'Agentic'], 'apps' => ['add-circle', 'Apps'], 'settings' => ['settings', 'Settings'],
];
[$ic, $label] = $labels[$path] ?? ['circle-dash', 'Page not found'];
$PAGE_TITLE = $label;
http_response_code(isset($labels[$path]) ? 200 : 404);
echo topbar($ic, [[$label, null]]);
?>
<div class="page">
  <div class="card" style="margin-top:6px">
    <?php if (isset($labels[$path])): ?>
      <?= empty_state('<svg viewBox="0 0 200 140"><circle cx="100" cy="70" r="60" fill="#f1f1f1"/><rect x="62" y="44" width="76" height="54" rx="8" fill="#fff" stroke="#d4d4d4"/><path d="M72 60h40M72 72h56M72 84h30" stroke="#c4c4c4" stroke-width="5" stroke-linecap="round"/></svg>',
          $label, $label . ' is part of the Amarfy roadmap and will be available in an upcoming release.', 'Go to home', 'home') ?>
    <?php else: ?>
      <?= empty_state('', 'This page doesn’t exist', 'Check the address, or go back to your store home.', 'Go to home', 'home') ?>
    <?php endif; ?>
  </div>
</div>
