<?php
require_once __DIR__ . '/config/db.php';

$stmt = $pdo->query("SELECT * FROM events WHERE status = 'active' AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 1");
$featured = $stmt->fetch() ?: null;

$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero text-center">
  <div class="container">
    <h1 class="display-4">Book Your Event Tickets Online</h1>
    <p class="lead mb-4">Welcome to EventTicket — skip the queue and get your e-ticket in minutes.</p>
    <?php if ($featured): ?>
    <p class="fs-5">Featured: <strong><?= e($featured['name']) ?></strong> · <?= e(date('j M Y', strtotime($featured['event_date']))) ?> · <?= e($featured['venue']) ?></p>
    <a class="btn btn-warning btn-lg me-2" href="<?= BASE_URL ?>book.php?event=<?= (int) $featured['id'] ?>">Book Ticket</a>
    <?php endif; ?>
    <a class="btn btn-outline-light btn-lg" href="<?= BASE_URL ?>register.php">Register</a>
  </div>
</section>
<?php if ($featured): ?>
<section class="container my-5">
  <div class="row align-items-center g-4">
    <div class="col-md-6">
      <?php $imagePath = __DIR__ . '/assets/img/' . $featured['image']; ?>
      <?php if ($featured['image'] !== '' && is_file($imagePath)): ?>
      <img src="<?= BASE_URL ?>assets/img/<?= e($featured['image']) ?>" alt="<?= e($featured['name']) ?>" class="img-fluid rounded shadow">
      <?php else: ?>
      <div class="d-flex align-items-center justify-content-center bg-secondary text-white rounded shadow" style="height:300px"><?= e($featured['name']) ?></div>
      <?php endif; ?>
    </div>
    <div class="col-md-6">
      <h2><?= e($featured['name']) ?></h2>
      <p><?= e($featured['description']) ?></p>
      <ul class="list-unstyled">
        <li><strong>Date:</strong> <?= e(date('l, j F Y', strtotime($featured['event_date']))) ?></li>
        <li><strong>Time:</strong> <?= e(date('g:i A', strtotime($featured['event_time']))) ?></li>
        <li><strong>Venue:</strong> <?= e($featured['venue']) ?></li>
        <li><strong>Tickets from:</strong> <?= naira($featured['regular_price']) ?></li>
      </ul>
      <a class="btn btn-primary" href="<?= BASE_URL ?>events.php">View All Events</a>
    </div>
  </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
