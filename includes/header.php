<?php require_once __DIR__ . '/../config/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Home') ?> — EventTicket</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark no-print">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>index.php">🎟 EventTicket</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>events.php">Events</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>about.php">About</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>contact.php">Contact</a></li>
        <?php if (!empty($_SESSION['user_id'])): ?>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>logout.php">Logout</a></li>
        <?php else: ?>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>login.php">Login</a></li>
        <li class="nav-item ms-lg-2"><a class="btn btn-warning btn-sm px-3" href="<?= BASE_URL ?>register.php">Register</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<main class="flex-grow-1">
<?php if ($flashMessage = flash()): ?>
<div class="container mt-3 no-print"><div class="alert alert-info alert-dismissible fade show"><?= e($flashMessage) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endif; ?>
