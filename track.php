<?php
require __DIR__ . '/config/session.php';
require __DIR__ . '/config/supabase.php';
require __DIR__ . '/config/security.php';

$BASE = '';
$pageTitle = 'Track Service';
$ref = trim($_GET['ref'] ?? ($_POST['ref'] ?? ''));
$booking = null;

if ($ref !== '') {
    $booking = sb_get_one('bookings', [
        'select'      => '*,technicians(name,avatar_initials,avatar_color,specialty),services(name)',
        'booking_ref' => sb_eq($ref),
    ]);
    if ($booking) {
        $booking['tech_name']       = $booking['technicians']['name'] ?? '';
        $booking['avatar_initials'] = $booking['technicians']['avatar_initials'] ?? '';
        $booking['avatar_color']    = $booking['technicians']['avatar_color'] ?? '';
        $booking['specialty']       = $booking['technicians']['specialty'] ?? '';
        $booking['service_name']    = $booking['services']['name'] ?? '';
    }
}

$existingReview = null;
if ($booking && $booking['status'] === 'completed') {
    $existingReview = sb_get_one('reviews', ['booking_id' => sb_eq($booking['id'])]);
}
$canRate = $booking
    && $booking['status'] === 'completed'
    && !$existingReview
    && isset($_SESSION['customer_id'])
    && $_SESSION['customer_id'] == $booking['customer_id'];

$logTimes = [];
if ($booking) {
    // Equivalent of "SELECT status, MIN(changed_at) GROUP BY status": walk the log
    // in ascending order and keep only the first time we see each status.
    $logRows = sb_get('booking_status_log', [
        'select'     => 'status,changed_at',
        'booking_id' => sb_eq($booking['id']),
        'order'      => 'changed_at.asc',
    ]);
    foreach ($logRows as $row) {
        if (!isset($logTimes[$row['status']])) {
            $logTimes[$row['status']] = $row['changed_at'];
        }
    }
}

$stepOrder = [
    'pending'     => 'Request sent',
    'accepted'    => 'Technician accepted',
    'on_the_way'  => 'On the way',
    'arrived'     => 'Arrived',
    'in_progress' => 'Service in progress',
    'completed'   => 'Completed',
];
$currentIndex = $booking ? array_search($booking['status'], array_keys($stepOrder), true) : false;

require __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:520px;margin:0 auto;">
  <form method="get" style="display:flex;gap:8px;margin-bottom:<?php echo $booking ? '22px' : '0'; ?>;">
    <input class="field-input" name="ref" placeholder="Enter booking reference, e.g. CA-4F9B2A" value="<?php echo htmlspecialchars($ref); ?>">
    <button type="submit" class="btn-primary">Track</button>
  </form>

  <?php if ($ref !== '' && !$booking): ?>
    <div class="error-box">No booking found with that reference.</div>
  <?php endif; ?>

  <?php if ($booking): ?>
    <div class="ref-box">
      <div class="ref"><?php echo htmlspecialchars($booking['booking_ref']); ?></div>
      <div class="lbl"><?php echo htmlspecialchars($booking['service_name']); ?> · <?php echo htmlspecialchars($booking['schedule_date']); ?>, <?php echo htmlspecialchars($booking['schedule_time']); ?></div>
    </div>

    <?php if (in_array($booking['status'], ['declined', 'cancelled'], true)): ?>
      <div class="status-banner <?php echo $booking['status']; ?>">
        <?php echo $booking['status'] === 'declined' ? 'This booking was declined by the technician.' : 'This booking was cancelled.'; ?>
      </div>
      <a href="index.php" class="btn-secondary">Find another technician</a>
    <?php else: ?>
      <div class="track-tech">
        <div class="avatar" style="width:44px;height:44px;font-size:14px;background:<?php echo htmlspecialchars($booking['avatar_color']); ?>"><?php echo htmlspecialchars($booking['avatar_initials']); ?></div>
        <div>
          <div class="tech-name" style="font-size:13.5px;"><?php echo htmlspecialchars($booking['tech_name']); ?></div>
          <div class="tech-spec" style="margin-top:0;"><?php echo htmlspecialchars($booking['specialty']); ?></div>
        </div>
      </div>

      <div class="steps">
        <?php $i = 0; foreach ($stepOrder as $statusKey => $label): ?>
          <?php
            $cls = '';
            if ($i < $currentIndex) $cls = 'done';
            elseif ($i === $currentIndex) $cls = ($statusKey === 'completed') ? 'done' : 'current';
            $time = $logTimes[$statusKey] ?? null;
          ?>
          <div class="step <?php echo $cls; ?>">
            <div class="step-title"><?php echo $label; ?></div>
            <div class="step-time"><?php echo $time ? date('g:i A, M j', strtotime($time)) : '—'; ?></div>
          </div>
        <?php $i++; endforeach; ?>
      </div>

      <?php if ($booking['status'] === 'completed'): ?>
        <?php if ($existingReview): ?>
          <div class="card" style="background:var(--blue-100);border:none;margin-top:20px;margin-bottom:0;padding:18px;">
            <div class="section-label" style="margin:0 0 8px;">Your rating</div>
            <div class="star" style="font-size:16px;"><?php echo str_repeat('★', (int)$existingReview['rating']) . str_repeat('☆', 5 - (int)$existingReview['rating']); ?></div>
            <?php if ($existingReview['comment']): ?>
              <p style="font-size:13px;color:var(--ink-600);margin-top:8px;"><?php echo htmlspecialchars($existingReview['comment']); ?></p>
            <?php endif; ?>
          </div>
        <?php elseif ($canRate): ?>
          <div class="card" style="border:none;background:var(--blue-100);margin-top:20px;margin-bottom:0;padding:18px;">
            <div class="section-label" style="margin:0 0 10px;">Rate <?php echo htmlspecialchars(explode(' ', $booking['tech_name'])[0]); ?>'s service</div>
            <form method="post" action="submit_rating.php">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
              <input type="hidden" name="ref" value="<?php echo htmlspecialchars($booking['booking_ref']); ?>">
              <div style="display:flex;gap:6px;margin-bottom:12px;" id="starPicker">
                <?php for ($n = 1; $n <= 5; $n++): ?>
                  <label style="cursor:pointer;font-size:26px;color:var(--ink-400);" class="star-choice" data-val="<?php echo $n; ?>">
                    <input type="radio" name="rating" value="<?php echo $n; ?>" style="display:none;" <?php echo $n === 5 ? 'checked' : ''; ?>>★
                  </label>
                <?php endfor; ?>
              </div>
              <textarea class="field-textarea" name="comment" placeholder="Optional — how was the service?"></textarea>
              <button type="submit" class="btn-primary btn-block" style="margin-top:12px;">Submit rating</button>
            </form>
          </div>
          <script>
            document.querySelectorAll('#starPicker .star-choice').forEach(function(label){
              label.addEventListener('click', function(){
                var val = parseInt(this.dataset.val, 10);
                document.querySelectorAll('#starPicker .star-choice').forEach(function(l){
                  l.style.color = parseInt(l.dataset.val, 10) <= val ? '#F5A623' : '#8296A1';
                });
              });
            });
            // Default highlight to match the pre-checked 5th star
            document.querySelectorAll('#starPicker .star-choice').forEach(function(l){ l.style.color = '#F5A623'; });
          </script>
        <?php endif; ?>
      <?php endif; ?>

      <p style="font-size:12px;color:var(--ink-400);margin-top:20px;">Bookmark this page or save your reference — refresh anytime to see the latest status.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
