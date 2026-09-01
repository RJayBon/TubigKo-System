<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';

// Already signed in? Send them straight to their workspace.
if (is_logged_in()) {
    header('Location: ' . (current_role() === 'admin' ? 'admin/dashboard.php' : 'customer/gallons.php'));
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $identity = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($identity === '' || $password === '') {
            $errors[] = 'Please enter your username/email and password.';
        } else {
            $user = db_one('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1', [$identity, $identity]);

            if (!$user || !password_verify($password, $user['password'])) {
                $errors[] = 'Invalid username/email or password.';
            } elseif ($user['status'] !== 'active') {
                $errors[] = 'This account is inactive. Please contact the station.';
            } else {
                login_user($user);
                flash_set('success', 'Signed in successfully. Welcome back, ' . $user['full_name'] . '!');
                header('Location: ' . ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'customer/gallons.php'));
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in &middot; TubigKo</title>
<meta name="description" content="Sign in to TubigKo Water Refilling Station System as an administrator or customer.">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="auth">
  <div class="auth__card">
    <a class="brand" href="index.php" style="padding:0 0 1.2rem">
      <span class="brand__mark" style="color:#fff">&#128167;</span>
      <span><strong style="color:var(--ink)">TubigKo</strong><small class="muted">Water Refilling Station</small></span>
    </a>
    <h2>Welcome back</h2>
    <p class="muted" style="font-size:.88rem;margin:.35rem 0 1.4rem">Sign in with your username or email address.</p>

    <?php foreach ($errors as $err): ?>
      <div class="flash flash--error"><?= e($err) ?></div>
    <?php endforeach; ?>
    <?php foreach (flash_all() as $type => $msg): ?>
      <div class="flash flash--<?= e($type) ?>"><?= e($msg) ?></div>
    <?php endforeach; ?>

    <form method="post" action="login.php">
      <?= csrf_field() ?>
      <div class="field">
        <label for="username">Username or email</label>
        <input id="username" name="username" type="text" placeholder="admin or admin@tubigko.ph" value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required>
      </div>
      <button class="btn btn--block" type="submit">Sign in</button>
    </form>
    <p class="muted" style="font-size:.85rem;margin-top:1.1rem;text-align:center">
      No account yet? <a href="register.php" style="color:var(--aqua-700);font-weight:600">Register here</a>
    </p>
  </div>
</main>
<script src="assets/js/app.js"></script>
</body>
</html>
