<?php
require_once __DIR__ . '/../config/db.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('admin/index.php');
}

$errors = [];
$oldUsername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldUsername = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM admin WHERE username = ?');
    $stmt->execute([$oldUsername]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_name'] = $admin['username'];
        redirect('admin/index.php');
    }
    $errors[] = 'Invalid username or password.';
}

$pageTitle = 'Admin Login';
require __DIR__ . '/../includes/header.php';
?>
<div class="container my-5" style="max-width: 420px">
  <h2 class="mb-4">Admin Login</h2>
  <?php foreach ($errors as $error): ?>
  <div class="alert alert-danger py-2"><?= e($error) ?></div>
  <?php endforeach; ?>
  <form method="post" class="card card-body shadow-sm">
    <div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" value="<?= e($oldUsername) ?>" required></div>
    <div class="mb-3"><label class="form-label">Password</label><input type="password" class="form-control" name="password" required></div>
    <button class="btn btn-primary">Login</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
