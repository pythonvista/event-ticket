<?php
require_once __DIR__ . '/config/db.php';
$sent = ($_SERVER['REQUEST_METHOD'] === 'POST');
$pageTitle = 'Contact';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5" style="max-width: 560px">
  <h2>Contact Us</h2>
  <?php if ($sent): ?>
  <div class="alert alert-success">Thank you — your message has been received. We will get back to you soon.</div>
  <?php endif; ?>
  <form method="post" class="card card-body shadow-sm">
    <div class="mb-3"><label class="form-label">Your Name</label><input class="form-control" name="name" required></div>
    <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required></div>
    <div class="mb-3"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="4" required></textarea></div>
    <button class="btn btn-primary">Send Message</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
