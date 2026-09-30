<?php
require __DIR__ . '/config/session.php';
require __DIR__ . '/config/supabase.php';
require __DIR__ . '/config/security.php';

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}
csrf_verify();
$customerId = $_SESSION['customer_id'];
$tab = in_array($_POST['tab'] ?? '', ['personal', 'account', 'activity'], true) ? $_POST['tab'] : 'personal';

$action = $_POST['action'] ?? '';

if ($action === 'update_profile') {
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '') {
        header('Location: profile.php?err=' . urlencode('Full name is required.') . '&tab=personal');
        exit;
    }

    sb_update('customers', ['id' => sb_eq($customerId)], [
        'name' => $name, 'phone' => $phone, 'address' => $address,
    ]);
    $_SESSION['customer_name'] = $name;

    header('Location: profile.php?msg=' . urlencode('Profile updated.') . '&tab=personal');
    exit;
}

if ($action === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $customer = sb_get_one('customers', ['id' => sb_eq($customerId)]);

    if (!$customer || !password_verify($current, $customer['password'])) {
        header('Location: profile.php?err=' . urlencode('Current password is incorrect.') . '&tab=account');
        exit;
    }
    if (strlen($new) < 8) {
        header('Location: profile.php?err=' . urlencode('New password must be at least 8 characters.') . '&tab=account');
        exit;
    }
    if ($new !== $confirm) {
        header('Location: profile.php?err=' . urlencode('New password and confirmation do not match.') . '&tab=account');
        exit;
    }

    sb_update('customers', ['id' => sb_eq($customerId)], [
        'password' => password_hash($new, PASSWORD_DEFAULT),
    ]);

    header('Location: profile.php?msg=' . urlencode('Password updated.') . '&tab=account');
    exit;
}

if ($action === 'update_photo') {
    $data = $_POST['avatar_data'] ?? '';

    if ($data === '' || strpos($data, 'data:image/') !== 0) {
        header('Location: profile.php?err=' . urlencode('Please choose a valid image.') . '&tab=' . $tab);
        exit;
    }
    if (strlen($data) > 700000) { // ~500KB decoded, well above what the client-side resize produces
        header('Location: profile.php?err=' . urlencode('Image is too large. Please try a smaller photo.') . '&tab=' . $tab);
        exit;
    }

    sb_update('customers', ['id' => sb_eq($customerId)], ['avatar_photo' => $data]);

    header('Location: profile.php?msg=' . urlencode('Profile photo updated.') . '&tab=' . $tab);
    exit;
}

header('Location: profile.php');
exit;
