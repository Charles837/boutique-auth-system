<?php
require_once __DIR__ . '/config.php';
if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}
$flash = take_flash();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $identity = trim((string)($_POST['identity'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($identity === '' || $password === '') {
        $error = 'Enter your username or email and password.';
    } else {
        $stmt = db()->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1");
        $stmt->execute([$identity, $identity]);
        $user = $stmt->fetch();
        $recent = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE username = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE) AND was_successful = 0");
        $recent->execute([$identity]);
        $failed = (int)$recent->fetchColumn();
        if ($failed >= 8) {
            $error = 'Too many failed attempts. Please wait 15 minutes before trying again.';
        } elseif (!$user || !password_verify($password, $user['password_hash'])) {
            db()->prepare("INSERT INTO login_attempts (username, was_successful) VALUES (?, 0)")->execute([$identity]);
            $error = 'The login details do not match our records.';
        } else {
            if (!empty($_SESSION['user']) || !empty($_SESSION['pending_2fa'])) {
                clear_session_state();
            }
            db()->prepare("INSERT INTO login_attempts (username, was_successful) VALUES (?, 1)")->execute([$identity]);
            session_regenerate_id(true);
            $otp = (string)random_int(100000, 999999);
            $_SESSION['pending_2fa'] = [
                'user_id' => (int)$user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
                'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
                'expires' => time() + (OTP_VALID_MINUTES * 60),
                'tries' => 0,
            ];
            $sent = send_otp_email($user['email'], $otp);
            if (!$sent && APP_ENV === 'development') {
                $_SESSION['dev_otp'] = $otp;
            } elseif (!$sent) {
                unset($_SESSION['pending_2fa']);
                $error = 'We could not send your verification code. Please contact the administrator.';
            }
            if ($error === '') {
                header('Location: verify.php');
                exit;
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in | ÉLAN Boutique</title><link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<div class="login-shell">
  <section class="visual-panel">
    <div class="visual-overlay"></div>
    <div class="visual-content">
      <div class="brand-lockup"><span class="brand-mark">É</span><span>ÉLAN <small>BOUTIQUE</small></span></div>
      <div class="hero-copy"><p class="eyebrow">STYLE. GRACE. CONFIDENCE.</p><h1>Where every detail<br><em>makes a statement.</em></h1><p>A refined space for the people who bring our boutique to life.</p></div>
      <div class="visual-footer"><span>CURATED WITH CARE</span><span>EST. 2026</span></div>
    </div>
  </section>
  <section class="form-panel">
    <div class="mobile-brand"><span class="brand-mark">É</span><span>ÉLAN BOUTIQUE</span></div>
    <div class="form-wrap">
      <div class="step-label"><span class="step-dot"></span> TEAM PORTAL <span class="step-line"></span> SECURE ACCESS</div>
      <h2>Welcome back<span>.</span></h2><p class="form-intro">Sign in to access your boutique workspace.</p>
      <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
      <?php if ($error !== ''): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="identity">Username or email</label>
        <div class="input-wrap"><input id="identity" name="identity" type="text" placeholder="e.g. ava@elanboutique.com" autocomplete="username" required value="<?= e($_POST['identity'] ?? '') ?>"></div>
        <div class="label-row"><label for="password">Password</label><span class="micro-label">STEP 1 OF 2</span></div>
        <div class="input-wrap"><input id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required><button type="button" class="show-password" aria-label="Show password" onclick="togglePassword()">Show</button></div>
        <button class="primary-button" type="submit">Continue securely</button>
      </form>
      <div class="security-note"><span><b>Your access is protected</b><br>Two-step verification keeps your account safer.</span></div>
      <div class="form-bottom"><span>ÉLAN BOUTIQUE · STAFF ACCESS</span><span>© 2026</span></div>
    </div>
  </section>
</div>
<script>function togglePassword(){const p=document.getElementById('password');const b=document.querySelector('.show-password');p.type=p.type==='password'?'text':'password';b.textContent=p.type==='password'?'Show':'Hide';}</script>
</body></html>
