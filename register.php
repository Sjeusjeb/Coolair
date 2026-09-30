<?php
require __DIR__ . '/config/session.php';
require __DIR__ . '/config/supabase.php';
require __DIR__ . '/config/security.php';

$BASE = '';
$pageTitle = 'Sign Up';
$error = '';

if (isset($_SESSION['customer_id'])) {
    header('Location: index.php');
    exit;
}

$form = [
    'name'  => $_POST['name'] ?? '',
    'email' => $_POST['email'] ?? '',
    'phone' => $_POST['phone'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name = trim($form['name']);
    $email = trim($form['email']);
    $phone = trim($form['phone']);
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Please fill in your name, email, and password.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $existing = sb_get_one('customers', ['email' => sb_eq($email)]);
        if ($existing) {
            $error = 'An account with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $customer = sb_insert('customers', [
                'name' => $name, 'email' => $email, 'password' => $hash, 'phone' => $phone,
            ]);
            $customerId = $customer['id'];

            session_regenerate_id(true);
            $_SESSION['customer_id'] = $customerId;
            $_SESSION['customer_name'] = $name;
            $redirect = $_GET['redirect'] ?? 'index.php';
            header('Location: ' . $redirect);
            exit;
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="login-wrap">
  <div class="card">
    <h2 style="margin-bottom:4px;">Create an account</h2>
    <p style="font-size:13px;color:var(--ink-600);margin-bottom:20px;">Sign up to book technicians and track your service requests.</p>

    <?php if ($error): ?><div class="error-box"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="post">
      <?php echo csrf_field(); ?>
      <label class="field-label" style="margin-top:0;">Full name</label>
      <input class="field-input" name="name" required value="<?php echo htmlspecialchars($form['name']); ?>">

      <label class="field-label">Email</label>
      <input class="field-input" type="email" name="email" required value="<?php echo htmlspecialchars($form['email']); ?>">

      <label class="field-label">Phone number</label>
      <input class="field-input" name="phone" placeholder="09XX XXX XXXX" value="<?php echo htmlspecialchars($form['phone']); ?>">

      <label class="field-label">Password</label>
      <input class="field-input" type="password" name="password" required>

      <label class="field-label">Confirm password</label>
      <input class="field-input" type="password" name="confirm_password" required>

      <button type="submit" class="btn-primary btn-block" style="margin-top:18px;">Create account</button>
    </form>

    <p style="font-size:12.5px;color:var(--ink-600);margin-top:16px;">
      Already have an account? <a href="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" style="color:var(--blue-900);font-weight:700;">Log in</a>
    </p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
