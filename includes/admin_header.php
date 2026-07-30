<?php require_once __DIR__ . '/../config/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Admin') ?> — EventTicket Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary no-print">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>admin/index.php">🛠 EventTicket Admin</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/index.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/users.php">Manage Users</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/events.php">Manage Events</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/bookings.php">Bookings</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/payments.php">Payments</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/reports.php">Reports</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
<main class="flex-grow-1">
<?php if ($flashMessage = flash()): ?>
<div class="container mt-3 no-print"><div class="alert alert-info alert-dismissible fade show"><?= e($flashMessage) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endif; ?>
