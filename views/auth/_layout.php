<?php
/** Shared shell for login/register. Expects $AUTH_TITLE and $AUTH_BODY. */
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($AUTH_TITLE) ?> · Amarfy</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;650&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('app.css') ?>">
</head>
<body class="auth-body">
  <div class="auth-wrap">
    <div class="auth-card">
      <div class="auth-logo"><span class="logo">a</span><b>amarfy</b></div>
      <?= $AUTH_BODY ?>
    </div>
    <footer class="auth-footer">
      <span>© 2024 - 2026 BizScale Global Limited | All rights reserved</span>
      <span>Powered By: Amarfy v<?= h($GLOBALS['CFG']['version']) ?></span>
    </footer>
  </div>
</body>
</html>
