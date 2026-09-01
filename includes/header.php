<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/data.php';
$BASE = $BASE ?? '.';
$PAGE_TITLE = $PAGE_TITLE ?? 'Dashboard';
$ROLE = $ROLE ?? 'admin';

$__me = current_user();
$__initials = 'U';
if ($__me && trim($__me['full_name']) !== '') {
    $__parts = preg_split('/\s+/', trim($__me['full_name']));
    $__initials = strtoupper(substr($__parts[0], 0, 1) . substr(end($__parts), 0, 1));
}
$__unread = $__me ? (int)(db_one(
    'SELECT COUNT(*) AS c FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0',
    [$__me['id']]
)['c'] ?? 0) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($PAGE_TITLE) ?> &middot; TubigKo</title>
<meta name="description" content="TubigKo Water Refilling Station System - manage customers, gallons, payments, deliveries and notifications.">
<link rel="stylesheet" href="<?= $BASE ?>/assets/css/style.css">
</head>
<body class="app">
<div class="layout">
<?php include __DIR__ . '/sidebar.php'; ?>
  <main class="main">
    <header class="topbar">
      <button class="icon-btn only-mobile" id="menuToggle" aria-label="Toggle menu">&#9776;</button>
      <div>
        <h1 class="topbar__title"><?= htmlspecialchars($PAGE_TITLE) ?></h1>
        <p class="topbar__sub"><?= $ROLE === 'admin' ? 'Administrator workspace' : 'Customer workspace' ?><?= $__me ? ' &middot; ' . e($__me['full_name']) : '' ?></p>
      </div>
      <div class="topbar__right">
        <a class="icon-btn" href="<?= $BASE ?>/<?= $ROLE ?>/notifications.php" aria-label="Notifications" style="position:relative">
          &#128276;<?php if ($__unread > 0): ?><span class="notif-count"><?= $__unread > 9 ? '9+' : $__unread ?></span><?php endif; ?>
        </a>
        <div class="avatar" aria-hidden="true"><?= e($__initials) ?></div>
        <a class="btn btn--ghost" href="<?= $BASE ?>/logout.php">Sign out</a>
      </div>
    </header>
    <div class="content">
      <?php foreach (flash_all() as $type => $msg): ?>
        <div class="flash flash--<?= e($type) ?>"><?= e($msg) ?></div>
      <?php endforeach; ?>
