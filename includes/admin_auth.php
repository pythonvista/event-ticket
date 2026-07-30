<?php
// Guard: only the logged-in administrator may view this page.
require_once __DIR__ . '/../config/db.php';
if (empty($_SESSION['admin_id'])) {
    flash('Please log in as administrator.');
    redirect('admin/login.php');
}
