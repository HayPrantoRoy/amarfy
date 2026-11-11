<?php
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) exit;
$d = __DIR__ . '/uploads/amarfy_100002'; if (is_dir($d)) { foreach (glob($d . '/*') as $x) unlink($x); rmdir($d); echo "rm ok\n"; }
