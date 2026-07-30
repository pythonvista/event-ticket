<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$payments = $pdo->query('SELECT p.*, b.booking_ref, u.full_name, e.name AS event_name
                         FROM payments p
                         JOIN bookings b ON b.id = p.booking_id
                         JOIN users u ON u.id = b.user_id
                         JOIN events e ON e.id = b.event_id
                         ORDER BY p.id DESC')->fetchAll();

$pageTitle = 'Payments';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="container my-5">
  <h2 class="mb-4">Payments</h2>
  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>#</th><th>Booking</th><th>Customer</th><th>Event</th><th>Amount</th><th>Reference</th><th>Status</th><th>Paid At</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $payment): ?>
          <tr>
            <td><?= (int) $payment['id'] ?></td>
            <td class="small"><?= e($payment['booking_ref']) ?></td>
            <td><?= e($payment['full_name']) ?></td>
            <td><?= e($payment['event_name']) ?></td>
            <td><?= naira($payment['amount']) ?></td>
            <td class="small text-muted"><?= e($payment['paystack_reference']) ?></td>
            <td><span class="badge bg-<?= $payment['status'] === 'success' ? 'success' : ($payment['status'] === 'failed' ? 'danger' : 'warning text-dark') ?>"><?= e(ucfirst($payment['status'])) ?></span></td>
            <td class="small text-muted"><?= $payment['paid_at'] ? e(date('j M Y H:i', strtotime($payment['paid_at']))) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
