<?php
require __DIR__ . '/config/session.php';
require __DIR__ . '/config/supabase.php';
require __DIR__ . '/config/security.php';

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
csrf_verify();

$customerId = $_SESSION['customer_id'];
$bookingId = (int)($_POST['booking_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');
$ref = $_POST['ref'] ?? '';

// Only the customer who made this booking can rate it, and only once it's completed
$booking = sb_get_one('bookings', [
    'id'          => sb_eq($bookingId),
    'customer_id' => sb_eq($customerId),
    'status'      => sb_eq('completed'),
]);

if ($booking && $rating >= 1 && $rating <= 5) {
    $existing = sb_get_one('reviews', ['booking_id' => sb_eq($bookingId)]);

    if (!$existing) {
        sb_insert('reviews', [
            'technician_id' => $booking['technician_id'],
            'booking_id'    => $bookingId,
            'customer_name' => $_SESSION['customer_name'],
            'rating'        => $rating,
            'comment'       => $comment,
        ]);

        // Recompute the technician's aggregate rating + review count from all their reviews
        sb_rpc('recompute_technician_rating', ['p_technician_id' => $booking['technician_id']]);
    }
}

header('Location: track.php?ref=' . urlencode($ref));
exit;
