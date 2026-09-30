<?php
require __DIR__ . '/config/session.php';
require __DIR__ . '/config/supabase.php';
require __DIR__ . '/config/security.php';

$BASE = '';
$pageTitle = 'My Profile';

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php?redirect=profile.php');
    exit;
}
$customerId = $_SESSION['customer_id'];

$customer = sb_get_one('customers', ['id' => sb_eq($customerId)]);
if (!$customer) {
    header('Location: logout.php');
    exit;
}

$totalBookings     = sb_count('bookings', ['customer_id' => sb_eq($customerId)]);
$completedBookings = sb_count('bookings', ['customer_id' => sb_eq($customerId), 'status' => sb_eq('completed')]);

$activity = sb_get('bookings', [
    'select'      => '*,technicians(name),services(name)',
    'customer_id' => sb_eq($customerId),
    'order'       => 'created_at.desc',
    'limit'       => 8,
]);
foreach ($activity as &$b) {
    $b['tech_name']    = $b['technicians']['name'] ?? '';
    $b['service_name'] = $b['services']['name'] ?? '';
}
unset($b);

$statusLabel = [
    'pending'     => 'Request sent',   'accepted'  => 'Accepted',   'declined'    => 'Declined',
    'on_the_way'  => 'On the way',     'arrived'   => 'Arrived',    'in_progress' => 'In progress',
    'completed'   => 'Completed',      'cancelled' => 'Cancelled',
];

$activeTab = in_array($_GET['tab'] ?? '', ['personal', 'account', 'activity'], true) ? $_GET['tab'] : 'personal';
$initials  = strtoupper(substr(trim($customer['name']), 0, 1) . substr(trim(strrchr($customer['name'], ' ') ?: ''), 1, 1));
if (trim($initials) === '') { $initials = 'C'; }

require __DIR__ . '/includes/header.php';
?>

<div class="acct-head">
  <h2>My profile</h2>
  <p class="sub">View and manage your account information</p>
</div>

<?php if (!empty($_GET['msg'])): ?><div class="success-box"><?php echo htmlspecialchars($_GET['msg']); ?></div><?php endif; ?>
<?php if (!empty($_GET['err'])): ?><div class="error-box"><?php echo htmlspecialchars($_GET['err']); ?></div><?php endif; ?>

<div class="card acct-cover">
  <div class="acct-banner">
    <div class="acct-avatar-wrap">
      <div class="acct-avatar" style="background:var(--blue-700);">
        <?php if (!empty($customer['avatar_photo'])): ?>
          <img src="<?php echo htmlspecialchars($customer['avatar_photo']); ?>" alt="">
        <?php else: ?>
          <?php echo htmlspecialchars($initials); ?>
        <?php endif; ?>
      </div>
      <form method="post" action="profile_actions.php" id="photoForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="update_photo">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
        <input type="hidden" name="avatar_data" id="avatarData">
        <label class="acct-cam-btn" for="avatarInput" title="Change photo">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 8h3l2-3h6l2 3h3v12H4z"/><circle cx="12" cy="14" r="3.5"/></svg>
        </label>
        <input type="file" id="avatarInput" accept="image/*" style="display:none;">
      </form>
    </div>
    <div class="acct-banner-info">
      <div class="acct-name"><?php echo htmlspecialchars($customer['name']); ?></div>
      <div class="acct-role">Customer</div>
      <span class="verified-tag" style="background:var(--green-100);color:var(--green-600);">Active</span>
      <div class="acct-id">Account ID: CUST-<?php echo str_pad($customer['id'], 4, '0', STR_PAD_LEFT); ?></div>
    </div>
  </div>
</div>

<div class="acct-tabs">
  <button type="button" class="acct-tab <?php echo $activeTab === 'personal' ? 'on' : ''; ?>" data-tab="personal">Personal Information</button>
  <button type="button" class="acct-tab <?php echo $activeTab === 'account' ? 'on' : ''; ?>" data-tab="account">Account Information</button>
  <button type="button" class="acct-tab <?php echo $activeTab === 'activity' ? 'on' : ''; ?>" data-tab="activity">Activity Log</button>
</div>

<!-- Personal Information -->
<div class="acct-panel <?php echo $activeTab === 'personal' ? '' : 'hidden'; ?>" id="panel-personal">
  <div class="card">
    <div class="card-head">
      <h3>Personal Information</h3>
      <button type="button" class="btn-secondary acct-edit-btn" onclick="toggleEdit('form-personal')">Edit Profile</button>
    </div>
    <form method="post" action="profile_actions.php" class="acct-form" id="form-personal">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="update_profile">

      <label class="field-label">Full name</label>
      <input class="field-input" name="name" value="<?php echo htmlspecialchars($customer['name']); ?>" readonly required>

      <label class="field-label">Email address</label>
      <input class="field-input" value="<?php echo htmlspecialchars($customer['email']); ?>" readonly disabled>

      <label class="field-label">Contact number</label>
      <input class="field-input" name="phone" value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>" readonly placeholder="09XX XXX XXXX">

      <label class="field-label">Address</label>
      <input class="field-input" name="address" value="<?php echo htmlspecialchars($customer['address'] ?? ''); ?>" readonly placeholder="No address on file">

      <div class="acct-form-actions">
        <button type="submit" class="btn-primary">Save changes</button>
        <button type="button" class="btn-secondary" onclick="cancelEdit('form-personal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Account Information -->
