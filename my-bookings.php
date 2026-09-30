<?php
require __DIR__ . '/config/session.php';
require __DIR__ . '/config/supabase.php';

$BASE = '';
$pageTitle = 'My Bookings';

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php?redirect=my-bookings.php');
    exit;
}
$customerId = $_SESSION['customer_id'];

$bookings = sb_get('bookings', [
    'select'      => '*,technicians(name,avatar_initials,avatar_color),services(name)',
    'customer_id' => sb_eq($customerId),
    'order'       => 'created_at.desc',
]);
// Flatten the embedded technician/service names to match the old column aliases
foreach ($bookings as &$b) {
    $b['tech_name']       = $b['technicians']['name'] ?? '';
    $b['avatar_initials'] = $b['technicians']['avatar_initials'] ?? '';
    $b['avatar_color']    = $b['technicians']['avatar_color'] ?? '';
    $b['service_name']    = $b['services']['name'] ?? '';
}
unset($b);

$statusLabel = [
    'pending'     => 'Request sent',
    'accepted'    => 'Accepted',
    'declined'    => 'Declined',
    'on_the_way'  => 'On the way',
    'arrived'     => 'Arrived',
    'in_progress' => 'In progress',
    'completed'   => 'Completed',
    'cancelled'   => 'Cancelled',
];

require __DIR__ . '/includes/header.php';
?>

<h2 style="margin-bottom:4px;">My bookings</h2>
<p style="font-size:13px;color:var(--ink-600);margin-bottom:22px;">Everything you've booked with CoolAir technicians.</p>

<?php if (empty($bookings)): ?>
  <div class="card">
    <p class="empty-note">You haven't booked a technician yet.</p>
    <a href="index.php" class="btn-primary" style="display:inline-block;margin-top:8px;">Find a technician</a>
  </div>
<?php endif; ?>

<?php foreach ($bookings as $b): ?>
  <a href="track.php?ref=<?php echo urlencode($b['booking_ref']); ?>" class="req-row card" style="display:flex;text-decoration:none;">
    <div class="avatar" style="background:<?php echo htmlspecialchars($b['avatar_color']); ?>"><?php echo htmlspecialchars($b['avatar_initials']); ?></div>
    <div class="req-body">
      <div class="req-name"><?php echo htmlspecialchars($b['tech_name']); ?> — <?php echo htmlspecialchars($b['service_name']); ?></div>
      <div class="req-meta"><?php echo htmlspecialchars($b['schedule_date']); ?>, <?php echo htmlspecialchars($b['schedule_time']); ?> · Ref: <?php echo htmlspecialchars($b['booking_ref']); ?></div>
    </div>
    <div style="margin-left:auto;text-align:right;">
      <span class="tag"><?php echo htmlspecialchars($statusLabel[$b['status']] ?? $b['status']); ?></span>
    </div>
  </a>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
