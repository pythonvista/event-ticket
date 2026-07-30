<?php
require_once __DIR__ . '/config/db.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$errors = [];
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldEmail = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$oldEmail]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        redirect('dashboard.php');
    }
    $errors[] = 'Invalid email or password.';
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5" style="max-width: 440px">
  <h2 class="mb-4">Login</h2>
  <?php foreach ($errors as $error): ?>
  <div class="alert alert-danger py-2"><?= e($error) ?></div>
  <?php endforeach; ?>
  <form method="post" class="card card-body shadow-sm">
    <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= e($oldEmail) ?>" required></div>
    <div class="mb-3"><label class="form-label">Password</label><input type="password" class="form-control" name="password" required></div>
    <div class="d-flex justify-content-between align-items-center">
      <button class="btn btn-primary">Login</button>
      <a class="small" href="<?= BASE_URL ?>forgot_password.php">Forgot Password?</a>
    </div>
  </form>
  <p class="mt-3">No account yet? <a href="<?= BASE_URL ?>register.php">Register</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
