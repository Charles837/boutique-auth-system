<?php
require_once __DIR__ . '/config.php';
require_login();

$user = $_SESSION['user'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $error = 'Enter your current password and both new password fields.';
    } else {
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([(int)$user['id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
            $error = 'Your current password is incorrect.';
        } elseif (strlen($newPassword) < 10) {
            $error = 'Your new password must be at least 10 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'The new password and confirmation do not match.';
        } else {
            $update = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int)$user['id']]);
            set_flash('success', 'Your password was changed successfully.');
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Change password | ÉLAN Boutique</title><link rel="stylesheet" href="assets/style.css">
</head>
<body class="app-page">
<aside class="sidebar">
  <a class="sidebar-brand" href="dashboard.php"><span class="brand-mark">É</span><span>ÉLAN <small>BOUTIQUE</small></span></a>
  <div class="store-label">YOUR WORKSPACE</div>
  <nav>
    <a class="nav-item" href="dashboard.php">Overview</a>
    <a class="nav-item active" href="change_password.php">Change password</a>
    <?php if ($user['role'] === 'Admin'): ?><a class="nav-item" href="users.php">User management</a><?php endif; ?>
  </nav>
  <div class="sidebar-bottom"><a class="nav-item logout-link" href="logout.php">Sign out</a></div>
</aside>
<main class="main-content">
  <header class="topbar">
    <div>
      <p class="eyebrow">ACCOUNT SECURITY</p>
      <h1>Change your password<span>.</span></h1>
      <p class="muted">Keep your boutique workspace protected with a strong, unique password.</p>
    </div>
    <a class="secondary-button" href="dashboard.php">Back to dashboard</a>
  </header>

  <?php if ($error !== ''): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

  <section class="panel create-user change-password-panel">
    <p class="eyebrow">UPDATE SECURITY</p>
    <h3>Set a new password</h3>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label for="current_password">Current password</label>
      <input id="current_password" name="current_password" type="password" required placeholder="Enter your current password">

      <label for="new_password">New password</label>
      <input id="new_password" name="new_password" type="password" required minlength="10" placeholder="At least 10 characters">

      <label for="confirm_password">Confirm new password</label>
      <input id="confirm_password" name="confirm_password" type="password" required minlength="10" placeholder="Repeat the new password">

      <button class="primary-button" type="submit">Update password</button>
    </form>
  </section>

  <footer class="app-footer"><span>ÉLAN BOUTIQUE · SECURE ACCESS</span><span>ACCOUNT POLICY</span></footer>
</main>
</body>
</html>
