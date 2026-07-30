<?php
require_once __DIR__ . '/includes/auth.php';

$bookingRef = trim($_POST['booking'] ?? $_GET['booking'] ?? '');
$stmt = $pdo->prepare('SELECT * FROM bookings WHERE booking_ref = ? AND user_id = ?');
$stmt->execute([$bookingRef, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    flash('Booking not found.');
    redirect('dashboard.php');
}
if ($booking['status'] === 'confirmed') {
    redirect('confirmation.php?booking=' . urlencode($bookingRef));
}
if ($booking['status'] === 'cancelled') {
    flash('This booking was cancelled and can no longer be paid.');
    redirect('dashboard.php');
}

$isDemo = isset($_POST['demo']) && PAYSTACK_SECRET_KEY === '';

if ($isDemo) {
    $reference = 'DEMO-' . $bookingRef . '-' . time();
    $verified = true;
} else {
    $reference = trim($_GET['reference'] ?? '');
    $verified = false;
    if ($reference !== '' && PAYSTACK_SECRET_KEY !== '') {
        // Never trust the browser: confirm the transaction with Paystack directly.
        $ch = curl_init('https://api.paystack.co/transaction/verify/' . rawurlencode($reference));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . PAYSTACK_SECRET_KEY],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = json_decode((string) curl_exec($ch), true);
        curl_close($ch);
        $verified = $response
            && ($response['data']['status'] ?? '') === 'success'
            && (int) ($response['data']['amount'] ?? 0) === (int) round($booking['total_amount'] * 100)
            && strpos($reference, $booking['booking_ref'] . '-') === 0;
    }
}

if (!$verified) {
    $pdo->prepare("INSERT INTO payments (booking_id, amount, paystack_reference, status) VALUES (?, ?, ?, 'failed')")
        ->execute([$booking['id'], $booking['total_amount'], $reference !== '' ? $reference : 'NONE']);
    flash('Payment could not be verified. Please try again.');
    redirect('payment.php?booking=' . urlencode($bookingRef));
}

$pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?")->execute([$booking['id']]);
$pdo->prepare("INSERT INTO payments (booking_id, amount, paystack_reference, status, paid_at) VALUES (?, ?, ?, 'success', NOW())")
    ->execute([$booking['id'], $booking['total_amount'], $reference]);

redirect('confirmation.php?booking=' . urlencode($bookingRef));
