<?php
require_once __DIR__ . '/../config/db.php';
unset($_SESSION['admin_id'], $_SESSION['admin_name']);
flash('Admin logged out.');
redirect('admin/login.php');
