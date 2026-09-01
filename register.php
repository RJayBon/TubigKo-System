<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';

if (is_logged_in()) {
    header('Location: ' . (current_role() === 'admin' ? 'admin/dashboard.php' : 'customer/gallons.php'));
    exit;
}

$errors = [];
$old = [];

function make_username(string $email): string
{
    $base = strtolower(preg_replace('/[^a-z0-9._-]/i', '', strstr($email, '@', true) ?: 'user'));
    $base = $base !== '' ? $base : 'user';
    $username = $base;
    $i = 1;
    while (db_one('SELECT id FROM users WHERE username = ?', [$username])) {
        $username = $base . $i;
        $i++;
    }
    return $username;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please reload the page and try again.';
    } else {
        $old = $_POST;

        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $address   = trim($_POST['address'] ?? '');
        $barangay  = trim($_POST['barangay'] ?? '');
        $landmark  = trim($_POST['landmark'] ?? '');
        $password  = (string)($_POST['password'] ?? '');
        $confirm   = (string)($_POST['confirm_password'] ?? '');
        // Registration is always for a customer account — a submitted
        // "role" field, if any, is ignored so nobody can self-register as admin.

        if ($firstName === '' || $lastName === '' || $email === '' || $phone === '' || $address === '') {
            $errors[] = 'Please fill in all required fields.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (!$errors && db_one('SELECT id FROM users WHERE email = ?', [$email])) {
            $errors[] = 'An account with that email already exists.';
        }

        if (!$errors) {
            $fullName = trim($firstName . ' ' . $lastName);
            $username = make_username($email);
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $ok = db_exec(
                'INSERT INTO users (full_name, username, email, password, phone, address, barangay, landmark, role, status) VALUES (?,?,?,?,?,?,?,?,\'customer\',\'active\')',
                [$fullName, $username, $email, $hash, $phone, $address, $barangay, $landmark],
                $dbError
            );

            if ($ok) {
                flash_set('success', 'Registration successful! Your username is "' . $username . '". You can now sign in.');
                header('Location: login.php');
                exit;
            }
            $errors[] = $dbError ?: 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register &middot; TubigKo</title>
<meta name="description" content="Create your TubigKo customer account: fill up your information and submit to start ordering water gallons.">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="auth">
  <div class="auth__card" style="width:min(640px,100%)">
    <a class="brand" href="index.php" style="padding:0 0 1rem">
      <span class="brand__mark" style="color:#fff">&#128167;</span>
      <span><strong style="color:var(--ink)">TubigKo</strong><small class="muted">Create your account</small></span>
    </a>
    <div class="stepper">
      <div class="step is-active"><b>1</b> Fill Up Info</div>
      <div class="step"><b>2</b> Submit</div>
      <div class="step"><b>3</b> Start ordering</div>
    </div>

    <?php foreach ($errors as $err): ?>
      <div class="flash flash--error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" action="register.php">
      <?= csrf_field() ?>
      <div class="form-row">
        <div class="field"><label for="fname">First name</label><input id="fname" name="first_name" value="<?= e($old['first_name'] ?? '') ?>" required></div>
        <div class="field"><label for="lname">Last name</label><input id="lname" name="last_name" value="<?= e($old['last_name'] ?? '') ?>" required></div>
      </div>
      <div class="form-row">
        <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" value="<?= e($old['email'] ?? '') ?>" required></div>
        <div class="field"><label for="phone">Mobile number</label><input id="phone" name="phone" type="tel" placeholder="0917-000-0000" value="<?= e($old['phone'] ?? '') ?>" required></div>
      </div>
      <div class="field"><label for="address">Complete delivery address</label><input id="address" name="address" value="<?= e($old['address'] ?? '') ?>" required></div>
      <div class="form-row">
        <div class="field"><label for="brgy">Barangay</label>
          <select id="brgy" name="barangay">
            <?php foreach (['Brgy. San Roque','Brgy. Poblacion','Brgy. Malaya','Brgy. Bagong Silang'] as $b): ?>
              <option <?= ($old['barangay'] ?? '') === $b ? 'selected' : '' ?>><?= e($b) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label for="landmark">Landmark (optional)</label><input id="landmark" name="landmark" value="<?= e($old['landmark'] ?? '') ?>"></div>
      </div>
      <div class="form-row">
        <div class="field"><label for="pass">Password</label><input id="pass" name="password" type="password" minlength="8" required></div>
        <div class="field"><label for="pass2">Confirm password</label><input id="pass2" name="confirm_password" type="password" minlength="8" required></div>
      </div>
      <button class="btn btn--block" type="submit">Submit registration</button>
    </form>
    <p class="muted" style="font-size:.85rem;margin-top:1rem;text-align:center">
      Already registered? <a href="login.php" style="color:var(--aqua-700);font-weight:600">Sign in</a>
    </p>
  </div>
</main>
<script src="assets/js/app.js"></script>
</body>
</html>
