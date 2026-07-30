<?php
require_once __DIR__ . '/includes/auth.php';

$eventId = (int) ($_GET['event'] ?? $_POST['event_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND status = 'active'");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    flash('Sorry, that event was not found.');
    redirect('events.php');
}

$prices = [
    'regular' => (float) $event['regular_price'],
    'vip' => (float) $event['vip_price'],
    'vvip' => (float) $event['vvip_price'],
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticketType = $_POST['ticket_type'] ?? '';
    $quantity = (int) ($_POST['quantity'] ?? 0);

    if (!isset($prices[$ticketType])) { $errors[] = 'Please choose a ticket type.'; }
    if ($quantity < 1 || $quantity > 10) { $errors[] = 'Quantity must be between 1 and 10.'; }

    if (!$errors) {
        $total = $prices[$ticketType] * $quantity;
        $stmt = $pdo->prepare('INSERT INTO bookings (user_id, event_id, ticket_type, quantity, total_amount) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$_SESSION['user_id'], $eventId, $ticketType, $quantity, $total]);
        $bookingId = (int) $pdo->lastInsertId();
        $bookingRef = sprintf('TKT-%s-%05d', date('Y'), $bookingId);
        $pdo->prepare('UPDATE bookings SET booking_ref = ? WHERE id = ?')->execute([$bookingRef, $bookingId]);
        redirect('payment.php?booking=' . urlencode($bookingRef));
    }
}

$pageTitle = 'Book Ticket';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5" style="max-width: 640px">
  <h2 class="mb-1">Book Ticket</h2>
  <p class="text-muted mb-4"><?= e($event['name']) ?> · <?= e(date('D, j M Y', strtotime($event['event_date']))) ?> · <?= e($event['venue']) ?></p>
  <?php foreach ($errors as $error): ?>
  <div class="alert alert-danger py-2"><?= e($error) ?></div>
  <?php endforeach; ?>
  <form method="post" class="card card-body shadow-sm">
    <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
    <label class="form-label fw-bold">Ticket Type</label>
    <?php foreach ($prices as $type => $price): ?>
    <div class="form-check mb-2">
      <input class="form-check-input ticket-type" type="radio" name="ticket_type" id="type-<?= e($type) ?>" value="<?= e($type) ?>" data-price="<?= $price ?>" <?= $type === 'regular' ? 'checked' : '' ?> required>
      <label class="form-check-label d-flex justify-content-between" for="type-<?= e($type) ?>" style="width: 100%">
        <span><?= strtoupper(e($type)) ?></span><span><?= naira($price) ?></span>
      </label>
    </div>
    <?php endforeach; ?>
    <div class="my-3">
      <label class="form-label fw-bold">Number of Tickets</label>
      <input type="number" class="form-control" name="quantity" id="quantity" value="1" min="1" max="10" required>
    </div>
    <div class="alert alert-secondary d-flex justify-content-between mb-3">
      <span>Total</span><strong id="total">—</strong>
    </div>
    <button class="btn btn-primary">Continue to Payment</button>
  </form>
</div>
<script>
function updateTotal() {
  var checked = document.querySelector('.ticket-type:checked');
  var qty = parseInt(document.getElementById('quantity').value || '0', 10);
  var price = checked ? parseFloat(checked.dataset.price) : 0;
  document.getElementById('total').textContent = '₦' + (price * qty).toLocaleString('en-NG', {minimumFractionDigits: 2});
}
document.querySelectorAll('.ticket-type').forEach(function (el) { el.addEventListener('change', updateTotal); });
document.getElementById('quantity').addEventListener('input', updateTotal);
updateTotal();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
