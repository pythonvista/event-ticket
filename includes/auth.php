<?php
// Guard: only logged-in users may view this page.
require_once __DIR__ . '/../config/db.php';
if (empty($_SESSION['user_id'])) {
    flash('Please log in to continue.');
    redirect('login.php');
}
