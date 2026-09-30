<?php
require __DIR__ . '/config/session.php';
require __DIR__ . '/config/supabase.php';
require __DIR__ . '/config/security.php';
require_once __DIR__ . '/includes/panel_icons.php';

// Icon + short blurb per service type, keyed by service name.
$serviceMeta = [
    'Cleaning'     => ['icon' => 'sparkles',  'blurb' => 'Deep clean of coils, filters & body'],
    'Repair'       => ['icon' => 'wrench',    'blurb' => 'Fix leaks, noise, or cooling issues'],
    'Installation' => ['icon' => 'box',       'blurb' => 'New unit mounting & setup'],
    'Maintenance'  => ['icon' => 'gear',      'blurb' => 'Routine check-up & tune-up'],
    'Freon Refill' => ['icon' => 'snowflake', 'blurb' => 'Recharge refrigerant / gas'],
];
function service_meta_for(string $name, array $meta): array {
    return $meta[$name] ?? ['icon' => 'check-circle', 'blurb' => ''];
}

$BASE = '';
$pageTitle = 'Book a Technician';
$errors = [];

if (!isset($_SESSION['customer_id'])) {
    $redirectTarget = 'book.php?technician_id=' . (int)($_GET['technician_id'] ?? 0);
    header('Location: login.php?redirect=' . urlencode($redirectTarget));
    exit;
}
$customerId = $_SESSION['customer_id'];

$customer = sb_get_one('customers', ['id' => sb_eq($customerId)]);

$technicianId = isset($_GET['technician_id']) ? (int)$_GET['technician_id'] : (int)($_POST['technician_id'] ?? 0);

$tech = sb_get_one('technicians', ['id' => sb_eq($technicianId)]);

