<?php
// Central config: session, database connection, Paystack keys, small helpers.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Environment switch: XAMPP on this machine vs the live host.
$isLocal = in_array($_SERVER['SERVER_NAME'] ?? 'localhost', ['localhost', '127.0.0.1'], true);

if ($isLocal) {
    define('BASE_URL', '/event-ticket/');
    $dbDsn  = 'mysql:host=localhost;dbname=event_ticket_db;charset=utf8mb4';
    $dbUser = 'root';
    $dbPass = '';
} else {
    define('BASE_URL', '/');
    $dbDsn  = 'mysql:host=sa180265xb.pxxldb.pxxl.pro;port=36390;dbname=pxxldb_19fc7793d763cb7;charset=utf8mb4';
    $dbUser = 'pxxluser_19fc7793d750da1';
    $dbPass = '&JF4cvZgXOJ7h4v8CGeCCKxH6JlVcAkH';
}

// Paystack TEST keys. Leave blank to enable the "Demo Pay" fallback button.
define('PAYSTACK_PUBLIC_KEY', '');
define('PAYSTACK_SECRET_KEY', '');

try {
    $pdo = new PDO(
        $dbDsn,
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $ex) {
    exit('<h2 style="font-family:sans-serif;text-align:center;margin-top:20vh">Site under maintenance. Please try again later.</h2>');
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

function naira($amount): string
{
    return '₦' . number_format((float) $amount, 2);
}

function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $current = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $current;
}
