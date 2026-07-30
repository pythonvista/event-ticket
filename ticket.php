<?php
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])) {
    flash('Please log in to continue.');
    redirect('login.php');
}

$bookingRef = trim($_GET['booking'] ?? '');
$sql = 'SELECT b.*, e.name AS event_name, e.event_date, e.event_time, e.venue, u.full_name
        FROM bookings b
        JOIN events e ON e.id = b.event_id
        JOIN users u ON u.id = b.user_id
        WHERE b.booking_ref = ?';
$params = [$bookingRef];
if (empty($_SESSION['admin_id'])) {
    $sql .= ' AND b.user_id = ?';
    $params[] = $_SESSION['user_id'];
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$booking = $stmt->fetch();

if (!$booking) {
    flash('Ticket not found.');
    redirect(empty($_SESSION['admin_id']) ? 'dashboard.php' : 'admin/bookings.php');
}
if ($booking['status'] !== 'confirmed') {
    flash('This booking has not been paid for yet.');
    redirect('confirmation.php?booking=' . urlencode($bookingRef));
}

$pageTitle = 'E-Ticket ' . $booking['booking_ref'];
require __DIR__ . '/includes/header.php';
?>
<div class="ticket shadow">
  <div class="ticket-head text-center py-3 px-4">
    <h3 class="mb-0">🎟 <?= e($booking['event_name']) ?></h3>
    <small>E-TICKET</small>
  </div>
  <div class="p-4">
    <div class="row">
      <div class="col-8">
        <table class="table table-sm table-borderless mb-0">
          <tr><th>Ticket Number</th><td><?= e($booking['booking_ref']) ?></td></tr>
          <tr><th>Customer Name</th><td><?= e($booking['full_name']) ?></td></tr>
          <tr><th>Ticket Type</th><td><?= strtoupper(e($booking['ticket_type'])) ?> × <?= (int) $booking['quantity'] ?></td></tr>
          <tr><th>Date</th><td><?= e(date('l, j F Y', strtotime($booking['event_date']))) ?></td></tr>
          <tr><th>Time</th><td><?= e(date('g:i A', strtotime($booking['event_time']))) ?></td></tr>
          <tr><th>Venue</th><td><?= e($booking['venue']) ?></td></tr>
        </table>
      </div>
      <div class="col-4 text-center">
        <div id="qrcode" class="d-inline-block"></div>
        <div class="small text-muted mt-2">Scan at entry</div>
      </div>
    </div>
  </div>
</div>
<div class="text-center mb-5 no-print">
  <button class="btn btn-primary" onclick="window.print()">Print Ticket</button>
  <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>dashboard.php">Back to Dashboard</a>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('qrcode'), { text: '<?= e($booking['booking_ref']) ?>', width: 140, height: 140 });
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
