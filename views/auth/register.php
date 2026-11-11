<?php
if (!empty($_SESSION['uid'])) redirect('home');
$errors = [];
$v = ['name' => '', 'store_name' => '', 'email' => '', 'mobile_number' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) $v[$k] = trim((string)($_POST[$k] ?? ''));
    $v['email'] = strtolower($v['email']);
    $pass = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password_confirmation'] ?? '');
    if (!csrf_ok($_POST['_csrf'] ?? null)) $errors[] = 'Your session expired. Please try again.';
    if ($v['name'] === '' || mb_strlen($v['name']) > 255) $errors[] = 'Enter your full name.';
    if ($v['store_name'] === '' || mb_strlen($v['store_name']) > 255) $errors[] = 'Enter a store name.';
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!preg_match('/^\+?[0-9]{8,15}$/', $v['mobile_number'])) $errors[] = 'Enter a valid mobile number (8–15 digits).';
    if (strlen($pass) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pass !== $pass2) $errors[] = 'Passwords do not match.';
    if (!$errors) {
        if (m_row("SELECT id FROM users WHERE email = ?", [$v['email']])) $errors[] = 'An account with this email already exists.';
        if (m_row("SELECT id FROM users WHERE mobile_number = ?", [$v['mobile_number']])) $errors[] = 'This mobile number is already registered.';
    }
    if (!$errors) {
        try {
            $uid = m_exec("INSERT INTO users (name, store_name, email, mobile_number, password) VALUES (?,?,?,?,?)",
                [$v['name'], $v['store_name'], $v['email'], $v['mobile_number'], password_hash($pass, PASSWORD_DEFAULT)]);
            provision_tenant($uid, $v['name'], $v['email'], $v['store_name']);
            redirect('login?registered=1');
        } catch (Throwable $e) {
            error_log('[amarfy register] ' . $e->getMessage());
            if (!empty($uid)) m_exec("DELETE FROM users WHERE id = ? AND db_name IS NULL", [$uid]);
            $errors[] = 'Could not create your store: ' . $e->getMessage();
        }
    }
}
$AUTH_TITLE = 'Create your store';
ob_start(); ?>
<h1>Create your store</h1>
<p class="auth-sub">Start selling with Amarfy</p>
<?php if ($errors): ?><div class="banner critical"><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="auth-form" novalidate>
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
  <label class="field"><span>Full name</span><input name="name" value="<?= h($v['name']) ?>" required autofocus></label>
  <label class="field"><span>Store name</span><input name="store_name" value="<?= h($v['store_name']) ?>" required></label>
  <label class="field"><span>Email</span><input type="email" name="email" value="<?= h($v['email']) ?>" autocomplete="email" required></label>
  <label class="field"><span>Mobile number</span><input name="mobile_number" value="<?= h($v['mobile_number']) ?>" placeholder="01XXXXXXXXX" required></label>
  <div class="row2">
    <label class="field"><span>Password</span><input type="password" name="password" autocomplete="new-password" required></label>
    <label class="field"><span>Confirm password</span><input type="password" name="password_confirmation" autocomplete="new-password" required></label>
  </div>
  <button class="btn primary block lg" type="submit">Create store</button>
</form>
<p class="auth-switch">Already have a store? <a href="<?= h(url('login')) ?>">Log in</a></p>
<?php $AUTH_BODY = ob_get_clean();
require __DIR__ . '/_layout.php';
