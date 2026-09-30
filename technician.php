<?php
require __DIR__ . '/config/session.php';
require __DIR__ . '/config/supabase.php';

$BASE = '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$tech = sb_get_one('technicians', ['id' => sb_eq($id)]);

if (!$tech) {
    require __DIR__ . '/includes/header.php';
    echo '<p>Technician not found. <a href="index.php">Back to search</a></p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $tech['name'];

$links = sb_get('technician_services', ['select' => 'service_id', 'technician_id' => sb_eq($id)]);
$serviceIds = array_column($links, 'service_id');
$offeredServices = [];
if ($serviceIds) {
    $svcRows = sb_get('services', ['id' => 'in.(' . implode(',', $serviceIds) . ')']);
    $offeredServices = array_column($svcRows, 'name');
}

$reviews = sb_get('reviews', ['technician_id' => sb_eq($id), 'order' => 'created_at.desc']);

$completedJobs = sb_count('bookings', ['technician_id' => sb_eq($id), 'status' => sb_eq('completed')]);

require __DIR__ . '/includes/header.php';
?>

<a href="index.php" class="btn-secondary" style="display:inline-block;margin-bottom:18px;">&larr; Back to search</a>

<div class="profile-hero">
  <div class="avatar avatar-lg" style="background:<?php echo htmlspecialchars($tech['avatar_color']); ?>"><?php echo htmlspecialchars($tech['avatar_initials']); ?></div>
  <div>
    <div class="tech-name" style="font-size:17px;"><?php echo htmlspecialchars($tech['name']); ?></div>
    <div class="tech-spec"><?php echo htmlspecialchars($tech['specialty']); ?> · <?php echo (int)$tech['years_experience']; ?> yrs experience</div>
    <div class="badge-row">
      <?php if ($tech['is_verified']): ?><span class="verified-tag">ID Verified</span><?php endif; ?>
      <?php if ($tech['background_checked']): ?><span class="verified-tag" style="background:var(--amber-100);color:#8A5A05;">Background checked</span><?php endif; ?>
      <?php
        $availLabel = ['available_now' => 'Available now', 'scheduled' => 'Scheduled', 'offline' => 'Offline'][$tech['availability']] ?? $tech['availability'];
        $availColor = ['available_now' => 'var(--green-600)', 'scheduled' => '#8A5A05', 'offline' => 'var(--ink-400)'][$tech['availability']] ?? 'var(--ink-400)';
        $availBg = ['available_now' => 'var(--green-100)', 'scheduled' => 'var(--amber-100)', 'offline' => 'var(--line)'][$tech['availability']] ?? 'var(--line)';
      ?>
      <span class="verified-tag" style="background:<?php echo $availBg; ?>;color:<?php echo $availColor; ?>;"><?php echo htmlspecialchars($availLabel); ?></span>
    </div>
  </div>
</div>

<div class="stat-row">
  <div class="stat"><div class="num"><?php echo number_format($tech['rating'], 1); ?> ★</div><div class="lbl"><?php echo $tech['review_count']; ?> reviews</div></div>
  <div class="stat"><div class="num"><?php echo $completedJobs; ?></div><div class="lbl">jobs done</div></div>
  <div class="stat"><div class="num"><?php echo number_format($tech['distance_km'], 1); ?> km</div><div class="lbl">from you</div></div>
</div>

<div class="section-label" style="margin-top:0;">Services offered</div>
<div class="tag-list">
  <?php foreach ($offeredServices as $s): ?><span class="tag"><?php echo htmlspecialchars($s); ?></span><?php endforeach; ?>
</div>

<div class="section-label">Service area</div>
<p style="font-size:13.5px;color:var(--ink-600);margin-top:-6px;"><?php echo htmlspecialchars($tech['service_area']); ?></p>

<div class="section-label">Reviews</div>
<?php if (empty($reviews)): ?>
  <p class="empty-note">No reviews yet.</p>
<?php endif; ?>
<?php foreach ($reviews as $r): ?>
  <div class="review">
    <div class="review-top"><span><?php echo htmlspecialchars($r['customer_name']); ?></span><span class="star"><?php echo str_repeat('★', (int)$r['rating']); ?></span></div>
    <div class="review-text"><?php echo htmlspecialchars($r['comment']); ?></div>
  </div>
<?php endforeach; ?>

<div class="sticky-cta">
  <a href="book.php?technician_id=<?php echo $tech['id']; ?>" class="btn-primary amber btn-block">Book <?php echo htmlspecialchars(explode(' ', $tech['name'])[0]); ?></a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
