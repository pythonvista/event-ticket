<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalEvents = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
$totalBookings = (int) $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
$totalRevenue = (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'success'")->fetchColumn();

$recent = $pdo->query('SELECT b.booking_ref, b.status, b.total_amount, b.created_at, u.full_name, e.name AS event_name
                       FROM bookings b JOIN users u ON u.id = b.user_id JOIN events e ON e.id = b.event_id
                       ORDER BY b.created_at DESC LIMIT 5')->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="container my-5">
  <h2 class="mb-4">Admin Dashboard</h2>
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card text-bg-primary shadow-sm"><div class="card-body"><h6>Total Users</h6><div class="fs-3 fw-bold"><?= $totalUsers ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card text-bg-success shadow-sm"><div class="card-body"><h6>Total Events</h6><div class="fs-3 fw-bold"><?= $totalEvents ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card text-bg-warning shadow-sm"><div class="card-body"><h6>Total Bookings</h6><div class="fs-3 fw-bold"><?= $totalBookings ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card text-bg-dark shadow-sm"><div class="card-body"><h6>Total Revenue</h6><div class="fs-4 fw-bold"><?= naira($totalRevenue) ?></div></div></div></div>
  </div>
  <div class="card shadow-sm">
    <div class="card-body">
      <h5 class="card-title">Recent Bookings</h5>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Ref</th><th>Customer</th><th>Event</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach ($recent as $row): ?>
            <tr>
              <td class="small"><?= e($row['booking_ref']) ?></td>
              <td><?= e($row['full_name']) ?></td>
              <td><?= e($row['event_name']) ?></td>
              <td><?= naira($row['total_amount']) ?></td>
              <td><span class="badge bg-<?= $row['status'] === 'confirmed' ? 'success' : ($row['status'] === 'pending' ? 'warning text-dark' : 'secondary') ?>"><?= e(ucfirst($row['status'])) ?></span></td>
              <td class="small text-muted"><?= e(date('j M Y H:i', strtotime($row['created_at']))) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
