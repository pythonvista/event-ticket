<?php
require_once __DIR__ . '/config/db.php';
unset($_SESSION['user_id'], $_SESSION['user_name']);
flash('You have been logged out.');
redirect('index.php');