<div class="acct-panel <?php echo $activeTab === 'account' ? '' : 'hidden'; ?>" id="panel-account">
  <div class="card">
    <div class="card-head"><h3>Account Information</h3></div>
    <div class="info-row"><span class="k">Username</span><span class="v"><?php echo htmlspecialchars($customer['email']); ?></span></div>
    <div class="info-row"><span class="k">Password</span><span class="v">••••••••</span></div>
    <div class="info-row"><span class="k">Account role</span><span class="v">Customer</span></div>
    <div class="info-row"><span class="k">Status</span><span class="v" style="color:var(--green-600);">Active</span></div>
    <div class="info-row"><span class="k">Date created</span><span class="v"><?php echo htmlspecialchars(date('F j, Y', strtotime($customer['created_at']))); ?></span></div>
  </div>

  <div class="card">
    <div class="card-head">
      <h3>Change Password</h3>
      <button type="button" class="btn-secondary acct-edit-btn" onclick="toggleEdit('form-password')">Change Password</button>
    </div>
    <form method="post" action="profile_actions.php" class="acct-form" id="form-password">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="change_password">

      <label class="field-label">Current password</label>
      <input class="field-input" type="password" name="current_password" readonly>

      <label class="field-label">New password</label>
      <input class="field-input" type="password" name="new_password" readonly>

      <label class="field-label">Confirm new password</label>
      <input class="field-input" type="password" name="confirm_password" readonly>

      <div class="acct-form-actions">
        <button type="submit" class="btn-primary">Update password</button>
        <button type="button" class="btn-secondary" onclick="cancelEdit('form-password')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Activity Log -->
<div class="acct-panel <?php echo $activeTab === 'activity' ? '' : 'hidden'; ?>" id="panel-activity">
  <div class="card">
    <div class="card-head"><h3>Activity Log</h3></div>
    <?php if (empty($activity)): ?>
      <p class="empty-note">No activity yet — your recent bookings will show up here.</p>
    <?php endif; ?>
    <?php foreach ($activity as $b): ?>
      <a href="track.php?ref=<?php echo urlencode($b['booking_ref']); ?>" class="req-row" style="display:flex;text-decoration:none;">
        <div class="req-body">
          <div class="req-name"><?php echo htmlspecialchars($b['service_name']); ?> — <?php echo htmlspecialchars($b['tech_name']); ?></div>
          <div class="req-meta"><?php echo htmlspecialchars($b['schedule_date']); ?>, <?php echo htmlspecialchars($b['schedule_time']); ?> · Ref: <?php echo htmlspecialchars($b['booking_ref']); ?></div>
        </div>
        <div style="margin-left:auto;text-align:right;"><span class="tag"><?php echo htmlspecialchars($statusLabel[$b['status']] ?? $b['status']); ?></span></div>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="stat-row" style="border:none;padding:0;margin:0 4px;">
    <div class="stat"><div class="num"><?php echo $totalBookings; ?></div><div class="lbl">total bookings</div></div>
    <div class="stat"><div class="num"><?php echo $completedBookings; ?></div><div class="lbl">completed</div></div>
  </div>
</div>

<div style="display:flex;gap:10px;margin-top:6px;flex-wrap:wrap;">
  <a href="logout.php" class="btn-secondary">Logout</a>
</div>

<script>
function showTab(name){
  document.querySelectorAll('.acct-tab').forEach(function(t){ t.classList.toggle('on', t.dataset.tab === name); });
  document.querySelectorAll('.acct-panel').forEach(function(p){ p.classList.toggle('hidden', p.id !== 'panel-' + name); });
  var url = new URL(window.location);
  url.searchParams.set('tab', name);
  url.searchParams.delete('msg'); url.searchParams.delete('err');
  window.history.replaceState({}, '', url);
}
document.querySelectorAll('.acct-tab').forEach(function(t){
  t.addEventListener('click', function(){ showTab(t.dataset.tab); });
});

function toggleEdit(formId){
  var form = document.getElementById(formId);
  form.classList.add('editing');
  form.querySelectorAll('input[name]:not([type=hidden]):not([disabled])').forEach(function(i){ i.readOnly = false; });
  var first = form.querySelector('input[name]:not([type=hidden]):not([disabled])');
  if (first) first.focus();
}
function cancelEdit(formId){
  var form = document.getElementById(formId);
  form.reset();
  form.classList.remove('editing');
  form.querySelectorAll('input[name]:not([type=hidden]):not([disabled])').forEach(function(i){ i.readOnly = true; });
}

// Photo upload: resize/compress client-side, then auto-submit
document.getElementById('avatarInput').addEventListener('change', function(e){
  var file = e.target.files[0];
  if (!file) return;
  var img = new Image();
  var reader = new FileReader();
  reader.onload = function(ev){
    img.onload = function(){
      var size = 320;
      var canvas = document.createElement('canvas');
      canvas.width = size; canvas.height = size;
      var ctx = canvas.getContext('2d');
      var s = Math.max(size / img.width, size / img.height);
      var w = img.width * s, h = img.height * s;
      ctx.drawImage(img, (size - w) / 2, (size - h) / 2, w, h);
      document.getElementById('avatarData').value = canvas.toDataURL('image/jpeg', 0.85);
      document.getElementById('photoForm').submit();
    };
    img.src = ev.target.result;
  };
  reader.readAsDataURL(file);
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
