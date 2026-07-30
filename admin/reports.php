<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$validFrom = $from !== '' && strtotime($from) ? $from : null;
$validTo = $to !== '' && strtotime($to) ? $to : null;

$sql = "SELECT e.name, e.event_date,
               COUNT(DISTINCT b.id) AS bookings_count,
               COALESCE(SUM(CASE WHEN b.status = 'confirmed' THEN b.quantity END), 0) AS tickets_sold,
               COALESCE((SELECT SUM(p.amount) FROM payments p JOIN bookings b2 ON b2.id = p.booking_id
                         WHERE b2.event_id = e.id AND p.status = 'success'" .
                         ($validFrom ? " AND p.paid_at >= :pfrom" : '') .
                         ($validTo ? " AND p.paid_at <= :pto" : '') . "), 0) AS revenue
        FROM events e
        LEFT JOIN bookings b ON b.event_id = e.id" .
        ($validFrom ? " AND b.created_at >= :bfrom" : '') .
        ($validTo ? " AND b.created_at <= :bto" : '') . "
        GROUP BY e.id, e.name, e.event_date
        ORDER BY e.event_date ASC";

$stmt = $pdo->prepare($sql);
if ($validFrom) { $stmt->bindValue(':pfrom', $validFrom . ' 00:00:00'); $stmt->bindValue(':bfrom', $validFrom . ' 00:00:00'); }
if ($validTo) { $stmt->bindValue(':pto', $validTo . ' 23:59:59'); $stmt->bindValue(':bto', $validTo . ' 23:59:59'); }
$stmt->execute();
$rows = $stmt->fetchAll();

$grandRevenue = array_sum(array_column($rows, 'revenue'));
$grandBookings = array_sum(array_column($rows, 'bookings_count'));

$pageTitle = 'Reports';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="container my-5">
  <div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <h2 class="mb-0">Reports</h2>
    <button class="btn btn-outline-primary" onclick="window.print()">Print Report</button>
  </div>
  <form method="get" class="row g-2 mb-4 no-print" style="max-width: 520px">
    <div class="col"><label class="form-label small">From</label><input type="date" class="form-control" name="from" value="<?= e($from) ?>"></div>
    <div class="col"><label class="form-label small">To</label><input type="date" class="form-control" name="to" value="<?= e($to) ?>"></div>
    <div class="col-auto d-flex align-items-end"><button class="btn btn-primary">Filter</button></div>
  </form>
  <h4 class="d-none d-print-block mb-3">EventTicket — Bookings &amp; Revenue Report<?= $validFrom || $validTo ? ' (' . e($from ?: '…') . ' to ' . e($to ?: '…') . ')' : '' ?></h4>
  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Event</th><th>Event Date</th><th>Bookings</th><th>Tickets Sold (confirmed)</th><th>Revenue</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= e($row['name']) ?></td>
            <td><?= e(date('j M Y', strtotime($row['event_date']))) ?></td>
            <td><?= (int) $row['bookings_count'] ?></td>
            <td><?= (int) $row['tickets_sold'] ?></td>
            <td><?= naira($row['revenue']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="table-active fw-bold"><td colspan="2">Total</td><td><?= (int) $grandBookings ?></td><td></td><td><?= naira($grandRevenue) ?></td></tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