if (!$tech) {
    require __DIR__ . '/includes/header.php';
    echo '<p>Technician not found. <a href="index.php">Back to search</a></p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$links = sb_get('technician_services', ['select' => 'service_id', 'technician_id' => sb_eq($technicianId)]);
$offeredServiceIds = array_column($links, 'service_id');
$offeredServices = [];
if ($offeredServiceIds) {
    $offeredServices = sb_get('services', [
        'id'     => 'in.(' . implode(',', $offeredServiceIds) . ')',
        'select' => 'id,name',
    ]);
}

// Defaults for re-showing the form after a validation error (prefill from the account)
$form = [
    'service_id'          => $_POST['service_id'] ?? '',
    'customer_name'       => $_POST['customer_name'] ?? $customer['name'],
    'customer_phone'      => $_POST['customer_phone'] ?? $customer['phone'],
    'address'             => $_POST['address'] ?? '',
    'schedule_date'       => $_POST['schedule_date'] ?? date('Y-m-d'),
    'schedule_time'       => $_POST['schedule_time'] ?? '',
    'problem_description' => $_POST['problem_description'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $validServiceIds = array_column($offeredServices, 'id');

    if (!in_array((int)$form['service_id'], $validServiceIds, true)) {
        $errors[] = 'Please select a valid service.';
    }
    if (trim($form['customer_name']) === '') {
        $errors[] = 'Please enter your name.';
    }
    if (trim($form['address']) === '') {
        $errors[] = 'Please enter the service address.';
    }
    if (trim($form['schedule_date']) === '' || trim($form['schedule_time']) === '') {
        $errors[] = 'Please choose a date and time.';
    }

    if (empty($errors)) {
        $estimatedMin = (float)$tech['starting_fee'];
        $estimatedMax = round($estimatedMin * 1.4 / 10) * 10;
        $bookingRef = 'CA-' . strtoupper(substr(uniqid('', true), -6));

        $booking = sb_insert('bookings', [
            'booking_ref'          => $bookingRef,
            'customer_id'          => $customerId,
            'technician_id'        => $technicianId,
            'service_id'           => (int)$form['service_id'],
            'customer_name'        => trim($form['customer_name']),
            'customer_phone'       => trim($form['customer_phone']),
            'address'              => trim($form['address']),
            'schedule_date'        => $form['schedule_date'],
            'schedule_time'        => $form['schedule_time'],
            'problem_description'  => trim($form['problem_description']),
            'estimated_min'        => $estimatedMin,
            'estimated_max'        => $estimatedMax,
            'status'               => 'pending',
        ]);
        $bookingId = $booking['id'];

        sb_insert('booking_status_log', ['booking_id' => $bookingId, 'status' => 'pending']);

        header('Location: track.php?ref=' . urlencode($bookingRef));
        exit;
    }
}

require __DIR__ . '/includes/header.php';
?>

<a href="technician.php?id=<?php echo $tech['id']; ?>" class="btn-secondary" style="display:inline-block;margin-bottom:18px;">&larr; Back to profile</a>

<div class="card">
  <div style="display:flex;gap:12px;align-items:center;margin-bottom:6px;">
    <div class="avatar" style="background:<?php echo htmlspecialchars($tech['avatar_color']); ?>"><?php echo htmlspecialchars($tech['avatar_initials']); ?></div>
    <div>
      <div class="tech-name"><?php echo htmlspecialchars($tech['name']); ?></div>
      <div class="tech-spec"><?php echo htmlspecialchars($tech['specialty']); ?></div>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="error-box"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
  <?php endif; ?>

  <form method="post">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="technician_id" value="<?php echo $tech['id']; ?>">

    <label class="field-label">Select type of service</label>
    <div class="service-pick service-pick-grid">
      <?php foreach ($offeredServices as $s): $meta = service_meta_for($s['name'], $serviceMeta); ?>
        <label class="service-opt service-opt-card <?php echo (string)$form['service_id'] === (string)$s['id'] ? 'on' : ''; ?>">
          <input type="radio" name="service_id" value="<?php echo $s['id']; ?>"
                 onclick="document.querySelectorAll('.service-opt-card').forEach(o=>o.classList.remove('on'));this.parentElement.classList.add('on');"
                 <?php echo (string)$form['service_id'] === (string)$s['id'] ? 'checked' : ''; ?>>
          <span class="service-opt-icon"><?php echo panel_icon($meta['icon']); ?></span>
          <span class="service-opt-text">
            <span class="service-opt-name"><?php echo htmlspecialchars($s['name']); ?></span>
            <?php if ($meta['blurb']): ?><span class="service-opt-blurb"><?php echo htmlspecialchars($meta['blurb']); ?></span><?php endif; ?>
          </span>
        </label>
      <?php endforeach; ?>
    </div>

    <label class="field-label">Your name</label>
    <input class="field-input" name="customer_name" value="<?php echo htmlspecialchars($form['customer_name']); ?>" required>

    <label class="field-label">Phone number</label>
    <input class="field-input" name="customer_phone" value="<?php echo htmlspecialchars($form['customer_phone']); ?>" placeholder="09XX XXX XXXX">

    <label class="field-label">Service address</label>
    <textarea class="field-textarea" name="address" required><?php echo htmlspecialchars($form['address']); ?></textarea>

    <label class="field-label">Preferred date</label>
    <input class="field-input" type="date" name="schedule_date" value="<?php echo htmlspecialchars($form['schedule_date']); ?>" required>

    <label class="field-label">Preferred time</label>
    <input class="field-input" name="schedule_time" placeholder="e.g. 2:00 PM – 4:00 PM" value="<?php echo htmlspecialchars($form['schedule_time']); ?>" required>

    <label class="field-label">Describe the problem</label>
    <textarea class="field-textarea" name="problem_description" placeholder="e.g. Aircon is running but not cooling."><?php echo htmlspecialchars($form['problem_description']); ?></textarea>

    <div class="est-row">
      <div><div class="lbl">Estimated fee</div><div class="amt">₱<?php echo number_format($tech['starting_fee']); ?> – ₱<?php echo number_format(round($tech['starting_fee'] * 1.4 / 10) * 10); ?></div></div>
      <div class="lbl">Final price after inspection</div>
    </div>

    <button type="submit" class="btn-primary btn-block" style="margin-top:16px;">Confirm booking</button>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
