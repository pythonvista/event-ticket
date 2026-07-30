<?php
require_once __DIR__ . '/includes/auth.php';

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$stmt = $pdo->prepare('SELECT b.*, e.name AS event_name, e.event_date
                       FROM bookings b JOIN events e ON e.id = b.event_id
                       WHERE b.user_id = ? ORDER BY b.created_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5">
  <div class="row g-4">
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title">My Profile</h5>
          <p class="mb-1"><strong><?= e($user['full_name']) ?></strong></p>
          <p class="mb-1 text-muted"><?= e($user['email']) ?></p>
          <p class="mb-3 text-muted"><?= e($user['phone']) ?></p>
          <a class="btn btn-outline-danger btn-sm" href="<?= BASE_URL ?>logout.php">Logout</a>
        </div>
      </div>
    </div>
    <div class="col-md-8">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title mb-3">Booking History</h5>
          <?php if (!$bookings): ?>
          <p class="text-muted">No bookings yet. <a href="<?= BASE_URL ?>events.php">Browse events</a> to get started.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead><tr><th>Ref</th><th>Event</th><th>Tickets</th><th>Amount</th><th>Status</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($bookings as $booking): ?>
                <tr>
                  <td class="small"><?= e($booking['booking_ref']) ?></td>
                  <td><?= e($booking['event_name']) ?><br><small class="text-muted"><?= e(date('j M Y', strtotime($booking['event_date']))) ?></small></td>
                  <td><?= strtoupper(e($booking['ticket_type'])) ?> × <?= (int) $booking['quantity'] ?></td>
                  <td><?= naira($booking['total_amount']) ?></td>
                  <td><span class="badge bg-<?= $booking['status'] === 'confirmed' ? 'success' : ($booking['status'] === 'pending' ? 'warning text-dark' : 'secondary') ?>"><?= e(ucfirst($booking['status'])) ?></span></td>
                  <td>
                    <?php if ($booking['status'] === 'confirmed'): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>ticket.php?booking=<?= urlencode($booking['booking_ref']) ?>">E-Ticket</a>
                    <?php elseif ($booking['status'] === 'pending'): ?>
                    <a class="btn btn-sm btn-outline-success" href="<?= BASE_URL ?>payment.php?booking=<?= urlencode($booking['booking_ref']) ?>">Pay Now</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
