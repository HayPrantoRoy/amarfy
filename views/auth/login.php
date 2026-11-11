<?php
if (!empty($_SESSION['uid'])) redirect('home');
$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass = (string)($_POST['password'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!csrf_ok($_POST['_csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($email === '' || $pass === '') {
        $error = 'Enter your email and password.';
    } else {
        // Simple brute-force guard: max 8 failed attempts per email in 15 minutes.
        $fails = (int)(m_row("SELECT COUNT(*) c FROM login_logs WHERE email = ? AND success = 0 AND created_at > NOW() - INTERVAL 15 MINUTE", [$email])['c'] ?? 0);
        $u = m_row("SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);
        if ($fails >= 8) {
            $error = 'Too many failed attempts. Try again in 15 minutes.';
        } elseif (!$u || !password_verify($pass, $u['password'])) {
            m_exec("INSERT INTO login_logs (user_id, email, success, ip, user_agent) VALUES (?,?,0,?,?)", [$u ? (int)$u['id'] : null, $email, $ip, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
            $error = 'Incorrect email or password.';
        } elseif (!(int)$u['status']) {
            $error = 'This account is disabled. Contact support.';
        } elseif (empty($u['db_name'])) {
            $error = 'Your store database is not ready. Contact support.';
        } else {
            m_exec("INSERT INTO login_logs (user_id, email, success, ip, user_agent) VALUES (?,?,1,?,?)", [(int)$u['id'], $email, $ip, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
            login_user($u);
            redirect('home');
        }
    }
}
$AUTH_TITLE = 'Log in';
ob_start(); ?>
<h1>Log in</h1>
<p class="auth-sub">Continue to your Amarfy store</p>
<?php if (!empty($_GET['registered'])): ?><div class="banner success">Your store is ready. Log in to continue.</div><?php endif; ?>
<?php if ($error): ?><div class="banner critical"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="auth-form" novalidate>
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
  <label class="field"><span>Email</span><input type="email" name="email" value="<?= h($email) ?>" autocomplete="email" required autofocus></label>
  <label class="field"><span>Password</span><input type="password" name="password" autocomplete="current-password" required></label>
  <button class="btn primary block lg" type="submit">Log in</button>
</form>
<p class="auth-switch">New to Amarfy? <a href="<?= h(url('register')) ?>">Create your store</a></p>
<?php $AUTH_BODY = ob_get_clean();
require __DIR__ . '/_layout.php';
