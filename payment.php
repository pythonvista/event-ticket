<?php
require_once __DIR__ . '/includes/auth.php';

$bookingRef = trim($_GET['booking'] ?? '');
$stmt = $pdo->prepare('SELECT b.*, e.name AS event_name, u.email AS user_email
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
if ($booking['status'] === 'confirmed') {
    redirect('confirmation.php?booking=' . urlencode($bookingRef));
}

$unitPrice = $booking['total_amount'] / max(1, $booking['quantity']);
$pageTitle = 'Payment';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5" style="max-width: 560px">
  <h2 class="mb-4">Payment</h2>
  <div class="card shadow-sm">
    <div class="card-body">
      <table class="table mb-4">
        <tr><th>Event</th><td><?= e($booking['event_name']) ?></td></tr>
        <tr><th>Ticket Type</th><td><?= strtoupper(e($booking['ticket_type'])) ?></td></tr>
        <tr><th>Ticket Price</th><td><?= naira($unitPrice) ?></td></tr>
        <tr><th>Quantity</th><td><?= (int) $booking['quantity'] ?></td></tr>
        <tr class="table-active"><th>Total Amount</th><td class="fw-bold"><?= naira($booking['total_amount']) ?></td></tr>
      </table>
      <?php if (PAYSTACK_PUBLIC_KEY !== ''): ?>
      <button class="btn btn-success w-100" onclick="payNow()">Pay Now</button>
      <p class="small text-muted mt-2 mb-0">Test card: 4084 0840 8408 4081 · any future expiry · CVV 408</p>
      <?php else: ?>
      <form method="post" action="<?= BASE_URL ?>verify_payment.php">
        <input type="hidden" name="booking" value="<?= e($booking['booking_ref']) ?>">
        <input type="hidden" name="demo" value="1">
        <button class="btn btn-success w-100">Pay Now (Demo)</button>
      </form>
      <p class="small text-muted mt-2 mb-0">Paystack keys are not configured — this demo payment always succeeds.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php if (PAYSTACK_PUBLIC_KEY !== ''): ?>
<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
function payNow() {
  var handler = PaystackPop.setup({
    key: '<?= PAYSTACK_PUBLIC_KEY ?>',
    email: '<?= e($booking['user_email']) ?>',
    amount: <?= (int) round($booking['total_amount'] * 100) ?>, // kobo
    currency: 'NGN',
    ref: '<?= e($booking['booking_ref']) ?>-<?= time() ?>',
    callback: function (response) {
      window.location = '<?= BASE_URL ?>verify_payment.php?booking=<?= urlencode($booking['booking_ref']) ?>&reference=' + encodeURIComponent(response.reference);
    },
    onClose: function () {}
  });
  handler.openIframe();
}
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
