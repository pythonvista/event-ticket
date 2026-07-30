<?php
require_once __DIR__ . '/../includes/admin_auth.php';

// Manual payment confirmation (e.g. bank transfer confirmed offline).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking_id'])) {
    $id = (int) $_POST['confirm_booking_id'];
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND status = 'pending'");
    $stmt->execute([$id]);
    if ($booking = $stmt->fetch()) {
        $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?")->execute([$id]);
        $pdo->prepare("INSERT INTO payments (booking_id, amount, paystack_reference, status, paid_at) VALUES (?, ?, ?, 'success', NOW())")
            ->execute([$id, $booking['total_amount'], 'MANUAL-' . $booking['booking_ref']]);
        flash('Payment confirmed for ' . $booking['booking_ref'] . '.');
    }
    redirect('admin/bookings.php');
}

$statusFilter = $_GET['status'] ?? '';
$sql = 'SELECT b.*, u.full_name, e.name AS event_name
        FROM bookings b JOIN users u ON u.id = b.user_id JOIN events e ON e.id = b.event_id';
$params = [];
if (in_array($statusFilter, ['pending', 'confirmed', 'cancelled'], true)) {
    $sql .= ' WHERE b.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY b.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$pageTitle = 'Bookings';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="container my-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Bookings</h2>
    <div class="btn-group">
      <a class="btn btn-sm btn-outline-secondary <?= $statusFilter === '' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/bookings.php">All</a>
      <a class="btn btn-sm btn-outline-secondary <?= $statusFilter === 'pending' ? 'active' : '' ?>" href="?status=pending">Pending</a>
      <a class="btn btn-sm btn-outline-secondary <?= $statusFilter === 'confirmed' ? 'active' : '' ?>" href="?status=confirmed">Confirmed</a>
      <a class="btn btn-sm btn-outline-secondary <?= $statusFilter === 'cancelled' ? 'active' : '' ?>" href="?status=cancelled">Cancelled</a>
    </div>
  </div>
  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Ref</th><th>Customer</th><th>Event</th><th>Tickets</th><th>Amount</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($bookings as $booking): ?>
          <tr>
            <td class="small"><?= e($booking['booking_ref']) ?></td>
            <td><?= e($booking['full_name']) ?></td>
            <td><?= e($booking['event_name']) ?></td>
            <td><?= strtoupper(e($booking['ticket_type'])) ?> × <?= (int) $booking['quantity'] ?></td>
            <td><?= naira($booking['total_amount']) ?></td>
            <td><span class="badge bg-<?= $booking['status'] === 'confirmed' ? 'success' : ($booking['status'] === 'pending' ? 'warning text-dark' : 'secondary') ?>"><?= e(ucfirst($booking['status'])) ?></span></td>
            <td class="small text-muted"><?= e(date('j M Y H:i', strtotime($booking['created_at']))) ?></td>
            <td class="text-nowrap">
              <?php if ($booking['status'] === 'pending'): ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Confirm payment for this booking?');">
                <input type="hidden" name="confirm_booking_id" value="<?= (int) $booking['id'] ?>">
                <button class="btn btn-sm btn-success">Confirm Payment</button>
              </form>
              <?php elseif ($booking['status'] === 'confirmed'): ?>
              <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>ticket.php?booking=<?= urlencode($booking['booking_ref']) ?>">Ticket</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
