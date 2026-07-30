<?php
require_once __DIR__ . '/config/db.php';

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($old['full_name'] === '') { $errors[] = 'Full name is required.'; }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'A valid email address is required.'; }
    if ($old['phone'] === '') { $errors[] = 'Phone number is required.'; }
    if (strlen($password) < 6) { $errors[] = 'Password must be at least 6 characters.'; }
    if ($password !== $confirm) { $errors[] = 'Passwords do not match.'; }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (full_name, email, phone, password) VALUES (?, ?, ?, ?)');
            $stmt->execute([$old['full_name'], $old['email'], $old['phone'], password_hash($password, PASSWORD_DEFAULT)]);
            flash('Registration successful. Please log in.');
            redirect('login.php');
        }
    }
}

$pageTitle = 'Register';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5" style="max-width: 520px">
  <h2 class="mb-4">Create an Account</h2>
  <?php foreach ($errors as $error): ?>
  <div class="alert alert-danger py-2"><?= e($error) ?></div>
  <?php endforeach; ?>
  <form method="post" class="card card-body shadow-sm">
    <div class="mb-3"><label class="form-label">Full Name</label><input class="form-control" name="full_name" value="<?= e($old['full_name']) ?>" required></div>
    <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= e($old['email']) ?>" required></div>
    <div class="mb-3"><label class="form-label">Phone Number</label><input class="form-control" name="phone" value="<?= e($old['phone']) ?>" required></div>
    <div class="mb-3"><label class="form-label">Password</label><input type="password" class="form-control" name="password" required></div>
    <div class="mb-3"><label class="form-label">Confirm Password</label><input type="password" class="form-control" name="confirm_password" required></div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary">Register</button>
      <button type="reset" class="btn btn-outline-secondary">Reset</button>
    </div>
  </form>
  <p class="mt-3">Already have an account? <a href="<?= BASE_URL ?>login.php">Login</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
