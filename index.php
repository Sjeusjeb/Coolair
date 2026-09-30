<?php
require __DIR__ . '/config/session.php';
require __DIR__ . '/config/supabase.php';

$BASE = '';
$pageTitle = 'Find a Technician';

$serviceId    = isset($_GET['service']) && $_GET['service'] !== '' ? (int)$_GET['service'] : null;
$availability = $_GET['availability'] ?? null;
$q            = trim($_GET['q'] ?? '');

$services = sb_get('services', ['order' => 'name.asc']);

// Narrow down to technician IDs offering the selected service, if any
$matchingTechIds = null;
if ($serviceId) {
    $links = sb_get('technician_services', [
        'select'     => 'technician_id',
        'service_id' => sb_eq($serviceId),
    ]);
    $matchingTechIds = array_column($links, 'technician_id');
    if (empty($matchingTechIds)) {
        $matchingTechIds = [-1]; // no matches -> query nothing
    }
}

$filters = ['order' => 'rating.desc,review_count.desc'];
if ($matchingTechIds !== null) {
    $filters['id'] = 'in.(' . implode(',', $matchingTechIds) . ')';
}
if ($availability && in_array($availability, ['available_now', 'scheduled'], true)) {
    $filters['availability'] = sb_eq($availability);
}
if ($q !== '') {
    $escaped = str_replace(['*', ','], ['', ''], $q); // keep PostgREST's or()/ilike syntax safe
    $filters['or'] = '(name.ilike.*' . $escaped . '*,specialty.ilike.*' . $escaped . '*)';
}

$technicians = sb_get('technicians', $filters);

require __DIR__ . '/includes/header.php';
?>

<div class="hero-banner">
  <div class="hero-eyebrow">CoolAir Services</div>
  <div class="hero-line">Find a trusted aircon technician near you</div>
  <p class="hero-sub">Cleaning, repair, installation &amp; maintenance — booked in minutes.</p>
</div>

<div class="search-float">
  <form method="get" class="searchbar">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4C5F6B" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" name="q" placeholder="Search by name or specialty…" value="<?php echo htmlspecialchars($q); ?>">
    <?php if ($serviceId): ?><input type="hidden" name="service" value="<?php echo (int)$serviceId; ?>"><?php endif; ?>
    <?php if ($availability): ?><input type="hidden" name="availability" value="<?php echo htmlspecialchars($availability); ?>"><?php endif; ?>
    <button type="submit">Search</button>
  </form>

  <div class="chip-row">
    <a class="chip <?php echo !$serviceId ? 'on' : ''; ?>" href="index.php?availability=<?php echo urlencode($availability ?? ''); ?>">All services</a>
    <?php foreach ($services as $s): ?>
      <a class="chip <?php echo $serviceId == $s['id'] ? 'on' : ''; ?>"
         href="index.php?service=<?php echo $s['id']; ?>&availability=<?php echo urlencode($availability ?? ''); ?>">
         <?php echo htmlspecialchars($s['name']); ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="avail-row">
    <a class="avail-pill <?php echo $availability === 'available_now' ? 'on' : ''; ?>"
       href="index.php?service=<?php echo (int)$serviceId; ?>&availability=available_now">Available now</a>
    <a class="avail-pill <?php echo $availability === 'scheduled' ? 'on' : ''; ?>"
       href="index.php?service=<?php echo (int)$serviceId; ?>&availability=scheduled">Scheduled</a>
    <a class="avail-pill <?php echo !$availability ? 'on' : ''; ?>"
       href="index.php?service=<?php echo (int)$serviceId; ?>">Any</a>
  </div>
</div>

<div class="section-label"><?php echo count($technicians); ?> technician<?php echo count($technicians) === 1 ? '' : 's'; ?> found</div>

<div class="tech-grid">
<?php if (empty($technicians)): ?>
  <p class="empty-note">No technicians match those filters right now.</p>
<?php endif; ?>
<?php foreach ($technicians as $t): ?>
  <?php
  $availLabel = ['available_now' => 'Available now', 'scheduled' => 'Scheduled', 'offline' => 'Offline'][$t['availability']] ?? $t['availability'];
  $availColor = ['available_now' => 'var(--green-600)', 'scheduled' => '#8A5A05', 'offline' => 'var(--ink-400)'][$t['availability']] ?? 'var(--ink-400)';
  $availBg = ['available_now' => 'var(--green-100)', 'scheduled' => 'var(--amber-100)', 'offline' => 'var(--line)'][$t['availability']] ?? 'var(--line)';
?>
<a class="tech-card" href="technician.php?id=<?php echo $t['id']; ?>">
    <div class="avatar" style="background:<?php echo htmlspecialchars($t['avatar_color']); ?>"><?php echo htmlspecialchars($t['avatar_initials']); ?></div>
    <div class="tech-info">
      <div class="tech-name-row">
        <span class="tech-name"><?php echo htmlspecialchars($t['name']); ?></span>
        <?php if ($t['is_verified']): ?>
          <svg class="verified" viewBox="0 0 20 20" fill="#1F9D6B"><circle cx="10" cy="10" r="10"/><path d="M6 10.5l2.5 2.5L14 7" stroke="#fff" stroke-width="1.8" fill="none"/></svg>
        <?php endif; ?>
      </div>
      <div class="tech-spec"><?php echo htmlspecialchars($t['specialty']); ?></div>
      <span class="verified-tag" style="background:<?php echo $availBg; ?>;color:<?php echo $availColor; ?>;margin-top:6px;"><?php echo htmlspecialchars($availLabel); ?></span>
      <div class="tech-meta">
        <span><span class="star">★</span> <?php echo number_format($t['rating'], 1); ?> (<?php echo $t['review_count']; ?>)</span>
        <span><?php echo number_format($t['distance_km'], 1); ?> km</span>
      </div>
      <div class="tech-price"><span class="amt">₱<?php echo number_format($t['starting_fee']); ?></span> <span class="lbl">starting fee</span></div>
    </div>
  </a>
<?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
