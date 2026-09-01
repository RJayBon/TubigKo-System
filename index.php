<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
$CUSTOMERS = load_customers();
$GALLONS = load_gallons();
$DELIVERIES = load_deliveries();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TubigKo Water Refilling Station System</title>
<meta name="description" content="Order purified, mineral and alkaline water gallons, track delivery and manage payments with TubigKo Water Refilling Station System.">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="hero">
  <nav class="hero__nav">
    <a class="brand" href="index.php" style="padding:0">
      <span class="brand__mark">&#128167;</span>
      <span><strong>TubigKo</strong><small>Water Refilling Station</small></span>
    </a>
    <div style="display:flex;gap:.6rem">
      <a class="btn btn--ghost" style="color:#fff;border-color:rgba(255,255,255,.35)" href="login.php">Sign in</a>
      <a class="btn btn--light" href="register.php">Register</a>
    </div>
  </nav>
  <?php foreach (flash_all() as $type => $msg): ?>
    <div class="flash flash--<?= e($type) ?>" style="margin:1rem clamp(1rem,4vw,3rem) 0"><?= e($msg) ?></div>
  <?php endforeach; ?>
  <div class="hero__body">
    <div>
      <h2>Clean water, ordered in seconds and tracked to your door.</h2>
      <p class="lead">TubigKo handles customer accounts, gallon inventory, payments, delivery process and notifications in one simple system for your refilling station.</p>
      <div class="hero__actions">
        <a class="btn btn--light" href="customer/gallons.php">Order gallons</a>
        <a class="btn btn--ghost" style="color:#fff;border-color:rgba(255,255,255,.35)" href="admin/dashboard.php">Admin panel</a>
      </div>
      <div class="hero__stats">
        <div><b><?= count($CUSTOMERS) ?>+</b><span>Registered customers</span></div>
        <div><b><?= count($GALLONS) ?></b><span>Gallon variants</span></div>
        <div><b><?= count(array_filter($DELIVERIES, fn($d) => $d['status']==='Ongoing')) ?></b><span>Deliveries in progress</span></div>
      </div>
    </div>
    <div class="glass">
      <h3>What the system covers</h3>
      <div class="modlist">
        <div><span>1</span>Manage Customer Account &mdash; list, export, print</div>
        <div><span>2</span>Manage Gallons &mdash; list and view per gallon</div>
        <div><span>3</span>Manage Payment &mdash; methods and payment status</div>
        <div><span>4</span>Manage Delivery Process &mdash; ongoing and delivered</div>
        <div><span>5</span>Manage Notification &mdash; send notifications</div>
        <div><span>6</span>Customer &mdash; register, order, pay, track, get notified</div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
