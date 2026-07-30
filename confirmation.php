<?php
require_once __DIR__ . '/includes/auth.php';

$bookingRef = trim($_GET['booking'] ?? '');
$stmt = $pdo->prepare('SELECT b.*, e.name AS event_name, u.full_name
                       FROM bookings b
                       JOIN events e ON e.id = b.event_id
                       JOIN users u ON u.id = b.user_id
                       WHERE b.booking_ref = ? AND b.user_id = ?');
$stmt->execute([$bookingRef, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    flash('Booking not found.');
    redirect('dashboard.php');
}

$stmt = $pdo->prepare("SELECT status FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$booking['id']]);
$paymentStatus = $stmt->fetchColumn() ?: 'pending';

$pageTitle = 'Booking Confirmation';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5" style="max-width: 560px">
  <div class="card shadow-sm text-center">
    <div class="card-body p-4">
      <?php if ($booking['status'] === 'confirmed'): ?>
      <div class="display-6 text-success mb-2">✔</div>
      <h2 class="mb-4">Booking Confirmed!</h2>
      <?php else: ?>
      <h2 class="mb-4">Booking <?= e(ucfirst($booking['status'])) ?></h2>
      <?php endif; ?>
      <table class="table text-start">
        <tr><th>Booking ID</th><td><?= e($booking['booking_ref']) ?></td></tr>
        <tr><th>Customer Name</th><td><?= e($booking['full_name']) ?></td></tr>
        <tr><th>Event Name</th><td><?= e($booking['event_name']) ?></td></tr>
        <tr><th>Payment Status</th><td><span class="badge bg-<?= $paymentStatus === 'success' ? 'success' : 'warning text-dark' ?>"><?= e(ucfirst($paymentStatus)) ?></span></td></tr>
      </table>
      <?php if ($booking['status'] === 'confirmed'): ?>
      <a class="btn btn-primary" href="<?= BASE_URL ?>ticket.php?booking=<?= urlencode($booking['booking_ref']) ?>">Download E-Ticket</a>
      <?php else: ?>
      <a class="btn btn-warning" href="<?= BASE_URL ?>payment.php?booking=<?= urlencode($booking['booking_ref']) ?>">Complete Payment</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
