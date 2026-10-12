<?php
require_once __DIR__ . '/config.php';
require_role(['Admin']);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'create_user';

    if ($action === 'reset_password') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        $newPassword = (string)($_POST['new_password'] ?? '');

        if ($targetId <= 0 || strlen($newPassword) < 10) {
            $error = 'Choose a user and provide a temporary password with at least 10 characters.';
        } else {
            $target = db()->prepare("SELECT id, username FROM users WHERE id = ? LIMIT 1");
            $target->execute([$targetId]);
            $userRow = $target->fetch();

            if (!$userRow) {
                $error = 'The selected user could not be found.';
            } else {
                $resetStmt = db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $resetStmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $targetId]);
                set_flash('success', 'Password reset for ' . $userRow['username'] . '. Temporary password: ' . $newPassword);
                header('Location: users.php');
                exit;
            }
        }
    } elseif ($action === 'update_user') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        $name = trim((string)($_POST['full_name'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $role = (string)($_POST['role'] ?? 'Sales Assistant');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($targetId <= 0 || $name === '' || !preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['Admin','Manager','Sales Assistant'], true)) {
            $error = 'Please provide a valid full name, username, email, and role for the selected user.';
        } else {
            try {
                $check = db()->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? LIMIT 1");
                $check->execute([$username, $email, $targetId]);
                if ($check->fetch()) {
                    throw new RuntimeException('duplicate');
                }
                $stmt = db()->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, role = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $username, $email, $role, $isActive, $targetId]);
                set_flash('success', 'User profile updated successfully.');
                header('Location: users.php');
                exit;
            } catch (Throwable $ex) {
                $error = $ex instanceof RuntimeException && $ex->getMessage() === 'duplicate'
                    ? 'Another account is already using that username or email.'
                    : 'Could not update the selected user.';
            }
        }
    } else {
        $name = trim((string)($_POST['full_name'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? 'Sales Assistant');
        if ($name === '' || !preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10 || !in_array($role, ['Admin','Manager','Sales Assistant'], true)) {
            $error = 'Check the form. Username must be 3–50 characters, password at least 10 characters, and email valid.';
        } else {
            try {
                $stmt = db()->prepare("INSERT INTO users (full_name, username, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $username, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
                set_flash('success', 'Staff account created successfully.');
                header('Location: users.php');
                exit;
            } catch (PDOException $ex) {
                $error = 'That username or email is already in use.';
            }
        }
    }
}
$users = db()->query("SELECT id, full_name, username, email, role, is_active, created_at FROM users ORDER BY created_at DESC")->fetchAll();
$flash = take_flash();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>User management | ÉLAN Boutique</title><link rel="stylesheet" href="assets/style.css"></head><body class="app-page"><aside class="sidebar"><a class="sidebar-brand" href="dashboard.php"><span class="brand-mark">É</span><span>ÉLAN <small>BOUTIQUE</small></span></a><div class="store-label">YOUR WORKSPACE</div><nav><a class="nav-item" href="dashboard.php">Overview</a><a class="nav-item active" href="users.php">User management</a></nav><div class="sidebar-bottom"><a class="nav-item logout-link" href="logout.php">Sign out</a></div></aside><main class="main-content"><header class="topbar"><div><p class="eyebrow">ADMINISTRATION</p><h1>Team & access<span>.</span></h1><p class="muted">Create staff accounts and assign permissions.</p></div><a class="secondary-button" href="dashboard.php">Back to dashboard</a></header><?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="users-layout"><section class="panel create-user"><p class="eyebrow">NEW TEAM MEMBER</p><h3>Create an account</h3><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Full name</label><input name="full_name" required maxlength="100" placeholder="Staff member's name"><label>Username</label><input name="username" required minlength="3" maxlength="50" pattern="[a-zA-Z0-9_.-]+" placeholder="e.g. ama.k"><label>Email address</label><input name="email" type="email" required placeholder="name@example.com"><label>Temporary password</label><input name="password" type="password" required minlength="10" placeholder="At least 10 characters"><label>Role</label><select name="role"><option>Sales Assistant</option><option>Manager</option><option>Admin</option></select><button class="primary-button" type="submit">Create staff account</button></form><p class="hint">Give new staff their credentials privately and ask them to change their password before real-world use.</p></section>
<section class="panel users-table-panel"><div class="panel-heading"><div><p class="eyebrow">REGISTERED ACCOUNTS</p><h3>Team directory</h3></div><span class="role-chip"><?= count($users) ?> accounts</span></div><div class="table-scroll"><table><thead><tr><th>Team member</th><th>Username</th><th>Role</th><th>Status</th><th>Update</th><th>Reset password</th></tr></thead><tbody><?php foreach ($users as $u): ?><tr><td><b><?= e($u['full_name']) ?></b><small><?= e($u['email']) ?></small></td><td><?= e($u['username']) ?></td><td><span class="table-role"><?= e($u['role']) ?></span></td><td><span class="<?= (int)$u['is_active'] ? 'active-text' : 'inactive-text' ?>"><?= (int)$u['is_active'] ? 'Active' : 'Inactive' ?></span></td><td><form class="inline-action-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_user"><input type="hidden" name="target_id" value="<?= (int)$u['id'] ?>"><input type="text" name="full_name" value="<?= e($u['full_name']) ?>" required><input type="text" name="username" value="<?= e($u['username']) ?>" required><input type="email" name="email" value="<?= e($u['email']) ?>" required><select name="role"><option <?= $u['role'] === 'Sales Assistant' ? 'selected' : '' ?>>Sales Assistant</option><option <?= $u['role'] === 'Manager' ? 'selected' : '' ?>>Manager</option><option <?= $u['role'] === 'Admin' ? 'selected' : '' ?>>Admin</option></select><label class="checkbox-row"><input type="checkbox" name="is_active" <?= (int)$u['is_active'] ? 'checked' : '' ?>> Active</label><button type="submit" class="secondary-button small-button">Save</button></form></td><td><form class="inline-reset-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="target_id" value="<?= (int)$u['id'] ?>"><input type="text" name="new_password" placeholder="New temp password" minlength="10" required><button type="submit" class="secondary-button small-button">Reset</button></form></td></tr><?php endforeach; ?></tbody></table></div></section></div><footer class="app-footer"><span>ÉLAN BOUTIQUE · USER MANAGEMENT</span><span>ADMIN ONLY</span></footer></main></body></html>
