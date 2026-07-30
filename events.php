<?php
require_once __DIR__ . '/config/db.php';

$events = $pdo->query("SELECT * FROM events WHERE status = 'active' ORDER BY event_date ASC")->fetchAll();

$pageTitle = 'Events';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5">
  <h2 class="mb-4">Upcoming Events</h2>
  <?php if (!$events): ?>
  <div class="alert alert-secondary">No events available right now. Please check back soon.</div>
  <?php endif; ?>
  <div class="row g-4">
    <?php foreach ($events as $event): ?>
    <div class="col-sm-6 col-lg-4">
      <div class="card event-card h-100 shadow-sm">
        <?php $imagePath = __DIR__ . '/assets/img/' . $event['image']; ?>
        <?php if ($event['image'] !== '' && is_file($imagePath)): ?>
        <img src="<?= BASE_URL ?>assets/img/<?= e($event['image']) ?>" class="card-img-top" alt="<?= e($event['name']) ?>">
        <?php else: ?>
        <div class="card-img-top d-flex align-items-center justify-content-center bg-secondary text-white" style="height:200px"><?= e($event['name']) ?></div>
        <?php endif; ?>
        <div class="card-body d-flex flex-column">
          <h5 class="card-title"><?= e($event['name']) ?></h5>
          <ul class="list-unstyled small text-muted mb-3">
            <li><strong>Date:</strong> <?= e(date('D, j M Y', strtotime($event['event_date']))) ?></li>
            <li><strong>Time:</strong> <?= e(date('g:i A', strtotime($event['event_time']))) ?></li>
            <li><strong>Venue:</strong> <?= e($event['venue']) ?></li>
            <li><strong>Price:</strong> from <?= naira($event['regular_price']) ?></li>
          </ul>
          <a class="btn btn-primary mt-auto" href="<?= BASE_URL ?>book.php?event=<?= (int) $event['id'] ?>">Book Now</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
