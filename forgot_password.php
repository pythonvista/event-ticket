<?php
// Educational simplification: a production system would email a one-time
// reset link instead of letting anyone who knows an email set a password.
require_once __DIR__ . '/config/db.php';

$errors = [];
$stage = 'email';
$email = trim($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $errors[] = 'No account found with that email address.';
    } elseif (isset($_POST['password'])) {
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password'] ?? '';
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
            $stage = 'reset';
        } elseif ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
            $stage = 'reset';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            flash('Password updated. Please log in with your new password.');
            redirect('login.php');
        }
    } else {
        $stage = 'reset';
    }
}

$pageTitle = 'Forgot Password';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5" style="max-width: 440px">
  <h2 class="mb-4">Forgot Password</h2>
  <?php foreach ($errors as $error): ?>
  <div class="alert alert-danger py-2"><?= e($error) ?></div>
  <?php endforeach; ?>
  <?php if ($stage === 'email'): ?>
  <form method="post" class="card card-body shadow-sm">
    <div class="mb-3"><label class="form-label">Registered Email</label><input type="email" class="form-control" name="email" required></div>
    <button class="btn btn-primary">Continue</button>
  </form>
  <?php else: ?>
  <form method="post" class="card card-body shadow-sm">
    <input type="hidden" name="email" value="<?= e($email) ?>">
    <p class="text-muted small">Setting a new password for <strong><?= e($email) ?></strong></p>
    <div class="mb-3"><label class="form-label">New Password</label><input type="password" class="form-control" name="password" required></div>
    <div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" class="form-control" name="confirm_password" required></div>
    <button class="btn btn-primary">Set New Password</button>
  </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
