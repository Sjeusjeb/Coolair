<?php
require __DIR__ . '/config/session.php';
require __DIR__ . '/config/supabase.php';
require __DIR__ . '/config/security.php';

$BASE = '';
$pageTitle = 'Log In';
$error = '';

if (isset($_SESSION['customer_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $lockedUntil = login_is_locked('customer', $email);
    if ($lockedUntil) {
        $error = 'Too many failed attempts. Please try again after ' . date('g:i A', strtotime($lockedUntil)) . '.';
    } else {
        $customer = sb_get_one('customers', ['email' => sb_eq($email)]);

        if ($customer && password_verify($password, $customer['password'])) {
            login_reset('customer', $email);
            session_regenerate_id(true);
            $_SESSION['customer_id'] = $customer['id'];
            $_SESSION['customer_name'] = $customer['name'];

            $redirect = $_GET['redirect'] ?? 'index.php';
            header('Location: ' . $redirect);
            exit;
        }
        login_record_failure('customer', $email);
        $error = 'Incorrect email or password.';
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="login-wrap">
  <div class="card">
    <h2 style="margin-bottom:4px;">Log in</h2>
    <p style="font-size:13px;color:var(--ink-600);margin-bottom:20px;">Welcome back to CoolAir.</p>

    <?php if ($error): ?><div class="error-box"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="post">
      <?php echo csrf_field(); ?>
      <label class="field-label" style="margin-top:0;">Email</label>
      <input class="field-input" type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">

      <label class="field-label">Password</label>
      <input class="field-input" type="password" name="password" required>

      <button type="submit" class="btn-primary btn-block" style="margin-top:18px;">Log in</button>
    </form>

    <p style="font-size:12.5px;color:var(--ink-600);margin-top:16px;">
      No account yet? <a href="register.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" style="color:var(--blue-900);font-weight:700;">Sign up</a>
    </p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
