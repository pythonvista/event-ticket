<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$users = $pdo->query('SELECT u.*, COUNT(b.id) AS booking_count
                      FROM users u LEFT JOIN bookings b ON b.user_id = u.id
                      GROUP BY u.id ORDER BY u.created_at DESC')->fetchAll();

$pageTitle = 'Manage Users';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="container my-5">
  <h2 class="mb-4">Registered Users</h2>
  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>#</th><th>Full Name</th><th>Email</th><th>Phone</th><th>Bookings</th><th>Registered</th></tr></thead>
        <tbody>
        <?php foreach ($users as $user): ?>
          <tr>
            <td><?= (int) $user['id'] ?></td>
            <td><?= e($user['full_name']) ?></td>
            <td><?= e($user['email']) ?></td>
            <td><?= e($user['phone']) ?></td>
            <td><?= (int) $user['booking_count'] ?></td>
            <td class="small text-muted"><?= e(date('j M Y', strtotime($user['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
